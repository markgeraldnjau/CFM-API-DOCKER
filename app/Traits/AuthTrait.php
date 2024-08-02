<?php
namespace App\Traits;

use App\Events\SendMail;
use App\Events\SendSms;
use App\Models\Card;
use App\Models\OTP;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

trait AuthTrait
{

    protected function saveOTP(int $userId, string $userType, $otp, $token)
    {

        if ($token == null) {
            $token = new OTP();
            $token->user_id = $userId;
            $token->operator = 0;
            $token->device = 0;
            $token->user_type = $userType;
            $token->otpcode = Hash::make($otp);
            $token->status = UNSED_OTP;
            $token->save();
        } else {
            $token->otpcode = Hash::make($otp);
            $token->operator = 0;
            $token->device = 0;
            $token->status = UNSED_OTP;
            $token->updated_at = Carbon::now()->toDateTimeString();
            $token->save();
        }

        if (env('APP_ENV') == 'local'){
            Log::channel('customer')->info("Save Mobile Customer OTP");
            Log::channel('customer')->info(json_encode($token));
            Log::channel('customer')->info('Logging the customer otp for testing: '. $otp);
        }

        return $token;
    }

    public function sendCode($code, $token): void
    {
        dd($code, $token);
        event(new SendSms('otp', $token->id, ['code' => $code]));
        event(new SendMail('otp', $token->id, ['code' => $code]));
    }


    function generateAlphanumericPassword($length = 8): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $password;
    }


    public function contructSelectedActionsForModule($payload): array
    {
        foreach ($payload as $item) {
            // Extract the id and action from the item
            $id = substr($item, 0, 1);
            $action = substr($item, 2);

            // Check if the id already exists in the result array
            if (!isset($result[$id])) {
                $result[$id] = [
                    'id' => $id,
                    'actions' => []
                ];
            }

            // Append the action to the actions array
            $result[$id]['actions'][] = $action;
        }
        return array_values($result);
    }

}
