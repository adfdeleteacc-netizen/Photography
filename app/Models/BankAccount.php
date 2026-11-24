<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $guarded = [];
    protected $hidden = ['account_number'];
    protected $casts = ['account_number' => 'encrypted', 'is_primary' => 'boolean'];

    public function getMaskedNumberAttribute(): string
    {
        return str_repeat('•', max(strlen($this->account_number) - 4, 0)) . substr($this->account_number, -4);
    }
}
