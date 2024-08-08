<?php

namespace App\Http\Controllers\Api;


use App\Models\MainLine;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Exceptions\RestApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MainLineController extends Controller
{

    use ApiResponse, CommonTrait;
    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $trainLines = MainLine::select('id', 'line_name','line_distance')->latest('id')->get();
            Log::info('TRAIN_MAIN_LINE',['TRAIN_LINE'=>$trainLines]);

            return response()->json($trainLines);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $trainLine = new MainLine();
            $trainLine->line_code = strtoupper($request->line_name);
            $trainLine->line_name = $request->line_name;
            $trainLine->line_distance = $request->line_distance;
            $trainLine->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully Create New Train Line'], HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($th)));
            return response()->json(['status' =>  $request->line_name, 'message' => $th->getMessage()], HTTP_OK);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            $trainLine = MainLine::findOrFail($id, ['id', 'line_code', 'line_name']);

            if (!$trainLine) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No train line found!');
            }
            return $this->success($trainLine, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try{
            $trainLine = MainLine::find($id);
            if ($trainLine) {
                $trainLine->line_code = strtoupper($request->line_code);
                $trainLine->line_name = $request->line_name;
                $trainLine->line_distance = $request->line_distance;
                $trainLine->update();
                return response()->json(['status' => 'success', 'message' => 'Successfully update Train Line'], HTTP_OK);
            } else {
                return response()->json(['status' => 'failed', 'message' => 'Train Line not found'], HTTP_OK);
            }
        }catch (\Exception $e){
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

}
