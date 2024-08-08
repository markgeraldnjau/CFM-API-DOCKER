<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Train\CreateTrainRequest;
use App\Http\Requests\Train\UpdateTrainRequest;
use App\Models\Train;
use App\Http\Controllers\Controller;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use App\Exceptions\RestApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainController extends Controller
{
    use ApiResponse, AuditTrail,CommonTrait;
    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index()
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
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function train_details_informations()
    {
        try {
            $trains = DB::table('trains')->get();
            return response()->json($trains);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function allTrainSelectedInfo()
    {
        try {
            $trains = Train::select('id', 'train_number')->get();

            if (!$trains) {
                return $this->error(null, "No train found", 404);
            }
            $this->auditLog("View All Trains", PORTAL, null, null);
            return $this->success($trains, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            return $this->error(null, $errorMessage, $statusCode);
        }
    }

    public function train_detail(Request $request)
    {
        $validator = validator($request->all(), [
            'id' => 'required|integer|exists:trains,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $columns = [
                'id',
                'train_number',
                'train_name',
                'etd',
                'train_type',
                'departure_day',
                'arrival_day',
                'route_id',
                'start_stop_id',
                'end_stop_id',
                'activated',
                'last_update',
                'train_first_class',
                'train_second_class',
                'train_third_class',
                'pair_train',
                'train_capacity',
                'travel_hours_duration',
                'zone_one',
                'zone_two',
                'zone_three',
                'zone_four',
                'int_price_group',
                'reverse_train_id',
                'etd_24_format',
                'created_at',
                'updated_at'
            ];

             $trains = DB::table('trains')
                 ->select($columns)
                ->where('id','=',$validator['id'])
                ->get();

            return response()->json($trains);

        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
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
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
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
                throw new RestApiException(HTTP_NOT_FOUND, 'No train found!');
            }
            return $this->success($Train, DATA_RETRIEVED);
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
    public function update(UpdateTrainRequest $request, $id)
    {
        $train = Train::findOrFail($id);
        DB::beginTransaction();
        try {

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

            $train->update($payload);

            $this->auditLog("Update Train: " . $train->train_name . '-' . $train->train_number, PORTAL, $payload, $payload);

            DB::commit();

            return $this->success($train, DATA_UPDATED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

}
