<?php
// The glass-box debug dial, shared by every challenge page.
//
// Three cumulative levels, selected by the sticky ?debug=<n> URL parameter:
//   0  Challenge  the challenge exactly as shipped, the way a real target gives it
//   1  Hints      the learner's input becomes an editor; symptom-side output only
//                 (the error their own attempt provoked, timing they could measure)
//   2  Debug      the target's internals (the assembled query, the rows it returned,
//                 the server source, the victim's rendered output)
//
// Placing a panel: level 1 tells the learner *how their own attempt failed*;
// level 2 discloses *what the target is doing*. Anything a learner could not
// already read in their own browser belongs at level 2.
//
// Anything unparseable, negative, or too large lands on the nearest valid level:
// (int) turns 'banana' and '' into 0, and the clamp handles 7 and -1.
$debugLevel = max(0, min(2, (int)($_GET['debug'] ?? 0)));

// Appended to internal links so the level survives navigation.
$debugSuffix = $debugLevel > 0 ? '?debug=' . $debugLevel : '';

// Renders the header control. Every level is one interaction away.
function debug_switch(): void
{
    global $debugLevel;
    $levels = [0 => 'Challenge', 1 => 'Hints', 2 => 'Debug'];
    ?>
<select id="gb-debug-dial" aria-label="Debug level" style="width:auto;margin:calc(var(--pico-nav-link-spacing-vertical) * -1) 0 0">
<?php foreach ($levels as $value => $label): ?>
 <option value="<?php echo $value; ?>"<?php echo $value === $debugLevel ? ' selected' : ''; ?>><?php echo $label; ?></option>
<?php endforeach; ?>
</select>
<script>
(function () {
  var d = document.getElementById('gb-debug-dial');
  if (!d) return;
  // A form counts as "submitted" (re-runnable) when it carries a filled data input.
  function filled(form) {
    var els = form.querySelectorAll('input, textarea');
    for (var i = 0; i < els.length; i++) {
      var t = (els[i].type || 'text').toLowerCase();
      if (t === 'submit' || t === 'button' || t === 'hidden' || t === 'file' ||
          t === 'checkbox' || t === 'radio') continue;
      if ((els[i].value || '').trim() !== '') return true;
    }
    return false;
  }
  d.addEventListener('change', function () {
    var v = this.value;
    // Default: re-run the last submission so the learner's result survives the level
    // change in one click. Re-POST the first POST form that has input and has not opted
    // out; a form whose submit is not safe to replay marks itself data-debug-no-resubmit.
    var forms = document.querySelectorAll('form[method="post" i]');
    for (var i = 0; i < forms.length; i++) {
      var f = forms[i];
      if (f.hasAttribute('data-debug-no-resubmit') || !filled(f)) continue;
      f.action = v === '0' ? './' : './?debug=' + v;
      f.submit();
      return;
    }
    // Nothing to re-run: change the level in place, preserving other query params so a
    // GET form's submission is kept.
    var p = new URLSearchParams(window.location.search);
    v === '0' ? p.delete('debug') : p.set('debug', v);
    var s = p.toString();
    window.location.replace(s ? '?' + s : window.location.pathname);
  });
})();
</script>
<?php
}
