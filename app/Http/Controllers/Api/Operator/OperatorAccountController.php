<?php

namespace App\Http\Controllers\Api\Operator;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\OperatorRequest;
use App\Http\Requests\Operator\UpdateOperatorRequest;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\AuthTrait;
use App\Traits\CommonTrait;
use App\Traits\checkAuthPermsissionTrait;
use App\Traits\OperatorTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperatorAccountController extends Controller
{
    use ApiResponse, OperatorTrait, CommonTrait, checkAuthPermsissionTrait, AuditTrail, AuthTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
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
            $query = DB::table('operators as o')
                ->join('operator_accounts as oa', 'oa.operator_id', 'o.id')
                ->join('train_lines as tl', 'tl.id', 'o.train_line_id')
                ->join('operator_types as ot', 'ot.id', 'o.operator_type_code')
                ->join('train_stations as s', 's.id', 'o.station_id')
                ->select(
                    'o.id',
                    'o.operator_id',
                    'o.operator_no',
                    'o.full_name',
                    'o.username',
                    'o.phone',
                    'o.email',
                    'o.status',
                    's.station_name',
                    'tl.line_name',
                    'ot.name as operator_type',
                    'oa.system_transaction_amount',
                    'oa.total_collection_amount',
                    'oa.trend',
                    'o.updated_at',
                );
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('o.full_name', 'like', "%$searchQuery%");
                });
            }
            $operators = $query->orderByDesc('updated_at')->paginate($itemPerPage);
            return $this->success($operators, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(OperatorRequest $request)
    {

        DB::beginTransaction();
        try {
            $password = $this->generateAlphanumericPassword();
            $data = [
                'full_name' => $request->full_name,
                'email' => $request->email,
                'username' => $request->username,
                'phone' => $request->phone,
                'train_line_id' => $request->train_line_id,
                'operator_type_code' => $request->operator_type_code,
                'operator_category_id' => $request->operator_category_id,
                'station_id' => $request->station_id,
                'password' => $password
            ];

            $response = $this->createOperator((object) $data);
            if (!$response) {
                Log::error("Error on register operator: " . json_encode($request));
                DB::rollBack();
                return $this->error(null, SOMETHING_WENT_WRONG);
            }

            $this->auditLog("Create Operator: " . $request->full_name, PORTAL, $request, $request);
            DB::commit();
            return $this->success(null, DATA_SAVED);
        } catch (\Exception $e) {

            Log::error(json_encode($this->errorPayload($e)));

            DB::rollBack();
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($operatorId)
    {

        if (is_null($operatorId) || !is_numeric($operatorId)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        try {
            $operator = DB::table('operators as o')
                ->join('operator_accounts as oa', 'oa.operator_id', 'o.id')
                ->join('train_lines as tl', 'tl.id', 'o.train_line_id')
                ->join('operator_types as ot', 'ot.id', 'o.operator_type_code')
                ->join('train_stations as s', 's.id', 'o.station_id')
                ->select(
                    'o.id',
                    'o.operator_no',
                    'o.full_name',
                    'o.username',
                    'o.phone',
                    'o.email',
                    'o.status',
                    's.station_name',
                    'tl.line_name',
                    'ot.name as operator_type',
                    'oa.system_transaction_amount',
                    'oa.total_collection_amount',
                    'oa.trend',
                    'o.updated_at',
                )
                ->where('operator_id', $operatorId)
                ->first();

            if (!$operator) {
                return $this->error(null, 'No Operator found!', HTTP_NOT_FOUND);
            }

            $this->auditLog("View Operator Account Details: " . $operator->full_name, PORTAL, null, null);
            return $this->success($operator, DATA_RETRIEVED);
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
    public function update(UpdateOperatorRequest $request, $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            $data = [
                'full_name' => $request->full_name,
                'email' => $request->email,
                'username' => $request->username,
                'phone' => $request->phone,
                'train_line_id' => $request->train_line_id,
                'operator_type_code' => $request->operator_type_code,
                'operator_category_id' => $request->operator_category_id,
                'station_id' => $request->station_id,
            ];

            $response = $this->updateOperator((object) $data, $id);

            if (!$response) {
                Log::error("Error on register operator: " . json_encode($request));
                DB::rollBack();
                return $this->error(null, SOMETHING_WENT_WRONG);
            }

            $this->auditLog("Create Operator: " . $request->full_name, PORTAL, $request, $request);
            DB::commit();
            return $this->success(null, DATA_SAVED);
        } catch (\Exception $e) {

            Log::error(json_encode($this->errorPayload($e)));

            DB::rollBack();
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


}
