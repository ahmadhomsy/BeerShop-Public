<?php

namespace App\Modules\Orders\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Requests\StoreOrderRequest;
use App\Modules\Orders\Requests\UpdateOrderStatusRequest;
use App\Modules\Orders\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * List orders. Customer sees only their orders; Admin sees all.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $perPage = (int) $request->query('per_page', 10);
        $orders = $this->orderService->listForUser($user, $perPage);

        return $this->successResponse([
            'items' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
                'last_page'    => $orders->lastPage(),
            ],
        ], 'Orders retrieved successfully.');
    }

    /**
     * Show single order details with ownership verification.
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found.', 404);
        }

        // Policy check: customer can only view their own order
        $this->authorize('view', $order);

        return $this->successResponse($order, 'Order retrieved successfully.');
    }

    /**
     * Store new order for current user.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key');
        $order = $this->orderService->create($user, $request->validated(), $idempotencyKey);

        return $this->successResponse($order, 'Order placed successfully.', 201);
    }

    /**
     * Update order status. Restricted to ADMIN only.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found.', 404);
        }

        // Policy check: only admin can update status
        $this->authorize('updateStatus', $order);

        $updatedOrder = $this->orderService->updateStatus($order, (string) $request->status);

        return $this->successResponse($updatedOrder, 'Order status updated successfully.');
    }
}
