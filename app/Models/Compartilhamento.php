<?php

namespace App\Models;

use Database\Factories\CompartilhamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

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

    public function showUrl(): string
    {
        return URL::temporarySignedRoute(
            'compartilhamentos.show',
            $this->signatureExpiration(),
            ['compartilhamento' => $this->id],
        );
    }

    public function downloadUrl(): ?string
    {
        if (! $this->allow_download) {
            return null;
        }

        return URL::temporarySignedRoute(
            'compartilhamentos.download',
            $this->signatureExpiration(),
            ['compartilhamento' => $this->id, 'formato' => 'bpmn'],
        );
    }

    /**
     * A assinatura da URL não é o mecanismo de expiração do compartilhamento:
     * isValido() faz isso via expires_at/revoked_at. Por isso a assinatura
     * dura muito mais que qualquer expires_at válido (máx. 60 dias).
     */
    private function signatureExpiration(): \DateTimeInterface
    {
        return now()->addYears(10);
    }
}
