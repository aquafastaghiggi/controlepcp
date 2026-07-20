# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ControlePCP V2** is a production scheduling and sequencing system (PCP = "Planejamento e Controle da Produção"). It manages production lines, optimizes manufacturing schedules using metaheuristic algorithms, and provides real-time tracking. The UI language is Portuguese (pt_BR).

**Stack:** Laravel 12, PHP 8.2+, Livewire 4, Alpine.js, Tailwind CSS 4, MySQL, Vite

## Development Commands

```bash
# Full setup (installs deps, generates .env, migrates, installs npm)
composer setup

# Start all dev processes concurrently (server + queue + logs + vite)
composer dev

# Individual processes
php artisan serve          # http://localhost:8000
npm run dev                # Vite asset server
php artisan queue:listen   # Queue worker
php artisan pail           # Log streaming
npm run build              # Production assets

# Database
php artisan migrate
php artisan migrate:fresh --seed

# Tests
composer test              # or: php artisan test
php artisan test --filter=TestClassName
```

## Architecture

The app follows **MVC + Livewire + Service/Action layers**:

```
Blade/Livewire Views
  → Livewire Components (reactive UI, no page reloads)
  → HTTP Controllers / Action Classes (workflow orchestration)
  → Service Classes (domain logic)
  → Eloquent Models
  → MySQL
```

**Action classes** (`app/Actions/`) orchestrate multi-step workflows and are the main entry points for complex business operations. **Service classes** (`app/Services/`) contain reusable domain logic called by actions.

## Core Domain Logic

### Production Scheduling Pipeline

The central flow is: create a `Programacao` (schedule) with `ItemProgramacao` rows (production orders), then run `CalcularSequenciaAction` which:
1. Optionally optimizes item order via `OtimizadorService`
2. Calculates exact start/end times via `SequenciadorService` (respecting work shifts and holidays from `CalendarioService`)
3. Persists `ResultadoSequencia` blocks (type: `setup` or `producao`) in a DB transaction
4. Updates `Programacao.status` to `calculada`

### Scheduling Algorithms (`app/Services/`)

- **`SequenciadorService`** — Given a fixed order, distributes production across shifts. Handles multi-shift days, overnight shifts (truncates if next day is blocked), and generates a `memoria_calculo` audit trail.
- **`OtimizadorService`** — Finds optimal sequence to minimize setup time + urgency cost. Uses Nearest Neighbor (greedy) then Simulated Annealing for refinement. `PESO_SETUP=0.4`, `PESO_PRAZO=0.6`.
- **`CalendarioService`** — Distributes minutes across actual work shifts, skipping non-working days, holidays, and respecting shift override days.

### `Programacao` Status Flow
`rascunho` → `calculada` → `confirmada` | `cancelada`

### Key Business Rules
- `Produto.taxa_por_hora` drives production time calculation
- `MatrizSetup` is directional (A→B ≠ B→A); missing pairs default to 0 minutes
- `ItemProgramacao` denormalizes product details (sku, descricao_produto) to preserve history even if a product is later deleted
- `Programacao.eficiencia` is a % modifier applied during simulation

## Key Files

| Path | Purpose |
|------|---------|
| `app/Actions/CalcularSequenciaAction.php` | Orchestrates full scheduling calculation |
| `app/Services/SequenciadorService.php` | Timeline calculation engine |
| `app/Services/OtimizadorService.php` | Nearest Neighbor + Simulated Annealing optimizer |
| `app/Services/CalendarioService.php` | Shift/calendar distribution logic |
| `app/Livewire/Programacao/FormularioProgramacao.php` | Main scheduling UI component |
| `app/Models/Programacao.php` | Central domain model |
| `routes/web.php` | Web routes (all behind `auth` middleware) |
| `routes/api.php` | REST API under `/api/v1/` prefix |

## Livewire Components

Components under `app/Livewire/` communicate via events. Key example: `ImportarExcel` dispatches `ordensImportadas` which `FormularioProgramacao` listens to for populating items.

## ERP Integration

The `app/Models/Codi/` and `app/Services/Codi/` namespaces handle optional sync with an external ERP (CODI). Configured via `ERP_API_URL`, `ERP_API_USUARIO`, `ERP_API_SENHA` in `.env`. The integration is non-critical — the app functions fully without it.

## Naming Conventions

- Domain terms are in Portuguese: `Programacao`, `Sequenciador`, `Otimizador`, `ItemProgramacao`, `MatrizSetup`, `Linha`, `Feriado`
- Laravel infrastructure code (providers, middleware, base classes) uses English
- All PHP files use `declare(strict_types=1)` and full type hints

## Subagents (`.claude/agents/`)

Specialized agents are available for focused work. Always finish with `reviewer` before reporting completion.

| Agent | When to use |
|-------|-------------|
| `backend` | PHP/Laravel work — Services, Actions, Controllers, Models, routes, ERP integration |
| `database` | Migrations, schema changes, new Models, Eloquent relationships, query optimization, seeders/factories |
| `frontend` | Livewire components, Blade views, Alpine.js interactions, Tailwind styling |
| `tester` | PHPUnit unit and feature tests — especially for Services and Actions |
| `reviewer` | Final validation before any implementation is complete — checks standards, MRP integrity, security, and test coverage |
| `mrp` | Pipeline de sequenciamento — `CalcularSequenciaAction`, `OtimizadorService` (NN + Simulated Annealing), `SequenciadorService`, `CalendarioService`, regras de transição de status, calendários/turnos |
| `eficiencia` | OEE, métricas de desempenho, integração CODI — `EficienciaCalculator`, `PainelDesempenho`, `codi_eficiencia`, `codi_eventos`, planejado × realizado |
| `relatorios` | Queries analíticas, dashboards, exportação de dados — padrões de query, view de impressão (`/programacoes/{id}/imprimir`), N+1 prevention, PhpSpreadsheet |

### Typical Workflow

```
backend (implement) → database (schema, if needed) → frontend (UI, if needed)
  → tester (write tests) → reviewer (validate all) → done
```

**Domain-specialist workflow (para trabalho nos algoritmos ou OEE):**
```
mrp ou eficiencia ou relatorios (domain logic)
  → backend (infra changes, if needed) → database (schema, if needed)
  → frontend (UI, if needed) → tester → reviewer → done
```

For isolated changes, skip uninvolved agents. Always run `reviewer` last.
