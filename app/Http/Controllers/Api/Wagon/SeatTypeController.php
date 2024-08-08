<?php

namespace App\Http\Controllers\Api\Wagon;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Wagon\SeatType;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SeatTypeController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait;

    /**
     * Display a listing of the resource.
     */

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
            $query = SeatType::select('id', 'token', 'name', 'code');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('name', 'like', "%$searchQuery%");
                });
            }
            $seatType = $query->orderByDesc('updated_at')->paginate($itemPerPage);

            if (!$seatType) {
                throw new RestApiException(404, 'No seat type found!');
            }
            $this->auditLog("View seat types", PORTAL, null, null);
            return $this->success($seatType, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


}
