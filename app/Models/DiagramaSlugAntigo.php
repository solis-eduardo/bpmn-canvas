<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diagrama_id', 'slug_antigo'])]
class DiagramaSlugAntigo extends Model
{
    protected $table = 'diagrama_slugs_antigos';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function diagrama(): BelongsTo
    {
        return $this->belongsTo(Diagrama::class);
    }
}
