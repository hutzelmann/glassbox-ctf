// Available from the harness that includes this file:
// <stdio.h>, <stdlib.h>, <unistd.h>

// A message record read off the wire.
struct msg { char tag[16]; char body[48]; };

void vuln(void)
{
    char buf[16];
    read(0, buf, sizeof(struct msg));
}
