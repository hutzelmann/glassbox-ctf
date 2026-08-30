#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

/*
 * ret2libc, the fixed, uneditable harness. Same overflow as ret2win, but there
 * is no win() to jump to. Instead the ingredients for a call to system("/bin/sh")
 * are scattered in the binary: the system@plt stub, a "/bin/sh" string, and a
 * `pop rdi; ret` gadget. The learner chains them into a small ROP payload.
 */

/* Keeps the "/bin/sh" string in the binary for the ROP chain to point RDI at. */
char *shell = "/bin/sh";

/* A `pop rdi; ret` gadget. Not called, found with ROPgadget/objdump and used as
 * a link in the chain (it loads the next stack value into RDI, system()'s arg). */
__asm__(
    ".text\n"
    "pop_rdi_ret:\n"
    "    pop %rdi\n"
    "    ret\n"
);

void vuln(void);

int main(int argc, char **argv)
{
    setvbuf(stdout, NULL, _IONBF, 0);
    puts("ret2libc: same overflow, but there is no win().");
    puts("system() and \"/bin/sh\" are in this binary. Build a chain that calls system(\"/bin/sh\").");
    /* Imports system@plt and references `shell` so both survive the link. The
       branch never runs (argc is never > 9 here), so this is not a shortcut. */
    if (argc > 9) {
        system(shell);
    }
    vuln();
    puts("...returned normally. No shell for you.");
    return 0;
}

#line 1 "critical.c"
#include "critical.c"
