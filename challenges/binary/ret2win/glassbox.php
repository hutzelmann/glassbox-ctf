<?php
// Per-challenge config the harness fix.php reads. The Fix editor edits critical.c,
// runs build.sh on Save (which recompiles the binary, atomically), and shows the
// compiler-flags field so the learner can toggle protections as a second fix lane.
return [
    'target' => 'critical.c',
    'build'  => 'build.sh',
    'fields' => [
        ['file' => 'build.flags', 'label' => 'Compiler flags (allowlisted: -f*stack-protector*, -pie/-no-pie, -Wl,-z,execstack/noexecstack, -O0..-O3, -g)'],
    ],
];
