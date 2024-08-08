<?php

namespace App\Http\Controllers\Api\Management;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\TrainLine;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainLineController extends Controller
{
    use ApiResponse, CommonTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        try {
            $trainLines = TrainLine::select('id', 'line_code', 'line_name')->latest('id')->get();

            if (!$trainLines) {
                throw new RestApiException(HTTP_NOT_FOUND, NOT_FOUND);
            }
            return $this->success($trainLines, DATA_RETRIEVED);
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
    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'line_name' => 'required|string|max:30',
            'line_code' => 'required|string|max:15|unique:lines,line_code',
            'line_distance' => 'required|numeric|between:0,99999999.99',
            'region_id' => 'required|exists:regions,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'code' => HTTP_UNPROCESSABLE_ENTITY,
                'errors' => $validator->errors()->messages()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $trainLine = new TrainLine();
            $trainLine->line_code = strtoupper($request->line_code);
            $trainLine->line_name = $request->line_name;
            $trainLine->line_distance = $request->line_distance;
            $trainLine->region_id = $request->cfm_region;
            $trainLine->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully Create New Train Line'], HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
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
            ], HTTP_BAD_REQUEST);
        }
        try {
            $trainLine = TrainLine::findOrFail($id, ['id', 'line_code', 'line_name']);
            if (!$trainLine) {
                throw new RestApiException(HTTP_NOT_FOUND, NOT_FOUND);
            }
            return $this->success($trainLine, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        $validator = validator($request->all(), [
            'line_name' => 'required|string|max:30',
            'line_distance' => 'required|numeric|between:0,99999999.99',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'code' => HTTP_UNPROCESSABLE_ENTITY,
                'errors' => $validator->errors()->messages()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $trainLine = TrainLine::findOrFail($id);
        if (!$trainLine) {
            throw new RestApiException(HTTP_NOT_FOUND, NOT_FOUND);
        }


        DB::beginTransaction();
        try {
            if ($trainLine) {
                $trainLine->line_name = $request->line_name;
                $trainLine->line_distance = $request->line_distance;
                $trainLine->update();
                DB::commit();
                return response()->json(['status' => 'success', 'message' => 'Successfully update Train Line'], HTTP_OK);

            } else {
                return response()->json(['status' => 'failed', 'message' => 'Train Line not found'], HTTP_NOT_FOUND);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));

            $statusCode = $th->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $statusCode = $th->getCode() ?: 500;
            $errorMessage = $th->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }

    }
}
