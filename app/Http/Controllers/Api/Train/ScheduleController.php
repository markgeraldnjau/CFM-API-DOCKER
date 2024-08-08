<?php

namespace App\Http\Controllers\Api\Train;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Train\ScheduleRequest;
use App\Http\Requests\Train\UpdateScheduleRequest;

use App\Models\Train\Schedule;

use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use App\Traits\checkAuthPermsissionTrait;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    use ApiResponse, AuditTrail, CommonTrait, checkAuthPermsissionTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = validator($request->all(), [
            'search_query' => 'nullable|string|max:255',
            'item_per_page' => 'nullable|integer|max:255',
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
            $query = DB::table('schedules as sc')
                ->join('train_layouts as tl', 'tl.id', 'sc.train_layout_id')
                ->join('trains as t', 't.id', 'sc.train_id')
                ->join('train_stations as depart_s', 'depart_s.id', 'sc.departure_station_id')
                ->join('train_stations as dest_s', 'dest_s.id', 'sc.destination_station_id')
                ->select(
                    'sc.id',
                    'sc.token',
                    'tl.token as train_layout_token',
                    'sc.day_of_the_week',
                    'sc.trip_duration_hours',
                    'sc.train_layout_id',
                    't.train_number',
                    'tl.total_wagons',
                    'tl.total_seats',
                    'depart_s.station_name as destination_station_name',
                    'dest_s.station_name as departure_station_name',
                    'sc.departure_time',
                    'sc.est_destination_time',
                    'sc.updated_at',
                )->whereNull('sc.deleted_at');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('t.train_number', 'like', "%$searchQuery%")
                        ->where('depart_s.station_name', 'like', "%$searchQuery%")
                        ->where('dest_s.departure_time', 'like', "%$searchQuery%");
                });
            }
            $schudele = $query->orderByDesc('sc.updated_at')->paginate($itemPerPage);

            return $this->success($schudele, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    public function todaySchedules(Request $request)
    {
        $validator = validator($request->all(), [
            'search_query' => 'nullable|string|max:255',
            'item_per_page' => 'nullable|integer|max:255',
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
        $todayDayOfWeek = now()->dayOfWeekIso;
        try {
            $query = DB::table('schedules as sc')
                ->join('train_layouts as tl', 'tl.id', 'sc.train_layout_id')
                ->join('trains as t', 't.id', 'sc.train_id')
                ->join('train_stations as depart_s', 'depart_s.id', 'sc.departure_station_id')
                ->join('train_stations as dest_s', 'dest_s.id', 'sc.destination_station_id')
                ->select(
                    'sc.id',
                    'sc.token',
                    'tl.token as train_layout_token',
                    'sc.day_of_the_week',
                    'sc.trip_duration_hours',
                    'sc.train_layout_id',
                    't.train_number',
                    'tl.total_wagons',
                    'tl.total_seats',
                    'depart_s.station_name as destination_station_name',
                    'dest_s.station_name as departure_station_name',
                    'sc.departure_time',
                    'sc.est_destination_time',
                    'sc.updated_at',
                )->where('day_of_the_week', $todayDayOfWeek)->whereNull('sc.deleted_at');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('t.train_number', 'like', "%$searchQuery%")
                        ->where('depart_s.station_name', 'like', "%$searchQuery%")
                        ->where('dest_s.departure_time', 'like', "%$searchQuery%");
                });
            }
            $schudele = $query->orderByDesc('sc.updated_at')->paginate($itemPerPage);

            $this->auditLog("View Train Schedules", PORTAL, null, null);
            return $this->success($schudele, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function allSchedules(): \Illuminate\Http\JsonResponse
    {
        //
        try {
            $schedules = DB::table('schedules as sc')
                ->join('train_layouts as tl', 'tl.id', 'sc.train_layout_id')
                ->join('trains as t', 't.id', 'sc.train_id')
                ->join('train_stations as depart_s', 'depart_s.id', 'sc.departure_station_id')
                ->join('train_stations as dest_s', 'dest_s.id', 'sc.destination_station_id')
                ->select(
                    'sc.id',
                    'sc.token',
                    'sc.day_of_the_week',
                    'sc.train_layout_id',
                    't.train_number',
                    'tl.total_wagons',
                    'tl.total_seats',
                    'depart_s.station_name as destination_station_name',
                    'dest_s.station_name as departure_station_name',
                    'sc.departure_time',
                    'sc.est_destination_time',
                    'sc.updated_at',
                )->whereNull('sc.deleted_at')->get();

            if (!$schedules) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No schedule found!');
            }
            $this->auditLog("View train schedules", PORTAL, null, null);
            return $this->success($schedules, DATA_RETRIEVED);
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
    public function store(ScheduleRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            $exists = DB::table('schedules')
                ->select('id')
                ->where('day_of_the_week', $request->day_of_the_week)
                ->where('departure_time', $request->departure_time)
                ->where('est_destination_time', $request->est_destination_time)
                ->where('train_layout_id', $request->train_layout_id)
                ->exists();

            if ($exists) {
                return $this->error(null, "The schedule for this combination of day, departure time, estimated destination time, and train layout already exists.");
            }

            $train = DB::table('trains as t')->join('train_layouts as l', 'l.train_id', 't.id')
                ->select('t.train_number', 't.id', 't.start_stop_id', 't.end_stop_id')->where('l.id', $request->train_layout_id)->first();

            if (!$train) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No train found!, contact admin for support');
            }

            if ($request->day_of_the_week == ALL_DAYS) {
                $allDays = [MONDAY, TUESDAY, WEDNESDAY, THURSDAY, FRIDAY, SATURDAY, SUNDAY];
                foreach ($allDays as $day) {
                    $payload = [
                        'train_id' => $train->id,
                        'day_of_the_week' => $day,
                        'trip_duration_hours' => $request->trip_duration_hours,
                        'departure_time' => $request->departure_time,
                        'est_destination_time' => $request->est_destination_time,
                        'train_layout_id' => $request->train_layout_id,
                        'departure_station_id' => $train->start_stop_id,
                        'destination_station_id' => $train->end_stop_id,
                    ];
                    $schedule = Schedule::updateOrCreate($payload);
                }

            } else {
                $payload = [
                    'train_id' => $train->id,
                    'day_of_the_week' => $request->day_of_the_week,
                    'trip_duration_hours' => $request->trip_duration_hours,
                    'departure_time' => $request->departure_time,
                    'est_destination_time' => $request->est_destination_time,
                    'train_layout_id' => $request->train_layout_id,
                    'departure_station_id' => $train->start_stop_id,
                    'destination_station_id' => $train->end_stop_id,
                ];
                $schedule = Schedule::updateOrCreate($payload);
            }
            $this->auditLog("Create train schedule for : " . $train->train_number, PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($schedule, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($token)
    {
        if (is_null($token)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        try {
            $schedule = DB::table('schedules as sc')
                ->join('train_layouts as tl', 'tl.id', 'sc.train_layout_id')
                ->join('trains as t', 't.id', 'sc.train_id')
                ->join('train_stations as depart_s', 'depart_s.id', 'sc.departure_station_id')
                ->join('train_stations as dest_s', 'dest_s.id', 'sc.destination_station_id')
                ->select(
                    'sc.id',
                    'sc.token',
                    'sc.day_of_the_week',
                    'sc.train_layout_id',
                    't.train_number',
                    'tl.total_wagons',
                    'tl.total_seats',
                    'depart_s.station_name as destination_station_name',
                    'dest_s.station_name as departure_station_name',
                    'sc.departure_time',
                    'sc.est_destination_time',
                    'sc.updated_at',
                )->where('sc.token', $token)->whereNull('sc.deleted_at')->first();

            if (!$schedule) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No train schedule found!');
            }

            $this->auditLog("View Train schedule: " . $schedule->train_number, PORTAL, $schedule, $schedule);
            return $this->success($schedule, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
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
    public function update(ScheduleRequest $request, string $scheduleId)
    {
        if (is_null($scheduleId) || !is_numeric($scheduleId)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        DB::beginTransaction();
        try {

            $exists = DB::table('schedules')
                ->select('id')
                ->where('day_of_the_week', $request->day_of_the_week)
                ->where('departure_time', $request->departure_time)
                ->where('est_destination_time', $request->est_destination_time)
                ->where('train_layout_id', $request->train_layout_id)
                ->exists();

            if ($exists) {
                return $this->error(null, "The schedule for this combination of day, departure time, estimated destination time, and train layout already exists.");
            }

            $schedule = Schedule::findOrFail($scheduleId);
            $oldData = clone $schedule;

            $train = DB::table('trains as t')->join('train_layouts as l', 'l.train_id', 't.id')
                ->select('t.train_number', 't.start_stop_id', 't.end_stop_id')->where('l.id', $request->train_layout_id)->first();

            if (!$train) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No train found!, contact admin for support');
            }
            $payload = [
                'day_of_the_week' => $request->day_of_the_week,
                'departure_time' => $request->departure_time,
                'est_destination_time' => $request->est_destination_time,
                'train_layout_id' => $request->train_layout_id,
                'departure_station_id' => $train->start_stop_id,
                'destination_station_id' => $train->end_stop_id,
            ];
            $schedule->update($payload);
            $this->auditLog("Update train schedule for : " . $train->train_number, PORTAL, $oldData, $payload);
            DB::commit();
            return $this->success($schedule, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($token)
    {
        if (is_null($token)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        try {
            $schedule = Schedule::where('token', $token)->firstOrFail();
            $schedule->delete();
            $this->auditLog("Delete Train Schedule: " . $schedule->train_layout_id, PORTAL, $schedule, null);
            return $this->success(null, DATA_DELETED);
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
}
