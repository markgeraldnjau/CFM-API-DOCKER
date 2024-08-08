<?php

namespace App\Http\Controllers\Api\Management;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\TrainDirection;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Support\Facades\Log;

class TrainDirectionController extends Controller
{
    use ApiResponse, CommonTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Http\JsonResponse
    {

        try {
            $trainDirections = TrainDirection::select('id', 'code', 'name')->get();

            // Check if any branches were found
            if (!$trainDirections) {
                throw new RestApiException(404, 'No train direction found!');
            }

            return $this->success($trainDirections, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $trainDirection = TrainDirection::findOrFail($id, ['id', 'code', 'name']);

            if (!$trainDirection) {
                throw new RestApiException(404, 'No train direction found!');
            }
            return $this->success($trainDirection, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

}
