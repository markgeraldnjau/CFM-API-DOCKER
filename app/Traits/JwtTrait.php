<?php
namespace App\Traits;

use App\Models\CardCustomer;
use Illuminate\Http\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\TokenRepository;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

trait JwtTrait
{
    use ApiResponse;
    public function getCustomerByJwtToken($request): array
    {
        try {
            $token = $request->bearerToken();
            $encoder = new JoseEncoder();
            $parser = new Parser($encoder);
            $jwt = $parser->parse($token);

            $tokenId = $jwt->claims()->get('jti');
            $tokenRepository = app(TokenRepository::class);
            $token = $tokenRepository->find($tokenId);

            $response = [];
            if (!$token || $token->revoked) {
                $response['status'] = false;
                $response['data'] = $this->error(null, UNAUTHENTICATED, 401);
                return $response;
            }

            $customerId = $token->user_id;
            $customer = CardCustomer::find($customerId);

            $response['status'] = true;
            $response['data'] = $customer;

            return $response;
        }catch (\Exception $e){
            Log::channel('customer')->error($e->getMessage());
            $response['status'] = false;
            $response['data'] = $this->error(null, UNAUTHENTICATED, 401);
            return $response;
        }
    }
}
