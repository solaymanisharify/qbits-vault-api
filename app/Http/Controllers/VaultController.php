<?php

namespace App\Http\Controllers;

use App\Services\VaultService;
use Illuminate\Http\Request;

class VaultController extends Controller
{
    public function __construct(protected VaultService $vaultService) {}

    public function index()
    {
        $vaults = $this->vaultService->getAll(request()->only('search', 'user_id'));

        return $vaults;
    }

    // public function create(Request $request)
    // {
    //     $vault = $this->vaultService->create($request->all());

    //     return $vault;
    // }

    public function store(Request $request)
    {
        $vault = $this->vaultService->store($request->all());
        return $vault;
    }

    public function show($id)
    {
        $vault = $this->vaultService->show($id);
        return view('vaults.show', compact('vault'));
    }

    public function edit($id)
    {
        $vault = $this->vaultService->edit($id);
        return view('vaults.edit', compact('vault'));
    }

    public function update(Request $request, $id)
    {
        $this->vaultService->update($id, $request->validated());

        return redirect()->route('vaults.index')->with('success', 'Vault updated!');
    }

    public function destroy($id)
    {
        $this->vaultService->delete($id);

        return redirect()->route('vaults.index')->with('success', 'Vault deleted!');
    }
}
