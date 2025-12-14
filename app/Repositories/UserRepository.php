<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    public function index()
    {
        return User::with('roles', 'permissions')->get();
    }

    public function create($data)
    {
        info($data);
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);
    }

    public function createUser($request)
    {
        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
            // Can create admin or user
        } elseif ($user->hasRole('admin')) {
            // Can create user only
            if ($request->role === 'admin') {
                return response()->json(['error' => 'Admins cannot create admins'], 403);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
        ]);

        return response()->json($newUser, 201);
    }
}
