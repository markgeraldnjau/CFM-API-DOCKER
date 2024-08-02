<?php

namespace App\Http\Controllers\Api\User;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Wagon;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
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
    use ApiResponse, AuditTrail;
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
        }catch(\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required',
        ]);

        DB::beginTransaction();
        try {

            $role = new Role();
            $role->name = $validatedData['name'];
            $role->is_default = false;
            $role->save();
            DB::commit();
            return $this->success($role,DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }

    }


    public function edit($id)
    {
        try {
            $role = Role::find($id);
            if (!$role) {
                return response()->json(['message' => 'role not found'], 404);
            }
            return $this->success($role, DATA_RETRIEVED);
        }catch(\Exception $e) {
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
     * @return RoleResource
     */
    public function show(Role $role)
    {
        return new RoleResource($role);
    }

    public function update($id, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::find($id);
            if (empty($role)){
                return $this->error(null, "invalid role!, contact admin for support");
            }
            $role->name = $validatedData['name'];
            $role->save();
            DB::commit();
            return $this->success($role,DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function destroy($id) {
        DB::beginTransaction();
        try {
            $role = Role::find($id);

            if (empty($role)){
                return $this->error(null, "invalid role!, contact admin for support", 404);
            }
            if ($role->is_default){
                return $this->error(null, "Can not delete default role!", 422);
            }
            if (count($role->users) > 0){
                return $this->error(null, "Remove the role to existing users to proceed with this action!", 422);
            }
            $role->permissions()->delete();
            $role->delete();
            DB::commit();
            return $this->success($role,DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getRoleDetails($token)
    {
        try {
            $roles = Role::where('token', $token)->first();
            return $this->success($roles, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
