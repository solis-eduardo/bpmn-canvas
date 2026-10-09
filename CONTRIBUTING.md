# Contributing to BPMN Canvas

Thanks for your interest in contributing! This is a small project, so the process is intentionally lightweight.

## Development setup

1. Clone the repository.
2. Copy the environment file and generate an app key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. Set `DB_PASSWORD` and, at minimum, `ADMIN_EMAIL`/`ADMIN_PASSWORD` in `.env`.
4. Start the stack:
   ```bash
   docker compose up -d --build
   ```
5. Visit `http://localhost/login`. See the root `README.md` for authentication details (local and Keycloak SSO).

## Domain vocabulary

Before touching code, skim `CONTEXT.md` (domain glossary) and `docs/adr/` (architectural decisions). This project uses a controlled vocabulary — Diagram, Author, Collaborator, Share, Editing Lock, Version, Fork, User — consistently across code, UI, and docs. Match that vocabulary in new code, commit messages, and PR descriptions rather than introducing synonyms (`CONTEXT.md` also lists the PT-BR UI term for each).

## Running tests

```bash
php artisan test
```

Tests use an in-memory SQLite database (see `phpunit.xml`), so no database needs to be running. To run them (and phpstan/pint) inside a container instead of on the host, use the dev overlay, which adds the Composer dev dependencies the production image omits:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm dev
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm dev vendor/bin/phpstan analyse --memory-limit=1G
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm dev vendor/bin/pint --test
```

## Coding standards

This project uses [Laravel Pint](https://laravel.com/docs/pint) for PHP formatting:

```bash
vendor/bin/pint
```

Run it before opening a PR. Keep changes focused — avoid unrelated refactors or reformatting in the same PR as a feature/fix.

## Internationalization

User-facing strings live in `lang/{en,pt_BR,es}/messages.php`, referenced from Blade views via `__('messages.key')`. If you add or change UI text, update all three locale files, not just English.

## Submitting a pull request

1. Fork the repository and create a branch from `main`.
2. Make your changes, with tests for new behavior where practical.
3. Run `php artisan test` and `vendor/bin/pint` locally.
4. Open a PR with a clear description of what changed and why. Reference any related issue.
5. Be responsive to review feedback — small, focused PRs get merged faster.

## Reporting issues

Open a GitHub issue with steps to reproduce, expected vs. actual behavior, and relevant logs (`docker compose logs app`) when applicable.

---

# Contribuindo com o BPMN Canvas (Português)

Obrigado pelo interesse em contribuir! Este é um projeto pequeno, então o processo é propositalmente enxuto.

## Configurando o ambiente de desenvolvimento

1. Clone o repositório.
2. Copie o arquivo de ambiente e gere a chave da aplicação:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. Defina `DB_PASSWORD` e, no mínimo, `ADMIN_EMAIL`/`ADMIN_PASSWORD` no `.env`.
4. Suba o stack:
   ```bash
   docker compose up -d --build
   ```
5. Acesse `http://localhost/login`. Veja o `README.md` na raiz para detalhes de autenticação (local e Keycloak SSO).

## Vocabulário do domínio

Antes de mexer no código, dê uma olhada em `CONTEXT.md` (glossário do domínio) e `docs/adr/` (decisões arquiteturais). Este projeto usa um vocabulário controlado — Diagrama, Autor, Colaborador, Compartilhamento, Trava de Edição, Versão, Fork, Usuário — de forma consistente no código, na UI e na documentação. Siga esse vocabulário em código novo, mensagens de commit e descrições de PR, em vez de introduzir sinônimos (`CONTEXT.md` também lista o termo PT-BR da interface para cada um).

## Rodando os testes

```bash
php artisan test
```

Os testes usam um banco SQLite em memória (veja `phpunit.xml`), então nenhum banco precisa estar de pé. Para rodá-los (e phpstan/pint) dentro de um container em vez do host, use o overlay de dev, que adiciona as dependências de dev do Composer que a imagem de produção omite:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm dev
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm dev vendor/bin/phpstan analyse --memory-limit=1G
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm dev vendor/bin/pint --test
```

## Padrões de código

Este projeto usa o [Laravel Pint](https://laravel.com/docs/pint) para formatação PHP:

```bash
vendor/bin/pint
```

Rode antes de abrir um PR. Mantenha as mudanças focadas — evite refatorações ou reformatações não relacionadas no mesmo PR de uma feature/fix.

## Internacionalização

As strings voltadas ao usuário ficam em `lang/{en,pt_BR,es}/messages.php`, referenciadas nas views Blade via `__('messages.key')`. Ao adicionar ou alterar texto de UI, atualize os três arquivos de idioma, não só o inglês.

## Enviando um pull request

1. Faça um fork do repositório e crie uma branch a partir de `main`.
2. Faça suas mudanças, com testes para comportamento novo quando fizer sentido.
3. Rode `php artisan test` e `vendor/bin/pint` localmente.
4. Abra um PR com uma descrição clara do que mudou e por quê. Referencie qualquer issue relacionada.
5. Responda ao feedback da revisão — PRs pequenos e focados são mesclados mais rápido.

## Reportando problemas

Abra uma issue no GitHub com passos para reproduzir, comportamento esperado vs. observado, e logs relevantes (`docker compose logs app`) quando aplicável.
