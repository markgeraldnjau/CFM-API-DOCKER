<?php
namespace App\Traits;

use App\Events\SendMail;
use App\Events\SendSms;
use App\Models\Card;
use App\Models\Customer;
use App\Models\OTP;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

trait CustomerTrait
{
    public function generateDefaultPin($customerDetails)
    {
        $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        if (env('app_env') == 'production' or env('db_env') == 'uat'){
            Log::channel('customer')->info("register customer");
            Log::channel('customer')->info(json_encode($customerDetails));
            Log::channel('customer')->info('Logging the customer pin for testing: '. $pin);
        }

        return $pin;
    }


}
