<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diagram_id', 'user_id', 'status'])]
class DiagramCollaborator extends Model
{
    protected $table = 'diagram_collaborators';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REQUESTED = 'requested';

    public const UPDATED_AT = null;

    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
