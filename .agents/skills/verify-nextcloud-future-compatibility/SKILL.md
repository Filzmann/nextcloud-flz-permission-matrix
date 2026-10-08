---
name: verify-nextcloud-future-compatibility
description: Check that the development workspace uses the latest released Nextcloud, or prove the full support range and future ceiling while creating a release candidate. Use for periodic runtime currency checks and publishable RC compatibility gates; do not use for unrelated app development, production deployment, or publication itself.
---

# Verify Nextcloud future compatibility

Keep the development runtime current and, separately, establish the highest
supportable future Nextcloud major from official upstream sources, static
checks, and real runtime evidence. The lightweight currency result is not a
release proof. Treat the complete matrix as a mandatory release-candidate
gate, not as authorization to publish.

Read [evidence-contract.md](references/evidence-contract.md) completely before
selecting upstream refs or recording a result.

## Choose the evidence mode

### Routine development currency check

Use this mode periodically outside a release-candidate run.

1. Resolve the latest officially released Nextcloud stable version from the
   official `https://github.com/nextcloud/server` Git tags. Pin the tag and
   commit and verify its version in `version.php`.
2. Read the actual local DDEV version with `occ status`. Compare the complete
   version, not only the branch or image label. Running or changing DDEV,
   Docker, Nextcloud, or `occ` still requires the authorization defined by the
   Parent rules.
3. If the local runtime is current, run the established narrow DDEV/workspace
   smokes appropriate to the mounted apps. If it is behind, report the exact
   pinned target and open or execute the separately authorized runtime-update
   task before claiming that development happens on the newest version.
4. Record the upstream and local versions, commits or tags, retrieval time,
   commands, and smoke result. This mode does not produce a release compatibility verdict,
   does not prove the declared support range, and must not change `min-version`
   or `max-version`.

### Release-candidate compatibility gate

Run the full compatibility matrix while creating every publishable release candidate.
Resolve and record the current openDesk Nextcloud major from the authoritative
openDesk release or deployment source named for that candidate. Every app's
declared range must include it; the declared minimum need not equal it and may
remain lower when that wider support is still proved. An unavailable or
ambiguous openDesk source makes the RC unverified.

The remaining sections describe this release-candidate mode unless they
explicitly mention the routine currency check.

## Protect scope and state

1. Read the Parent `AGENTS.md`, every in-scope repository's complete
   `AGENTS.md`, and its local `work-in-nextcloud-app` skill. Inspect
   `git status --short` in each repository.
2. Identify the exact app commits intended for the release candidate. A dirty
   or moving app revision is not release evidence.
3. Before requesting authorization, record one concrete action matrix with a
   row per affected repository and separate columns for file changes,
   DDEV/`occ`, commits, push/release, and external-state writes. Bundle all
   predictable rows into one request. A grant applies only to its named rows
   and actions; once explicitly granted, retain it for the whole run and do
   not ask for it again.
4. Before changing any app repository, apply the repository stop gate: name
   the compatibility risk, exact files, tests, and rollback, then obtain
   explicit authorization for every affected repository. Include the Parent
   when its build or delivery contracts contain fixed version assumptions.
5. Use an isolated temporary test environment. Do not mutate the documented
   DDEV instance, run `occ`, install or upgrade Nextcloud, or access staging or
   production without the separate authorization required by local rules.
6. Keep upstream clones and generated evidence outside tracked app trees.
   Never commit a Nextcloud checkout, credentials, databases, logs, or test
   artifacts.

## Resolve the candidate majors

1. Read each app's current `min-version` and `max-version`. The declared range
   is inclusive and cannot express gaps.
2. Confirm that the declared range includes the current openDesk Nextcloud
   major recorded for this candidate. Treat a missing or contradictory
   openDesk version source as unverified rather than guessing a floor.
3. Query the official `https://github.com/nextcloud/server` repository. Pin
   every tested ref to a commit and verify the major in `version.php` rather
   than inferring it from a branch name.
4. Include every declared major and then every successive upstream-named major
   through the highest testable candidate:
   - for a released major, test the newest published patch tag and the current
     `stableNN` head when it exists;
   - for an upcoming major, test `master` only when `version.php` names that
     major and the official developer manual already has dedicated release
     notes for it;
   - never invent or extrapolate a major that upstream has not named.
5. Re-fetch refs immediately before the release-candidate verdict. Evidence
   applies only to the recorded commits; a later moving-branch result needs a
   new run.
6. Determine the highest contiguous green major. Stop extending the range at
   the first failed, missing, or unverified major even if a later major appears
   to pass.

## Review the lower support boundary

Keep the existing lower boundary by default. Never raise `min-version` automatically.

Trigger a separate lower-bound review only when at least one evidenced reason
applies:

- the declared minimum major is officially end-of-life;
- its required PHP, database, or dependency stack can no longer receive
  necessary security fixes or be reproduced safely;
- the major cannot be tested meaningfully with maintained tooling;
- preserving it requires a risky compatibility shim or blocks a necessary
  supported-platform change; or
- the user explicitly requests a support-range review.

Do not use a temporary infrastructure outage, a missing local image, or an
uninvestigated test failure as a reason to drop support. Produce a proposal
that identifies the removed majors, affected users and upgrade path, the last
compatible app release, security and maintenance consequences, the required
app version/change-log treatment under Nextcloud's current official release
process, exact files, tests, and rollback. Ask for an explicit decision before
editing `min-version` or dropping tests.

If any currently declared major fails, block the release candidate. Offer the
separate choices to repair compatibility or approve a support drop; do not
make the release green by narrowing the range. After an approved increase,
test every major in the resulting range and preserve an obtainable prior app
release for the dropped Nextcloud majors where distribution policy permits.

## Map the corresponding Nextcloud repositories

For each app, inventory Nextcloud-owned dependencies and integrations from PHP
imports, boot registration, `composer.json`/lock files, `package.json`/lock
files, and direct runtime adapters.

- Always include `nextcloud/server` and the matching Nextcloud OCP API package
  or OCP tree from the pinned server ref.
- For a shipped core app such as DAV, test the exact code included in that
  server checkout. Do not substitute an arbitrary newer checkout.
- For a separately versioned `nextcloud/*` Composer, npm, or app repository,
  resolve the tag or branch actually compatible with the target server ref,
  pin its commit, and review its migration notes and removed APIs.
- Treat private `OC\\*`, cross-app `OCA\\*`, removed browser globals, and
  internal JavaScript/CSS coupling as explicit compatibility risks. A locally
  approved adapter still requires a runtime test against the target code.
- Mark an unresolved repository/ref mapping red. Do not silently test against
  `latest` or upgrade locked dependencies as part of the proof.

## Build the proof matrix

Run the following stages for every app and every major in the contiguous
matrix. Use the target major's supported PHP, database, Node, and browser
requirements.

1. Review that major's official critical changes, upgrade guide,
   deprecations, and the relevant changelogs in every mapped repository.
2. Validate `appinfo/info.xml` against the official schema. Run PHP and
   JavaScript syntax checks, the app's complete local tests, and static
   analysis against the matching OCP API level. Run `occ app:check-code` when
   present in the pinned server version.
3. On a fresh isolated installation of the pinned server revision, install
   required infrastructure, place the exact release-candidate app revision in
   the custom app path, enable it, and verify status, dependency injection,
   background-job/command/settings registration, and database setup.
   This is the primary Fresh-Install/Reinstall proof. Record the actual
   executable invocation and show that server configuration, database and
   app data were empty or deliberately recreated from the selected sources;
   disabling/re-enabling an app or reading `occ status` is not that proof.
   Reuse this stage for development-reset verification instead of creating
   an identical second reinstall suite. A documented requirement without an
   executed, reproducible command remains unverified.
4. Exercise the app's relevant authenticated API, permission, file, job,
   integration, UI, and static-asset smokes. A page load alone is insufficient.
5. When the app has persistent state or migrations, test an upgrade from the
   immediately preceding supported major with synthetic existing data and
   verify integrity, repeatability, and failure behavior.
   This checks supported Nextcloud platform transitions. Preservation of an
   older internal app development revision is a separate decision governed
   by `docs/architecture.md`, section "Entwicklungsphase und
   Kompatibilitätsbedarf"; do not manufacture historical app compatibility
   solely because an earlier development schema exists.
6. For infrastructure or suite apps, test required standalone combinations
   and the complete release combination. Record the suite ceiling as the
   lowest proven maximum of all included apps.

If the current source metadata blocks enabling the app on a future major, use
an explicitly recorded, test-copy-only `max-version` overlay in the isolated
environment. Do not change the source metadata until the proof is green, and
do not treat the overlay itself as evidence.

Do not treat documentation review or static analysis alone as compatibility proof.

## Handle future incompatibilities

- If a failed future major is outside the declared range and is not an explicit
  release target, record the failure, stop the extension, and keep
  `max-version` at the preceding highest contiguous green major. The release
  candidate may remain publishable only when the complete declared range and
  normal Delivery Gate are green and the evidence reports the limited ceiling.
- If the failed major is already declared or is an explicit release target,
  block the release candidate. Classify the cause as app code, removed or
  private API, dependency/platform requirement, migration, upstream defect, or
  environment limitation. Repair it test-first, wait for a pinned upstream
  correction, or request a separate support-range decision as applicable.
- Never bridge a failed major with a later green result because `info.xml`
  cannot express a gap. Treat a failure on moving `master` as a recorded future
  risk, not automatically as a regression in the still-green declared range.
- Do not raise `min-version`, lower an already declared `max-version`, adopt a
  private API, or weaken security and architecture rules merely to turn the
  compatibility gate green.

## Decide and update metadata

Classify a major as green only when all mandatory stages passed on pinned
upstream and app commits. An infrastructure limitation is `unverified`, not
green.

After all required repository write approvals are present:

1. Preserve `min-version` unless dropping older support was separately
   requested, reviewed, approved, and proved.
2. Set `dependencies/nextcloud@max-version` in each `appinfo/info.xml` to that
   app's highest contiguous green major, using only the integer major.
3. Change no unrelated metadata. Validate the XML and inspect the diff.
4. Do not silently lower an already declared maximum. Block the release
   candidate and report the unsupported claim for an explicit decision.
5. Find Parent, installer, CI, and delivery checks that hard-code the old
   maximum. Update them only within approved scope so they validate the proven
   range rather than reintroducing a fixed stale ceiling; otherwise block the
   release candidate.
6. Repeat the highest-major fresh-install and smoke checks from the real
   updated source, then run every affected app test and the normal release
   Delivery Gate.

## Gate publication and report

Do not publish the release candidate when any declared major or explicit
release target is red or unverified, the matrix has a gap, evidence does not
match the candidate commit, metadata differs from the verdict, an included app
has a lower suite ceiling, or the normal Delivery Gate is not green. A red
exploratory major beyond the declared range limits the reported future ceiling
but does not by itself invalidate an otherwise truthful release candidate.

Report the evidence location, exact app and upstream commits, all mapped
repositories, per-major results, highest proven app and suite majors,
`info.xml` changes, tests and exit codes, skipped checks, residual risks, and
rollback. State separately that publication, push, release, deployment, and
production approval were not performed unless explicitly authorized.
