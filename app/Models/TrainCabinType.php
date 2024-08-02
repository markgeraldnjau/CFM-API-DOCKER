<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainCabinType extends Model
{
    use HasFactory;


    protected $guarded = [];

    protected $table = 'train_cabins_type';
}
