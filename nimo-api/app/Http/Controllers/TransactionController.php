<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Transaction;
use App\Models\Card;
use App\Models\IncomeRelation;
use App\Models\RecurringRecord;
use Illuminate\Database\Eloquent\Relations\Relation;

class TransactionController extends Controller
{

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'concept' => 'required|string|max:32',
            'amount' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_date' => 'required|date',
            'accounting_date' => 'required|date',
            'place' => 'sometimes|max:64',
            'notes' => 'sometimes|max:128',
            'category_id' => 'required|numeric',
            'type_id' => 'required|numeric',
            'card_id' => 'required|numeric',
            'second_card_id' => 'sometimes|numeric'
        ]);

        //  Reservado para Ingresos y gastos
        if ($request->type_id != 3) {
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
                'user_id' => $user->id
            ]);

            $transaction = Transaction::find($transaction->id);

            return response()->json([
                'message' => 'El movimiento fue almacenado correctamente.',
                'data' => $transaction
            ], 201);
        }

        $fromTransaction = Transaction::create([
            'concept' => $request->concept,
            'amount' => $request->amount * (-1),
            'transaction_date' => $request->transaction_date,
            'accounting_date' => $request->accounting_date,
            'place' => $request->place ?? null,
            'notes' => $request->notes ?? null,
            'category_id' => 10,
            'type_id' => 3,
            'card_id' => $request->card_id,
            'user_id' => $user->id
        ]);

        $toTransaction = Transaction::create([
            'concept' => $request->concept,
            'amount' => $request->amount,
            'transaction_date' => $request->transaction_date,
            'accounting_date' => $request->accounting_date,
            'place' => $request->place ?? null,
            'notes' => $request->notes ?? null,
            'category_id' => 12,
            'type_id' => 1,
            'card_id' => $request->second_card_id,
            'user_id' => $user->id
        ]);

        $relation = IncomeRelation::create([
            'amount' => $request->amount,
            'contact_id' => 3,
            'from_id' => $fromTransaction->id,
            'to_id' => $toTransaction->id,
        ]);

        $transaction = Transaction::find($fromTransaction->id);

        return response()->json([
            'message' => 'El movimiento fue almacenado correctamente.',
            'data' => $transaction
        ], 201);
    }

    public function show(string $id)
    {
        $transaction = Transaction::find($id);

        if ($transaction == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $transaction
        ], 200);
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
            'second_card_id' => 'sometimes|numeric',
        ]);

        $transaction = Transaction::with('card')->findOrFail($id);
        $absAmount = $validated['amount'];

        $isTransfer = $transaction->type_id === 3;
        $isCreditIncome = $transaction->type_id === 1 && $transaction->card?->type_id === 2;

        $relation = null;
        $relatedTransaction = null;

        if ($isCreditIncome) {
            $relation = IncomeRelation::where('to_id', $transaction->id)->first();
        } elseif ($isTransfer) {
            $relation = IncomeRelation::where('from_id', $transaction->id)->first();
        }

        if ($relation) {
            $relatedTransaction = Transaction::findOrFail($isCreditIncome ? $relation->from_id : $relation->to_id);

            // Validar si el ingreso no supera el gasto
            if ($isCreditIncome) {
                $sumOther = IncomeRelation::where('to_id', $transaction->id)
                    ->where('from_id', '!=', $relation->from_id)
                    ->sum('amount');
                $newTotal = $sumOther + $absAmount;

                if ((-1 * $relatedTransaction->amount) < $newTotal) {
                    return response()->json([
                        'message' => 'La actualización del importe provoca que la sumatoria de ingresos vinculados supere el importe del gasto.',
                    ], 409);
                }
            }
        }

        // Actualiza la transacción principal
        $transaction->fill([
            'concept' => $validated['concept'],
            'amount' => ($isCreditIncome || $transaction->type_id === 1) ? $absAmount : $absAmount * -1,
            'transaction_date' => $validated['transaction_date'],
            'accounting_date' => $validated['accounting_date'],
            'place' => $validated['place'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'category_id' => $isCreditIncome ? 12 : ($isTransfer ? 10 : $validated['category_id']),
            'card_id' => $validated['card_id'],
        ]);
        $transaction->save();

        // Actualiza la transacción relacionada (si existe)
        if ($relatedTransaction) {
            $relatedTransaction->fill([
                'concept' => $validated['concept'],
                'transaction_date' => $validated['transaction_date'],
                'accounting_date' => $validated['accounting_date'],
                'place' => $validated['place'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($isTransfer) {
                $relatedTransaction->amount = $absAmount;
                $relatedTransaction->card_id = $validated['second_card_id'] ?? $relatedTransaction->card_id;
            }

            $relatedTransaction->save();

            // Actualiza la relación
            $relation->amount = $absAmount;
            $relation->save();

            return response()->json([
                'message' => $isTransfer
                    ? 'Transferencia actualizada correctamente.'
                    : 'Ingreso relacionado actualizado correctamente.',
                'data' => $transaction,
            ], 200);
        }

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data' => $transaction,
        ], 200);
    }


    public function destroy(string $id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->type_id == 1 && $transaction->card->type_id == 2) {
            $relation = IncomeRelation::where('to_id', $id)->first();
            if ($relation) {
                $relatedTransaction = Transaction::findOrFail($relation->from_id);
                $relation->delete();
                $relatedTransaction->delete();
            }
        }

        if ($transaction->type_id == 3) {
            $relation = IncomeRelation::where('from_id', $id)->first();
            if ($relation) {
                $relatedTransaction = Transaction::findOrFail($relation->to_id);
                $relation->delete();
                $relatedTransaction->delete();
            }
        }

        // Validaciones de ingresos
        if ($transaction->type_id == 1 && $transaction->card->type_id != 2) {
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

        //Validacion si es recurrente
        $recurring = RecurringRecord::where('transaction_id', $id)->first();
        if ($recurring != null) {
            $recurring->delete();
        }

        // Elimina la transacción principal
        $transaction->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }

    public function getYearsWith(Request $request)
    {
        $validated = $request->validate([
            'year' => 'sometimes|string|max:4'
        ]);

        $user = $request->user();

        $years = Transaction::without(['category', 'type', 'card', 'user'])
            ->where('user_id', $user->id)
            ->when($validated['year'] ?? null, function ($query, $year) {
                $query->whereYear('accounting_date', $year);
            })
            ->selectRaw('YEAR(accounting_date) as year')
            ->groupBy('year')
            ->orderByDesc('year')
            ->pluck('year');

        return response()->json([
            'message' => 'Consulta realizada exitosamente',
            'data' => $years
        ], 200);
    }


    public function getMonthsWith(Request $request)
    {
        $year = $request->query('year'); // Solo tomamos year de la URL

        $user = $request->user();

        $dates = Transaction::without(['category', 'type', 'card', 'user'])
            ->where('user_id', $user->id)
            ->when($year, function ($query, $year) {
                $query->whereYear('accounting_date', $year);
            })
            ->selectRaw('YEAR(accounting_date) as year, MONTH(accounting_date) as month')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->paginate(12);

        return response()->json([
            'message' => 'Consulta realizada exitosamente',
            'year' => $year,
            'data' => $dates,
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

        $creditBalances = [];
        foreach ($creditCards as $card) {
            $cardTransactions = $transactions[$card->id] ?? collect();

            $periodTx = $cardTransactions->filter(function ($tx) use ($lastDayPrev, $lastDayCurr) {
                return $tx->accounting_date <= $lastDayCurr;
            });

            $bills = $periodTx->where('type_id', 2)->sum('amount');
            $payments = $periodTx->where('type_id', 1)->sum('amount');

            $difference = $bills + $payments;

            $creditBalances[] = [
                'card' => $card,
                'bills' => $bills,
                'payments' => $payments,
                'difference' => $difference
            ];
        }

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
                'card' => $card,
                'initial_balance' => $initialBalance,
                'final_balance' => $finalBalance,
                'difference' => $difference,
                'incomes' => $incomes,
                'expenses' => $expenses,
                'payments' => $payments
            ];
        }

        return response()->json([
            'message' => 'Balance del mes obtenido exitosamente',
            'data' => [
                'debit' => $debitBalances,
                'credit' => $creditBalances
            ],
        ]);
    }

    public function getMonthBalance($year, $month, Request $request)
    {
        $user = $request->user();

        // Fechas límites
        $lastDayPrev = Carbon::createFromDate($year, $month, 1)->subDay()->endOfDay();
        $lastDayCurr = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        $transactions = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->get();

        // Saldo inicial de debito
        $initialBalance = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '<=', $lastDayPrev)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 1);
            })
            ->sum('amount');

        // Credito
        $currentBills = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '>', $lastDayPrev)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->where('type_id', 2)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 2);
            })
            ->sum('amount');

        $currentPayments = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '>', $lastDayPrev)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->where('type_id', 1)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 2);
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
        $expenses = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '>', $lastDayPrev)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->where('type_id', 2)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 1);
            })
            ->sum('amount');

        $initialBills = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '<=', $lastDayPrev)
            ->where('type_id', '!=', 3)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 2);
            })
            ->sum('amount');

        $finalBills = Transaction::where('user_id', $user->id)
            ->where('accounting_date', '<=', $lastDayCurr)
            ->where('type_id', '!=', 3)
            ->whereIn('card_id', function ($query) {
                $query->select('id')
                    ->from('cards')
                    ->where('type_id', 2);
            })
            ->sum('amount');

        $finalBalance = $initialBalance + $incomes + $expenses + ($currentPayments * -1);
        $projectedFinalBalance = $initialBalance + $incomes + $expenses + $currentBills + $initialBills;

        return response()->json([
            'message' => 'Balance del mes obtenido exitosamente',
            'data' => [
                'credit' => [
                    'expenses' => $currentBills,
                    'payments' => $currentPayments,
                    'initial_bills' => $initialBills,
                    'final_bills' => $finalBills,
                ],
                'debit' => [
                    'initial_balance' => $initialBalance,
                    'incomes' => $incomes,
                    'expenses' => $expenses,
                    'final_balance' => $finalBalance,
                    'projected_final_balance' => $projectedFinalBalance,
                    'difference' => $finalBalance - $initialBalance,
                    'projected_difference' => $projectedFinalBalance - $initialBalance
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

        $perPage = $validated['per_page'] ?? 10;

        $transactions = $query->paginate($perPage);

        /*$transactions->setCollection(
            $transactions->getCollection()->map(function ($t) {
                return [
                    'id' => $t->id,
                    'concept' => $t->concept,
                    'amount' => $t->amount,
                    'type' => $t->type->type,
                    'notes' => $t->notes,
                    'transaction_date' => $t->transaction_date,
                    'accounting_date' => $t->accounting_date,
                    'updated_at' => $t->updated_at->format('Y-m-d H:i:s'),
                    'category_icon' => optional($t->category)->icon,
                    'card_bank_name' => $t->card->bank->name ?? null,
                    'card_numbers' => $t->card->numbers,
                    'card_type' => $t->card->type->type ?? null,
                    'card_network_name' => $t->card->network->name ?? null,
                    'card_network' => $t->card->network->img_path ?? null,
                    'income_relation_count' => $t->income_relations_from_count + $t->income_relations_to_count,
                ];
            })
        );*/

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $transactions
        ]);
    }
}
