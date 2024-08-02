<?php

namespace App\Http\Controllers\Api\Management;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\TrainLine;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainLineController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $trainLines = TrainLine::select('id', 'line_code', 'line_name')->latest('id')->get();

            // Check if any branches were found
            if (!$trainLines) {
                throw new RestApiException(404, 'No train line found!');
            }

            return $this->success($trainLines, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
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
        DB::beginTransaction();
        try {
            $trainLine = new TrainLine();
            $trainLine->line_code = strtoupper($request->line_code);
            $trainLine->line_name = $request->line_name;
            $trainLine->line_distance = $request->line_distance;
            $trainLine->region_id = $request->cfm_region;
            $trainLine->save();
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully Create New Train Line'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            //throw $th;
            return response()->json(['status' => 'fail', 'message' => $th->getMessage()], 200);

        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            $trainLine = TrainLine::findOrFail($id, ['id', 'line_code', 'line_name']);
            if (!$trainLine) {
                throw new RestApiException(404, 'No train line found!');
            }
            return $this->success($trainLine, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
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
        $trainLine = TrainLine::find($id);
        if ($trainLine) {
            // $trainLine->line_code = strtoupper($request->line_code);
            $trainLine->line_name = $request->line_name;
            $trainLine->line_distance = $request->line_distance;
            // $trainLine->region_id = $request->cfm_region;
            $trainLine->update();
            return response()->json(['status' => 'success', 'message' => 'Successfully update Train Line'], 200);
        } else {
            return response()->json(['status' => 'failed', 'message' => 'Train Line not found'], 200);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
