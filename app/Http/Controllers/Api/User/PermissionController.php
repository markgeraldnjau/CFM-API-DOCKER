<?php

namespace App\Http\Controllers\Api\User;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\RoleHasPermission;
use App\Models\RolePermission;
use App\Models\SysModule;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use App\Traits\AuthTrait;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    use ApiResponse,CommonTrait, AuthTrait, AuditTrail;
    public function getSysModules()
    {
        try {

            $moduleActions = DB::table('sys_module_actions as sma')
                ->join('sys_modules as sm', 'sm.id', 'sma.sys_module_id')
                ->select(
                    'sm.id',
                    'sm.code',
                    'sm.name',
                    'sm.base_route',
                    'sma.actions',
                )->whereNull('sma.deleted_at')
                ->orderBy('sm.id')
                ->get();

            $permissionActions = DB::table('permission_actions')
                ->select(
                    'id',
                    'code',
                    'name',
                )
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('code');

            $modules = [];

            foreach ($moduleActions as $moduleAction) {
                $actions = [];
                foreach (json_decode($moduleAction->actions) as $actionCode) {
                    if (isset($permissionActions[$actionCode])) {
                        $action = [
                            'id' => $permissionActions[$actionCode]->id,
                            'code' => $actionCode,
                            'name' => $permissionActions[$actionCode]->name
                        ];
                        $actions[] = $action;
                    }
                }
                $data = [
                    'id' => $moduleAction->id,
                    'code' => $moduleAction->code,
                    'name' => $moduleAction->name,
                    'base_route' => $moduleAction->base_route,
                    'actions' => $actions
                ];
                $modules[] = $data;
            }

            return $this->success($modules, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    private function getActions($token)
    {
        $roles = DB::table('roles as r')->where('r.token', $token)
            ->leftJoin('role_has_permissions as rhp', 'rhp.role_id', 'r.id')
            ->leftJoin('sys_modules as sm', 'sm.id', 'rhp.sys_module_id')
            ->select(
                'r.id',
                'r.token',
                'r.name as role_name',
                'sm.code as sys_module_code',
                'sm.name as sys_module_name',
                'sm.base_route',
                'rhp.actions',
            )->orderBy('sm.id')
            ->get();

        $permissionActions = DB::table('permission_actions')
            ->select(
                'id',
                'code',
                'name',
            )
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('code');

        $modules = [];

        foreach ($roles as $role) {
            $actions = [];
            $jsonDecodeRoles = json_decode($role->actions);
            $data = [
                'id' => null,
                'code' => null,
                'name' => null,
                'base_route' => null,
                'actions' => []
            ];
            if ($jsonDecodeRoles){
                foreach ($jsonDecodeRoles as $actionCode) {
                    if (isset($permissionActions[$actionCode])) {
                        $action = [
                            'id' => $permissionActions[$actionCode]->id,
                            'code' => $actionCode,
                            'name' => $permissionActions[$actionCode]->name
                        ];
                        $actions[] = $action;
                    }
                }
                $data = [
                    'id' => $role->id,
                    'code' => $role->sys_module_code,
                    'name' => $role->sys_module_name,
                    'base_route' => $role->base_route,
                    'actions' => $actions
                ];
            }
            $modules[] = $data;
        }

        return $modules;
    }

    public function attachPermission($id, Request $request){
        // Validate the request data
        $validatedData = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        try {
            // Get permissions
            $permissions = $validatedData['permissions'];


            RolePermission::where('role_id', $id)->delete();


            DB::beginTransaction();
            if(is_array($permissions)){
                foreach ($permissions as $permission) {
                    RolePermission::create([
                        'role_id' => $id,
                        'permission_id' => $permission
                    ]);
                }
            }

            DB::commit();
            return $this->success(null,DATA_SAVED);
        } catch (\Exception $e){
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getPermissions()
    {
        try {
            $modules = Permission::select('id', 'name','sys_module_id')
                ->orderBy('id', 'asc')
                ->get();
            return $this->success($modules, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getRolePermissions($id)
    {
        try {
            $modules = RolePermission::select('permission_id')
                ->where('role_id', $id)
                ->pluck('permission_id');
            return $this->success($modules, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getModuleAndPermissions()
    {
        try {
            $moduleActions = DB::table('sys_module_actions as sma')
                ->join('sys_modules as sm', 'sm.id', 'sma.sys_module_id')
                ->select(
                    'sm.id',
                    'sm.code as sys_module_code',
                    'sm.name as sys_module_name',
                    'sm.base_route',
                    'sma.actions',
                )->whereNull('sma.deleted_at')
                ->get();

            $permissionActions = DB::table('permission_actions')
                ->select(
                    'id',
                    'code',
                    'name',
                )
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('code');

            $sysModuleData = [];

            foreach ($moduleActions as $moduleAction) {
                $actions = [];
                foreach (json_decode($moduleAction->actions) as $actionCode) {
                    if (isset($permissionActions[$actionCode])) {
                        $action = [
                            'id' => $permissionActions[$actionCode]->id,
                            'code' => $actionCode,
                            'name' => $permissionActions[$actionCode]->name
                        ];
                        $actions[] = $action;
                    }
                }
                $data = [
                    'id' => $moduleAction->id,
                    'sys_module_code' => $moduleAction->sys_module_code,
                    'sys_module_name' => $moduleAction->sys_module_name,
                    'base_route' => $moduleAction->base_route,
                    'actions' => $actions
                ];
                $sysModuleData[] = $data;
            }

            return $this->success($sysModuleData, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getRoleWithActions($token)
    {
        try {
            $roleWithActionsData = $this->getActions($token);
            $roleWithActionsData = collect($roleWithActionsData);

            $moduleActions = DB::table('sys_module_actions as sma')
                ->join('sys_modules as sm', 'sm.id', 'sma.sys_module_id')
                ->select(
                    'sm.id',
                    'sm.code',
                    'sm.name',
                    'sm.base_route',
                    'sma.actions',
                )->whereNull('sma.deleted_at')
                ->orderBy('sm.id')
                ->get();

            $permissionActions = DB::table('permission_actions')
                ->select(
                    'id',
                    'code',
                    'name',
                )
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('code');

            $modules = [];
            foreach ($moduleActions as $moduleAction) {
                $roleWithActionData = $roleWithActionsData->where('code', $moduleAction->code)->first();
                $roleActions = [];
                if (!empty($roleWithActionData)){
                    $roleActions = collect($roleWithActionData['actions']);
                }
                $actions = [];
                foreach (json_decode($moduleAction->actions) as $actionCode) {

                    $isChecked = false;
                    if (!empty($roleActions)){
                        $roleAction =  $roleActions->firstWhere('code', $actionCode);
                        if (!empty($roleAction)){
                            $isChecked = $roleAction['code'] ==  $actionCode;
                        }
                    }

                    if (isset($permissionActions[$actionCode])) {
                        $action = [
                            'id' => $permissionActions[$actionCode]->id,
                            'code' => $actionCode,
                            'name' => $permissionActions[$actionCode]->name,
                            'is_checked' => $isChecked
                        ];
                        $actions[] = $action;
                    }
                }
                $data = [
                    'id' => $moduleAction->id,
                    'code' => $moduleAction->code,
                    'name' => $moduleAction->name,
                    'base_route' => $moduleAction->base_route,
                    'actions' => $actions
                ];
                $modules[] = $data;
            }
            return $this->success($modules, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function updateRoleActions(Request $request)
    {
        DB::beginTransaction();
        try {

            $role = Role::where('token', $request->role_token)->select('id', 'name')->first();
            if (empty($role)){
                return $this->error(null, "invalid role, contact admin for support", HTTP_NOT_FOUND);
            }

            $structuredActions = $this->contructSelectedActionsForModule($request->actions);


            foreach ($structuredActions as $structuredAction) {

                $roleHasPermission = DB::table('role_has_permissions')
                    ->select('id')
                    ->where('sys_module_id', $structuredAction['id'])
                    ->where('role_id', $role->id)->first();
                if (empty($roleHasPermission)){
                    $sysModule = SysModule::find($structuredAction['id'], ['base_route']);
                    $newRolePermission = RoleHasPermission::updateOrCreate([
                           'role_id' => $role->id,
                           'sys_module_id' => $structuredAction['id'],
                           'actions' => json_encode($structuredAction['actions']),
                            'url' => $sysModule->base_route
                        ]);
                    if (!$newRolePermission){
                        return $this->error(null, "Error on updating permissions for a module, contact admin for support", 404);
                    }
                } else {
                    $update = RoleHasPermission::where('id', $roleHasPermission->id)->update([
                        'actions' => $structuredAction['actions']
                    ]);

                    if (!$update){
                        return $this->error(null, "Error on updating permissions for a module, contact admin for support", 404);
                    }
                }
            }
            DB::commit();
            return $this->success(null, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
