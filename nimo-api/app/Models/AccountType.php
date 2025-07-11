<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    protected $fillable = [
        'type'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function cards(){
        return $this->hasMany(Card::class);
    }
}
