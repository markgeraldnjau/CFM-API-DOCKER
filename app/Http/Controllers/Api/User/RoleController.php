<?php

namespace App\Http\Controllers\Api\User;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Wagon;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    use ApiResponse, CommonTrait, AuditTrail;
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index()
    {
        try {

            $roles = Role::select('id', 'name', 'code', 'token', 'is_default')
                ->orderBy('id', 'desc')
                ->get();

            return $this->success($roles, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    public function store(Request $request)
    {
        $validatedData = validator($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        DB::beginTransaction();
        try {

            $role = new Role();
            $role->name = strip_tags($validatedData['name']);
            $role->is_default = false;
            $role->save();
            DB::commit();
            return $this->success($role, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }

    }


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
            $role = Role::findOrFail($id);
            if (!$role) {
                return response()->json(['message' => 'role not found'], HTTP_NOT_FOUND);
            }
            return $this->success($role, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return RoleResource
     */
    public function show(Role $role)
    {
        return new RoleResource($role);
    }

    public function update($id, Request $request)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], 400);
        }
        $validatedData = validator($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validatedData->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $role = Role::find($id);
            if (empty($role)) {
                return $this->error(null, "invalid role!, contact admin for support");
            }
            $role->name = $validatedData['name'];
            $role->save();
            DB::commit();
            return $this->success($role, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function destroy($id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        DB::beginTransaction();
        try {
            $role = Role::findOrFail($id);

            if (empty($role)) {
                return $this->error(null, "invalid role!, contact admin for support", HTTP_NOT_FOUND);
            }
            if ($role->is_default) {
                return $this->error(null, "Can not delete default role!", HTTP_UNPROCESSABLE_ENTITY);
            }
            if (count($role->users) > 0) {
                return $this->error(null, "Remove the role to existing users to proceed with this action!", HTTP_UNPROCESSABLE_ENTITY);
            }
            $role->permissions()->delete();
            $role->delete();
            DB::commit();
            return $this->success($role, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getRoleDetails($token)
    {
        if (is_null($token)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        try {
            $roles = Role::where('token', $token)->first();
            return $this->success($roles, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
