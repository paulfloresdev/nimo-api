<?php

namespace App\Http\Controllers;

use App\Models\RecurringRecord;
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

        $records = RecurringRecord::where('recurring_id', $request->recurring_id)->get();

        if($records->isEmpty()){
            return response()->json([
                'message' => 'No se encontraron los recursos solicitados.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $records
        ], 200);
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
