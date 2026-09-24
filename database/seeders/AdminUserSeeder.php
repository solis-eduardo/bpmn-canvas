<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $email = config('bpmn.admin_email') ?: "admin@{$host}";

        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        if ($isNew) {
            $password = config('bpmn.admin_password') ?: Str::random(24);
            $user->password = Hash::make($password);
        }

        $user->name = config('bpmn.admin_name');
        $user->is_admin = true;
        $user->save();

        if ($isNew && ! config('bpmn.admin_password')) {
            $this->command?->warn("ADMIN_EMAIL/ADMIN_PASSWORD não definidos — Administrador criado com e-mail {$email} e senha gerada: {$password}");
        }
    }
}
