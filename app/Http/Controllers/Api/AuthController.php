<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $firstName = $request->string('first_name')->trim()->value();
        $lastName = $request->string('last_name')->trim()->value();

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $request->string('email')->lower()->value(),
            'profile_image_url' => User::defaultProfileImageUrl($firstName, $lastName),
            'password' => $request->string('password')->value(),
        ]);

        $accessToken = $this->issueAccessToken($user);
        $refreshToken = $this->issueRefreshToken($user);

        $this->persistRefreshToken($user, $refreshToken);

        return $this->tokenResponse($user, $accessToken, $refreshToken, 201, 'Registration successful.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->string('email')->lower()->value();
        $password = $request->string('password')->value();

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $accessToken = $this->issueAccessToken($user);
        $refreshToken = $this->issueRefreshToken($user);

        $this->persistRefreshToken($user, $refreshToken);

        return $this->tokenResponse($user, $accessToken, $refreshToken, 200, 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->serializeUser($user),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $incomingRefreshToken = (string) ($request->cookie('refresh_token') ?? $request->input('refresh_token', ''));

        if ($incomingRefreshToken === '') {
            return $this->unauthorized('Refresh token is required.', true);
        }

        $tokenRecord = RefreshToken::query()
            ->where('token_hash', hash('sha256', $incomingRefreshToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $tokenRecord || $tokenRecord->expires_at->isPast()) {
            return $this->unauthorized('Refresh token is invalid or expired.', true);
        }

        try {
            $payload = JWTAuth::setToken($incomingRefreshToken)->getPayload();
        } catch (JWTException $exception) {
            return $this->unauthorized('Refresh token is invalid.', true);
        }

        if (($payload->get('token_type') ?? null) !== 'refresh') {
            return $this->unauthorized('Invalid token type for refresh.', true);
        }

        if ((string) $payload->get('jti') !== (string) $tokenRecord->jti) {
            return $this->unauthorized('Refresh token mismatch.', true);
        }

        $user = User::query()->find($payload->get('sub'));

        if (! $user) {
            return $this->unauthorized('User not found for refresh token.', true);
        }

        $tokenRecord->update([
            'revoked_at' => now(),
        ]);

        $accessToken = $this->issueAccessToken($user);
        $refreshToken = $this->issueRefreshToken($user);

        $this->persistRefreshToken($user, $refreshToken);

        return $this->tokenResponse($user, $accessToken, $refreshToken, 200, 'Token refreshed.');
    }

    public function logout(Request $request): JsonResponse
    {
        $incomingRefreshToken = (string) $request->cookie('refresh_token', '');

        if ($incomingRefreshToken !== '') {
            RefreshToken::query()
                ->where('token_hash', hash('sha256', $incomingRefreshToken))
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                ]);
        }

        try {
            $accessToken = JWTAuth::getToken();

            if ($accessToken) {
                JWTAuth::setToken($accessToken)->invalidate(true);
            }
        } catch (JWTException $exception) {
            // Ignore invalid/expired access token on logout while still revoking refresh token.
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ])->withCookie($this->forgetRefreshCookie());
    }

    private function issueAccessToken(User $user): string
    {
        return JWTAuth::claims(['token_type' => 'access'])->fromUser($user);
    }

    private function issueRefreshToken(User $user): string
    {
        $factory = JWTAuth::factory();

        $defaultTtl = $factory->getTTL();
        $refreshTtl = (int) env('REFRESH_TOKEN_TTL_MINUTES', 43200);

        $factory->setTTL($refreshTtl);
        $refreshToken = JWTAuth::claims(['token_type' => 'refresh'])->fromUser($user);
        $factory->setTTL($defaultTtl);

        return $refreshToken;
    }

    private function persistRefreshToken(User $user, string $refreshToken): void
    {
        $payload = JWTAuth::setToken($refreshToken)->getPayload();

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $refreshToken),
            'jti' => (string) $payload->get('jti'),
            'expires_at' => Carbon::createFromTimestampUTC((int) $payload->get('exp')),
            'revoked_at' => null,
        ]);
    }

    private function tokenResponse(User $user, string $accessToken, string $refreshToken, int $statusCode, string $message): JsonResponse
    {
        $payload = JWTAuth::setToken($refreshToken)->getPayload();
        $expiresAt = Carbon::createFromTimestampUTC((int) $payload->get('exp'));

        return response()->json([
            'message' => $message,
            'data' => [
                'user' => $this->serializeUser($user),
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'access_token_expires_in' => (int) config('jwt.ttl') * 60,
                'refresh_token_expires_at' => $expiresAt->toIso8601String(),
            ],
        ], $statusCode)->withCookie($this->refreshCookie($refreshToken, $expiresAt));
    }

    private function refreshCookie(string $refreshToken, Carbon $expiresAt): \Symfony\Component\HttpFoundation\Cookie
    {
        $minutes = max(1, now()->diffInMinutes($expiresAt, false));

        return cookie(
            name: 'refresh_token',
            value: $refreshToken,
            minutes: $minutes,
            path: '/',
            secure: filter_var((string) env('JWT_COOKIE_SECURE', app()->isProduction()), FILTER_VALIDATE_BOOL),
            httpOnly: true,
            sameSite: 'lax'
        );
    }

    private function forgetRefreshCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie()->forget('refresh_token', '/');
    }

    private function unauthorized(string $message, bool $clearCookie = false): JsonResponse
    {
        $response = response()->json([
            'message' => $message,
        ], 401);

        if ($clearCookie) {
            $response->withCookie($this->forgetRefreshCookie());
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'email' => $user->email,
            'profile_image_url' => $user->profile_image_url,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
