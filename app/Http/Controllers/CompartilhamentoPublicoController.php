<?php

namespace App\Http\Controllers;

use App\Models\Compartilhamento;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompartilhamentoPublicoController extends Controller
{
    public function show(Compartilhamento $compartilhamento): View
    {
        if (! $compartilhamento->isValido()) {
            return view('compartilhamentos.invalido');
        }

        $diagrama = $compartilhamento->diagrama;
        $diagramaXml = Storage::disk('diagrams')->get($diagrama->disk_path);

        return view('compartilhamentos.show', compact('compartilhamento', 'diagrama', 'diagramaXml'));
    }

    public function download(Compartilhamento $compartilhamento, string $formato): Response
    {
        abort_unless($compartilhamento->isValido(), 403);
        abort_unless($compartilhamento->allow_download, 403);
        abort_unless($formato === 'bpmn', 404);

        $diagrama = $compartilhamento->diagrama;
        $content = Storage::disk('diagrams')->get($diagrama->disk_path);

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$diagrama->slug.'.bpmn"',
        ]);
    }
}
