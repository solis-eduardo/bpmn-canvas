<?php

namespace App\Repositories;

use App\Models\Diagrama;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Guarda o histórico de Versões de um Diagrama usando um repositório Git
 * próprio por Diagrama, no mesmo diretório onde o arquivo `.bpmn` vive
 * (ver ADR-0004). Nenhum termo de Git deve escapar desta classe: a
 * interface fala só em "Versão" — inicializar, salvar, listar, obter,
 * restaurar.
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
    public function inicializar(Diagrama $diagrama, User $autor): void
    {
        $diretorio = $this->diretorio($diagrama);

        if ($this->garantirGitInit($diretorio)) {
            return;
        }

        if (Storage::disk('diagrams')->exists($diagrama->disk_path)) {
            $this->salvar($diagrama, $autor, self::MENSAGEM_INICIAL);
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
    public function salvar(Diagrama $diagrama, User $autor, ?string $mensagem = null, bool $permitirVazio = false): ?string
    {
        $diretorio = $this->diretorio($diagrama);
        $arquivo = $this->arquivo($diagrama);

        $this->garantirGitInit($diretorio);

        $this->git(['git', 'add', '--', $arquivo], $diretorio)->mustRun();

        $diff = $this->git(['git', 'diff', '--cached', '--quiet'], $diretorio);
        $diff->run();

        $temMudanca = match ($diff->getExitCode()) {
            0 => false,
            1 => true,
            default => throw new ProcessFailedException($diff),
        };

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

        $this->git($args, $diretorio, [
            'GIT_AUTHOR_DATE' => $agora,
            'GIT_COMMITTER_DATE' => $agora,
        ])->mustRun();

        return $this->versaoAtual($diagrama);
    }

    /**
     * Histórico de Versões, mais recente primeiro. A agregação por dia é
     * responsabilidade de quem exibe (controller/view) — aqui cada item já
     * traz a data completa para isso.
     *
     * @return array<int, array{id: string, salvo_em: Carbon, autor: string}>
     */
    public function listar(Diagrama $diagrama): array
    {
        $diretorio = $this->diretorio($diagrama);

        if (! is_dir($diretorio.'/.git')) {
            return [];
        }

        $process = $this->git(['git', 'log', '--pretty=format:%H%x09%cI%x09%an'], $diretorio);
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
    public function obter(Diagrama $diagrama, string $versao): string
    {
        $this->validarIdentificador($versao);

        $process = $this->git(['git', 'show', $versao.':'.$this->arquivo($diagrama)], $this->diretorio($diagrama));
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
        $conteudo = $this->obter($diagrama, $versao);

        Storage::disk('diagrams')->put($diagrama->disk_path, $conteudo);

        $this->salvar($diagrama, $autor, self::MENSAGEM_RESTAURACAO, permitirVazio: true);
    }

    /**
     * @return bool true se o repositório já existia (nada a fazer); false se
     *              acabou de ser inicializado agora.
     */
    private function garantirGitInit(string $diretorio): bool
    {
        if (is_dir($diretorio.'/.git')) {
            return true;
        }

        $this->git(['git', 'init', '--quiet'], $diretorio)->mustRun();

        return false;
    }

    private function versaoAtual(Diagrama $diagrama): string
    {
        $process = $this->git(['git', 'rev-parse', 'HEAD'], $this->diretorio($diagrama));
        $process->mustRun();

        return trim($process->getOutput());
    }

    /**
     * @param  array<string, string>  $env
     */
    private function git(array $args, string $cwd, array $env = []): Process
    {
        // Nunca confia em descoberta implícita de cwd: se o diretório não
        // existir de verdade neste instante, falha alto em vez de deixar o
        // processo filho cair para o cwd do PHP (ver diretorio()).
        if (! is_dir($cwd)) {
            throw new \RuntimeException("Diretório de repositório de Versões inexistente: {$cwd}");
        }

        $process = new Process($args, $cwd, $env ?: null);
        $process->setTimeout(30);

        return $process;
    }

    private function validarIdentificador(string $versao): void
    {
        if (! preg_match('/^[0-9a-f]{4,40}$/i', $versao)) {
            throw new \RuntimeException('Identificador de versão inválido.');
        }
    }

    private function diretorio(Diagrama $diagrama): string
    {
        $relativo = dirname($diagrama->disk_path);

        // Garante que o diretório existe ANTES de qualquer comando git rodar
        // nele. Sem isso, se o diretório não existir (ex.: disk_path mal
        // formado, ou o arquivo ainda não foi gravado), o proc_open do PHP
        // cai silenciosamente para o cwd do processo pai — que ao rodar via
        // `php artisan test`/`artisan serve` é a raiz do projeto, fazendo o
        // `git commit` acontecer no repositório do PROJETO em vez do
        // repositório isolado do Diagrama (incidente real: ver histórico).
        Storage::disk('diagrams')->makeDirectory($relativo);

        $caminho = Storage::disk('diagrams')->path($relativo);

        if (! is_dir($caminho)) {
            throw new \RuntimeException("Não foi possível preparar o diretório de Versões: {$caminho}");
        }

        return $caminho;
    }

    private function arquivo(Diagrama $diagrama): string
    {
        return basename($diagrama->disk_path);
    }
}
