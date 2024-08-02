<?php

namespace App\Http\Controllers\Api\Setting;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use App\Models\SpecialGroup;
use App\Models\TrainCabin;
use App\Models\TrainCabinSetting;
use App\Models\TrainWagon;
use App\Models\TrainWagonSetup;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrainLayoutController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        try {
            $tains = TrainCabinSetting::select('train_cabin_settings.id', 'train_cabin_settings.compatment_no','train_cabin_settings.cabin_no','train_cabin_settings.total_seat_no','t.train_number','twc.name as class')
                ->join('trains as t', 't.id', '=', 'train_cabin_settings.train_id')
                ->join('train_wagon_class as twc', 'twc.id', '=', 'train_cabin_settings.class_id')
                ->orderBy('id', 'asc')
                ->get();

            return $this->success($tains, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'train_id' => 'required',
            'cabin' => 'required',
            'compatment' => 'required',
            'seat_no' => 'required',
            'class_id' => 'required',
        ]);

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
            return $this->success($data,DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
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
        $data = TrainCabinSetting::find($id);
        if (!$data) {
            return response()->json(['message' => 'data not found'], 404);
        }
        return response()->json($data, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

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
        $validatedData = $request->validate([
            'train_id' => 'required',
            'cabin' => 'required',
            'compatment' => 'required',
            'seat_no' => 'required',
            'class_id' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $data = TrainCabinSetting::find($id);
            if (!$data) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $data->train_id = $validatedData['train_id'];
            $data->cabin_no = $validatedData['cabin'];
            $data->compatment_no = $validatedData['compatment'];
            $data->user_id = 1;
            $data->total_seat_no = $validatedData['seat_no'];
            $data->class_id = $validatedData['class_id'];
            $data->save();

            DB::commit();

            return response()->json(['message' => 'Data updated successfully'], 201);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
            \Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to update cabin'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
