<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/v1/auth/register
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required','string','max:60'],
            'last_name'  => ['nullable','string','max:60'],
            'email'      => ['required','email','max:120','unique:users,email'],
            'password'   => ['required','string','min:6','confirmed'],
            'marketing_opt_in' => ['nullable','boolean'],
        ]);

        $user = User::create([
            'name'     => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $customer = Customer::create([
            'user_id' => $user->id,
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'] ?? null,
            'marketing_opt_in' => (int)($data['marketing_opt_in'] ?? 0),
            'default_billing_address_id' => null,
            'default_shipping_address_id' => null,
        ]);

        $token = $user->createToken('customer-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Registered successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'customer' => [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'marketing_opt_in' => (int)$customer->marketing_opt_in,
            ],
        ], 201);
    }

    // POST /api/v1/auth/login
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        // ensure customer profile exists
        $customer = $user->customer;
        if (!$customer) {
            $customer = Customer::create([
                'user_id' => $user->id,
                'first_name' => $user->name ?? 'Customer',
                'last_name' => null,
                'marketing_opt_in' => 0,
                'default_billing_address_id' => null,
                'default_shipping_address_id' => null,
            ]);
        }

        $token = $user->createToken('customer-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'customer' => [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'marketing_opt_in' => (int)$customer->marketing_opt_in,
            ],
        ]);
    }

    // GET /api/v1/auth/me  (auth:sanctum)
    public function me(Request $request)
    {
        $user = $request->user();
        $customer = $user->customer;

        return response()->json([
            'status' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'customer' => $customer ? [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'default_billing_address_id' => $customer->default_billing_address_id,
                'default_shipping_address_id' => $customer->default_shipping_address_id,
                'marketing_opt_in' => (int)$customer->marketing_opt_in,
            ] : null
        ]);
    }

    // POST /api/v1/auth/logout  (auth:sanctum)
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out',
        ]);
    }
}