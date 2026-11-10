<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // 401 Unauthorized
        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated or invalid token.',
                    'data'    => null,
                    'errors'  => null,
                ], 401);
            }
        });

        // 403 Forbidden
        $this->renderable(function (AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Unauthorized access.',
                    'data'    => null,
                    'errors'  => null,
                ], 403);
            }
        });

        $this->renderable(function (AccessDeniedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Access forbidden for this role.',
                    'data'    => null,
                    'errors'  => null,
                ], 403);
            }
        });

        // 404 Not Found
        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'data'    => null,
                    'errors'  => null,
                ], 404);
            }
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'data'    => null,
                    'errors'  => null,
                ], 404);
            }
        });

        // 422 Validation Error
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error.',
                    'data'    => null,
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // 429 Too Many Requests
        $this->renderable(function (ThrottleRequestsException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                    'data'    => null,
                    'errors'  => null,
                ], 429);
            }
        });

        // 503 Inventory Service Unavailable
        $this->renderable(function (InventoryServiceUnavailableException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inventory service temporarily unavailable',
                    'data'    => null,
                    'errors'  => null,
                ], 503);
            }
        });

        // 500 Internal Server Error & general catch-all (strictly sanitized, no stack traces)
        $this->renderable(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = 500;
                $message = 'An unexpected internal server error occurred.';

                if ($e instanceof HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                    if ($status === 503) {
                        $message = 'Inventory service temporarily unavailable';
                    } elseif ($status !== 500 && !empty($e->getMessage())) {
                        $message = $e->getMessage();
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data'    => null,
                    'errors'  => null,
                ], $status);
            }
        });
    }
}
