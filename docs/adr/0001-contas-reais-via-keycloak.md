# Contas reais via Keycloak, substituindo o Basic Auth compartilhado

Status: accepted — integração Keycloak implementada (ticket 08); ver ADR-0005 para as decisões específicas de vínculo de conta e papel de Administrador via claim

O stack legado (nginx + WebDAV) usava um único usuário/senha via Basic Auth para todo mundo que editava — sem autoria, sem trilha de quem fez o quê. A reconstrução em Laravel introduz contas de Usuário reais, com Keycloak como fonte de verdade de identidade de destino (provisionamento just-in-time no primeiro login), e criação manual por um Administrador como via alternativa para casos que não passam pelo SSO.

Decidimos por Keycloak (em vez de autenticação local do Laravel como destino final) porque a organização já possui essa peça de infraestrutura, e porque os recursos que dependem de autoria (Autor de Diagrama, Colaborador, papel de Administrador) exigem identidade individual estável — o que o Basic Auth compartilhado do legado nunca ofereceu.

**Nota (histórico):** esta ADR foi originalmente escrita enquanto o client deste projeto ainda não existia no Keycloak da organização, e a integração SSO ficou adiada como ticket independente. O ticket 08 implementou essa integração; as duas origens de Usuário (local e Keycloak) coexistem como planejado, sem mudança no modelo de dados previsto aqui.
</content>
