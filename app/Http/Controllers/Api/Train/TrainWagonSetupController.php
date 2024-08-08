<?php

namespace App\Http\Controllers\Api\Train;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\TrainWagonSetup;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainWagonSetupController extends Controller
{
    use CommonTrait, ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        try {
            $wagonSetups = TrainWagonSetup::select('train_wagon_setups.id', 't.train_Number as train_number', 'w.serial_number as wagon_name', 'train_wagon_setups.wagon_label')
                ->join('trains as t', 't.id', '=', 'train_wagon_setups.train_id')
                ->join('train_wagon as w', 'w.id', '=', 'train_wagon_setups.wagon_id')
                ->orderBy('id', 'desc')
                ->get();
            return $this->success($wagonSetups, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'train_id'      => 'required|exists:trains,id', // Must be an existing train ID
            'wagon_id'      => 'required|exists:wagons,id', // Must be an existing wagon ID
            'wagon_label'   => 'required|string|max:255', // Must be a string and have a maximum length of 255 characters
        ];
        $validatedData = validator($request->all(), $rules);
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $wagon = new TrainWagonSetup();
            $wagon->train_id = $validatedData['train_id'];
            $wagon->wagon_id = $validatedData['wagon_id'];
            $wagon->wagon_label = $validatedData['wagon_label'];
            $wagon->save();
            DB::commit();
            return $this->success($wagon, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        $wagon = TrainWagonSetup::findOrFail($id);
        try {
            if (!$wagon) {
                return response()->json(['message' => 'wagon setup not found'], 404);
            }
            return response()->json($wagon, 200);
        } catch (\Throwable $th) {
            Log::error(json_encode($this->errorPayload($th)));
            $statusCode = $th->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $th->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);

        }

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        $rules = [
            'train_id'      => 'required|exists:trains,id', // Must be an existing train ID
            'wagon_id'      => 'required|exists:wagons,id', // Must be an existing wagon ID
            'wagon_label'   => 'required|string|max:255', // Must be a string and have a maximum length of 255 characters
        ];
        $validatedData = validator($request->all(), $rules);
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $wagon = TrainWagonSetup::find($id);
            if (!$wagon) {
                return response()->json(['message' => 'Wagon setup not found'], 404);
            }

            $wagon->train_id = $validatedData['train_id'];
            $wagon->wagon_id = $validatedData['wagon_id'];
            $wagon->wagon_label = $validatedData['wagon_label'];
            $wagon->save();
            DB::commit();
            return response()->json(['message' => 'Wagon updated successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to update wagon'], 500);
        }
    }


}
