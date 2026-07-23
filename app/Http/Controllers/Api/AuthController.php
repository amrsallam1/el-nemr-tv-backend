<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $validator->validated()['name'],
            'email' => Str::lower($validator->validated()['email']),
            'password' => $validator->validated()['password'],
        ]);

        return response()->json($this->issueTokenPair($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', Str::lower($credentials['username']))->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid login details.'], 422);
        }

        return response()->json($this->issueTokenPair($user));
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => ['required', 'string']]);

        $token = AccessToken::query()
            ->with('user')
            ->where('name', 'refresh')
            ->where('token_hash', hash('sha256', $request->string('refresh_token')->toString()))
            ->first();

        if (! $token || ($token->expires_at && $token->expires_at->isPast()) || ! $token->user->is_active) {
            return response()->json(['message' => 'Invalid refresh token.'], 401);
        }

        $user = $token->user;
        $token->delete();

        return response()->json($this->issueTokenPair($user));
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('access_token')->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function issueTokenPair(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $access = Str::random(80);
            $refresh = Str::random(80);

            $user->accessTokens()->create([
                'name' => 'android',
                'token_hash' => hash('sha256', $access),
                'expires_at' => now()->addDays(30),
            ]);
            $user->accessTokens()->create([
                'name' => 'refresh',
                'token_hash' => hash('sha256', $refresh),
                'expires_at' => now()->addDays(90),
            ]);

            return [
                'access_token' => $access,
                'refresh_token' => $refresh,
                'token_type' => 'Bearer',
                'expires_in' => 2_592_000,
            ];
        });
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'role' => $user->role,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'premuim' => $user->is_premium ? 1 : 0,
            'manual_premuim' => 0,
            'expired_in' => $user->premium_until?->toIso8601String(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'favoritesMovies' => [],
            'favoritesSeries' => [],
            'favoritesAnimes' => [],
            'favoritesStreaming' => [],
            'profiles' => [],
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
