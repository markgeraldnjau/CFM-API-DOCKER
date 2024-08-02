<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NormalPrice extends Model
{
    use HasFactory;
    protected $table = 'normal_prices';
    protected $fillable = [
        'fare_charge_offtrain',
        'origin_station',
        'destination_station',
        'line_id',
        'class_id',
        'fare_charge_ontrain',
        'fare_charge_offtrain_group_one',
        'fare_charge_offtrain_group_two',
        'fare_charge_ontrain_group_one',
        'fare_charge_ontrain_group_two',
    ];
}
