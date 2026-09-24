<?php

namespace App\Repositories;

use App\Models\Diagrama;
use App\Models\DiagramaSlugAntigo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mantém a linha do Diagrama no banco e o arquivo `.bpmn` em disco
 * consistentes entre si. Nenhum termo de disco ou de transação deve
 * escapar desta classe: quem consome só enxerga "criar", "mover" e
 * "remover" um Diagrama.
 */
class DiagramaRepository
{
    /**
     * Cria a linha do Diagrama e escreve $conteudoInicial em disco. Se a
     * escrita falhar, a linha criada é desfeita (nunca fica um Diagrama sem
     * arquivo correspondente).
     *
     * @param  array<string, mixed>  $dados
     */
    public function criar(array $dados, string $conteudoInicial): Diagrama
    {
        $diagrama = Diagrama::create($dados);

        try {
            Storage::disk('diagrams')->put($diagrama->disk_path, $conteudoInicial);
        } catch (\Throwable $e) {
            $diagrama->delete();

            throw $e;
        }

        return $diagrama;
    }

    /**
     * Renomeia um Diagrama: move o arquivo em disco (se o Slug mudou),
     * grava o Slug antigo como redirecionamento permanente e atualiza a
     * linha, tudo dentro de uma transação. Se a transação falhar, o arquivo
     * já movido é devolvido ao lugar original.
     */
    public function mover(Diagrama $diagrama, string $novoTitulo, string $novoSlug): Diagrama
    {
        $slugAntigo = $diagrama->slug;
        $arquivo = basename($diagrama->disk_path);
        $slugMudou = $novoSlug !== $slugAntigo;

        if ($slugMudou) {
            Storage::disk('diagrams')->move($slugAntigo, $novoSlug);
        }

        try {
            DB::transaction(function () use ($diagrama, $slugAntigo, $novoSlug, $arquivo, $novoTitulo, $slugMudou) {
                if ($slugMudou) {
                    DiagramaSlugAntigo::create([
                        'diagrama_id' => $diagrama->id,
                        'slug_antigo' => $slugAntigo,
                    ]);
                }

                $diagrama->update([
                    'title' => $novoTitulo,
                    'slug' => $novoSlug,
                    'disk_path' => $novoSlug.'/'.$arquivo,
                ]);
            });
        } catch (\Throwable $e) {
            if ($slugMudou) {
                Storage::disk('diagrams')->move($novoSlug, $slugAntigo);
            }

            throw $e;
        }

        return $diagrama;
    }

    /**
     * Remove a linha do Diagrama e o diretório correspondente em disco.
     */
    public function remover(Diagrama $diagrama): void
    {
        $slug = $diagrama->slug;

        $diagrama->delete();

        Storage::disk('diagrams')->deleteDirectory($slug);
    }
}
