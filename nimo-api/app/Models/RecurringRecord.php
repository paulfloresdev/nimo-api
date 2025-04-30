<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringRecord extends Model
{
    protected $fillable = [
        'recurring_id',
        'transaction_id'
    ];

    protected $hidden = [
        'recurring_id',
        'transaction_id'
    ];

    protected $with = [
        'recurring',
        'transaction'
    ];

    public function recurring()
    {
        return $this->belongsTo(Recurring::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
