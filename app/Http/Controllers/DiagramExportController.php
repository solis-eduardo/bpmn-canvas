<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDiagram;
use App\Repositories\DiagramRepository;
use App\Repositories\VersionRepository;
use App\Services\DiagramExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Server-side PNG/SVG/PDF export for signed-in Users who can open the
 * Diagram (issue #250).
 */
class DiagramExportController extends Controller
{
    use ResolvesVisibleDiagram;

    public function __construct(
        private readonly DiagramRepository $diagrams,
    ) {}

    public function show(Request $request, string $slug, string $format, DiagramExportService $exports, VersionRepository $versions): Response
    {
        $diagram = $this->resolveDiagramAllowingOldSlug($slug);

        $this->authorize('view', $diagram);

        $versionId = $request->query('version');
        abort_unless($versionId === null || is_string($versionId), 404);

        try {
            $content = $versionId === null
                ? $this->diagrams->contentOf($diagram)
                : $versions->get($diagram, $versionId);
        } catch (RuntimeException) {
            abort(404);
        }

        return $exports->download($diagram, $content, $versionId, $format);
    }
}
