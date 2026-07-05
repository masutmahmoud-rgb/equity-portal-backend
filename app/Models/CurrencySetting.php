<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencySetting extends Model
{
    protected $fillable = [
        'reporting_currency',
    ];
}
