<?php

namespace App\Http\Controllers\Api\Management;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Train;
use App\Models\TrainStation;
use App\Models\TrainStationScheduleTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TrainStationScheduleTimeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Log::info("page item are");
        Log::info($request->all());
        try {

                    $trainStationScheduleTimes = DB::table('train_station_schedule_times')
                        ->select('train_station_schedule_times.*', 'train_stations.station_name', 'trains.train_number')
                    ->join('train_stations' ,'train_stations.id','=','train_station_schedule_times.station_id')
                    ->join('trains' ,'trains.id','=','train_station_schedule_times.train_id')
                    ->get();


            // return $this->success($trainLines, DATA_RETRIEVED);
            return response()->json($trainStationScheduleTimes);

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
        //
        $train = DB::table('train_station_schedule_times')
        ->where('train_id', $request->train_number)
        ->where('station_id', $request->station)
        ->first();

        if(empty($train)){
        DB::table('train_station_schedule_times')->insert([
            'train_id' => $request->train_number,
            'station_id' => $request->station,
            'time_arrive' => $request->arrival_time,
            'time_departure' => $request->departure_time,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Successfully created new Train Schedule'], 201);
    }else{
        return response()->json(['status' => 'success', 'message' => 'Trains Details exist'], 201);
    }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }


    public function update(Request $request)
    {
        try {
            // Validate the request data
            $validatedData = $request->validate([
                'id' => ['required', 'integer'],
                'train_number' => 'required|integer',
                'station' => 'required|integer',
                'arrival_time' => 'required|date_format:H:i',
                'departure_time' => 'required|date_format:H:i',
            ]);

            // Check if the schedule exists
            $train = DB::table('train_station_schedule_times')
                ->where('id', $validatedData['id'])
                ->first();

            if (empty($train)) {
                return response()->json(['status' => 'failed', 'message' => 'Train schedule not found'], 200);
            } else {
                $trainId = DB::table('trains')->where('id', $validatedData['train_number'])->value('id');
                $stationId = DB::table('train_stations')->where('id', $validatedData['station'])->value('id');
                if (empty($trainId) || empty($stationId)) {
                    return response()->json(['status' => 'failed', 'message' => 'Invalid train or station selected'], 200);
                }

                // Update existing schedule
                DB::table('train_station_schedule_times')
                    ->where('id', $validatedData['id'])
                    ->update([
                        'train_id' => $trainId,
                        'station_id' => $stationId,
                        'time_arrive' => $validatedData['arrival_time'],
                        'time_departure' => $validatedData['departure_time'],
                    ]);

                return response()->json(['status' => 'success', 'message' => 'Train Schedule updated successfully'], 200);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'failed', 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return response()->json(['status' => 'failed', 'message' => 'An error occurred while updating the train schedule'], 500);
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
