<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Cargo\CargoCustomerType;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Support\Facades\Log;

class CargoCustomerTypeController extends Controller
{
    use ApiResponse, CommonTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $customerTypes = CargoCustomerType::select('id', 'code', 'name')->get();

            if (!$customerTypes) {
                return $this->error(null, "No cargo customers type found!");
            }

            return $this->success($customerTypes, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
