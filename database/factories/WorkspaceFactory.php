<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'owner_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace) {
            // The Workspace Owner is also a Member (CONTEXT.md, "Workspace";
            // ADR-0009), so a factory-made Workspace behaves like a real one
            // straight away.
            $workspace->addMember($workspace->owner);
        });
    }
}
