# challenge-structure Specification

## Purpose
Defines the repository layout and the glass-box conventions every challenge must
follow, so challenges group cleanly by domain and new challenge types can be
added without reorganizing the tree.
## Requirements
### Requirement: Challenges live in a domain-grouped tree

Every challenge SHALL live in a folder `challenges/<domain>/<challenge>/`, where
`<domain>` groups by the class of target being attacked (e.g. `intro`, `web`).
The repository root SHALL NOT contain challenge folders.

#### Scenario: Web challenge location

- **WHEN** a SQL-injection or XSS challenge is added
- **THEN** it is placed under `challenges/web/<challenge>/`

#### Scenario: Setup challenges grouped under intro

- **WHEN** a challenge exists whose purpose is verifying the learner's setup
  rather than teaching a vulnerability (`hello`, `runtime-check`)
- **THEN** it is placed under `challenges/intro/`

### Requirement: Published image names are decoupled from folder paths

A challenge's published image SHALL be named `glassbox-ctf-<name>`, where
`<name>` is the challenge folder's own basename, independent of its `<domain>`
path segment.

#### Scenario: Flat tag under nested path

- **WHEN** a challenge lives at `challenges/web/sqli-login/`
- **THEN** its published image is `ghcr.io/<owner>/glassbox-ctf-sqli-login`, with
  no `web` segment in the tag

### Requirement: Base images form a platform family chain

Shared runtime infrastructure SHALL live under `platform/`. A `platform/harness/`
image SHALL provide the shared web-delivery skeleton (Apache/PHP runtime,
`pico.css`, and the `fix.php` Fix editor). Each challenge family SHALL be a
separate image that builds `FROM` the harness and adds only that family's
toolchain. The initial `platform/php/` family SHALL add the CodeMirror bundles,
Psalm, and `lint.php`.

#### Scenario: Web challenge descends from the php family

- **WHEN** a web challenge Dockerfile is built
- **THEN** it builds `FROM` the `glassbox-php` family image, which itself builds
  `FROM` the `glassbox-harness` image

#### Scenario: Harness carries only the shared skeleton

- **WHEN** the harness image is built
- **THEN** it contains `fix.php` and `pico.css` but no PHP static-analysis
  tooling, so a non-PHP-analysis family can descend from it unchanged

### Requirement: Challenges follow the glass-box editing contract

Each challenge SHALL isolate its single vulnerable snippet in a `critical.<ext>`
file that the including page loads, snapshot it to `critical.orig.<ext>` at image
build time, and expose it through the `fix.php` editor (Save writes the running
file; Restore reverts to the snapshot). Each challenge page SHALL support the
sticky `?debug=1` switch that reveals server internals relevant to its
vulnerability.

#### Scenario: Learner patches the running code

- **WHEN** a learner edits the snippet in the Fix editor and saves
- **THEN** the challenge page immediately runs the edited `critical.<ext>`

#### Scenario: Restore reverts to the shipped snippet

- **WHEN** a learner clicks Restore in the Fix editor
- **THEN** `critical.<ext>` is overwritten with the `critical.orig.<ext>` snapshot

### Requirement: Setup containers are not forkable challenges

A container whose sole purpose is verifying the learner's environment
(`runtime-check`) SHALL NOT be required to follow the glass-box editing contract
and MAY descend from an unrelated minimal base image.

#### Scenario: Runtime check needs no Fix editor

- **WHEN** the `runtime-check` container is built
- **THEN** it may be a minimal image (e.g. `alpine`) with no `critical.<ext>`,
  no Fix editor, and no `?debug=1` view

