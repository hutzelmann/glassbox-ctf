#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

/*
 * ret2libc: the fixed, uneditable harness around the vulnerable snippet.
 *
 * The learner edits only critical.c (pulled in at the bottom). Everything they
 * must not touch lives here, BEFORE the #include, so the `#line 1 "critical.c"`
 * directive scopes cleanly to the snippet and compiler errors in it report
 * correct critical.c line numbers.
 */

/* Defined by the learner-editable critical.c below. */
void vuln(void);

int main(int argc, char **argv)
{
    setvbuf(stdout, NULL, _IONBF, 0);

    /* Hidden maintenance self-test: prints host info via a fixed command. */
    if (argc > 1 && strcmp(argv[1], "selftest") == 0) {
        return system("uname -srm");
    }

    puts("ret2libc: I read some bytes onto the stack, then return.");
    puts("No win() this time, and the stack is non-executable.");
    vuln();
    puts("...returned normally. No shell for you.");
    return 0;
}

#line 1 "critical.c"
#include "critical.c"
