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

        $recurrings = Recurring::where('user_id', $user->id)->paginate(20);

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
            'type_id' => 'required|numeric'
        ]);

        $recurring = Recurring::create([
            'concept' => $request->concept,
            'amount' => ($request->type_id == 1) ? $request->amount : $request->amount * (-1),
            'category_id' => $request->category_id,
            'type_id' => $request->type_id,
            'user_id' => $user->id
        ]);

        $recurring->with(['category', 'type']);

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
            'type_id' => 'required|numeric'
        ]);

        $recurring = Recurring::findOrFail($id);

        $adjustedAmount = ($request->type_id == 1) ? $request->amount : $request->amount * (-1);

        // Actualizar el recurring
        $recurring->update([
            'concept' => $request->concept,
            'amount' => $adjustedAmount,
            'category_id' => $request->category_id,
            'type_id' => $request->type_id,
            'user_id' => $user->id
        ]);

        // Buscar los records relacionados
        $records = RecurringRecord::where('recurring_id', $id)->get();

        // Obtener los IDs de las transacciones
        $transactionIds = $records->pluck('transaction_id');

        // Actualizar todas las transacciones relacionadas
        Transaction::whereIn('id', $transactionIds)->update([
            'concept' => $request->concept,
            'amount' => $adjustedAmount,
            'category_id' => $request->category_id,
            'type_id' => $request->type_id
        ]);

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
