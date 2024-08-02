<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Traits\ApiResponse;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditTrailController extends Controller
{
    use ApiResponse, checkAuthPermsissionTrait;

    /**
     * Display a listing of the resource.
     * @throws RestApiException
     */
    public function index(Request $request)
    {
//        return $request->path();
        $this->checkPermissionFn($request, VIEW);

        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            $query = DB::table('audit_trails as at');

            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('at.browser', 'like', "%$searchQuery%")
                        ->orWhere('at.user_name', 'like', "%$searchQuery%")
                        ->orWhere('at.ip_address', 'like', "%$searchQuery%")
                        ->orWhere('at.endpoint', 'like', "%$searchQuery%")
                        ->orWhere('at.method', 'like', "%$searchQuery%")
                        ->orWhere('at.action', 'like', "%$searchQuery%");
                });
            }

            $auditTrails = $query->orderByDesc('at.updated_at')->paginate($itemPerPage);

            if (!$auditTrails) {
                throw new RestApiException(404, 'No audit trail found!');
            }

            return $this->success($auditTrails, DATA_RETRIEVED);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
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
    }

    /**
     * Display the specified resource.
     */
    public function show(string $token)
    {
        //
        try {
            $auditTrail = AuditTrail::where('token', $token)->firstOrFail();
            return $this->success($auditTrail, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
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
