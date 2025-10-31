<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class BaseApiController extends Controller
{
    /**
     * Return a successful response with data
     */
    protected function successResponse($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * Return an error response
     */
    protected function errorResponse(string $message = 'Error', int $status = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a resource response
     */
    protected function resourceResponse(JsonResource $resource, string $message = 'Success', int $status = 200): JsonResponse
    {
        return $this->successResponse($resource, $message, $status);
    }

    /**
     * Return a collection response
     */
    protected function collectionResponse(ResourceCollection $collection, string $message = 'Success', int $status = 200): JsonResponse
    {
        return $this->successResponse($collection, $message, $status);
    }

    /**
     * Return a paginated response
     */
    protected function paginatedResponse(ResourceCollection $collection, string $message = 'Success'): JsonResponse
    {
        $data = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data['data'],
            'meta' => [
                'current_page' => $data['meta']['current_page'] ?? 1,
                'last_page' => $data['meta']['last_page'] ?? 1,
                'per_page' => $data['meta']['per_page'] ?? 15,
                'total' => $data['meta']['total'] ?? 0,
                'from' => $data['meta']['from'] ?? null,
                'to' => $data['meta']['to'] ?? null,
                'pagination' => [
                    'current_page' => $data['meta']['current_page'] ?? 1,
                    'last_page' => $data['meta']['last_page'] ?? 1,
                    'per_page' => $data['meta']['per_page'] ?? 15,
                    'total' => $data['meta']['total'] ?? 0,
                    'from' => $data['meta']['from'] ?? null,
                    'to' => $data['meta']['to'] ?? null,
                ],
            ],
            'links' => $data['links'] ?? null,
        ]);
    }

    /**
     * Return a not found response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Return a validation error response
     */
    protected function validationErrorResponse(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Return a created response
     */
    protected function createdResponse($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Return a deleted response
     */
    protected function deletedResponse(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->successResponse(null, $message, 200);
    }

    /**
     * Return an unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Return a forbidden response
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }
}
