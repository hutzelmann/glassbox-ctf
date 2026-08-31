# gdb-Python stack-frame dumper for the native binary family.
#
# native-run.php runs this under `gdb -batch` to capture the REAL contents of the
# vulnerable frame while the binary runs, at two points inside vuln():
#   BP-A = the `call read` instruction   -> the pristine frame (real saved return
#          address, real saved RBP, real canary, uninitialized buffer)
#   BP-B = the instruction right after it -> the frame after read(), i.e. the
#          learner's payload laid over those slots (captured before the epilogue's
#          canary check, so a clobbered canary is still visible even when it aborts)
#
# It reads a window relative to the real $rbp at each stop and prints ONE JSON
# object between GBXJSON / GBXEND markers on stdout. It is best-effort: on any
# failure it prints {"ok": false, ...} so the PHP caller degrades to the
# payload-derived model instead of erroring.
#
# All parameters arrive via the environment (set by native-run.php):
#   BP_OFF_A / BP_OFF_B  byte offsets from vuln's start to BP-A / BP-B
#   RBP_TO_BUF           bytes from $rbp down to the buffer start (from the `lea`)
#   WINDOW_LEN           total bytes to read, from buf start upward (covers the
#                        buffer, saved RBP, saved return address, and any payload
#                        that ran past the frame, e.g. a ROP chain)
#   HAS_CANARY           "1" when the binary has a stack canary
#   PAYLOAD_PATH         file whose bytes feed the binary's stdin
import gdb, json, os, sys


def env_int(name):
    return int(os.environ[name])


OFF_A = env_int('BP_OFF_A')
OFF_B = env_int('BP_OFF_B')
RBP_TO_BUF = env_int('RBP_TO_BUF')
WINDOW_LEN = env_int('WINDOW_LEN')
HAS_CANARY = os.environ.get('HAS_CANARY') == '1'
PAYLOAD = os.environ['PAYLOAD_PATH']


def capture():
    rbp = int(gdb.parse_and_eval('$rbp')) & 0xffffffffffffffff
    rsp = int(gdb.parse_and_eval('$rsp')) & 0xffffffffffffffff
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
    gdb.execute('break *(vuln+%d)' % OFF_A)
    gdb.execute('break *(vuln+%d)' % OFF_B)
    gdb.execute('run < %s' % PAYLOAD, to_string=True)
    before = capture()                       # BP-A: pristine
    gdb.execute('continue', to_string=True)  # read() consumes the payload
    after = capture()                        # BP-B: clobbered
    out = {'ok': True, 'before': before, 'after': after}
except Exception as e:  # noqa: BLE001 - any gdb failure means "unavailable"
    out = {'ok': False, 'error': str(e)}
finally:
    sys.stdout.write('GBXJSON' + json.dumps(out) + 'GBXEND\n')
    sys.stdout.flush()
