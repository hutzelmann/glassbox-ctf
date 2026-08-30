// remember-form-input.js: a comfort feature, NOT part of any challenge.
//
// Challenge pages re-render their forms empty after you submit, so coming back
// would mean retyping your payload every single time. This puts it back, and it
// survives moving the debug dial as well.
//
// There is no vulnerability in this file. Nothing here to exploit or to fix.
// The code you are meant to change always lives in critical.<ext>.
//
// How it works: on leaving a page, the values of every form are stashed in
// sessionStorage (per browser tab, gone when the tab closes). On loading a
// page, a field is refilled only if the server left it blank, so anything the
// server put there always wins. Add data-no-restore to a form or field to opt
// out.
//
// Loaded without `defer` on purpose: that registers the DOMContentLoaded
// listener below before any deferred script runs, so a restored value is in
// place before the CodeMirror editors read it.
(function () {
  "use strict";

  var PREFIX = "form-input:";

  function isCheckable(el) {
    var type = (el.type || "").toLowerCase();
    return type === "checkbox" || type === "radio";
  }

  // The fields a form actually submits and a learner can actually type into.
  function editableControls(form) {
    var skipped = { submit: 1, button: 1, reset: 1, image: 1, hidden: 1, file: 1 };
    var out = [];
    for (var i = 0; i < form.elements.length; i++) {
      var el = form.elements[i];
      var tag = el.tagName;
      if (tag !== "INPUT" && tag !== "TEXTAREA" && tag !== "SELECT") continue;
      if (!el.name || el.disabled) continue;
      if (skipped[(el.type || "").toLowerCase()]) continue;
      if (el.closest("[data-no-restore]")) continue;
      out.push(el);
    }
    return out;
  }

  function groupByName(controls) {
    var groups = {};
    controls.forEach(function (el) {
      (groups[el.name] = groups[el.name] || []).push(el);
    });
    return groups;
  }

  // Identity of a form: where it lives plus which fields it has. Deliberately
  // ignores the query string, so changing ?debug=<n> does not lose your input,
  // and ignores buttons and hidden fields, so a debug marker does not either.
  function keyFor(names) {
    if (!names.length) return null;
    return PREFIX + location.pathname + "|" + names.slice().sort().join(",");
  }

  // Announce a refill the way typing would. Pages wire fields to each other with
  // input/change listeners (the binary challenges mirror the hex payload into a
  // readable escape view that way), and those listeners have already run by the
  // time this fires, so a silent write would leave the mirror stale.
  function announce(el) {
    var type = isCheckable(el) || el.tagName === "SELECT" ? "change" : "input";
    el.dispatchEvent(new Event(type, { bubbles: true }));
  }

  // "The server left this blank": the one condition under which we may write.
  function isBlank(el) {
    if (el.tagName === "SELECT") {
      return el.value === "" || !el.querySelector("option[selected]");
    }
    return el.value === "";
  }

  function save(form) {
    var groups = groupByName(editableControls(form));
    var names = Object.keys(groups);
    var key = keyFor(names);
    if (!key) return;
    var data = {};
    names.forEach(function (name) {
      var els = groups[name];
      if (isCheckable(els[0])) {
        data[name] = els.filter(function (el) { return el.checked; })
                        .map(function (el) { return el.value; });
      } else if (els.length > 1) {
        data[name] = els.map(function (el) { return el.value; });
      } else {
        data[name] = els[0].value;
      }
    });
    sessionStorage.setItem(key, JSON.stringify(data));
  }

  function restore(form) {
    var groups = groupByName(editableControls(form));
    var names = Object.keys(groups);
    var key = keyFor(names);
    if (!key) return;
    var raw = sessionStorage.getItem(key);
    if (!raw) return;
    var data = JSON.parse(raw);
    if (!data || typeof data !== "object") return;
    names.forEach(function (name) {
      var els = groups[name];
      var stored = data[name];
      if (stored === undefined) return;
      if (isCheckable(els[0])) {
        var anyChecked = els.some(function (el) { return el.checked; });
        if (anyChecked || !Array.isArray(stored)) return;
        els.forEach(function (el) {
          if (stored.indexOf(el.value) === -1) return;
          el.checked = true;
          announce(el);
        });
      } else if (els.length > 1) {
        if (!Array.isArray(stored)) return;
        els.forEach(function (el, i) {
          if (!isBlank(el) || typeof stored[i] !== "string") return;
          el.value = stored[i];
          announce(el);
        });
      } else if (isBlank(els[0]) && typeof stored === "string") {
        els[0].value = stored;
        announce(els[0]);
      }
    });
  }

  // A convenience must never break the page it is helping: sessionStorage
  // throws outright when site data is blocked, and a stored entry may be
  // anything. Every form is handled on its own and every failure is dropped.
  function eachForm(handler) {
    for (var i = 0; i < document.forms.length; i++) {
      try {
        handler(document.forms[i]);
      } catch (e) {
        /* ignore: this feature is never worth an error */
      }
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    eachForm(restore);
  });

  // pagehide, not submit: it also covers plain links, the back button and the
  // debug dial's location.replace().
  window.addEventListener("pagehide", function () {
    eachForm(save);
  });
})();
