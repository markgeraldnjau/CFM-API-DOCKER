<?php
namespace App\Traits\Mobile;

use App\Models\Card;
use App\Models\CardCustomer;
use App\Models\Identification;
use App\Models\PlatformDetail;

trait MobileAppTrait
{

    public function appCustomerInfo($user, $request): array
    {
        $platform = PlatformDetail::where('platform_type', APP)->select('app_store_link', 'google_store_link', 'ios_current_version', 'android_current_version')->first();
        $shareAppLink = null;
        $currentVersion = null;
        if ($request->is_android){
            $shareAppLink = $platform->google_store_link;
            $currentVersion = $platform->android_current_version;
        } else {
            $shareAppLink = $platform->app_store_link;
            $currentVersion = $platform->ios_current_version;
        }

        $identificationId = $user->identification_type;
        if ($identificationId){
            $identification = Identification::find($user->identification_type, ['name', 'code']);
        } else {
            $identification = Identification::where('code', NUIT)->select('name', 'code')->first();
        }


        $customer = [
            'user_id' => $user->id,
            'full_name' => $user->full_name,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'identification_name' => $identification->name . "(" . $identification->code . ")",
            'identification_type' => $user->identification_type,
            'identification_number' => $user->identification_number,
            'designation_title' => $user->designation_title,
            'gender' => $user->gender,
            'birth_date' => $user->birthdate,
            'phone' => $user->phone,
            'email' => $user->email,
            'address' => $user->address,
            'id_image' => $user->id_image,
            'is_first_login' => $user->is_first_login,
            'first_login_date' => $user->first_login_date,
            'share_app_link' => $shareAppLink,
            'current_version' => $currentVersion
        ];

        $cards = Card::join('customer_cards as cc', 'cc.card_id', 'cards.id')
            ->join('card_types as ct', 'ct.id', 'cards.card_type')
            ->where('cc.customer_id', $user->id)
            ->select(
                'cards.id as card_id',
                'cards.card_number',
                'cards.status',
                'ct.type_name as card_type',
                'cards.card_block_action',
                'cards.company_id',
                'cards.expire_date',
            )->get();

        return [
            'customer' => $customer,
            'cards' => $cards,
        ];
    }

    public function getCustomerDetails($cardNumber)
    {
        return CardCustomer::join('customer_cards as cuc', 'cuc.customer_id', 'card_customers.id')
            ->join('cards as ca', 'ca.id', 'cuc.card_id')
            ->select(
                'card_customers.id',
                'card_customers.full_name',
                'card_customers.phone',
                'card_customers.email',
                'card_customers.app_pin',
                'card_customers.status',
                'ca.card_number',
                'ca.expire_date',
                'ca.status',
                'ca.card_block_action',
            )->where('ca.card_number', $cardNumber)
            ->whereNull('ca.deleted_at')
            ->whereNull('card_customers.deleted_at')->first();
    }

    public function getCustomerDetailsByCustomerId($customerId)
    {
        return CardCustomer::join('customer_cards as cuc', 'cuc.customer_id', 'card_customers.id')
            ->join('cards as ca', 'ca.id', 'cuc.card_id')
            ->select(
                'card_customers.id',
                'card_customers.full_name',
                'card_customers.phone',
                'card_customers.email',
                'card_customers.app_pin',
                'card_customers.status',
                'ca.card_number',
                'ca.expire_date',
                'ca.status',
                'ca.card_block_action',
            )->where('card_customers.id', $customerId)
            ->whereNull('ca.deleted_at')
            ->whereNull('card_customers.deleted_at')->first();
    }
    public function getCustomerCard($customer_id)
    {
        return Card::getCardDetailsWithCustomerAccounts($customer_id);
    }


}
