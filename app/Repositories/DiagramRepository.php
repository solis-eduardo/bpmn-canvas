<?php

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\DiagramOldSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the Diagram's database row and its `.bpmn` file on disk consistent
 * with each other. No disk or transaction terms should leak out of this
 * class: callers only ever see "create", "move", and "remove" a Diagram.
 */
class DiagramRepository
{
    /**
     * Creates the Diagram row and writes $initialContent to disk. If the
     * write fails, the created row is rolled back (a Diagram never ends up
     * without a corresponding file).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $initialContent): Diagram
    {
        $diagram = Diagram::create($data);

        try {
            Storage::disk('diagrams')->put($diagram->disk_path, $initialContent);
        } catch (\Throwable $e) {
            $diagram->delete();

            throw $e;
        }

        return $diagram;
    }

    /**
     * Renames a Diagram: moves the file on disk (if the Slug changed),
     * records the old Slug as a permanent redirect, and updates the row, all
     * within a transaction. If the transaction fails, the already-moved file
     * is moved back to its original place.
     */
    public function move(Diagram $diagram, string $newTitle, string $newSlug): Diagram
    {
        $oldSlug = $diagram->slug;
        $file = basename($diagram->disk_path);
        $slugChanged = $newSlug !== $oldSlug;

        if ($slugChanged) {
            Storage::disk('diagrams')->move($oldSlug, $newSlug);
        }

        try {
            DB::transaction(function () use ($diagram, $oldSlug, $newSlug, $file, $newTitle, $slugChanged) {
                if ($slugChanged) {
                    DiagramOldSlug::create([
                        'diagram_id' => $diagram->id,
                        'old_slug' => $oldSlug,
                    ]);
                }

                $diagram->update([
                    'title' => $newTitle,
                    'slug' => $newSlug,
                    'disk_path' => $newSlug.'/'.$file,
                ]);
            });
        } catch (\Throwable $e) {
            if ($slugChanged) {
                Storage::disk('diagrams')->move($newSlug, $oldSlug);
            }

            throw $e;
        }

        return $diagram;
    }

    /**
     * Removes the Diagram row and its corresponding directory on disk.
     */
    public function remove(Diagram $diagram): void
    {
        $slug = $diagram->slug;

        $diagram->delete();

        Storage::disk('diagrams')->deleteDirectory($slug);
    }
}
