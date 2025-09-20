<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\AdminLogs;
use App\Models\History;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'password' => 'required|string',
            'email' => 'sometimes|string|email|unique:users',
            'phone_number' => 'sometimes|string',
            'wallet_balance' => 'sometimes|integer',
            'otp' => 'sometimes|string',
            'otp_expires_at' => 'sometimes|string',
            'email_verified_at' => 'sometimes|string',
            'should_change_password' => 'sometimes|boolean',
        ]);

        $user = User::where('name', $request->name)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'name' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Log
        AdminLogs::query()->create([
            "type" => "user_activity",
            "action" => "login",
            "data" => [
                "user_id" => $user->id,
                "user_name" => $user->name,
                "email" => $user->email,
                "phone" => $user->phone_number,
                "timestamp" => time(),
            ]
        ]);

        return response()->json([
            'token' => $user->createToken('user-token')->plainTextToken
        ]);
    }

    public function signup(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'password' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'phone_number' => 'required|string',
        ]);

        $otp = rand(100000, 999999);

        $user = User::create([
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'wallet_balance' => 0,
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(30),
            'email_verified_at' => Null,
            'should_change_password' => true,
        ]);

        // Log
        AdminLogs::query()->create([
            "type" => "user_activity",
            "action" => "signup",
            "data" => [
                "user_id" => $user->id,
                "user_name" => $user->name,
                "email" => $user->email,
                "phone" => $user->phone_number,
                "timestamp" => time(),
            ]
        ]);
        return response()->json(UserResource::make($user));
    }

    public function verifyEmail(Request $request)
    {
        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json(["error" => "Invalid or expired token"], 401);
        }

        if ($user->otp != $request->otp) {
            return response()->json(["error" => "Invalid or expired otp"], 401);
        }

        $user->email_verified_at = now();
        $user->save();

        // Log
        AdminLogs::query()->create([
            "type" => "user_activity",
            "action" => "verify_email",
            "data" => [
                "user_id" => $user->id,
                "user_name" => $user->name,
                "email" => $user->email,
                "phone" => $user->phone_number,
                "timestamp" => time(),
            ]
        ]);

        return response()->json(["message" => "Email verified successfully"]);
    }

    public function getByToken(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(["error" => "Token missing"], 401);
        }

        $user_id = DB::table('personal_access_tokens')->find(explode("|", $token)[0])->tokenable_id;
        $user = User::find($user_id);

        return response()->json(UserResource::make($user));
    }

    public function update_info(Request $request)
    {
        $user = $request->user('sanctum');
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|string|email|unique:users',
            'phone_number' => 'sometimes|string',
        ]);
        $old_name = $user->name;
        $old_email = $user->email;
        $old_phone_number = $user->phone_number;
        $user->update($validated);
        if (isset($validated['email'])) {
            $user->email_verified_at = Null;
            $user->save();
        }

        // Log
        AdminLogs::query()->create([
            "type" => "user_activity",
            "action" => "update_info",
            "data" => [
                "user_id" => $user->id,
                "user_name" => $user->name,
                "email" => $user->email,
                "phone" => $user->phone_number,
                "old_data" => [
                    "name" => $old_name,
                    "email" => $old_email,
                    "phone" => $old_phone_number,
                ],
                "timestamp" => time(),
            ]
        ]);

        return response()->json(UserResource::make($user));
    }

    public function update(string $id, Request $request)
    {
        $user = User::query()->find($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|string|email|unique:users',
            'phone_number' => 'sometimes|string',
            'wallet_balance' => 'sometimes|integer',
        ]);
        if (!$user) {
            return response()->json(["error" => "User not found"], 401);
        }
        if (isset($validated['wallet_balance'])) {
            // Log
            AdminLogs::query()->create([
                "type" => "financial_transactions",
                "action" => "wallet_balance_change",
                "data" => [
                    "user_id" => $user->id,
                    "amount" => abs($validated['wallet_balance'] - $user->wallet_balance),
                    "change" => $validated['wallet_balance'] > $user->wallet_balance ? "increase" : "decrease",
                    "timestamp" => time()
                ]
            ]);
            if ($validated['wallet_balance'] != $user->wallet_balance) {
                History::query()->create([
                    "user_id" => $user->id,
                    "amount" => abs($validated['wallet_balance'] - $user->wallet_balance),
                    "action" => $validated['wallet_balance'] > $user->wallet_balance ? "increase" : "decrease",
                ]);
            }
        }
        $user->update($validated);
        return response()->json(UserResource::make($user));
    }

    public function changePassword(string $id, Request $request)
    {
        $user = User::query()->find($id);

        $validated = $request->validate([
            'current_password' => !$user->should_change_password ? 'required|string' : "sometimes|string",
            'new_password' => 'required|string',
        ]);

        if (!$user->should_change_password && !Hash::check($validated['current_password'], $user->password)) {
            return response()->json(["error" => "Invalid current password"], 401);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->should_change_password = false;
        $user->save();

        // Log
        AdminLogs::query()->create([
            "type" => "user_activity",
            "action" => "change_password",
            "data" => [
                "user_id" => $user->id,
                "user_name" => $user->name,
                "email" => $user->email,
                "phone" => $user->phone_number,
                "timestamp" => time(),
            ]
        ]);

        return response()->json(UserResource::make($user));
    }

    public function get(Request $request)
    {
        $validated = $request->validate([
            'search' => 'sometimes|string',
        ]);
        $query = User::with('addresses', 'cart', 'history');
        if (isset($validated['search'])) {
            $query = $query->where('name', 'like', '%' . $validated['search'] . '%');
        }
        $users = $query->get();
        return response()->json(UserResource::collection($users));
    }

    public function get_by_id(string $id)
    {
        $user = User::with('addresses', 'cart', 'history')->findOrFail($id);
        return response()->json(UserResource::make($user));
    }
}
