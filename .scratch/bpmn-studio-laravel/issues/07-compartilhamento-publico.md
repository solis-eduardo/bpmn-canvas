# 07: Compartilhamento público

**What to build:** O Autor de um Diagrama gera um link de Compartilhamento público, assinado e com expiração em dias (padrão 7, máximo 60), com uma permissão de download própria (desabilitada por padrão). Um visitante sem conta consegue visualizar o Diagrama enquanto o link for válido, e exportar (PNG/SVG/BPMN) só se o download estiver habilitado. O Autor consegue ver e revogar seus Compartilhamentos ativos.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] O Autor consegue criar um Compartilhamento de um Diagrama, informando o número de dias de validade (padrão 7 se não informado, mínimo 1, máximo 60) e se permite download.
- [ ] O link gerado é assinado (não é só o nome/slug do Diagrama) e funciona sem login.
- [ ] Um visitante com um Compartilhamento válido (não expirado, não revogado) visualiza o Diagrama em modo somente-leitura.
- [ ] Um visitante com um Compartilhamento expirado ou revogado recebe uma mensagem clara de que o link não é mais válido, em vez de um erro genérico.
- [ ] O visitante só consegue exportar (PNG/SVG/BPMN) se o Compartilhamento usado permitir download; caso contrário, a opção de exportar não fica disponível/nem é aceita pelo servidor.
- [ ] O Autor consegue listar todos os Compartilhamentos ativos do seu Diagrama (com validade e permissão de download de cada um).
- [ ] O Autor consegue revogar um Compartilhamento antes da expiração, invalidando o link imediatamente.
- [ ] Um Diagrama pode ter múltiplos Compartilhamentos ativos simultaneamente, cada um com sua própria expiração e permissão de download.
- [ ] Testes de Feature cobrem: criação com dias padrão/customizados, rejeição de mais de 60 dias, acesso de visitante válido, acesso expirado (via manipulação de tempo), acesso revogado, download permitido vs. bloqueado, listagem e revogação.
</content>
