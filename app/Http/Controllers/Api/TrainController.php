<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Train\CreateTrainRequest;
use App\Http\Requests\Train\UpdateTrainRequest;
use App\Models\Train;

use App\Http\Controllers\Controller;
use App\Traits\AuditTrail;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use App\Exceptions\RestApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainController extends Controller
{
    use ApiResponse, AuditTrail;
    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        try {

                $trains = DB::table('trains')
                ->join('train_routes', 'train_routes.id', '=', 'trains.route_id')
                ->join('train_lines', 'train_lines.id', '=', 'train_routes.train_line_id')
                ->join('train_types', 'train_types.id', '=', 'trains.train_type')
                ->join('train_stations', 'train_stations.id', '=', 'trains.start_stop_id')
                ->join('train_stations as destination', 'destination.id', '=', 'trains.end_stop_id')
                ->select('trains.*','train_routes.route_name','train_routes.route_direction',
                'train_lines.line_name','train_types.type_name',
                'train_stations.station_name','destination.station_name as destination')
                ->get();

            return response()->json($trains);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function train_details_informations(Request $request)
    {

        try {
            $trains = DB::table('trains')->get();
            return response()->json($trains);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function allTrainSelectedInfo(Request $request)
    {
        try {
            $trains = Train::select('id', 'train_number')->get();

            if (!$trains) {
                return $this->error(null, "No train found", 404);
            }
            $this->auditLog("View All Trains", PORTAL, null, null);
            return $this->success($trains, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            return $this->error(null, $errorMessage, $statusCode);
        }
    }

    public function train_detail(Request $request)
    {

        try {

                $trains = DB::table('trains')
                ->where('id','=',$request->id)
                ->get();

            return response()->json($trains);

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
    public function store(CreateTrainRequest $request): \Illuminate\Http\JsonResponse
    {
        DB::beginTransaction();

        try {
            $payload = [
                'train_number' => $request->train_number,
                'train_name' => $request->train_name,
                'train_type' => $request->train_type,
                'route_id' => $request->train_route,
                'reverse_train_id' => $request->reverse_train,
                'start_stop_id' => $request->startstation,
                'end_stop_id' => $request->end_station,
                'activated' => $request->train_status,
                'train_first_class' => $request->train_first_class,
                'train_second_class' => $request->train_second_class,
                'train_third_class' => $request->train_third_class,
                'travel_hours_duration' => $request->travel_hours,
                'int_price_group' => $request->zone_price_group,
                'zone_one' => $request->zone_one,
                'zone_two' => $request->zone_two,
                'zone_three' => $request->zone_three,
                'zone_four' => $request->zone_four,
            ];

            $train = Train::create($payload);

            $this->auditLog("Create Train: " . $train->train_name . '-' . $train->train_number, PORTAL, $payload, $payload);

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Successfully Created New Train Line', 'data' => $train], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            $Train = Train::findOrFail($id);

            if (!$Train) {
                throw new RestApiException(404, 'No train found!');
            }
            return $this->success($Train, DATA_RETRIEVED);
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
    public function update(UpdateTrainRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $train = Train::findOrFail($id);

            $payload = [
                'train_name' => $request->train_name,
                'train_number' => $request->train_number,
                'train_type' => $request->train_type,
                'route_id' => $request->train_route,
                'reverse_train_id' => $request->reverse_train,
                'start_stop_id' => $request->startstation,
                'end_stop_id' => $request->end_station,
                'activated' => $request->train_status,
                'train_first_class' => $request->train_first_class,
                'train_second_class' => $request->train_second_class,
                'train_third_class' => $request->train_third_class,
                'travel_hours_duration' => $request->travel_hours,
                'zone_one' => $request->zone_one,
                'zone_two' => $request->zone_two,
                'zone_three' => $request->zone_three,
                'zone_four' => $request->zone_four,
            ];

//            dd($payload, $train);

            $train->update($payload);

            $this->auditLog("Update Train: " . $train->train_name . '-' . $train->train_number, PORTAL, $payload, $payload);

            DB::commit();

            return $this->success($train, DATA_UPDATED);
        } catch (\Exception $e) {
            dd($e);
            Log::error($e->getMessage());
            DB::rollBack();
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
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
