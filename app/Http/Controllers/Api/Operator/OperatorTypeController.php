<?php

namespace App\Http\Controllers\Api\Operator;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Operator\OperatorType;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OperatorTypeController extends Controller
{
    use ApiResponse, CommonTrait, AuditTrail, checkAuthPermsissionTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = validator($request->all(), [
            'search_query' => 'nullable|string|max:255',
            'item_per_page' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            $query = OperatorType::select('id', 'code', 'name');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('category', 'like', "%$searchQuery%");
                });
            }
            $operatorTypes = $query->orderByDesc('updated_at')->paginate($itemPerPage);

            return $this->success($operatorTypes, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getAllOperatorTypes(): \Illuminate\Http\JsonResponse
    {
        try {
            $operatorTypes = OperatorType::select('id', 'code', 'name')->get();
            $this->auditLog("View Operator types", PORTAL, null, null);
            return $this->success($operatorTypes, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


}
