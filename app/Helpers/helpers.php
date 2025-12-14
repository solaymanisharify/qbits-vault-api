<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;

if (!function_exists('successResponse')) {
    function successResponse($message = null, $data = [], $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }
}

if (!function_exists('errorResponse')) {
    function errorResponse($message = null, $errors = [], $status = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'    => $errors,
        ], $status);
    }
}

if (!function_exists('handleHttpRequest')) {
    /**
     * Secure HTTP request helper with proper error handling
     */
    function handleHttpRequest(
        string $method,
        string $url,
        array $headers = [],
        array $data = [],
        bool $requireAuth = true
    ): ?array {
        $client = new Client([
            'timeout' => 15,
            'connect_timeout' => 10,
            'http_errors' => false, // We handle errors manually
        ]);

        // Default headers
        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // Add Bearer token if required and available
        if ($requireAuth && env('QBITS_SERVICE_TOKEN')) {
            $defaultHeaders['Authorization'] = 'Bearer ' . env('QBITS_SERVICE_TOKEN');
        }

        // Merge custom headers (custom ones override defaults)
        $finalHeaders = array_merge($defaultHeaders, $headers);

        try {
            $response = $client->request($method, $url, [
                'headers' => $finalHeaders,
                'json' => $data,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            // Always return structured data
            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status' => $statusCode,
                'data' => $decoded ?? $body,
                'raw' => $body,
            ];
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = $response?->getStatusCode() ?? 500;
            $body = $response?->getBody()->getContents() ?? $e->getMessage();

            \Log::error('External API request failed', [
                'url' => $url,
                'method' => $method,
                'status' => $statusCode,
                'response' => $body,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'data' => json_decode($body, true) ?? ['message' => 'Request failed'],
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            \Log::critical('Unexpected error in handleHttpRequest', [
                'exception' => $e->getMessage(),
                'url' => $url,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'data' => ['message' => 'Internal request error'],
            ];
        }
    }

    if (!function_exists('is_assoc')) {
        function is_assoc(array $arr): bool
        {
            if ([] === $arr) return false;
            return array_keys($arr) !== range(0, count($arr) - 1);
        }
    }
}
