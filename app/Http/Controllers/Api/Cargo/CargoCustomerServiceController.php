<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Cargo\CargoCustomerServiceType;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Support\Facades\Log;

class CargoCustomerServiceController extends Controller
{
    use ApiResponse, CommonTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $customerServiceTypes = CargoCustomerServiceType::select('id', 'code', 'name')->get();

            if (!$customerServiceTypes) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No cargo customers service type found!');
            }

            return $this->success($customerServiceTypes, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
