<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\MonthlySubBudget;
use App\Models\MonthlySubBudgetTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MonthlySubBudgetController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $items = MonthlySubBudget::where('user_id', $request->user()->id)
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->with([
                'transactions.category',
                'transactions.type',
                'transactions.card.bank',
                'transactions.card.network',
                'transactions.card.type',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $items,
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'name' => 'required|string|max:64',
            'planned_amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
            'category_id' => 'required|integer|exists:categories,id',
            'card_id' => 'required|integer|exists:cards,id',
        ]);

        $user = $request->user();
        $this->assertCardBelongsToUser($validated['card_id'], $user->id);

        $subBudget = DB::transaction(function () use ($validated, $user) {
            $subBudget = MonthlySubBudget::create([
                ...$validated,
                'user_id' => $user->id,
                'active' => true,
            ]);

            $adjustment = Transaction::create([
                'concept' => 'Presupuesto ' . $validated['name'],
                'amount' => $validated['planned_amount'] * -1,
                'transaction_date' => $this->endOfMonth($validated['year'], $validated['month']),
                'accounting_date' => $this->endOfMonth($validated['year'], $validated['month']),
                'place' => null,
                'notes' => 'Ajuste automatico de subpresupuesto mensual',
                'category_id' => $validated['category_id'],
                'type_id' => 2,
                'card_id' => $validated['card_id'],
                'user_id' => $user->id,
            ]);

            $subBudget->adjustment_transaction_id = $adjustment->id;
            $subBudget->save();

            return $subBudget->fresh();
        });

        return response()->json([
            'message' => 'Subpresupuesto creado exitosamente.',
            'data' => $subBudget,
        ], 201);
    }

    public function update(Request $request, MonthlySubBudget $monthlySubBudget)
    {
        $this->assertOwnsBudget($monthlySubBudget, $request->user()->id);

        $validated = $request->validate([
            'name' => 'required|string|max:64',
            'planned_amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
            'category_id' => 'required|integer|exists:categories,id',
            'card_id' => 'required|integer|exists:cards,id',
            'active' => 'sometimes|boolean',
        ]);

        $this->assertCardBelongsToUser($validated['card_id'], $request->user()->id);

        $monthlySubBudget->update([
            ...$validated,
            'active' => $request->boolean('active', $monthlySubBudget->active),
        ]);
        $monthlySubBudget->recalculateAdjustment();

        return response()->json([
            'message' => 'Subpresupuesto actualizado exitosamente.',
            'data' => $monthlySubBudget->fresh(),
        ], 200);
    }

    public function destroy(Request $request, MonthlySubBudget $monthlySubBudget)
    {
        $this->assertOwnsBudget($monthlySubBudget, $request->user()->id);

        $validated = $request->validate([
            'delete_mode' => ['sometimes', Rule::in(['budget_only', 'delete_all', 'selected'])],
            'transaction_ids' => 'sometimes|array',
            'transaction_ids.*' => 'integer|exists:transactions,id',
        ]);

        $deleteMode = $validated['delete_mode'] ?? 'budget_only';
        $selectedIds = collect($validated['transaction_ids'] ?? []);

        DB::transaction(function () use ($monthlySubBudget, $deleteMode, $selectedIds) {
            $linkedIds = $monthlySubBudget->transactions()
                ->pluck('transactions.id')
                ->push($monthlySubBudget->adjustment_transaction_id)
                ->filter()
                ->unique();

            $deleteIds = collect();

            if ($deleteMode === 'delete_all') {
                $deleteIds = $linkedIds;
            }

            if ($deleteMode === 'selected') {
                $deleteIds = $selectedIds->intersect($linkedIds);
            }

            if ($deleteMode === 'budget_only') {
                $deleteIds = collect([$monthlySubBudget->adjustment_transaction_id])->filter();
            }

            MonthlySubBudgetTransaction::where('monthly_sub_budget_id', $monthlySubBudget->id)->delete();
            $monthlySubBudget->delete();

            if ($deleteIds->isNotEmpty()) {
                Transaction::whereIn('id', $deleteIds)->delete();
            }
        });

        return response()->json([
            'message' => 'Subpresupuesto eliminado exitosamente.',
        ], 200);
    }

    public function availableTransactions(Request $request, MonthlySubBudget $monthlySubBudget)
    {
        $this->assertOwnsBudget($monthlySubBudget, $request->user()->id);

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->where('id', '!=', $monthlySubBudget->adjustment_transaction_id)
            ->where('type_id', 2)
            ->where('category_id', $monthlySubBudget->category_id)
            ->whereYear('accounting_date', $monthlySubBudget->year)
            ->whereMonth('accounting_date', $monthlySubBudget->month)
            ->whereDoesntHave('monthlySubBudgetLinks')
            ->with(['category', 'type', 'card.bank', 'card.network', 'card.type'])
            ->orderByDesc('accounting_date')
            ->get();

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $transactions,
        ], 200);
    }

    public function attachTransactions(Request $request, MonthlySubBudget $monthlySubBudget)
    {
        $this->assertOwnsBudget($monthlySubBudget, $request->user()->id);

        $validated = $request->validate([
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'integer|exists:transactions,id',
        ]);

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->whereIn('id', $validated['transaction_ids'])
            ->where('type_id', 2)
            ->where('category_id', $monthlySubBudget->category_id)
            ->whereYear('accounting_date', $monthlySubBudget->year)
            ->whereMonth('accounting_date', $monthlySubBudget->month)
            ->whereDoesntHave('monthlySubBudgetLinks')
            ->get();

        foreach ($transactions as $transaction) {
            MonthlySubBudgetTransaction::firstOrCreate([
                'monthly_sub_budget_id' => $monthlySubBudget->id,
                'transaction_id' => $transaction->id,
            ]);
        }

        $monthlySubBudget->recalculateAdjustment();

        return response()->json([
            'message' => 'Movimientos vinculados exitosamente.',
            'data' => $monthlySubBudget->fresh(),
        ], 200);
    }

    public function detachTransaction(Request $request, MonthlySubBudget $monthlySubBudget, Transaction $transaction)
    {
        $this->assertOwnsBudget($monthlySubBudget, $request->user()->id);

        MonthlySubBudgetTransaction::where('monthly_sub_budget_id', $monthlySubBudget->id)
            ->where('transaction_id', $transaction->id)
            ->delete();

        $monthlySubBudget->recalculateAdjustment();

        return response()->json([
            'message' => 'Movimiento desvinculado exitosamente.',
            'data' => $monthlySubBudget->fresh(),
        ], 200);
    }

    public function recalculate(Request $request, MonthlySubBudget $monthlySubBudget)
    {
        $this->assertOwnsBudget($monthlySubBudget, $request->user()->id);

        $monthlySubBudget->recalculateAdjustment();

        return response()->json([
            'message' => 'Subpresupuesto recalculado exitosamente.',
            'data' => $monthlySubBudget->fresh(),
        ], 200);
    }

    private function assertOwnsBudget(MonthlySubBudget $monthlySubBudget, int $userId): void
    {
        abort_if($monthlySubBudget->user_id !== $userId, 403, 'No tienes permisos para este subpresupuesto.');
    }

    private function assertCardBelongsToUser(int $cardId, int $userId): void
    {
        abort_if(!Card::where('id', $cardId)->where('user_id', $userId)->exists(), 422, 'La tarjeta no pertenece al usuario actual.');
    }

    private function endOfMonth(int $year, int $month): string
    {
        return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
    }
}
