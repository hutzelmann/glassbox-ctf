#!/bin/bash
# Symmetric cross-architecture self-test for the native family.
#
# The host-not-equal-target run path (see AGENTS.md and the openspec change
# native-binaries-any-host, decision D7) is direction-agnostic, so it is exercised on
# ANY host by building an aarch64-target binary and driving it under qemu-aarch64. That
# is the same code a student on an arm64 host hits in reverse (an x86-64 binary under
# qemu-x86_64). This test covers, with the real family code:
#   1. nrun_run()'s qemu-user path for a dynamic foreign binary (incl. QEMU_LD_PREFIX)
#   2. fatal-signal detection under emulation
#   3. gdb-dump.py's remote qemu-gdbstub frame capture with arch-generic registers
#
# It does NOT re-prove the x86-64 stack-frame interpretation (that is unchanged and
# already exercised natively) or the arm64-host/x86-64-binary environment itself (that
# is the one remaining Apple-Silicon sign-off). Exits 0 on success, non-zero on failure.
#
# Run inside the native family image, mounting the working tree so it tests the current
# helpers:
#   podman run --rm -v "$PWD/platform/native:/src:ro,Z" glassbox-native bash /src/selftest.sh
set -u

NRUN="${NRUN:-/src/native-run.php}"
GDBDUMP="${GDBDUMP:-/src/gdb-dump.py}"
CC=aarch64-linux-gnu-gcc
QEMU=qemu-aarch64-static
PREFIX=/usr/aarch64-linux-gnu

W="$(mktemp -d)"
cd "$W" || exit 1
fail() { echo "SELFTEST FAIL: $*" >&2; exit 1; }
for t in "$CC" "$QEMU" gdb-multiarch php; do
  command -v "$t" >/dev/null 2>&1 || fail "missing tool: $t"
done
[ -f "$NRUN" ] || fail "native-run.php not found at $NRUN"
[ -f "$GDBDUMP" ] || fail "gdb-dump.py not found at $GDBDUMP"

# 1) A dynamic aarch64 binary runs under qemu-user through nrun_run().
cat > ok.c <<'EOF'
#include <unistd.h>
int main(void) { write(1, "GBX_SELFTEST_OK\n", 16); return 0; }
EOF
"$CC" -o ok ok.c || fail "cross-compile ok.c"
out="$(NRUN="$NRUN" BIN="$W/ok" php -r '
require getenv("NRUN");
$b = getenv("BIN");
if (nrun_bin_arch($b) !== "aarch64") { fwrite(STDERR, "arch=".nrun_bin_arch($b)); exit(2); }
if (!nrun_is_emulated($b))          { fwrite(STDERR, "not emulated");          exit(3); }
$r = nrun_run($b, "");
echo $r["stdout"];
if ($r["crashed"]) exit(4);
' 2>err)" || fail "nrun_run(ok) [$(cat err)]"
echo "$out" | grep -q GBX_SELFTEST_OK || fail "no marker (got: $out)"
echo "PASS 1/3: dynamic aarch64 binary runs under qemu via nrun_run (QEMU_LD_PREFIX)"

# 2) A guest SIGSEGV is detected under emulation.
cat > crash.c <<'EOF'
int main(void) { volatile int *p = 0; return *p; }
EOF
"$CC" -o crash crash.c || fail "cross-compile crash.c"
NRUN="$NRUN" BIN="$W/crash" php -r '
require getenv("NRUN");
$r = nrun_run(getenv("BIN"), "");
fwrite(STDERR, "crashed=".($r["crashed"]?1:0)." sig=".($r["signal"]??0)."\n");
exit($r["crashed"] && ($r["signal"] ?? 0) === 11 ? 0 : 5);
' 2>err || fail "segfault not detected under qemu [$(cat err)]"
echo "PASS 2/3: guest SIGSEGV detected under qemu [$(tr -d '\n' <err)]"

# 3) gdb-dump.py remote mode captures a frame over qemu's gdbstub (arch-generic regs).
cat > vuln.c <<'EOF'
#include <unistd.h>
void vuln(void) { char buf[16]; read(0, buf, 64); }
int main(void) { vuln(); return 0; }
EOF
"$CC" -O0 -g -w -o vuln vuln.c || fail "cross-compile vuln.c"   # -w: the overflow is the point
port=$(( (RANDOM % 20000) + 20000 ))   # ephemeral port; container is single-process
QEMU_LD_PREFIX="$PREFIX" "$QEMU" -g "$port" "$W/vuln" </dev/null >/dev/null 2>&1 &
qpid=$!
sleep 0.4
gout="$(BP_OFF_A=0 BP_OFF_B=4 RBP_TO_BUF=16 WINDOW_LEN=32 HAS_CANARY=0 \
  GBX_MODE=remote GBX_PORT="$port" PAYLOAD_PATH=/dev/null \
  gdb-multiarch -q -batch -nx -x "$GDBDUMP" "$W/vuln" 2>/dev/null)"
kill -9 "$qpid" 2>/dev/null
echo "$gout" | grep -q '"ok": true' || fail "remote gdb capture did not return ok [$gout]"
echo "PASS 3/3: gdb-dump.py remote capture over qemu gdbstub (arch-generic regs)"

rm -rf "$W"
echo "SELFTEST OK"
