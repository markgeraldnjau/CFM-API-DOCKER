<?php

namespace App\Http\Controllers\Api;


use App\Models\OnOffTicket;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Exceptions\RestApiException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;



class OnOffTicketController extends Controller
{
    use ApiResponse, CommonTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $onOffTickets = OnOffTicket::select('id', 'name')->latest('id')->get();

            if (!$onOffTickets) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No train line found!');
            }
            return response()->json($onOffTickets);

        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

}
