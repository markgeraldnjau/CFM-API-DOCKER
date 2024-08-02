<?php
namespace App\Traits;

use App\Events\SendMail;
use App\Events\SendSms;
use App\Models\OTP;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

trait SmsTrait
{

    public function sendSms($phoneNumber, $sms)
    {
        $data =  array(
            "customerKey" => env('BLUETEK_CUSTOMER_KEY'),
            "messageRequestList" => [
                json_decode(json_encode(array(
                    "number" => $phoneNumber,
                    "message" => $sms,
                    "id" => env('BLUETEK_ID'),
                    "campaignID" => env('BLUETEK_CAMPAIGN_ID'),
                    "Result" => SUCCESS_RESPONSE
                ))),
            ],
        );

        $response = Http::post(env('BLUETEK_END_POINT'), $data);
        return $response->body();
    }

}
