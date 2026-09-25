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
     * The Workspaces this User is a Member of (CONTEXT.md, "Member") —
     * includes any Workspace they own, since the Workspace Owner is also a
     * Member.
     */
    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members');
    }

    /**
     * Validation rules for identifying a target User by either "user_id" or
     * "email" — the shared "invite/add by id-or-email" shape used by both
     * DiagramCollaboratorController::store and WorkspaceMemberController::store.
     *
     * @return array<string, array<int, string>>
     */
    public static function idOrEmailRules(): array
    {
        return [
            'user_id' => ['required_without:email', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'email'],
        ];
    }

    /**
     * Resolves the User identified by validated data shaped by
     * {@see idOrEmailRules()}. Throws a 404 (ModelNotFoundException) if
     * neither identifies an existing User.
     *
     * @param  array{user_id?: int, email?: string}  $data
     */
    public static function findByIdOrEmail(array $data): self
    {
        return isset($data['user_id'])
            ? self::findOrFail($data['user_id'])
            : self::where('email', $data['email'])->firstOrFail();
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
