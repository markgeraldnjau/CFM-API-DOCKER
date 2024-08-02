<?php

namespace App\Models;

use App\Models\Operator\OperatorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceOperator extends Model
{
    use HasFactory;

    public function category()
    {
        return $this->belongsTo(OperatorCategory::class, 'operator_category_id', 'id');
    }

    public function type()
    {
        return $this->belongsTo(OperatorType::class, 'operator_type_id', 'id');
    }

    public function customerAccounts()
    {
        return $this->hasMany(CustomerAccount::class, 'customer_id', 'customer_id');
    }
}
