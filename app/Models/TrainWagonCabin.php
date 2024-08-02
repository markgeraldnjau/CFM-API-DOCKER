<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainWagonCabin extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'train_wagon_cabin_setups';
}
