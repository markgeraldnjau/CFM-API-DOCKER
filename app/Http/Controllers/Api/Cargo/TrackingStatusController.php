<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Cargo\CargoCategory;
use App\Models\Cargo\CargoTrackStatus;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrackingStatusController extends Controller
{
    use ApiResponse, AuditTrail;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        //
        try {
            $query = CargoTrackStatus::leftJoin('cargo_track_statuses as next', 'cargo_track_statuses.next_status_id', '=', 'next.id')
                ->select('cargo_track_statuses.*', 'next.name as next_status_name');

            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('cargo_track_statuses.name', 'like', "%$searchQuery%")
                        ->orWhere('next.name', 'like', "%$searchQuery%"); // Also search in the name of the next_status_id
                });
            }

            $cargoTrackingStatuses = $query->orderBy('cargo_track_statuses.id')->whereNull('cargo_track_statuses.deleted_at')->paginate($itemPerPage);

            if (!$cargoTrackingStatuses) {
                throw new RestApiException(404, 'No cargo tracking status found!');
            }
            $this->auditLog("View Cargo Tracking Statuses", PORTAL, null, null);
            return $this->success($cargoTrackingStatuses, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        DB::beginTransaction();
        try {
            $payload = [
                'name' => $request->name,
            ];

            $category = CargoCategory::create($payload);
            $this->auditLog("Create cargo category: ". $request->name, PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($category, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
