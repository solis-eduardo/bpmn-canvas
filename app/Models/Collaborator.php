<?php

namespace App\Models;

use App\Models\Scopes\ActiveUserScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diagram_id', 'user_id', 'status', 'role'])]
class Collaborator extends Model
{
    protected $table = 'collaborators';

    /** @var array<string, string> */
    protected $attributes = ['role' => self::ROLE_COLLABORATOR];

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REQUESTED = 'requested';

    public const ROLE_COLLABORATOR = 'collaborator';

    public const ROLE_MANAGER = 'manager';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Diagram, $this> */
    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(ActiveUserScope::class);
    }

    /**
     * Rows of active Users only (ADR-0020): the row of a deactivated User is
     * kept, so reactivation needs no re-invitation, but no list counts it.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfActiveUsers(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('user_id'), User::query()->select('id'));
    }
}
