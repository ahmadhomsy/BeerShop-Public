#!/bin/sh
set -e

cd /var/www

# 1. Setup .env if missing
if [ ! -f .env ]; then
    echo "[Entrypoint] .env file not found, creating from .env.example..."
    cp .env.example .env
fi

# 2. Install composer dependencies if vendor is missing
if [ ! -f vendor/autoload.php ]; then
    echo "[Entrypoint] vendor directory missing, installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# 3. Ensure storage & bootstrap permissions
chmod -R 777 /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

# 4. Safe and idempotent application secrets initialization
# 4a. Initialize APP_KEY if missing or empty
if ! grep -E -q "^APP_KEY=[^[:space:]]+" .env 2>/dev/null; then
    echo "[Entrypoint] APP_KEY is uninitialized. Generating application key..."
    php artisan key:generate --force --no-interaction >/dev/null
    echo "[Entrypoint] APP_KEY successfully initialized."
else
    echo "[Entrypoint] APP_KEY already initialized, preserving existing key."
fi

# 4b. Initialize JWT_SECRET if missing or empty
if ! grep -q "^JWT_SECRET=" .env 2>/dev/null; then
    echo "JWT_SECRET=" >> .env
fi

if ! grep -E -q "^JWT_SECRET=[^[:space:]]+" .env 2>/dev/null; then
    echo "[Entrypoint] JWT_SECRET is uninitialized. Generating JWT secret..."
    php artisan jwt:secret --force --no-interaction >/dev/null
    echo "[Entrypoint] JWT_SECRET successfully initialized."
else
    echo "[Entrypoint] JWT_SECRET already initialized, preserving existing secret."
fi

# 5. Wait for MySQL database
echo "[Entrypoint] Waiting for MySQL database (${DB_HOST:-db_main}:${DB_PORT:-3306})..."
until php -r '
$host = getenv("DB_HOST") ?: "db_main";
$port = getenv("DB_PORT") ?: 3306;
$db   = getenv("DB_DATABASE") ?: "aboodm4_beershop";
$user = getenv("DB_USERNAME") ?: "beershop_user";
$pass = getenv("DB_PASSWORD") ?: "beershop_password";
try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    exit(0);
} catch (Exception $e) {
    exit(1);
}
' 2>/dev/null; do
    echo "[Entrypoint] MySQL is not ready yet - retrying in 2 seconds..."
    sleep 2
done

echo "[Entrypoint] MySQL is ready!"

# 6. Run database migrations & seeds (always against primary write host)
echo "[Entrypoint] Running database migrations..."
DB_READ_HOST="${DB_HOST:-db_main}" php artisan migrate --force

echo "[Entrypoint] Running database seeds (idempotent)..."
DB_READ_HOST="${DB_HOST:-db_main}" php artisan db:seed --force

# 7. Verify MySQL read replica health if configured
if [ "${DB_READ_HOST}" = "db_replica" ]; then
    echo "[Entrypoint] Verifying MySQL read replica health..."
    php artisan replica:status || echo "[Entrypoint] Notice: Replica status check completed with warnings."
fi

echo "[Entrypoint] Starting container process: $@"
exec "$@"
