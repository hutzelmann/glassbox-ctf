# gdb-Python stack-frame dumper for the native binary family.
#
# native-run.php runs this under `gdb -batch` (native) or `gdb-multiarch -batch`
# (remote) to capture the REAL contents of the vulnerable frame while the binary
# runs, at two points inside vuln():
#   BP-A = the `call read` instruction   -> the pristine frame (real saved return
#          address, real saved RBP, real canary, uninitialized buffer)
#   BP-B = the instruction right after it -> the frame after read(), i.e. the
#          learner's payload laid over those slots (captured before the epilogue's
#          canary check, so a clobbered canary is still visible even when it aborts)
#
# Two modes, chosen by GBX_MODE (set by native-run.php):
#   native  the binary matches the host arch: gdb ptraces it, `run < payload`.
#   remote  the binary is a foreign arch: qemu-user started it paused on a gdbstub
#           at GBX_PORT with the payload on its stdin; connect and `continue` to the
#           breakpoints (no `run`). This is what gives full-fidelity capture on a
#           host that does not match the binary.
#
# It reads a window relative to the real frame pointer at each stop and prints ONE
# JSON object between GBXJSON / GBXEND markers on stdout. It is best-effort: on any
# failure it prints {"ok": false, ...} so the PHP caller degrades to the
# payload-derived model instead of erroring.
#
# Parameters arrive via the environment (set by native-run.php):
#   BP_OFF_A / BP_OFF_B  byte offsets from vuln's start to BP-A / BP-B
#   RBP_TO_BUF           bytes from the frame pointer down to the buffer start
#   WINDOW_LEN           total bytes to read, from buf start upward
#   HAS_CANARY           "1" when the binary has a stack canary
#   GBX_MODE             "native" (default) or "remote"
#   GBX_PORT             remote mode: the qemu gdbstub port to connect to
#   PAYLOAD_PATH         native mode: file whose bytes feed the binary's stdin
import gdb, json, os, sys


def env_int(name):
    return int(os.environ[name])


OFF_A = env_int('BP_OFF_A')
OFF_B = env_int('BP_OFF_B')
RBP_TO_BUF = env_int('RBP_TO_BUF')
WINDOW_LEN = env_int('WINDOW_LEN')
HAS_CANARY = os.environ.get('HAS_CANARY') == '1'
MODE = os.environ.get('GBX_MODE', 'native')
PORT = os.environ.get('GBX_PORT', '')
PAYLOAD = os.environ.get('PAYLOAD_PATH', '')


def frame_regs():
    # Frame pointer + stack pointer via gdb's architecture-generic aliases: $fp is
    # $rbp on x86-64 and $x29 on aarch64, $sp is $rsp / $sp respectively. Using the
    # generic names (instead of $rbp/$rsp) lets the remote path read a FOREIGN guest's
    # frame, which is what makes the cross-arch self-test work, while staying identical
    # to the old behaviour on a native x86-64 host. The window math and slot
    # interpretation below stay x86-64-shaped; interpreting an aarch64 frame is out of
    # scope until a real aarch64 rung ships.
    fp = int(gdb.parse_and_eval('$fp')) & 0xffffffffffffffff
    sp = int(gdb.parse_and_eval('$sp')) & 0xffffffffffffffff
    return fp, sp


def capture():
    rbp, rsp = frame_regs()
    lo = rbp - RBP_TO_BUF
    mem = bytes(gdb.selected_inferior().read_memory(lo, WINDOW_LEN))
    d = {
        'rbp': rbp,
        'rsp': rsp,
        'buf': lo,
        'window': mem.hex(),
        'savedrbp': int.from_bytes(mem[RBP_TO_BUF:RBP_TO_BUF + 8], 'little'),
        'ret': int.from_bytes(mem[RBP_TO_BUF + 8:RBP_TO_BUF + 16], 'little'),
    }
    if HAS_CANARY:
        d['canary'] = int.from_bytes(mem[RBP_TO_BUF - 8:RBP_TO_BUF], 'little')
    return d


out = {'ok': False, 'error': 'unset'}
try:
    gdb.execute('set pagination off')
    gdb.execute('set confirm off')
    if MODE == 'remote':
        # qemu-user has the guest paused on its gdbstub. Connect, set the breakpoints,
        # then `continue` to them (the payload arrives via qemu's stdin, not `run`).
        gdb.execute('target remote :%s' % PORT, to_string=True)
        gdb.execute('break *(vuln+%d)' % OFF_A)
        gdb.execute('break *(vuln+%d)' % OFF_B)
        gdb.execute('continue', to_string=True)  # -> BP-A
    else:
        gdb.execute('break *(vuln+%d)' % OFF_A)
        gdb.execute('break *(vuln+%d)' % OFF_B)
        gdb.execute('run < %s' % PAYLOAD, to_string=True)  # -> BP-A
    before = capture()                       # BP-A: pristine
    gdb.execute('continue', to_string=True)  # read() consumes the payload -> BP-B
    after = capture()                        # BP-B: clobbered
    out = {'ok': True, 'before': before, 'after': after}
except Exception as e:  # noqa: BLE001 - any gdb failure means "unavailable"
    out = {'ok': False, 'error': str(e)}
finally:
    sys.stdout.write('GBXJSON' + json.dumps(out) + 'GBXEND\n')
    sys.stdout.flush()
