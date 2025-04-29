<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $fillable = [
        'numbers',
        'color',
        'type_id',
        'bank_id',
        'network_id',
        'user_id'
    ];

    // Oculta solo los IDs (opcional, si no quieres mostrarlos)
    protected $hidden = [
        'type_id',
        'bank_id',
        'network_id',
        'user_id',
    ];

    // Carga automáticamente las relaciones al consultar
    protected $with = [
        'type',
        'bank',
        'network',
        'user'
    ];

    public function type()
    {
        return $this->belongsTo(AccountType::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function network()
    {
        return $this->belongsTo(Network::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }
}