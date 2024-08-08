<?php

namespace App\Http\Controllers\Api;

use App\Models\ContractInstitution;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Validator;


class ContractInstitutionController extends Controller
{

    use ApiResponse, CommonTrait;
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_per_page' => ['nullable', 'numeric', 'max:255'],
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());
            return $this->error(null, $errors, HTTP_UNPROCESSABLE_ENTITY);
        }

        $contracts = ContractInstitution::paginate($validator['items_per_page']);
        return response()->json($contracts, 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'contractNumber' => 'required|string|max:255',
            'shortName' => 'required|string|max:255',
            'registeredName' => 'required|string|max:255',
            'registrationNumber' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'postalAdress' => 'required|string|max:255',
            'contactPerson' => 'required|string|max:255',
            'phoneNumber' => 'required|string|max:20',  // Adjust max length as per your schema
            'email' => 'nullable|email|max:255',
            'faxNumber' => 'nullable|string|max:20',  // Adjust max length as per your schema
            'contractStatus' => 'required|integer',
            'contractExpireDate' => 'required|date',
            'contractValidity' => 'required|integer',
            'specialDiscount' => 'required|numeric',
            'limitAmount' => 'required|numeric',
            'startDate' => 'required|date',
            'payType' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {
            $contract = new ContractInstitution;
            $contract->contract_number = $validatedData['contractNumber'];
            $contract->short_name = $validatedData['shortName'];
            $contract->registered_name = $validatedData['registeredName'];
            $contract->registration_number = $validatedData['registrationNumber'];
            $contract->address = $validatedData['address'];
            $contract->postal_adress = $validatedData['postalAdress'];
            $contract->contact_person = $validatedData['contactPerson'];
            $contract->phone_number = $validatedData['phoneNumber'];
            $contract->email = $request->input('email', null);
            $contract->fax_number = $request->input('faxNumber', null);
            $contract->contract_status = $validatedData['contractStatus'];
            $contract->contract_expire = $validatedData['contractExpireDate'];
            $contract->validity = $validatedData['contractValidity'];
            $contract->special_discount = $validatedData['specialDiscount'];
            $contract->limit_amount = $validatedData['limitAmount'];
            $contract->start_date = $validatedData['startDate'];
            $contract->type_contract_payment = $validatedData['payType'];
            $contract->save();

            DB::commit();
            return $this->success(null,"Contract institution created successfully");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to create contract institution'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function show($id)
    {
        try{
            $contract = ContractInstitution::find($id);
            if (!$contract) {
                return response()->json(['message' => 'Contract institution not found'], HTTP_NOT_FOUND);
            }
            return response()->json($contract, HTTP_OK);
        }catch(\Exception $e){
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to fetch contract institution'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'contractNumber' => 'required|string|max:255',
            'shortName' => 'required|string|max:255',
            'registeredName' => 'required|string|max:255',
            'registrationNumber' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'postalAdress' => 'required|string|max:255',
            'contactPerson' => 'required|string|max:255',
            'phoneNumber' => 'required|string|max:20',  // Adjust max length as per your schema
            'contractStatus' => 'required|integer',
            'contractExpireDate' => 'required|date',
            'contractValidity' => 'required|integer',
            'specialDiscount' => 'required|numeric',
            'limitAmount' => 'required|numeric',
            'startDate' => 'required|date',
            'payType' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {
            $contract = ContractInstitution::find($id);
            if (!$contract) {
                return response()->json(['message' => 'Contract institution not found'], HTTP_NOT_FOUND);
            }
            $contract->contractNumber = $validatedData['contractNumber'];
            $contract->shortName = $validatedData['shortName'];
            $contract->registeredName = $validatedData['registeredName'];
            $contract->registrationNumber = $validatedData['registrationNumber'];
            $contract->address = $validatedData['address'];
            $contract->postalAdress = $validatedData['postalAdress'];
            $contract->contactPerson = $validatedData['contactPerson'];
            $contract->phoneNumber = $validatedData['phoneNumber'];
            $contract->email = $request->input('email', null);
            $contract->faxNumber = $request->input('faxNumber', null);
            $contract->contractStatus = $validatedData['contractStatus'];
            $contract->contractExpireDate = $validatedData['contractExpireDate'];
            $contract->contractValidity = $validatedData['contractValidity'];
            $contract->specialDiscount = $validatedData['specialDiscount'];
            $contract->limitAmount = $validatedData['limitAmount'];
            $contract->startDate = $validatedData['startDate'];
            $contract->payType = $validatedData['payType'];
            $contract->save();

            DB::commit();
            return response()->json(['message' => 'Contract institution updated successfully'], HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to update contract institution'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $contract = ContractInstitution::find($id);
            if (!$contract) {
                return response()->json(['message' => 'Contract institution not found'], HTTP_NOT_FOUND);
            }
            $contract->delete();
            DB::commit();
            return response()->json(['message' => 'Contract institution deleted successfully'], HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to delete contract institution'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }



}
