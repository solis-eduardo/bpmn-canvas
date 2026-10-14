<?php

namespace App\Support;

use App\Models\User;

/**
 * The browser demo (ADR-0018): a self-contained build of the app whose
 * affordances are all behind DEMO_MODE, off by default.
 */
final class Demo
{
    public const PASSWORD = 'demo-password';

    /**
     * The seeded accounts, keyed by the role they showcase. Signing in as one
     * of them is how the multi-user features (Lock, access requests) are
     * demonstrated.
     *
     * @var array<string, array{name: string, email: string, is_admin: bool}>
     */
    public const ACCOUNTS = [
        'administrator' => ['name' => 'Alex Admin', 'email' => 'admin@demo.test', 'is_admin' => true],
        'author' => ['name' => 'Ana Author', 'email' => 'author@demo.test', 'is_admin' => false],
        'collaborator' => ['name' => 'Caio Collaborator', 'email' => 'collaborator@demo.test', 'is_admin' => false],
    ];

    public static function enabled(): bool
    {
        return (bool) config('bpmn.demo_mode');
    }

    public static function isAccount(User $user): bool
    {
        return collect(self::ACCOUNTS)->contains('email', $user->email);
    }
}
