<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Card;

class CardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $cards = Card::where('user_id', $user->id)->get();

        if ($cards->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron los recursos solicitados.',
            ], 404);
        }

        // Agrupar por type_id
        $grouped = $cards->groupBy('type_id')->map(function ($group) {
            return $group->values(); // resetear los índices
        });

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => [
                'debit' => $grouped->first(),
                'credit' => $grouped->last()
            ]
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'numbers' => 'required|string|size:4',
            'color' => 'required|string|size:7',
            'type_id' => 'required|integer|exists:account_types,id',
            'bank_id' => 'required|integer|exists:banks,id',
            'network_id' => 'required|integer|exists:networks,id',
        ]);

        $card = Card::create([
            'numbers' => $request->numbers,
            'color' => $request->color,
            'type_id' => $request->type_id,
            'bank_id' => $request->bank_id,
            'network_id' => $request->network_id,
            'user_id' => $user->id,
        ]);

        $cardData = Card::where('id', $card->id)
            ->first();

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $cardData
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $card = Card::find($id);

        if ($card == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $card
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'numbers' => 'sometimes|string|size:4',
            'color' => 'sometimes|string|size:7',
            'type_id' => 'sometimes|integer|exists:account_types,id',
            'bank_id' => 'sometimes|integer|exists:banks,id',
            'network_id' => 'sometimes|integer|exists:networks,id',
        ]);

        $card = Card::findOrFail($id);

        if ($card == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca actualizar.',
            ], 404);
        }

        $card->update([
            'numbers' => $request->numbers,
            'color' => $request->color,
            'type_id' => $request->type_id,
            'bank_id' => $request->bank_id,
            'network_id' => $request->network_id,
        ]);

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data' => $card
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $card = Card::findOrFail($id);

        if ($card == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca eliminar.',
            ], 404);
        }

        $hasTransactions = $card->transactions()->exists();
        if ($hasTransactions) {
            return response()->json([
                'message' => 'No se puede eliminar la tarjeta porque tiene transacciones asociadas.',
            ], 400);
        }

        $card->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }
}
