<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Override;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable([
    'first_name',
    'last_name',
    'email',
    'password',
    'role',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(
            set: fn (string $firstName) => Str::title(trim($firstName))
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            set: fn (string $lastName) => Str::title(trim($lastName))
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $email) => Str::lower(trim($email))
        );
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => "{$attributes['first_name']} {$attributes['last_name']}"
        );
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'customer_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    #[Override]
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return custom claims to be added to the JWT.
     */
    #[Override]
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }
}
