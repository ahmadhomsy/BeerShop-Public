<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryServiceUnavailableException extends Exception
{
    protected $message = 'Inventory service temporarily unavailable';
    protected $code = 503;

    public function __construct(string $message = 'Inventory service temporarily unavailable', int $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Inventory service temporarily unavailable',
            'data'    => null,
            'errors'  => null,
        ], 503);
    }
}
