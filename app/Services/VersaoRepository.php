<?php

namespace App\Services;

use App\Models\Diagrama;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Guarda o histórico de Versões de um Diagrama usando um repositório Git
 * próprio por Diagrama, no mesmo diretório onde o arquivo `.bpmn` vive
 * (ver ADR-0004). Nenhum termo de Git deve escapar desta classe: quem
 * consome este serviço só enxerga "Versão", "salva em" e "restaurar".
 */
class VersaoRepository
{
    private const MENSAGEM_PADRAO = 'Versão salva';

    private const MENSAGEM_INICIAL = 'Versão inicial';

    private const MENSAGEM_RESTAURACAO = 'Restauração de versão anterior';

    /**
     * Garante que o Diagrama tem um repositório de Versões. Se ainda não
     * existir, inicializa e registra o conteúdo atualmente em disco como a
     * primeira Versão — chamado antes de sobrescrever o arquivo em um save,
     * para nunca perder o conteúdo que já estava lá (criado via CRUD do
     * ticket 02, ou via qualquer outro caminho que não passe por este
     * serviço, como um Fork).
     */
    public function ensureRepositorio(Diagrama $diagrama, User $autor): void
    {
        $diretorio = $this->diretorio($diagrama);

        if (is_dir($diretorio.'/.git')) {
            return;
        }

        (new Process(['git', 'init', '--quiet'], $diretorio))->mustRun();

        if (Storage::disk('diagrams')->exists($diagrama->disk_path)) {
            $this->commit($diagrama, $autor, self::MENSAGEM_INICIAL);
        }
    }

    /**
     * Registra uma nova Versão a partir do conteúdo atual em disco. Se o
     * conteúdo for idêntico ao da última Versão, nenhuma Versão nova é
     * criada (evita Versões vazias em saves repetidos) — a menos que
     * $permitirVazio seja true (usado pela restauração, que sempre deve
     * criar uma nova Versão no topo do histórico).
     *
     * @return string|null Identificador opaco da Versão criada, ou null se
     *                      nada mudou e nenhuma Versão foi criada.
     */
    public function commit(Diagrama $diagrama, User $autor, ?string $mensagem = null, bool $permitirVazio = false): ?string
    {
        $diretorio = $this->diretorio($diagrama);
        $arquivo = $this->arquivo($diagrama);

        if (! is_dir($diretorio.'/.git')) {
            (new Process(['git', 'init', '--quiet'], $diretorio))->mustRun();
        }

        (new Process(['git', 'add', '--', $arquivo], $diretorio))->mustRun();

        $diff = new Process(['git', 'diff', '--cached', '--quiet'], $diretorio);
        $diff->run();
        $temMudanca = ! $diff->isSuccessful();

        if (! $temMudanca && ! $permitirVazio) {
            return null;
        }

        $args = [
            'git',
            '-c', 'user.name='.$autor->name,
            '-c', 'user.email='.$autor->email,
            'commit',
            '-m', $mensagem ?? self::MENSAGEM_PADRAO,
        ];

        if (! $temMudanca) {
            $args[] = '--allow-empty';
        }

        // Usa o relógio da aplicação (respeita Carbon::setTestNow em testes)
        // em vez do relógio do sistema onde o processo `git` roda.
        $agora = Carbon::now()->toIso8601String();

        $processo = new Process($args, $diretorio, [
            'GIT_AUTHOR_DATE' => $agora,
            'GIT_COMMITTER_DATE' => $agora,
        ]);
        $processo->mustRun();

        return $this->versaoAtual($diagrama);
    }

    /**
     * Histórico de Versões, mais recente primeiro. A agregação por dia é
     * responsabilidade de quem exibe (controller/view) — aqui cada item já
     * traz a data completa para isso.
     *
     * @return array<int, array{id: string, salvo_em: Carbon, autor: string}>
     */
    public function historico(Diagrama $diagrama): array
    {
        $diretorio = $this->diretorio($diagrama);

        if (! is_dir($diretorio.'/.git')) {
            return [];
        }

        $process = new Process(['git', 'log', '--pretty=format:%H%x09%cI%x09%an'], $diretorio);
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $saida = trim($process->getOutput());

        if ($saida === '') {
            return [];
        }

        return array_map(function (string $linha) {
            [$id, $data, $autor] = explode("\t", $linha);

            return [
                'id' => $id,
                'salvo_em' => Carbon::parse($data),
                'autor' => $autor,
            ];
        }, explode("\n", $saida));
    }

    /**
     * Conteúdo do Diagrama tal como estava em uma Versão específica.
     */
    public function conteudoDaVersao(Diagrama $diagrama, string $versao): string
    {
        $this->validarIdentificador($versao);

        $process = new Process(['git', 'show', $versao.':'.$this->arquivo($diagrama)], $this->diretorio($diagrama));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Versão não encontrada.', previous: new ProcessFailedException($process));
        }

        return $process->getOutput();
    }

    /**
     * Restaura o conteúdo de uma Versão antiga: lê aquele conteúdo, escreve
     * como conteúdo atual do arquivo e cria uma nova Versão no topo do
     * histórico (estilo "revert"). Nunca apaga Versões intermediárias.
     */
    public function restaurar(Diagrama $diagrama, string $versao, User $autor): void
    {
        $conteudo = $this->conteudoDaVersao($diagrama, $versao);

        Storage::disk('diagrams')->put($diagrama->disk_path, $conteudo);

        $this->commit($diagrama, $autor, self::MENSAGEM_RESTAURACAO, permitirVazio: true);
    }

    private function versaoAtual(Diagrama $diagrama): string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], $this->diretorio($diagrama));
        $process->mustRun();

        return trim($process->getOutput());
    }

    private function validarIdentificador(string $versao): void
    {
        if (! preg_match('/^[0-9a-f]{4,40}$/i', $versao)) {
            throw new \RuntimeException('Identificador de versão inválido.');
        }
    }

    private function diretorio(Diagrama $diagrama): string
    {
        return Storage::disk('diagrams')->path(dirname($diagrama->disk_path));
    }

    private function arquivo(Diagrama $diagrama): string
    {
        return basename($diagrama->disk_path);
    }
}
