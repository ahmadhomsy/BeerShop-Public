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

# 4. Wait for MySQL database
echo "[Entrypoint] Waiting for MySQL database (db_main:3306)..."
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

# 5. Run database migrations & seeds
echo "[Entrypoint] Running database migrations..."
php artisan migrate --force

echo "[Entrypoint] Running database seeds (idempotent)..."
php artisan db:seed --force

echo "[Entrypoint] Starting container process: $@"
exec "$@"
