<?php

namespace App\Http\Controllers;

use App\Models\IncomeRelation;
use App\Models\Transaction;
use Illuminate\Http\Request;

class IncomeRelationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'from_id' => 'sometimes|numeric',
            'to_id' => 'sometimes|numeric',
            'contact_id' => 'sometimes|numeric',
            'month' => 'sometimes|numeric|min:1|max:12',
            'year' => 'sometimes|numeric|min:1900',
            'page' => 'sometimes|numeric|min:1',
        ]);

        $query = IncomeRelation::query();

        if ($request->has('from_id')) {
            $query->where('from_id', $request->from_id);
        }

        if ($request->has('to_id')) {
            $query->where('to_id', $request->to_id);
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        if ($request->has('month') && $request->has('year')) {
            $query->whereHas('fromTransaction', function ($q) use ($request) {
                $q->whereMonth('transaction_date', $request->month)
                    ->whereYear('transaction_date', $request->year);
            });
        }

        // Define el número de elementos por página según si hay filtros de fecha
        $perPage = ($request->has('month') && $request->has('year')) ? 10 : 5;

        $paginatedRelations = $query->with([
            'fromTransaction.category',
            'fromTransaction.card.bank',
            'fromTransaction.card.type',
            'fromTransaction.card.network',
            'toTransaction.category',
            'toTransaction.card.bank',
            'toTransaction.card.type',
            'toTransaction.card.network',
            'contact'
        ])->paginate($perPage);


        $transformedData = $paginatedRelations->getCollection()->map(function ($relation) {
            $transform = function ($t) {
                return [
                    'id' => $t->id,
                    'concept' => $t->concept,
                    'amount' => $t->amount,
                    'transaction_date' => $t->transaction_date,
                    'accounting_date' => $t->accounting_date,
                    'category_icon' => optional($t->category)->icon,
                    'card_bank_name' => $t->card->bank->name ?? null,
                    'card_numbers' => $t->card->numbers,
                    'card_type' => $t->card->type->type ?? null,
                    'card_network' => $t->card->network->img_path ?? null,
                    'card_network_name' => $t->card->network->name ?? null,
                    'updated_at' => $t->updated_at,
                    'type' => $t->type->type,
                    'notes' => $t->notes,
                ];
            };

            return [
                'id' => $relation->id,
                'amount' => $relation->amount,
                'contact' => $relation->contact,
                'from_transaction' => $relation->fromTransaction ? $transform($relation->fromTransaction) : null,
                'to_transaction' => $relation->toTransaction ? $transform($relation->toTransaction) : null,
            ];
        });

        return response()->json([
            'message' => 'Consulta realizada exitosamente',
            'data' => $transformedData,
            'pagination' => [
                'current_page' => $paginatedRelations->currentPage(),
                'per_page' => $paginatedRelations->perPage(),
                'total' => $paginatedRelations->total(),
                'last_page' => $paginatedRelations->lastPage(),
                'next_page_url' => $paginatedRelations->nextPageUrl(),
                'prev_page_url' => $paginatedRelations->previousPageUrl(),
            ]
        ]);
    }




    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'from_id' => 'required|numeric',
            'to_id' => 'required|numeric',
            'contact_id' => 'required|numeric',
        ]);

        $fromTr = Transaction::find($request->from_id);
        if ($fromTr->type_id != 1) {
            return response()->json([
                'message' => 'La transacción emirosra no es un ingreso.',
            ], 409);
        }

        $toTr = Transaction::find($request->to_id);
        if ($toTr->type_id != 2) {
            return response()->json([
                'message' => 'La transacción destinataria no es un gasto.',
            ], 409);
        }

        $fromRd = IncomeRelation::where('from_id', $fromTr->id)->first();
        if ($fromRd != null) {
            return response()->json([
                'message' => 'La transacción emisora ya está vinculada a otro destinatario.',
            ], 409);
        }

        $toRelations = IncomeRelation::where('to_id', $request->to_id)->sum('amount') + $request->amount;
        if (((-1) * $toTr->amount) < $toRelations) {
            return response()->json([
                'message' => 'La sumatoria de pagos vinculados supera el monto del gasto destinatario.',
            ], 400);
        }

        $incomeRelation = IncomeRelation::create([
            'amount' => $request->amount,
            'from_id' => $request->from_id,
            'to_id' => $request->to_id,
            'contact_id' => $request->contact_id,
        ]);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'toRelations' => $toRelations,
            'toTrAmount' => $toTr->amount,
            'data' => $incomeRelation

        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $incomeRelation = IncomeRelation::find($id);

        if ($incomeRelation == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $incomeRelation
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'from_id' => 'required|numeric',
            'to_id' => 'required|numeric',
            'contact_id' => 'required|numeric',
        ]);

        $toTr = Transaction::find($request->to_id);

        if ($toTr->type_id != 2) {
            return response()->json([
                'message' => 'La transacción destinataria no es un gasto.',
            ], 400);
        }

        $toRelations = IncomeRelation::where('to_id', $request->to_id)->sum('amount') + $request->amount;

        if (((-1) * $toTr->amount) < $toRelations) {
            return response()->json([
                'message' => 'La sumatoria de pagos vinculados supera el monto del gasto destinatario.',
            ], 400);
        }

        $incomeRelation = IncomeRelation::update([
            'amount' => $request->amount,
            'from_id' => $request->from_id,
            'to_id' => $request->to_id,
            'contact_id' => $request->contact_id,
        ]);

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'toRelations' => $toRelations,
            'toTrAmount' => $toTr->amount,
            'data' => $incomeRelation

        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $incomeRelation = IncomeRelation::findOrFail($id);

        if ($incomeRelation == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca eliminar.',
            ], 404);
        }

        $incomeRelation->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }

    public function verifyIncomeRelation(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'to_id' => 'required|numeric',
        ]);

        $expense = Transaction::find($request->to_id);

        if ($expense == null) {
            return response()->json([
                'message' => 'La transacción destinataria no existe.',
            ], 409);
        }

        if ($expense->type_id != 2) {
            return response()->json([
                'message' => 'La transacción destinataria no es un gasto.',
            ], 409);
        }

        $sumIr = IncomeRelation::where('to_id', $request->to_id)->sum('amount') + $request->amount;

        if ((-1) * $expense->amount < $sumIr) {
            return response()->json([
                'message' => 'La transacción que intenta vincular junto a los demás pagos vinculados, supera el importe del gasto.',
            ], 409);
        }

        return response()->json([
            'message' => 'Verificación pasada con éxito.'
        ], 200);
    }
}
