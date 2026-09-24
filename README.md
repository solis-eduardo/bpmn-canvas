# BPMN Studio (editor + viewer self-hosted)

Stack mínima para modelar BPMN 2.0 e compartilhar diagramas **sem exigir conta de quem recebe**.

- **Editor** (`/editor/`): bpmn-js Modeler, protegido por usuário/senha (Basic Auth).
- **Viewer** (`/view/?d=arquivo.bpmn`): público, somente leitura, com zoom/pan e download PNG/SVG/BPMN.
- **Armazenamento**: arquivos `.bpmn` (BPMN 2.0 XML padrão) na pasta `./diagrams` do host.
- Um único container `nginx:stable-alpine`; sem banco de dados, sem backend próprio (gravação via módulo WebDAV do nginx).
- bpmn-js é baixado no build e servido localmente, sem depender de CDN em runtime.

## Subindo

```bash
cp .env.example .env        # ajuste EDITOR_USER / EDITOR_PASSWORD
docker compose up -d --build
```

Acesse `http://servidor:8080/` → redireciona para o editor.

## Uso

1. No editor, dê um nome ao diagrama e clique em **Salvar** (ou Ctrl+S).
   O nome vira o arquivo: `Processo de Compras` → `processo-de-compras.bpmn`.
2. **Copiar link** gera `http://servidor:8080/view/?d=processo-de-compras.bpmn`.
   Quem recebe o link só vê aquele diagrama — não há login, listagem nem edição.
3. **Importar arquivo…** abre um `.bpmn` exportado de outra ferramenta (Heflo, Camunda, Bizagi…).
4. **Baixar** exporta `.bpmn`, SVG ou PNG (também disponível no viewer).

Salvar com outro nome funciona como "Salvar como" (o original é mantido).
O link público sempre mostra a **última versão salva**.

## Rotas

| Rota                         | Acesso        | Função                                  |
|------------------------------|---------------|-----------------------------------------|
| `/editor/`                   | login         | Editor                                  |
| `GET /api/files/`            | login         | Lista os diagramas (JSON)               |
| `GET/PUT/DELETE /api/files/x.bpmn` | login   | Lê / grava / exclui                     |
| `/view/?d=x.bpmn`            | público       | Viewer somente leitura                  |
| `GET /files/x.bpmn`          | público       | XML bruto (usado pelo viewer)           |

Nomes aceitos: `[A-Za-z0-9][A-Za-z0-9._-]*.bpmn` (o editor já normaliza acentos e espaços).
Qualquer outro caminho, método ou extensão é recusado pelo nginx.

## Produção

- **Use HTTPS.** Basic Auth sem TLS trafega a senha em texto claro. Coloque atrás do seu reverse proxy
  (Traefik, nginx, Caddy) e remova o `ports:` do compose se o proxy estiver na mesma rede Docker.
- **Backup / histórico**: `./diagrams` é uma pasta comum de `.bpmn`. Dá para versionar com Git
  (ex.: cron com `git add -A && git commit -m "snapshot"`), ou incluir no backup existente.
- **Links públicos**: qualquer pessoa com o link vê o diagrama. Não há listagem pública, mas nomes
  óbvios podem ser adivinhados. Para processos sensíveis, use um sufixo aleatório no nome
  (ex.: `onboarding-7f3k9q`).
- **Vários editores**: todos usam o mesmo usuário. Para usuários distintos, troque o htpasswd gerado
  no `entrypoint/40-bpmn-setup.sh` por um arquivo montado via volume com várias linhas.
  Não há trava de edição concorrente: se duas pessoas salvarem o mesmo arquivo, vale o último.
- **Atualizar o bpmn-js**: altere `BPMN_JS_VERSION` no `.env` e rode `docker compose up -d --build`.

## Estrutura

```
docker-compose.yml
Dockerfile
.env.example
nginx/default.conf                 # rotas, auth, WebDAV
entrypoint/40-bpmn-setup.sh        # gera htpasswd e ajusta permissões no start
web/editor/                        # editor (bpmn-js Modeler)
web/view/                          # viewer público (bpmn-js NavigatedViewer)
web/assets/                        # CSS e utilitários de exportação
diagrams/                          # seus .bpmn (volume)
```

## Licença do bpmn-js

O bpmn-js é gratuito, inclusive para uso comercial, mas a licença exige que a marca
**bpmn.io** exibida no canto do diagrama permaneça visível.
