<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'icon'
    ];

    protected $hidden = [
        'updated_at',
        'created_at'
    ];

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }
}
