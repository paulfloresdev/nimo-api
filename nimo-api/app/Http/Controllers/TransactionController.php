<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;

class TransactionController extends Controller
{

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'concept' => 'required|string|max:64',
            'amount' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_date' => 'required|date',
            'accounting_date' => 'required|date',
            'place' => 'sometimes|max:64',
            'notes' => 'sometimes|max:128',
            'category_id' => 'required|numeric',
            'type_id' => 'required|numeric',
            'card_id' => 'required|numeric',
            'user_id' => 'required|numeric',
        ]);

        $transaction = Transaction::create([
            'concept' => $request->concept,
            'amount' => $request->amount,
            'transaction_date' => $request->transaction_date,
            'accounting_date' => $request->accounting_date,
            'place' => $request->place ?? null,
            'notes' => $request->notes ?? null,
            'category_id' => $request->category_id,
            'type_id' => $request->type_id,
            'card_id' => $request->card_id,
            'user_id' => $request->user_id
        ]);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $transaction
        ], 201);
    }

    public function getMonthsWithTransactions(Request $request)
    {
        
    }
}
