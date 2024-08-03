<?php

namespace App\Http\Controllers\App\Auth;

use App\Events\SendMail;
use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\CardCustomer;
use App\Models\Customer;
use App\Models\OTP;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\AuthTrait;
use App\Traits\CommonTrait;
use App\Traits\Mobile\MobileAppTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    use AuthTrait, ApiResponse, AuditTrail, MobileAppTrait, CommonTrait;

    protected $maxAttempts;
    protected $decayMinutes;
    //
    public function login(Request $request)
    {
        $request->validate([
            'card_number' => 'required|string',
            'app_pin' => 'required|string',
        ]);

        $cardNumber = $request->card_number;
        $pin = $request->app_pin;

        try {
            $customer = $this->getCustomerDetails($cardNumber);

            if (empty($customer)) {
                return $this->error(null, "The invalid account!", HTTP_UNAUTHORIZED);
            }

            if (!$customer->status) {
                return $this->error(null, "Account Suspended, contact CFM administration for help.", HTTP_UNAUTHORIZED);
            }

            $attempts = $this->hasTooManyLoginAttempts($customer);
            if ($attempts) {
                $message = 'We have detected multiple login attempts on your account. For security purposes, we have temporarily disabled your account. If you believe this is an error, please contact CFM support immediately.';
                $payload = [
                    'email' => $customer->email,
                    'message' => $message,
                ];
                event(new SendMail(TOO_MANY_LOGIN_ATTEMPTS, $payload));
                return $this->sendLockoutResponse($customer);
            }

            if (Hash::check($pin, $customer->app_pin)) {
                $customerModel = CardCustomer::find($customer->id);
                $customerModel->tokens()->delete();

                $customerModel->last_activity = Carbon::now();
                $customerModel->save();
                if ($customer->status == 0) {
                    Auth::logout();
                    $request->session()->flush();
                    throw ValidationException::withMessages([
                        'card_number' => "Your account is locked, Please contact your admin to unlock your account",
                    ]);
                }

                // Generate OTP
                $otp = OTP::generate();

                $saveOtpResponse = $this->saveOTP($customer->id, get_class($customer), $otp, $customer->otp);
                if (!$saveOtpResponse) {
                    return $this->error(null, SOMETHING_WENT_WRONG);
                }

                $saveOtpResponse->sendCode($otp);


                Cache::forget('login_attempts_' . $customer->id);

                $maskedPhoneNumber = $this->maskPhoneNumber($customer->phone);
                $data = [
                    'token' => $customer->createToken('CustomerToken')->accessToken,
                    'otp_sent_to' => $maskedPhoneNumber
                ];

                return $this->success($data, SUCCESS_RESPONSE);
            } else {
                $this->incrementLoginAttempts($customer);
                return $this->sendFailedLoginResponse();
            }
        } catch (\Exception $e) {
            Log::channel('customer')->error($e);
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? 'SERVER_ERROR';
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    protected function hasTooManyLoginAttempts($customer): bool
    {
        $attempts = Cache::get('login_attempts_' . $customer->id, HAS_TOO_MANY_ATTEMPTS);
        return $attempts >= config('custom.login.login_attempts');
    }

    protected function incrementLoginAttempts($customer): void
    {
        $attempts = Cache::get('login_attempts_' . $customer->id, HAS_TOO_MANY_ATTEMPTS);
        $attempts++;
        Cache::put('login_attempts_' . $customer->id, $attempts, now()->addMinutes(config('custom.login.lockout_duration_minutes')));
    }

    protected function sendLockoutResponse(Customer $customer)
    {
        $seconds = Cache::get('login_lockout_seconds_' . $customer->id);

        throw ValidationException::withMessages([
            $this->card_number() => [Lang::get('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ])->status(Response::HTTP_TOO_MANY_REQUESTS);
    }

    protected function sendFailedLoginResponse()
    {
        return $this->error(null, "These credentials do not match our records", HTTP_UNAUTHORIZED);
    }

    protected function card_number(): string
    {
        return 'card_number';
    }

}
