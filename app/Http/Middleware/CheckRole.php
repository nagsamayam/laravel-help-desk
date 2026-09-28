<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowedRoles = array_map(fn ($role) => Role::tryFrom($role), $roles);

        if (! $user || ! in_array($user->role, $allowedRoles, true)) {
            return response()->json(
                [
                    'message' => 'Forbidden',
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
