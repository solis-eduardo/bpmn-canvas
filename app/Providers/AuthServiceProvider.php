<?php

namespace App\Providers;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\CollaboratorPolicy;
use App\Policies\DiagramOwnerPolicy;
use App\Policies\DiagramPolicy;
use App\Policies\DiagramWorkspacePolicy;
use App\Policies\ForkPolicy;
use App\Policies\LockPolicy;
use App\Policies\MemberPolicy;
use App\Policies\WorkspaceOwnerPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Authorization wiring (Gates and Policies), split out of
 * AppServiceProvider to keep its coupling under the PHPMD threshold
 * (phpmd.xml, CouplingBetweenObjects) as the number of Policies grows.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->is_admin);

        Gate::policy(Diagram::class, DiagramPolicy::class);
        Gate::policy(Workspace::class, WorkspacePolicy::class);

        $this->defineSubResourcePolicies();
    }

    /**
     * Sub-resource Policies don't belong to a single Model, so each ability
     * is registered by name: `lock.transfer`, `collaborator.grant`, ...
     */
    private function defineSubResourcePolicies(): void
    {
        $policies = [
            'lock' => [LockPolicy::class, ['view', 'acquire', 'renew', 'write', 'transfer', 'release']],
            'fork' => [ForkPolicy::class, ['create']],
            'collaborator' => [CollaboratorPolicy::class, ['grant', 'invite', 'requestAccess', 'revoke']],
            'member' => [MemberPolicy::class, ['add', 'remove', 'leave']],
            'diagram-owner' => [DiagramOwnerPolicy::class, ['transfer']],
            'diagram-workspace' => [DiagramWorkspacePolicy::class, ['assign', 'remove']],
            'workspace-owner' => [WorkspaceOwnerPolicy::class, ['transfer']],
        ];

        foreach ($policies as $prefix => [$class, $abilities]) {
            foreach ($abilities as $ability) {
                Gate::define("{$prefix}.{$ability}", [$class, $ability]);
            }
        }
    }
}
