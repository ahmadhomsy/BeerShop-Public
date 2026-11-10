import requests
import time
import sys
import subprocess
from concurrent.futures import ThreadPoolExecutor, as_completed

BASE_URL = "http://localhost:8080/api/v1"
LOGIN_URL = f"{BASE_URL}/auth/login"
ORDERS_URL = f"{BASE_URL}/orders"


def get_jwt_token(email="aa@gmail.com", password="0000"):
    res = requests.post(LOGIN_URL, json={"email": email, "password": password})
    if res.status_code == 200:
        return res.json()["data"]["token"]
    raise Exception(f"Failed to obtain token: {res.text}")


def test_concurrent_orders_race():
    print("=" * 75)
    print("  HTTP RACE CONDITION TEST: 10 CONCURRENT POST /api/v1/orders")
    print("  Target: Simultaneous requests for the LAST remaining piece of Product #1")
    print("=" * 75)

    token = get_jwt_token()
    print("[Auth] JWT Bearer Token obtained successfully.")

    # Reset stock for Product 1 in PostgreSQL inventory container to exactly 1 piece
    subprocess.run([
        "docker", "compose", "exec", "-T", "db_inventory",
        "psql", "-U", "inventory_user", "-d", "inventory_db",
        "-c", "UPDATE inventory SET total_stock = 1, reserved_stock = 0 WHERE product_id = 1;"
    ], check=True)
    print("[Setup] Product #1 stock reset in PostgreSQL to: total_stock = 1, reserved_stock = 0 (Available = 1).")

    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
        "Accept": "application/json"
    }

    order_payload = {
        "name": "Concurrent Buyer",
        "email": "cc@gmail.com",
        "address": "Street 10, Apartment 5",
        "phone": "0911223344",
        "note": "Competing for last piece",
        "items": [
            {
                "product_id": 1,
                "quantity": 1
            }
        ]
    }

    num_requests = 10
    start_barrier = time.time() + 0.3

    def send_order_request(idx):
        sleep_time = start_barrier - time.time()
        if sleep_time > 0:
            time.sleep(sleep_time)
        try:
            r = requests.post(ORDERS_URL, json=order_payload, headers=headers, timeout=5.0)
            return (idx, r.status_code, r.json())
        except Exception as e:
            return (idx, 500, {"error": str(e)})

    print(f"\n[Action] Sending {num_requests} simultaneous HTTP POST requests...")
    results = []
    with ThreadPoolExecutor(max_workers=num_requests) as executor:
        futures = [executor.submit(send_order_request, i + 1) for i in range(num_requests)]
        for f in as_completed(futures):
            results.append(f.result())

    results.sort(key=lambda x: x[0])

    created_orders = [r for r in results if r[1] == 201]
    rejected_orders = [r for r in results if r[1] != 201]

    print("\n" + "-" * 75)
    print("  HTTP RESPONSES SUMMARY:")
    print("-" * 75)
    for r in results:
        status_label = "[201 CREATED - ORDER PLACED]" if r[1] == 201 else f"[{r[1]} REJECTED - OUT OF STOCK]"
        msg = r[2].get("message", "")
        print(f"  Request #{r[0]:02d} -> Status: {r[1]} | {status_label} | Msg: {msg}")

    print("-" * 75)
    print(f"Total 201 Created (Success) : {len(created_orders)} (Expected: EXACTLY 1)")
    print(f"Total Rejected              : {len(rejected_orders)} (Expected: EXACTLY {num_requests - 1})")
    print("-" * 75)

    assert len(created_orders) == 1, f"RACE CONDITION FAILURE: {len(created_orders)} orders created instead of 1!"
    assert len(rejected_orders) == num_requests - 1, f"Expected {num_requests - 1} rejected orders!"

    print("\n[PostgreSQL Verification] Querying inventory table in db_inventory for Product #1:")
    verify = subprocess.run([
        "docker", "compose", "exec", "-T", "db_inventory",
        "psql", "-U", "inventory_user", "-d", "inventory_db",
        "-c", "SELECT product_id, total_stock, reserved_stock FROM inventory WHERE product_id = 1;"
    ], capture_output=True, text=True)
    print(verify.stdout)

    print(">>> HTTP RACE CONDITION PROOF: PASSED! Exactly ONE 201 Created and the remaining 9 were rejected.")
    return True


if __name__ == "__main__":
    test_concurrent_orders_race()
