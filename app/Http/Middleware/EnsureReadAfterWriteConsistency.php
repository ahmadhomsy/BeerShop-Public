<?php

namespace App\Http\Middleware;

use App\Services\Database\ReadAfterWriteManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReadAfterWriteConsistency
{
    protected ReadAfterWriteManager $rawManager;

    public function __construct(ReadAfterWriteManager $rawManager)
    {
        $this->rawManager = $rawManager;
    }

    /**
     * Handle an incoming request.
     * If the user or target resource is pinned from a recent write operation,
     * switch database queries for this request to the authoritative primary (Write PDO).
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if authenticated user has an active write pin
        // JWT guard is evaluated on-demand from the Authorization header
        $user = auth('api')->user();
        if ($user && $this->rawManager->isUserPinned($user->id)) {
            $this->rawManager->forcePrimaryRead();
        }

        // 2. Check for resource-specific pin (e.g. /api/v1/products/{id})
        $productId = $request->route('id') ?? $request->route('product');
        if (!$productId && preg_match('#/api/v1/products/(\d+)#', $request->getPathInfo(), $matches)) {
            $productId = (int) $matches[1];
        }

        if ($productId && $this->rawManager->isResourcePinned("product:{$productId}")) {
            $this->rawManager->forcePrimaryRead();
        }

        return $next($request);
    }
}
