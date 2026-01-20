<?php

namespace App\Http\Controllers;

use App\Services\RoleService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roleService) {}
    public function index(Request $request)
    {
        return $this->roleService->index($request->all());
    }
    public function store(Request $request)
    {
        return $this->roleService->create($request->all());
    }
    public function update(Request $request, $id)
    {
        return $this->roleService->update($request->all(), $id);
    }
    public function destroy($id)
    {
        return $this->roleService->delete($id);
    }
}
