<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccountType;

class AccountTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $accountTypes = AccountType::all();

        if ($accountTypes->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron los recursos solicitados.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $accountTypes
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:DEBIT,CREDIT',
        ]);

        $accountType = AccountType::create([
            'type' => $request->type
        ]);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $accountType
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $accountType = AccountType::find($id);

        if ($accountType == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $accountType
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'type' => 'required|in:DEBIT,CREDIT',
        ]);

        $accountType = AccountType::findOrFail($id);

        if ($accountType == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca actualizar.',
            ], 404);
        }

        $accountType->update([
            'type' => $request->type
        ]);

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data' => $accountType
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $accountType = AccountType::findOrFail($id);

        if ($accountType == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca eliminar.',
            ], 404);
        }

        $accountType->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }
}
