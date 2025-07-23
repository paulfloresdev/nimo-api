<?php

namespace App\Http\Controllers;

use App\Models\IncomeRelation;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Exports\IncomeRelationsExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class IncomeRelationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function getAllFiltered(Request $request)
    {
        $validated = $request->validate([
            'from_id' => 'sometimes|numeric',
            'to_id' => 'sometimes|numeric',
            'contact_id' => 'sometimes|numeric',
            'month' => 'sometimes|numeric|min:1|max:12',
            'year' => 'sometimes|numeric|min:1900',
            'page' => 'sometimes|numeric|min:1',
            'exclude_payments' => 'sometimes|boolean',
        ]);

        $query = IncomeRelation::query();

        if ($request->filled('exclude_payments')) {
            $query->where('contact_id', '!=', 3);
        }

        if ($request->filled('from_id')) {
            $query->where('from_id', $request->from_id);
        }

        if ($request->filled('to_id')) {
            $query->where('to_id', $request->to_id);
        }

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereHas('fromTransaction', function ($q) use ($request) {
                $q->whereMonth('transaction_date', $request->month)
                    ->whereYear('transaction_date', $request->year);
            });
        }

        $perPage = ($request->has('month') && $request->has('year')) ? 12 : 4;

        // 💡 Forzamos la página desde el cuerpo del request
        $currentPage = $request->input('page', 1);

        // 🔁 Calculamos el total antes de paginar
        $totalAmount = (clone $query)
            ->with('fromTransaction')
            ->get()
            ->pluck('fromTransaction.amount')
            ->filter()
            ->sum();

        $incomeRelations = $query->with([
            'fromTransaction.category',
            'fromTransaction.card.bank',
            'fromTransaction.card.type',
            'fromTransaction.card.network',
            'toTransaction.category',
            'toTransaction.card.bank',
            'toTransaction.card.type',
            'toTransaction.card.network',
            'contact',
        ])->paginate($perPage, ['*'], 'page', $currentPage);

        return response()->json([
            'message' => 'Datos obtenidos correctamente.',
            'data' => $incomeRelations,
            'total_amount' => $totalAmount,
        ], 200);
    }

    public function exportExcel(Request $request): StreamedResponse|Response
    {
        $validated = $request->validate([
            'month' => 'required|numeric|min:1|max:12',
            'year' => 'required|numeric|min:1900',
            'contact_id' => 'required|numeric',
        ]);

        $query = IncomeRelation::query();

        $query->where('contact_id', $request->contact_id);

        $query->whereHas('fromTransaction', function ($q) use ($request) {
            $q->whereMonth('transaction_date', $request->month)
                ->whereYear('transaction_date', $request->year);
        });

        $data = $query->with([
            'fromTransaction.category',
            'fromTransaction.card.bank',
            'fromTransaction.card.type',
            'toTransaction.category',
            'toTransaction.card.bank',
            'toTransaction.card.type',
            'contact',
        ])->get()
            ->sortBy(function ($item) {
                return optional($item->toTransaction)->transaction_date;
            })
            ->values();

        if ($data->isEmpty()) {
            // Retornar HTML simple con mensaje si se accede desde navegador
            return response(
                "<h2 style='font-family:sans-serif;color:#444;margin:2rem'>No se encontraron datos para exportar.</h2>",
                200,
                ['Content-Type' => 'text/html']
            );
        }

        $contact = $data->first()->contact;
        $fileName = 'Cuentas_' . ($contact->alias ?? 'Desconocido') . "_{$request->year}-{$request->month}.xlsx";

        return Excel::download(new IncomeRelationsExport($data, $contact->alias, $request->month, $request->year), $fileName);
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
