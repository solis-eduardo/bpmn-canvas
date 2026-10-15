<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A conversation about a Diagram, or about one of its BPMN elements
 * (CONTEXT.md, "Comment Thread"; ADR-0024).
 *
 * @property int $id
 * @property int $diagram_id
 * @property ?string $element_id
 * @property ?Carbon $resolved_at
 */
#[Fillable(['diagram_id', 'element_id'])]
class CommentThread extends Model
{
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Diagram, $this> */
    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id')->withDeactivated();
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
