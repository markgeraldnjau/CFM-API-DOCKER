<?php

namespace App\Models;

use App\Models\Operator\OperatorAccount;
use App\Models\Operator\OperatorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Operator extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['password', 'created_at', 'updated_at', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();

        // Event listener for the creating event
        static::creating(function ($operator) {
            if (is_null($operator->operator_id)) {
                $operator->operator_id = $operator->generateOperatorId();
            }
        });
    }

    public function generateOperatorId(): string
    {
        // Here you can customize the logic to generate the operator ID.
        // For simplicity, we'll use the UUID.
        return (string) Str::uuid();
    }

    protected function generateOperatorNo(): string
    {
        // Get the last operator's number
        $lastOperator = Operator::orderBy('operator_no', 'desc')->first();

        if ($lastOperator) {
            // Increment the last operator number by 1
            $lastNumber = intval($lastOperator->operator_no);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            // Start from 000001 if no operators exist
            $newNumber = '000001';
        }

        return $newNumber;
    }

    public function category()
    {
        return $this->belongsTo(OperatorCategory::class, 'operator_category_id', 'id');
    }

    public function type()
    {
        return $this->belongsTo(OperatorType::class, 'operator_type_code', 'id');
    }


    public function operatorAccount(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OperatorAccount::class, 'operator_id');
    }
}
