<?php

namespace App\Models;

use App\Support\Slugger;
use Database\Factories\DiagramaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['slug', 'title', 'author_id', 'disk_path', 'forked_from_id'])]
class Diagrama extends Model
{
    /** @use HasFactory<DiagramaFactory> */
    use HasFactory;

    public const EMPTY_BPMN = <<<'BPMN'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" name="Início" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="180" y="160" width="36" height="36" />
      </bpmndi:BPMNShape>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>
BPMN;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function forkedFrom(): BelongsTo
    {
        return $this->belongsTo(Diagrama::class, 'forked_from_id');
    }

    public function slugsAntigos(): HasMany
    {
        return $this->hasMany(DiagramaSlugAntigo::class);
    }

    public function compartilhamentos(): HasMany
    {
        return $this->hasMany(Compartilhamento::class);
    }

    /**
     * Gera um slug único a partir de um nome livre, normalizando-o e
     * anexando um sufixo alfanumérico curto em caso de colisão com o slug
     * atual de qualquer Diagrama ou com um slug antigo reservado.
     */
    public static function uniqueSlug(string $name, ?int $ignoreDiagramaId = null): string
    {
        $base = Slugger::slugify($name);
        $slug = $base;
        $attempts = 0;
        $maxAttempts = 20;

        while (static::slugIsTaken($slug, $ignoreDiagramaId)) {
            if (++$attempts > $maxAttempts) {
                $slug = $base.'-'.Str::lower(Str::random(12));

                break;
            }

            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    private static function slugIsTaken(string $slug, ?int $ignoreDiagramaId): bool
    {
        $query = static::where('slug', $slug);

        if ($ignoreDiagramaId !== null) {
            $query->where('id', '!=', $ignoreDiagramaId);
        }

        if ($query->exists()) {
            return true;
        }

        return DiagramaSlugAntigo::where('slug_antigo', $slug)->exists();
    }
}
