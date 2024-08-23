<?php

namespace App\Traits\Phc;

use Illuminate\Http\JsonResponse;

trait PhcApiResponse
{
    public function success($requestID, $message, $code = 200, $data = null): JsonResponse
    {
        return response()->json([
            'ID' => $requestID,
            'status' => 'SUCCESS', // Adjust this to a string that represents success
            'message' => $message,
            'Cod' => $code,
            'CodDesc' => $data
        ], $code);
    }

    public function warn($requestID, $message, $data = null, $code = 400)
    {
        return response()->json([
            'ID' => $requestID,
            'status' => 'WARNING', // Adjust this to a string that represents warning
            'message' => $message,
            'Cod' => $code,
            'CodDesc' => $data
        ], $code);
    }

    public function error($requestID, $message, $code = 500, $data = null): JsonResponse
    {
        return response()->json([
            'ID' => $requestID,
            'status' => 'ERROR', // Adjust this to a string that represents success
            'message' => $message,
            'Cod' => $code,
            'CodDesc' => $data
        ], $code);
    }
}
