<?php

namespace App\Http\Controllers\Api\Train;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CardCustomer;
use App\Models\TrainCabinType;
use App\Models\TrainWagonClass;
use App\Models\TrainWagonType;
use App\Models\User;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrainCarbinTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $carbinTypes = TrainCabinType::select('id','name')->orderBy('id','asc')->get();

        return response()->json(['data' => $carbinTypes], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'phone_number' => 'required',
            'agent_number' => 'required',
            'operator_id' => 'required',
        ]);

        DB::beginTransaction();
        try {

            $password = Str::random(8);
            Log::info($password);
//    TODO: Send username and password

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

            return response()->json(['message' => 'User created successfully'], 201);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
            \Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to create user'], 500);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'user not found'], 404);
        }
        return response()->json($user, 200);
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

        $validatedData = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'phone_number' => 'required',
            'agent_number' => 'required',
            'operator_id' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
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

            return response()->json(['message' => 'User updated successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to update user'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
