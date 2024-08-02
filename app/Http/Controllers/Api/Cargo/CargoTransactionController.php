<?php

namespace App\Http\Controllers\Api\Cargo;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Cargo\CargoTracker;
use App\Models\Cargo\CargoTransaction;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\checkAuthPermsissionTrait;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CargoTransactionController extends Controller
{
    use ApiResponse, checkAuthPermsissionTrait, AuditTrail;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->checkPermissionFn($request, VIEW);

        $criteria = $request->input('criteria');
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);

        try {
            $query = DB::table('cargo_transactions as ct')->select(
                'ct.id',
                    'ct.token',
                    'ct.item_id',
                    'ct.item_name',
                    'ct.total_amount',
                    'ct.total_kg',
                    'ct.quantity',
                    'ct.receipt_number',
                    'ct.station_from',
                    'ct.station_to',
                    'ct.track_status',
                    'ct.receiver_name',
                    'ct.receiver_phone',
                    'ct.sender_name',
                    'ct.sender_phone',
                    'ct.operator_id',
                    'ct.train_id',
                    'ct.device_number',
                    'ct.reference_number',
                    'ct.on_off',
                    'ct.paid_status',
                    'ct.extended_trnx_type',
                    'ct.collection_batch_number_id',
                    'ct.is_collected',
                    'ct.trnx_date',
                    'ct.trnx_time',
                    'ct.created_at',
                    //other
                    'tr.train_name',
                    'op.full_name',
                    //Stations
                    'from_station.station_name as from_station_name',
                    'to_station.station_name as to_station_name',
                    //Extended Transaction type
                    'ct.extended_trnx_type',
                    'ett.name as extended_transaction_type',
                    //Transaction Status
                    'ct.trnx_status',
                    'ts.name as transaction_status'
                )
                ->join('trains as tr', 'tr.id', 'ct.train_id')
                ->join('train_stations as from_station', 'from_station.id', 'ct.station_from')
                ->join('train_stations as to_station', 'to_station.id', 'ct.station_to')
                ->join('extended_transaction_types as ett', 'ett.code', 'ct.extended_trnx_type')
                ->join('transaction_statuses as ts', 'ts.code', 'ct.trnx_status')
                ->join('operators as op', 'op.id', 'ct.operator_id');

            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('tr.full_name', 'like', "%$searchQuery%")
                        ->orWhere('tr.train_name', 'like', "%$searchQuery%")
                        ->orWhere('ct.receipt_number', 'like', "%$searchQuery%")
                        ->orWhere('ct.item_name', 'like', "%$searchQuery%")
                        ->orWhere('cc.address', 'like', "%$searchQuery%")
                        ->orWhere('ct.receiver_name', 'like', "%$searchQuery%")
                        ->orWhere('ct.receiver_phone', 'like', "%$searchQuery%")
                        ->orWhere('ct.sender_name', 'like', "%$searchQuery%")
                        ->orWhere('ct.sender_phone', 'like', "%$searchQuery%")
                        ->orWhere('ct.device_number', 'like', "%$searchQuery%");
                });
            }

            $cargoCustomers = $query->orderByDesc('ct.updated_at')->paginate($itemPerPage);

            if (!$cargoCustomers) {
                throw new RestApiException(404, 'No cargo customer found!');
            }
            $this->auditLog("View All Transactions", PORTAL, null, null);
            return $this->success($cargoCustomers, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
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
    public function show(string $token)
    {
        try {
            $cargoTransaction = CargoTransaction::where('token', $token)->select(
                'cargo_transactions.id',
                'cargo_transactions.item_id',
                'cargo_transactions.item_name',
                'cargo_transactions.total_amount',
                'cargo_transactions.total_kg',
                'cargo_transactions.quantity',
                'cargo_transactions.receipt_number',
                'cargo_transactions.station_from',
                'cargo_transactions.station_to',
                'cargo_transactions.track_status',
                'cargo_transactions.receiver_name',
                'cargo_transactions.receiver_phone',
                'cargo_transactions.sender_name',
                'cargo_transactions.sender_phone',
                'cargo_transactions.operator_id',
                'cargo_transactions.train_id',
                'cargo_transactions.device_number',
                'cargo_transactions.reference_number',
                'cargo_transactions.on_off',
                'cargo_transactions.paid_status',
                'cargo_transactions.extended_trnx_type',
                'cargo_transactions.collection_batch_number_id',
                'cargo_transactions.is_collected',
                'cargo_transactions.trnx_date',
                'cargo_transactions.trnx_status',
                'ts.name as trnx_status_name',
                'cargo_transactions.trnx_time',
                'cargo_transactions.created_at',
            )->join('transaction_statuses as ts', 'ts.code', 'cargo_transactions.trnx_status')->with('transactionItemDetails')->firstOrFail();

            $trackingInfo = CargoTracker::where('cargo_transaction_id', $cargoTransaction->id)
                ->leftJoin('users', function ($join) {
                    $join->on('cargo_trackers.actor_id', '=', 'users.id')
                        ->where('cargo_trackers.actor_type', '=', PORTAL_ACTOR);
                })
                ->leftJoin('operators', function ($join) {
                    $join->on('cargo_trackers.actor_id', '=', 'operators.id')
                        ->where('cargo_trackers.actor_type', '=', POS_ACTOR);
                })
                ->leftJoin('cargo_item_trackers', 'cargo_trackers.id', '=', 'cargo_item_trackers.cargo_tracker_id')
                ->selectRaw('
                    cargo_trackers.id,
                    cargo_trackers.tracker_status_id,
                    cargo_trackers.tracker_status,
                    cargo_trackers.actor_type,
                    cargo_trackers.created_at,
                    IF(cargo_trackers.actor_type = 0, "Portal", "POS") as actor_type_name,
                    CONCAT(users.first_name, " ", users.last_name) as user_name,
                    operators.full_name as operator_name,
                    COUNT(DISTINCT cargo_item_trackers.id) as total_item_trackers
                ')
                ->whereNull('cargo_trackers.deleted_at')
                ->groupBy('cargo_trackers.id')
                ->get();

            $data = [
                'transaction' => $cargoTransaction,
                'tracking_info' => $trackingInfo
            ];

            if (!$data) {
                throw new RestApiException(404, 'No cargo transaction detail found!');
            }
            $this->auditLog("View Transaction Details", PORTAL, null, null);
            return $this->success($data, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {

            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
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

    /**
     * Show the form for creating a new resource.
     */
    public function cancelTransaction(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required',
        ]);
        //
        DB::beginTransaction();
        try {

            $transaction = CargoTransaction::findOrFail($validatedData['id']);

            if (!$transaction){
                throw new RestApiException(404, 'invalid cargo transaction found!');
            }

            $transaction->update(['trnx_status' => CANCELLED]);

            DB::commit();
            return $this->success(null, DATA_SAVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(500);
        }
    }

    public function testReceipt(Request $request)
    {
        // Logic to fetch transaction details (replace this with your own logic)
        $transaction = [
            'id' => 123,
            'date' => '2024-03-15',
            'amount' => 100.00,
            // Add more transaction details as needed
        ];

        return view('pdf.cargo-receipt', compact('transaction'));

        // Generate PDF using the template and transaction data
        $pdf = PDF::loadView('pdf.cargo-receipt', compact('transaction'));

        // Save the PDF to a temporary file
        $tempFilePath = tempnam(sys_get_temp_dir(), 'receipt');
        $pdf->save($tempFilePath);

        // Return the URL to access the PDF stream
        $url = route('receipt.stream', ['filename' => basename($tempFilePath)]);
        return response()->json(['url' => $url]);
    }
    public function transactionReceipt(Request $request)
    {
        // Logic to fetch transaction details (replace this with your own logic)
        $transaction = [
            'id' => 123,
            'date' => '2024-03-15',
            'amount' => 100.00,
            // Add more transaction details as needed
        ];

        // Generate PDF using the template and transaction data
        $pdf = PDF::loadView('pdf.cargo-receipt', compact('transaction'));

        // Save the PDF to a temporary file
        $tempFilePath = tempnam(sys_get_temp_dir(), 'receipt');
        $pdf->save($tempFilePath);

        // Return the URL to access the PDF stream
        $url = route('receipt.stream', ['filename' => basename($tempFilePath)]);
        return response()->json(['url' => $url]);
    }

    public function streamReceipt($filename)
    {
        // Get the full path to the temporary file
        $filePath = sys_get_temp_dir() . '/' . $filename;

        // Check if the file exists
        if (!file_exists($filePath)) {
            abort(404);
        }

        // Return the PDF file as a stream response
        return response()->file($filePath, ['Content-Type' => 'application/pdf']);
    }



}
