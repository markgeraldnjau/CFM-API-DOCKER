<?php

namespace App\Models;

use App\Events\SendMail;
use App\Events\SendSms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class Customer extends Authenticatable
{
    use HasFactory, SoftDeletes, HasApiTokens;


    protected $table = 'card_customers';

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

    public function validateSession($sessionToken)
    {
        // Retrieve the customer record based on the session token
        $customer = Customer::where('session_token', $sessionToken)->first();

        // Check if the customer exists and the session token is valid
        if ($customer && $customer->session_token_expiry > now()) {
            return true;
        }

        return false;
    }
}
