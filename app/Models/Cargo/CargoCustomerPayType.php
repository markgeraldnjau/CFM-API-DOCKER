<?php

namespace App\Models\Cargo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CargoCustomerPayType extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected array $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
