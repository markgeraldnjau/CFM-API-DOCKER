<?php

namespace App\Http\Controllers\App\Tickets;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\JwtTrait;
use App\Traits\Mobile\MobileAppTrait;
use App\Traits\Mobile\TicketTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    //
    use MobileAppTrait, ApiResponse, AuditTrail, JwtTrait, TicketTrait;

    public function getTicketsDetails(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $tickets = $this->getTicketHistory($customer->id);
            $this->auditLog("Mobile App Get Customer's Transactions for ". $customer->full_name, PORTAL, null, null);
            return $this->success($tickets, DATA_RETRIEVED);
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

    public function getCustomerMetrics(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $tickets = $this->getTicketMetrics($customer->id);
            $this->auditLog("Mobile App Get Customer's Transaction Metrics ". $customer->full_name, PORTAL, null, null);
            return $this->success($tickets, DATA_RETRIEVED);
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
