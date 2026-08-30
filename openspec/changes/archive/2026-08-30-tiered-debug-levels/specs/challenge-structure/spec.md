## ADDED Requirements

### Requirement: Debug output is graded into three cumulative levels

Each challenge page SHALL expose its debug output at three levels selected by a
sticky `debug` URL parameter: level `0` (absent or `0`), level `1`, and level
`2`. The levels SHALL be cumulative — a page at level 2 SHALL show everything it
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
own attempt failed* — error messages the learner's input provoked, console errors
from the learner's own payload, and timing or behavioral signals a black-box
attacker could infer. Output SHALL sit at level 2 when it discloses the target's
internals — server source code, the assembled query, returned data, the raw
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
teaching the learner to produce.

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

## MODIFIED Requirements

### Requirement: Challenges follow the glass-box editing contract

Each challenge SHALL isolate its single vulnerable snippet in a `critical.<ext>`
file that the including page loads, snapshot it to `critical.orig.<ext>` at image
build time, and expose it through the `fix.php` editor (Save writes the running
file; Restore reverts to the snapshot). Each challenge page SHALL support the
sticky, three-level `debug` switch defined by the graded-debug requirements, and
SHALL assign its own debug output to those levels.

#### Scenario: Learner patches the running code

- **WHEN** a learner edits the snippet in the Fix editor and saves
- **THEN** the challenge page immediately runs the edited `critical.<ext>`

#### Scenario: Restore reverts to the shipped snippet

- **WHEN** a learner clicks Restore in the Fix editor
- **THEN** `critical.<ext>` is overwritten with the `critical.orig.<ext>` snapshot

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
