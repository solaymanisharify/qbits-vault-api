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
// if (!function_exists('handleHttpNewRequest')) {
//     function handleHttpNewRequest(
//         string $method,
//         string $url,
//         array $headers = [],
//         array $data = [],
//         bool $requireAuth = true
//     ): array {

//         \Log::debug('handleHttpNewRequest CALLED', [
//             'method' => $method,
//             'url'    => $url,
//             'data'   => $data,
//         ]);

//         $scalarize = function ($value) use (&$scalarize): string {
//             if (is_null($value))  return '';
//             if (is_bool($value))  return $value ? 'true' : 'false';
//             if (is_array($value)) return implode(', ', array_map($scalarize, $value));
//             return (string) $value;
//         };

//         $finalHeaders = [
//             'Accept'       => 'application/json',
//             'Content-Type' => 'application/json',
//         ];

//         if ($requireAuth) {
//             $apiKey    = env('NAAS_API_KEY');
//             $secretKey = env('NAAS_SECRET_KEY');

//             \Log::debug('handleHttpNewRequest AUTH KEYS', [
//                 'apikey_type'    => gettype($apiKey),
//                 'apikey_value'   => $apiKey,
//                 'secretkey_type' => gettype($secretKey),
//             ]);

//             $finalHeaders['apikey']    = $scalarize($apiKey);
//             $finalHeaders['secretkey'] = $scalarize($secretKey);
//         }

//         foreach ($headers as $key => $value) {
//             $finalHeaders[(string) $key] = $scalarize($value);
//         }

//         \Log::debug('handleHttpNewRequest FINAL HEADERS', $finalHeaders);

//         try {
//             $method = strtoupper($method);

//             $client = new \GuzzleHttp\Client([
//                 'timeout'     => 20,
//                 'http_errors' => false,
//             ]);

//             \Log::debug('handleHttpNewRequest FIRING REQUEST...');

//             $response = $client->request($method, $url, [
//                 'headers' => $finalHeaders,
//                 ($method === 'GET' ? 'query' : 'json') => $data,
//             ]);

//             $resBody = $response->getBody()->getContents();

//             \Log::debug('handleHttpNewRequest RESPONSE', [
//                 'status' => $response->getStatusCode(),
//                 'body'   => $resBody,
//             ]);

//             return [
//                 'success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
//                 'status'  => $response->getStatusCode(),
//                 'data'    => json_decode($resBody, true) ?? $resBody,
//             ];
//         } catch (\Throwable $e) {
//             \Log::error('handleHttpNewRequest EXCEPTION', [
//                 'message' => $e->getMessage(),
//                 'file'    => $e->getFile(),
//                 'line'    => $e->getLine(),
//                 'url'     => $url,
//             ]);

//             return [
//                 'success' => false,
//                 'status'  => 500,
//                 'error'   => $e->getMessage(),
//             ];
//         }
//     }
// }

if (!function_exists('handleHttpNewRequest')) {
    function handleHttpNewRequest(
        string $method,
        string $url,
        array $headers = [],
        array $data = [],
        bool $requireAuth = true
    ): array {

        \Log::debug('handleHttpNewRequest CALLED', [
            'method' => $method,
            'url'    => $url,
            'data'   => $data,
        ]);

        // Safely cast any value to a string for headers
        $scalarize = function ($value) use (&$scalarize): string {
            if (is_null($value))  return '';
            if (is_bool($value))  return $value ? 'true' : 'false';
            if (is_array($value)) return implode(', ', array_map($scalarize, $value));
            return (string) $value;
        };

        $finalHeaders = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($requireAuth) {
            $apiKey    = env('NAAS_API_KEY');
            $secretKey = env('NAAS_SECRET_KEY');

            if (empty($apiKey) || empty($secretKey)) {
                \Log::critical('NAAS API keys are missing from environment.');
                return [
                    'success' => false,
                    'status'  => 500,
                    'error'   => 'Missing NAAS API credentials.',
                ];
            }

            $finalHeaders['apikey']    = $scalarize($apiKey);
            $finalHeaders['secretkey'] = $scalarize($secretKey);

            \Log::debug('handleHttpNewRequest AUTH KEYS LOADED', [
                'apikey_present'    => !empty($apiKey),
                'secretkey_present' => !empty($secretKey),
            ]);
        }

        // Merge any caller-provided headers (override allowed)
        foreach ($headers as $key => $value) {
            $finalHeaders[(string) $key] = $scalarize($value);
        }

        \Log::debug('handleHttpNewRequest FINAL HEADERS', $finalHeaders);

        try {
            $method = strtoupper($method);

            $client = new \GuzzleHttp\Client([
                'timeout'         => 20,
                'connect_timeout' => 10,
                'http_errors'     => false, // Don't throw on 4xx/5xx
            ]);

            $requestOptions = [
                'headers' => $finalHeaders,
                ($method === 'GET' ? 'query' : 'json') => $data,
            ];

            \Log::debug('handleHttpNewRequest FIRING REQUEST...', [
                'url'    => $url,
                'method' => $method,
            ]);

            $response = $client->request($method, $url, $requestOptions);

            $statusCode = $response->getStatusCode();
            $resBody    = $response->getBody()->getContents();
            $decoded    = json_decode($resBody, true);

            \Log::debug('handleHttpNewRequest RESPONSE', [
                'status' => $statusCode,
                'body'   => $decoded ?? $resBody,
            ]);

            $success = $statusCode >= 200 && $statusCode < 300;

            if (!$success) {
                \Log::warning('handleHttpNewRequest NON-2XX RESPONSE', [
                    'status' => $statusCode,
                    'url'    => $url,
                    'body'   => $decoded ?? $resBody,
                ]);
            }

            return [
                'success' => $success,
                'status'  => $statusCode,
                'data'    => $decoded ?? $resBody,
            ];
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            \Log::error('handleHttpNewRequest CONNECTION FAILED', [
                'message' => $e->getMessage(),
                'url'     => $url,
            ]);
            return [
                'success' => false,
                'status'  => 503,
                'error'   => 'Could not connect to notification service.',
            ];
        } catch (\Throwable $e) {
            \Log::error('handleHttpNewRequest EXCEPTION', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'url'     => $url,
            ]);
            return [
                'success' => false,
                'status'  => 500,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
