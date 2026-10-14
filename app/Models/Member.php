<?php

namespace App\Models;

use App\Models\Scopes\ActiveUserScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'user_id', 'origin'])]
class Member extends Model
{
    protected $table = 'members';

    public const ORIGIN_MANUAL = 'manual';

    public const ORIGIN_GROUP = 'group';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
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
