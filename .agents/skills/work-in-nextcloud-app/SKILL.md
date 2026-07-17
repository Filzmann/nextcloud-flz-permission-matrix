---
name: work-in-nextcloud-app
description: Work safely in one existing, separately versioned Nextcloud app repository. Use when inspecting or changing an existing app from its own repository root; do not use for Parent-only work, new-app creation, release delivery, or an unauthorized cross-app change.
---

# Work safely in one existing Nextcloud app

This skill is intentionally self-contained. A direct start in an app repository must not depend on a Parent `AGENTS.md`, Parent skill, global skill, symlink, or user-level Codex configuration.

## Start and repository boundary

1. Work from the app repository root. Read its complete local `AGENTS.md` and every locally referenced skill before acting.
2. Run `git status --short` before changes. Preserve unrelated and pre-existing work; never stage, commit, discard, or rewrite another person's changes.
3. Change only the explicitly commissioned repository and scope. Parent files and sibling repositories are out of scope unless the request names the cross-repository change explicitly.
4. Keep changes small, testable, and reversible. For a larger refactoring, new data model, or new service, propose a dedicated branch before commit work unless the request already defines the branch strategy.

## Stop gates

If the concrete request does not already and explicitly include the affected risk area, stop before implementation, name the risk, affected files, tests, and rollback, and obtain approval for:

- database schemas, migrations, or existing production data;
- permissions, group logic, roles, CSRF, authentication, or access protection;
- public LocalBase contracts or changes spanning more than one repository;
- file storage, **file paths**, uploads, downloads, or document generation;
- deletion, renaming, or movement of larger code areas;
- DDEV, Docker, Nextcloud, or `occ` configuration;
- broad refactoring undertaken to repair red tests.

The rollback path being unclear is a separate stop reason. Without approval, only read-only analysis and a minimal change plan are allowed for these areas.

Stop immediately if production systems, Git history rewriting, new production dependencies, changes outside the commissioned repositories, or loss of a binding domain rule would be required. Unexpectedly required external services also trigger a stop. An explicitly commissioned access to a named staging or hosting system is not unexpected, but remains limited to the approved system, operation, and credentials scope.

## Architecture, rights, and data safety

- Keep controllers thin and separate domain logic, data access, rendering, document generation, and file storage. Use repositories, mappers, stores, or services for data access; keep JavaScript API adapters, models/view-models, workflows, and rendering/event binding separated.
- Apply DRY and KISS together. Extract shared code or test helpers only after at least two repositories need the same contract, assertions, fakes, fixtures, or setup steps **semantically identically**, and the shared contract is testable.
- Nextcloud-native group, user, session, AppConfig, share, file, capability, configuration, and request mechanisms must be used by default. A parallel custom mechanism is allowed only when the native option is demonstrably insufficient, the deviation is documented as a reasoned architecture decision, its effects on permissions, migration, maintenance, and interoperability have been checked, and the decision was approved before implementation. Without that documented, checked, and pre-approved architecture decision, stop before implementation.
- Enforce deny by default, least privilege, and server-side first. UI visibility and navigation never grant rights. Centralize permission decisions and scope repository/service results to the actor.
- Distinguish Nextcloud admin, app admin, group membership, ordinary users, read-only/edit roles, shares/capabilities, and background jobs. Validate and type requests, bind QueryBuilder values, escape output, protect writes with CSRF, and justify `NoCSRFRequired` explicitly.
- Temporary local permission simplifications are allowed only when named as pre-production constraints and when they do not obstruct a later granular rights architecture.
- Never construct SQL fragments from request data. Use synthetic, neutral, privacy-sparse data in tests, fixtures, screenshots, logs, examples, and documentation; do not reuse real employee, works-council, customer, mail, health, conflict, decision, or internal-document data.
- Normalize file paths and never compose them unchecked from input. Keep secrets and unnecessary personal data out of repositories, logs, documentation, and test fixtures. Test relevant Allow, Deny, and direct unauthorized API calls.

## UI and accessibility contract

- Use real German umlauts and `ß` in user-visible text; do not change technical IDs, URLs, or machine contracts for typography.
- Use semantic HTML, complete keyboard operation, visible focus, meaningful labels, understandable errors, responsive layouts, and no meaning conveyed only by color, hover, or pointer interaction. Avoid keyboard traps and unnecessary modal flows; use ARIA only where native semantics are insufficient.
- Personal settings in a navigable domain app belong in their own semantic `Einstellungen` tab. App administration belongs in that app's admin section; cross-app organization settings belong in the responsible suite admin section. Small contextual options may remain near their subject, but must not replace required settings or admin surfaces.
- A tab UI uses consistent `role="tablist"`, `role="tab"`, and `role="tabpanel"`, correct `aria-controls`/`aria-labelledby`, maintained `aria-selected`, keyboard activation, and visible focus. Endpoint authorization remains server-side regardless of tab visibility. New or materially changed tabs need a tab/accessibility smoke.
- The direct app root is the vertical scroll container with `height: 100%`, `min-height: 0`, `overflow-y: auto`, `box-sizing: border-box`, and an opaque Nextcloud-compatible background. Wide tables scroll horizontally only inside an inner wrapper. Flex/grid children receive required `min-height: 0` or `min-width: 0`; app layouts do not impose an unjustified global `max-width`.
- Do not override `body` or global Nextcloud core selectors. New apps and material UI/layout changes need suitable keyboard, focus, accessibility, tab, responsive, and scroll smokes; report anything not visually or interactively checked as not fully verified.

## Test-first and coverage

- New development and bug fixes normally follow Red – Green – Refactor. Start a bug fix with a regression test and a refactoring with characterization tests. Develop domain logic, permissions, hierarchies, conflicts, and validation test-first.
- API changes cover success, validation failure, and typical Allow/Deny cases. Cross-app contracts have provider and consumer contract tests. Use integration/DDEV tests for migrations and repository behavior when unit tests cannot represent the real contract.
- Develop executable UI logic test-first; additionally cover layout, accessibility, and Nextcloud integration with suitable smoke or browser checks.
- Permitted test-first entry exceptions are time-boxed exploratory spikes, purely declarative text/metadata or trivial presentation changes, and hard-to-isolate Nextcloud integration where a broader integration test is more truthful. Discard spike code or characterize it before adoption; give declarative changes appropriate syntax, contract, layout, or visibility checks.
- Use the local fast entries named by `AGENTS.md`, normally `php tests/run.php` and `node tests/run-js.mjs`; dependency-light PHP smokes run in isolated processes. Run LocalBase and every affected consumer contract/smoke suite after a LocalBase contract change.
- In local pre-production, app tests may use shared LocalBase test helpers through relative repository paths. Add heavier packaging/autoload structure or a larger test framework only when path handling, runners, assertions, mocks, or fixtures are materially duplicated or impair readability.
- Known overall and app coverage must not decline unnoticed. Aim for at least 85 percent line coverage for new or materially changed executable code, report PHP and JavaScript separately, and fully cover security invariants regardless of percentages. Coverage is a warning and delivery indicator, not a substitute for meaningful assertions.
- Do not prepare a commit or release with red relevant fast tests, contract tests, security checks, coverage gates, or delivery gates.

## DDEV, Nextcloud, and hosting safety

- Prefer local PHP/Node checks and batch DDEV checks. Control the shared DDEV project only from its documented `nextcloud-dev` root when that separate Parent workspace is actually available.
- DDEV paths, DDEV users, container paths, PHP binaries, database credentials, and other local assumptions must never be transferred to a production or hosting environment. Determine production paths, users, `apps_paths`, the PHP binary, and the CLI memory limit separately in the target environment. A switch between DDEV and production is an environment boundary and must be named explicitly. If the target environment is unclear, stop before proceeding.
- Treat Docker/stream-FD errors in a normal sandbox as environment limitations, not automatically as app defects. Read-only diagnostics may be retried with narrowly scoped escalation. State-changing DDEV, Docker, Nextcloud, `occ`, migration, installation, or cleanup commands require the concrete request or explicit approval.
- Nextcloud 34 has no local `occ migrations:migrate` command. App migrations run through `occ app:enable <app-id>` or `occ upgrade` when `needsDbUpgrade: true` is reported.
- On hosting panels, derive the real Nextcloud root, domain user, static-webserver context, CLI-PHP binary, and required CLI memory limit from actual configuration. Do not assume `/var/www/nextcloud`, `www-data`, system `php`, or a default memory limit.
- Verify the real `apps_paths`: Nextcloud core paths stay read-only and the intended `custom_apps` path is writable by the required runtime/CLI context. Preserve configured paths and use the smallest ownership/group/mode correction; never use blanket `chmod 777`.
- Verify separately that the PHP-FPM/domain user can write only where required and that the static-webserver context can read assets and traverse the necessary directories. Diagnose an HTTPS asset `403` against the real path, ownership, group, traversal, and server context rather than broadening permissions blindly.
- An installation is delivered only after status and migration checks, at least one CSS and one JavaScript asset in the static-webserver context and over HTTPS with the correct content type, and the visible UI. An `occ` success alone is insufficient.

## Git and completion report

- Do not commit, push, release, deploy, or use `git add .` without Simon's explicit authorization. Stage individual files only when staging was requested.
- Before a commit, show `git status --short`, `git diff --stat`, and `git diff --name-only`. Never use `git reset --hard`, `git clean`, force-push, history rewrite, or versioned backup copies.
- Run relevant local tests, `git diff --check`, and the repository's own structure/fast check. For an explicitly authorized cross-app contract change, validate every provider and consumer repository from its own root and use the Parent workspace check only as an additional coordinator.
- Finish with the repository root, loaded local instruction chain, locally resolved skills, checks with results, skipped checks with reasons, remaining risks, and the final `git status --short`, diff statistics, and complete changed-file list.

## Learning candidates

Do not turn observations automatically into rules. A candidate must be reproducible or evidenced, reusable in future work, and assigned to the correct level. One-off state, guesses, temporary workarounds, task-specific to-dos, sensitive content, and details better enforced by tests, scripts, code comments, or ordinary documentation are not durable rules.

Classify a valid observation as a local app rule, executable test/script/check, or cross-app candidate. Keep it explicitly unconfirmed until Simon approves it. A cross-app candidate may be proposed to the Parent, but a direct app run must not edit or depend on Parent files merely to record it.
