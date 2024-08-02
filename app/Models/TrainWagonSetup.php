<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainWagonSetup extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'train_wagon_setups';
}
