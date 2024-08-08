<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ExtendedTransactionType;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionTypeController extends Controller
{
    use ApiResponse, CommonTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $transactionTypes = ExtendedTransactionType::select('id', 'code', 'name')->paginate(100);

            // Check if any branches were found
            if ($transactionTypes->isEmpty()) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No transaction types found!');
            }

            return $this->success($transactionTypes, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
