<?php

namespace App\Http\Controllers;

use App\Models\Share;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicShareController extends Controller
{
    public function show(Share $share): View
    {
        if (! $share->isValid()) {
            return view('shares.invalid');
        }

        $diagram = $share->diagram;
        $diagramXml = Storage::disk('diagrams')->get($diagram->disk_path);

        return view('shares.show', compact('share', 'diagram', 'diagramXml'));
    }

    public function download(Share $share, string $format): Response
    {
        abort_unless($share->isValid(), 403);
        abort_unless($share->allow_download, 403);
        abort_unless($format === 'bpmn', 404);

        $diagram = $share->diagram;
        $content = Storage::disk('diagrams')->get($diagram->disk_path);

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$diagram->slug.'.bpmn"',
        ]);
    }
}
