<?php
// CartTrait.php

namespace App\Traits;

trait ApiResponse
{
    public function success($data = null, $message = null, $code = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => $code,
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public function warn($data = null, $message = null, $code = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => $code,
            'status' => 'warn',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public function error($data = null, $message = null, $code = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => $code,
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ], $code);
    }
}
