## MODIFIED Requirements

### Requirement: The debug view exposes binary internals

With `?debug=1`, a native challenge SHALL reveal the internals a learner needs to
understand and exploit the bug: the disassembly of the relevant functions, the
symbol listing including the target function's address, the `checksec`
protections, the process memory map, a hexdump of the bytes the server received,
and a stack-frame view mapping the submitted bytes onto the buffer, the saved
frame pointer, and the saved return address. The stack-frame view SHALL resolve
the value that lands in the saved return address to the symbol or region it points
at, so the learner sees where execution would go. Guidance that interprets these
internals SHALL appear only once debug is enabled.

Every stack slot's note SHALL describe that slot. A note SHALL NOT attribute to one
slot a consequence that belongs to another: only the saved-return-address row may
state what the CPU does when the function returns. Slots above the frame SHALL be
resolved to the symbol or region they point at — that identification is what lets a
learner see a target address landed one slot off — but SHALL be described as stack
above the frame, reached only if execution returns again, not as the address the
`ret` jumps to.

#### Scenario: Debug reveals the win() address

- **WHEN** a learner enables debug
- **THEN** the symbol listing shows `win()`'s address, which the payload must target

#### Scenario: Stack view maps the learner's bytes

- **WHEN** a learner submits a payload and enables debug
- **THEN** the stack-frame view marks which submitted bytes overwrote the saved
  return address and resolves the value there to the symbol or region it points at

#### Scenario: A slot above the frame is not described as a return target

- **WHEN** a learner submits a payload long enough to fill whole words past the
  saved return address and opens the stack view
- **THEN** each such row is described as stack above the frame, reached only if
  execution returns again, and its value is still resolved to the symbol or region
  it points at
- **AND** no such row claims the CPU faults, or jumps, on returning to that value

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

Chain interpretation is opt-in per rung. Chain vocabulary — naming a slot a chain
link, a gadget, an argument or a call target — SHALL appear only for a rung that has
declared the chain roles being resolved, and SHALL be identical in the
payload-derived stack view and the live captured stack view. A rung that declares no
chain SHALL NOT use that vocabulary anywhere in its debug views, so a learner meets
those terms first on the rung that teaches them. A slot whose value the declaring
rung does not recognise SHALL fall back to the plain past-frame description rather
than being named a chain link.

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

#### Scenario: A rung without a chain never says "chain link"

- **WHEN** a learner on a rung that declares no chain roles (`ret2win`) submits an
  overlong payload and opens either the payload-derived or the live stack view at
  any debug level
- **THEN** no row is labelled a chain link, gadget, argument or call target, and no
  row names return-oriented programming

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
