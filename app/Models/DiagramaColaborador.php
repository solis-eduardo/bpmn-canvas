<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diagrama_id', 'usuario_id', 'status'])]
class DiagramaColaborador extends Model
{
    protected $table = 'diagrama_colaboradores';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_SOLICITADO = 'solicitado';

    public const UPDATED_AT = null;

    public function diagrama(): BelongsTo
    {
        return $this->belongsTo(Diagrama::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
