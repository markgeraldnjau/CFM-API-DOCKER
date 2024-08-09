<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use App\Traits\JwtTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Check2FA
{
    use ApiResponse,JwtTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }
        $customer = $response['data'];

        if (!$customer->two_fa_auth) {
            $customer->revokeAllTokens();
            return $this->error(null, "Session Expired!", HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
