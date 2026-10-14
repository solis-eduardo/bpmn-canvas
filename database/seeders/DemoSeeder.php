<?php

namespace Database\Seeders;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\CollaboratorService;
use App\Services\ShareService;
use App\Support\Demo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample data for the browser demo (ADR-0018): three Users with known
 * credentials and Diagrams that put every main feature on screen at first
 * login.
 */
class DemoSeeder extends Seeder
{
    public function __construct(
        private readonly DiagramRepository $diagrams,
        private readonly VersionRepository $versions,
        private readonly WorkspaceRepository $workspaces,
        private readonly CollaboratorService $collaborators,
        private readonly ShareService $shares,
    ) {}

    public function run(): void
    {
        $users = collect(Demo::ACCOUNTS)->map(function (array $account): User {
            $user = User::firstOrNew(['email' => $account['email']]);
            $user->name = $account['name'];
            $user->password = Hash::make(Demo::PASSWORD);
            $user->is_admin = $account['is_admin'];
            $user->email_verified_at = now();
            $user->save();

            return $user;
        });

        ['administrator' => $admin, 'author' => $author, 'collaborator' => $collaborator] = $users->all();

        $operations = $this->workspaces->create('Operations', $author);
        $this->workspaces->addMember($operations, $collaborator);

        $this->orderFulfilment($author, $collaborator, $operations);
        $this->expenseApproval($author, $collaborator);
        $this->customerOnboarding($admin, $author);
        $this->incidentResponse($collaborator, $operations);
    }

    /**
     * Versions history (renamed, provisional), Tags, a Workspace, a
     * Collaborator promoted to Diagram Manager and an active Share.
     */
    private function orderFulfilment(User $author, User $collaborator, Workspace $workspace): void
    {
        $diagram = $this->createDiagram('Order fulfilment', $author, ['Sales', 'Logistics'], $workspace);

        $this->collaborators->grant($diagram, $collaborator, Collaborator::ROLE_MANAGER);

        $this->saveVersion($diagram, $author, ['Receive order', 'Check stock']);
        $this->saveVersion($diagram, $collaborator, ['Receive order', 'Check stock', 'Pick items']);
        $approved = $this->saveVersion($diagram, $author, ['Receive order', 'Check stock', 'Pick items', 'Ship order']);
        $this->versions->rename($diagram, $approved, 'Approved by operations');

        $provisional = $this->saveVersion($diagram, $collaborator, ['Receive order', 'Check stock', 'Pick items', 'Pack and ship', 'Send invoice']);
        $this->versions->markProvisional($diagram, $provisional);

        $this->shares->create($diagram, $author, days: 30, allowDownload: true);
    }

    /**
     * An active Lock held by another User, with a request waiting on it.
     */
    private function expenseApproval(User $author, User $collaborator): void
    {
        $diagram = $this->createDiagram('Expense approval', $author, ['Finance']);
        $this->collaborators->grant($diagram, $collaborator);
        $this->saveVersion($diagram, $author, ['Submit expense', 'Manager review', 'Finance review']);

        $diagram->acquireLock($collaborator);
        $diagram->registerLockRequest($author);
    }

    /**
     * A pending access request, and a Diagram only its Diagram Owner sees.
     */
    private function customerOnboarding(User $admin, User $author): void
    {
        $diagram = $this->createDiagram('Customer onboarding', $admin, ['Sales', 'Compliance']);
        $this->saveVersion($diagram, $admin, ['Collect documents', 'Verify identity', 'Open account']);

        $this->collaborators->requestAccess($diagram, $author);
    }

    private function incidentResponse(User $owner, Workspace $workspace): void
    {
        $diagram = $this->createDiagram('Incident response', $owner, ['Operations'], $workspace);
        $this->saveVersion($diagram, $owner, ['Detect incident', 'Triage', 'Mitigate', 'Write post-mortem']);
        $this->shares->create($diagram, $owner, days: 7);
    }

    /**
     * @param  list<string>  $tags
     */
    private function createDiagram(string $title, User $owner, array $tags, ?Workspace $workspace = null): Diagram
    {
        $slug = $this->diagrams->uniqueSlug($title);

        $diagram = $this->diagrams->create([
            'title' => $title,
            'slug' => $slug,
            'author_id' => $owner->id,
            'owner_id' => $owner->id,
            'workspace_id' => $workspace?->id,
            'disk_path' => $slug.'/diagram.bpmn',
        ], Diagram::emptyBpmn());

        $this->versions->initialize($diagram, $owner);
        $diagram->syncTags($tags);

        return $diagram;
    }

    /**
     * @param  list<string>  $steps
     */
    private function saveVersion(Diagram $diagram, User $author, array $steps): string
    {
        return $this->versions->save($diagram, $author, $this->process($steps));
    }

    /**
     * A start event, one task per step and an end event, laid out left to right.
     *
     * @param  list<string>  $steps
     */
    private function process(array $steps): string
    {
        $nodes = ['<bpmn:startEvent id="Start" />'];
        $shapes = ['<bpmndi:BPMNShape id="Start_di" bpmnElement="Start"><dc:Bounds x="160" y="102" width="36" height="36" /></bpmndi:BPMNShape>'];
        $flows = [];
        $edges = [];
        $previous = 'Start';
        $x = 240;

        foreach ($steps as $i => $step) {
            $id = 'Task_'.($i + 1);
            $nodes[] = sprintf('<bpmn:task id="%s" name="%s" />', $id, htmlspecialchars($step, ENT_QUOTES | ENT_XML1));
            $shapes[] = sprintf('<bpmndi:BPMNShape id="%s_di" bpmnElement="%s"><dc:Bounds x="%d" y="80" width="100" height="80" /></bpmndi:BPMNShape>', $id, $id, $x);
            $this->connect($previous, $id, $previous === 'Start' ? 196 : $x - 40, $x, $flows, $edges);
            $previous = $id;
            $x += 140;
        }

        $nodes[] = '<bpmn:endEvent id="End" />';
        $shapes[] = sprintf('<bpmndi:BPMNShape id="End_di" bpmnElement="End"><dc:Bounds x="%d" y="102" width="36" height="36" /></bpmndi:BPMNShape>', $x);
        $this->connect($previous, 'End', $x - 40, $x, $flows, $edges);

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" xmlns:dc="http://www.omg.org/spec/DD/20100524/DC" xmlns:di="http://www.omg.org/spec/DD/20100524/DI" id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">'.
            '<bpmn:process id="Process_1" isExecutable="false">%s%s</bpmn:process>'.
            '<bpmndi:BPMNDiagram id="BPMNDiagram_1"><bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">%s%s</bpmndi:BPMNPlane></bpmndi:BPMNDiagram>'.
            '</bpmn:definitions>',
            implode('', $nodes),
            implode('', $flows),
            implode('', $shapes),
            implode('', $edges),
        );
    }

    /**
     * @param  list<string>  $flows
     * @param  list<string>  $edges
     */
    private function connect(string $from, string $to, int $fromX, int $toX, array &$flows, array &$edges): void
    {
        $id = "Flow_{$from}_{$to}";
        $flows[] = sprintf('<bpmn:sequenceFlow id="%s" sourceRef="%s" targetRef="%s" />', $id, $from, $to);
        $edges[] = sprintf('<bpmndi:BPMNEdge id="%s_di" bpmnElement="%s"><di:waypoint x="%d" y="120" /><di:waypoint x="%d" y="120" /></bpmndi:BPMNEdge>', $id, $id, $fromX, $toX);
    }
}
