<?php

namespace App\Http\Controllers\Api\Train;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\TrainWagonCabin;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainCabinSetupController extends Controller
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
            $cabinSetups = TrainWagonCabin::select('train_wagon_cabin_setups.id', 'train_wagon_cabin_setups.cabin_label', 'tw.serial_number as wagon', 'tc.name as cabin')
                ->join('train_wagon as tw', 'tw.id', '=', 'train_wagon_cabin_setups.wagon_id')
                ->join('train_cabins as tc', 'tc.id', '=', 'train_wagon_cabin_setups.cabin_id')
                ->orderBy('train_wagon_cabin_setups.id', 'desc')
                ->get();
            return $this->success($cabinSetups, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
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
            'wagon_id' => 'required|exists:wagons,id', // Must be an existing wagon ID
            'cabin_id' => 'required|exists:cabins,id', // Must be an existing cabin ID
            'cabin_label' => 'required|string|max:255', // Must be a string and have a maximum length of 255 characters
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
            $cabinSetup = new TrainWagonCabin();
            $cabinSetup->cabin_id = $validatedData['cabin_id'];
            $cabinSetup->wagon_id = $validatedData['wagon_id'];
            $cabinSetup->cabin_label = $validatedData['cabin_label'];
            $cabinSetup->save();

            DB::commit();
            return $this->success($cabinSetup, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        $cabin = TrainWagonCabin::findOrFail($id);
        try {
            if (!$cabin) {
                return response()->json(['message' => 'cabin not found'], HTTP_NOT_FOUND);
            }
            return response()->json($cabin, HTTP_OK);
        } catch (\Throwable $th) {
            Log::error(json_encode($this->errorPayload($th)));
            $statusCode = $th->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $th->getMessage() ?: SERVER_ERROR;
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
            ], HTTP_BAD_REQUEST);
        }
        $cabin = TrainWagonCabin::findOrFail($id);
        try {
            if (!$cabin) {
                return response()->json(['message' => 'cabin not found'], HTTP_NOT_FOUND);
            }
            return response()->json($cabin, HTTP_OK);
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
            ], HTTP_BAD_REQUEST);
        }
        $rules = [
            'wagon_id' => 'required|exists:wagons,id', // Must be an existing wagon ID
            'cabin_id' => 'required|exists:cabins,id', // Must be an existing cabin ID
            'cabin_label' => 'required|string|max:255', // Must be a string and have a maximum length of 255 characters
        ];
        $validatedData = validator($request->all(), $rules);
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $cabinSetup = TrainWagonCabin::findOrFail($id);

        DB::beginTransaction();
        try {
            if (!$cabinSetup) {
                return response()->json(['message' => 'Cabin not found'], HTTP_NOT_FOUND);
            }
            $cabinSetup->cabin_id = $validatedData['cabin_id'];
            $cabinSetup->wagon_id = $validatedData['wagon_id'];
            $cabinSetup->cabin_label = $validatedData['cabin_label'];
            $cabinSetup->save();
            DB::commit();
            return response()->json(['message' => 'User updated successfully'], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to update user'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
