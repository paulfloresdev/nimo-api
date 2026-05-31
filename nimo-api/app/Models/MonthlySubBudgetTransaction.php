<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlySubBudgetTransaction extends Model
{
    protected $fillable = [
        'monthly_sub_budget_id',
        'transaction_id',
    ];

    public function monthlySubBudget()
    {
        return $this->belongsTo(MonthlySubBudget::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
