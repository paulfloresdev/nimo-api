<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recurring extends Model
{
    protected $fillable = [
        'concept', 
        'amount', 
        'category_id', 
        'type_id', 
        'user_id'
    ];

    protected $hidden = [
        'category_id',
        'type_id',
        'user_id'
    ];

    protected $with = [
        'category',
        'type'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function type()
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function records(){
        return $this->hasMany(RecurringRecord::class);
    }
}
