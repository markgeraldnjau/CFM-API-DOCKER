<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Cargo\CargoTrackStatus;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TrackingStatusController extends Controller
{
    use ApiResponse, AuditTrail, CommonTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'search_query' => ['nullable', 'string', 'max:255'],
            'item_per_page' => ['nullable', 'numeric', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return $this->error(null, $errors, HTTP_UNPROCESSABLE_ENTITY);
        }

        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        //
        try {
            $query = CargoTrackStatus::leftJoin('cargo_track_statuses as next', 'cargo_track_statuses.next_status_id', '=', 'next.id')
                ->select('cargo_track_statuses.*', 'next.name as next_status_name');

            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('cargo_track_statuses.name', 'like', "%$searchQuery%")
                        ->orWhere('next.name', 'like', "%$searchQuery%");
                });
            }

            $cargoTrackingStatuses = $query->orderBy('cargo_track_statuses.id')->whereNull('cargo_track_statuses.deleted_at')->paginate($itemPerPage);

            if (!$cargoTrackingStatuses) {
                return $this->error(null, 'No cargo tracking status found!', HTTP_NOT_FOUND);
            }
            $this->auditLog("View Cargo Tracking Statuses", PORTAL, null, null);
            return $this->success($cargoTrackingStatuses, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_NOT_FOUND;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

}
