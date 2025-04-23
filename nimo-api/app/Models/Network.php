<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Network extends Model
{
    protected $fillable = ['name', 'img_path'];

    protected $hidden = ['created_at', 'updated_at'];

    public function cards(){
        return $this->hasMany(Card::class);
    }
}
