<?php

namespace App\Http\Controllers;

use App\Models\RecurringRecord;
use App\Models\Transaction;
use Illuminate\Http\Request;

class RecurringRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    $validated = $request->validate([
        'recurring_id' => 'required|integer|exists:recurrings,id'
    ]);

    $transactions = Transaction::whereHas('recurringRecord', function ($query) use ($request) {
        $query->where('recurring_id', $request->recurring_id);
    })
    ->with(['category:id,name,icon', 'type:id,type', 'card:id,numbers,color,type_id,bank_id,network_id', 'card.bank:id,name,img_path', 'card.network:id,name,img_path', 'card.type:id,type'])
    ->get([
        'id', 'concept', 'amount', 'transaction_date', 'accounting_date', 'category_id', 'type_id', 'card_id'
    ]);

    if ($transactions->isEmpty()) {
        return response()->json([
            'message' => 'No se encontraron los recursos solicitados.',
        ], 404);
    }

    $data = $transactions->map(function ($t) {
        return [
            'id' => $t->id,
            'concept' => $t->concept,
            'amount' => $t->amount,
            'transaction_date' => $t->transaction_date,
            'accounting_date' => $t->accounting_date,
            'category' => [
                'id' => $t->category->id,
                'name' => $t->category->name,
                'icon' => $t->category->icon,
            ],
            'type' => [
                'id' => $t->type->id,
                'type' => $t->type->type,
            ],
            'card' => [
                'id' => $t->card->id,
                'numbers' => $t->card->numbers,
                'color' => $t->card->color,
                'type' => $t->card->type->type,
                'bank' => [
                    'id' => $t->card->bank->id,
                    'name' => $t->card->bank->name,
                    'img_path' => $t->card->bank->img_path,
                ],
                'network' => [
                    'id' => $t->card->network->id,
                    'name' => $t->card->network->name,
                    'img_path' => $t->card->network->img_path,
                ],
            ],
        ];
    });

    return response()->json([
        'message' => 'Consulta realizada exitosamente.',
        'data' => $data
    ]);
}



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'recurring_id' => 'required|integer|exists:recurrings,id',
            'transaction_id' => 'required|integer|exists:transactions,id'
        ]);

        $record = RecurringRecord::create([
            'recurring_id' => $request->recurring_id,
            'transaction_id' => $request->transaction_id,
        ]);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
