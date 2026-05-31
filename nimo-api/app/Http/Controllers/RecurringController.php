<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Recurring;
use App\Models\RecurringRecord;
use App\Models\Transaction;

class RecurringController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'concept' => 'sometimes|string|max:64',
            'category_id' => 'sometimes|integer',
            'type_id' => 'sometimes|integer',
            'card_id' => 'sometimes|integer',
            'include_inactive' => 'sometimes|in:true,false,1,0',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $includeInactive = filter_var(
            $validated['include_inactive'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $query = Recurring::where('user_id', $user->id)
            ->when(!$includeInactive, function ($query) {
                $query->where('active', true);
            })
            ->when($validated['concept'] ?? null, function ($query, $concept) {
                $query->where('concept', 'LIKE', '%' . trim($concept) . '%');
            })
            ->when($validated['category_id'] ?? null, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($validated['type_id'] ?? null, function ($query, $typeId) {
                $query->where('type_id', $typeId);
            })
            ->when($validated['card_id'] ?? null, function ($query, $cardId) {
                $query->where('card_id', $cardId);
            })
            ->orderByDesc('created_at');

        $recurrings = $query->paginate($validated['per_page'] ?? 20);

        if ($recurrings->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron los recursos solicitados.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $recurrings
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'concept' => 'required|string|max:64',
            'amount' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],     
            'category_id' => 'required|numeric',
            'type_id' => 'required|numeric',
            'card_id' => 'required|numeric|exists:cards,id',
            'active' => 'sometimes|boolean'
        ]);

        $recurring = Recurring::create([
            'concept' => $request->concept,
            'amount' => ($request->type_id == 1) ? $request->amount : $request->amount * (-1),
            'category_id' => $request->category_id,
            'type_id' => $request->type_id,
            'card_id' => $request->card_id,
            'active' => $request->boolean('active', true),
            'user_id' => $user->id
        ]);

        $recurring = Recurring::find($recurring->id);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $recurring
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $recurring = Recurring::find($id);

        if ($recurring == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $recurring
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $validated = $request->validate([
            'concept' => 'required|string|max:64',
            'amount' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            'category_id' => 'required|numeric',
            'type_id' => 'required|numeric',
            'card_id' => 'required|numeric|exists:cards,id',
            'active' => 'sometimes|boolean',
            'update_generated_transactions' => 'sometimes|boolean',
        ]);

        $recurring = Recurring::where('user_id', $user->id)->findOrFail($id);

        $adjustedAmount = ($request->type_id == 1) ? $request->amount : $request->amount * (-1);
        $updateGeneratedTransactions = $request->boolean('update_generated_transactions', false);

        // Actualizar el recurring
        $recurring->update([
            'concept' => $request->concept,
            'amount' => $adjustedAmount,
            'category_id' => $request->category_id,
            'type_id' => $request->type_id,
            'card_id' => $request->card_id,
            'active' => $request->boolean('active', $recurring->active),
            'user_id' => $user->id
        ]);

        if ($updateGeneratedTransactions) {
            // Buscar los records relacionados
            $records = RecurringRecord::where('recurring_id', $id)->get();

            // Obtener los IDs de las transacciones
            $transactionIds = $records->pluck('transaction_id');

            // Actualizar todas las transacciones relacionadas
            Transaction::where('user_id', $user->id)
                ->whereIn('id', $transactionIds)
                ->update([
                    'concept' => $request->concept,
                    'amount' => $adjustedAmount,
                    'category_id' => $request->category_id,
                    'type_id' => $request->type_id,
                    'card_id' => $request->card_id
                ]);
        }

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data' => $recurring
        ]);
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $recurring = Recurring::find($id);

        if (!$recurring) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca eliminar.',
            ], 404);
        }

        // Obtener los registros relacionados
        $records = RecurringRecord::where('recurring_id', $recurring->id)->get();

        // Obtener los IDs de las transacciones asociadas
        $transactionIds = $records->pluck('transaction_id');

        // Eliminar las transacciones
        Transaction::whereIn('id', $transactionIds)->delete();

        // Eliminar los registros recurrentes
        RecurringRecord::where('recurring_id', $recurring->id)->delete();

        // Finalmente eliminar el recurring
        $recurring->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }

}
