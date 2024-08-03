<?php

namespace App\Http\Controllers\App\Cards;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\JwtTrait;
use App\Traits\Mobile\MobileAppTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CardController extends Controller
{
    //
    use MobileAppTrait, ApiResponse, AuditTrail, JwtTrait;

    public function getCardDetails(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $cards = $this->getCustomerCard($customer->id);
            $this->auditLog("Mobile App Get Customer's Cards for ". $customer->full_name, PORTAL, null, null);
            return $this->success($cards, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::channel('customer')->error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::channel('customer')->error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
