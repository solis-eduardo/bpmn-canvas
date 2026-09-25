<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\Share;
use App\Repositories\VersionRepository;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicShareController extends Controller
{
    public function show(Share $share, VersionRepository $versions): View
    {
        if (! $share->isValid()) {
            return view('shares.invalid');
        }

        $diagram = $share->diagram;
        $diagramXml = $this->content($share, $diagram, $versions);

        return view('shares.show', compact('share', 'diagram', 'diagramXml'));
    }

    public function download(Share $share, string $format, VersionRepository $versions): Response
    {
        abort_unless($share->isValid(), 403);
        abort_unless($share->allow_download, 403);
        abort_unless($format === 'bpmn', 404);

        $diagram = $share->diagram;
        $content = $this->content($share, $diagram, $versions);

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$diagram->slug.'.bpmn"',
        ]);
    }

    /**
     * The Diagram content a Share serves: the pinned Version's content when
     * pinned, otherwise whatever is currently on disk (the latest Version)
     * — reuses VersionRepository::get(), the same access path
     * DiagramController's Version content endpoint already uses, instead of
     * duplicating Version-reading logic here (see CONTEXT.md, "Share").
     */
    private function content(Share $share, Diagram $diagram, VersionRepository $versions): string
    {
        if ($share->isPinned()) {
            return $versions->get($diagram, $share->pinned_version_id);
        }

        return Storage::disk('diagrams')->get($diagram->disk_path);
    }
}
