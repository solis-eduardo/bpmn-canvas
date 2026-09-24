<?php

namespace App\Models;

use Database\Factories\CompartilhamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diagrama_id', 'expires_at', 'allow_download', 'revoked_at', 'created_by'])]
class Compartilhamento extends Model
{
    /** @use HasFactory<CompartilhamentoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'allow_download' => 'boolean',
        ];
    }

    public function diagrama(): BelongsTo
    {
        return $this->belongsTo(Diagrama::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValido(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
