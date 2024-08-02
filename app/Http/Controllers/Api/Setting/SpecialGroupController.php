<?php

namespace App\Http\Controllers\Api\Setting;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CardCustomer;
use App\Models\SpecialGroup;
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

class SpecialGroupController extends Controller
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
            $specials = SpecialGroup::select('special_groups.id', 'special_groups.title','special_groups.percent', 'special_groups.main_category', 'special_groups.is_used_for_tranx')
//                ->join('train_wagon_class as twc', 'twc.id', '=', 'train_wagon.wagon_class_id')
                ->orderBy('id', 'asc')
                ->get();

            return $this->success($specials, DATA_RETRIEVED);
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
            'title' => 'required',
            'percent' => 'required',
            'is_used_for_tranx' => 'required',
        ]);

        DB::beginTransaction();
        try {

            $data = new SpecialGroup();
            $data->title = $validatedData['title'];
//            $data->main_category = $validatedData['main_category'];
            $data->percent = $validatedData['percent'];
            $data->is_used_for_tranx = $validatedData['is_used_for_tranx'];
            $data->save();

            DB::commit();
            return $this->success($data,DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
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
        $data = SpecialGroup::find($id);
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
        $data = SpecialGroup::find($id);
        if (!$data) {
            return response()->json(['message' => 'data not found'], 404);
        }
        return response()->json($data, 200);
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
            'title' => 'required',
            'percent' => 'required',
            'main_category' => 'required',
            'is_used_for_tranx' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $data = SpecialGroup::find($id);
            if (!$data) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $data->title = $validatedData['title'];
            $data->main_category = $validatedData['main_category'];
            $data->percent = $validatedData['percent'];
            $data->is_used_for_tranx = $validatedData['is_used_for_tranx'];
            $data->save();

            DB::commit();

            return response()->json(['message' => 'Cabin updated successfully'], 201);
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
