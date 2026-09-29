<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query();

        if ($request->has('role')) {
            $role = Role::tryFrom(strtoupper((string) $request->query('role')));
            if ($role !== null) {
                $query->where('role', $role);
            }
        }

        $users = $query->orderBy('first_name')->orderBy('last_name')->get();

        return UserResource::collection($users);
    }

    public function agents(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $agents = User::query()
            ->whereIn('role', [Role::Agent, Role::Admin])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return UserResource::collection($agents);
    }
}
