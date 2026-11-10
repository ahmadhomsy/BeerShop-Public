import requests
import json

BASE_URL = "http://localhost:8080/api/v1"
LOGIN_URL = f"{BASE_URL}/auth/login"
ORDERS_URL = f"{BASE_URL}/orders"

def test_service_down():
    print("=" * 70)
    print("  TEST: 503 SERVICE UNAVAILABLE PROOF (INVENTORY SERVICE DOWN)")
    print("=" * 70)

    # 1. Login to get JWT Token
    print("[1] Logging in as customer...")
    res = requests.post(LOGIN_URL, json={"email": "aa@gmail.com", "password": "0000"})
    assert res.status_code == 200, f"Login failed: {res.text}"
    token = res.json()["data"]["token"]
    print("    JWT Token acquired.")

    # 2. Attempt to create order while inventory_service is stopped
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
        "Accept": "application/json"
    }

    payload = {
        "name": "Customer During Outage",
        "email": "cc@gmail.com",
        "address": "Damascus Street 5",
        "phone": "0911223344",
        "items": [
            {"product_id": 1, "quantity": 1}
        ]
    }

    print("[2] Sending POST /api/v1/orders (waiting for 2s deadline)...")
    response = requests.post(ORDERS_URL, json=payload, headers=headers, timeout=10.0)

    print("\n" + "-" * 70)
    print(f"HTTP Status Code : {response.status_code} (Expected: 503)")
    print(f"Response Body    : {response.text}")
    print("-" * 70)

    assert response.status_code == 503, f"Expected 503, got {response.status_code}"
    body = response.json()
    assert body["success"] is False
    assert "Inventory service temporarily unavailable" in body["message"], f"Unexpected message: {body['message']}"

    print("\n>>> 503 SERVICE UNAVAILABLE PROOF: PASSED! Clean, sanitized 503 returned.")

if __name__ == "__main__":
    test_service_down()
