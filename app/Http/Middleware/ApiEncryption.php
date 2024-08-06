<?php

namespace App\Http\Middleware;

use App\Encryption\EncryptionHelper;
use Closure;
use GPBMetadata\Google\Api\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApiEncryption
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId  =  \auth()->device_id;
        $keys  =  DB::table('keys')->where(['android_id'=>$deviceId])->first();
        $pwKey = $keys->key;
        $decodedJson = EncryptionHelper::decrypt($request->data,$pwKey);
        $outerArray = json_decode($decodedJson);
        $request['data'] = $outerArray->data;
        $request['key'] = $pwKey;
        return $next($request);
    }
}
