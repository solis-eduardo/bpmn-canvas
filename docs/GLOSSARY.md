# Glossary / Thesaurus

Canonical PT-BR → English term mapping for BPMN Studio. This is the single
source of truth for renaming code, database schema, routes, and translating
docs — every rename in the codebase follows this table. Definitions are kept
short here; full domain definitions live in `CONTEXT.md`.

## Domain terms

| PT-BR | English | Notes |
|---|---|---|
| Diagrama | Diagram | Core entity: a BPMN 2.0 process. |
| Slug | Slug | Unchanged — already an English/technical term. |
| Slug antigo | Old slug | Reserved, permanently redirecting former slug. |
| Autor | Author | The owning user of a Diagram. |
| Colaborador | Collaborator | User granted edit access to a Diagram they don't own. |
| Administrador | Administrator / Admin | Elevated role, can grant Collaborators on any Diagram. |
| Compartilhamento | Share | Public, signed, expiring read-only link. |
| Editor | Editor | Unchanged — the authenticated editing interface. |
| Visualizador Público | Public Viewer | Read-only interface for Share links. |
| Trava (de edição) | Editing Lock | Exclusive edit state on a Diagram; held until explicitly released by its holder or force-released by an Administrator. |
| Solicitar (Trava) | Request (a Lock) | |
| Conceder | Grant | |
| Liberar (Trava) | Release (a Lock) | |
| Fork | Fork | Unchanged. |
| Versão | Version | Historical snapshot of a Diagram's content, backed by Git. |
| Restaurar (Versão) | Restore (a Version) | |
| Usuário | User | Authenticated account (local or Keycloak SSO). |
| Ambiente de Trabalho | Workspace | Group of Users (Members) holding Diagrams; hides them from outsiders. |
| Dono do Diagrama | Diagram Owner | Transferable; holds the powers that used to belong to the Author. Never plain "Owner". |
| Dono do Ambiente | Workspace Owner | Manages a Workspace; transferable. Never plain "Owner". |
| Membro | Member | User belonging to a Workspace — not a Collaborator. |
| Tag | Tag | Global marker for organizing Diagrams. |
| Nome da Versão | Version name | Defaults to "Version N"; renamable. |

## Code naming

### Tables / columns

| PT-BR | English |
|---|---|
| `diagramas` | `diagrams` |
| `diagrama_slugs_antigos` | `diagram_old_slugs` |
| `diagrama_colaboradores` | `collaborators` |
| `compartilhamentos` | `shares` |
| `slug_antigo` | `old_slug` |
| `usuario_id` | `user_id` |
| `diagrama_id` (FK, appears in 3 migrations) | `diagram_id` |
| `STATUS_APROVADO` / `STATUS_SOLICITADO` | `STATUS_APPROVED` / `STATUS_REQUESTED` |

**Laravel convention check** (reviewed against migrations in `database/migrations/`): all four renamed tables are plural snake_case matching their singular-StudlyCase models (`diagrams`↔`Diagram`, `shares`↔`Share`, `diagram_old_slugs`↔`DiagramOldSlug`, `collaborators`↔`Collaborator`), foreign keys follow `{singular}_id` (`diagram_id`, `user_id`, `author_id`), and audit-style columns correctly use the `_by`/`_at` suffix convention (`created_by`, `locked_by`, `locked_at`, `lock_requested_by`, `lock_requested_at`) rather than the FK suffix — no changes needed there. `collaborators` is not a bare `belongsToMany` pivot (it has its own model, a `status` column, and no `updated_at`), so the plural table name is correct Eloquent convention, not the alphabetical-singular pivot pattern (`role_user`-style) that only applies to pivot tables without a dedicated model. Per ADR-0012, `Collaborator` carries no aggregate prefix since the concept is unambiguous (unlike `Diagram`/`Workspace` `Owner`).

### Classes

| PT-BR | English |
|---|---|
| `Diagrama` (Model) | `Diagram` |
| `DiagramaSlugAntigo` (Model) | `DiagramOldSlug` |
| `DiagramaColaborador` (Model) | `Collaborator` |
| `Compartilhamento` (Model) | `Share` |
| `DiagramaRepository` | `DiagramRepository` |
| `VersaoRepository` | `VersionRepository` |
| `DiagramaPolicy` | `DiagramPolicy` |
| `DiagramaController` | `DiagramController` |
| `DiagramaColaboradorController` | `DiagramCollaboratorController` |
| `CompartilhamentoController` | `ShareController` |
| `CompartilhamentoPublicoController` | `PublicShareController` |
| `Actions\Diagramas\ConcederOuSolicitarColaborador` | `Actions\Diagrams\GrantOrRequestCollaborator` |
| `Actions\Diagramas\LiberarTrava` | `Actions\Diagrams\ReleaseLock` |
| `Actions\Diagramas\SolicitarTrava` | `Actions\Diagrams\RequestLock` |
| `DiagramaFactory` | `DiagramFactory` |
| `CompartilhamentoFactory` | `ShareFactory` |
| `ColaboracaoTest` | `CollaborationTest` |
| `CompartilhamentoTest` | `ShareTest` |
| `DiagramaTest` | `DiagramTest` |
| `TravaEdicaoTest` | `EditingLockTest` |
| `VersaoDiagramaTest` | `DiagramVersionTest` |

### Methods

| PT-BR | English |
|---|---|
| `criar` | `create` |
| `mover` | `move` |
| `remover` | `remove` |
| `inicializar` | `initialize` |
| `salvar` | `save` |
| `listar` | `list` |
| `obter` | `get` |
| `restaurar` | `restore` |
| `commitar` | `commit` |
| `diretorio` | `directory` |
| `arquivo` | `file` |
| `versaoAtual` | `currentVersion` |
| `garantirGitInit` | `ensureGitInit` |
| `validarIdentificador` | `validateIdentifier` |
| `manageColaboradores` | `manageCollaborators` |
| `manageCompartilhamentos` | `manageShares` |
| `podeEditar` | `canEdit` |
| `usuario()` (relation) | `user()` |
| `autor()` (relation) | `author()` |
| `isValido()` | `isValid()` |
| `solicitarTrava()` | `requestLock()` |
| `restaurarVersao()` | `restoreVersion()` |
| `versoes()` | `versions()` |

### Routes

| PT-BR | English |
|---|---|
| `/diagramas` | `/diagrams` |
| `/diagramas/{slug}/conteudo` | `/diagrams/{slug}/content` |
| `/diagramas/{slug}/colaboradores` | `/diagrams/{slug}/collaborators` |
| `/diagramas/{slug}/trava/solicitar` | `/diagrams/{slug}/lock/request` |
| `/diagramas/{slug}/versoes` | `/diagrams/{slug}/versions` |
| `/diagramas/{slug}/versoes/{versao}/restaurar` | `/diagrams/{slug}/versions/{version}/restore` |
| `/compartilhamentos/{compartilhamento}` | `/shares/{share}` |
| route names `diagramas.*` | `diagrams.*` |
| route names `compartilhamentos.*` | `shares.*` |
