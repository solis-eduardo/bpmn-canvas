# 09: Atualizar README.md

**What to build:** O `README.md` da raiz do repositório passa a descrever a arquitetura atual (aplicação Laravel, contas de Usuário, Diagrama/Autor/Colaborador, Compartilhamento, Trava de Edição, Versionamento), em vez da descrição do stack legado (nginx + WebDAV + Basic Auth), que hoje induz quem abre o repositório ao erro.

**Blocked by:** None (recomenda-se fazer por último, para descrever o sistema já construído em vez de um plano ainda incompleto)

**Status:** done

- [x] O README descreve a stack atual (Laravel + Postgres + Keycloak opcional), não mais nginx/WebDAV como mecanismo principal.
- [x] O README usa o vocabulário do `CONTEXT.md` (Diagrama, Autor, Colaborador, Compartilhamento, Trava de Edição, Versão), sem termos do glossário antigo (ex.: "Owner", "Fluxo", "Token de compartilhamento").
- [x] O README explica como subir o ambiente (`docker compose up`) e aponta que a integração com Keycloak é opcional/adiada, com contas locais como caminho padrão nesta fase.
- [x] A menção ao stack legado (mantida por contexto histórico) deixa claro que ele está em `legacy/` e não é mais a aplicação em uso.
- [x] Instruções de uso (criar Diagrama, compartilhar, colaborar) refletem o comportamento real implementado (todos os 9 tickets já concluídos no momento desta atualização), não funcionalidades planejadas e ainda não construídas.

## Comments

README reescrito do zero (era a descrição do stack legado nginx+WebDAV). Cobre: stack (Laravel/Postgres/nginx, Diagrama com Git interno por slug), subida do ambiente com o seeder de Administrador, as duas formas de login (local padrão + Keycloak opcional), fluxo de uso ponta a ponta (criar → Editor/Trava → renomear → Colaboradores → Compartilhar → Versões), tabela de variáveis de ambiente principais, notas de produção, estrutura de diretórios atualizada, e uma seção curta apontando `legacy/` como histórico fora de uso.

Feito por último, após todos os tickets de funcionalidade (01–08) estarem implementados, mergeados e revisados — validado ponta a ponta contra a stack Docker real (não só a suíte de testes) antes de escrever, para garantir que as instruções descrevem comportamento real.
