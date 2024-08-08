<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Cargo\CargoCustomerPayType;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Support\Facades\Log;

class CargoCustomerPayController extends Controller
{
    use ApiResponse, CommonTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $customerPayTypes = CargoCustomerPayType::select('id', 'code', 'name')->get();

            if (!$customerPayTypes) {
                throw new RestApiException(404, 'No cargo customers pay type found!');
            }

            return $this->success($customerPayTypes, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
