<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}
    public function register(Request $request)
    {
        return $this->authService->create($request);
    }

    public function login(Request $request)
    {
        return $this->authService->login($request);
    }

    public function logout()
    {
        return $this->authService->logout();
    }

    public function refresh()
    {
        return $this->authService->refresh();
    }
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:6|different:currentPassword',
        ]);


        if ($validator->fails()) {

            return errorResponse("Validation error", $validator->errors(), 422);
        }

        $user = $this->authService->changePassword($request);
        return successResponse([], "Password changed successfully", 200);
    }
}
