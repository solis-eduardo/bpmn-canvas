<?php

namespace Database\Factories;

use App\Models\Diagrama;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Diagrama>
 */
class DiagramaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);
        $slug = Diagrama::uniqueSlug($title);

        return [
            'title' => $title,
            'slug' => $slug,
            'author_id' => User::factory(),
            'disk_path' => $slug.'/diagram.bpmn',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Diagrama $diagrama) {
            Storage::disk('diagrams')->put($diagrama->disk_path, Diagrama::EMPTY_BPMN);
        });
    }
}
