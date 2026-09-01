# Legal Case Management Platform

Monorepo for the case management platform. Two applications live side by side:

| Path | Stack | Responsibility |
|---|---|---|
| [`/backend`](backend/) | Laravel 13, PHP 8.4, MySQL | Serves the versioned JSON API (`/api/v1`) **and** the Blade/Livewire web app |
| [`/mobile`](mobile/) | React Native (community CLI, TypeScript) | Consumes the backend REST API |

The mobile app is not scaffolded yet — see [Follow-ups](#follow-ups).

## Repository layout

```
/
├── backend/          Laravel app (API + web)
│   └── CONVENTIONS.md   PHP/Laravel code conventions
├── mobile/           React Native app
│   └── CONVENTIONS.md   TypeScript/React Native code conventions
├── CONTRIBUTING.md   Branching, PRs, releases, security patches
└── SECURITY.md       Vulnerability reporting
```

Code style is **per app** — each folder has its own `CONVENTIONS.md`. Shared process
(branching, commits, PRs, releases) lives in [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Getting started

### Backend

```bash
cd backend
composer setup     # installs deps, copies .env, generates key, migrates
composer dev        # serve
```

### Mobile

Added once the app is scaffolded.

## CI

| Workflow | Trigger | What it does |
|---|---|---|
| `tests` | changes under `backend/**` | Laravel Pint (style) + PHPUnit |
| `security` | every push/PR + weekly | `composer audit` (backend) + gitleaks (whole repo) |

## Follow-ups

- Scaffold `/mobile` with the React Native community CLI (TypeScript template).
- Add a `mobile-tests` workflow (Jest + React Native Testing Library + ESLint) once
  `mobile/package.json` exists.
