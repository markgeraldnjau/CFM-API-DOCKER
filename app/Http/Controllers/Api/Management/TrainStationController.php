<?php

namespace App\Http\Controllers\Api\Management;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\TrainStation;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class TrainStationController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
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
            ->paginate(10);


            // return $this->success($trainStations, DATA_RETRIEVED);
            return response()->json($trainStations);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function trainsForReport(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $trainStations = TrainStation::select('id', 'station_name', 'station_name_erp')->get();

            if (!$trainStations) {
                throw new RestApiException(404, 'No train stations found!');
            }

            return $this->success($trainStations, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
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

        $validatedData = $request->validate([
            'station_name' => 'required|string',
            'station_name_initial' => 'nullable|string',
            'line' => 'required|integer',
            'first_class' => 'required|in: 1,0',
            'second_class' => 'required|in: 2,0',
            'third_class' => 'required|in:3,0',
            'automotora' => 'required|in: 1,0',
            'cargo' => 'required|in: 1,0',
            'normal' => 'required|in: 1,0',
            'zone' => 'required|in: 0,1,2,3,4',
            'zone_desc' => 'required|in: 0,1,2,3,4',
            'is_off_train_ticket_available' => 'nullable|boolean',
            'distance_maputo' => 'required|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        DB::beginTransaction();
        try {
            $trainStation = new TrainStation;
            $trainStation->station_name = $validatedData['station_name'];
            $trainStation->station_name_erp = $validatedData['station_name_initial'];
            $trainStation->province = "Maputo";
            $trainStation->distance_maputo = $validatedData['distance_maputo'];
            $trainStation->latitude = "1.0000";
            $trainStation->longitude = "1.0000";
            $trainStation->line_id = $validatedData['line'];
            $trainStation->frst_class = $validatedData['first_class'] ? "1" : "0";
            $trainStation->sec_class = $validatedData['second_class'] ? "2" : "0";
            $trainStation->thr_class = $validatedData['third_class'] ? "3" : "0";
            $trainStation->zone_st = $validatedData['zone'];
            $trainStation->	zone_id_desc = $validatedData['zone_desc'];
            $trainStation->	automotora = $validatedData['automotora'];
            $trainStation->	cargo = $validatedData['cargo'];
            $trainStation->	normal = $validatedData['normal'];
            $trainStation->is_off_train_ticket_available = $validatedData['is_off_train_ticket_available'];
            $trainStation->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully created new Train Class'], 201);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['status' => 'fail', 'message' => 'Failed to create train cfm class detail'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            $trainStation = DB::table('train_stations as ts')->join('train_lines as tl', 'tl.id', 'ts.line_id')
                ->select('ts.*', 'tl.line_name')->where('ts.id', $id)->first();

            if (!$trainStation) {
                throw new RestApiException(404, 'No train station found!');
            }
            return $this->success($trainStation, DATA_RETRIEVED);
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
        try {
            $validatedData = $request->validate([
                'station_name' => 'required|string',
                'station_name_initial' => 'required|string',
                'province' => 'required|string',
                'line' => 'required|integer',
                'first_class' => 'required|in: 1,0',
                'second_class' => 'required|in: 2,0',
                'third_class' => 'required|in:3,0',
                'automotora' => 'required|in: 1,0',
                'cargo' => 'required|in: 1,0',
                'normal' => 'required|in: 1,0',
                'zone' => 'required|in: 0,1,2,3,4',
                'zone_desc' => 'required|in: 0,1,2,3,4',
//                'zone' => [
//                    'required',
//                    function ($attribute, $value, $fail) {
//                        if ($value === 0) {
//                            return; // Allow "0" as "no zone selected"
//                        }
//                        if (!DB::table('zones')->where('id', $value)->exists()) {
//                            $fail($attribute . ' does not exist in the zones table.');
//                        }
//                    },
//                ],
//                'zone_desc' => [
//                    'required',
//                    function ($attribute, $value, $fail) {
//                        if ($value === 0) {
//                            return; // Allow "0" as "no zone selected"
//                        }
//                        if (!DB::table('zones')->where('id', $value)->exists()) {
//                            $fail($attribute . ' does not exist in the zones table.');
//                        }
//                    },
//                ],
                'is_off_train_ticket_available' => 'nullable|boolean',
                'distance_maputo' => 'required|numeric',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
            ]);

            $trainStation = TrainStation::find($id);
            if (empty($trainStation)) {
                return response()->json(['status' => 'fail', 'message' => 'No train station found!'], 500);
            }

            DB::beginTransaction();
            try {
                $trainStation->station_name = $request['station_name'];
                $trainStation->station_name_erp = $request['station_name_initial'];
                $trainStation->province = $request['province'];
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
//                dd($trainStation);

                return response()->json(['status' => 'success', 'message' => 'Successfully updated Train station'], 201);
            } catch (\Exception $e) {
                dd($e);
                DB::rollBack();
                Log::error($e->getMessage());
                return response()->json(['status' => 'fail', 'message' => 'Failed to update Train station'], 500);
            }
        } catch (ValidationException $e) {
            return response()->json(['status' => 'fail', 'message' => 'Validation errors', 'errors' => $e->errors()], 422);
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
