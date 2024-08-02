<?php

namespace App\Http\Controllers\Api;


use App\Models\OnOffTicket;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Exceptions\RestApiException;
use Log;
use DB;



class OnOffTicketController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        try {

            $onOffTickets = OnOffTicket::select('id', 'name')->latest('id')->get();

            if (!$onOffTickets) {
                throw new RestApiException(404, 'No train line found!');
            }
            // return $this->success($trainLines, DATA_RETRIEVED);
            return response()->json($onOffTickets);

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
    public function show(OnOffTicket $onOffTicket)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OnOffTicket $onOffTicket)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OnOffTicket $onOffTicket)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OnOffTicket $onOffTicket)
    {
        //
    }
}
