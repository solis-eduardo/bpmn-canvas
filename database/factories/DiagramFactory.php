<?php

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\User;
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
        $slug = Diagram::uniqueSlug($title);

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
        return $this->afterMaking(function (Diagram $diagram) {
            $diagram->owner_id ??= $diagram->author_id;
        })->afterCreating(function (Diagram $diagram) {
            Storage::disk('diagrams')->put($diagram->disk_path, Diagram::emptyBpmn());
        });
    }
}
