<?php

namespace App\Http\Controllers\Api\Setting;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\SpecialGroup;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpecialGroupController extends Controller
{
    use ApiResponse, CommonTrait;

    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {

        try {
            $specials = SpecialGroup::select('special_groups.id', 'special_groups.title', 'special_groups.percent', 'special_groups.main_category', 'special_groups.is_used_for_tranx')
                ->orderBy('id', 'asc')
                ->get();

            return $this->success($specials, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = validator($request->all(), [
            'title' => 'required|string|max:35',
            'percent' => 'required|string|max:3',
            'is_used_for_tranx' => 'required|integer',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'code' => HTTP_UNPROCESSABLE_ENTITY,
                'errors' => $validatedData->errors()->messages()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        DB::beginTransaction();
        try {

            $data = new SpecialGroup();
            $data->title = strip_tags($validatedData['title']);
            $data->percent = strip_tags($validatedData['percent']);
            $data->is_used_for_tranx = $validatedData['is_used_for_tranx'];
            $data->save();

            DB::commit();
            return $this->success($data, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
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
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        $data = SpecialGroup::findOrFail($id);
        if (!$data) {
            return response()->json(['message' => NOT_FOUND], HTTP_NOT_FOUND);
        }
        return response()->json($data, HTTP_OK);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        try {
            //code...
        } catch (\Throwable $th) {
            Log::error(json_encode($this->errorPayload($th)));
            $statusCode = $th->getCode() ?: 500;
            $errorMessage = $th->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
        $data = SpecialGroup::findOrFail($id);
        if (!$data) {
            return response()->json(['message' => NOT_FOUND], HTTP_NOT_FOUND);
        }
        return response()->json($data, HTTP_OK);
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
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        $validatedData = validator($request->all(), [
            'title' => 'required|string|max:35',
            'percent' => 'required|string|max:3',
            'is_used_for_tranx' => 'required|integer',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $data = SpecialGroup::findOrFail($id);

        DB::beginTransaction();
        try {
            if (!$data) {
                return response()->json(['message' => NOT_FOUND], HTTP_NOT_FOUND);
            }

            $data->title = $validatedData['title'];
            $data->main_category = $validatedData['main_category'];
            $data->percent = $validatedData['percent'];
            $data->is_used_for_tranx = $validatedData['is_used_for_tranx'];
            $data->save();
            DB::commit();
            return response()->json(['message' => 'Cabin updated successfully'], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to update cabin'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
