<?php

namespace App\Models\Operator;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperatorType extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];


    public function devices()
    {
        return $this->hasMany(Device::class, 'operator_type_id');
    }
}
