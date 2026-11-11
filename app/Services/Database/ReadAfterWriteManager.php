<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReadAfterWriteManager
{
    public const DEFAULT_WINDOW_SECONDS = 10;
    public const CACHE_PREFIX = 'raw_fence:';

    /**
     * Mark a user as having performed a write operation.
     * Subsequent read requests by this user will be pinned to the primary database
     * for $seconds duration to guarantee immediate read-after-write consistency.
     */
    public function pinUser(int $userId, ?int $seconds = null): void
    {
        $window = $seconds ?? (int) config('database.read_after_write_window', self::DEFAULT_WINDOW_SECONDS);
        $key = self::CACHE_PREFIX . "user:{$userId}";

        // 1. Primary write to Redis cache
        try {
            Cache::put($key, time() + $window, $window);
        } catch (\Throwable $e) {
            Log::warning("[ReadAfterWriteManager] Redis write failure when pinning user {$userId}: " . $e->getMessage());
        }

        // 2. Secondary durable fallback to local file store.
        // Guarantees that if Redis was down during the write, subsequent reads
        // still recognize the active write pin even if Redis recovers before the window expires.
        try {
            Cache::store('file')->put($key, time() + $window, $window);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Check if a user currently has an active write pin.
     */
    public function isUserPinned(int $userId): bool
    {
        $key = self::CACHE_PREFIX . "user:{$userId}";

        // 1. Check primary Redis store
        try {
            if (Cache::has($key)) {
                return true;
            }
        } catch (\Throwable $e) {
            // Fail-safe invariant: if Redis is down or throwing, prefer authoritative primary read
            Log::warning("[ReadAfterWriteManager] Cache read failure for user {$userId}; defaulting to primary read: " . $e->getMessage());
            return true;
        }

        // 2. Check secondary durable file store
        try {
            if (Cache::store('file')->has($key)) {
                return true;
            }
        } catch (\Throwable $e) {
            return true; // Fail-safe to primary
        }

        return false;
    }

    /**
     * Pin a specific resource (e.g. 'product:10').
     */
    public function pinResource(string $resource, ?int $seconds = null): void
    {
        $window = $seconds ?? (int) config('database.read_after_write_window', self::DEFAULT_WINDOW_SECONDS);
        $key = self::CACHE_PREFIX . "res:{$resource}";

        try {
            Cache::put($key, time() + $window, $window);
        } catch (\Throwable $e) {
            Log::warning("[ReadAfterWriteManager] Redis write failure for resource {$resource}: " . $e->getMessage());
        }

        try {
            Cache::store('file')->put($key, time() + $window, $window);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Check if a specific resource is pinned.
     */
    public function isResourcePinned(string $resource): bool
    {
        $key = self::CACHE_PREFIX . "res:{$resource}";

        try {
            if (Cache::has($key)) {
                return true;
            }
        } catch (\Throwable $e) {
            return true; // fail-safe to primary
        }

        try {
            if (Cache::store('file')->has($key)) {
                return true;
            }
        } catch (\Throwable $e) {
            return true;
        }

        return false;
    }

    /**
     * Force the default MySQL connection to use the write PDO (primary) for all read queries in the current request.
     */
    public function forcePrimaryRead(string $connection = 'mysql'): void
    {
        try {
            $conn = DB::connection($connection);
            if (method_exists($conn, 'useWriteConnectionWhenReading')) {
                $conn->useWriteConnectionWhenReading(true);
            }
        } catch (\Throwable $e) {
            Log::warning("[ReadAfterWriteManager] Failed to switch connection to write PDO: " . $e->getMessage());
        }
    }

    /**
     * Inspect whether write connection is forced for reads on the connection.
     */
    public function isWriteConnectionForced(string $connection = 'mysql'): bool
    {
        try {
            $conn = DB::connection($connection);
            $reflector = new \ReflectionClass($conn);
            if ($reflector->hasProperty('readWriteType')) {
                $prop = $reflector->getProperty('readWriteType');
                $prop->setAccessible(true);
                return $prop->getValue($conn) === 'write';
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return false;
    }
}
