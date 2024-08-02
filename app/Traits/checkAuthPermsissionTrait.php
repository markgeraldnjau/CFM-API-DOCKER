<?php
// CartTrait.php

namespace App\Traits;

use App\Exceptions\RestApiException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait checkAuthPermsissionTrait
{
    public function modulePermissionCheck($path, $action): bool
    {
        $roleId = Auth::user()->role_id ?? null;
        return DB::table('role_has_permissions as rp')->where('rp.role_id', $roleId)->where('rp.url', $path)->whereJsonContains('rp.actions', $action)->exists();
    }

    public function checkPermissionFn($request, $action): void
    {
        $path = Str::replaceFirst('api/', '', $request->path());
        if (!$path){
            throw new RestApiException(404, 'Unknown path, contact admin for support');
        }

        // checkPermissions
        if (!$this->modulePermissionCheck($path, $action)){
            throw new RestApiException(403, ACCESS_DENIED);
        }
    }

    public function getRolePermission($roleId)
    {
        return DB::table('role_has_permissions as rp')->where('rp.role_id', $roleId)
            ->leftJoin('roles as r', 'r.id', 'rp.role_id')
            ->select('rp.actions', 'rp.url')
            ->get();
    }
}
