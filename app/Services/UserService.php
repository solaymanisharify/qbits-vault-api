<?php

namespace App\Services;

use App\Repositories\UserRepository;

class UserService
{
    public function __construct(protected UserRepository $userRepository) {}
    public function index()
    {
        return $this->userRepository->index();
    }

    public function createUser($request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,user', // Super can't create super
        ]);

        return $this->userRepository->createUser($request);
    }
}
