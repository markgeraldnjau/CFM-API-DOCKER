<?php

namespace App\Http\Controllers\Api\Train;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CardCustomer;
use App\Models\Wagon;
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

class TrainWagonSetupController extends Controller
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
            $wagonSetups = TrainWagonSetup::select('train_wagon_setups.id', 't.train_Number as train_number', 'w.serial_number as wagon_name', 'train_wagon_setups.wagon_label')
                ->join('trains as t', 't.id', '=', 'train_wagon_setups.train_id')
                ->join('train_wagon as w', 'w.id', '=', 'train_wagon_setups.wagon_id')
                ->orderBy('id', 'desc')
                ->get();
            return $this->success($wagonSetups, DATA_RETRIEVED);
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
            'wagon_id' => 'required',
            'wagon_label' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $wagon = new TrainWagonSetup();
            $wagon->train_id = $validatedData['train_id'];
            $wagon->wagon_id = $validatedData['wagon_id'];
            $wagon->wagon_label = $validatedData['wagon_label'];
            $wagon->save();
            DB::commit();
            return $this->success($wagon,DATA_SAVED);
        } catch (\Exception $e) {
            dd($e);
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $wagon = TrainWagonSetup::find($id);
        if (!$wagon) {
            return response()->json(['message' => 'wagon setup not found'], 404);
        }
        return response()->json($wagon, 200);
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
            'wagon_id' => 'required',
            'wagon_label' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $wagon = TrainWagonSetup::find($id);
            if (!$wagon) {
                return response()->json(['message' => 'Wagon setup not found'], 404);
            }

            $wagon->train_id = $validatedData['train_id'];
            $wagon->wagon_id = $validatedData['wagon_id'];
            $wagon->wagon_label = $validatedData['wagon_label'];
            $wagon->save();

            DB::commit();

            return response()->json(['message' => 'Wagon updated successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to update wagon'], 500);
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
