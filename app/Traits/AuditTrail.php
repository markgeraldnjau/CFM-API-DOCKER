<?php
// CartTrait.php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

trait AuditTrail
{
    public static function auditLog($action, $requestFrom, $originalData = null, $newData = null): void
    {
        $userId = auth()->id() ?? 0;
        $userName = Auth::user()->username ?? 'N/A';
        $userAgent = Request::header('User-Agent') ?? 'N/A'; // Fix here to get the User-Agent header
        $ipAddress = Request::ip();
        $endpoint = Request::url();
        $method = Request::method();

        // Serialize arrays to strings
        $originalData = $originalData ? json_encode($originalData) : null;
        $newData = $newData ? json_encode($newData) : null;

        DB::statement('CALL LogAuditTrail(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $userId,
            $userName,
            $userAgent,
            $ipAddress,
            $action,
            $endpoint,
            $method,
            $originalData,
            $newData,
            $requestFrom,
        ]);
    }

}
