<?php

namespace App\Http\Controllers;

use App\Services\PermissionService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function __construct(protected PermissionService $permissionService) {}
    public function index()
    {
        return $this->permissionService->permissions();
    }
    public function show($id)
    {
        return $this->permissionService->getUserPermissions($id);
    }

    public function update(Request $request, $id)
    {
        return $this->permissionService->update($request->all(), $id);
    }
}
