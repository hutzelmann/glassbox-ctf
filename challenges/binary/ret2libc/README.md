# Binary Exploitation: ret2libc

> The same stack overflow as ret2win, but this time there is no `win()` to jump
> to, and the stack is non-executable, so you can't inject your own code either.
> You'll have to build a function call out of pieces the binary already contains.

**Domain:** Binary · ROP **Ladder:** Binary 2 of 2, [ret2win](../ret2win/) → **ret2libc**

## The scenario

You control the saved return address (you learned how in
[ret2win](../ret2win/)). But controlling *one* address only lets you jump to code
that already exists. There is no convenient `win()` here, and **NX** (a
non-executable stack) means shellcode you place in the buffer will not run.

The way forward is **return-oriented programming**: instead of one address, you
write a *chain* of them onto the stack. Each little snippet ends in `ret`, which
pops the next address off your chain, so you can set up a register and then call
a function. Your goal is to make the program call `system("/bin/sh")`, using three
things already inside the binary:

- the `system@plt` stub (the program imports `system`),
- a `"/bin/sh"` string,
- a `pop rdi; ret` gadget (to put the string's address into RDI, `system`'s first
  argument).

And one trick: the vulnerable `read` stops after a fixed number of bytes, so
anything you send **after** your chain stays on stdin, ready for the shell you
just spawned to execute.

## Your tasks

- **a)** Confirm you still control the return address (as in ret2win), and note
  what's different: no `win()`, and `checksec` shows **NX enabled**.
- **b)** Locate the three ingredients, `system@plt`, the `"/bin/sh"` string, and a
  `pop rdi; ret` gadget (find them with Ghidra, `ROPgadget`, and `objdump`).
- **c)** Build a ROP chain that calls `system("/bin/sh")`. Mind the **16-byte stack
  alignment** `system` expects, you may need a bare `ret` first.
- **d)** Get a shell and read the flag. Append a command (e.g. `cat /flag`) *after*
  your chain, the leftover bytes feed the shell.
- **e)** Fix `critical.c` so the overflow can't reach the return address, then
  confirm the chain fails.
- **f)** Explain why **NX did not stop this**, what protections would (stack canary,
  PIE + ASLR, CFI), and how each can still be bypassed.
- **g)** Script the chain with **pwntools** (`ROP()`, `p64()`, `process()`) against
  the downloaded binary.

## Running it

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-ret2libc
```

Then open <http://localhost:9000/>. As with ret2win the binary is x86-64
(`linux/amd64`; arm64 hosts run it under emulation).

## The glass box

- **Fix button**: edits the one vulnerable function (`critical.c`); **Save
  recompiles** the binary (a broken edit is rejected with the compiler's errors and
  cannot brick the container). The **compiler-flags** field lets you change the
  protections, try turning NX *off* (`-Wl,-z,execstack`) and see `checksec` change,
  or add a canary and watch the chain get caught.
- **Debug dial** (optional, never required to solve), in two settings:
  - **Hints** (`?debug=1`) gives you a byte/endianness calculator and a live stack
    table of your chain laid onto the frame — your own bytes, so you get the
    **offset** and alignment from what you sent.
  - **Debug** (`?debug=2`) adds the **ROP ingredients** panel (the `system`,
    `/bin/sh`, and gadget addresses your chain is built from), the disassembly,
    `checksec`, and the memory map — the pieces a real attacker recovers with
    `ROPgadget` / `objdump`.

## Stuck?

The full chain, the addresses, the alignment fix, and the pwntools version are in
[solution.md](solution.md). Spoilers ahead.
