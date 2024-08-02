<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAccount extends Model
{
    use HasFactory;

    protected $table = 'customer_accounts';

    protected $fillable = [
        'account_number',
        'card_id',
        'customer_id',
        'accounts_usage_type',
        'customer_account_package_type',
        'account_balance',
        'min_account_balance',
        'status',
        'linker',
        'account_validity',
        'trips_number_balance',
        'last_update',
        'max_trip_per_day',
        'max_trip_per_month',
        'reset_employee_balance',
        'link_date',
        'approve_id',
        'approve_date',
    ];

    public function cardCustomer()
    {
        return $this->belongsTo(CardCustomer::class, 'customer_id', 'id');
    }

    public function account()
    {
        // return $this->belongsTo(Account::class,'');
    }

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id', 'id');
    }



    public function customerAccountPackageType()
    {
        return $this->belongsTo(CustomerAccountPackageType::class, 'customer_account_package_type', 'id');
    }


    public function accountUsageType()
    {
        return $this->belongsTo(AccountUsageType::class, 'accounts_usage_type', 'type');
    }


}
