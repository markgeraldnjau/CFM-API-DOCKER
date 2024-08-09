<?php

namespace App\Http\Middleware;

use App\Models\CardCustomer;
use App\Traits\ApiResponse;
use App\Traits\JwtTrait;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\TokenRepository;
use Symfony\Component\HttpFoundation\Response;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Validation\Validator;
class MobileAppAuthMiddleware
{
    use JwtTrait, ApiResponse;
    public function handle($request, Closure $next)
    {

        $response = $this->getCustomerByJwtToken($request);

        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];

        if (!$customer) {
            Log::channel('customer')->info('TokenExpiry Middleware: No authenticated user found.');
            return $this->error(null, UNAUTHENTICATED, 401);
        }


        $lastActivity = Carbon::parse($customer->last_activity);
        if ($lastActivity->diffInMinutes(Carbon::now()) > 60) {
            $customer->tokens()->delete();
            Log::info('TokenExpiry Middleware: Session expired due to inactivity for user ' . $customer->id);
            return $this->error(null, "Session expired due to inactivity", 401);
        }


        $customerUpdate = CardCustomer::where('id', $customer->id)->update(['last_activity' => Carbon::now()]);

        if (!$customerUpdate){
            $this->error(null, SOMETHING_WENT_WRONG, 500);
        }

        return $next($request);
    }
}
