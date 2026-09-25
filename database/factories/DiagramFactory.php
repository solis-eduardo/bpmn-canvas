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
            'disk_path' => $slug.'/diagram.bpmn',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Diagram $diagram) {
            Storage::disk('diagrams')->put($diagram->disk_path, Diagram::EMPTY_BPMN);
        });
    }
}
