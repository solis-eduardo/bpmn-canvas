<?php

namespace App\Services;

use App\Models\Diagram;
use App\Repositories\VersionRepository;
use RuntimeException;

class DiagramVersionService
{
    public function __construct(private readonly VersionRepository $versions) {}

    /**
     * The content of any two Versions, ordered chronologically by Version
     * number no matter which order they're asked for in, so a diff always
     * reads "older -> newer".
     *
     * @return array{older: array{id: string, number: int, name: string, content: string}, newer: array{id: string, number: int, name: string, content: string}}
     *
     * @throws RuntimeException when either Version doesn't exist
     */
    public function compare(Diagram $diagram, string $from, string $to): array
    {
        $history = collect($this->versions->list($diagram))->keyBy('id');

        $fromMeta = $history->get($from);
        $toMeta = $history->get($to);

        if (! $fromMeta || ! $toMeta) {
            throw new RuntimeException('Version not found.');
        }

        [$olderMeta, $newerMeta] = $fromMeta['number'] <= $toMeta['number']
            ? [$fromMeta, $toMeta]
            : [$toMeta, $fromMeta];

        $content = $this->versions->getMany($diagram, [$olderMeta['id'], $newerMeta['id']]);

        return [
            'older' => $this->describe($olderMeta, $content),
            'newer' => $this->describe($newerMeta, $content),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $content
     * @return array{id: string, number: int, name: string, content: string}
     */
    private function describe(array $meta, array $content): array
    {
        return [
            'id' => $meta['id'],
            'number' => $meta['number'],
            'name' => $meta['name'],
            'content' => $content[$meta['id']],
        ];
    }
}
