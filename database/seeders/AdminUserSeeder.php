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
        $email = config('bpmn.admin_email');

        if (! $email) {
            $this->command?->warn('ADMIN_EMAIL não definido — nenhum Administrador semeado.');

            return;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = config('bpmn.admin_name');
        $user->password = Hash::make(config('bpmn.admin_password') ?: Str::random(32));
        $user->is_admin = true;
        $user->save();
    }
}
