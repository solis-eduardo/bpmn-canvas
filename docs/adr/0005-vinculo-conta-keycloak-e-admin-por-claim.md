# Vínculo de conta local↔Keycloak por e-mail, e papel de Administrador só-liga-nunca-desliga

Status: accepted

Ao ativar o login via Keycloak (ticket 08), duas questões exigiam uma escolha que não é óbvia e é cara de reverter depois que já existem contas reais em produção: (1) o que fazer quando alguém loga via Keycloak e já existe uma conta local com o mesmo e-mail, e (2) como a claim de Administrador do Keycloak interage com a flag manual já existente.

**Vínculo por e-mail:** login via Keycloak resolve a conta primeiro por `keycloak_id`; se não encontrar, procura uma conta local (`keycloak_id IS NULL`) pelo mesmo e-mail e vincula essa conta à identidade Keycloak, em vez de criar uma segunda conta duplicada (o que também violaria a constraint `unique` de `email`). A alternativa rejeitada — sempre criar conta nova via Keycloak, deixando a local órfã — quebraria a autoria de Diagramas já criados por quem migra de login local para SSO. Presume-se que o mesmo e-mail identifica a mesma pessoa.

**Administrador só-liga-nunca-desliga:** a claim/role do Keycloak pode *conceder* `is_admin`, mas nunca o revoga — um `is_admin=true` setado manualmente por outro Administrador persiste mesmo que a claim desapareça em login futuro. A alternativa (papel sempre derivado do estado atual da claim) foi rejeitada porque tornaria o acesso de Administrador dependente de configuração externa ao sistema (roles no Keycloak) mesmo para contas que nunca dependeram disso, um efeito colateral surpreendente para quem concedeu o papel manualmente.
