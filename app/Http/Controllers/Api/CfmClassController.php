<?php

namespace App\Http\Controllers\Api;


use App\Models\CfmClass;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Exceptions\RestApiException;
use Log;
use DB;

class CfmClassController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            // $cfmClasses = CfmClass::paginate($request->items_per_page);
            // if (isset ($request->is_fetch_all)) {
                $cfmClasses = CfmClass::select('id', 'class_type')

                    ->get();

            $this->auditLog("View train class", PORTAL, null, null);
            return response()->json($cfmClasses);
            // return $this->success($cfmClasses, DATA_RETRIEVED);
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

        $validatedData = $request->validate([
            'class_type' => 'required',
            // 'excess_luggage' => 'required',
            // 'status' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $cfmClass = new CfmClass;
            $cfmClass->class_type = $request['class_type'];
            // $cfmClass->code = strtoupper($validatedData['class_type']);
            $cfmClass->excess_luggage = "100";
            $cfmClass->status = "1";
            $cfmClass->save();
            $this->auditLog("Create train class ", PORTAL, $request, $request);
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully created new Train Class', 'code' => 201]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['status' => 'fail', 'message' => 'Failed to create train cfm class detail..', 'code'=>500]);
        }


    }

    /**
     * Display the specified resource.
     */
    public function show(CfmClass $cfmClass)
    {
        //
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
        $cfmClass = CfmClass::find($id);
        $oldData = clone $cfmClass;
        if ($cfmClass) {
            $cfmClass->code = strtoupper($request->code);
            $cfmClass->class_type = $request->class_type;
            $cfmClass->update();
            $this->auditLog("Update train class", PORTAL, $oldData, $cfmClass);
            return response()->json(['status' => 'success', 'message' => 'Successfully update Train Class'], 200);
        } else {
            return response()->json(['message' => 'Train Class not found'], 200);
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
