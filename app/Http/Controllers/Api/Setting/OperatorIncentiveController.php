<?php

namespace App\Http\Controllers\Api\Setting;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\OperatorIncentiveConfiguration;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperatorIncentiveController extends Controller
{
    use ApiResponse, AuditTrail, CommonTrait, checkAuthPermsissionTrait;

    public function index(Request $request)
    {

        $validator = validator($request->all(), [
            'search_query' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $searchQuery = strip_tags($request->input('search_query'));
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
                throw new RestApiException(HTTP_NOT_FOUND, 'No operator incentive configurations found!');
            }
            $this->auditLog("View operator incentive configurations", PORTAL, null, null);
            return $this->success($incentives, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        try {
            $configuration = OperatorIncentiveConfiguration::find($id);

            if (!$configuration) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No operator incentive configurations found!');
            }

            $this->auditLog("View operator incentive configurations: " . $configuration->mode . " and amount" . $configuration->amount, PORTAL, null, null);
            return $this->success($configuration, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));

            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);

        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $confId)
    {
        if (is_null($confId) || !is_numeric($confId)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }

        $validatedData = validator($request->all(), [
            'type' => 'required|boolean',
            'mode' => 'required|in:PER,FLAT',
            'amount' => 'required|numeric|min:0',
        ]);
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $configuration = OperatorIncentiveConfiguration::findOrFail($confId);
        DB::beginTransaction();
        try {

            $oldData = clone $configuration;
            $payload = [
                'mode' => $request->mode,
                'type' => $request->type,
                'amount' => $request->amount,
            ];

            $configuration->update($payload);

            $this->auditLog("operator incentive configurations: " . $request->mode . " and amount" . $request->amount, PORTAL, $oldData, $payload);

            DB::commit();
            return $this->success($configuration, DATA_UPDATED);
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
