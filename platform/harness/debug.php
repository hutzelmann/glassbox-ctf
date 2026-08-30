<?php
// The glass-box debug dial, shared by every challenge page.
//
// Three cumulative levels, selected by the sticky ?debug=<n> URL parameter:
//   0  Off    the challenge exactly as shipped
//   1  Hints  the learner's input becomes an editor; symptom-side output only
//             (the error their own attempt provoked, timing they could measure)
//   2  Debug  the target's internals (the assembled query, the rows it returned,
//             the server source, the victim's rendered output)
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
    $levels = [0 => 'Off', 1 => 'Hints', 2 => 'Debug'];
    ?>
<select aria-label="Debug level" style="width:auto;margin:calc(var(--pico-nav-link-spacing-vertical) * -1) 0 0" onchange="var p=new URLSearchParams(window.location.search);this.value==='0'?p.delete('debug'):p.set('debug',this.value);var s=p.toString();window.location.replace(s?'?'+s:window.location.pathname)">
<?php foreach ($levels as $value => $label): ?>
 <option value="<?php echo $value; ?>"<?php echo $value === $debugLevel ? ' selected' : ''; ?>><?php echo $label; ?></option>
<?php endforeach; ?>
</select>
<?php
}
