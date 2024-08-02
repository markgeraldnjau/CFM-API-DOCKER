<?php

namespace App\Http\Controllers\Api\User;

use App\Events\SendMail;
use App\Events\SendSms;
use App\Exceptions\RestApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\OperatorRequest;
use App\Http\Requests\User\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Traits\AuditTrail;
use App\Traits\AuthTrait;
use App\Traits\OperatorTrait;
use http\Env\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use mysql_xdevapi\Exception;

class UserController extends Controller
{
    use ApiResponse, OperatorTrait, AuditTrail, AuthTrait;

    public function index(Request $request)
    {
        try {

            $searchQuery = $request->search_query;
            $query = DB::table('users as u')->join('roles as r', 'r.id', 'u.role_id')
                ->select(
            "u.id",
                    "u.token",
                    "u.first_name",
                    "u.last_name",
                    "u.username",
                    "u.role_id",
                    'r.name as role_name',
                    "u.email",
                    "u.account_status",
                    "u.phone_number",
                    "u.agent_number",
                    "u.operator_id",
                    "u.gender",
                    'u.updated_at',
                )->whereNull('u.deleted_at');
//            if (isset($request->type)) {
//                if ($request->input('type') == 'A') {
//                    Log::info('fetch active users');
//                    $query->where('account_status', $request->input('type'));
//                } else {
//                    Log::info('fetch inactive users');
//                    $query->where('account_status', $request->input('type'));
//
//                }
//            }

            if (!empty($searchQuery)) {
                $query->where(function ($query) use ($searchQuery) {
                    $query->where('u.first_name', 'like', "%$searchQuery%")
                        ->orWhere('u.email', 'like', "%$searchQuery%")
                        ->orWhere('u.last_name', 'like', "%$searchQuery%")
                        ->orWhere('u.gender', 'like', "%$searchQuery%");
                });
            }

            $users = $query->orderByDesc('id')->paginate(10);

            return $this->success($users, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }
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


    public function store(UserRequest $request)
    {
        DB::beginTransaction();
        try {
            $password = $this->generateAlphanumericPassword();

            $role = Role::findOrFail($request['role_id']);

            // Check If Operator Role
            if (empty($role)) {
                return $this->error(null, 'Undefined Role', 404);
            }

            $user = User::create([
                'first_name' => $request['first_name'],
                'last_name' => $request['last_name'],
                'username' => $request['username'],
                'email' => $request['email'],
                'phone_number' => $request['phone_number'],
                'agent_number' => $request['agent_number'],
                'id_number' => $request['id_number'],
                'account_type' => 1,
                'account_status' => "A",
                'role_id' => $request['role_id'],
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ]);

            if (empty($user)){
                Log::error("Error on register user: ". json_encode($request));
                DB::rollBack();
                return $this->error(null, SOMETHING_WENT_WRONG);
            }

            // Check if role code is 'OPERADOR' and if it is the default role
            if ($role->code == OPERADOR && $role->is_default) {
                $data = [
                    'full_name' => $request->first_name . $request->last_name,
                    'email' => $request->email,
                    'operator_id' => 0,
                    'username' => $request->username,
                    'phone' => $request->phone_number,
                    'role_id' => $role->id,
                    'train_line_id' => $request->train_line_id,
                    'operator_type_code' => $request->operator_type_code,
                    'operator_category_id' => $request->operator_category_id,
                    'station_id' => $request->station_id,
                    'password' => $password
                ];
                $response = $this->createOperator((object)$data);

                if (!$response){
                    Log::error("Error on register user operator: ". json_encode($request));
                    DB::rollBack();
                    return $this->error(null, SOMETHING_WENT_WRONG);
                }
            }

            //Send Sms
            $message = "Ola $user->first_name, Seu PIN padrao para acesso a web do XITIMELA e: $password. Use este PIN para acessar o portal.";
            $payload = [
                'phone' => $user->phone_number,
                'message' => $message,
            ];

            Log::info(json_encode($payload));
            event(new SendSms(SEND_USER_CREDENTIALS, $payload));

            $this->auditLog("Create User: ". $request->first_name . $request->last_name, PORTAL, $request, $request);
            DB::commit();
            return $this->success(null, DATA_SAVED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
        }

    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($token)
    {
        //
        try {

            $user = User::where('token', $token)->first();
            return $this->success($user, DATA_RETRIEVED);
        }catch(\Exception $e) {
            Log::error($e->getMessage());
            $statusCode = $e->getCode() ?? 500;
            $errorMessage = $e->getMessage() ?? SERVER_ERROR;
            throw new RestApiException($statusCode, $errorMessage);
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {

        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'agent_number' => 'nullable|numeric|max:999999999999',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($id),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($id),
            ],
            'phone_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($id),
            ],
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        DB::beginTransaction();
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $user->first_name = $validatedData['first_name'];
            $user->last_name = $validatedData['last_name'];
            $user->username = $validatedData['username'];
            $user->email = $validatedData['email'];
            $user->phone_number = $validatedData['phone_number'];
            $user->agent_number = $validatedData['agent_number'];
            $user->role_id = $validatedData['role_id'];
            $user->save();

            DB::commit();

//            return response()->json(['message' => 'User updated successfully', 'status' => SUCCESS_RESPONSE], 201);
            return $this->success(null, DATA_SAVED);
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
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'user not found'], 404);
        }
        $user->delete();
        return response()->json($user, 200);
    }

    public function activateUser(Request $request, $id)
    {
        $message = '';

        DB::beginTransaction();
        try {
            $user = User::find($id);
            if (!is_null($user)) {

                if ($user->account_status == ACTIVE_STATUS) {
                    $user->account_status = INACTIVE_STATUS;
                    $user->save();
                    $message = 'Successfully deactivated user';
                } else {
                    $user->account_status = ACTIVE_STATUS;
                    $user->save();
                    $message = 'Successfully activated user';
                }
                DB::commit();
                return response()->json(['message' => $message, 'data' => $user], 200);
            } else {
                return response()->json(['message' => "User not found"], 200);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json(["message" => $th->getMessage()]);
        }

    }

    public function resetPassword($id)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($id);
            $oldData = clone $user;

            $newPassword = Str::random(8);

            Log::channel("portal")->info("Reset User Password for: ". $user->first_name . " " .$user->last_name . "( " . $user->id ." ) to password: ". $newPassword);

            $user->password = Hash::make($newPassword);
            $user->save();

            $payload = [
                'old_password' => $oldData->password,
                'new_password' => $user->password
            ];

            $this->auditLog("Reset User's password: ". $user->first_name . " " .$user->last_name, PORTAL, $oldData, $payload);

            DB::commit();

            $this->sendPasswordResetNotification($user, $newPassword);

            return $this->success($user, 'Password has been reset successfully.');
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(404, 'User not found.');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw new RestApiException(500, 'Failed to reset password.');
        }
    }
    protected function sendPasswordResetNotification($user, $newPassword): void
    {
        $message = "Ola, sua senha para o portal XITIMELA foi redefinida, agora você pode usar: $newPassword. Para qualquer suporte, por favor, entre em contato com o suporte.";
        $smsPayload = [
            'phone' => $user->phone,
            'message' => $message,
        ];

        $emailPayload = [
            'username' => $user->first_name,
            'email' => $user->email,
            'message' => $message,
        ];

        event(new sendSms(RESET_PASSWORD, $smsPayload));
        event(new SendMail(RESET_PASSWORD, $emailPayload));
    }


}
