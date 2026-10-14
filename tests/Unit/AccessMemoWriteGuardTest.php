<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards the AccessMemo invalidation rule (issue #241): a write to the rows
 * behind the access predicates that fires no model event leaves the memo
 * stale. This fails when app/ gains one that is not on the reviewed list;
 * a new entry means the write calls AccessMemo::forget() itself (or provably
 * cannot change access).
 */
class AccessMemoWriteGuardTest extends TestCase
{
    /**
     * Files reviewed to either forget() the memo or touch no access column.
     *
     * @var list<string>
     */
    private const REVIEWED = [
        // Writes searchable_text / updated_at only.
        'Repositories/Concerns/SharedVersionRepository.php',
        // This rule's own docblock.
        'Support/AccessMemo.php',
        // Builder deletes that forget() right after.
        'Repositories/MemberRepository.php',
    ];

    private const EVENTLESS_WRITES = [
        '/\b(saveQuietly|updateQuietly|deleteQuietly|forceDeleteQuietly|withoutEvents|withoutEvent)\b/',
        '/DB::table\([\'"](diagrams|collaborators|members|workspaces)[\'"]\)/',
        '/\bDB::(update|delete|insert|statement|unprepared)\(/',
        '/\b(Member|Collaborator|Workspace)::[^;]*->(delete|update|forceDelete)\(/',
        '/->(members|collaborators)\(\)[^;]*->(delete|update|forceDelete)\(/',
        '/\b(Member|Collaborator)::(insert|upsert|destroy)\(/',
    ];

    public function test_no_unreviewed_write_bypasses_the_model_events_on_access_rows(): void
    {
        $offenders = [];
        $root = dirname(__DIR__, 2).'/app/';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            $relative = substr((string) $file, strlen($root));

            if ($file->getExtension() !== 'php' || in_array($relative, self::REVIEWED, true)) {
                continue;
            }

            foreach (self::EVENTLESS_WRITES as $pattern) {
                if (preg_match($pattern, (string) file_get_contents((string) $file), $match)) {
                    $offenders[] = "{$relative}: {$match[0]}";
                }
            }
        }

        $this->assertSame([], $offenders, 'Event-less writes to access rows must call AccessMemo::forget().');
    }
}
