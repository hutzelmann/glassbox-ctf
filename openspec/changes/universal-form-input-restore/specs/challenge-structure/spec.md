## MODIFIED Requirements

### Requirement: Base images form a platform family chain

Shared runtime infrastructure SHALL live under `platform/`. A `platform/harness/`
image SHALL provide the shared web-delivery skeleton (Apache/PHP runtime,
`pico.css`, the `fix.php` Fix editor, the `debug.php` debug dial, and the
`remember-form-input.js` form-input helper). Each challenge family SHALL be a
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
- **THEN** it contains `fix.php`, `debug.php`, `remember-form-input.js` and
  `pico.css` but no family-specific analysis tooling, so any family (PHP
  analysis, C analysis, …) can descend from it unchanged

#### Scenario: Native family descends from the harness

- **WHEN** the `native` family image is built
- **THEN** it builds `FROM` the `glassbox-harness` image and adds only the C
  toolchain (a C compiler, binutils, a C-mode editor bundle, and a C linter),
  carrying no PHP static-analysis tooling

### Requirement: Level 1 upgrades learner input to a linting editor

At level 1 and above, each challenge SHALL present the input fields through which
its vulnerability is exploited as a syntax-highlighting editor with a diagnostics
gutter, using highlighting rules matching the language the target actually parses
the input as. Where a page has more than one exploitable input, every one of them
SHALL be upgraded. A linter SHALL NOT flag the construct the challenge is
teaching the learner to produce. On a challenge page, an editor that replaces a
field inside a form SHALL write its document back into that field on every
change, not only on submit, so that leaving the page by any route preserves
what the learner typed.

#### Scenario: Every exploitable field is upgraded

- **WHEN** a challenge page with two injectable fields is viewed at level 1
- **THEN** both fields are editors, not only the first

#### Scenario: Highlighting matches the target parser

- **WHEN** a learner types a payload containing a backslash-escaped quote into a
  MySQL-backed challenge at level 1
- **THEN** the string boundaries are coloured as MySQL parses them, not as a
  generic SQL dialect would

#### Scenario: The intended payload is not marked as an error

- **WHEN** a learner writes a payload that breaks out of a string literal, which
  is the point of the exercise
- **THEN** the editor does not report it as a diagnostic error

#### Scenario: An editor keeps its field current without a submit

- **WHEN** a learner types a payload into a level-1 editor and then leaves the
  page without submitting, for example by moving the debug dial
- **THEN** the underlying form field already holds what they typed

## ADDED Requirements

### Requirement: Typed input survives navigation

Challenge pages SHALL remember what a learner typed into their forms and refill
those forms on return, without any per-form or per-field configuration. The
behaviour SHALL be delivered by a single harness-provided script whose file name
and header comment identify it as a comfort feature that is not part of any
challenge. Every challenge page that contains a form SHALL load that script from
`<head>` without `defer`, so its restore runs before any deferred editor bundle
initialises. A page with no form SHALL NOT load it, and the `fix.php` editor
SHALL NOT load it.

A stored value SHALL only be written into a field the server left blank, so
server-rendered state always wins, and writing it SHALL raise the same input or
change event that typing would, so page scripts that mirror one field into
another stay consistent. A form or field carrying `data-no-restore` SHALL be
skipped.

#### Scenario: Input returns after submitting

- **WHEN** a learner submits a challenge form and then returns to the form page
- **THEN** the form is refilled with the values they last typed

#### Scenario: Input survives a change of debug level

- **WHEN** a learner fills a form and then moves the debug dial to another level
- **THEN** the reloaded page's form still holds the values they typed, even
  though the level changed the field from a plain input into an editor

#### Scenario: An editor-backed field survives the dial

- **WHEN** a learner types into a form field that a level-1 editor has taken
  over, and then moves the debug dial without submitting
- **THEN** the reloaded page still holds what they typed

#### Scenario: Server-rendered values are never overwritten

- **WHEN** a page renders a form field with a value of its own, such as a search
  term echoed from the query string
- **THEN** the stored value is discarded and the server's value is left in place

#### Scenario: A refilled field updates the fields mirrored from it

- **WHEN** a page derives one field from another, such as a binary challenge
  mirroring its hex payload into a readable escape view, and the source field is
  refilled
- **THEN** the derived field is brought up to date as if the value had been typed

#### Scenario: The Fix editor is never refilled from storage

- **WHEN** a learner opens the Fix editor, whose fields always show the file
  currently on disk, including when that file is legitimately empty
- **THEN** no stored value is written into them, because the Fix editor does not
  load the helper, so the editor can never disagree with what is running

#### Scenario: A form opts out

- **WHEN** a form or one of its fields carries `data-no-restore`
- **THEN** the helper neither stores nor restores it

#### Scenario: The debug dial is never treated as form state

- **WHEN** a page renders the debug dial in its header
- **THEN** the helper leaves it alone, because the dial carries no `name` and
  sits outside every form
