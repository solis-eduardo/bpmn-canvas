<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the append-only activity log (CONTEXT.md, "Activity Log"; ADR-0022).
 * The actor is whoever is signed in; entries made outside a request by a
 * person (a Keycloak login, a group sync) have none. Names are copied into
 * the entry so it stays readable after a rename or deletion.
 */
class ActivityLogger
{
    /**
     * @param  Model  $subject  the User, Workspace, Diagram or Share the action is about
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  bool  $system  the change was made by the application itself (a Keycloak login or
     *                        group sync), so whoever happens to be signed in is not its actor
     */
    public function record(
        string $action,
        Model $subject,
        ?Diagram $diagram = null,
        ?Workspace $workspace = null,
        array $before = [],
        array $after = [],
        bool $system = false,
    ): ActivityLog {
        $actor = $system ? null : auth()->user();
        $workspace ??= $diagram?->workspace;

        return ActivityLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'action' => $action,
            'diagram_id' => $diagram?->id,
            'diagram_title' => $diagram?->title,
            'workspace_id' => $workspace?->id,
            'workspace_name' => $workspace?->name,
            'subject_type' => $this->subjectType($subject),
            'subject_id' => $subject->getKey(),
            'subject_label' => $this->subjectLabel($subject),
            'before' => $before ?: null,
            'after' => $after ?: null,
        ]);
    }

    /**
     * Logs a change of $user's Administrator role, comparing it to $wasAdmin;
     * nothing is logged when the role did not change.
     */
    public function recordAdministratorChange(User $user, bool $wasAdmin, bool $system = false): void
    {
        $isAdmin = (bool) $user->is_admin;

        if ($isAdmin === $wasAdmin) {
            return;
        }

        $this->record(
            $isAdmin ? 'administrator.granted' : 'administrator.revoked',
            $user,
            before: ['is_admin' => $wasAdmin],
            after: ['is_admin' => $isAdmin],
            system: $system,
        );
    }

    private function subjectType(Model $subject): string
    {
        return match (true) {
            $subject instanceof User => 'user',
            $subject instanceof Workspace => 'workspace',
            $subject instanceof Diagram => 'diagram',
            $subject instanceof Share => 'share',
            default => strtolower(class_basename($subject)),
        };
    }

    private function subjectLabel(Model $subject): string
    {
        return match (true) {
            $subject instanceof User, $subject instanceof Workspace => $subject->name,
            $subject instanceof Diagram => $subject->title,
            $subject instanceof Share => 'Share #'.$subject->getKey(),
            default => (string) $subject->getKey(),
        };
    }
}
