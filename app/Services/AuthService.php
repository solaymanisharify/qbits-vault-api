<?php

namespace App\Services;

use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function __construct(protected UserRepository $userRepository) {}

    public function create($request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = $this->userRepository->create($request);

        $token = Auth::login($user);

        return response()->json([
            'user' => $user,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
    }

    public function login($request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        $token = Auth::attempt($credentials);
        if (!$token) {

            return errorResponse("Invalid credentials", [], 401);
            // return response()->json([
            //     'message' => 'Unauthorized',
            // ], 401);
        }

        $user = Auth::user();

        $authResponse = [
            'user' => $user,
            'access_token' => $token,
        ];

        return successResponse("Successfully logged in", $authResponse, 200);
    }

    public function logout()
    {
        Auth::logout();

        return successResponse("Successfully logged out", [], 200);
    }

    public function refresh()
    {
        return response()->json([
            'user' => Auth::user(),
            'authorization' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ]);
    }
}
