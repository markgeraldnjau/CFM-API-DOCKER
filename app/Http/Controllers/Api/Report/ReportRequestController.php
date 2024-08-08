<?php

namespace App\Http\Controllers\Api\Report;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ApprovalProcessRequest;
use App\Jobs\RunGeneralReportsJob;
use App\Models\Report;
use App\Models\Report\ReportRequest;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use App\Traits\checkAuthPermsissionTrait;
use App\Traits\ReportTrait;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class ReportRequestController extends Controller
{
    use ApiResponse, AuditTrail, CommonTrait, checkAuthPermsissionTrait, ReportTrait;

    public function index(Request $request)
    {
        $validator = validator($request->all(), [
            "search_query" => "nullable|string",
            "item_per_page" => "nullable|integer",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        //
        try {
            $query = DB::table('report_requests as r')->select(
                'r.id',
                'r.token',
                'r.report_code',
                'rm.name as module_name',
                'r.report_name',
                'r.from_date',
                'r.to_date',
                'r.file_type',
                'r.download_link',
                'r.status',
                'r.created_by',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS created_by_name"),
                'r.created_at',
            )->join('reports as rp', 'rp.code', 'r.report_code')
                ->join('report_modules as rm', 'rm.code', 'rp.report_module_code')
                ->leftJoin('users as u', 'u.id', 'r.created_by');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('r.report_name', 'like', "%$searchQuery%")
                        ->where('rm.name', 'like', "%$searchQuery%")
                        ->where('u.first_name', 'like', "%$searchQuery%")
                        ->where('u.last_name', 'like', "%$searchQuery%");
                });
            }
            $reportRequest = $query->orderByDesc('r.updated_at')->paginate($itemPerPage);

            if (!$reportRequest) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No report requests found!');
            }
            return $this->success($reportRequest, DATA_RETRIEVED);
        } catch (\Exception $e) {

            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function lastFirstReportRequest(Request $request)
    {

        $validator = validator($request->all(), [
            "search_query" => "nullable|string",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $searchQuery = $request->input('search_query');
        try {
            $query = DB::table('report_requests as r')->select(
                'r.id',
                'r.token',
                'r.report_code',
                'rm.name as module_name',
                'r.report_name',
                'r.from_date',
                'r.to_date',
                'r.file_type',
                'r.download_link',
                'r.status',
                'r.created_by',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS created_by_name"),
                'r.created_at',
            )->join('reports as rp', 'rp.code', 'r.report_code')
                ->join('report_modules as rm', 'rm.code', 'rp.report_module_code')
                ->leftJoin('users as u', 'u.id', 'r.created_by');
            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('r.report_name', 'like', "%$searchQuery%")
                        ->where('rm.name', 'like', "%$searchQuery%")
                        ->where('u.first_name', 'like', "%$searchQuery%")
                        ->where('u.last_name', 'like', "%$searchQuery%");
                });
            }
            $reportRequest = $query->latest()->first();

            if (!$reportRequest) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No report request found!');
            }
            return $this->success($reportRequest, DATA_RETRIEVED);
        } catch (\Exception $e) {

            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(\App\Http\Requests\Report\ReportRequest $request)
    {
        $validator = validator($request->all(), [
            'report_code' => 'required|string',
            'report_name' => 'required|string',
            'file_type' => 'required|in:EXCEL,PDF,CSV,TXT',
            'parameters' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        DB::beginTransaction();
        try {
            //Check and decide which report needed
            $report = Report::where('code', $request->report_code)->select('name', 'has_parameters', 'code')->firstOrFail();
            $parameters = $request->parameters;
            $startDate = $parameters['start_date'];
            $endDate = $parameters['end_date'];
            $createdBy = Auth::user()->id;

            $payload = [
                'report_code' => $request->report_code,
                'report_name' => $report->name,
                'from_date' => $startDate,
                'to_date' => $endDate,
                'file_type' => $request->file_type,
                'created_by' => $createdBy,
                'parameters' => json_encode($parameters),
            ];
            $reportRequest = ReportRequest::create($payload);

            $delayInSeconds = 0;
            $job = new RunGeneralReportsJob($request->report_code, $startDate, $endDate, $request->file_type, $createdBy, $reportRequest->id);
            Queue::later(now()->addSeconds($delayInSeconds), $job);
            $this->auditLog("Create Report request: " . $request->report_name . ' from ' . $startDate . ' to ' . $endDate, PORTAL, $payload, $payload);
            DB::commit();
            return $this->success($reportRequest, DATA_SAVED);
        } catch (ModelNotFoundException $e) {
            Log::error(json_encode($this->errorPayload($e)));

            DB::rollBack();
            throw new RestApiException(HTTP_NOT_FOUND, DATA_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            DB::rollBack();
            throw new RestApiException(HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getReportFile(ApprovalProcessRequest $request)
    {
        try {
            $response = $this->openReportFile($request->file_type, $request->download_url);
            $this->auditLog("Get Report with Type: " . $request['file_type'] . ' with url ' . $response['url'], PORTAL, null, null);
            return $this->success($response, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function testPdf()
    {
        $data = [
            'receipt' => [
                'number' => '0000057144',
                'date_time' => '2024-05-27 16:07:20',
                'reviewer_name' => 'Nelson Chelengo',
                'reviewer_id' => '123'
            ],
            'summary' => [
                'passengers' => 98,
                'difference' => 0,
                'revenue' => '7192,00',
                'revenue_diff' => '0,00'
            ],
            'report' => [ // Relatório do Portal
                'passengers' => 98,
                'difference' => 0,
                'revenue' => '7192,00',
                'revenue_diff' => '0,00'
            ],
            'manual' => [ // Vendas Manuais
                'passengers' => 0,
                'difference' => 0,
                'revenue' => '0,00',
                'revenue_diff' => '0,00'
            ],
            'cashier' => [ // Receita a Recebedoria
                'revenue' => '7192,00',
                'difference' => '0,00'
            ],
            'total_in_words' => 'Sete mil cento e noventa dois meticais e zero cêntavos'
        ];

        $pdf = PDF::loadView('pdf.test-pdf', $data);
        return $pdf->download('test.pdf');
    }

}
