<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomotoraPrice extends Model
{
    use HasFactory;

    protected $table = 'automotora_prices';
    protected $fillable = [
        'fare_charge',
        'origin_station',
        'destination_station',
        'line_id',
        'class_id',
    ];
}
