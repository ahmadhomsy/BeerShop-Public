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

        try {
            Cache::put($key, time() + $window, $window);
        } catch (\Throwable $e) {
            Log::warning("[ReadAfterWriteManager] Cache write failure when pinning user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Check if a user currently has an active write pin.
     */
    public function isUserPinned(int $userId): bool
    {
        $key = self::CACHE_PREFIX . "user:{$userId}";

        try {
            return Cache::has($key);
        } catch (\Throwable $e) {
            // Fail-safe: if cache service is down, prefer authoritative primary read over stale replica
            Log::warning("[ReadAfterWriteManager] Cache read failure for user {$userId}; defaulting to primary read: " . $e->getMessage());
            return true;
        }
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
            Log::warning("[ReadAfterWriteManager] Cache write failure for resource {$resource}: " . $e->getMessage());
        }
    }

    /**
     * Check if a specific resource is pinned.
     */
    public function isResourcePinned(string $resource): bool
    {
        $key = self::CACHE_PREFIX . "res:{$resource}";

        try {
            return Cache::has($key);
        } catch (\Throwable $e) {
            return true; // fail-safe to primary
        }
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
}
