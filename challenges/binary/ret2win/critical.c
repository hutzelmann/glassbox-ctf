// The vulnerable function. This is the ONLY code you can edit.
//
// It reads your input onto a small stack buffer. The bug: it reads far more
// bytes than the buffer holds, so your input runs off the end of `buf` and over
// the saved frame pointer and the saved return address that sit just above it.
//
// (Available from the harness that includes this file: <stdio.h>, <stdlib.h>,
// <unistd.h>. The buffer is 16 bytes; the read asks for up to 64.)
void vuln(void)
{
    char buf[16];
    read(0, buf, 0x40);
}
