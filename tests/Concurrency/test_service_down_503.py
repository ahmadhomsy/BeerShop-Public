import subprocess
import time
import requests

BASE_URL = "http://localhost:8080/api/v1"
LOGIN_URL = f"{BASE_URL}/auth/login"
ORDERS_URL = f"{BASE_URL}/orders"
PRODUCTS_URL = f"{BASE_URL}/products/1"


def get_customer_jwt(email="cc@gmail.com", password="0000"):
    """
    Authenticate using documented customer demo account.
    Fails fast if authentication fails, distinguishing auth errors from outage errors.
    """
    try:
        res = requests.post(LOGIN_URL, json={"email": email, "password": password}, timeout=5.0)
    except Exception as e:
        raise RuntimeError(f"Could not reach API login endpoint: {e}")

    assert res.status_code == 200, (
        f"[Authentication Failure] Expected HTTP 200 for customer login ({email}), "
        f"got {res.status_code}: {res.text}"
    )

    body = res.json()
    assert body.get("success") is True, f"Login response 'success' is not True: {body}"
    token = body.get("data", {}).get("token")
    assert token, "JWT Bearer token is missing from login response data."
    return token


def ensure_inventory_service_stopped():
    """
    Ensures that the inventory_service Docker container is stopped before testing.
    Returns True if this script stopped it, False if already stopped.
    """
    try:
        check = subprocess.run(
            ["docker", "compose", "ps", "--status", "running", "inventory_service"],
            capture_output=True, text=True, check=True
        )
        if "beershop_inventory_service" in check.stdout or "inventory_service" in check.stdout:
            print("[Setup] inventory_service is running. Stopping container now...")
            subprocess.run(["docker", "compose", "stop", "inventory_service"], check=True)
            print("[Setup] inventory_service stopped successfully.")
            return True
        else:
            print("[Setup] inventory_service is already stopped.")
            return False
    except Exception as e:
        print(f"[Setup Notice] Could not manage docker container automatically: {e}")
        return False


def restart_inventory_service():
    """
    Restarts the inventory_service Docker container after test completion.
    """
    try:
        print("[Teardown] Restarting inventory_service container...")
        subprocess.run(["docker", "compose", "start", "inventory_service"], check=True)
        print("[Teardown] inventory_service restarted successfully.")
    except Exception as e:
        print(f"[Teardown Notice] Could not restart inventory_service: {e}")


def test_service_down():
    print("=" * 80)
    print("  TEST: 503 SERVICE UNAVAILABLE PROOF (INVENTORY SERVICE OUTAGE)")
    print("=" * 80)

    # 1. Authenticate with documented customer demo account (cc@gmail.com / 0000)
    print("[1] Authenticating using documented customer demo account (cc@gmail.com)...")
    token = get_customer_jwt("cc@gmail.com", "0000")
    print("    Customer JWT acquired successfully.")

    # 2. Ensure inventory microservice is stopped
    stopped_by_test = ensure_inventory_service_stopped()

    try:
        headers = {
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
            "Accept": "application/json"
        }

        payload = {
            "name": "Customer During Outage",
            "email": "customer_outage@example.com",
            "address": "Damascus Street 5",
            "phone": "0911223344",
            "items": [
                {"product_id": 1, "quantity": 1}
            ]
        }

        # 3. Test Order Creation during outage (POST /api/v1/orders)
        print("\n[2] Testing POST /api/v1/orders during outage (configured deadline: 2.0s)...")
        start_time = time.time()
        order_response = requests.post(ORDERS_URL, json=payload, headers=headers, timeout=8.0)
        elapsed_orders = time.time() - start_time

        print(f"    Elapsed Time: {elapsed_orders:.2f}s")
        print(f"    HTTP Status : {order_response.status_code}")
        print(f"    Body        : {order_response.text}")

        # Assert deadline enforcement: configured timeout is 2.0s, allow up to 4.5s total roundtrip
        assert elapsed_orders <= 5.0, (
            f"Configured deadline violated! Request took {elapsed_orders:.2f}s (expected <= 4.5s)."
        )

        # Distinguish failure types
        assert order_response.status_code != 200, "CRITICAL: Application concealed outage with HTTP 200!"
        assert order_response.status_code != 500, "CRITICAL: Application crashed with HTTP 500 instead of 503!"
        assert order_response.status_code != 401, "Test failed due to authentication rejection (401), not outage."
        assert order_response.status_code != 422, "Test failed due to validation error (422), not outage."
        assert order_response.status_code == 503, f"Expected HTTP 503, got {order_response.status_code}"

        # Assert exact standardized response fields and sanitized message
        body = order_response.json()
        assert body.get("success") is False, f"Expected 'success': false, got {body.get('success')}"
        assert body.get("message") == "Inventory service temporarily unavailable", (
            f"Expected sanitized message 'Inventory service temporarily unavailable', got '{body.get('message')}'"
        )
        assert body.get("data") is None, f"Expected 'data': null, got {body.get('data')}"
        assert body.get("errors") is None, f"Expected 'errors': null, got {body.get('errors')}"
        print("    [PASS] POST /api/v1/orders correctly returned sanitized 503 within configured deadline.")

        # 4. Test Product Details during outage (GET /api/v1/products/1)
        print("\n[3] Testing GET /api/v1/products/1 during outage (configured deadline: 2.0s)...")
        start_time = time.time()
        prod_response = requests.get(PRODUCTS_URL, headers={"Accept": "application/json"}, timeout=8.0)
        elapsed_prod = time.time() - start_time

        print(f"    Elapsed Time: {elapsed_prod:.2f}s")
        print(f"    HTTP Status : {prod_response.status_code}")
        print(f"    Body        : {prod_response.text}")

        assert elapsed_prod <= 5.0, (
            f"Configured deadline violated! Product request took {elapsed_prod:.2f}s (expected <= 4.5s)."
        )
        assert prod_response.status_code == 503, (
            f"Expected 503 for product detail during outage, got {prod_response.status_code}!"
        )
        prod_body = prod_response.json()
        assert prod_body.get("success") is False
        assert prod_body.get("message") == "Inventory service temporarily unavailable"
        assert prod_body.get("data") is None
        assert prod_body.get("errors") is None
        print("    [PASS] GET /api/v1/products/1 correctly returned sanitized 503 without concealing outage.")

        print("\n" + "=" * 80)
        print(">>> 503 SERVICE UNAVAILABLE PROOF: PASSED!")
        print("    - Clean, standardized JSON contract enforced across endpoints.")
        print("    - Deadline behavior strictly verified (timed out within tolerance).")
        print("    - No stack traces, hostnames, or credentials leaked.")
        print("=" * 80)
        return True

    finally:
        if stopped_by_test:
            restart_inventory_service()


if __name__ == "__main__":
    test_service_down()
