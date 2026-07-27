---
name: test-driven-change
description: Deliver observable behavior changes through a verified Red–Green–Refactor cycle. Use for new features, bug fixes, domain logic, permission changes, API behavior, data changes, and contract changes; do not use automatically for documentation-only, formatting, generated-file, or mechanical changes without testable behavior.
---

# Deliver a test-driven change

## Establish the proof

Before writing the test, record:

- the domain invariant;
- the observable target behavior;
- the defect or regression the test must detect;
- the chosen test level;
- what the test proves and what it does not prove;
- relevant negative and boundary cases.

Choose the lowest level that proves the real contract without replacing
observable behavior with implementation detail. Use a higher integration,
contract, smoke, or end-to-end level when the lower level cannot exercise the
relevant boundary truthfully. For a bug fix, make the smallest proof a
regression test.

## Red

1. Write the smallest meaningful test before changing production code.
2. Run that test and record the command, exit code, and concrete failure or
   assertion difference.
3. Confirm that it fails for the expected domain reason: the target behavior
   is missing or wrong.
4. Do not proceed to Green when:
   - the test is immediately green and is not an explicitly justified
     characterization test;
   - it fails only because of infrastructure, syntax, fixture, or configuration
     defects;
   - it does not reach the target behavior;
   - it checks only a mock call although observable behavior can be checked
     meaningfully.

Repair a defective test environment without adding the target production
behavior, then repeat Red. An immediately green test can preserve known
behavior as a characterization test, but is not evidence of a TDD Red step.

## Green

1. Make only the smallest production-code change needed to satisfy the test.
2. Add no speculative abstraction or unrelated refactoring.
3. Run the new test again.
4. Run the relevant existing regression suite.

## Cover risk-specific evidence

For permission changes, verify at least:

- unauthorized access is rejected;
- authorized access succeeds;
- a foreign or manipulated object ID grants no access and causes no data
  change;
- a rejected request changes no data;
- UI visibility is never used as a substitute for server-side access control.

For database or migration changes, verify at least:

- fresh installation on an empty schema;
- upgrade from at least the immediately relevant prior version;
- preservation or correct migration of existing data;
- required constraints and indexes exist and preserve integrity;
- no incomplete state after failure.

For shared libraries or contracts, verify:

- relevant provider and consumer contract tests;
- every affected dependent app;
- backward compatibility or an explicitly documented break.

For write operations, verify:

- the return value or HTTP result;
- the persisted state;
- absence of unwanted side effects;
- repeated execution when idempotency is relevant.

These checks supplement applicable repository stop gates. They do not authorize
database, permission, cross-repository, or production changes.

## Refactor

Refactor only after the new test and relevant regression suite are green.
Change no domain behavior. Run the new test and relevant existing tests again
after refactoring.

## Handle non-behavioral changes and deviations

For documentation-only, formatting, generated-file, or mechanical changes
without meaningfully testable behavior, use the suitable deterministic syntax,
contract, generation, layout, link, or structure check instead of an artificial
TDD cycle.

State and justify every deviation from the test-driven workflow. Do not treat a
test that was never observed red as TDD evidence.

## Report

Include:

- the protected invariant and chosen test level;
- the initially red test and expected failure reason;
- the minimal implementation;
- executed focused, regression, and contract tests as applicable;
- concrete commands, results, and exit codes;
- remaining untested risks;
- justified deviations.
