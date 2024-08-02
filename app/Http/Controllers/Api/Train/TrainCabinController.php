<?php

namespace App\Http\Controllers\Api\Train;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CardCustomer;
use App\Models\TrainCabin;
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

class TrainCabinController extends Controller
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
            $cabins = TrainCabin::select('train_cabins.id', 'train_cabins.total_seat_no', 'train_cabins.label', 'train_cabins.name',
                 'tct.name as class_name', 'tcc.name as contain_name')
                ->join('train_cabins_type as tct', 'tct.id', '=', 'train_cabins.cabin_type_id')
                ->join('train_cabins_contain as tcc', 'tcc.id', '=', 'train_cabins.contain_id')
                ->orderBy('train_cabins.id', 'desc')
                ->get();

            return $this->success($cabins, DATA_RETRIEVED);
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
            'name' => 'required',
            'cabin_type_id' => 'required',
            'label' => 'required',
            'total_seat_no' => 'required',
            'contain_id' => 'required',
        ]);

        DB::beginTransaction();
        try {

            $cabin = new TrainCabin();
            $cabin->name = $validatedData['name'];
            $cabin->cabin_type_id = $validatedData['cabin_type_id'];
            $cabin->label = $validatedData['label'];
            $cabin->total_seat_no = $validatedData['total_seat_no'];
            $cabin->contain_id = $validatedData['contain_id'];
            $cabin->user_id = 1;
            $cabin->save();

            DB::commit();
            return $this->success($cabin,DATA_SAVED);
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
        $cabin = TrainCabin::find($id);
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
            'name' => 'required',
            'cabin_type_id' => 'required',
            'label' => 'required',
            'total_seat_no' => 'required',
            'contain_id' => 'required'
        ]);

        DB::beginTransaction();
        try {
            $cabin = TrainCabin::find($id);
            if (!$cabin) {
                return response()->json(['message' => 'Cabin not found'], 404);
            }

            $cabin->name = $validatedData['name'];
            $cabin->cabin_type_id = $validatedData['cabin_type_id'];
            $cabin->label = $validatedData['label'];
            $cabin->total_seat_no = $validatedData['total_seat_no'];
            $cabin->contain_id = $validatedData['contain_id'];
            $cabin->save();

            DB::commit();

            return response()->json(['message' => 'Cabin updated successfully'], 201);
        } catch (\Exception $e) {
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
