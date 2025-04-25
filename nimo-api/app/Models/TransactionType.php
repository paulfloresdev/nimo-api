<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
{
    protected $fillable = [
        'type'
    ];

    protected $hidden = ['created_at', 'updated_at'];
}
