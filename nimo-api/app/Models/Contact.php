<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'alias',
        'user_id'
    ];

    protected $hidden = [
        'user_id'
    ];

    protected $with = [
        'type'
    ];

    public function type()
    {
        return $this->belongsTo(User::class);
    }

    public function incomeRelations(): HasMany
    {
        return $this->hasMany(IncomeRelation::class);
    }
}
