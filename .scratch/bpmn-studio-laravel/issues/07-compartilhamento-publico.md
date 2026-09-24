# 07: Compartilhamento público

**What to build:** O Autor de um Diagrama gera um link de Compartilhamento público, assinado e com expiração em dias (padrão 7, máximo 60), com uma permissão de download própria (desabilitada por padrão). Um visitante sem conta consegue visualizar o Diagrama enquanto o link for válido, e exportar (PNG/SVG/BPMN) só se o download estiver habilitado. O Autor consegue ver e revogar seus Compartilhamentos ativos.

**Blocked by:** 02

**Status:** done

- [x] O Autor consegue criar um Compartilhamento de um Diagrama, informando o número de dias de validade (padrão 7 se não informado, mínimo 1, máximo 60) e se permite download.
- [x] O link gerado é assinado (não é só o nome/slug do Diagrama) e funciona sem login.
- [x] Um visitante com um Compartilhamento válido (não expirado, não revogado) visualiza o Diagrama em modo somente-leitura.
- [x] Um visitante com um Compartilhamento expirado ou revogado recebe uma mensagem clara de que o link não é mais válido, em vez de um erro genérico.
- [x] O visitante só consegue exportar (PNG/SVG/BPMN) se o Compartilhamento usado permitir download; caso contrário, a opção de exportar não fica disponível/nem é aceita pelo servidor.
- [x] O Autor consegue listar todos os Compartilhamentos ativos do seu Diagrama (com validade e permissão de download de cada um).
- [x] O Autor consegue revogar um Compartilhamento antes da expiração, invalidando o link imediatamente.
- [x] Um Diagrama pode ter múltiplos Compartilhamentos ativos simultaneamente, cada um com sua própria expiração e permissão de download.
- [x] Testes de Feature cobrem: criação com dias padrão/customizados, rejeição de mais de 60 dias, acesso de visitante válido, acesso expirado (via manipulação de tempo), acesso revogado, download permitido vs. bloqueado, listagem e revogação.

## Comments

Implementado via TDD (16 testes de Feature novos, seam HTTP + banco + disco, `tests/Feature/CompartilhamentoTest.php`; suíte total: 53 testes).

Modelo de dados: migration `create_compartilhamentos_table` (`diagrama_id`, `expires_at`, `allow_download` default `false`, `revoked_at` nullable, `created_by`), model `Compartilhamento` (`isValido()` = não revogado e não expirado) e relação `Diagrama::compartilhamentos()`.

Rotas novas (arquivos tocados minimamente, sem reestruturar nada existente):
- `routes/web.php`: 2 rotas públicas fora dos grupos `auth`/`guest`, num novo grupo `Route::middleware('signed')` (mesmo padrão das rotas `/login/keycloak*`, que também ficam fora desses grupos) — `GET /compartilhamentos/{compartilhamento}` (`compartilhamentos.show`) e `GET /compartilhamentos/{compartilhamento}/download/{formato}` (`compartilhamentos.download`); e 3 rotas autenticadas adicionadas ao grupo `auth` já existente, após a rota `diagramas.show`: `GET/POST /diagramas/{slug}/compartilhamentos` e `DELETE /compartilhamentos/{compartilhamento}`. Nenhuma rota existente foi alterada.
- `app/Models/Diagrama.php`: só adicionada a relação `compartilhamentos(): HasMany`. `DiagramaController` e `Diagrama::$fillable`/migration de `diagramas` não foram tocados.

Controllers novos (não reaproveitei `DiagramaController` para minimizar raio de mudança nesse arquivo compartilhado com os tickets 03/05/06):
- `CompartilhamentoController` (autor, autenticado): `index`/`store`/`destroy`. Autorização verificada diretamente via `$diagrama->author_id === $request->user()->id` (sem depender de Policy do ticket 06, conforme instruído), retornando 403 caso contrário.
- `CompartilhamentoPublicoController` (público, rota `signed`): `show` valida `isValido()` (expiração + revogação) além da assinatura HTTP já garantida pelo middleware, renderizando `resources/views/compartilhamentos/invalido.blade.php` com mensagem clara quando inválido; `download` também recusa (403) quando `allow_download = false` ou quando expirado/revogado, mesmo com assinatura válida — testado batendo direto na rota, não só escondendo o botão na view.

Link assinado: `URL::temporarySignedRoute()` referenciando `compartilhamentos.id`, com expiração da assinatura fixada bem no futuro (10 anos) — a validade de negócio real (`expires_at`/`revoked_at`) é responsabilidade do controller, não da assinatura, para poder mostrar a mensagem clara em vez do erro genérico de assinatura inválida do Laravel quando o Compartilhamento expira/é revogado.

Visualizador Público (`resources/views/compartilhamentos/show.blade.php` + `public/assets/compartilhamento-viewer.js`) adaptado de `legacy/web/view/index.html`/`viewer.js`: bpmn-js `NavigatedViewer`, conteúdo do `.bpmn` embutido no HTML (evita rota JSON pública extra), reaproveita `public/assets/common.js` (export PNG/SVG client-side, como no legado) e mostra o botão "Baixar" condicionalmente via Blade quando `allow_download`. Rota `download` serve o `.bpmn` cru (export de imagem seguindo 100% client-side, como no legado).
</content>
