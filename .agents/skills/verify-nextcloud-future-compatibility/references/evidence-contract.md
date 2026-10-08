# Compatibility evidence contract

Choose and label exactly one evidence mode. Store its record in the ignored
release workspace or another explicitly chosen non-secret artifact location;
do not add generated reports to an app repository by default.

## 0. Evidence modes

For a routine development currency check, record only the retrieval time, the
latest officially released stable `nextcloud/server` tag and commit, its
`version.php` value, the actual local DDEV version, comparison result, commands,
and the established narrow smoke results. Mark the result `current` or
`behind`, never `publishable`. This record proves neither the declared app
range nor a future ceiling and cannot justify metadata changes. Sections 1–5
below do not apply to this lightweight record.

For a release-candidate compatibility gate, use one report for one immutable
candidate revision set and complete every remaining section.

## 1. Scope

Record:

- release-candidate label and UTC timestamp;
- every app ID, repository URL, branch, and commit;
- original `min-version` and `max-version`;
- the authoritative openDesk release or deployment source, its immutable ref
  or retrieval timestamp, and the current Nextcloud major resolved from it;
- confirmation that every declared app range includes that openDesk major;
- official lifecycle status and testability of the declared minimum major;
- requested publication target and whether the run is diagnostic or a gate;
- approvals for app, Parent, DDEV/`occ`, staging, or external-state writes.

## 2. Upstream identity

For every tested major, record a row with:

| Field | Required value |
| --- | --- |
| Major | Integer Nextcloud major |
| Status | released or officially upcoming |
| Server ref | Exact tag, `stableNN`, or `master` |
| Server commit | Full commit ID |
| Version proof | Major read from the pinned `version.php` |
| Release notes | Official developer-manual URL |
| OCP source | Matching package version/ref and resolved commit or checksum |
| Retrieval time | UTC time refs were fetched |

For each additional Nextcloud-owned dependency or direct integration, record
its repository, why it is relevant, the compatibility mapping to the server
major, its ref and commit, and the changelog or migration source reviewed.

## 3. Per-app, per-major results

Use `green`, `red`, or `unverified`; never collapse skipped work into green.
Record command, exit code, and artifact/log location for each applicable row:

| Stage | Minimum evidence |
| --- | --- |
| Metadata | XML schema validation and effective dependency range |
| Source review | Critical changes, deprecations, and mapped repo changes |
| PHP static | Lint plus analysis against the matching OCP API |
| JavaScript static | Lint/build/tests against the app's locked dependencies |
| App tests | Complete repository-local PHP and JavaScript suites |
| Fresh install | Server install, app/dependency enable, DI and registration |
| Code check | Target server's `occ app:check-code`, if available |
| Runtime smoke | Relevant API, permission, job, UI, and asset checks |
| Upgrade | Previous major to target with synthetic existing data, if applicable |
| Combination | Standalone and suite/dependency combinations, if applicable |

For an optional runtime provider, set
`NC_COMPAT_OPTIONAL_PROVIDER_LIFECYCLE=<app-id>` only in the explicitly
approved Fresh-Install run that owns this additional trust boundary. The
existing DDEV driver then verifies the consumers after provider disable,
removal and reinstallation. Do not enable this lifecycle stage in ordinary
compatibility runs that do not need it.

When that provider also needs an app-version update proof, pass its immutable
higher-version target as `--update-app APP_ID:REPOSITORY:COMMIT`. The runner
rejects missing base snapshots and non-increasing versions, records both trees
in the manifest and exposes only the extracted target through
`NC_COMPAT_UPDATE_APPS_ROOT`. The DDEV driver verifies the consumer after the
update; for LocalBase it also compares a synthetic persisted organization
state before and after the version transition.

For each red or unverified row, include the exact failure and whether it is an
app incompatibility, an upstream defect, an environment limitation, or missing
evidence. Do not waive a mandatory row inside the report.

## 4. Decision

Record:

- the first non-green major for each app;
- the highest contiguous green major for each app;
- whether a lower-bound review was triggered, its evidenced reason, and the
  explicit decision or unchanged lower boundary;
- whether each failed future major is inside the declared range, an explicit
  release target, or exploratory only, plus its publication impact;
- the suite ceiling, equal to the minimum of included app maxima;
- the proposed `info.xml` diff for each app;
- any Parent/installer/CI hard-coded version contract that must change;
- the post-update rerun and normal Delivery Gate result.

The verdict is `publishable compatibility gate: green` only when the report
matches the exact candidate commits, every declared major and explicit release
target is green, updated metadata matches the decision, and the standard clean
Delivery Gate is green. An exploratory failure above the truthful declared
ceiling is allowed only when it is recorded and no range gap is claimed. This
verdict does not authorize publishing or production use.

## 5. Mandatory negative cases

Reject the gate when any of these applies:

- a future major was inferred rather than named by official upstream sources;
- a branch name and `version.php` disagree;
- a moving ref was tested but its commit was not recorded;
- a major inside the claimed range failed or was skipped;
- `min-version` was raised or its tests were removed without a separately
  evidenced review and explicit approval;
- a temporary environment limitation was used as justification to drop an
  older Nextcloud major;
- a corresponding Nextcloud dependency repository could not be mapped;
- only documentation, lint, static analysis, or a page-load check was run;
- source metadata was changed before proof and the real updated source was not
  retested;
- an app maximum exceeds the lowest compatible required dependency;
- the report uses a different app commit from the packaged candidate;
- a hard-coded release check still enforces a stale maximum;
- a failed future target was treated as optional even though it was already
  declared or explicitly required for the release.
