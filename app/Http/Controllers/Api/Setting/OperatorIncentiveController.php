<?php

namespace App\Http\Controllers\Api\Setting;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\OperatorIncentiveConfiguration;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperatorIncentiveController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait;

    public function index(Request $request)
    {
        //
        $searchQuery = $request->input('search_query');
        try {
            $query = DB::table('operator_incentive_configurations as i')->select(
                'i.id',
                'i.amount',
                'i.mode',
                'i.type',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS created_by"),
                'i.created_by'
            )->leftJoin('users as u', 'u.id', 'i.created_by');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('i.amount', 'like', "%$searchQuery%");
                });
            }
            $incentives = $query->orderByDesc('i.updated_at')->get();

            if (!$incentives) {
                throw new RestApiException(404, 'No operator incentive configurations found!');
            }
            $this->auditLog("View operator incentive configurations", PORTAL, null, null);
            return $this->success($incentives, DATA_RETRIEVED);
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
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            $configuration = OperatorIncentiveConfiguration::find($id);

            if (!$configuration) {
                throw new RestApiException(404, 'No operator incentive configurations found!');
            }

            $this->auditLog("View operator incentive configurations: ". $configuration->mode . " and amount". $configuration->amount, PORTAL, null, null);
            return $this->success($configuration, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
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
    public function update(Request $request, string $confId)
    {
        //
        DB::beginTransaction();
        try {
            $configuration = OperatorIncentiveConfiguration::findOrFail($confId);
            $oldData = clone $configuration;
            $payload = [
                'mode' => $request->mode,
                'type' => $request->type,
                'amount' => $request->amount,
            ];

            $configuration->update($payload);

            $this->auditLog("operator incentive configurations: ". $request->mode . " and amount". $request->amount, PORTAL, $oldData, $payload);

            DB::commit();
            return $this->success($configuration, DATA_UPDATED);
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
