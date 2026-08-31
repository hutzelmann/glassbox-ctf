# native-binary-challenges Specification (delta)

## ADDED Requirements

### Requirement: The debug view interprets a submitted ROP chain

For a rung solved with a multi-link return-oriented chain (`ret2libc`), the Hints
level (`?debug=1`) SHALL interpret the learner's own submitted bytes as a chain, not
merely display them. It SHALL show the full submitted payload on the stack view
(never silently truncated), mark where the vulnerable read stops so bytes beyond it
are shown as feeding the spawned shell's input rather than the stack, resolve each
filled slot at and above the saved return address to the role it plays in the chain,
and report whether the chain reaches its `system` call with the stack alignment that
call requires. This guidance interprets the learner's attempt, so it SHALL appear
only once Hints (or Debug) is enabled and only after a payload has been submitted.

#### Scenario: Full submitted bytes with the read boundary marked

- **WHEN** a learner submits a payload longer than the vulnerable read and opens the
  stack view
- **THEN** every submitted byte is shown, and the bytes past the read boundary are
  marked as feeding the spawned shell's stdin rather than lying on the stack

#### Scenario: Chain links are resolved to their roles

- **WHEN** a learner submits a chain and opens the stack view or the chain read-out
- **THEN** each filled slot from the saved return address upward is labelled with its
  role (for example an alignment `ret`, a `pop rdi; ret` gadget, the address of the
  `"/bin/sh"` string used as an argument, or the `system` call target), and an
  argument slot is not mislabelled as a return target

#### Scenario: Misalignment before the system call is reported

- **WHEN** a learner's chain calls `system` but reaches it without the 16-byte stack
  alignment that call requires
- **THEN** the view reports that the call will fault on the alignment-sensitive
  instruction and tells the learner to add a bare `ret` to realign, and when the
  chain is correctly aligned it does not raise that warning

#### Scenario: The chain read-out summarizes the attempt

- **WHEN** a learner submits a chain and opens the chain read-out
- **THEN** it lists the chain step by step — each link's stack offset, value, resolved
  role, and effect — together with the alignment verdict, without the learner having
  to reconstruct it from the raw frame
