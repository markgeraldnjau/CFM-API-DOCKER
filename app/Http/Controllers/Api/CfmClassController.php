<?php

namespace App\Http\Controllers\Api;


use App\Models\CfmClass;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Exceptions\RestApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CfmClassController extends Controller
{
    use ApiResponse, AuditTrail, checkAuthPermsissionTrait, CommonTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $cfmClasses = CfmClass::select('id', 'class_type')
                    ->get();

            $this->auditLog("View train class", PORTAL, null, null);
            return response()->json($cfmClasses);
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

        $validatedData = $request->validate([
            'class_type' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $cfmClass = new CfmClass;
            $cfmClass->class_type = $validatedData['class_type'];
            $cfmClass->excess_luggage = "100";
            $cfmClass->status = "1";
            $cfmClass->save();
            $this->auditLog("Create train class ", PORTAL, $request, $request);
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully created new Train Class', 'code' => HTTP_CREATED]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['status' => 'fail', 'message' => 'Failed to create train cfm class detail..', 'code'=>HTTP_INTERNAL_SERVER_ERROR]);
        }


    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'class_type' => 'required|string|max:255',
        ]);

        try{
            $cfmClass = CfmClass::find($id);
            $oldData = clone $cfmClass;
            if ($cfmClass) {
                $cfmClass->code = strtoupper($request->code);
                $cfmClass->class_type = $validatedData['class_type'];
                $cfmClass->update();
                $this->auditLog("Update train class", PORTAL, $oldData, $cfmClass);
                return response()->json(['status' => 'success', 'message' => 'Successfully update Train Class'], HTTP_OK);
            } else {
                return response()->json(['message' => 'Train Class not found'], HTTP_OK);
            }
        }catch (\Exception $e){
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['status' => 'fail', 'message' => SOMETHING_WENT_WRONG, 'code'=>HTTP_INTERNAL_SERVER_ERROR]);
        }

    }

}
