<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    // protected $table = 'card_details';
    protected $table = 'cards';
    protected $fillable = [
        'id',
        'tag_id',
        'card_number',
        'status',
        'dateuploaded',
        'card_type',
        'expire_date',
        'card_ownership',
        'credit_type',
        'company_id',
        'last_update_time',
        'card_block_action',
        'card_pin',
    ];

    public function customerAccounts()
    {
        return $this->hasMany(CustomerAccount::class, 'id', 'card_id');
    }

    public function customerAccountDetails()
    {
        return $this->customerAccounts()->join('customer_account_package_types as pck', 'pck.id', 'customer_accounts.customer_account_package_type')
            ->select('customer_accounts.*', 'pck.package_name');
    }

    public static function getCardDetailsWithCustomerAccounts($customerId)
    {
        $card =  self::join('customer_cards as cc', 'cc.card_id', 'cards.id')
            ->where('cc.customer_id', $customerId)
            ->select(
                'cards.id as card_id',
                'cards.card_number',
                'cards.status',
                'cards.card_type',
                'cards.card_block_action',
                'cards.company_id'
            )
            ->first();

        $accountPackages = CustomerAccount::join('customer_account_package_types as pck', 'pck.id', 'customer_accounts.customer_account_package_type')
            ->select(
                'customer_accounts.id as account_package_id',
                'customer_accounts.account_balance as package_account_balance',
                'customer_accounts.min_account_balance as package_min_account_balance',
                'customer_accounts.trips_number_balance',
                'customer_accounts.max_trip_per_day',
                'customer_accounts.max_trip_per_month',
                'customer_accounts.reset_employee_balance',
                'pck.package_code',
                'pck.package_name'
            )
            ->where('card_id', $card->card_id)->get();
        $card->packages_accounts = $accountPackages;
        return $card;
    }

    public function cardType()
    {
        return $this->belongsTo(CardType::class, 'card_type', 'id');
    }
}
