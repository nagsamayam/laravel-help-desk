<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create($validated);

        /** @var JWTGuard $auth */
        $auth = auth()->guard('api');
        $token = $auth->login($user);

        return $this->tokenResponse($token, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->tokenResponse($token);
    }

    public function me(): JsonResponse
    {
        /** @var JWTGuard $auth */
        $auth = Auth::guard('api');
        $user = $auth->user();

        return response()->json($user ? new UserResource($user) : null);
    }

    public function refresh(): JsonResponse
    {
        /** @var JWTGuard $auth */
        $auth = Auth::guard('api');

        return $this->tokenResponse($auth->refresh());
    }

    public function logout(): JsonResponse
    {
        /** @var JWTGuard $auth */
        $auth = Auth::guard('api');
        $auth->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    private function tokenResponse(string $token, int $httpStatus = Response::HTTP_OK): JsonResponse
    {
        /** @var JWTGuard $auth */
        $auth = auth()->guard('api');
        $user = $auth->user();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $auth->factory()->getTTL() * 60,
            'user' => $user ? (new UserResource($user))->resolve() : null,
        ], $httpStatus);
    }
}
