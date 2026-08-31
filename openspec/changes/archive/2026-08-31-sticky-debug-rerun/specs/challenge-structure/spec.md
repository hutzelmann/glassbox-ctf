# challenge-structure Specification (delta)

## MODIFIED Requirements

### Requirement: Debug output is graded into three cumulative levels

Each challenge page SHALL expose its debug output at three levels selected by a
sticky `debug` URL parameter: level `0` (absent or `0`), level `1`, and level
`2`. The levels SHALL be cumulative: a page at level 2 SHALL show everything it
shows at level 1. Level 0 SHALL present the challenge exactly as shipped, with no
debug output. A `debug` value that is absent, empty, non-numeric, or outside the
range SHALL resolve to the nearest valid level rather than producing an error.
The selected level SHALL persist across navigation: every internal link and form
submission a page emits SHALL carry the current level.

Changing the level via the dial SHALL, by default, re-run the page's last
submission so the learner's current result is preserved at the new level in a
single action rather than being lost and re-entered: when the page has a re-runnable
submitted form, changing the level SHALL re-submit it at the new level; when it has
none, changing the level SHALL navigate as before without re-submitting. A form
whose submit is not safe to replay MAY opt out, and the dial SHALL then treat it as
not re-runnable. Re-running SHALL apply only to submissions that carry their data in
the request body; a submission whose data lives in the URL is already preserved by
the level change and SHALL NOT be re-submitted.

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

#### Scenario: Changing the level preserves the result by default

- **WHEN** a learner submits a form (e.g. a login attempt or a payload) on any
  challenge, then changes the debug dial to another level
- **THEN** the same submission is re-run at the new level and its result is shown,
  without the learner re-entering or re-sending it

#### Scenario: An opted-out submit is not replayed

- **WHEN** a learner submits a form that has opted out of re-running because its
  submit mutates state (e.g. a registration that inserts a row), then changes the dial
- **THEN** that submission is not replayed; the dial navigates to the new level, and
  the learner re-submits if they want the result there

#### Scenario: Nothing to re-run navigates as before

- **WHEN** a learner changes the debug dial before submitting anything, or on a page
  whose submission is a URL (GET) rather than a request body
- **THEN** the dial changes the level without a spurious re-submission, and a GET
  submission's data is preserved by the level change
