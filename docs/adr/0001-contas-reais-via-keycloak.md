# Contas reais via Keycloak, substituindo o Basic Auth compartilhado

Status: accepted, integração Keycloak adiada (ver nota abaixo e ticket de integração SSO)

O stack legado (nginx + WebDAV) usava um único usuário/senha via Basic Auth para todo mundo que editava — sem autoria, sem trilha de quem fez o quê. A reconstrução em Laravel introduz contas de Usuário reais, com Keycloak como fonte de verdade de identidade de destino (provisionamento just-in-time no primeiro login), e criação manual por um Administrador como via alternativa para casos que não passam pelo SSO.

Decidimos por Keycloak (em vez de autenticação local do Laravel como destino final) porque a organização já possui essa peça de infraestrutura, e porque os recursos que dependem de autoria (Autor de Diagrama, Colaborador, papel de Administrador) exigem identidade individual estável — o que o Basic Auth compartilhado do legado nunca ofereceu.

**Nota (adiado):** o Keycloak já existe na organização, mas o client deste projeto ainda não foi criado nele. Optamos por não bloquear o build nisso — a via alternativa (contas criadas por um Administrador) vira a via *principal* enquanto o client não existe, e a integração SSO com Keycloak entra como um ticket independente e opcional, plugável depois sem mudar o modelo de dados (`Usuário`/`Administrador` já são desenhados para suportar as duas origens).
</content>
