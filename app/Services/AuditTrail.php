<?php

namespace App\Services;

use App\Models\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditTrailService
{
    public static function log($action, $originalData = null, $newData = null, $isApiRequest = false): void
    {
        $userId = auth()->id()  ?? 'N/A';
        $userName = Auth::user()->username ?? 'N/A';
        $userAgent = Request::header() ?? 'N/A';
        $ipAddress = Request::ip();
        $endpoint = Request::url();
        $method = Request::method();

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
            $isApiRequest,
        ]);
    }
}
