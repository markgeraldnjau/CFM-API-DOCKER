<?php

namespace App\Http\Controllers\Api\Train;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CardCustomer;
use App\Models\Wagon;
use App\Models\TrainWagonCabin;
use App\Models\TrainWagonSetup;
use App\Models\User;
use App\Traits\ApiResponse;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrainCabinSetupController extends Controller
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
            $cabinSetups = TrainWagonCabin::select('train_wagon_cabin_setups.id','train_wagon_cabin_setups.cabin_label', 'tw.serial_number as wagon','tc.name as cabin')
                ->join('train_wagon as tw', 'tw.id', '=', 'train_wagon_cabin_setups.wagon_id')
                ->join('train_cabins as tc', 'tc.id', '=', 'train_wagon_cabin_setups.cabin_id')
                ->orderBy('train_wagon_cabin_setups.id', 'desc')
                ->get();
            return $this->success($cabinSetups, DATA_RETRIEVED);
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
            'cabin_id' => 'required',
            'wagon_id' => 'required',
            'cabin_label' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $cabinSetup = new TrainWagonCabin();
            $cabinSetup->cabin_id = $validatedData['cabin_id'];
            $cabinSetup->wagon_id = $validatedData['wagon_id'];
            $cabinSetup->cabin_label = $validatedData['cabin_label'];
            $cabinSetup->save();

            DB::commit();
            return $this->success($cabinSetup,DATA_SAVED);
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
        $cabin = TrainWagonCabin::find($id);
        if (!$cabin) {
            return response()->json(['message' => 'cabin not found'], 404);
        }
        return response()->json($cabin, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $cabin = TrainWagonCabin::find($id);
        if (!$cabin) {
            return response()->json(['message' => 'cabin not found'], 404);
        }
        return response()->json($cabin, 200);
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
            'cabin_id' => 'required',
            'wagon_id' => 'required',
            'cabin_label' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $cabinSetup = TrainWagonCabin::find($id);
            if (!$cabinSetup) {
                return response()->json(['message' => 'Cabin not found'], 404);
            }

            $cabinSetup->cabin_id = $validatedData['cabin_id'];
            $cabinSetup->wagon_id = $validatedData['wagon_id'];
            $cabinSetup->cabin_label = $validatedData['cabin_label'];
            $cabinSetup->save();

            DB::commit();

            return response()->json(['message' => 'User updated successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to update user'], 500);
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
