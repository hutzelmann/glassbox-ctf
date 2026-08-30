<?php
// ret2libc config: the Fix editor edits critical.c, recompiles via build.sh on
// Save, and shows the compiler-flags field. NX stays ON by default here, that is
// the whole reason this rung needs code reuse (ROP) instead of shellcode.
return [
    'target' => 'critical.c',
    'build'  => 'build.sh',
    'fields' => [
        ['file' => 'build.flags', 'label' => 'Compiler flags (allowlisted: -f*stack-protector*, -pie/-no-pie, -Wl,-z,execstack/noexecstack, -O0..-O3, -g)'],
    ],
];
