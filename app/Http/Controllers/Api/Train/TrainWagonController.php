<?php

namespace App\Http\Controllers\Api\Train;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CardCustomer;
use App\Models\TrainWagon;
use App\Models\User;
use App\Traits\ApiResponse;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrainWagonController extends Controller
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
            $users = TrainWagon::select('train_wagon.id', 'twt.name as wagon_type', 'twc.name as wagon_class', 'train_wagon.model', 'train_wagon.serial_number')
                ->join('train_wagon_types as twt', 'twt.id', '=', 'train_wagon.wagon_type_id')
                ->join('train_wagon_class as twc', 'twc.id', '=', 'train_wagon.wagon_class_id')
                ->orderBy('id', 'desc')
                ->get();

            return $this->success($users, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
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
            'wagon_type_id' => 'required',
            'wagon_class_id' => 'required',
            'model' => 'required',
            'serial_number' => 'required'
        ]);

        DB::beginTransaction();
        try {

            $wagon = new TrainWagon();
            $wagon->wagon_type_id = $validatedData['wagon_type_id'];
            $wagon->wagon_class_id = $validatedData['wagon_type_id'];
            $wagon->model = $validatedData['model'];
            $wagon->serial_number = $validatedData['serial_number'];
            $wagon->save();

            DB::commit();

         return $this->success($wagon,DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
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
        $wagon = TrainWagon::find($id);
        if (!$wagon) {
            return response()->json(['message' => 'wagon not found'], 404);
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
            'wagon_type_id' => 'required',
            'wagon_class_id' => 'required',
            'model' => 'required',
            'serial_number' => 'required'
        ]);

        DB::beginTransaction();
        try {
            $wagon = TrainWagon::find($id);
            if (!$wagon) {
                return response()->json(['message' => 'Wagon not found'], 404);
            }

            $wagon->wagon_type_id = $validatedData['wagon_type_id'];
            $wagon->model = $validatedData['model'];
            $wagon->wagon_class_id = $validatedData['wagon_class_id'];
            $wagon->serial_number = $validatedData['serial_number'];
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
