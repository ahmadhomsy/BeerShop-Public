import subprocess
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
import requests

BASE_URL = "http://localhost:8080/api/v1"
LOGIN_URL = f"{BASE_URL}/auth/login"
ORDERS_URL = f"{BASE_URL}/orders"


def get_customer_jwt(email="cc@gmail.com", password="0000"):
    """
    Authenticate using documented customer demo account.
    """
    res = requests.post(LOGIN_URL, json={"email": email, "password": password}, timeout=5.0)
    assert res.status_code == 200, f"Login failed ({res.status_code}): {res.text}"
    body = res.json()
    assert body.get("success") is True, f"Login response success is not True: {body}"
    token = body.get("data", {}).get("token")
    assert token, "Token missing in login response."
    return token


def reset_inventory_stock(product_id=1, total_stock=1, reserved_stock=0):
    """
    Reset inventory table in PostgreSQL to exact test quantities.
    """
    sql = (
        f"DELETE FROM reservation_items WHERE product_id = {product_id}; "
        f"UPDATE inventory SET total_stock = {total_stock}, reserved_stock = {reserved_stock} "
        f"WHERE product_id = {product_id};"
    )
    res = subprocess.run([
        "docker", "compose", "exec", "-T", "db_inventory",
        "psql", "-U", "inventory_user", "-d", "inventory_db",
        "-c", sql
    ], capture_output=True, text=True, check=True)
    return res.stdout


def fetch_inventory_state(product_id=1):
    """
    Read actual total_stock and reserved_stock directly from PostgreSQL.
    """
    query = f"SELECT total_stock, reserved_stock FROM inventory WHERE product_id = {product_id};"
    res = subprocess.run([
        "docker", "compose", "exec", "-T", "db_inventory",
        "psql", "-U", "inventory_user", "-d", "inventory_db",
        "-t", "-A", "-F", ",", "-c", query
    ], capture_output=True, text=True, check=True)

    raw = res.stdout.strip()
    assert raw, f"Empty output from PostgreSQL query: {query}"
    parts = raw.split(",")
    assert len(parts) == 2, f"Unexpected CSV format from PostgreSQL: '{raw}'"
    total_stock = int(parts[0].strip())
    reserved_stock = int(parts[1].strip())
    available_stock = total_stock - reserved_stock
    return total_stock, reserved_stock, available_stock


def test_concurrent_orders_race():
    print("=" * 80)
    print("  HTTP RACE CONDITION TEST: 10 CONCURRENT POST /api/v1/orders")
    print("  Target: Simultaneous requests for the LAST remaining piece of Product #1")
    print("=" * 80)

    # 1. Acquire JWT for documented customer demo account
    token = get_customer_jwt()
    print("[1] Customer JWT Bearer Token acquired successfully.")

    # 2. Reset database state
    reset_inventory_stock(product_id=1, total_stock=1, reserved_stock=0)
    init_total, init_reserved, init_avail = fetch_inventory_state(1)
    print(f"[2] Inventory initialized: total_stock={init_total}, reserved_stock={init_reserved}, available_stock={init_avail}")
    assert init_total == 1 and init_reserved == 0 and init_avail == 1, "Initial stock state mismatch!"

    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
        "Accept": "application/json"
    }

    order_payload = {
        "name": "Concurrent Customer",
        "email": "customer@example.com",
        "address": "Damascus High Street 10",
        "phone": "0911223344",
        "note": "Testing last piece concurrency",
        "items": [
            {
                "product_id": 1,
                "quantity": 1
            }
        ]
    }

    num_requests = 10
    start_barrier = time.time() + 0.35

    def send_order_request(idx):
        sleep_time = start_barrier - time.time()
        if sleep_time > 0:
            time.sleep(sleep_time)
        try:
            r = requests.post(ORDERS_URL, json=order_payload, headers=headers, timeout=10.0)
            return (idx, r.status_code, r.json())
        except Exception as e:
            return (idx, 500, {"error": str(e), "message": "Exception during request"})

    print(f"\n[3] Launching {num_requests} concurrent HTTP requests at exact barrier time...")
    results = []
    with ThreadPoolExecutor(max_workers=num_requests) as executor:
        futures = [executor.submit(send_order_request, i + 1) for i in range(num_requests)]
        for f in as_completed(futures):
            results.append(f.result())

    results.sort(key=lambda x: x[0])

    # 4. Analyze HTTP status codes
    status_codes = [r[1] for r in results]
    count_201 = status_codes.count(201)
    count_422 = status_codes.count(422)
    unexpected = [code for code in status_codes if code not in (201, 422)]

    print("\n" + "-" * 80)
    print("  HTTP RESPONSES SUMMARY:")
    print("-" * 80)
    for r in results:
        status_label = "[201 CREATED]" if r[1] == 201 else f"[{r[1]} REJECTED]"
        msg = r[2].get("message", "")
        print(f"  Req #{r[0]:02d} -> HTTP {r[1]} | {status_label:<15} | Message: {msg}")

    print("-" * 80)
    print(f"Total 201 Created (Success)       : {count_201} (Required: EXACTLY 1)")
    print(f"Total 422 Unprocessable (Rejected): {count_422} (Required: EXACTLY 9)")
    print(f"Unexpected Status Codes           : {len(unexpected)} (Required: 0)")
    print("-" * 80)

    # 5. Assert HTTP Status Code Distribution
    assert len(unexpected) == 0, f"Unexpected HTTP status codes encountered: {unexpected}"
    assert count_201 == 1, f"Expected EXACTLY 1 HTTP 201 response, got {count_201}!"
    assert count_422 == 9, f"Expected EXACTLY 9 HTTP 422 responses, got {count_422}!"

    # 6. Read and assert final PostgreSQL database state
    print("\n[4] Querying and asserting PostgreSQL inventory state...")
    final_total, final_reserved, final_avail = fetch_inventory_state(1)
    print(f"  Final State -> total_stock: {final_total}, reserved_stock: {final_reserved}, available_stock: {final_avail}")

    assert final_total == 1, f"PostgreSQL total_stock must be 1, got {final_total}"
    assert final_reserved == 1, f"PostgreSQL reserved_stock must be 1, got {final_reserved}"
    assert final_avail == 0, f"PostgreSQL available_stock must be 0, got {final_avail}"

    print("\n>>> HTTP RACE CONDITION PROOF: PASSED! Exactly 1 order created, 9 rejected with 422, zero overselling.")
    return True


if __name__ == "__main__":
    test_concurrent_orders_race()
