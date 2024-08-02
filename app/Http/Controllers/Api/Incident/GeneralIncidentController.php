<?php

namespace App\Http\Controllers\Api\Incident;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\OperatorCollectionTransactionRequest;
use App\Models\IncidentCategory;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeneralIncidentController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait;
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
            $query = DB::table('general_incidents as g')
                ->join('incident_categories as i', 'i.id', 'g.incident_category_id')
                ->select(
                    'g.id',
                    'i.title as category_name',
                    'g.level',
                    'g.title',
                    'g.title',
                    'g.description',
                    'g.platform',
                    'g.status'
                );
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('g.title', 'like', "%$searchQuery%")
                        ->where('i.title', 'like', "%$searchQuery%")
                        ->where('g.description', 'like', "%$searchQuery%");
                });
            }
            $incidents = $query->orderByDesc('g.updated_at')->paginate($itemPerPage);

            if (!$incidents) {
                throw new RestApiException(404, 'No cargo category found!');
            }
            $this->auditLog("View General Incidents", PORTAL, null, null);
            return $this->success($incidents, DATA_RETRIEVED);
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
    public function store(OperatorCollectionTransactionRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            $payload = [
                'incident_category_id' => $request->incident_category_id,
                'title' => $request->title,
                'level' => $request->level,
                'description' => $request->description,
                'platform' => $request->platform,
            ];

            $category = IncidentCategory::updateOrCreate($payload);
            $this->auditLog("Create general incident: " . $request->name, PORTAL, $payload, $payload);
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
