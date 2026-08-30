## MODIFIED Requirements

### Requirement: Challenges live in a domain-grouped tree

Every challenge SHALL live in a folder `challenges/<domain>/<challenge>/`, where
`<domain>` groups by the class of target being attacked (e.g. `intro`, `web`,
`binary`). The repository root SHALL NOT contain challenge folders.

#### Scenario: Web challenge location

- **WHEN** a SQL-injection or XSS challenge is added
- **THEN** it is placed under `challenges/web/<challenge>/`

#### Scenario: Setup challenges grouped under intro

- **WHEN** a challenge exists whose purpose is verifying the learner's setup
  rather than teaching a vulnerability (`hello`, `runtime-check`)
- **THEN** it is placed under `challenges/intro/`

#### Scenario: Binary challenge location

- **WHEN** a binary-exploitation challenge is added (e.g. `ret2win`, `ret2libc`)
- **THEN** it is placed under `challenges/binary/<challenge>/`

### Requirement: Base images form a platform family chain

Shared runtime infrastructure SHALL live under `platform/`. A `platform/harness/`
image SHALL provide the shared web-delivery skeleton (Apache/PHP runtime,
`pico.css`, and the `fix.php` Fix editor). Each challenge family SHALL be a
separate image that builds `FROM` the harness and adds only that family's
toolchain. The `platform/php/` family SHALL add the PHP CodeMirror bundles,
Psalm, and `lint.php`. Additional families (e.g. `platform/native/`) SHALL
likewise build `FROM` the harness as siblings, each adding only its own toolchain
and editor language, without modifying the harness or any other family.

#### Scenario: Web challenge descends from the php family

- **WHEN** a web challenge Dockerfile is built
- **THEN** it builds `FROM` the `glassbox-php` family image, which itself builds
  `FROM` the `glassbox-harness` image

#### Scenario: Harness carries only the shared skeleton

- **WHEN** the harness image is built
- **THEN** it contains `fix.php` and `pico.css` but no family-specific analysis
  tooling, so any family (PHP analysis, C analysis, …) can descend from it
  unchanged

#### Scenario: Native family descends from the harness

- **WHEN** the `native` family image is built
- **THEN** it builds `FROM` the `glassbox-harness` image and adds only the C
  toolchain (a C compiler, binutils, a C-mode editor bundle, and a C linter),
  carrying no PHP static-analysis tooling

### Requirement: Challenges follow the glass-box editing contract

Each challenge SHALL isolate its single vulnerable snippet in a `critical.<ext>`
file that the including page loads, snapshot it to `critical.orig.<ext>` at image
build time, and expose it through the `fix.php` editor (Save writes the running
file; Restore reverts to the snapshot). A challenge MAY declare a per-challenge
configuration that names the target file, an optional Save-hook to run after
writing, and optional additional editable fields shown beside the snippet; absent
that configuration, the target SHALL default to `critical.php` with no Save-hook
and no extra fields, so existing challenges are unaffected. When a challenge
declares a Save-hook, Save SHALL run it and the running artifact SHALL be updated
only if the hook succeeds; when the hook fails, the previously working artifact
SHALL keep running and the hook's errors SHALL be surfaced to the learner. Each
challenge page SHALL support the sticky `?debug=1` switch that reveals server
internals relevant to its vulnerability.

#### Scenario: Learner patches the running code

- **WHEN** a learner edits the snippet in the Fix editor and saves
- **THEN** the challenge serves the behavior of the edited `critical.<ext>` (after
  running the Save-hook, if the challenge declares one)

#### Scenario: Restore reverts to the shipped snippet

- **WHEN** a learner clicks Restore in the Fix editor
- **THEN** `critical.<ext>` is overwritten with the `critical.orig.<ext>` snapshot,
  any declared extra fields reset to their shipped defaults, and any declared
  Save-hook re-runs so the served artifact returns to its original state

#### Scenario: Config declares a non-default target and a Save-hook

- **WHEN** a challenge ships a per-challenge configuration naming a `critical.<ext>`
  target and a Save-hook
- **THEN** the Fix editor reads and writes that target file and runs the declared
  hook on Save

#### Scenario: A failed Save-hook never bricks the challenge

- **WHEN** a learner saves a snippet whose Save-hook fails (e.g. it does not
  compile)
- **THEN** the running artifact is left unchanged, the challenge remains fully
  usable, and the hook's error output is shown to the learner as editor
  diagnostics

#### Scenario: A successful Save-hook replaces the artifact atomically

- **WHEN** a Save-hook succeeds
- **THEN** the running artifact is replaced by the newly produced one atomically,
  so no request ever runs a half-written artifact
