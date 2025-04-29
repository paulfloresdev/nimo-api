<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'concept', 
        'amount', 
        'transaction_date', 
        'accounting_date', 
        'place', 
        'notes', 
        'category_id', 
        'type_id', 
        'card_id', 
        'user_id'
    ];

    protected $hidden = [
        'category_id',
        'type_id',
        'card_id',
        'user_id'
    ];

    protected $with = [
        'category',
        'type',
        'card',
        'user'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function type()
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function incomeRelationsFrom()
    {
        return $this->hasMany(IncomeRelation::class, 'from_id');
    }

    public function incomeRelationsTo()
    {
        return $this->hasMany(IncomeRelation::class, 'to_id');
    }

}
