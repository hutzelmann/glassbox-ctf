#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

/*
 * ret2win: the fixed, uneditable harness around the vulnerable snippet.
 *
 * The learner edits only critical.c (pulled in at the bottom). Everything they
 * must not touch (main() and the win() they are trying to reach) lives here,
 * BEFORE the #include, so the `#line 1 "critical.c"` directive scopes cleanly to
 * the snippet and compiler errors in it report correct critical.c line numbers.
 */

/* A decoy marker, NOT the real flag. The binary only ever prints this, so the
 * downloadable binary (and the debug "Program" view) cannot leak the flag; the web
 * interface recognises this marker and reveals the real flag, which it keeps
 * server-side. */
static const char *DECOY = "N0tTh3Fl4gR34lly";

/* The goal. main() never calls this; the overflow must redirect here. */
void win(void)
{
    puts("== win() reached ==");
    puts(DECOY);
    fflush(stdout);
    _exit(0);
}

/* Defined by the learner-editable critical.c below. */
void vuln(void);

int main(void)
{
    setvbuf(stdout, NULL, _IONBF, 0);
    puts("ret2win: I read some bytes onto the stack, then return. Nothing else.");
    puts("There is a win() function I never call. Make me call it.");
    vuln();
    puts("...returned normally. No flag for you.");
    return 0;
}

#line 1 "critical.c"
#include "critical.c"
