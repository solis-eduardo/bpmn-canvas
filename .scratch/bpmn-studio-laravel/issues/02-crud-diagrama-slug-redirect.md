# 02: CRUD de Diagrama + Slug com redirect

**What to build:** Um Usuário autenticado cria, lista, abre o Editor, salva, renomeia e exclui um Diagrama. Renomear preserva o acesso pelo Slug antigo via redirecionamento; um Slug colidindo com um já reservado nunca bloqueia a criação. Nesta etapa não existe ainda Trava de Edição, Versionamento, Colaboração nem Compartilhamento — salvar apenas sobrescreve o conteúdo atual do Diagrama.

**Blocked by:** 01

**Status:** done

- [x] Usuário autenticado cria um novo Diagrama informando um nome; um Slug é derivado desse nome.
- [x] Usuário autenticado vê uma listagem dos Diagramas aos quais tem acesso (nesta etapa, todo Usuário autenticado vê todos — Colaboração/permissão granular chega no ticket 06).
- [x] Usuário abre o Editor de um Diagrama existente pelo Slug e consegue ver/alterar seu conteúdo.
- [x] Salvar o Diagrama grava o novo conteúdo, sobrescrevendo o anterior.
- [x] Renomear um Diagrama atualiza o Slug atual, mas o Slug anterior continua resolvendo (redirecionamento) para o Diagrama, mesmo depois de múltiplas renomeações em sequência.
- [x] Um Slug antigo, uma vez liberado por uma renomeação, nunca é reaproveitado por outro Diagrama diferente.
- [x] Criar ou renomear um Diagrama para um nome cujo Slug colidiria com um já existente (atual ou anterior) gera automaticamente um Slug alternativo (sufixo), sem bloquear a operação.
- [x] Excluir um Diagrama remove seu acesso via Editor e via qualquer Slug (atual ou antigo) associado a ele.
- [x] Testes de Feature cobrem: criação, listagem, abertura, salvamento, renomeação com redirect, colisão de slug, exclusão.

## Comments

Implementado via TDD (12 testes de Feature novos em `tests/Feature/DiagramaTest.php`, seam HTTP + banco + disco fake via `Storage::fake('diagrams')`), sem quebrar os 14 testes do ticket 01 (suíte completa: 26 passando).

Principais arquivos:
- Migrations `create_diagramas_table` e `create_diagrama_slugs_antigos_table` (tipos genéricos, compatíveis com sqlite/Postgres).
- `app/Models/Diagrama.php`: relações `author()`, `forkedFrom()`, `slugsAntigos()`; método estático `uniqueSlug()` que resolve colisão contra `diagramas.slug` (ignorando o próprio registro quando é renomeação) e `diagrama_slugs_antigos.slug_antigo`, anexando sufixo alfanumérico até ficar único; constante `EMPTY_BPMN` com o template mínimo (mesmo usado no legado).
- `app/Models/DiagramaSlugAntigo.php`: `$timestamps = false` (só `created_at`, com `useCurrent()` no schema); `$table` setado explicitamente porque o pluralizador do Eloquent inferia `diagrama_slug_antigos` (errado) a partir do nome da classe.
- `app/Support/Slugger.php`: normalização do slug (ascii/minúsculas/`[a-z0-9-]`), no espírito do slugify legado.
- `app/Http/Controllers/DiagramaController.php`: `index`, `store`, `show`, `content`, `save`, `rename`, `destroy`. Um helper privado `resolve($slug)` busca primeiro em `diagramas.slug`; se não achar, em `diagrama_slugs_antigos.slug_antigo`; se achar por lá, retorna a flag `$isOldSlug=true`. Só a rota `show` (abrir o Editor) faz o redirect HTTP nesse caso — as rotas de API (`conteudo`, `save`, `rename`, `destroy`) resolvem o Diagrama silenciosamente por qualquer slug, atual ou antigo, sem redirecionar (não fazia sentido redirecionar uma chamada `fetch`).
- Rotas em `routes/web.php` dentro do grupo `auth` existente.
- Disco `diagrams` configurado em `config/filesystems.php` apontando para `storage_path('app/diagrams')` (mesmo caminho já montado pelo `docker-compose.yml`).
- Renomear usa `Storage::disk('diagrams')->move($oldSlug, $newSlug)` para mover o diretório inteiro (funciona porque o adapter local usa `rename()` do PHP, que move diretórios); o nome do arquivo interno (`diagram.bpmn`) é preservado, só o diretório muda.
- Excluir é hard delete (`$diagrama->delete()`); a FK de `diagrama_slugs_antigos.diagrama_id` tem `cascadeOnDelete()`, então os slugs antigos somem junto — não há Compartilhamento/Colaboração dependente ainda, então não havia motivo para soft delete.
- View mínima do Editor em `resources/views/diagramas/show.blade.php`, adaptada de `legacy/web/editor/index.html`, e `public/assets/editor.js`, adaptado de `legacy/web/editor/editor.js`, trocando o fetch direto ao WebDAV por chamadas com sessão Laravel (`credentials: 'same-origin'`, header `X-CSRF-TOKEN` lido da meta tag). `public/assets/common.js`/`app.css` copiados do legado sem alteração.

Decisões não 100% explícitas no ticket/spec, para revisão:
1. **Redirect só na rota de abrir o Editor** (`show`), não nas rotas de conteúdo/salvar/renomear/excluir — essas resolvem o slug antigo silenciosamente (sem 3xx) porque são chamadas `fetch`/JSON feitas a partir de uma página já carregada no slug atual. O ticket só menciona redirect para "abrir o Editor".
2. **Verificação de colisão de slug é global**, não restrita a "outro" Diagrama: um Diagrama não pode reaproveitar nem seu **próprio** slug antigo (só o CONTEXT.md/ticket garantem que não é reaproveitado por "outro" Diagrama; optei pela regra mais forte/simples — nunca reaproveitar nenhum slug antigo, de ninguém — por ser mais simples de implementar e testar, e por ser um superconjunto seguro da regra pedida).
3. **`disk_path` armazena o caminho completo dentro do disco** (`{slug}/diagram.bpmn`), não um caminho absoluto do filesystem — interpretei "caminho completo" como "caminho completo relativo ao disco `diagrams`" (diretório + nome do arquivo), já que um caminho absoluto do container não faria sentido guardado no banco.
4. **Nome do arquivo interno é sempre `diagram.bpmn`** (fixo), tanto na criação quanto preservado nas renomeações — a spec não especifica um nome, só que ele é preservado ao renomear.
5. **Renomear é feito via `PUT /diagramas/{slug}` retornando JSON** (chamado via fetch a partir do campo de nome no Editor, no `change` do input), em vez de um form HTML tradicional — para manter o padrão fetch+CSRF+sessão pedido para a página do Editor. Já a criação (`POST /diagramas`) e exclusão continuam com request comuns (form/redirect) e JSON respectivamente, únicas rotas onde a interface (listagem vs. Editor) pedia um ou outro.
6. **Listagem (`GET /diagramas`) é uma view Blade simples** com link para cada Diagrama e um form de criação — não há CSS/UX além do herdado do legado (`app.css`), conforme "Design visual/UX das telas" está fora de escopo.
</content>
