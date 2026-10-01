<?php

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Diagram>
 */
class DiagramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);
        $slug = app(DiagramRepository::class)->uniqueSlug($title);

        return [
            'title' => $title,
            'slug' => $slug,
            'author_id' => User::factory(),
            // Diagram Owner starts as the Author (CONTEXT.md, "Diagram
            // Owner"). Left unset here and defaulted in afterMaking()
            // below, so it tracks whatever author_id ends up being
            // (including a ->for($user, 'author') override) unless the
            // test explicitly diverges it via ->for($user, 'owner') or
            // ->state(['owner_id' => ...]).
            'disk_path' => $slug.'/diagram.bpmn',
        ];
    }

    public function configure(): static
    {
        return $this
            // Keeps disk_path derived from the final slug even when a test
            // overrides 'slug' directly (definition() can't know about that
            // override yet): disk_path must always point at the Diagram's
            // own directory, the same invariant application code keeps.
            // Diagram Owner starts as the Author (CONTEXT.md, "Diagram
            // Owner") unless a test explicitly diverges it.
            ->afterMaking(function (Diagram $diagram) {
                $diagram->disk_path = $diagram->slug.'/diagram.bpmn';
                $diagram->owner_id ??= $diagram->author_id;
            })
            ->afterCreating(function (Diagram $diagram) {
                Storage::disk('diagrams')->put($diagram->disk_path, Diagram::emptyBpmn());

                // Mirrors DiagramController::store(), which always initializes
                // the Version repository right after creating a Diagram — so a
                // factory-made Diagram behaves like a real one, already having
                // an initial Version (see issue #34: Versions are numbered from
                // 1, retroactively, and the very first save counts).
                app(VersionRepository::class)->initialize($diagram, $diagram->author);
            });
    }
}
