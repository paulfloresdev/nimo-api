<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Transaction;
use App\Models\Card;
use App\Models\IncomeRelation;
use App\Models\Recurring;
use App\Models\RecurringRecord;
use App\Models\MonthlySubBudgetTransaction;
use App\Models\MonthlySubBudget;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;


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
            'second_card_id' => 'sometimes|numeric',
            'monthly_sub_budget_id' => 'sometimes|nullable|integer|exists:monthly_sub_budgets,id'
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
            $this->syncMonthlySubBudgetLink($transaction, $validated['monthly_sub_budget_id'] ?? null, $user->id);

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

    public function generateFromRecurring(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'recurring_id' => 'required|numeric|exists:recurrings,id',
            'date' => 'required|date',
            'accounting_date' => 'sometimes|date',
            'times' => 'required|integer|min:1',
            'period' => 'required|string|in:day,week,month,year',
            'index' => 'required|boolean'
        ]);

        $recurring = Recurring::find($validated['recurring_id']);

        if ($recurring->user_id != $user->id) {
            return response()->json([
                'message' => 'Movimiento recurrente no pertenece al usuario actual.'
            ], 403);
        }

        $transactions = [];
        $baseDate = Carbon::parse($validated['date']);
        $baseAccountingDate = isset($validated['accounting_date'])
            ? Carbon::parse($validated['accounting_date'])
            : $baseDate->copy();

        for ($i = 0; $i < $validated['times']; $i++) {
            $date = $baseDate->copy();
            $accountingDate = $baseAccountingDate->copy();

            if ($i > 0) {
                switch ($validated['period']) {
                    case 'day':
                        $date->addDays($i);
                        $accountingDate = $date->copy();
                        break;
                    case 'week':
                        $date->addWeeks($i);
                        $accountingDate = $date->copy();
                        break;
                    case 'month':
                        $date->addMonths($i);
                        $accountingDate->addMonths($i);
                        break;
                    case 'year':
                        $date->addYears($i);
                        $accountingDate->addYears($i);
                        break;
                }
            }

            $concept = $validated['index']
                ? $recurring->concept . ' (' . ($i + 1) . '/' . $validated['times'] . ')'
                : $recurring->concept;

            $transaction = Transaction::create([
                'concept' => $concept,
                'amount' => $recurring->amount,
                'transaction_date' => $date->toDateString(),
                'accounting_date' => $accountingDate->toDateString(),
                'category_id' => $recurring->category_id,
                'card_id' => $recurring->card_id,
                'type_id' => $recurring->type_id,
                'user_id' => $user->id
            ]);

            RecurringRecord::create([
                'recurring_id' => $recurring->id,
                'transaction_id' => $transaction->id,
            ]);

            $transactions[] = Transaction::find($transaction->id);
        }

        return response()->json([
            'message' => 'Movimientos generados correctamente.',
            'data' => $transactions
        ], 201);
    }

    public function getMonthlyExpenseStats($year, $month, Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'discount_income_relations' => 'sometimes|boolean'
        ]);

        $discountIncomeRelations = filter_var(
            $validated['discount_income_relations'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Obtener gastos del mes con categoría
        $expenses = Transaction::with('category')
            ->where('user_id', $user->id)
            ->where('type_id', 2)
            ->whereBetween('accounting_date', [$startDate, $endDate])
            ->get();

        // Obtener descuentos (income relations) agrupados por gasto
        $incomeRelationsByExpense = IncomeRelation::select('to_id', DB::raw('SUM(amount) as total_related'))
            ->whereIn('to_id', $expenses->pluck('id'))
            ->groupBy('to_id')
            ->pluck('total_related', 'to_id');

        $categories = [];
        $totalGrossExpenses = 0;
        $totalIncomeRelationsApplied = 0;
        $totalNetExpenses = 0;

        foreach ($expenses as $expense) {
            $categoryId = $expense->category_id;
            $categoryName = $expense->category->name ?? null;
            $categoryIcon = $expense->category->icon ?? null;

            $grossAmount = abs((float) $expense->amount);
            $relatedAmount = (float) ($incomeRelationsByExpense[$expense->id] ?? 0);
            $netAmount = $grossAmount - $relatedAmount;

            if (!isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'icon' => $categoryIcon,
                    'gross_expenses' => 0,
                    'income_relations_discount' => 0,
                    'net_expenses' => 0,
                    'display_total' => 0,
                ];
            }

            $categories[$categoryId]['gross_expenses'] += $grossAmount;
            $categories[$categoryId]['income_relations_discount'] += $relatedAmount;
            $categories[$categoryId]['net_expenses'] += $netAmount;

            $totalGrossExpenses += $grossAmount;
            $totalIncomeRelationsApplied += $relatedAmount;
            $totalNetExpenses += $netAmount;
        }

        // Total de ingresos del mes
        $totalMonthlyIncome = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type_id', 1)
            ->whereBetween('accounting_date', [$startDate, $endDate])
            ->sum('amount');

        // Ingresos independientes de pagos de contactos
        $independentIncome = $totalMonthlyIncome - $totalIncomeRelationsApplied;

        // Evitar negativos por inconsistencias de datos
        if ($independentIncome < 0) {
            $independentIncome = 0;
        }

        // Definir cuál total mostrar según el filtro
        foreach ($categories as &$category) {
            $category['display_total'] = $discountIncomeRelations
                ? round($category['net_expenses'], 2)
                : round($category['gross_expenses'], 2);

            $category['gross_expenses'] = round($category['gross_expenses'], 2);
            $category['income_relations_discount'] = round($category['income_relations_discount'], 2);
            $category['net_expenses'] = round($category['net_expenses'], 2);
        }
        unset($category);

        // Acumulado de income relations por contact en el mes
        $incomeRelationsByContact = IncomeRelation::query()
            ->join('transactions as expense_transactions', 'expense_transactions.id', '=', 'income_relations.to_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'income_relations.contact_id')
            ->where('expense_transactions.user_id', $user->id)
            ->where('expense_transactions.type_id', 2)
            ->whereBetween('expense_transactions.accounting_date', [$startDate, $endDate])
            ->select(
                'income_relations.contact_id',
                'contacts.alias',
                DB::raw('SUM(income_relations.amount) as total_amount')
            )
            ->groupBy('income_relations.contact_id', 'contacts.alias')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->contact_id,
                    'alias' => $item->alias,
                    'total_amount' => round((float) $item->total_amount, 2),
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Estadísticas mensuales de gastos obtenidas exitosamente.',
            'data' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'discount_income_relations' => $discountIncomeRelations,
                'totals' => [
                    'gross_expenses' => round($totalGrossExpenses, 2),
                    'income_relations_discount' => round($totalIncomeRelationsApplied, 2),
                    'net_expenses' => round($totalNetExpenses, 2),
                    'display_total' => round(
                        $discountIncomeRelations ? $totalNetExpenses : $totalGrossExpenses,
                        2
                    ),
                    'total_monthly_income' => round($totalMonthlyIncome, 2),
                    'independent_income' => round($independentIncome, 2),
                ],
                'expenses_by_category' => array_values($categories),
                'income_relations_by_contact' => $incomeRelationsByContact,
            ]
        ], 200);
    }

    public function show(string $id)
    {
        $transaction = Transaction::with('monthlySubBudgetLinks.monthlySubBudget')->find($id);

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
            'monthly_sub_budget_id' => 'sometimes|nullable|integer|exists:monthly_sub_budgets,id',
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
        $this->syncMonthlySubBudgetLink($transaction, $validated['monthly_sub_budget_id'] ?? null, $request->user()->id);
        $this->recalculateLinkedSubBudgets($transaction->id);

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

        $linkedSubBudgets = MonthlySubBudgetTransaction::where('transaction_id', $id)
            ->with('monthlySubBudget')
            ->get()
            ->pluck('monthlySubBudget')
            ->filter();

        MonthlySubBudgetTransaction::where('transaction_id', $id)->delete();

        // Elimina la transacción principal
        $transaction->delete();

        foreach ($linkedSubBudgets as $subBudget) {
            $subBudget->recalculateAdjustment();
        }

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }

    private function recalculateLinkedSubBudgets(int $transactionId): void
    {
        $links = MonthlySubBudgetTransaction::where('transaction_id', $transactionId)
            ->with('monthlySubBudget')
            ->get();

        foreach ($links as $link) {
            if ($link->monthlySubBudget) {
                $link->monthlySubBudget->recalculateAdjustment();
            }
        }
    }

    private function syncMonthlySubBudgetLink(Transaction $transaction, ?int $subBudgetId, int $userId): void
    {
        $existingLinks = MonthlySubBudgetTransaction::where('transaction_id', $transaction->id)
            ->with('monthlySubBudget')
            ->get();

        foreach ($existingLinks as $link) {
            if (!$this->transactionMatchesSubBudget($transaction, $link->monthlySubBudget)) {
                $subBudget = $link->monthlySubBudget;
                $link->delete();
                $subBudget?->recalculateAdjustment();
            }
        }

        if (!$subBudgetId) {
            return;
        }

        $subBudget = MonthlySubBudget::where('user_id', $userId)->findOrFail($subBudgetId);

        if (!$this->transactionMatchesSubBudget($transaction, $subBudget)) {
            return;
        }

        MonthlySubBudgetTransaction::firstOrCreate([
            'monthly_sub_budget_id' => $subBudget->id,
            'transaction_id' => $transaction->id,
        ]);

        $subBudget->recalculateAdjustment();
    }

    private function transactionMatchesSubBudget(Transaction $transaction, ?MonthlySubBudget $subBudget): bool
    {
        if (!$subBudget) {
            return false;
        }

        return (int) $transaction->type_id === 2
            && (int) $transaction->category_id === (int) $subBudget->category_id
            && (int) $transaction->id !== (int) $subBudget->adjustment_transaction_id
            && (int) date('Y', strtotime($transaction->accounting_date)) === (int) $subBudget->year
            && (int) date('n', strtotime($transaction->accounting_date)) === (int) $subBudget->month;
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

            // Transacciones hasta fin de mes anterior (balance inicial)
            $prevTx = $cardTransactions->filter(fn($tx) => $tx->accounting_date <= $lastDayPrev);
            $prevBills = $prevTx->where('type_id', 2)->sum('amount');     // negativos
            $prevPayments = $prevTx->where('type_id', 1)->sum('amount');  // positivos
            $initialBalance = $prevBills + $prevPayments;

            // Transacciones del mes actual
            $periodTx = $cardTransactions->filter(
                fn($tx) =>
                $tx->accounting_date > $lastDayPrev && $tx->accounting_date <= $lastDayCurr
            );

            $currentBills = $periodTx->where('type_id', 2)->sum('amount');     // negativos
            $currentPayments = $periodTx->where('type_id', 1)->sum('amount');  // positivos

            $finalBalance = $initialBalance + $currentBills + $currentPayments;

            $creditBalances[] = [
                'card' => $card,
                'initial_balance' => round($initialBalance, 2),
                'bills' => round($currentBills, 2),
                'payments' => round($currentPayments, 2),
                'final_balance' => round($finalBalance, 2)
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

        $lastDayPrev = Carbon::createFromDate($year, $month, 1)->subDay()->endOfDay();
        $lastDayCurr = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        $initialBalance = round(
            Transaction::where('user_id', $user->id)
                ->where('accounting_date', '<=', $lastDayPrev)
                ->whereIn('card_id', function ($query) {
                    $query->select('id')->from('cards')->where('type_id', 1);
                })
                ->sum('amount'),
            2
        );

        $currentBills = round(
            Transaction::where('user_id', $user->id)
                ->where('accounting_date', '>', $lastDayPrev)
                ->where('accounting_date', '<=', $lastDayCurr)
                ->where('type_id', 2)
                ->whereIn('card_id', function ($query) {
                    $query->select('id')->from('cards')->where('type_id', 2);
                })
                ->sum('amount'),
            2
        );

        $currentPayments = round(
            Transaction::where('user_id', $user->id)
                ->where('accounting_date', '>', $lastDayPrev)
                ->where('accounting_date', '<=', $lastDayCurr)
                ->where('type_id', 1)
                ->whereIn('card_id', function ($query) {
                    $query->select('id')->from('cards')->where('type_id', 2);
                })
                ->sum('amount'),
            2
        );

        $incomes = round(
            Transaction::where('user_id', $user->id)
                ->where('accounting_date', '>', $lastDayPrev)
                ->where('accounting_date', '<=', $lastDayCurr)
                ->where('type_id', 1)
                ->whereIn('card_id', function ($query) {
                    $query->select('id')->from('cards')->where('type_id', 1);
                })
                ->sum('amount'),
            2
        );

        $expenses = round(
            Transaction::where('user_id', $user->id)
                ->where('accounting_date', '>', $lastDayPrev)
                ->where('accounting_date', '<=', $lastDayCurr)
                ->where('type_id', 2)
                ->whereIn('card_id', function ($query) {
                    $query->select('id')->from('cards')->where('type_id', 1);
                })
                ->sum('amount'),
            2
        );

        $initialBills = round(
            Transaction::where('user_id', $user->id)
                ->where('accounting_date', '<=', $lastDayPrev)
                ->where('type_id', '!=', 3)
                ->whereIn('card_id', function ($query) {
                    $query->select('id')->from('cards')->where('type_id', 2);
                })
                ->sum('amount'),
            2
        );

        $finalBills = round(
            Transaction::where('user_id', $user->id)
                ->where('accounting_date', '<=', $lastDayCurr)
                ->where('type_id', '!=', 3)
                ->whereIn('card_id', function ($query) {
                    $query->select('id')->from('cards')->where('type_id', 2);
                })
                ->sum('amount'),
            2
        );

        $finalBalance = round($initialBalance + $incomes + $expenses + ($currentPayments * -1), 2);
        $projectedFinalBalance = round($initialBalance + $incomes + $expenses + $currentBills + $initialBills, 2);

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
                    'difference' => round($finalBalance - $initialBalance, 2),
                    'projected_difference' => round($projectedFinalBalance - $initialBalance, 2)
                ]
            ]
        ]);
    }

    private function buildDebitMonthData(int $userId, Carbon $monthDate): array
    {
        $lastDayPrev = $monthDate->copy()->startOfMonth()->subDay()->endOfDay();
        $lastDayCurr = $monthDate->copy()->endOfMonth()->endOfDay();

        $initialBalance = round(
            Transaction::where('user_id', $userId)
                ->where('accounting_date', '<=', $lastDayPrev)
                ->whereIn('card_id', fn($q) => $q->select('id')->from('cards')->where('type_id', 1))
                ->sum('amount'),
            2
        );

        $incomes = round(
            Transaction::where('user_id', $userId)
                ->whereBetween('accounting_date', [$lastDayPrev, $lastDayCurr])
                ->where('type_id', 1)
                ->whereIn('card_id', fn($q) => $q->select('id')->from('cards')->where('type_id', 1))
                ->sum('amount'),
            2
        );

        $expenses = round(
            Transaction::where('user_id', $userId)
                ->whereBetween('accounting_date', [$lastDayPrev, $lastDayCurr])
                ->where('type_id', 2)
                ->whereIn('card_id', fn($q) => $q->select('id')->from('cards')->where('type_id', 1))
                ->sum('amount'),
            2
        );

        $currentPayments = round(
            Transaction::where('user_id', $userId)
                ->whereBetween('accounting_date', [$lastDayPrev, $lastDayCurr])
                ->where('type_id', 1)
                ->whereIn('card_id', fn($q) => $q->select('id')->from('cards')->where('type_id', 2))
                ->sum('amount'),
            2
        );

        $currentBills = round(
            Transaction::where('user_id', $userId)
                ->whereBetween('accounting_date', [$lastDayPrev, $lastDayCurr])
                ->where('type_id', 2)
                ->whereIn('card_id', fn($q) => $q->select('id')->from('cards')->where('type_id', 2))
                ->sum('amount'),
            2
        );

        $initialBills = round(
            Transaction::where('user_id', $userId)
                ->where('accounting_date', '<=', $lastDayPrev)
                ->where('type_id', '!=', 3)
                ->whereIn('card_id', fn($q) => $q->select('id')->from('cards')->where('type_id', 2))
                ->sum('amount'),
            2
        );

        $finalBalance = round($initialBalance + $incomes + $expenses + ($currentPayments * -1), 2);
        $projectedFinalBalance = round($initialBalance + $incomes + $expenses + $currentBills + $initialBills, 2);

        return [
            'initial_balance' => $initialBalance,
            'incomes' => $incomes,
            'expenses' => $expenses,
            'final_balance' => $finalBalance,
            'projected_final_balance' => $projectedFinalBalance,
            'difference' => round($finalBalance - $initialBalance, 2),
            'projected_difference' => round($projectedFinalBalance - $initialBalance, 2),
        ];
    }

    public function getDebitBalanceByPeriod(
        int $year,
        int $month,
        string $period,
        Request $request
    ): JsonResponse {
        $user = $request->user();
        $userId = $user->id;

        $currentDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();

        $historicalMinDate = Transaction::where('user_id', $userId)
            ->min('accounting_date');

        $startDate = match ($period) {
            '3_months' => $currentDate->copy()->subMonths(2)->startOfMonth(),
            '6_months' => $currentDate->copy()->subMonths(5)->startOfMonth(),
            '12_months' => $currentDate->copy()->subMonths(11)->startOfMonth(),
            'historical' => $historicalMinDate
                ? Carbon::parse($historicalMinDate)->startOfMonth()
                : $currentDate->copy()->startOfMonth(),
            default => $currentDate->copy()->startOfMonth(),
        };

        $endDate = $currentDate->copy()->endOfMonth();

        $items = [];
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $items[] = $this->buildDebitChartItem($userId, $cursor->year, $cursor->month);
            $cursor->addMonth();
        }

        return response()->json([
            'message' => 'Balance de débito por periodo obtenido exitosamente.',
            'data' => [
                'period' => $period,
                'from' => $startDate->format('Y-m-d'),
                'to' => $endDate->format('Y-m-d'),
                'items' => $items,
            ],
        ]);
    }

    private function buildDebitChartItem(int $userId, int $year, int $month): array
    {
        $date = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $debit = $this->buildDebitMonthData($userId, $date);

        return [
            'label' => $this->buildMonthYearLabel($month, $year),
            'value' => (float) ($debit['final_balance'] ?? 0),
            'projected' => (float) ($debit['projected_final_balance'] ?? 0),
            'month' => $month,
            'year' => $year,
        ];
    }

    private function buildMonthYearLabel(int $month, int $year): string
    {
        $months = [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
        ];

        return ($months[$month] ?? '') . ' ' . $year;
    }

    public function getTransactions($year, $month, Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'concept' => 'sometimes|string|max:64',
            'amount' => ['sometimes', 'regex:/^\-?\d+(\.\d{1,2})?$/'],
            'category_id' => 'sometimes|integer',
            'type_id' => 'sometimes|integer',
            'card_id' => 'sometimes|integer',
            'transaction_date_from' => 'sometimes|date',
            'transaction_date_to' => 'sometimes|date',
            'accounting_date_from' => 'sometimes|date',
            'accounting_date_to' => 'sometimes|date',
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
            ->withCount(['incomeRelationsFrom', 'incomeRelationsTo'])
            ->without(['user'])
            ->where('user_id', $user->id)
            ->whereYear('accounting_date', $year)
            ->whereMonth('accounting_date', $month);

        if (!empty($validated['concept'])) {
            $query->where('concept', 'LIKE', '%' . trim($validated['concept']) . '%');
        }

        if (isset($validated['amount']) && $validated['amount'] !== '') {
            $amount = abs((float) $validated['amount']);

            $query->whereRaw('ABS(amount) = ?', [$amount]);
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

        if (!empty($validated['transaction_date_from'])) {
            $query->whereDate('transaction_date', '>=', $validated['transaction_date_from']);
        }

        if (!empty($validated['transaction_date_to'])) {
            $query->whereDate('transaction_date', '<=', $validated['transaction_date_to']);
        }

        if (!empty($validated['accounting_date_from'])) {
            $query->whereDate('accounting_date', '>=', $validated['accounting_date_from']);
        }

        if (!empty($validated['accounting_date_to'])) {
            $query->whereDate('accounting_date', '<=', $validated['accounting_date_to']);
        }

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

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $transactions
        ]);
    }

    public function searchTransactions(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'concept' => 'sometimes|string|max:64',
            'amount' => ['sometimes', 'regex:/^\-?\d+(\.\d{1,2})?$/'],
            'category_id' => 'sometimes|integer',
            'type_id' => 'sometimes|integer',
            'card_id' => 'sometimes|integer',
            'transaction_date_from' => 'sometimes|date',
            'transaction_date_to' => 'sometimes|date',
            'accounting_date_from' => 'sometimes|date',
            'accounting_date_to' => 'sometimes|date',
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
            ->withCount(['incomeRelationsFrom', 'incomeRelationsTo'])
            ->without(['user'])
            ->where('user_id', $user->id);

        if (!empty($validated['concept'])) {
            $query->where('concept', 'LIKE', '%' . trim($validated['concept']) . '%');
        }

        if (isset($validated['amount']) && $validated['amount'] !== '') {
            $amount = abs((float) $validated['amount']);

            $query->whereRaw('ABS(amount) = ?', [$amount]);
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

        if (!empty($validated['transaction_date_from'])) {
            $query->whereDate('transaction_date', '>=', $validated['transaction_date_from']);
        }

        if (!empty($validated['transaction_date_to'])) {
            $query->whereDate('transaction_date', '<=', $validated['transaction_date_to']);
        }

        if (!empty($validated['accounting_date_from'])) {
            $query->whereDate('accounting_date', '>=', $validated['accounting_date_from']);
        }

        if (!empty($validated['accounting_date_to'])) {
            $query->whereDate('accounting_date', '<=', $validated['accounting_date_to']);
        }

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

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $transactions
        ]);
    }
}
