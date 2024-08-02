<?php

namespace App\Http\Controllers\Api\Wagon;

use App\Exceptions\RestApiException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\Wagon\Seat;
use App\Models\Wagon\WagonLayout;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use App\Traits\SeatTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SeatController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait, SeatTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'wagon_layout_id' => 'required|exists:wagon_layouts,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $wagonLayoutId = $request->input('wagon_layout_id');
        $searchQuery = $request->input('search_query');
        try {
            $query = Seat::select('id', 'token', 'seat_number', 'row', 'column', 'number', 'has_aisle')->where('wagon_layout_id', $wagonLayoutId);
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('number', 'like', "%$searchQuery%");
                });
            }
            $seatType = $query->orderByDesc('updated_at')->get();

            if (!$seatType) {
                throw new RestApiException(404, 'No seat type found!');
            }
            $this->auditLog("View all seats", PORTAL, null, null);
            return $this->success($seatType, DATA_RETRIEVED);
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
    public function store(Request $request)
    {
        //
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

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function generateWagonLayoutSeats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wagon_layout_id' => 'required|exists:wagon_layouts,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        DB::beginTransaction();
        try {
            $wagonLayout = WagonLayout::findOrFail($request->wagon_layout_id, ['id', 'name', 'label', 'seat_rows', 'seat_columns', 'aisle_interval', 'seat_generated', 'normal_seats']);
            if ($wagonLayout->seat_generated){
                return $this->warn(null, "Seat already generated");
            }
            $response = $this->generateSeat($wagonLayout);
            if (!$response){
                $this->auditLog("Attempt to generate seats for wagon layout: ". $wagonLayout->name .'-'. $wagonLayout->label, PORTAL, null, null);
                throw new RestApiException(422, 'No seat generated, for wagon layout: '. $wagonLayout->name .'-'. $wagonLayout->label);
            }
            $wagonLayout->seat_generated = true;
            $wagonLayout->save();
            $this->auditLog("Generate Seats for Wagon Layout: ". $wagonLayout->name .'-'. $wagonLayout->label, PORTAL, null, null);
            DB::commit();
            return $this->success($wagonLayout, DATA_UPDATED);
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            throw new RestApiException(404, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(500);
        }
    }
}
