<?php

namespace App\Http\Controllers\Api\Train;

use App\Http\Controllers\Controller;
use App\Models\TrainCabinContain;
use App\Models\User;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Exceptions\RestApiException;


class TrainCarbinContainController extends Controller
{
    use CommonTrait;
    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        $carbinContain = TrainCabinContain::select('id','name')->orderBy('id','asc')->get();

        return response()->json(['data' => $carbinContain], HTTP_OK);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|max:255',
            'phone_number' => 'required|string|phone_number|max:255',
            'agent_number' => 'required|string|max:255',
            'operator_id' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {

            $password = Str::random(8);
            $user = new User;
            $user->first_name = $validatedData['first_name'];
            $user->last_name = $validatedData['last_name'];
            $user->username = $validatedData['first_name'] . '.' . $validatedData['last_name'];
            $user->email = $validatedData['email'];
            $user->phone_number = $validatedData['phone_number'];
            $user->agent_Number = $validatedData['agent_number'];
            $user->account_type = 1;
            $user->account_status = "A";
            $user->operator_id = $validatedData['operator_id'];
            $user->password = Hash::make($password);
            $user->save();

            DB::commit();

            return response()->json(['message' => 'User created successfully'], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to create user'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);
        try {
            if (!$user) {
                return response()->json(['message' => 'user not found'], HTTP_NOT_FOUND);
            }
            return response()->json($user, HTTP_OK);
        } catch (\Throwable $th) {
            Log::error(json_encode($this->errorPayload($th)));
            $statusCode = $th->getCode() ?: HTTP_INTERNAL_SERVER_ERROR;
            $errorMessage = $th->getMessage() ?: SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);

        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (is_null($id) || !is_numeric($id)) {
            return response()->json([
                'status' => VALIDATION_ERROR,
                'message' => VALIDATION_FAIL,
                'errors' => VALIDATION_ERROR_FOR_ID
            ], HTTP_BAD_REQUEST);
        }
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|max:255',
            'phone_number' => 'required|string|phone_number|max:255',
            'agent_number' => 'required|string|max:255',
            'operator_id' => 'required|integer',
        ]);
        $user = User::findOrfail($id);
        DB::beginTransaction();
        try {

            if (!$user) {
                return response()->json(['message' => 'User not found'], HTTP_NOT_FOUND);
            }

            $user->first_name = $validatedData['first_name'];
            $user->last_name = $validatedData['last_name'];
            $user->username = $validatedData['first_name'] . '.' . $validatedData['last_name'];
            $user->email = $validatedData['email'];
            $user->phone_number = $validatedData['phone_number'];
            $user->agent_Number = $validatedData['agent_number'];
            $user->account_type = 1;
            $user->operator_id = $validatedData['operator_id'];
            $user->save();
            DB::commit();
            return response()->json(['message' => 'User updated successfully'], HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(json_encode($this->errorPayload($e)));
            return response()->json(['message' => 'Failed to update user'], HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}
