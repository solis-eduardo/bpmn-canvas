<?php

namespace Database\Factories;

use App\Models\Compartilhamento;
use App\Models\Diagrama;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compartilhamento>
 */
class CompartilhamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diagrama_id' => Diagrama::factory(),
            'expires_at' => now()->addDays(7),
            'allow_download' => false,
            'revoked_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
