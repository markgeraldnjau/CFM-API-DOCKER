<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CaptchaController extends Controller
{
    use ApiResponse, CommonTrait;
    //
    public function generateCaptcha(Request $request)
    {
        // Validate the request
        $validatedData = $request->validate([
            'identifier' => 'required',
        ]);

        try {
            $captchaCode = Str::random(5);
            // Store the captcha code in the cache with the identifier
            $identifier = $validatedData['identifier'];
            Cache::put('captcha_' . $identifier, $captchaCode, 5); // Store for 5 minutes

            return $this->success($captchaCode, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
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
            return response()->json(['response_code' => HTTP_OK, 'message' => 'Captcha code is valid'], HTTP_OK);
        } else {
            // Captcha code is invalid
            return response()->json(['response_code' => HTTP_BUSY, 'message' => 'Invalid captcha code'], HTTP_UNPROCESSABLE_ENTITY);
        }
    }

}
