<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

trait ApiResponser
{
    private const DEFAULT_SUCCESS_MESSAGE = 'Operation completed successfully';
    private const DEFAULT_ERROR_MESSAGE = 'An error occurred';
    private const TIMESTAMP_FORMAT = 'c';

    /* ============================================================
     * SUCCESS RESPONSES
     * ============================================================ */

    protected function successResponse(
        mixed $data = null,
        string $message = null,
        int $code = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'code' => $code,
            'message' => $message ?? self::DEFAULT_SUCCESS_MESSAGE,
            'data' => $data,
            'timestamp' => now()->format(self::TIMESTAMP_FORMAT),
        ], $code);
    }

    protected function createdResponse(mixed $data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    protected function updatedResponse(mixed $data = null, string $message = 'Resource updated successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 200);
    }

    protected function deletedResponse(
        string $message = 'Resource deleted successfully',
        mixed $data = null,
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'code' => $code,
            'timestamp' => now()->format(self::TIMESTAMP_FORMAT),
        ], $code);
    }


    protected function noContentResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 204,
            'message' => 'No content',
            'data' => null,
            'timestamp' => now()->format(self::TIMESTAMP_FORMAT),
        ], 204);
    }

    /* ============================================================
     * PAGINATION RESPONSE
     * ============================================================ */

    protected function paginatedResponse(AbstractPaginator $paginator, string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => $code,
            'message' => $message ?? 'Data retrieved successfully',
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'timestamp' => now()->format(self::TIMESTAMP_FORMAT),
        ], $code);
    }

    /* ============================================================
     * ERROR RESPONSES
     * ============================================================ */

    protected function errorResponse(
        string $message = null,
        string $errorCode = null,
        int $statusCode = 400,
        mixed $errors = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'code' => $statusCode,
            'message' => $message ?? self::DEFAULT_ERROR_MESSAGE,
            'error_code' => $errorCode,
            'errors' => $errors,
            'timestamp' => now()->format(self::TIMESTAMP_FORMAT),
        ], $statusCode);
    }

    protected function validationErrorResponse(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 'VALIDATION_ERROR', 422, $errors);
    }

    /* ============================================================
     * MAIN API EXECUTION HANDLER
     * ============================================================ */

    protected function apiTryCatch(callable $callback, ?string $successMessage = null): JsonResponse
    {
        try {
            $result = $callback();

            if ($result instanceof JsonResponse) {
                return $result;
            }

            if ($result instanceof AbstractPaginator) {
                return $this->paginatedResponse($result, $successMessage);
            }

            if ($result instanceof JsonResource || $result instanceof ResourceCollection) {
                return $this->successResponse($result, $successMessage);
            }

            return $this->successResponse($result, $successMessage);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    private function handleException(\Exception $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->validationErrorResponse($e->errors());
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->errorResponse('Resource not found', 'RESOURCE_NOT_FOUND', 404);
        }

        if ($e instanceof AuthenticationException) {
            return $this->errorResponse('Authentication required', 'UNAUTHENTICATED', 401);
        }

        if ($e instanceof AuthorizationException) {
            return $this->errorResponse('Insufficient permissions', 'FORBIDDEN', 403);
        }

        if ($e instanceof QueryException && $e->getCode() === '23000' && str_contains($e->getMessage(), 'Duplicate entry')) {
            return $this->errorResponse('Duplicate record', 'DUPLICATE_ENTRY', 409, ['general' => 'Duplicate record']);
        }

        return $this->errorResponse(
            config('app.debug') ? $e->getMessage() : 'Internal server error',
            'INTERNAL_ERROR',
            500
        );
    }
}
