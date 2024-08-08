<?php

namespace App\Http\Controllers\Api\Setting;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\TrainCabinSetting;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainLayoutController extends Controller
{
    use ApiResponse, CommonTrait;

    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        try {
            $tains = TrainCabinSetting::select('train_cabin_settings.id', 'train_cabin_settings.compatment_no', 'train_cabin_settings.cabin_no', 'train_cabin_settings.total_seat_no', 't.train_number', 'twc.name as class')
                ->join('trains as t', 't.id', '=', 'train_cabin_settings.train_id')
                ->join('train_wagon_class as twc', 'twc.id', '=', 'train_cabin_settings.class_id')
                ->orderBy('id', 'asc')
                ->get();

            return $this->success($tains, DATA_RETRIEVED);
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
        $validatedData = validator($request->all(), [
            'train_id' => 'required|exists:trains,id',
            'cabin' => 'required|integer',
            'compatment' => 'required|integer',
            'seat_no' => 'required|integer',
            'class_id' => 'required|exists:cfm_classes,id',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'code' => HTTP_UNPROCESSABLE_ENTITY,
                'errors' => $validatedData->errors()->messages()
            ], HTTP_UNPROCESSABLE_ENTITY);

        }
        DB::beginTransaction();
        try {
            $data = new TrainCabinSetting();
            $data->train_id = $validatedData['train_id'];
            $data->cabin_no = $validatedData['cabin'];
            $data->compatment_no = $validatedData['compatment'];
            $data->user_id = 1;
            $data->total_seat_no = $validatedData['seat_no'];
            $data->class_id = $validatedData['class_id'];
            $data->save();

            DB::commit();
            return $this->success($data, DATA_SAVED);
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
            ], 400);
        }
        $data = TrainCabinSetting::findOrFail($id);
        try {
            if (!$data) {
                return response()->json(['message' => 'data not found'], HTTP_NOT_FOUND);
            }
            return response()->json($data, HTTP_OK);
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
        $validatedData = $request->validate([
            'train_id' => 'required|exists:trains,id',
            'cabin' => 'required|integer',
            'compatment' => 'required|integer',
            'seat_no' => 'required|integer',
            'class_id' => 'required|exists:cfm_classes,id',
        ]);
        $data = TrainCabinSetting::findOrfail($id);
        DB::beginTransaction();
        try {

            if (!$data) {
                return response()->json(['message' => 'Data not found'], HTTP_NOT_FOUND);
            }

            $data->train_id = $validatedData['train_id'];
            $data->cabin_no = $validatedData['cabin'];
            $data->compatment_no = $validatedData['compatment'];
            $data->user_id = 1;
            $data->total_seat_no = $validatedData['seat_no'];
            $data->class_id = $validatedData['class_id'];
            $data->save();

            DB::commit();
            return response()->json(['message' => DATA_SAVED], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to update cabin'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
