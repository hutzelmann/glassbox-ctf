<?php
// The Fix editor. Generalized from a hardcoded critical.php target to a
// per-challenge glassbox.php config, so a family can point it at any
// critical.<ext>, run a build step on Save, and show extra editable inputs.
//
// glassbox.php (optional) returns an array:
//   [ 'target' => 'critical.c',            // file the main editor edits
//     'build'  => 'build.sh',              // run after writing on Save/Restore
//     'fields' => [ ['file' => 'build.flags', 'label' => 'Compiler flags'] ] ]
// Absent config => target critical.php, no build hook, no extra fields, which is
// exactly the original PHP behavior, existing challenges are unaffected.

require 'debug.php';   // $debugLevel, $debugSuffix, debug_switch()

$config = ['target' => 'critical.php', 'build' => null, 'fields' => []];
if (is_file(__DIR__ . '/glassbox.php')) {
    $loaded = require __DIR__ . '/glassbox.php';
    if (is_array($loaded)) {
        $config = array_merge($config, $loaded);
    }
}
$target = basename($config['target']);            // stay inside the web root
$build  = $config['build'] ? basename($config['build']) : null;
$fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];

// critical.c -> critical.orig.c ; build.flags -> build.orig.flags
function glassbox_orig(string $file): string {
    return preg_replace('/\.([^.]+)$/', '.orig.$1', $file, 1);
}

// Run the challenge's build hook (trusted, shipped by the challenge, not learner
// input). Returns [ok, combined output]. The hook is responsible for atomicity:
// it compiles to a temp path and only swaps the live artifact on success.
function glassbox_build(?string $build): array {
    if (!$build || !is_file(__DIR__ . '/' . $build)) {
        return [true, ''];
    }
    $out = [];
    $rc = 0;
    exec('cd ' . escapeshellarg(__DIR__) . ' && bash ' . escapeshellarg($build) . ' 2>&1', $out, $rc);
    return [$rc === 0, implode("\n", $out)];
}

$error = null;
$buildError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['restore'])) {
        $ok = @file_put_contents($target, file_get_contents(glassbox_orig($target))) !== false;
        foreach ($fields as $f) {
            $file = basename($f['file']);
            $ok = (@file_put_contents($file, file_get_contents(glassbox_orig($file))) !== false) && $ok;
        }
        if (!$ok) {
            $error = 'Failed to restore: permission denied.';
        } else {
            [$built, $bout] = glassbox_build($build);
            if (!$built) {
                $buildError = $bout;   // should not happen for the shipped original
            } else {
                header('Location: fix.php?restored=1' . ($debugLevel > 0 ? '&debug=' . $debugLevel : ''));
                exit;
            }
        }
    } elseif (isset($_POST['save']) && isset($_POST['content'])) {
        $ok = @file_put_contents($target, $_POST['content']) !== false;
        foreach ($fields as $f) {
            $file = basename($f['file']);
            if (isset($_POST['fields'][$f['file']])) {
                $ok = (@file_put_contents($file, $_POST['fields'][$f['file']]) !== false) && $ok;
            }
        }
        if (!$ok) {
            $error = 'Failed to save: permission denied.';
        } else {
            [$built, $bout] = glassbox_build($build);
            if (!$built) {
                // The source is saved and the editor keeps it, but the build failed
                // so the previous working artifact keeps running, never bricked.
                $buildError = $bout;
            } else {
                header('Location: fix.php?saved=1' . ($debugLevel > 0 ? '&debug=' . $debugLevel : ''));
                exit;
            }
        }
    }
}

$content = file_get_contents($target);
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Edit the critical code</title>
  <script src="codemirror-bundle.js" defer></script>
  <link rel="stylesheet" href="pico.min.css"/>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <hgroup>
       <h1>Edit the critical code</h1>
       <p>Repair the vulnerability</p>
      </hgroup>
      <nav>
       <ul></ul>
       <ul>
        <li><a href="./<?php echo $debugSuffix; ?>" role="button">Back</a></li>
       </ul>
      </nav>
     </div>
    </header>
    <?php if ($error): ?><p><mark><?php echo htmlspecialchars($error); ?></mark></p><?php endif; ?>
    <?php if ($buildError !== null): ?>
    <p><mark>Build failed, the previous working build is still running. Fix the errors and save again.</mark></p>
    <pre style="white-space:pre-wrap"><?php echo htmlspecialchars($buildError); ?></pre>
    <?php endif; ?>
    <?php if (!$error && $buildError === null && isset($_GET['saved'])): ?><p><ins>Saved and rebuilt successfully.</ins></p><?php endif; ?>
    <?php if (!$error && $buildError === null && isset($_GET['restored'])): ?><p><ins>Original code restored.</ins></p><?php endif; ?>
    <form method="POST">
     <textarea name="content" rows="16" style="font-family:monospace"><?php echo htmlspecialchars($content); ?></textarea>
     <?php foreach ($fields as $f): $file = basename($f['file']); ?>
     <label><?php echo htmlspecialchars($f['label'] ?? $file); ?>
      <textarea name="fields[<?php echo htmlspecialchars($f['file']); ?>]" rows="2" style="font-family:monospace"><?php echo htmlspecialchars(is_file($file) ? file_get_contents($file) : ''); ?></textarea>
     </label>
     <?php endforeach; ?>
     <div class="grid">
      <input type="submit" name="save" value="Save"/>
      <input type="submit" name="restore" value="Restore Original" class="secondary"/>
     </div>
    </form>
   </article>
  </main>
 </body>
</html>
