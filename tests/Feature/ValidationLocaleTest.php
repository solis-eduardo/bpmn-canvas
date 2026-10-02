<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidationLocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function localizedMessages(): array
    {
        return [
            'pt_BR' => ['pt_BR', 'É obrigatória a indicação de um valor para o campo e-mail.', 'É obrigatória a indicação de um valor para o campo senha.'],
            'es' => ['es', 'El campo correo electrónico es obligatorio.', 'El campo contraseña es obligatorio.'],
            'en' => ['en', 'The email field is required.', 'The password field is required.'],
        ];
    }

    #[DataProvider('localizedMessages')]
    public function test_validation_errors_follow_session_locale(string $locale, string $email, string $password): void
    {
        $this->withSession(['locale' => $locale])
            ->from('/login')
            ->post('/login', [])
            ->assertSessionHasErrors(['email' => $email, 'password' => $password]);
    }

    #[DataProvider('localizedMessages')]
    public function test_json_validation_errors_follow_accept_language(string $locale, string $email, string $password): void
    {
        $this->withHeaders(['Accept-Language' => str_replace('_', '-', $locale)])
            ->postJson('/login', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', $email)
            ->assertJsonPath('errors.password.0', $password);
    }
}
