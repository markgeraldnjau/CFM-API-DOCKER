<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ExtendedTransactionType;
use App\Models\TransactionType;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionTypeController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $transactionTypes = ExtendedTransactionType::select('id', 'code', 'name')->paginate(100);

            // Check if any branches were found
            if ($transactionTypes->isEmpty()) {
                throw new RestApiException(404, 'No transaction types found!');
            }

            return $this->success($transactionTypes, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?: 500;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
