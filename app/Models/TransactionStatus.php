<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected array $dates = ['created_at', 'updated_at', 'deleted_at'];
}
