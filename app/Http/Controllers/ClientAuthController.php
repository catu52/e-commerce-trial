<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientLoginRequest;
use App\Http\Requests\ClientRegisterRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ClientAuthController extends Controller
{
    /**
     * Register a new client and return a JSON response with
     * the client data and authentication token.
     */
    public function register(ClientRegisterRequest $request): JsonResponse
    {
        // Validate the incoming request using the ClientRegisterRequest
        $validated = $request->validated();
        // Create a new client using the validated data
        $client = Client::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        // Generate an authentication token for the newly created client
        $token = $client->createToken('buyer_auth_token')->plainTextToken;

        // Return a JSON response with the client data and authentication token
        return response()->json([
            'message' => 'Client registered successfully',
            'client' => $client,
            'token' => $token,
        ], 201);
    }

    /**
     * Login an existing client and return a JSON response with
     * the client data and authentication token.
     */
    public function login(ClientLoginRequest $request): JsonResponse
    {
        // Validate the incoming request using the ClientLoginRequest
        $validated = $request->validated();

        // Attempt to find the client by email
        $client = Client::where('email', $validated['email'])->first();

        // Check if the client exists and the provided password is correct
        if (! $client || ! Hash::check($validated['password'], $client->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Generate an authentication token for the authenticated client
        $token = $client->createToken('buyer_auth_token')->plainTextToken;

        // Return a JSON response with the client data and authentication token
        return response()->json([
            'message' => 'Logged in successfully',
            'client' => $client,
            'token' => $token,
        ]);
    }

    /**
     * Logout the currently authenticated client and revoke their authentication token.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the authentication token for the currently authenticated client
        $request->user()->currentAccessToken()->delete();

        // Return a JSON response indicating successful logout
        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
