# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SIMP 2.0 (Sistema Integrado de Macromedição e Pitometria) — a water measurement and pitometry management system for CESAN. Monolithic PHP application with a Python ML microservice.

**Stack:** PHP 8.3, Python 3.10 (Flask/XGBoost), SQL Server, jQuery 3.6, Materialize CSS, Docker Swarm.

## Build & Deploy

```bash
# Load environment variables
source docker/.env

# Build PHP container
docker build -f docker/Dockerfile -t registry.cesan.com.br/cesan/simp20-php:$(cat version) .

# Build TensorFlow/ML container
docker build -f docker/Dockerfile.tensorflow -t registry.cesan.com.br/cesan/simp20-tensorflow:latest .

# Deploy stack (dev)
docker stack deploy --with-registry-auth -c docker/stackdev.yml simp20-php

# Deploy stack (production)
docker stack deploy --with-registry-auth -c docker/stack.yml simp20-php
```

Version is controlled by the `version` file at repo root.

## Branch & CI/CD Workflow

- `develop` → development environment
- `staging` → homologation environment
- `master` → production environment

GitLab CI (`.gitlab-ci.yml`) runs two stages: `dockerize` (build+push image) and `deploy` (Docker Swarm deploy). Triggered automatically on push to any of the three branches above.

## Architecture

```
html/                         # Web root (PHP frontend + API)
├── *.php                     # Page controllers (server-rendered with inline JS)
├── includes/                 # Shared PHP: auth.php, header/footer/menu templates
├── bd/                       # Backend API layer (JSON endpoints)
│   ├── conexao.php           # PDO SQL Server connection
│   ├── ldap.php              # LDAP authentication
│   ├── logHelper.php         # Audit logging utility
│   ├── verificarAuth.php     # Auth verification for AJAX
│   ├── operacoes/            # Core business operations (25 endpoints)
│   ├── entidade/             # Entity management
│   ├── pontoMedicao/         # Measurement points
│   ├── dashboard/            # Dashboard data
│   ├── ia/                   # AI/ML rules
│   └── [other domains]/      # registroVazaoPressao, motorBomba, etc.
├── style/css/                # Page-specific CSS files
└── style/js/                 # jQuery, Materialize, plugins

docker/                       # Container configuration
├── Dockerfile                # PHP 8.3 Apache
├── Dockerfile.tensorflow     # Python 3.10 Flask
├── stack.yml                 # Production Swarm stack
├── stackdev.yml              # Dev Swarm stack
└── tensorflow/app/           # ML microservice (Flask)
    ├── main.py               # REST API (predict, anomalies, train)
    ├── predictor.py           # XGBoost prediction engine
    ├── anomaly_detector.py    # Anomaly detection
    └── database.py            # DB connector

scripts/                      # SQL Server migration scripts
```

### Key Patterns

- **Pages** (`html/*.php`): Server-rendered HTML with embedded JavaScript. Each page includes `header.inc.php`, `menu.inc.php`, `footer.inc.php`. AJAX calls go to `html/bd/` endpoints.
- **API endpoints** (`html/bd/*/`): Return JSON. Use `conexao.php` for DB access, `verificarAuth.php` for session validation, `logHelper.php` for audit trails.
- **Database**: Direct PDO queries with parameterized SQL against SQL Server. No ORM. Connection via `html/bd/conexao.php`.
- **Authentication**: LDAP + session-based. Permissions use `temPermissaoTela('NOME_TELA')` to check access by feature name (see `html/includes/auth.php`). Access levels: `ACESSO_LEITURA` (1) and `ACESSO_ESCRITA` (2).
- **ML service**: Separate Flask container communicating via internal Docker network. PHP calls it using the `TENSORFLOW_URL` env var. Models stored on shared NFS volume.
- **Cron job**: Daily at 4:30 AM calls `motorBatchTratamento.php` for batch processing.

## Development Rules (from premissas.md)

- **Preserve existing logic** — only change what's strictly necessary.
- **All screens must be responsive.**
- **Dropdowns must include search/filter functionality.**
- **Maintain the existing visual layout** — follow current Materialize patterns.
- **Handle accents and special characters carefully** (Portuguese text, UTF-8 encoding).
- **Check existing endpoints** before creating new ones — reuse when possible.
- **Document/comment generated code well.**

## Important Conventions

- All system text is in **Brazilian Portuguese**.
- Frontend uses **Materialize CSS** components (modals, toasts, selects, datepickers). Do not introduce other UI frameworks.
- JavaScript is embedded inline in PHP page files, not in separate .js files.
- API responses follow `{ "success": true/false, "message": "...", "data": [...] }` pattern.
- Audit logging is required for data modifications — use `logHelper.php`.
- The `html/bd/conexao.php` file supports environment-forced DB switching via session (`ambiente_forcado`).

## No Formal Test Suite

There is no automated test framework configured. Testing is done manually via browser.
