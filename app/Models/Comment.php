<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a Comment Thread (CONTEXT.md, "Comment"; ADR-0022).
 *
 * @property int $id
 * @property int $comment_thread_id
 * @property int $author_id
 * @property string $body
 */
#[Fillable(['comment_thread_id', 'author_id', 'body'])]
class Comment extends Model
{
    /** Longest Comment body, in characters. */
    public const MAX_BODY_LENGTH = 2000;

    /** @return BelongsTo<CommentThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommentThread::class, 'comment_thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withDeactivated();
    }
}
