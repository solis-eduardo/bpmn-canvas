Status: ready-for-agent

# BPMN Studio — Reconstrução em Laravel

## Problem Statement

O stack legado do BPMN Studio (nginx + WebDAV + Basic Auth, hoje em `legacy/`) atende à necessidade básica de editar e compartilhar diagramas BPMN sem exigir conta de quem recebe, mas tem limitações estruturais que impedem um uso colaborativo seguro:

- Um único usuário/senha compartilhado (Basic Auth) para todo mundo que edita, sem distinção de autoria.
- Links públicos permanentes e adivináveis — o nome do arquivo é a única "proteção" do link do Visualizador (`?d=processo.bpmn`).
- Nenhuma trava contra edição concorrente: "se duas pessoas salvarem o mesmo arquivo, vale o último" (perda silenciosa de trabalho).
- Nenhum histórico: cada save sobrescreve definitivamente o conteúdo anterior.

## Solution

Reconstruir o BPMN Studio como uma aplicação Laravel com contas de Usuário reais (via Keycloak), preservando a filosofia central do produto — Editor simples de diagramas BPMN, link público de leitura sem exigir conta de quem recebe — mas resolvendo as quatro lacunas acima: autoria e Colaboração por Diagrama, Compartilhamento assinado e com expiração, uma Trava de Edição exclusiva com liberação explícita e recuperação por Administrador ou Fork, e histórico de Versões restaurável (via Git, invisível ao usuário).

Ver `CONTEXT.md` para o glossário completo e `docs/adr/0001` a `0004` para as decisões arquiteturais que sustentam esta spec.

## User Stories

**Diagrama (CRUD e Slug)**

1. Como Usuário autenticado, quero criar um novo Diagrama, para começar a modelar um processo.
2. Como Autor, quero abrir o Editor do meu Diagrama, para continuar editando.
3. Como Autor, quero renomear meu Diagrama, para refletir mudanças no nome do processo sem perder o link antigo.
4. Como Usuário que acessa uma URL com um Slug antigo, quero ser redirecionado automaticamente para o Diagrama atual, para não receber um erro só porque o nome mudou.
5. Como Usuário que cria um Diagrama cujo nome geraria um Slug já reservado (mesmo que por outro Diagrama, atual ou renomeado), quero que o sistema anexe um sufixo alfanumérico automaticamente, para não ser bloqueado na criação.
6. Como Autor, quero excluir meu Diagrama, para remover processos que não são mais relevantes.
7. Como Usuário autenticado, quero ver a listagem de Diagramas aos quais tenho acesso (meus como Autor e aqueles onde sou Colaborador), para escolher em qual trabalhar.
8. Como Usuário navegando a listagem, quero ver quais Diagramas estão com Trava de Edição ativa no momento, para saber se alguém já está editando antes de tentar abrir.

**Colaboração**

9. Como Autor, quero convidar outro Usuário como Colaborador do meu Diagrama, para que ele possa editar comigo.
10. Como Usuário, quero solicitar acesso de Colaborador a um Diagrama que não é meu, para poder contribuir quando tiver motivo legítimo.
11. Como Autor, quero aprovar ou recusar uma solicitação de acesso de Colaborador, para manter controle sobre quem edita meu Diagrama.
12. Como Administrador, quero conceder acesso de Colaborador em qualquer Diagrama independentemente do Autor, para resolver bloqueios organizacionais sem depender da disponibilidade do Autor.
13. Como Autor, quero remover um Colaborador do meu Diagrama, para revogar acesso quando não for mais necessário.
14. Como Colaborador, quero abrir o Editor de um Diagrama que não é meu, para editá-lo com a mesma capacidade do Autor.

**Compartilhamento público**

15. Como Autor, quero gerar um link de Compartilhamento do meu Diagrama, para que pessoas sem conta possam visualizá-lo.
16. Como Autor, quero definir por quantos dias o link de Compartilhamento fica válido (padrão 7, máximo 60), para controlar por quanto tempo o acesso externo fica aberto.
17. Como Autor, quero permitir ou não download (PNG/SVG/BPMN) em cada link de Compartilhamento (desabilitado por padrão), para decidir se quem recebe o link também pode exportar o arquivo.
18. Como Autor, quero revogar um link de Compartilhamento antes da expiração, para cortar o acesso imediatamente se necessário.
19. Como Autor, quero ver todos os Compartilhamentos ativos do meu Diagrama, para saber quais links externos ainda estão abertos.
20. Como visitante com um link de Compartilhamento válido, quero visualizar o Diagrama sem precisar de login, para ver o processo sem precisar de conta.
21. Como visitante com um link de Compartilhamento expirado ou revogado, quero ver uma mensagem clara de que o link não é mais válido, para entender por que não consigo acessar.
22. Como visitante com um Compartilhamento que permite download, quero exportar o Diagrama em PNG/SVG/BPMN, para usá-lo fora do sistema.

**Trava de Edição**

23. Como Autor/Colaborador, quero que o Diagrama fique travado enquanto estou editando, podendo manter Travas em vários Diagramas, para evitar que outra pessoa sobrescreva meu trabalho sem perceber.
24. Como Usuário que tenta abrir o Editor de um Diagrama travado, quero ver quem detém a Trava, para saber com quem falar ou decidir esperar.
25. Como Usuário bloqueado por uma Trava, quero poder solicitar a Trava ao detentor atual, para pedir prioridade de edição.
26. Como detentor de uma Trava que recebe uma solicitação, quero ser avisado para decidir quando liberar explicitamente a Trava.
27. Como Usuário que solicitou uma Trava, quero continuar bloqueado até que o detentor ou um Administrador a libere explicitamente, para que o controle nunca seja transferido automaticamente.
28. Como Usuário bloqueado por uma Trava, quero poder criar um Fork do Diagrama, para continuar meu trabalho imediatamente sem esperar.
29. Como Administrador, quero poder forçar explicitamente a liberação de qualquer Trava, para resolver casos em que o detentor não está disponível.
30. Como Autor/Colaborador, quero liberar explicitamente a Trava quando terminar de editar; sair ou fechar o Editor não deve liberá-la.
31. Como Autor/Colaborador, quero que a Trava permaneça mesmo após inatividade, para que o controle não seja transferido sem minha ação.

**Versionamento**

32. Como Autor/Colaborador, quero que cada save gere uma nova Versão, para poder recuperar conteúdo anterior se precisar.
33. Como Usuário, quero ver o histórico de Versões de um Diagrama agrupado por dia, para navegar o histórico sem me perder em saves individuais.
34. Como Usuário, quero restaurar uma Versão antiga do Diagrama, para reverter uma mudança indesejada.
35. Como Usuário, quero que restaurar uma Versão crie uma nova Versão no topo do histórico (em vez de apagar o que veio depois), para nunca perder trabalho por engano.

**Contas e autenticação**

36. Como Administrador, quero criar contas de Usuário manualmente, para que a equipe consiga entrar no sistema mesmo sem integração com Keycloak ainda ativa.
37. Como Usuário, quero entrar no sistema com uma conta local criada por um Administrador, para acessar o BPMN Studio nesta fase.
38. *(Adiado/opcional — ticket independente, ver Implementation Decisions)* Como Usuário da organização, quero entrar no BPMN Studio com minha conta do Keycloak, para não precisar de outra senha, quando essa integração for ativada.
39. *(Adiado/opcional)* Como Usuário que acessa o sistema pela primeira vez via Keycloak, quero que minha conta seja criada automaticamente a partir do meu login, para não precisar de um cadastro manual.

## Implementation Decisions

**Diagrama e Slug**
- Tabela `diagramas`: `id`, `slug` (único), `title`, `author_id` (FK `users`), `disk_path`, `forked_from_id` (nullable, self-referencing, só metadado informativo), timestamps.
- Cada Diagrama vive em seu próprio diretório em `storage/app/diagrams/{slug}/`, contendo o arquivo `.bpmn` e seu próprio repositório Git (ver Versionamento). Renomear o Diagrama renomeia esse diretório.
- Tabela `diagrama_slugs_antigos`: `diagrama_id`, `slug_antigo` (único, nunca reaproveitado), `created_at`. Toda troca de nome insere uma linha aqui antes de atualizar `diagramas.slug`. Rotas que recebem um slug primeiro buscam em `diagramas.slug`; se não encontrarem, buscam em `diagrama_slugs_antigos` e redirecionam (HTTP redirect) para o slug atual.
- Geração de slug: normaliza o nome (acentos/espaços, como o legado já fazia); em caso de colisão com `diagramas.slug` ou `diagrama_slugs_antigos.slug_antigo`, anexa um sufixo alfanumérico curto e tenta de novo até ser único.

**Colaboração**
- Tabela `diagrama_colaboradores`: `diagrama_id`, `usuario_id`, `status` (`aprovado` | `solicitado`), `created_at`. Convite direto do Autor grava a linha já como `aprovado`. Solicitação do Usuário grava como `solicitado`; aprovação do Autor (ou concessão direta de um Administrador) atualiza para `aprovado`. Remover colaborador apaga a linha.
- Autorização de edição de um Diagrama = ser o `author_id`, ou ter linha em `diagrama_colaboradores` com `status = aprovado`.

**Compartilhamento**
- Tabela `compartilhamentos`: `id`, `diagrama_id`, `expires_at`, `allow_download` (boolean, default `false`), `revoked_at` (nullable), `created_by` (FK `users`), timestamps. `expires_at` é calculado na criação a partir de um número de dias informado (padrão 7, mínimo 1, máximo 60).
- O link público carrega um identificador assinado (Laravel signed URL) referenciando o `compartilhamentos.id`; o Visualizador Público valida assinatura + `expires_at` + `revoked_at IS NULL` antes de servir o conteúdo.

**Trava de Edição**
- Colunas em `diagramas`: `locked_by` (FK `users`, nullable), `locked_at`, `last_heartbeat_at`. É adquirida ao abrir o Editor, e um Usuário pode manter Travas em vários Diagramas. Navegar para outro Diagrama, sair ou fechar o Editor e ficar inativo não libera a Trava; somente o detentor pode liberá-la explicitamente. O heartbeat periódico mantém a comunicação com o Editor, mas não determina expiração.
- Uma solicitação de Trava notifica o detentor atual por polling. A solicitação não transfere a Trava automaticamente, nem após 30 segundos; quem solicitou continua bloqueado até a liberação explícita pelo detentor ou por um Administrador.
- Force-release por Administrador é explícito e limpa `locked_by`/`locked_at`/`last_heartbeat_at`, disponível a qualquer momento. Depois da liberação, outro Usuário autorizado pode abrir o Editor e adquirir a Trava.

**Fork**
- Criar Fork = criar um novo registro em `diagramas` (novo `slug`, `author_id` = quem forkou, `forked_from_id` = Diagrama original), copiando o conteúdo atual do `.bpmn` para um novo diretório com um novo repositório Git (histórico de Versões começa do zero — o Fork não herda o histórico do original).

**Versionamento (ver ADR-0004)**
- Cada Diagrama tem um repositório Git próprio em seu diretório. Um serviço (`VersaoRepository` ou nome equivalente) encapsula: commit ao salvar (autor do commit = Usuário autenticado), leitura do histórico (`git log`) agrupado por dia na camada de apresentação, e restauração (lê o conteúdo de um commit antigo via `git show`, escreve como conteúdo atual, comita de novo).
- Nenhum termo de Git aparece fora dessa camada — rotas, controllers e a UI falam só em "Versão", "salvo em", "restaurar".

**Autenticação e contas**
- **Fase atual (Keycloak adiado):** login local. Tabela `users` ganha `is_admin` (boolean, default `false`) e `keycloak_id` (nullable, único — coluna já prevista, mas não usada nesta fase). Contas são criadas exclusivamente por um Administrador (o primeiro Administrador é semeado via seeder). Papel de Administrador = `users.is_admin = true`.
- **Ticket independente e opcional (integração SSO):** login via Keycloak (OpenID Connect) usando um provedor Socialite, ativado quando o client for criado no Keycloak da organização. Primeiro login via Keycloak faz provisionamento just-in-time: se não existe `users` com aquele `keycloak_id`, cria um novo Usuário. Papel de Administrador passa a ser verdadeiro se a claim/role de admin estiver presente no token **ou** se `users.is_admin = true` — ou seja, essa integração se soma à autenticação local sem substituí-la (a criação manual por Administrador continua funcionando).

## Testing Decisions

- Seam único e mais alto possível: testes de Feature (`tests/Feature/`) usando o HTTP test client do Laravel, autenticando com `actingAs()` sobre Usuários de teste (sem depender de um Keycloak real em execução) e afirmando sobre respostas HTTP, estado no banco (sqlite in-memory, já configurado em `phpunit.xml`) e conteúdo em disco (`storage/app/diagrams`, incluindo o histórico Git, lido pelos próprios endpoints de listagem/restauração de Versão — não há necessidade de inspecionar o repositório Git diretamente no teste).
- Testes de tempo (expiração de Compartilhamento e permanência da Trava após inatividade e após 30 segundos) usam manipulação de tempo (`Carbon::setTestNow` / `travelTo`) em vez de `sleep` real.
- Bom teste = testa comportamento observável (o que a rota retorna, o que fica persistido), nunca como a implementação por dentro faz isso (não deve inspecionar commits Git diretamente, nem chamar métodos internos do serviço de Versão fora da rota HTTP).
- Prior art: o scaffold padrão do Laravel já está em `tests/Feature/ExampleTest.php` e `tests/Unit/ExampleTest.php` — seguir a mesma convenção de diretório/nomenclatura, substituindo pelos testes reais desta spec.
- Módulos a testar (cada um como um conjunto de testes de Feature): Diagrama (CRUD, slug/redirect, colisão de slug), Colaboração (convite, solicitação, aprovação, remoção, concessão por Admin), Compartilhamento (criação, expiração, revogação, `allow_download`), Trava de Edição (aquisição, liberação explícita, liberação por inatividade, solicitação com timeout de 30s, transferência manual, force-release por Admin), Fork, Versionamento (save gera Versão, listagem agregada por dia, restauração revert-style), Autenticação local (criação de conta por Admin, login, papel de Administrador via flag) e, no ticket independente, Autenticação via Keycloak (login simulado, provisionamento JIT, papel de Administrador via claim).

## Out of Scope

- **Ambiente de Trabalho / grupos**: previsto no `CONTEXT.md` como conceito futuro; nesta spec, cada Usuário permanece implicitamente seu próprio ambiente, sem compartilhamento automático.
- **Migração de dados do stack legado**: importar os `.bpmn` hoje em `legacy/` para o novo sistema não foi discutido durante o levantamento e não está coberto por esta spec — deve virar uma spec/ticket à parte se for necessário.
- **Merge entre Fork e Diagrama original**: um Fork é sempre independente; não há funcionalidade de mesclar de volta.
- **Design visual/UX das telas**: esta spec cobre comportamento e contratos, não layout ou fluxo de interface.
- **Notificações gerais do sistema**: só o aviso pontual do fluxo de solicitação de Trava está no escopo; um sistema de notificações mais amplo não foi discutido.

## Further Notes

- A frequência de polling da Trava de Edição (de quanto em quanto tempo o front-end verifica solicitações) fica a critério de quem implementar; a spec só fixa o timeout de 30 segundos para a transferência automática.
- A regra "convite direto do Autor concede acesso imediatamente, sem etapa de aceite pelo convidado" é uma inferência de síntese desta spec (não foi perguntada explicitamente durante o levantamento); é de baixo risco e fácil de reverter, mas vale confirmar durante o `/to-tickets` ou a implementação se isso importar para o time.
- Ver `CONTEXT.md` (glossário) e `docs/adr/0001-contas-reais-via-keycloak.md`, `0002-slug-interno-e-compartilhamento-externo.md`, `0003-trava-de-edicao-otimista.md`, `0004-versionamento-via-git-interno.md` para o histórico completo das decisões que sustentam esta spec.
</content>
