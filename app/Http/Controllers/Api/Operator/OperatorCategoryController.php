<?php

namespace App\Http\Controllers\Api\Operator;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\OperatorCategory;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;

use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class OperatorCategoryController extends Controller
{
    use ApiResponse, AuditTrail, CommonTrait, checkAuthPermsissionTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = validator($request->all(), [
            'search_query' => 'nullable|string',
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
            $query = OperatorCategory::select('id', 'token', 'code', 'category as name');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('category', 'like', "%$searchQuery%");
                });
            }
            $operatorCategories = $query->orderByDesc('updated_at')->paginate($itemPerPage);

            $this->auditLog("View Operator categories", PORTAL, null, null);
            return $this->success($operatorCategories, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    public function getAllOperatorCategories(): \Illuminate\Http\JsonResponse
    {
        try {
            $operatorCategories = OperatorCategory::select('id', 'token', 'code', 'category as name')->get();
            $this->auditLog("View Operator categories", PORTAL, null, null);
            return $this->success($operatorCategories, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


}
