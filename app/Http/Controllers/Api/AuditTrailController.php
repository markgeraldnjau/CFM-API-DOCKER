<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Traits\ApiResponse;
use App\Traits\checkAuthPermsissionTrait;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuditTrailController extends Controller
{
    use ApiResponse, checkAuthPermsissionTrait, CommonTrait;

    /**
     * Display a listing of the resource.
     * @throws RestApiException
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search_query' => ['nullable', 'string', 'max:255'],
            'item_per_page' => ['nullable', 'numeric', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return $this->error(null, $errors, HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->checkPermissionFn($request, VIEW);

        $searchQuery = $request->input('search_query');
        $itemPerPage = $request->input('item_per_page', 10);
        try {
            $query = DB::table('audit_trails as at');

            if ($searchQuery !== null) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('at.browser', 'like', "%$searchQuery%")
                        ->orWhere('at.user_name', 'like', "%$searchQuery%")
                        ->orWhere('at.ip_address', 'like', "%$searchQuery%")
                        ->orWhere('at.endpoint', 'like', "%$searchQuery%")
                        ->orWhere('at.method', 'like', "%$searchQuery%")
                        ->orWhere('at.action', 'like', "%$searchQuery%");
                });
            }

            $auditTrails = $query->orderByDesc('at.updated_at')->paginate($itemPerPage);

            if (!$auditTrails) {
                throw new RestApiException(HTTP_NOT_FOUND, 'No audit trail found!');
            }

            return $this->success($auditTrails, DATA_RETRIEVED);
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $token)
    {
        //
        try {
            $auditTrail = AuditTrail::where('token', $token)->firstOrFail();
            return $this->success($auditTrail, DATA_RETRIEVED);
        } catch (RestApiException $e) {
            throw new RestApiException($e->getStatusCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error(json_encode($this->errorPayload($e)));
            $statusCode = $e->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $e->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
    }
}
