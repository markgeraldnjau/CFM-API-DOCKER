<?php

namespace App\Models\Cargo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CargoCustomer extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($customer) {
            $customer->customer_number = static::generateCustomerNumber();
        });
    }

    /**
     * Generate a unique customer number.
     *
     * @return string
     */
    protected static function generateCustomerNumber(): string
    {
        // Generate a unique customer number using a combination of timestamp and a cryptographically secure random number
        return CUST_CONSTANT . self::getRandomNumber();
    }

    /**
     * Generate a cryptographically secure random number.
     *
     * @return int
     */
    protected static function getRandomNumber(): int
    {
        // Generate a secure random number between 1000 and 9999
        return random_int(1000, 9999);
    }
}
