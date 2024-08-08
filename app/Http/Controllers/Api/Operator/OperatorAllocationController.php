<?php

namespace App\Http\Controllers\Api\Operator;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\UpdateOperatorAllocationRequest;
use App\Models\OperatorAllocation;
use App\Traits\AuditTrail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;


class OperatorAllocationController extends Controller
{
    use ApiResponse, AuditTrail, CommonTrait;

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
            $operatorAllocations = OperatorAllocation::select(
                'operator_allocations.*',
                'operators.full_name',
                'trains.train_number',
                'wagons.serial_number',
                'wagon_layouts.normal_seats',
                'wagon_layouts.standing_seats',
            )
                ->join('operators', 'operators.id', '=', 'operator_allocations.operator_id')
                ->join('trains', 'trains.id', '=', 'operator_allocations.train_id_asc')
                ->join('wagons', 'wagons.id', '=', 'operator_allocations.wagon_id')
                ->join('wagon_layouts', 'wagon_layouts.id', '=', 'wagons.layout_id')
                ->paginate($request->items_per_page);

            if (!$operatorAllocations) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No train station schedule times found!');
            }

            return response()->json($operatorAllocations);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function allocate_seat()
    {

        $trains = DB::select('SELECT train_id_asc FROM `operator_allocations` GROUP BY train_id_asc');
        DB::beginTransaction();
        try {
            foreach ($trains as $train) {
                # code...
                $train_id = $train->train_id_asc;
                $totalseat = $this->getTotalSeat($train_id);

                DB::update('update operator_allocations set seat = ? where train_id_asc = ?', [$totalseat, $train_id]);
            }

            return response()->json(['status' => SUCCESS_RESPONSE, 'message' => DATA_UPDATED], HTTP_CREATED);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['message' => FAILED], HTTP_INTERNAL_SERVER_ERROR);

        }


    }

    public function getTotalSeat($trainid)
    {
        if (is_null($trainid) || !is_string($trainid)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $total_seat = DB::table('train_layouts')->where('train_id', $trainid)->value('total_normal_seats');
        return $total_seat ?? 0;
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'operator_id' => 'required|integer',
            'train_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        $operator_id = $request->operator_id;
        $train_id = $request->train_id;

        $firstRecord = DB::table('train_layouts')
            ->join('train_wagons', 'train_wagons.train_layout_id', '=', 'train_layouts.id')
            ->join('wagons', 'wagons.id', '=', 'train_wagons.wagon_id')
            ->join('wagon_layouts', 'wagon_layouts.id', '=', 'wagons.layout_id')
            ->where('train_layouts.train_id', $train_id)
            ->where('wagons.used_by_operator', '0')
            ->select('wagon_layouts.total_seats as seats', 'wagons.id as id')
            ->first();

        DB::beginTransaction();
        try {
            if (!empty($firstRecord)) {
                $operatorAllocation = new OperatorAllocation();
                $operatorAllocation->operator_id = $operator_id;
                $operatorAllocation->train_id_asc = $train_id;
                $operatorAllocation->seat = $firstRecord->seats;
                $operatorAllocation->wagon_id = $firstRecord->id;
                $operatorAllocation->save();

                DB::update('UPDATE wagons SET used_by_operator = 1 WHERE id = ?', [$firstRecord->id]);

                DB::commit(); // Commit the transaction here

                return $this->success(null, "Successfully Created New Train Allocations");
            } else {
                return $this->error(null, "No coach available!");
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Display the specified resource.
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
        try {
            $operatorAllocation = OperatorAllocation::select(
                'operator_allocations.*',
                'operators.full_name',
                'operators.username',
                'operators.phone',
                'operators.email',
                'trains.train_number',
                'wagons.serial_number',
                'wagon_layouts.normal_seats',
                'wagon_layouts.standing_seats',
                'wagons.model as wagon_model_number',
                'wagons.serial_number as wagon_serial_number',
            )
                ->join('operators', 'operators.id', '=', 'operator_allocations.operator_id')
                ->join('trains', 'trains.id', '=', 'operator_allocations.train_id_asc')
                ->join('wagons', 'wagons.id', '=', 'operator_allocations.wagon_id')
                ->join('wagon_layouts', 'wagon_layouts.id', '=', 'wagons.layout_id')
                ->where('operator_allocations.id', $id)->first();

            if (empty($operatorAllocation)) {
                return $this->error(null, "No operator allocation found!", HTTP_NOT_FOUND);
            }

            $this->auditLog("View Operator Allocations: " . $operatorAllocation->full_name, PORTAL, null, null);
            return $this->success($operatorAllocation, DATA_RETRIEVED);
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


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOperatorAllocationRequest $request, $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }

        $validator = validator($request->all(), [
            'operator_id' => 'required|integer',
            'train_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        $operator_id = $request->operator_id;
        $train_id = $request->train_id;

        $firstRecord = DB::table('train_layouts')
            ->join('train_wagons', 'train_wagons.train_layout_id', '=', 'train_layouts.id')
            ->join('wagons', 'wagons.id', '=', 'train_wagons.wagon_id')
            ->join('wagon_layouts', 'wagon_layouts.id', '=', 'wagons.layout_id')
            ->where('train_layouts.train_id', $train_id)
            ->where('wagons.used_by_operator', '0')
            ->select('wagon_layouts.total_seats as seats', 'wagons.id as id')
            ->first();

        DB::beginTransaction();
        try {
            $operatorAllocation = OperatorAllocation::find($id);

            if ($operatorAllocation) {
                // If a new wagon is available, update the wagon assignment
                if (!empty($firstRecord)) {
                    // Reset the previously used wagon
                    DB::update('UPDATE wagons SET used_by_operator = 0 WHERE id = ?', [$operatorAllocation->wagon_id]);

                    $operatorAllocation->wagon_id = $firstRecord->id;
                    $operatorAllocation->seat = $firstRecord->seats;

                    DB::update('UPDATE wagons SET used_by_operator = 1 WHERE id = ?', [$firstRecord->id]);
                }

                // Update other details
                $operatorAllocation->operator_id = $operator_id;
                $operatorAllocation->train_id_asc = $train_id;
                $operatorAllocation->save();

                DB::commit();

                return $this->success(null, "Successfully Updated Train Allocations");
            } else {
                return $this->error(null, "Allocation not found!");
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}
