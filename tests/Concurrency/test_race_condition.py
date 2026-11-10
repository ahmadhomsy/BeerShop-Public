import os
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed

# Add inventory_service path for proto stubs
sys.path.append(os.path.join(os.path.dirname(__file__), '..', '..', 'inventory_service'))

import grpc
import inventory_pb2
import inventory_pb2_grpc


def run_concurrency_race_condition_test(host="127.0.0.1", port=50051):
    print("=" * 70)
    print("  CONCURRENCY & RACE CONDITION PROOF TEST")
    print("  Testing 10 simultaneous orders competing for the LAST remaining item")
    print("=" * 70)

    channel = grpc.insecure_channel(f"{host}:{port}")
    stub = inventory_pb2_grpc.InventoryServiceStub(channel)

    target_product_id = 999
    # Direct DB seed for product 999 with EXACTLY 1 item in stock
    try:
        from server import db_manager
        conn = db_manager.get_connection()
        if db_manager.use_sqlite:
            with db_manager.lock:
                cur = conn.cursor()
                cur.execute("DELETE FROM inventory WHERE product_id = ?", (target_product_id,))
                cur.execute("DELETE FROM reservations WHERE order_ref LIKE 'CONC-%';")
                cur.execute("INSERT INTO inventory (product_id, total_stock, reserved_stock) VALUES (?, 1, 0);", (target_product_id,))
                conn.commit()
        else:
            with conn.cursor() as cur:
                cur.execute("DELETE FROM inventory WHERE product_id = %s;", (target_product_id,))
                cur.execute("DELETE FROM reservations WHERE order_ref LIKE 'CONC-%';")
                cur.execute("INSERT INTO inventory (product_id, total_stock, reserved_stock) VALUES (%s, 1, 0);", (target_product_id,))
                conn.commit()
        db_manager.put_connection(conn)
        print(f"[Setup] Product {target_product_id} initialized with total_stock=1, reserved_stock=0 (Available: 1)")
    except Exception as e:
        print(f"[Setup Notice] Could not seed directly: {e}")

    # Check stock via gRPC before race
    stock_res = stub.GetStock(inventory_pb2.GetStockRequest(product_id=target_product_id))
    print(f"[gRPC GetStock] Product {target_product_id} available: {stock_res.available_stock} item(s)")

    num_concurrent_requests = 10
    print(f"\n[Action] Launching {num_concurrent_requests} concurrent requests at the EXACT SAME INSTANT...")

    start_barrier = time.time() + 0.3  # Sync start

    def make_reservation_request(request_index):
        # Wait until barrier time for simultaneous execution
        sleep_time = start_barrier - time.time()
        if sleep_time > 0:
            time.sleep(sleep_time)

        order_ref = f"CONC-REQ-{request_index}-{time.time_ns()}"
        items = [inventory_pb2.StockItem(product_id=target_product_id, quantity=1)]
        req = inventory_pb2.ReserveStockRequest(order_ref=order_ref, items=items, ttl_seconds=60)

        try:
            res = stub.ReserveStock(req, timeout=3.0)
            return (request_index, res.success, res.reservation_id, res.message)
        except Exception as ex:
            return (request_index, False, "", str(ex))

    results = []
    with ThreadPoolExecutor(max_workers=num_concurrent_requests) as executor:
        futures = [executor.submit(make_reservation_request, i + 1) for i in range(num_concurrent_requests)]
        for f in as_completed(futures):
            results.append(f.result())

    # Sort by request index
    results.sort(key=lambda x: x[0])

    successful = [r for r in results if r[1] is True]
    rejected = [r for r in results if r[1] is False]

    print("\n" + "-" * 70)
    print("  EXECUTION RESULTS:")
    print("-" * 70)
    for r in results:
        status_label = "[SUCCESS - RESERVED]" if r[1] else "[REJECTED - OUT OF STOCK]"
        res_info = f"Reservation ID: {r[2]}" if r[1] else f"Reason: {r[3]}"
        print(f"  Request #{r[0]:02d} -> {status_label} | {res_info}")

    print("-" * 70)
    print(f"Total Successful Requests : {len(successful)} (Expected: EXACTLY 1)")
    print(f"Total Rejected Requests   : {len(rejected)} (Expected: EXACTLY {num_concurrent_requests - 1})")

    # Verify final stock
    stock_after = stub.GetStock(inventory_pb2.GetStockRequest(product_id=target_product_id))
    print(f"Final Stock State         : Total={stock_after.total_stock}, Reserved={stock_after.reserved_stock}, Available={stock_after.available_stock}")
    print("-" * 70)

    assert len(successful) == 1, f"CRITICAL RACE CONDITION FAILURE: {len(successful)} requests succeeded instead of 1!"
    assert len(rejected) == num_concurrent_requests - 1, "Expected all other requests to be rejected!"
    assert stock_after.available_stock == 0, "Available stock must be 0!"

    print("\n>>> RACE CONDITION PROOF: PASSED! Zero overselling under high concurrency.")
    return True


def run_service_down_simulation(host="127.0.0.1", invalid_port=59999):
    print("\n" + "=" * 70)
    print("  SERVICE DOWN / TIMEOUT PROOF SIMULATION")
    print(f"  Attempting gRPC call to stopped service on port {invalid_port} (Timeout: 1.5s)")
    print("=" * 70)

    channel = grpc.insecure_channel(f"{host}:{invalid_port}")
    stub = inventory_pb2_grpc.InventoryServiceStub(channel)

    start_time = time.time()
    try:
        stub.GetStock(inventory_pb2.GetStockRequest(product_id=1), timeout=1.5)
        print("[FAIL] Unexpected: call succeeded on dead port!")
        return False
    except grpc.RpcError as e:
        elapsed = time.time() - start_time
        print(f"[OK] Graceful Failure caught in {elapsed:.2f}s:")
        print(f"   gRPC Status Code: {e.code()}")
        print(f"   gRPC Details    : {e.details()}")
        print(f"   Laravel Mapping : HTTP 503 Service Unavailable (DEADLINE_EXCEEDED / UNAVAILABLE)")
        assert e.code() in (grpc.StatusCode.UNAVAILABLE, grpc.StatusCode.DEADLINE_EXCEEDED)
        return True


if __name__ == "__main__":
    success = run_concurrency_race_condition_test()
    run_service_down_simulation()
    sys.exit(0 if success else 1)
