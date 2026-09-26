<?php

namespace App\Http\Controllers;

use App\Models\Share;
use App\Repositories\VersionRepository;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicShareController extends Controller
{
    public function show(Share $share, VersionRepository $versions): View
    {
        if (! $share->isValid()) {
            return view('shares.invalid');
        }

        $diagram = $share->diagram;
        $diagramXml = $share->content($versions);

        return view('shares.show', compact('share', 'diagram', 'diagramXml'));
    }

    public function download(Share $share, string $format, VersionRepository $versions): Response
    {
        abort_unless($share->isValid(), 403);
        abort_unless($share->allow_download, 403);
        abort_unless($format === 'bpmn', 404);

        $diagram = $share->diagram;
        $content = $share->content($versions);

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$diagram->slug.'.bpmn"',
        ]);
    }
}
