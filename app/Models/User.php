<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// keycloak_id is only ever assigned internally by the OAuth flow (KeycloakLoginController),
// never from a user request payload — no route exposes it.
#[Fillable(['name', 'email', 'password', 'keycloak_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_admin_via_keycloak_claim' => 'boolean',
        ];
    }

    /**
     * Whether this User's current Administrator status can be revoked from the
     * User management screen. Per ADR 0005, a role granted via the Keycloak
     * claim is displayed but only a manual origin can be turned off here.
     */
    public function adminRoleIsManuallyRevocable(): bool
    {
        return $this->is_admin && ! $this->is_admin_via_keycloak_claim;
    }
}
