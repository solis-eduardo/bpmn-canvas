<?php

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\User;
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
        $slug = Diagram::uniqueSlug($title);

        return [
            'title' => $title,
            'slug' => $slug,
            'author_id' => User::factory(),
            'disk_path' => $slug.'/diagram.bpmn',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Diagram $diagram) {
            Storage::disk('diagrams')->put($diagram->disk_path, Diagram::EMPTY_BPMN);

            // Mirrors DiagramController::store(), which always initializes
            // the Version repository right after creating a Diagram — so a
            // factory-made Diagram behaves like a real one, already having
            // an initial Version (see issue #34: Versions are numbered from
            // 1, retroactively, and the very first save counts).
            app(VersionRepository::class)->initialize($diagram, $diagram->author);
        });
    }
}
