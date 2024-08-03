<?php

namespace App\Http\Controllers\App\Auth;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\CardCustomer;
use App\Models\OTP;
use App\Traits\ApiResponse;
use App\Traits\AuthTrait;
use App\Traits\CommonTrait;
use App\Traits\FireBaseTrait;
use App\Traits\JwtTrait;
use App\Traits\Mobile\MobileAppTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TwoFactorAuthController extends Controller
{
    use ApiResponse, AuthTrait, MobileAppTrait, JwtTrait, CommonTrait, FireBaseTrait;

    public function confirm(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];

        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'fb_device_token' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error(null, "Verification Code is invalid!", 401);
        }

        DB::beginTransaction();
        try {

            $code = $request->code;

            $otp = OTP::where('user_id', $customer->id)
                ->where('user_type', get_class($customer))
                ->first();

            $updateTwoFactorAuth = CardCustomer::find($customer->id);

            if ($otp == null) {
                $updateTwoFactorAuth->two_fa_auth = true;
                $updateTwoFactorAuth->save();
                return $this->error(null, "Otp code supplied does not exits!", 404);
            }

            if (!Hash::check($code, $otp->otpcode)) {
                $updateTwoFactorAuth->two_fa_auth = true;
                $updateTwoFactorAuth->save();
                return $this->error(null, "Invalid otp code supplied!", 401);
            }

            if ($otp->isUsed()) {
                $updateTwoFactorAuth->two_fa_auth = true;
                $updateTwoFactorAuth->save();
                return $this->error(null, "Otp code has already used!", 401);
            }

            if ($otp->isExpired()) {
                $updateTwoFactorAuth->two_fa_auth = true;
                $updateTwoFactorAuth->save();
                return $this->error(null, "Otp code has already expired!", 404);
            }

            $otp->status = USED_OTP;
            $otp->save();


            //Set FireBase Device Token
            $checkFireBaseDeviceToken = $this->checkUserDeviceToken($customer, $customer->phone);
            if (!$checkFireBaseDeviceToken){

                $deviceToken = $request->fb_device_token ?? Str::random(20);
                $saveFireBaseDeviceToken = $this->saveFireBaseDevice($customer, $customer->phone, $deviceToken);
                if (!$saveFireBaseDeviceToken){
                    return $this->error(null, "Something went wrong on saving Firebase Device Token", 500);
                }
            }

            $data = $this->appCustomerInfo($customer, $request);

            //Update TwoFactor Auth
            $updateTwoFactorAuth->two_fa_auth = true;
            $updateTwoFactorAuth->save();

            DB::commit();
            return $this->success($data, DATA_RETRIEVED);

        } catch (\Exception $e){
            DB::rollBack();
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function resend(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];

        try {
            $token = $customer->otp;
            $code = OTP::generate();
            $saveOtp = $this->saveOTP($customer->id, get_class($customer), $code, $token);

            if (!$saveOtp){
                return $this->error(null, SOMETHING_WENT_WRONG);
            }
            $token->sendCode($code);
            $maskedPhoneNumber = $this->maskPhoneNumber($customer->phone);
            $data = [
                'otp_sent_to' => $maskedPhoneNumber
            ];
            return $this->success($data, "Token resend successfully. Check your email/sms");

        } catch (\Exception $e){
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function logout(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }
        $customer = $response['data'];
        try {
            $customer->revokeAllTokens();
            return $this->success(null, "Successfully logged out");
        } catch (\Exception $e){
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }

    }

    public function changePin(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $validator = Validator::make($request->all(), [
                'old_pin' => 'required|string|min:4',
                'new_pin' => 'required|string|min:4',
                'confirm_pin' => 'required|string|min:4',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors(), "Validation error!", 422);
            }


            if ($request->old_pin === $request->new_pin) {
                return $this->error(null, "The provided old PIN can not be the same to the new PIN", 422);

            }

            // Check if the provided old PIN matches the stored PIN
            if (!Hash::check($request->input('old_pin'), $customer->app_pin)) {
                return $this->error(null, "The provided old PIN is incorrect.", 422);

            }

            // Manually check if the new_pin and confirm_pin match
            if ($request->new_pin !== $request->confirm_pin) {
                return $this->error(null, "PIN confirmation does not match.", 422);
            }

            $customer->app_pin = Hash::make($request->new_pin);

            if ($customer->is_first_login){
                $customer->is_first_login = false;
                $customer->first_login_date = Carbon::now();
            }
            $customer->save();

            if (!$customer){
                return $this->error(null, SOMETHING_WENT_WRONG);
            }

            return $this->success(null, "Password changed successfully");
        } catch (\Exception $e){

            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
