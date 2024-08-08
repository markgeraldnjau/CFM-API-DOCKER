<?php

namespace App\Http\Controllers\Api\Report;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportModule;
use App\Models\ReportParameter;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\CommonTrait;
use App\Traits\checkAuthPermsissionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportModuleController extends Controller
{
    use ApiResponse, AuditTrail, CommonTrait, checkAuthPermsissionTrait;
    //

    public function index()
    {
        try {
            $reportModules = ReportModule::all();

            if (!$reportModules) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No report modules found!');
            }
            $this->auditLog("View Report Modules", PORTAL, null, null);
            return $this->success($reportModules, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getReports(Request $request)
    {
        $validator = validator($request->all(), [
            'module_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $moduleCode = $request->input('module_code');
        try {
            $reportModules = Report::where('report_module_code', $moduleCode)->select('id', 'token', 'name', 'code', 'has_parameters')->get();

            if (!$reportModules) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No reports found!');
            }
            return $this->success($reportModules, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    public function getReportParameter(Request $request)
    {
        $validator = validator($request->all(), [
            'report_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => $validator->errors()
            ], HTTP_UNPROCESSABLE_ENTITY);
        }
        $reportCode = $request->input('report_code');
        //
        try {
            $reportModules = ReportParameter::where('report_code', $reportCode)->select('id', 'token', 'name', 'input_type', 'input_label', 'api_for_data', 'param')->get();

            if (!$reportModules) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No reports found!');
            }
            return $this->success($reportModules, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));

            $statusCode = $e->getCode() ?? HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
