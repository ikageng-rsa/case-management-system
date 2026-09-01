# Contributing — Branching, Reviews & CI

How branches, pull requests, and releases work in this repo. See `CONVENTIONS.md` for code
style (backend and mobile each have their own).

Apps in this repo: `backend`, `mobile`. Below, `{app}` stands for whichever one you're working
in — the process is identical either way, only the branch name changes.

## Branch Model

`main` is what's currently live. It only receives patches directly. New-version work happens on
a `release/{app}-{version}` branch — one track per app, since they don't share a release
cadence — and feature branches come off whichever track they belong to.

```
main                                              ← currently live, protected, PR-only
 │
 ├── patch/fix-narration-timezone                 ← branched from main for an urgent hotfix
 │
 ├── release/backend-1.4.0                        ← cut from main for the next backend release
 │     ├── feature/case-reference-generator        ← branched from release/backend-1.4.0
 │     └── feature/diary-alerts                     ← branched from release/backend-1.4.0
 │
 └── release/mobile-1.0.0                         ← cut from main for the next mobile release,
       └── feature/case-detail-screen                independent of backend's release above
```

| Branch | Purpose | Branched from | Merges into |
|---|---|---|---|
| `main` | What's currently live. Only patches land here directly. | — | — |
| `release/{app}-{version}` | Where the next release of `{app}` gets built. | `main` | merged into `main`, then tagged `{app}-v{version}` |
| `feature/{name}` | Non-patch work for whichever release track it belongs to. | the relevant `release/{app}-*` branch | that same branch |
| `patch/{name}` | A fix needed on what's currently live. | `main` | `main`, then also the active `release/{app}-*` branch for the affected app |

A feature only ever targets one release track — the one matching the folder it touches. A
feature branch that would need to touch both `/backend` and `/mobile` isn't one branch; it's two
coordinated feature branches (one per track), linked by the same cross-cutting issue below, each
merging into its own app's release branch on its own schedule.

## Starting a Feature

1. Confirm the current `release/{app}-{version}` branch for the app you're working in —
   `git branch -r`, or ask the team.
2. Confirm the work fits inside one area of the app (`CONVENTIONS.md` §15 for backend, §11 for
   mobile). If it touches another feature's shared code, or crosses the `/backend`–`/mobile`
   boundary, see "Cross-Cutting Changes" below before branching.
3. Branch from the relevant release branch:
   ```bash
   git checkout release/backend-1.4.0
   git pull
   git checkout -b feature/diary-alerts
   ```
4. Commit in small, descriptive, present-tense commits.
5. Write the tests required for that app (`CONVENTIONS.md` §14 backend, §10 mobile).
6. Open the PR into that same release branch once CI is green locally.

## Cross-Cutting Changes

1. Open a GitHub Issue titled `Cross-cutting: <short description>` explaining what shared code
   needs to change and why.
2. Tag it `cross-cutting`, tag whoever owns the affected area.
3. Get an explicit go-ahead before creating the branch.

This applies within a single app as before, and also across the folder boundary: a change to an
API endpoint's request/response shape in `/backend` that `/mobile` depends on is a cross-cutting
change too — flag it the same way before merging, not after mobile breaks against it.

## Pull Requests

Every PR — a feature into its `release/{app}-*` branch, or a patch into `main`:

- [ ] Description of what changed and why (link the issue if there is one)
- [ ] Unit tests for new/changed units
- [ ] One feature test covering the change end-to-end
- [ ] CI passes
- [ ] Self-reviewed diff
- [ ] At least one approving review
- [ ] No cross-cutting change without a linked issue

**Merge strategy:** squash merge into `main`/`release/{app}-*`.

## Continuous Integration

`.github/workflows/tests.yml` runs on every push to a PR branch targeting `main` or
`release/**`, scoped to changes under `backend/**` only — a mobile-only PR won't trigger it:

1. Installs PHP + Composer dependencies (cached).
2. Checks code style with Laravel Pint (`CONVENTIONS.md` §3).
3. Spins up MySQL, runs migrations, runs `php artisan test` (`CONVENTIONS.md` §14).

`security.yml`'s `composer audit` job is scoped to `backend/**` the same way; its `gitleaks`
secret scan covers the whole repo, since a leaked credential can end up in either folder. Mobile
has no CI yet — that's a planned follow-up, not an oversight, and not symmetric with backend
until it exists.

## Branch Protection

For `main` and every `release/{app}-*` branch, in GitHub repo settings:

- [ ] Require a pull request before merging
- [ ] Require status checks to pass before merging
- [ ] Require at least 1 approving review
- [ ] Require branches to be up to date before merging
- [ ] Restrict force-pushes and branch deletion

## Starting a New Release

Same steps for either app — substitute `{app}` and the version:

1. ```bash
   git checkout -b release/backend-1.4.0 main
   git push -u origin release/backend-1.4.0
   ```
2. `feature/{name}` branches for that app target this release branch. The other app's release
   branch, if one is active, is unaffected — they don't block each other.
3. Once every planned feature has merged and the branch is stable, open a PR merging it into
   `main` — a regular merge commit, not squash.
4. Once merged, tag `main` at the merge commit:
   ```bash
   git checkout main
   git pull
   git tag backend-v1.4.0
   git push --tags
   ```
   Deploy/submit from `main`. Delete the release branch once merged.

## Patching What's Live

1. ```bash
   git checkout -b patch/fix-narration-timezone main
   ```
2. Fix it, test it, open a PR back into `main`.
3. After merge, deploy `main`.
4. If a `release/{app}-*` branch is currently active for the app the patch fixes, also carry the
   same fix into it (PR or cherry-pick) before the next feature merge, so it isn't overwritten or
   reintroduced. A backend patch has nothing to carry into an active mobile release branch, and
   vice versa.

## Security Patches

A vulnerability reported per `SECURITY.md` is not treated as an ordinary patch:

1. Branch from `main`, same as any patch: `git checkout -b patch/{name} main`.
2. Fix and test it. Review is expedited — one reviewer, same day, not queued behind other PRs.
3. Merge into `main` and deploy immediately.
4. If a `release/{app}-*` branch is active for the affected app, the fix is carried into it **as
   part of the same fix, not a follow-up** — both branches are patched together, before either
   is considered resolved. This is mandatory, not conditional on convenience. (If the
   vulnerability affects both apps, both tracks get it, same rule.)
5. If the vulnerability was present in a version still in use, follow the disclosure and
   acknowledgement process in `SECURITY.md`.
