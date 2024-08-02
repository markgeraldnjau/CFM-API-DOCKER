<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainCabinContain extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'train_cabins_contain';
}
