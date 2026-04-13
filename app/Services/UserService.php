<?php

namespace App\Services;

use App\Repositories\UserRepository;

class UserService
{
    public function __construct(protected UserRepository $userRepository) {}
    public function findById($id)
    {
        return $this->userRepository->findById($id);
    }
    public function index($request = null)
    {
        $users = $this->userRepository->index($request);

        return successResponse("Successfully fetch all users", $users, 200);
    }
    public function show($id)
    {
        $users = $this->userRepository->show($id);

        return successResponse("Successfully fetch all users", $users, 200);
    }

    public function getAllUsersPermissionByName($name)
    {
        return $this->userRepository->getAllUsersPermissionByName($name);
    }

    public function createUser($request)
    {
        return $this->userRepository->createUser($request);
    }

    public function update($request, $id)
    {
        return $this->userRepository->update($request, $id);
    }
}
