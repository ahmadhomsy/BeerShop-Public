import os
import subprocess
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed

# Add inventory_service directory to sys.path for proto stubs
sys.path.append(os.path.join(os.path.dirname(__file__), "..", "..", "inventory_service"))

import grpc
import inventory_pb2
import inventory_pb2_grpc


def setup_target_product_in_postgres(product_id=999, total_stock=1, reserved_stock=0):
    """
    Seed target product in PostgreSQL database reliably from host or container environment.
    Does NOT use SQLite and does NOT rely on inaccessible Docker DNS names.
    """
    sql = (
        f"DELETE FROM reservation_items WHERE product_id = {product_id}; "
        f"DELETE FROM reservations WHERE order_ref LIKE 'CONC-%'; "
        f"DELETE FROM inventory WHERE product_id = {product_id}; "
        f"INSERT INTO inventory (product_id, total_stock, reserved_stock) VALUES ({product_id}, {total_stock}, {reserved_stock});"
    )

    # Strategy A: Docker Compose Exec (reliable from host without local postgres client)
    try:
        res = subprocess.run([
            "docker", "compose", "exec", "-T", "db_inventory",
            "psql", "-U", "inventory_user", "-d", "inventory_db",
            "-c", sql
        ], capture_output=True, text=True, check=True)
        print(f"[Setup] Product {product_id} seeded via Docker PostgreSQL: total={total_stock}, reserved={reserved_stock}")
        return True
    except Exception as docker_err:
        # Strategy B: Direct psycopg2 connection (fallback if running in container or host psycopg2 configured)
        try:
            import psycopg2
            host = os.getenv("DB_HOST", "127.0.0.1")
            port = int(os.getenv("DB_PORT", "5433" if host in ("127.0.0.1", "localhost") else "5432"))
            conn = psycopg2.connect(
                host=host,
                port=port,
                dbname=os.getenv("DB_NAME", "inventory_db"),
                user=os.getenv("DB_USER", "inventory_user"),
                password=os.getenv("DB_PASSWORD", "inventory_password")
            )
            with conn.cursor() as cur:
                cur.execute(sql)
            conn.commit()
            conn.close()
            print(f"[Setup] Product {product_id} seeded via psycopg2 ({host}:{port}): total={total_stock}, reserved={reserved_stock}")
            return True
        except Exception as pg_err:
            raise RuntimeError(f"Failed to seed PostgreSQL. Docker error: {docker_err}. Direct PG error: {pg_err}")


def fetch_postgres_stock_direct(product_id=999):
    """
    Read stock directly from PostgreSQL table.
    """
    query = f"SELECT total_stock, reserved_stock FROM inventory WHERE product_id = {product_id};"
    try:
        res = subprocess.run([
            "docker", "compose", "exec", "-T", "db_inventory",
            "psql", "-U", "inventory_user", "-d", "inventory_db",
            "-t", "-A", "-F", ",", "-c", query
        ], capture_output=True, text=True, check=True)
        parts = res.stdout.strip().split(",")
        return int(parts[0]), int(parts[1])
    except Exception:
        import psycopg2
        host = os.getenv("DB_HOST", "127.0.0.1")
        port = int(os.getenv("DB_PORT", "5433" if host in ("127.0.0.1", "localhost") else "5432"))
        conn = psycopg2.connect(
            host=host,
            port=port,
            dbname=os.getenv("DB_NAME", "inventory_db"),
            user=os.getenv("DB_USER", "inventory_user"),
            password=os.getenv("DB_PASSWORD", "inventory_password")
        )
        with conn.cursor() as cur:
            cur.execute(query)
            row = cur.fetchone()
        conn.close()
        return int(row[0]), int(row[1])


def run_concurrency_race_condition_test(host="127.0.0.1", port=50051):
    print("=" * 80)
    print("  DIRECT gRPC RACE CONDITION TEST")
    print("  Testing 10 simultaneous ReserveStock calls competing for the LAST remaining item")
    print("=" * 80)

    target_product_id = 999

    # 1. Reliable PostgreSQL Setup
    setup_target_product_in_postgres(product_id=target_product_id, total_stock=1, reserved_stock=0)

    # 2. Connect to gRPC Microservice
    grpc_target = f"{host}:{port}"
    print(f"[Channel] Connecting to gRPC service at {grpc_target}...")
    channel = grpc.insecure_channel(grpc_target)
    stub = inventory_pb2_grpc.InventoryServiceStub(channel)

    # Verify initial stock via gRPC
    init_stock = stub.GetStock(inventory_pb2.GetStockRequest(product_id=target_product_id), timeout=3.0)
    print(f"[gRPC GetStock] Product {target_product_id} initial state: Total={init_stock.total_stock}, Reserved={init_stock.reserved_stock}, Available={init_stock.available_stock}")
    assert init_stock.total_stock == 1 and init_stock.reserved_stock == 0 and init_stock.available_stock == 1

    # 3. Launch 10 simultaneous ReserveStock calls
    num_concurrent_requests = 10
    start_barrier = time.time() + 0.35

    def make_reservation_request(idx):
        sleep_time = start_barrier - time.time()
        if sleep_time > 0:
            time.sleep(sleep_time)

        order_ref = f"CONC-REQ-{idx}-{time.time_ns()}"
        items = [inventory_pb2.StockItem(product_id=target_product_id, quantity=1)]
        req = inventory_pb2.ReserveStockRequest(order_ref=order_ref, items=items, ttl_seconds=60)

        try:
            res = stub.ReserveStock(req, timeout=5.0)
            return (idx, res.success, res.reservation_id, res.message)
        except Exception as ex:
            return (idx, False, "", str(ex))

    print(f"\n[Action] Firing {num_concurrent_requests} concurrent ReserveStock requests...")
    results = []
    with ThreadPoolExecutor(max_workers=num_concurrent_requests) as executor:
        futures = [executor.submit(make_reservation_request, i + 1) for i in range(num_concurrent_requests)]
        for f in as_completed(futures):
            results.append(f.result())

    results.sort(key=lambda x: x[0])

    successful = [r for r in results if r[1] is True]
    rejected = [r for r in results if r[1] is False]

    print("\n" + "-" * 80)
    print("  gRPC EXECUTION RESULTS:")
    print("-" * 80)
    for r in results:
        status_label = "[SUCCESS - RESERVED]" if r[1] else "[REJECTED - OUT OF STOCK]"
        detail = f"Reservation ID: {r[2]}" if r[1] else f"Reason: {r[3]}"
        print(f"  Request #{r[0]:02d} -> {status_label:<25} | {detail}")

    print("-" * 80)
    print(f"Total Successful Reservations : {len(successful)} (Required: EXACTLY 1)")
    print(f"Total Rejected Reservations   : {len(rejected)} (Required: EXACTLY 9)")
    print("-" * 80)

    # 4. Assert Exact Race Condition Proof
    assert len(successful) == 1, f"CRITICAL CONCURRENCY FAILURE: Expected 1 success, got {len(successful)}!"
    assert len(rejected) == 9, f"CRITICAL CONCURRENCY FAILURE: Expected 9 rejections, got {len(rejected)}!"

    # 5. Verify final stock via gRPC
    final_stock = stub.GetStock(inventory_pb2.GetStockRequest(product_id=target_product_id), timeout=3.0)
    print(f"\n[Final gRPC State] Total: {final_stock.total_stock}, Reserved: {final_stock.reserved_stock}, Available: {final_stock.available_stock}")

    assert final_stock.total_stock == 1, f"Expected total_stock = 1, got {final_stock.total_stock}"
    assert final_stock.reserved_stock == 1, f"Expected reserved_stock = 1, got {final_stock.reserved_stock}"
    assert final_stock.available_stock == 0, f"Expected available_stock = 0, got {final_stock.available_stock}"

    # 6. Verify final stock directly in PostgreSQL table
    pg_total, pg_reserved = fetch_postgres_stock_direct(target_product_id)
    print(f"[Final PostgreSQL Table] Total: {pg_total}, Reserved: {pg_reserved}, Available: {pg_total - pg_reserved}")
    assert pg_total == 1, f"PostgreSQL total_stock must be 1, got {pg_total}"
    assert pg_reserved == 1, f"PostgreSQL reserved_stock must be 1, got {pg_reserved}"
    assert (pg_total - pg_reserved) == 0, f"PostgreSQL available stock must be 0, got {pg_total - pg_reserved}"

    print("\n>>> DIRECT gRPC RACE CONDITION PROOF: PASSED! Zero overselling under high concurrency.")
    return True


def run_service_down_simulation(host="127.0.0.1", invalid_port=59999):
    print("\n" + "=" * 80)
    print("  SERVICE DOWN / TIMEOUT PROOF SIMULATION")
    print(f"  Attempting gRPC call to stopped service on port {invalid_port} (Timeout: 2.0s)")
    print("=" * 80)

    channel = grpc.insecure_channel(f"{host}:{invalid_port}")
    stub = inventory_pb2_grpc.InventoryServiceStub(channel)

    start_time = time.time()
    try:
        stub.GetStock(inventory_pb2.GetStockRequest(product_id=1), timeout=2.0)
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
