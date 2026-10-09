<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// keycloak_id is only ever assigned internally by the OAuth flow (KeycloakLoginController),
// never from a user request payload — no route exposes it. login_method is set only from
// validated Administrator input or by the OAuth flow.
#[Fillable(['name', 'email', 'password', 'keycloak_id', 'login_method', 'email_notifications_enabled', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const LOGIN_METHOD_LOCAL = 'local';

    public const LOGIN_METHOD_KEYCLOAK = 'keycloak';

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
            'email_notifications_enabled' => 'boolean',
        ];
    }

    /**
     * The IANA timezone this User sees dates in; the app timezone (UTC) when
     * they have not chosen one. Storage is always UTC, only display converts.
     */
    public function displayTimezone(): string
    {
        return $this->timezone ?: config('app.timezone');
    }

    /**
     * Whether this User signs in through Keycloak (ADR-0015); their local
     * password, if any, is kept but not valid for login.
     */
    public function usesKeycloak(): bool
    {
        return $this->login_method === self::LOGIN_METHOD_KEYCLOAK;
    }

    /**
     * The Workspaces this User is a Member of (CONTEXT.md, "Member") —
     * includes any Workspace they own, since the Workspace Owner is also a
     * Member.
     */
    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'members');
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
