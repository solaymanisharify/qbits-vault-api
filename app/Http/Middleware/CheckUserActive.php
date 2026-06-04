<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckUserActive
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $isSuperAdmin = $user->roles()->where('name', 'super-admin')->exists();

            if (!$isSuperAdmin && $user->status !== 'active') {
                JWTAuth::invalidate(JWTAuth::getToken());

                return response()->json([
                    'success'    => false,
                    'message'    => 'Your account has been deactivated. Please contact administrator.',
                    'error_code' => 'ACCOUNT_INACTIVE',
                ], 403);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
