<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TransactionType;

class TransactionTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $transactionTypes = TransactionType::all();

        if ($transactionTypes->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron los recursos solicitados.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $transactionTypes
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:INCOME,EXPENSE,PAYMENT',
        ]);

        $transactionType = TransactionType::create([
            'type' => $request->type
        ]);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $transactionType
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transactionType = TransactionType::find($id);

        if ($transactionType == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $transactionType
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'type' => 'required|in:INCOME,EXPENSE,PAYMENT',
        ]);

        $transactionType = TransactionType::findOrFail($id);

        if ($transactionType == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca actualizar.',
            ], 404);
        }

        $transactionType->update([
            'type' => $request->type
        ]);

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data' => $transactionType
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $transactionType = TransactionType::findOrFail($id);

        if ($transactionType == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca eliminar.',
            ], 404);
        }

        $transactionType->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }
}
