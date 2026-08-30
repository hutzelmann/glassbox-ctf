// The vulnerable function. This is the ONLY code you can edit.
//
// Same bug as ret2win: it reads far more bytes than the 16-byte buffer holds, so
// your input overflows onto the saved return address. The read is a little larger
// here to give your ROP chain room, and because `read` stops at the number of
// bytes it is told, anything you send PAST that limit stays on stdin, waiting for
// whatever the chain runs next.
//
// (Available from the harness that includes this file: <stdio.h>, <stdlib.h>,
// <unistd.h>. The buffer is 16 bytes; the read asks for up to 64.)
void vuln(void)
{
    char buf[16];
    read(0, buf, 0x40);
}
