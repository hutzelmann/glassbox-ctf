## ADDED Requirements

### Requirement: Solutions name the debug level that shows what they describe

Where a `solution.md` walkthrough tells the reader to consult debug output, it
SHALL name the level at which that output actually appears. A walkthrough SHALL
NOT instruct the reader to use a level that does not show the referenced output.

#### Scenario: Walkthrough cites the level that shows the query

- **WHEN** a SQL-injection `solution.md` step tells the reader to inspect the
  assembled query
- **THEN** it directs them to the level at which the assembled query is shown,
  not to a level that only reports the database error

## MODIFIED Requirements

### Requirement: Every challenge ships a spoiler-free README

Each challenge folder SHALL contain a `README.md` aimed at the learner: the
challenge premise, its position in its ladder, the tasks to accomplish, how to
run that container, and what the Fix editor and each debug level do for it. The
`README.md` SHALL describe every debug level the challenge offers, and SHALL make
clear which level withholds the answer and which reveals it, so a learner can
choose a level deliberately. The `README.md` SHALL NOT contain flags, payloads,
injection strings, or the fix.

#### Scenario: README omits the answer

- **WHEN** a learner reads a challenge `README.md`
- **THEN** they see the tasks and how to run the container, but no flag, no
  payload, and no fix

#### Scenario: README distinguishes the levels

- **WHEN** a learner reads the glass-box section of a challenge `README.md`
- **THEN** it says what each debug level shows for that challenge, and warns
  which level gives the answer away
