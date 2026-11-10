import os
import time
import uuid
import threading
from datetime import datetime, timedelta, timezone
from concurrent import futures

import grpc
import psycopg2
from psycopg2 import pool
from psycopg2.extras import RealDictCursor

import inventory_pb2
import inventory_pb2_grpc

DB_HOST = os.getenv("DB_HOST", "db_inventory")
DB_PORT = int(os.getenv("DB_PORT", "5432"))
DB_NAME = os.getenv("DB_NAME", "inventory_db")
DB_USER = os.getenv("DB_USER", "inventory_user")
DB_PASSWORD = os.getenv("DB_PASSWORD", "inventory_password")
GRPC_PORT = os.getenv("GRPC_PORT", "50051")


class DatabaseManager:
    """
    Manages PostgreSQL connection pooling and atomic transactions.
    Pure PostgreSQL - no SQLite fallback.
    """
    def __init__(self):
        try:
            self.pool = psycopg2.pool.SimpleConnectionPool(
                minconn=1,
                maxconn=25,
                host=DB_HOST,
                port=DB_PORT,
                dbname=DB_NAME,
                user=DB_USER,
                password=DB_PASSWORD,
            )
            print(f"[DB] Successfully connected to PostgreSQL at {DB_HOST}:{DB_PORT}/{DB_NAME}")
            self.init_postgres_schema()
        except Exception as e:
            error_msg = f"[CRITICAL] Fatal error: Cannot connect to PostgreSQL at {DB_HOST}:{DB_PORT}/{DB_NAME}: {e}"
            print(error_msg)
            raise RuntimeError(error_msg)

    def get_connection(self):
        return self.pool.getconn()

    def put_connection(self, conn):
        if conn:
            self.pool.putconn(conn)

    def init_postgres_schema(self):
        conn = self.get_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("""
                    CREATE TABLE IF NOT EXISTS inventory (
                        product_id INT PRIMARY KEY,
                        total_stock INT NOT NULL,
                        reserved_stock INT NOT NULL DEFAULT 0
                    );
                    CREATE TABLE IF NOT EXISTS reservations (
                        reservation_id VARCHAR(64) PRIMARY KEY,
                        order_ref VARCHAR(255) NOT NULL UNIQUE,
                        status VARCHAR(32) NOT NULL,
                        expires_at TIMESTAMP NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS reservation_items (
                        id SERIAL PRIMARY KEY,
                        reservation_id VARCHAR(64) NOT NULL,
                        product_id INT NOT NULL,
                        quantity INT NOT NULL
                    );
                """)
                conn.commit()

                # Seed initial products if inventory table is empty
                cur.execute("SELECT COUNT(*) FROM inventory;")
                count = cur.fetchone()[0]
                if count == 0:
                    for pid in range(1, 20):
                        cur.execute(
                            "INSERT INTO inventory (product_id, total_stock, reserved_stock) VALUES (%s, %s, %s);",
                            (pid, 20, 0)
                        )
                    conn.commit()
                    print("[DB] Initialized inventory table with 20 pieces for products 1-19.")
        finally:
            self.put_connection(conn)


db_manager = DatabaseManager()


class InventoryServiceServicer(inventory_pb2_grpc.InventoryServiceServicer):
    """
    gRPC Servicer implementing all 4 RPCs with atomic concurrency, idempotency, and TTL.
    Exclusively powered by PostgreSQL.
    """

    def GetStock(self, request, context):
        pid = request.product_id
        conn = db_manager.get_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT total_stock, reserved_stock FROM inventory WHERE product_id = %s;", (pid,))
                row = cur.fetchone()

            if not row:
                return inventory_pb2.GetStockResponse(
                    product_id=pid,
                    total_stock=0,
                    reserved_stock=0,
                    available_stock=0
                )

            total = row[0]
            reserved = row[1]
            available = max(0, total - reserved)

            return inventory_pb2.GetStockResponse(
                product_id=pid,
                total_stock=total,
                reserved_stock=reserved,
                available_stock=available
            )
        finally:
            db_manager.put_connection(conn)

    def ReserveStock(self, request, context):
        order_ref = request.order_ref
        items = request.items
        ttl_seconds = request.ttl_seconds if request.ttl_seconds > 0 else 300

        if not order_ref:
            return inventory_pb2.ReserveStockResponse(
                success=False,
                message="order_ref is required for idempotency."
            )

        if not items:
            return inventory_pb2.ReserveStockResponse(
                success=False,
                message="items list cannot be empty."
            )

        conn = db_manager.get_connection()
        try:
            # 1. Idempotency Check
            with conn.cursor() as cur:
                cur.execute("SELECT reservation_id, status FROM reservations WHERE order_ref = %s;", (order_ref,))
                existing = cur.fetchone()

            if existing:
                res_id, status = existing[0], existing[1]
                if status in ("pending", "confirmed"):
                    return inventory_pb2.ReserveStockResponse(
                        success=True,
                        reservation_id=res_id,
                        message=f"Idempotent response: reservation already {status}."
                    )
                else:
                    return inventory_pb2.ReserveStockResponse(
                        success=False,
                        reservation_id=res_id,
                        message="Order reference was previously released."
                    )

            # 2. Atomic All-or-Nothing Reservation
            failed_product_ids = []
            res_id = str(uuid.uuid4())
            expires_at = datetime.now(timezone.utc).replace(tzinfo=None) + timedelta(seconds=ttl_seconds)

            try:
                with conn.cursor() as cur:
                    for item in items:
                        cur.execute(
                            """
                            UPDATE inventory
                            SET reserved_stock = reserved_stock + %s
                            WHERE product_id = %s AND (total_stock - reserved_stock) >= %s;
                            """,
                            (item.quantity, item.product_id, item.quantity)
                        )
                        if cur.rowcount == 0:
                            failed_product_ids.append(item.product_id)

                    if failed_product_ids:
                        conn.rollback()
                        return inventory_pb2.ReserveStockResponse(
                            success=False,
                            message="Insufficient stock for one or more items.",
                            failed_product_ids=failed_product_ids
                        )

                    cur.execute(
                        "INSERT INTO reservations (reservation_id, order_ref, status, expires_at) VALUES (%s, %s, %s, %s);",
                        (res_id, order_ref, "pending", expires_at)
                    )
                    for item in items:
                        cur.execute(
                            "INSERT INTO reservation_items (reservation_id, product_id, quantity) VALUES (%s, %s, %s);",
                            (res_id, item.product_id, item.quantity)
                        )
                    conn.commit()
                    return inventory_pb2.ReserveStockResponse(
                        success=True,
                        reservation_id=res_id,
                        message="Stock reserved successfully."
                    )
            except Exception as ex:
                conn.rollback()
                raise ex
        finally:
            db_manager.put_connection(conn)

    def ReleaseStock(self, request, context):
        res_id = request.reservation_id
        order_ref = request.order_ref

        conn = db_manager.get_connection()
        try:
            with conn.cursor() as cur:
                if res_id:
                    cur.execute("SELECT reservation_id, status FROM reservations WHERE reservation_id = %s;", (res_id,))
                else:
                    cur.execute("SELECT reservation_id, status FROM reservations WHERE order_ref = %s;", (order_ref,))
                row = cur.fetchone()

                if not row:
                    return inventory_pb2.ReleaseStockResponse(success=False, message="Reservation not found.")

                actual_res_id, status = row[0], row[1]
                if status == "released":
                    return inventory_pb2.ReleaseStockResponse(success=True, message="Already released (idempotent).")

                cur.execute("SELECT product_id, quantity FROM reservation_items WHERE reservation_id = %s;", (actual_res_id,))
                items = cur.fetchall()
                for item in items:
                    cur.execute(
                        "UPDATE inventory SET reserved_stock = GREATEST(0, reserved_stock - %s) WHERE product_id = %s;",
                        (item[1], item[0])
                    )
                cur.execute("UPDATE reservations SET status = 'released' WHERE reservation_id = %s;", (actual_res_id,))
                conn.commit()
                return inventory_pb2.ReleaseStockResponse(success=True, message="Reserved stock released.")
        finally:
            db_manager.put_connection(conn)

    def ConfirmStock(self, request, context):
        res_id = request.reservation_id
        order_ref = request.order_ref

        conn = db_manager.get_connection()
        try:
            with conn.cursor() as cur:
                if res_id:
                    cur.execute("SELECT reservation_id, status FROM reservations WHERE reservation_id = %s;", (res_id,))
                else:
                    cur.execute("SELECT reservation_id, status FROM reservations WHERE order_ref = %s;", (order_ref,))
                row = cur.fetchone()

                if not row:
                    return inventory_pb2.ConfirmStockResponse(success=False, message="Reservation not found.")

                actual_res_id, status = row[0], row[1]
                if status == "confirmed":
                    return inventory_pb2.ConfirmStockResponse(success=True, message="Already confirmed (idempotent).")

                cur.execute("UPDATE reservations SET status = 'confirmed' WHERE reservation_id = %s;", (actual_res_id,))
                conn.commit()
                return inventory_pb2.ConfirmStockResponse(success=True, message="Reservation confirmed.")
        finally:
            db_manager.put_connection(conn)


def orphan_reservation_cleanup_job():
    """
    Background worker that releases pending reservations that have expired (TTL).
    Runs every 15 seconds against PostgreSQL.
    """
    while True:
        try:
            time.sleep(15)
            now = datetime.now(timezone.utc).replace(tzinfo=None)
            conn = db_manager.get_connection()
            try:
                with conn.cursor() as cur:
                    cur.execute("SELECT reservation_id FROM reservations WHERE status = 'pending' AND expires_at < %s;", (now,))
                    expired = cur.fetchall()
                    for row in expired:
                        res_id = row[0]
                        cur.execute("SELECT product_id, quantity FROM reservation_items WHERE reservation_id = %s;", (res_id,))
                        for item in cur.fetchall():
                            cur.execute(
                                "UPDATE inventory SET reserved_stock = GREATEST(0, reserved_stock - %s) WHERE product_id = %s;",
                                (item[1], item[0])
                            )
                        cur.execute("UPDATE reservations SET status = 'released' WHERE reservation_id = %s;", (res_id,))
                        print(f"[TTL Cleaner] Released expired pending reservation {res_id}")
                    conn.commit()
            finally:
                db_manager.put_connection(conn)
        except Exception as e:
            print(f"[TTL Cleaner Error] {e}")


def serve():
    cleanup_thread = threading.Thread(target=orphan_reservation_cleanup_job, daemon=True)
    cleanup_thread.start()

    server = grpc.server(futures.ThreadPoolExecutor(max_workers=30))
    inventory_pb2_grpc.add_InventoryServiceServicer_to_server(InventoryServiceServicer(), server)
    server.add_insecure_port(f"0.0.0.0:{GRPC_PORT}")
    server.start()
    print(f"[*] Polyglot Inventory gRPC Service running on PostgreSQL on port {GRPC_PORT}...")
    server.wait_for_termination()


if __name__ == "__main__":
    serve()
