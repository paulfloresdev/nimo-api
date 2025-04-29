<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeRelation extends Model
{
    protected $fillable = ['amount', 'from_id', 'to_id', 'contact_id'];

    protected $hidden = ['from_id', 'to_id'];

    public function fromTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'from_id');
    }

    public function toTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'to_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
