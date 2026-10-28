<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum personal access tokens for API clients. The admin console itself uses the session, not tokens.
 */
class TokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            event(new Failed('sanctum', $user, ['email' => $data['email']]));
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $token = $user->createToken($data['device_name']);
        AuditLog::record('token_create', 'auth', $user->email, $data['device_name'], $user);

        return response()->json(['token' => $token->plainTextToken], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        AuditLog::record('token_revoke', 'auth', $request->user()->email, $token->name);
        $token->delete();

        return response()->json(null, 204);
    }
}
