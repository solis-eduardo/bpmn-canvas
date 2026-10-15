<?php

namespace App\Http\Controllers;

use App\Models\Share;
use App\Rendering\ExportFormat;
use App\Rendering\RenderingFailedException;
use App\Repositories\VersionRepository;
use App\Services\DiagramExportService;
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

        return view('shares.show', [
            'diagram' => $diagram,
            'diagramXml' => $diagramXml,
            'downloadUrls' => $share->downloadUrls(),
        ]);
    }

    public function download(Share $share, string $format, VersionRepository $versions, DiagramExportService $exports): Response
    {
        abort_unless($share->isValid(), 403);
        abort_unless($share->allow_download, 403);

        $diagram = $share->diagram;
        $content = $share->content($versions);

        if ($format === 'bpmn') {
            return $exports->exportBpmn($diagram, $content);
        }

        $exportFormat = ExportFormat::tryFrom($format) ?? abort(404);

        try {
            return $exports->export($diagram, $content, $share->pinned_version_id, $exportFormat);
        } catch (RenderingFailedException $e) {
            report($e);
            abort(503, __('messages.export_unavailable'));
        }
    }
}
