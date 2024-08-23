<?php

namespace App\Http\Controllers\PHC;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\ApiUser;
use App\Traits\CommonTrait;
use App\Traits\Phc\PhcApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\JWTAuth;

class AuthenticationController extends Controller
{
    use PhcApiResponse, CommonTrait;
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */

    protected $jwt;

    // Inject JWTAuth into the controller
    public function __construct(JWTAuth $jwt)
    {
        $this->middleware('auth:api_user', ['except' => ['login']]);
        $this->jwt = $jwt;
    }

    public function login(Request $request)
    {
        try{
            $rules = [
                'username' => 'required|string|strip_tag',
                'password' => 'required|string',
            ];

            // Validate the request
            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                $errors = implode(', ', $validator->errors()->all());
                return $this->error(LOGIN_API, $errors, null,HTTP_UNPROCESSABLE_ENTITY);
            }

            // Retrieve the validated data
            $credentials = $request->only('username', 'password');

            // Manually check user credentials
            $user = ApiUser::where('username', $credentials['username'])->first();

            if (!$user || !Auth::guard('api_user')->attempt($credentials)) {
                return $this->error(LOGIN_API, "Unauthorized", null,HTTP_UNAUTHORIZED);
            }

            // Generate a JWT token
            $token = $this->jwt->fromUser($user);
            return $this->success(LOGIN_API, SUCCESS_RESPONSE, 200, $this->respondWithToken($token));
        } catch (\Exception $e){
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            return $this->error(LOGIN_API, $errorMessage, null,$statusCode);
        }


    }

    protected function respondWithToken($token): array
    {
        // Get the TTL from the JWT configuration
        $ttl = $this->jwt->factory()->getTTL() * HAS_TOO_MANY_ATTEMPTS; // in minutes

        // Calculate the expiration date using Carbon
        $expiresAt = Carbon::now()->addMinutes($ttl);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $expiresAt->toDateTimeString(),
        ];
    }
    public function me()
    {
        return $this->success(null, SUCCESS_RESPONSE, 200,\auth()->user());
    }

    public function logout()
    {
        auth()->logout();
        return $this->success(LOGOUT_API, "Successfully logged out", null,SUCCESS_RESPONSE);
    }

    public function refresh()
    {
        return $this->success(REFRESH_API, "Successfully logged out", $this->respondWithToken(auth()->refresh()),SUCCESS_RESPONSE);
    }


}
