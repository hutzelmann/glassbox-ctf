<?php
require 'native-run.php';

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$debugSuffix = $debug ? '?debug=1' : '';
$BIN = __DIR__ . '/ret2win';
$BUFSIZE = 16; // char buf[16] in critical.c, the stack table labels regions from this

// The binary only ever prints this decoy marker, never the real flag, so the
// downloadable binary and the Program view cannot leak it. The interface maps the
// decoy to the real flag, which lives here server-side (ret2win gives no way to
// read it).
$DECOY = 'N0tTh3Fl4gR34lly';
$FLAG  = 'R3turn2Th3W1n';

$payloadHex = $_POST['payload_hex'] ?? '';
$ran = false;
$won = false;
$run = null;
$payload = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $payloadHex !== '') {
    $payload = nrun_decode_hex($payloadHex);
    $run = nrun_run($BIN, $payload);
    $ran = true;
    $won = (strpos($run['stdout'], $DECOY) !== false);
}
$winAddr = nrun_symbol_addr($BIN, 'win');
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>ret2win</title>
  <link rel="stylesheet" href="pico.min.css"/>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <hgroup>
       <h1>ret2win</h1>
       <p>Overflow the buffer, overwrite the return address, land in <code>win()</code>.</p>
      </hgroup>
      <nav>
       <ul></ul>
       <ul>
        <li>
         <label>
          <input type="checkbox" role="switch"<?php echo $debug ? ' checked' : ''; ?> onchange="var p=new URLSearchParams(window.location.search);this.checked?p.set('debug','1'):p.delete('debug');var s=p.toString();window.location.replace(s?'?'+s:window.location.pathname)"/>
         </label>
        </li>
        <li><a href="fix.php<?php echo $debugSuffix; ?>" role="button">Fix</a></li>
       </ul>
      </nav>
     </div>
    </header>

    <p>There is a <code>win()</code> function that prints the flag, but nothing calls
       it. Make the program call it anyway. <a href="ret2win" download>Download the
       binary</a> and analyse it.</p>

    <form method="POST" action="./<?php echo $debugSuffix; ?>">
     <label>Payload (text and <code>\xNN</code> escapes)
      <textarea id="esc" rows="2" style="font-family:monospace" placeholder="e.g. hi\x41\x42\x0a"></textarea>
     </label>
     <label>Payload as hex (this is what is sent)
      <textarea id="hex" name="payload_hex" rows="2" style="font-family:monospace" placeholder="e.g. 686941420a"><?php echo htmlspecialchars($payloadHex); ?></textarea>
     </label>
     <label>…or load a payload file
      <input type="file" id="up"/>
     </label>
     <input type="submit" value="Send bytes"/>
    </form>

    <?php if ($ran): ?>
    <hr/>
    <?php if ($won): ?>
    <p><ins>You reached <code>win()</code>! Flag: <strong><?php echo htmlspecialchars($FLAG); ?></strong> 🎉</ins></p>
    <?php elseif ($run['timedout']): ?>
    <p><mark>The program was killed (timeout / resource limit).</mark></p>
    <?php elseif (($run['signal'] ?? 0) === 6): ?>
    <p><mark>The program aborted (signal 6), a stack canary or other runtime check
       caught the overflow before the function could return. See the error output below.</mark></p>
    <?php elseif ($run['crashed']): ?>
    <p><mark>Segmentation fault (signal <?php echo (int)($run['signal'] ?? 11); ?>),
       you corrupted the stack, but execution did not land in <code>win()</code>.</mark></p>
    <?php else: ?>
    <p>The program returned normally. Your bytes did not reach the return address.</p>
    <?php endif; ?>
    <strong>Program output</strong>
    <pre style="white-space:pre-wrap"><?php echo htmlspecialchars($run['stdout'] !== '' ? $run['stdout'] : '(no output)'); ?></pre>
    <?php if (trim($run['stderr']) !== ''): ?>
    <strong>Error output</strong>
    <pre style="white-space:pre-wrap"><?php echo htmlspecialchars($run['stderr']); ?></pre>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($debug): ?>
    <hr/>
    <article style="margin-bottom:var(--pico-spacing)">
     <small><strong>Endianness helper.</strong> Type a hex address on one side to get the little-endian bytes for your payload (and back).</small>
     <div class="grid">
      <label>big-endian (as written)
       <input type="text" id="be-conv" placeholder="e.g. 1234" style="font-family:monospace"/>
      </label>
      <label>little-endian (payload bytes)
       <input type="text" id="le-conv" placeholder="e.g. 3412000000000000" style="font-family:monospace"/>
      </label>
     </div>
    </article>
    <?php
    $activeTab = 'stack';
    $dbgTabs = [
        'stack'    => 'Your bytes',
        'checksec' => 'checksec',
        'disasm'   => 'Disassembly',
        'maps'     => 'Memory map',
        'prog'     => 'Program',
        'symbols'  => 'Symbols',
    ];
    ?>
    <div role="group" id="dbg-tabs">
     <?php foreach ($dbgTabs as $id => $label): ?>
     <button type="button" data-tab="<?php echo $id; ?>"<?php echo $id === $activeTab ? '' : ' class="outline"'; ?>><?php echo htmlspecialchars($label); ?></button>
     <?php endforeach; ?>
    </div>

    <section data-panel="stack">
     <?php if ($ran): ?>
     <p><small>Hexdump of the <?php echo strlen($payload); ?> bytes the server received:</small></p>
     <pre style="overflow-x:auto"><?php echo htmlspecialchars(nrun_hexdump($payload)); ?></pre>
     <?php echo nrun_stack_table($BIN, $payload, $BUFSIZE, [
        'origRet'   => nrun_return_addr($BIN),
        'hasCanary' => (nrun_checksec($BIN)['Canary'] ?? 'No') === 'Yes',
     ]); ?>
     <?php else: ?>
     <p><small>The stack frame your input runs off the end of. Your bytes start at
        <code>+0</code>; fill the buffer and the saved RBP, and the 8 bytes at the
        highlighted <strong>saved return address</strong> become where the CPU jumps.
        Put <code><?php echo htmlspecialchars($winAddr ?? '?'); ?></code>
        (little-endian) there and send, and your bytes appear laid onto this frame.</small></p>
     <?php echo nrun_frame_diagram($BUFSIZE); ?>
     <?php endif; ?>
    </section>

    <section data-panel="checksec" hidden>
     <figure><table>
      <tbody>
      <?php foreach (nrun_checksec($BIN) as $k => $v): ?>
       <tr><th><?php echo htmlspecialchars($k); ?></th><td><?php echo htmlspecialchars($v); ?></td></tr>
      <?php endforeach; ?>
      </tbody>
     </table></figure>
     <p><small>Enable a protection in the <a href="fix.php<?php echo $debugSuffix; ?>">Fix</a>
        editor's compiler-flags field and watch this change, a stack canary
        (<code>-fstack-protector</code>) stops this exploit without any source edit.</small></p>
    </section>

    <section data-panel="disasm" hidden>
     <pre style="overflow-x:auto"><?php echo htmlspecialchars(nrun_disasm($BIN, ['vuln', 'win', 'main'])); ?></pre>
    </section>

    <section data-panel="maps" hidden>
     <?php $maps = nrun_maps($BIN); ?>
     <?php if ($maps): ?>
     <figure><table>
      <thead><tr><th>region</th><th>address range</th><th>perms</th></tr></thead>
      <tbody>
      <?php foreach ($maps as $r): ?>
       <tr><td><?php echo htmlspecialchars($r['label']); ?></td><td><code><?php echo htmlspecialchars($r['range']); ?></code></td><td><code><?php echo htmlspecialchars($r['perms']); ?></code></td></tr>
      <?php endforeach; ?>
      </tbody>
     </table></figure>
     <?php else: ?>
     <p><small>Memory map unavailable in this environment.</small></p>
     <?php endif; ?>
     <p><small>Where this program is loaded in memory this run. With
        <code>-no-pie</code> (the default here) the base is fixed, which is why
        <code>win()</code>'s address is constant. Enable PIE (<code>-fpie -pie</code>)
        in the Fix editor and, with the host's ASLR on, reload a few times, the
        program and its code move each run, so a hard-coded address no longer works.</small></p>
    </section>

    <section data-panel="prog" hidden>
     <p><small>The full program (<code>main.c</code>). You edit only <code>critical.c</code>.</small></p>
     <pre style="overflow-x:auto"><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/main.c')); ?></pre>
    </section>

    <section data-panel="symbols" hidden>
     <p><code>win</code> is at <mark><?php echo htmlspecialchars($winAddr ?? 'unknown'); ?></mark>.</p>
     <figure><table>
      <thead><tr><th>address</th><th>type</th><th>symbol</th></tr></thead>
      <tbody>
      <?php foreach (nrun_symbols($BIN) as $s): if (!in_array($s['name'], ['win','vuln','main'], true)) continue; ?>
       <tr><td><code>0x<?php echo htmlspecialchars($s['addr']); ?></code></td><td><?php echo htmlspecialchars($s['type']); ?></td><td><code><?php echo htmlspecialchars($s['name']); ?></code></td></tr>
      <?php endforeach; ?>
      </tbody>
     </table></figure>
    </section>
    <?php endif; ?>
   </article>
  </main>

  <script>
  (function () {
    var esc = document.getElementById('esc');
    var hex = document.getElementById('hex');
    var up = document.getElementById('up');

    function hexToBytes(h) {
      h = h.replace(/[^0-9a-fA-F]/g, '');
      if (h.length % 2) h = h.slice(0, -1);
      var a = [];
      for (var i = 0; i < h.length; i += 2) a.push(parseInt(h.substr(i, 2), 16));
      return a;
    }
    function bytesToHex(b) {
      return b.map(function (x) { return (x & 0xff).toString(16).padStart(2, '0'); }).join('');
    }
    function escToBytes(s) {
      var b = [];
      for (var i = 0; i < s.length; i++) {
        if (s[i] === '\\' && i + 1 < s.length) {
          var n = s[i + 1];
          if (n === 'x') { b.push(parseInt(s.substr(i + 2, 2), 16) || 0); i += 3; }
          else if (n === 'n') { b.push(10); i++; }
          else if (n === 't') { b.push(9); i++; }
          else if (n === 'r') { b.push(13); i++; }
          else if (n === '0') { b.push(0); i++; }
          else if (n === '\\') { b.push(92); i++; }
          else { b.push(s.charCodeAt(i)); }
        } else {
          b.push(s.charCodeAt(i) & 0xff);
        }
      }
      return b;
    }
    function bytesToEsc(b) {
      return b.map(function (x) {
        return (x >= 0x20 && x < 0x7f && x !== 0x5c) ? String.fromCharCode(x) : ('\\x' + x.toString(16).padStart(2, '0'));
      }).join('');
    }

    if (esc) esc.addEventListener('input', function () { hex.value = bytesToHex(escToBytes(esc.value)); });
    if (hex) hex.addEventListener('input', function () { esc.value = bytesToEsc(hexToBytes(hex.value)); });
    // On load, if hex was preserved from a POST, mirror it into the escape view.
    if (hex && hex.value) esc.value = bytesToEsc(hexToBytes(hex.value));

    if (up) up.addEventListener('change', function () {
      var f = up.files[0];
      if (!f) return;
      var r = new FileReader();
      r.onload = function () {
        var b = Array.from(new Uint8Array(r.result));
        hex.value = bytesToHex(b);
        esc.value = bytesToEsc(b);
      };
      r.readAsArrayBuffer(f);
    });
  })();

  // Debug tabs: a click shows one panel and hides the rest.
  (function () {
    var bar = document.getElementById('dbg-tabs');
    if (!bar) return;
    var buttons = bar.querySelectorAll('button');
    var panels = document.querySelectorAll('[data-panel]');
    buttons.forEach(function (b) {
      b.addEventListener('click', function () {
        buttons.forEach(function (x) { x.classList.add('outline'); });
        b.classList.remove('outline');
        var id = b.getAttribute('data-tab');
        panels.forEach(function (p) { p.hidden = (p.getAttribute('data-panel') !== id); });
      });
    });
  })();

  // Big-endian <-> little-endian helper (live sync).
  (function () {
    var be = document.getElementById('be-conv');
    var le = document.getElementById('le-conv');
    if (!be || !le) return;
    function norm(h) { return h.replace(/[^0-9a-fA-F]/g, '').toLowerCase(); }
    function revBytes(h) {
      if (h.length % 2) h = '0' + h;
      var out = '';
      for (var i = h.length - 2; i >= 0; i -= 2) out += h.substr(i, 2);
      return out;
    }
    be.addEventListener('input', function () {
      var h = norm(be.value);
      if (h === '') { le.value = ''; return; }
      if (h.length < 16) h = h.padStart(16, '0');
      le.value = revBytes(h);
    });
    le.addEventListener('input', function () {
      var h = norm(le.value);
      if (h === '') { be.value = ''; return; }
      var b = revBytes(h).replace(/^(?:00)+/, '');
      be.value = b === '' ? '0' : b;
    });
  })();
  </script>
 </body>
</html>
