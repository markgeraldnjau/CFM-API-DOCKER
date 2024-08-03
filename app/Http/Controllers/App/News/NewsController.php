<?php

namespace App\Http\Controllers\App\News;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\CfmInformation;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\JwtTrait;
use App\Traits\Mobile\MobileAppTrait;
use App\Traits\Mobile\TicketTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\NewsAndUpdate;

class NewsController extends Controller
{
    //
    use MobileAppTrait, ApiResponse, AuditTrail, JwtTrait, TicketTrait;

    public function getAllNews(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $news = NewsAndUpdate::select('token', 'title', 'content', 'posted_on')->where('status', PUBLISHED)->orderByDesc('posted_on')->get();
            $this->auditLog("Mobile App Get All News and Updates ". $customer->full_name, PORTAL, null, null);
            return $this->success($news, DATA_RETRIEVED);
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


    public function aboutUs(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $news = CfmInformation::select('token', 'title', 'information', 'posted_on')->where('status', PUBLISHED)->where('code', ABOUT_US)->orderByDesc('posted_on')->first();
            $this->auditLog("Mobile App Get About us information ". $customer->full_name, PORTAL, null, null);
            return $this->success($news, DATA_RETRIEVED);
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

    public function contactUs(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $news = CfmInformation::select('token', 'title', 'information', 'posted_on')->where('status', PUBLISHED)->where('code', CONTACT_US)->orderByDesc('posted_on')->first();
            $this->auditLog("Mobile App Get About us information ". $customer->full_name, PORTAL, null, null);
            return $this->success($news, DATA_RETRIEVED);
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

    public function services(Request $request)
    {
        $response = $this->getCustomerByJwtToken($request);
        if (!$response['status']){
            return $response['data'];
        }

        $customer = $response['data'];
        try {
            $news = CfmInformation::select('token', 'title', 'information', 'posted_on')->where('status', PUBLISHED)->where('code', SERVICES_PAGE)->orderByDesc('posted_on')->first();
            $this->auditLog("Mobile App Get About us information ". $customer->full_name, PORTAL, null, null);
            return $this->success($news, DATA_RETRIEVED);
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
