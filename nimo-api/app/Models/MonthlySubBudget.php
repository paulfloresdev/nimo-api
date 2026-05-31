<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MonthlySubBudget extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'month',
        'name',
        'planned_amount',
        'category_id',
        'card_id',
        'adjustment_transaction_id',
        'active',
    ];

    protected $casts = [
        'planned_amount' => 'float',
        'active' => 'boolean',
    ];

    protected $with = [
        'category',
        'card',
        'adjustmentTransaction',
    ];

    protected $appends = [
        'spent_amount',
        'remaining_amount',
        'adjustment_amount',
        'exceeded_amount',
        'progress',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function adjustmentTransaction()
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }

    public function transactionLinks()
    {
        return $this->hasMany(MonthlySubBudgetTransaction::class);
    }

    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'monthly_sub_budget_transactions');
    }

    public function getSpentAmountAttribute(): float
    {
        return round($this->transactions()
            ->where('transactions.id', '!=', $this->adjustment_transaction_id)
            ->sum(DB::raw('ABS(transactions.amount)')), 2);
    }

    public function getRemainingAmountAttribute(): float
    {
        return round($this->planned_amount - $this->spent_amount, 2);
    }

    public function getAdjustmentAmountAttribute(): float
    {
        return round(max($this->remaining_amount, 0), 2);
    }

    public function getExceededAmountAttribute(): float
    {
        return round(max($this->spent_amount - $this->planned_amount, 0), 2);
    }

    public function getProgressAttribute(): float
    {
        if ($this->planned_amount <= 0) {
            return 0;
        }

        return round(min(($this->spent_amount / $this->planned_amount) * 100, 999), 2);
    }

    public function accountingDate(): string
    {
        return Carbon::create((int) $this->year, (int) $this->month, 1)->endOfMonth()->toDateString();
    }

    public function recalculateAdjustment(): void
    {
        $remaining = max($this->planned_amount - $this->spent_amount, 0);

        if (!$this->adjustmentTransaction) {
            return;
        }

        $this->adjustmentTransaction->update([
            'concept' => 'Presupuesto ' . $this->name,
            'amount' => $remaining * -1,
            'transaction_date' => $this->accountingDate(),
            'accounting_date' => $this->accountingDate(),
            'category_id' => $this->category_id,
            'type_id' => 2,
            'card_id' => $this->card_id,
        ]);

        $this->refresh();
    }
}
