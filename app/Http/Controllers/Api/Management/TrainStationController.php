<?php

namespace App\Http\Controllers\Api\Management;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\TrainStation;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class TrainStationController extends Controller
{
    use ApiResponse, CommonTrait;


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = validator($request->all(), [
            "items_per_page" => "nullable|integer",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        try {

            $trainStations = TrainStation::select([
                'train_stations.id',
                'train_stations.station_name',
                'train_stations.province',
                'train_stations.latitude',
                'train_stations.longitude',
                'train_stations.distance_maputo',
                'train_lines.line_name',
                'train_stations.zone_st',
                'train_stations.zone_id_desc'
            ])
                ->join('train_lines', 'train_stations.line_id', '=', 'train_lines.id')
                ->paginate($request->items_per_page);
            // return $this->success($trainStations, DATA_RETRIEVED);
            return response()->json($trainStations);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function trainsForReport(): \Illuminate\Http\JsonResponse
    {
        try {
            $trainStations = TrainStation::select('id', 'station_name', 'station_name_erp')->get();

            if (!$trainStations) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No train stations found!');
            }

            return $this->success($trainStations, DATA_RETRIEVED);
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
        $validatedData = validator($request->all(), [
            'station_name' => 'required|string|max:255|regex:/^[\pL\s\-]+$/u',
            'station_name_initial' => 'nullable|string|max:255|regex:/^[\pL\s\-]+$/u',
            'line' => 'required|integer',
            'first_class' => 'required|integer|in:1,0',
            'second_class' => 'required|integer|in:2,0',
            'third_class' => 'required|integer|in:3,0',
            'automotora' => 'required|integer|in:1,0',
            'cargo' => 'required|integer|in:1,0',
            'normal' => 'required|integer|in:1,0',
            'zone' => 'required|integer|in:0,1,2,3,4',
            'zone_desc' => 'required|in:0,1,2,3,4',
            'is_off_train_ticket_available' => 'nullable|boolean',
            'distance_maputo' => 'required|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validatedData->validated();

        DB::beginTransaction();
        try {
            $trainStation = new TrainStation;
            $trainStation->station_name = strip_tags($data['station_name']);
            $trainStation->station_name_erp = strip_tags($data['station_name_initial']);
            $trainStation->province = DEFAULT_PROVINCE;
            $trainStation->distance_maputo = $data['distance_maputo'];
            $trainStation->latitude = DEFAULT_LATITUDE;
            $trainStation->longitude = DEFAULT_LONGITUDE;
            $trainStation->line_id = $data['line'];
            $trainStation->frst_class = $data['first_class'] ? "1" : "0";
            $trainStation->sec_class = $data['second_class'] ? "2" : "0";
            $trainStation->thr_class = $data['third_class'] ? "3" : "0";
            $trainStation->zone_st = $data['zone'];
            $trainStation->zone_id_desc = $data['zone_desc'];
            $trainStation->automotora = $data['automotora'];
            $trainStation->cargo = $data['cargo'];
            $trainStation->normal = $data['normal'];
            $trainStation->is_off_train_ticket_available = $data['is_off_train_ticket_available'];
            $trainStation->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully created new Train Class'], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));

            return response()->json(['status' => 'fail', 'message' => 'Failed to create train class detail'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (is_null($id) || !is_string($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $trainStation = DB::table('train_stations as ts')->join('train_lines as tl', 'tl.id', 'ts.line_id')
                ->select('ts.*', 'tl.line_name')->where('ts.id', $id)->first();

            if (!$trainStation) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No train station found!');
            }
            return $this->success($trainStation, DATA_RETRIEVED);
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
        try {
            $validatedData = validator($request->all(), [
                'station_name' => 'required|string|max:255',
                'station_name_initial' => 'nullable|string|max:255',
                'line' => 'required|integer',
                'first_class' => 'required|integer|in: 1,0',
                'second_class' => 'required|integer||in: 2,0',
                'third_class' => 'required|integer|in:3,0',
                'automotora' => 'required|integer|in: 1,0',
                'cargo' => 'required|integer|in: 1,0',
                'normal' => 'required|integer|in: 1,0',
                'zone' => 'required|integer|in: 0,1,2,3,4',
                'zone_desc' => 'required|in: 0,1,2,3,4',
                'is_off_train_ticket_available' => 'nullable|boolean',
                'distance_maputo' => 'required|numeric',
                'province' => 'required|string|max:255|regex:/^[\pL\s\-]+$/u',
            ]);

            if ($validatedData->fails()) {
                return response()->json([
                    'status' => VALIDATION_ERROR,
                    'message' => VALIDATION_FAIL,
                    'errors' => $validatedData->errors()
                ], HTTP_UNPROCESSABLE_ENTITY);
            }

            $trainStation = TrainStation::find($id);
            if (empty($trainStation)) {
                return response()->json(['status' => 'fail', 'message' => NOT_FOUND], HTTP_NOT_FOUND);
            }

            DB::beginTransaction();
            try {
                $trainStation->station_name = strip_tags($request['station_name']);
                $trainStation->station_name_erp = strip_tags($request['station_name_initial']);
                $trainStation->province = strip_tags($request['province']);
                $trainStation->line_id = $request['line'];
                $trainStation->frst_class = $request['first_class'] ? "1" : "0";
                $trainStation->sec_class = $request['second_class'] ? "2" : "0";
                $trainStation->thr_class = $request['third_class'] ? "3" : "0";
                $trainStation->zone_st = $request['zone'];
                $trainStation->zone_id_desc = $request['zone_desc'];
                $trainStation->distance_maputo = $request['distance_maputo'];
                $trainStation->normal = $request['normal'];
                $trainStation->cargo = $request['cargo'];
                $trainStation->automotora = $request['automotora'];
                $trainStation->is_off_train_ticket_available = $request['is_off_train_ticket_available'] ? "1" : "0";
                $trainStation->update();
                DB::commit();

                return response()->json(['status' => 'success', 'message' => DATA_UPDATED], HTTP_CREATED);
            } catch (\Exception $e) {

                DB::rollBack();
                Log::error(json_encode($this->errorPayload($e)));
                return response()->json(['status' => 'fail', 'message' => FAILED], HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (ValidationException $e) {
            return response()->json(['status' => 'fail', 'message' => VALIDATION_ERROR, 'errors' => $e->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}
