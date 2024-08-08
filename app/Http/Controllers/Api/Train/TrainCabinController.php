<?php

namespace App\Http\Controllers\Api\Train;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\TrainCabin;
use App\Traits\ApiResponse;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainCabinController extends Controller
{
    use CommonTrait, ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        try {
            $cabins = TrainCabin::select(
                'train_cabins.id',
                'train_cabins.total_seat_no',
                'train_cabins.label',
                'train_cabins.name',
                'tct.name as class_name',
                'tcc.name as contain_name'
            )
                ->join('train_cabins_type as tct', 'tct.id', '=', 'train_cabins.cabin_type_id')
                ->join('train_cabins_contain as tcc', 'tcc.id', '=', 'train_cabins.contain_id')
                ->orderBy('train_cabins.id', 'desc')
                ->get();

            return $this->success($cabins, DATA_RETRIEVED);
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
        $rules = [
            'name' => 'required|string|max:255', // Must be a string with a maximum length of 255 characters
            'cabin_type_id' => 'required|exists:cabin_types,id', // Must be an existing cabin type ID
            'contain_id' => 'required|exists:containers,id', // Must be an existing container ID
            'label' => 'required|string|max:255', // Must be a string with a maximum length of 255 characters
            'total_seat_no' => 'required|integer|min:0', // Must be an integer, zero or positive
        ];
        $validatedData = validator($request->all(), $rules);
        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

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
            return $this->success($cabin, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
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
            ], HTTP_BAD_REQUEST);
        }
        try {
            $cabin = TrainCabin::findOrFail($id);
            if (!$cabin) {
                return response()->json(['message' => 'cabin not found'], HTTP_NOT_FOUND);
            }
            return response()->json($cabin, HTTP_OK);
        } catch (\Throwable $th) {
            Log::error(json_encode($this->errorPayload($th)));
            $statusCode = $th->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $th->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }

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
            ], HTTP_BAD_REQUEST);
        }
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'cabin_type_id' => 'required|exists:cabin_types,id',
            'contain_id' => 'required|exists:containers,id',
            'label' => 'required|string|max:255',
            'total_seat_no' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $cabin = TrainCabin::find($id);
            if (!$cabin) {
                return response()->json(['message' => 'Cabin not found'], HTTP_NOT_FOUND);
            }

            $cabin->name = $validatedData['name'];
            $cabin->cabin_type_id = $validatedData['cabin_type_id'];
            $cabin->label = $validatedData['label'];
            $cabin->total_seat_no = $validatedData['total_seat_no'];
            $cabin->contain_id = $validatedData['contain_id'];
            $cabin->save();

            DB::commit();

            return response()->json(['message' => 'Cabin updated successfully'], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to update cabin'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
