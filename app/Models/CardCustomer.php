<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class CardCustomer extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable, SoftDeletes;


    protected $table = 'card_customers';

    protected $guarded = [];

    public function customerAccount()
    {
        return $this->hasOne(CustomerAccount::class, 'customer_id', 'id');
    }

    public function account()
    {
        // return $this->hasOne(CustomerAccount::class, 'customer_id', 'id');
        return $this->hasOne(CustomerAccount::class, 'customer_id', 'id')->select(['id', 'card_id', 'customer_id']);
    }

    public function card()
    {
        return $this->hasOne(Card::class, 'customer_id', 'id');
    }
    public function specialGroup()
    {
        return $this->belongsTo(SpecialGroup::class, 'special_group_id', 'id');
    }

    public function employeeDepartment()
    {
        return $this->belongsTo(EmployeeDepartment::class, 'employee_department_id', 'id');
    }
    public function gender()
    {
        return $this->belongsTo(Gender::class, 'gender_id', 'id');
    }

    public function employeeMonthIncentive()
    {
        return $this->hasOne(EmployeeIncentive::class, 'customer_id', 'id');
    }


    protected $casts = [
        'app_pin' => 'string',
    ];

    public function getAuthIdentifierName()
    {
        return 'identification_number';
    }

    public function fullname()
    {
        return $this->full_name;
    }

    public function otp(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(OTP::class, 'user');
    }

    public function revokeAllTokens(): void
    {
        $tokens = $this->tokens;

        foreach ($tokens as $token) {
            $token->revoke();
        }
    }

}
