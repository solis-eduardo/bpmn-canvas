# 02: CRUD de Diagrama + Slug com redirect

**What to build:** Um Usuário autenticado cria, lista, abre o Editor, salva, renomeia e exclui um Diagrama. Renomear preserva o acesso pelo Slug antigo via redirecionamento; um Slug colidindo com um já reservado nunca bloqueia a criação. Nesta etapa não existe ainda Trava de Edição, Versionamento, Colaboração nem Compartilhamento — salvar apenas sobrescreve o conteúdo atual do Diagrama.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] Usuário autenticado cria um novo Diagrama informando um nome; um Slug é derivado desse nome.
- [ ] Usuário autenticado vê uma listagem dos Diagramas aos quais tem acesso (nesta etapa, todo Usuário autenticado vê todos — Colaboração/permissão granular chega no ticket 06).
- [ ] Usuário abre o Editor de um Diagrama existente pelo Slug e consegue ver/alterar seu conteúdo.
- [ ] Salvar o Diagrama grava o novo conteúdo, sobrescrevendo o anterior.
- [ ] Renomear um Diagrama atualiza o Slug atual, mas o Slug anterior continua resolvendo (redirecionamento) para o Diagrama, mesmo depois de múltiplas renomeações em sequência.
- [ ] Um Slug antigo, uma vez liberado por uma renomeação, nunca é reaproveitado por outro Diagrama diferente.
- [ ] Criar ou renomear um Diagrama para um nome cujo Slug colidiria com um já existente (atual ou anterior) gera automaticamente um Slug alternativo (sufixo), sem bloquear a operação.
- [ ] Excluir um Diagrama remove seu acesso via Editor e via qualquer Slug (atual ou antigo) associado a ele.
- [ ] Testes de Feature cobrem: criação, listagem, abertura, salvamento, renomeação com redirect, colisão de slug, exclusão.
</content>
