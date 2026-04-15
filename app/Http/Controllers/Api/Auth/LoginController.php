<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * Authenticate editor/admin account and issue a Sanctum token.
     *
     * Request example:
     * {"email":"editor@example.com","password":"secret-password"}
     *
     * Success response example:
     * {"message":"Login success","token":"1|token","user":{"id":"uuid","email":"editor@example.com"}}
     *
     * Error response example (401):
     * {"message":"Invalid credentials"}
     *
     * @response array{
     *     message: string,
     *     token: string,
     *     user: array{id: string, email: string}
     * }
     */
    public function store(ApiLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user->createToken('editor-token')->plainTextToken;

        return response()->json([
            'message' => 'Login success',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
    }
}
