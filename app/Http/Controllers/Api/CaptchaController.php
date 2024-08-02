<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\TrainLine;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CaptchaController extends Controller
{
    use ApiResponse;
    //
    public function generateCaptcha(Request $request)
    {
        try {
            $captchaCode = Str::random(5);
            // Store the captcha code in the cache with the identifier
            $identifier = $request->input('identifier');
            Cache::put('captcha_' . $identifier, $captchaCode, 5); // Store for 5 minutes

            return $this->success($captchaCode, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function validateCaptcha(Request $request)
    {
        $captchaCode = $request->input('code');
        $identifier = $request->input('identifier');

        // Retrieve the captcha code from the cache
        $storedCaptchaCode = Cache::get('captcha_' . $identifier);

        if ($captchaCode == $storedCaptchaCode) {
            // Captcha code is valid
            Cache::forget('captcha_' . $identifier); // Clear the cache
            return response()->json(['response_code' => 200, 'message' => 'Captcha code is valid'], 200);
        } else {
            // Captcha code is invalid
            return response()->json(['response_code' => 600, 'message' => 'Invalid captcha code'], 422);
        }
    }

}
