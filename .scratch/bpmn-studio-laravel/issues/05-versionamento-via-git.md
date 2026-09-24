# 05: Versionamento via Git

**What to build:** Cada save de um Diagrama gera uma Versão, guardada internamente via um repositório Git próprio do Diagrama (ver ADR-0004), mas exposta ao Usuário sem nenhum vocabulário de Git — só "Versão", "salvo em" e "restaurar". O histórico é listado agregado por dia; restaurar uma Versão antiga cria uma nova Versão no topo do histórico (estilo revert), sem apagar nada.

**Blocked by:** 02

**Status:** done

- [x] Cada save de um Diagrama gera uma nova Versão internamente (um commit no repositório Git próprio desse Diagrama), sem descartar saves intermediários.
- [x] Um Usuário consegue ver o histórico de Versões de um Diagrama, agrupado por dia de calendário.
- [x] Nenhuma tela ou resposta da aplicação expõe termos de Git (commit, branch, etc.) — só "Versão"/"salvo em"/"restaurar".
- [x] Um Usuário consegue restaurar uma Versão antiga; isso torna o conteúdo do Diagrama igual ao daquela Versão.
- [x] Restaurar uma Versão antiga cria uma nova Versão no topo do histórico (o histórico entre a versão restaurada e o momento do restore continua existindo e visível).
- [x] Testes de Feature cobrem: múltiplos saves gerando múltiplas Versões, listagem agregada por dia, e restauração (incluindo verificar que o histórico anterior ao restore não é apagado) — tudo observado pelas rotas HTTP, sem inspecionar o repositório Git diretamente no teste.

## Comments

Implementado via TDD (5 novos testes de Feature em `tests/Feature/VersaoDiagramaTest.php`, seam HTTP — nenhum teste inspeciona o repositório Git diretamente). Suíte final: 42 testes (37 pré-existentes + 5 novos), todos passando.

- **Motor**: `Symfony\Component\Process\Process` (já dependência do Laravel) executando o binário `git` do sistema, encapsulado em `App\Services\VersaoRepository` (`ensureRepositorio`, `commit`, `historico`, `conteudoDaVersao`, `restaurar`). Nenhum termo de Git escapa dessa classe: `DiagramaController` e as respostas JSON só falam em "versão"/"salvo em"/"restaurar" (`id` é o hash do commit, tratado como identificador opaco).
- **Repositório por Diagrama**: cada Diagrama tem seu `.git` interno em `storage/app/diagrams/{slug}/`, ao lado de `diagram.bpmn`. `ensureRepositorio()` é chamado tanto em `store()` quanto no início de `save()` (antes de sobrescrever o arquivo) — isso garante que o conteúdo que já estava em disco (inclusive quando o Diagrama foi criado por outro caminho que não passa por este serviço, como a `DiagramaFactory` usada nos testes, ou um futuro Fork) vire a Versão inicial, em vez de ser perdido antes do primeiro commit.
- **Identidade do commit**: autor/e-mail do Usuário autenticado, passados via `-c user.name=`/`-c user.email=` por invocação (não mexe no `.gitconfig` global do sistema/container).
- **Data do commit**: `GIT_AUTHOR_DATE`/`GIT_COMMITTER_DATE` são setados explicitamente a partir de `Carbon::now()` (em vez de deixar o `git` usar o relógio do sistema), para que a listagem/agregação por dia respeite `Carbon::setTestNow()`/`travelTo()` em teste e, em produção, para que a Versão reflita o instante da aplicação.
- **Commits vazios**: se o conteúdo salvo é idêntico ao da última Versão, `commit()` detecta via `git diff --cached --quiet` e **não cria uma nova Versão** (evita ruído de "saves" sem mudança real) — decisão consciente, documentada aqui. Exceção: a **restauração** (`restaurar()`) sempre cria uma nova Versão no topo (`--allow-empty`), mesmo se o conteúdo restaurado for igual ao atual, porque o critério de aceite exige que restaurar sempre produza uma nova Versão visível.
- **Endpoints**: `GET /diagramas/{slug}/versoes` (histórico agrupado por dia, campo `dia` = `Y-m-d`, `itens` com `id`/`salvo_em`/`autor`) e `POST /diagramas/{slug}/versoes/{versao}/restaurar`. `{versao}` é validado por regex de hash hexadecimal antes de qualquer chamada ao `git`, retornando 404 para identificadores inválidos ou inexistentes.
- **Ambiente de teste**: confirmado `git` disponível em `/usr/bin/git` no `PATH` deste ambiente (mesmo host que já usa `git` para o próprio projeto) — os testes rodam fora do Docker, então nenhuma mudança de imagem foi necessária para a suíte passar.
- **Pendência para produção (não bloqueante para este ticket)**: a imagem Docker do stage `app` (`Dockerfile`, `FROM php:8.4-fpm-alpine`) só roda `apk add --no-cache postgresql-dev` — **não tem o binário `git` instalado**. É necessário adicionar `apk add --no-cache git` a esse `RUN` antes do deploy em produção, senão `ensureRepositorio`/`commit`/`historico`/`restaurar` vão falhar em runtime (o container `app` é onde o Laravel roda os processos `git`).
- **Raio de mudança em arquivos compartilhados** (para o coordenador prever conflito de merge com os tickets 03/06/07, que também mexem em `DiagramaController.php`/`routes/web.php`): nenhum método existente foi reescrito por completo. `store()` ganhou 2 linhas (injeção de `VersaoRepository $versoes` no método e chamada `$versoes->ensureRepositorio(...)` dentro do `try` existente). `save()` ganhou o parâmetro `VersaoRepository $versoes` e 2 chamadas (`ensureRepositorio` antes do `put`, `commit` depois) — o corpo continua com a mesma validação e o mesmo `Storage::put`. Dois métodos novos foram adicionados ao final da classe: `versoes()` e `restaurarVersao()`. Em `routes/web.php`, duas linhas novas foram inseridas entre a rota de `rename`/`destroy` e a rota `show` (`GET .../versoes` e `POST .../versoes/{versao}/restaurar`); nenhuma rota existente foi movida ou alterada.

Integração pós-merge (com o ticket 06, mergeado antes deste): `versoes()` e `restaurarVersao()` não tinham `authorize()` — mesma brecha já corrigida em `content()`/`fork()` (qualquer Usuário autenticado conseguia ler/restaurar Versões de um Diagrama que não podia nem visualizar). Adicionado `authorize('view')` em `versoes()` e `authorize('update')` em `restaurarVersao()` (restaurar é uma mutação, mesmo nível de permissão de `save`). +1 teste de regressão (`VersaoDiagramaTest`) — suíte final: 66 testes.

Rodado `mattpocock-skills:code-review` (Standards + Spec) após essa integração. Achados corrigidos:
- **Standards**: `git diff --cached --quiet` só checava `isSuccessful()`, tratando qualquer erro real do git (exit code > 1) como "há mudança" silenciosamente — trocado por `match` explícito no exit code (0 = sem mudança, 1 = há mudança, outro = lança `ProcessFailedException`). Lógica duplicada de `git init` entre `ensureRepositorio()`/`commit()` extraída para `garantirGitInit()`. Adicionado timeout explícito (30s) em todos os processos `git` via factory `git()` privada (antes ficava no default implícito do Symfony Process).
- **Spec**: o comportamento de "não criar Versão para conteúdo idêntico" (dedup de commit vazio) não tinha teste cobrindo esse caminho — adicionado `test_saving_identical_content_does_not_create_a_new_version`. Também trocado `assertGreaterThanOrEqual` por `assertCount` exato no teste de múltiplos saves (era mais fraco que o necessário).

+2 testes — suíte final: 83 testes (contando também os tickets 06/07 mergeados entre essas revisões).
</content>
