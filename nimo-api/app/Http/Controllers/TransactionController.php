<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Card;
use App\Models\IncomeRelation;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            'amount' => ($request->type_id == 1) ? $request->amount : $request->amount * (-1),
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

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'concept' => 'required|string|max:64',
            'amount' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_date' => 'required|date',
            'accounting_date' => 'required|date',
            'place' => 'sometimes|max:64',
            'notes' => 'sometimes|max:128',
            'category_id' => 'required|numeric',
            'card_id' => 'required|numeric',
        ]);

        $transaction = Transaction::findOrFail($id);

        $isIncome = $transaction->type_id == 1;
        $absAmount = $validated['amount'];

        // Validación de ingresos (type_id == 1)
        if ($isIncome) {
            $mainRt = IncomeRelation::where('from_id', $id)->first();
            if ($mainRt) {
                $expense = Transaction::find($mainRt->to_id);
                $sumOtherRelations = IncomeRelation::where('to_id', $expense->id)
                    ->where('from_id', '!=', $id)
                    ->sum('amount');
                $newTotal = $sumOtherRelations + $absAmount;

                if ((-1 * $expense->amount) < $newTotal) {
                    return response()->json([
                        'message' => 'La actualización del importe provoca que la sumatoria de ingresos vinculados supere el importe del gasto.',
                    ], 409);
                }

                $mainRt->amount = $absAmount;
                $mainRt->save();
            }
        }

        // Validación de egresos (type_id != 1)
        if (!$isIncome) {
            $relations = IncomeRelation::where('to_id', $id)->get();
            if ($relations->isNotEmpty()) {
                $sumRt = $relations->sum('amount');
                if ($absAmount < $sumRt) {
                    return response()->json([
                        'message' => 'La actualización del importe del gasto es menor a la sumatoria de sus ingresos vinculados.',
                    ], 409);
                }
            }
        }

        // Actualiza los campos (excepto amount que depende del signo)
        $transaction->fill(array_merge(
            $validated,
            ['amount' => $isIncome ? $absAmount : $absAmount * -1]
        ));

        $transaction->save();

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data' => $transaction
        ], 200);
    }

    public function destroy(string $id)
    {
        $transaction = Transaction::findOrFail($id);

        // Validaciones de ingresos
        if ($transaction->type_id == 1) {
            // Elimina la relación si existe
            IncomeRelation::where('from_id', $id)->delete();
        }

        // Validaciones de egresos
        if ($transaction->type_id == 2) {
            // Obtiene y elimina relaciones vinculadas
            $fromIds = IncomeRelation::where('to_id', $id)->pluck('from_id');

            if ($fromIds->isNotEmpty()) {
                IncomeRelation::where('to_id', $id)->delete();
                Transaction::whereIn('id', $fromIds)->delete(); // Elimina ingresos relacionados
            }
        }

        // Elimina la transacción principal
        $transaction->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }

    public function getMonthsWith(Request $request)
    {
        $validated = $request->validate([
            'year' => 'sometimes|string|max:4'
        ]);

        $user = $request->user();

        $dates = Transaction::without(['category', 'type', 'card', 'user'])
            ->where('user_id', $user->id)
            ->when($validated['year'] ?? null, function ($query, $year) {
                $query->whereYear('accounting_date', $year);
            })
            ->selectRaw('YEAR(accounting_date) as year, MONTH(accounting_date) as month')
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12);

        return response()->json([
            'message' => 'Consulta realizada exitosamente',
            'data' => $dates
        ], 200);
    }

    public function getCardsBalance($year, $month, Request $request)
    {
        $user = $request->user();

        // Cargar tarjetas con relaciones necesarias
        $cards = Card::with(['bank', 'network'])
            ->without('user')
            ->where('user_id', $user->id)
            ->get();

        // Agrupar tarjetas
        $debitCards = $cards->where('type_id', 1);
        $creditCards = $cards->where('type_id', 2);

        // Fechas límites
        $lastDayPrev = Carbon::createFromDate($year, $month, 1)->subDay()->endOfDay();
        $lastDayCurr = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        // Obtener todas las transacciones del usuario con las fechas mínimas necesarias
        $cardIds = $cards->pluck('id');

        $transactions = Transaction::whereIn('card_id', $cardIds)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->get()
            ->groupBy('card_id');

        $debitBalances = [];
        foreach ($debitCards as $card) {
            $cardTransactions = $transactions[$card->id] ?? collect();

            $initialBalance = $cardTransactions->where('accounting_date', '<=', $lastDayPrev)
                ->sum('amount');

            $finalBalance = $cardTransactions->sum('amount');

            $difference = $finalBalance - $initialBalance;

            $periodTx = $cardTransactions->filter(function ($tx) use ($lastDayPrev, $lastDayCurr) {
                return $tx->accounting_date > $lastDayPrev && $tx->accounting_date <= $lastDayCurr;
            });

            $incomes = $periodTx->where('type_id', 1)->sum('amount');
            $expenses = $periodTx->where('type_id', 2)->sum('amount');
            $payments = $periodTx->where('type_id', 3)->sum('amount');

            $debitBalances[] = [
                'card' => [
                    'id' => $card->id,
                    'numbers' => $card->numbers,
                    'color' => $card->color,
                    'bank_name' => $card->bank->name,
                    'bank_img_path' => $card->bank->img_path,
                    'network_img_path' => $card->network->img_path,
                ],
                'initial_balance' => $initialBalance,
                'final_balance' => $finalBalance,
                'difference' => $difference,
                'incomes' => $incomes,
                'expenses' => $expenses,
                'payments' => $payments
            ];
        }

        $creditBalances = [];
        foreach ($creditCards as $card) {
            $cardTransactions = $transactions[$card->id] ?? collect();

            $periodTx = $cardTransactions->filter(function ($tx) use ($lastDayPrev, $lastDayCurr) {
                return $tx->accounting_date > $lastDayPrev && $tx->accounting_date <= $lastDayCurr;
            });

            $bills = $periodTx->where('type_id', 2)->sum('amount');
            $payments = $periodTx->where('type_id', 1)->sum('amount');

            $difference = $bills + $payments;

            $creditBalances[] = [
                'card' => [
                    'id' => $card->id,
                    'numbers' => $card->numbers,
                    'color' => $card->color,
                    'bank_name' => $card->bank->name,
                    'bank_img_path' => $card->bank->img_path,
                    'network_img_path' => $card->network->img_path,
                ],
                'bills' => $bills,
                'payments' => $payments,
                'difference' => $difference
            ];
        }

        return response()->json([
            'message' => 'Balance del mes obtenido exitosamente',
            'data' => [
                'cards' => [
                    'debit' => $debitBalances,
                    'credit' => $creditBalances
                ]
            ]
        ]);
    }

    public function getMonthBalance($year, $month, Request $request)
    {
        $user = $request->user();

        // Fechas límites
        $lastDayPrev = Carbon::createFromDate($year, $month, 1)->subDay()->endOfDay();
        $lastDayCurr = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();
        
        $bills = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '>', $lastDayPrev)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->where('type_id', 2)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 2);
            })
            ->sum('amount');

        $payments = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '>', $lastDayPrev)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->where('type_id', 1)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 2);
            })
            ->sum('amount');

            $initialBalance = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '<=', $lastDayPrev)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 1);
            })
            ->sum('amount');
        $incomes = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '>', $lastDayPrev)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->where('type_id', 1)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 1);
            })
            ->sum('amount');
        $expenses = $bills + Transaction::where('user_id', $user->id)
            ->where('accounting_date', '>', $lastDayPrev)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->where('type_id', 2)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 1);
            })
            ->sum('amount');

        $finalBalance = $initialBalance + $incomes + $expenses;
        
        return response()->json([
            'message' => 'Balance del mes obtenido exitosamente',
            'data' => [
                'credit' => [
                    'expenses' => $bills,
                    'payments' => $payments,
                    'difference' => $bills + $payments
                ],
                'debit' => [
                    'initial_balance' => $initialBalance,
                    'incomes' => $incomes,
                    'expenses' => $expenses,
                    'final_balance' => $finalBalance,
                    'difference' => $finalBalance - $initialBalance
                ]
            ]
        ]);
    }

    public function getTransactions($year, $month, Request $request)
{
    $user = $request->user();

    $validated = $request->validate([
        'concept' => 'sometimes|string|max:64',
        'amount' => ['sometimes', 'regex:/^\d+(\.\d{1,2})?$/'],
        'category_id' => 'sometimes|integer',
        'type_id' => 'sometimes|integer',
        'card_id' => 'sometimes|integer',
        'order_by' => 'required|integer|in:1,2,3,4,5,6',
        'per_page' => 'sometimes|integer|min:1|max:100'
    ]);

    $query = Transaction::with([
            'category',
            'type',
            'card' => function ($query) {
                $query->without('user');
            }
        ])
        ->withCount(['incomeRelationsFrom', 'incomeRelationsTo']) // Conteos de relaciones
        ->without(['user'])
        ->where('user_id', $user->id)
        ->whereYear('accounting_date', $year)
        ->whereMonth('accounting_date', $month);

    if (!empty($validated['concept'])) {
        $query->where('concept', 'LIKE', '%' . $validated['concept'] . '%');
    }

    if (!empty($validated['amount'])) {
        $query->where('amount', $validated['amount']);
    }

    if (!empty($validated['category_id'])) {
        $query->where('category_id', $validated['category_id']);
    }

    if (!empty($validated['type_id'])) {
        $query->where('type_id', $validated['type_id']);
    }

    if (!empty($validated['card_id'])) {
        $query->where('card_id', $validated['card_id']);
    }

    // Ordenamiento
    switch ($validated['order_by']) {
        case 1:
            $query->orderBy('accounting_date', 'asc');
            break;
        case 2:
            $query->orderBy('accounting_date', 'desc');
            break;
        case 3:
            $query->orderBy('transaction_date', 'asc');
            break;
        case 4:
            $query->orderBy('transaction_date', 'desc');
            break;
        case 5:
            $query->orderBy('created_at', 'asc');
            break;
        case 6:
            $query->orderBy('created_at', 'desc');
            break;
    }

    $perPage = $validated['per_page'] ?? 20;

    $transactions = $query->paginate($perPage);

    $transactions->setCollection(
        $transactions->getCollection()->map(function ($t) {
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
                'income_relation_count' => $t->income_relations_from_count + $t->income_relations_to_count,
            ];
        })
    );

    return response()->json([
        'message' => 'Consulta realizada exitosamente.',
        'data' => $transactions
    ]);
}


}
