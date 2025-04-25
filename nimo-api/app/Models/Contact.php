<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
