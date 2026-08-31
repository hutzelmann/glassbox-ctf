# challenge-structure Specification

## Purpose
Defines the repository layout and the glass-box conventions every challenge must
follow, so challenges group cleanly by domain and new challenge types can be
added without reorganizing the tree.
## Requirements
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
challenge page SHALL support the sticky, three-level `debug` switch defined by the
graded-debug requirements, and SHALL assign its own debug output to those levels.

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

#### Scenario: Fix editor is not gated on debug

- **WHEN** a learner opens the Fix editor at level 0
- **THEN** the snippet is editable with full editor support, because the Fix
  editor is not part of the graded debug output

### Requirement: Setup containers are not forkable challenges

A container whose sole purpose is verifying the learner's environment
(`runtime-check`) SHALL NOT be required to follow the glass-box editing contract
and MAY descend from an unrelated minimal base image.

#### Scenario: Runtime check needs no Fix editor

- **WHEN** the `runtime-check` container is built
- **THEN** it may be a minimal image (e.g. `alpine`) with no `critical.<ext>`,
  no Fix editor, and no debug levels

### Requirement: Debug output is graded into three cumulative levels

Each challenge page SHALL expose its debug output at three levels selected by a
sticky `debug` URL parameter: level `0` (absent or `0`), level `1`, and level
`2`. The levels SHALL be cumulative: a page at level 2 SHALL show everything it
shows at level 1. Level 0 SHALL present the challenge exactly as shipped, with no
debug output. A `debug` value that is absent, empty, non-numeric, or outside the
range SHALL resolve to the nearest valid level rather than producing an error.
The selected level SHALL persist across navigation: every internal link and form
submission a page emits SHALL carry the current level.

#### Scenario: Level 0 is the untouched challenge

- **WHEN** a learner opens a challenge page with no `debug` parameter, or with
  `debug=0`
- **THEN** the page shows no debug output and no editor upgrade

#### Scenario: Level 2 includes level 1

- **WHEN** a learner views a page at `debug=2`
- **THEN** everything that level 1 would show is present, plus the level-2 output

#### Scenario: Level survives navigation

- **WHEN** a learner at `debug=1` follows a link within the challenge or submits
  one of its forms
- **THEN** the destination page is still at level 1

#### Scenario: Out-of-range values are clamped

- **WHEN** a page is requested with `debug=7`, `debug=-1`, or `debug=banana`
- **THEN** the page renders at a valid level instead of failing

### Requirement: Debug panels are placed by the symptom-versus-cause rule

Every piece of debug output SHALL be assigned to level 1 or level 2 by a single
rule. Output SHALL sit at level 1 when it reports *that* and *how the learner's
own attempt failed*: error messages the learner's input provoked, console errors
from the learner's own payload, and timing or behavioral signals a black-box
attacker could infer. Output SHALL sit at level 2 when it discloses the target's
internals: server source code, the assembled query, returned data, the raw
request as the server parsed it, or the victim's rendered output. Debug output
SHALL NOT disclose information the learner could not obtain at level 0 by
ordinary browser inspection unless it is placed at level 2.

#### Scenario: Database error is a symptom

- **WHEN** a learner's input causes a SQL syntax error at level 1
- **THEN** the page shows the database's own error message, and does not show the
  assembled query or any returned row

#### Scenario: Assembled query is a cause

- **WHEN** a learner views a SQL-injection challenge at level 2
- **THEN** the exact query string the server built and the rows it returned are
  shown

#### Scenario: Victim output is a cause

- **WHEN** a challenge simulates a victim rendering the learner's payload
- **THEN** any console error the payload provoked may appear at level 1, while
  the victim's rendered page source appears only at level 2

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

### Requirement: Editors render in the page's colour scheme

Every in-browser code editor the platform ships, the Fix editor and every debug
or payload editor a challenge mounts, SHALL render in the same colour scheme as
the page around it, in both directions. Editor chrome (the text, the editing
surface, the line-number gutter and its separator, the caret, the selection, the
active line, and any panel or tooltip the editor opens) SHALL take its colours
from the page's own stylesheet variables rather than from a palette the editor
carries, so a change to the page's theme moves the editor with it and cannot
leave the two disagreeing.

Syntax highlighting SHALL stay legible against the editing surface in both
schemes. Where the page's stylesheet offers no variable for a colour the editor
needs, that colour SHALL be expressed so that it resolves from the colour scheme
the page has declared, not from an independent reading of the browser preference
which could disagree with the page.

#### Scenario: The gutter follows the page into dark

- **WHEN** a learner opens the Fix editor with the browser set to a dark colour
  scheme
- **THEN** the line-number gutter, its separator, and the editing surface are
  dark like the page, with no light strip beside the code

#### Scenario: The light scheme is unchanged in character

- **WHEN** the same page is opened with the browser set to a light colour scheme
- **THEN** the editor is light, with the gutter still distinguishable from the
  editing surface

#### Scenario: Highlighting stays readable in both schemes

- **WHEN** a snippet containing comments, strings, keywords, and a PHP open tag
  is displayed in either colour scheme
- **THEN** every token is readable against the editing surface behind it, rather
  than being a dark colour on a dark background

#### Scenario: Every editor is themed, not only the editable one

- **WHEN** a challenge page renders a read-only source view, a level-1 payload
  editor, or a level-2 view of the assembled query in a dark colour scheme
- **THEN** each of them is themed exactly like the Fix editor, because the theme
  is shared by every bundle a family ships

#### Scenario: The scheme changes while a page is open

- **WHEN** the browser's colour scheme changes while a page with an editor is
  open
- **THEN** the editor follows immediately, without a reload

### Requirement: No challenge offers a level with nothing behind it

Each challenge that implements the debug contract SHALL provide distinct output
at every level its control offers, so that moving the control always changes what
the learner sees.

#### Scenario: Reflected-XSS challenge has real level-2 content

- **WHEN** a learner moves a reflected-XSS challenge from level 1 to level 2
- **THEN** output appears that was not present at level 1

### Requirement: Every page renders a control for all three levels

Each challenge page SHALL render a control in its header that moves the learner
between all three levels in one interaction, and that shows which level is
currently active. Pages of the same challenge SHALL render the same control.

#### Scenario: Current level is visible

- **WHEN** a learner is at level 1
- **THEN** the header control identifies level 1 as the active selection

#### Scenario: Any level reachable in one step

- **WHEN** a learner at level 0 wants full internals
- **THEN** they can select level 2 directly, without passing through level 1

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

