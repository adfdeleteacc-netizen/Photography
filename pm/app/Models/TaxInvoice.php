<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class TaxInvoice extends Model
{
    protected $guarded = [];
    protected $casts = ['is_credit_note' => 'boolean'];
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
