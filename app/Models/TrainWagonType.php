<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainWagonType extends Model
{
    use HasFactory;

    protected $table = 'train_wagon_types';

    protected $guarded = [];
}
