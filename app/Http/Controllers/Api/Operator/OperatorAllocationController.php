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

class OperatorAllocationController extends Controller
{
    use ApiResponse, AuditTrail;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

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
                throw new RestApiException(404, 'No train station schedule times found!');
            }

            // return $this->success($trainLines, DATA_RETRIEVED);
            return response()->json($operatorAllocations);

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


    public function allocate_seat()
    {
        //

        $trains = DB::select('SELECT train_id_asc FROM `operator_allocations` GROUP BY train_id_asc');
        foreach ($trains as $train ) {
            # code...
            $train_id = $train->train_id_asc;
            $totalseat = $this->getTotalSeat($train_id);
            DB::update('update operator_allocations set seat = ? where train_id_asc = ?', [$totalseat,$train_id]);
        }

        return response()->json(['status' => 'success', 'message' => 'Seat updated successfully'], 201);


    }

    public function getTotalSeat($trainid){
        $total_seat = DB::table('train_layouts')->where('train_id', $trainid)->value('total_normal_seats');
        return $total_seat ? $total_seat : 0;
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
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
            Log::error($th->getMessage());
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], 200);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
//        dd($id);
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
                return $this->error(null, "No operator allocation found!", 404);
            }

            $this->auditLog("View Operator Allocations: ". $operatorAllocation->full_name, PORTAL, null, null);
            return $this->success($operatorAllocation, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
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
    public function update(UpdateOperatorAllocationRequest $request, $id)
    {
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
            Log::error($th->getMessage());
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], 200);
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
