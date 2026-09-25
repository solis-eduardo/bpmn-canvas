<?php

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Share>
 */
class ShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diagram_id' => Diagram::factory(),
            'expires_at' => now()->addDays(7),
            'allow_download' => false,
            'revoked_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
