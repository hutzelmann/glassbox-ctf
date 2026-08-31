// Regenerates the README screenshots in assets/, in both colour schemes.
//
//   podman run --rm -d --name shots -p 9010:80 glassbox-sqli-login
//   node assets/shots.mjs            # writes assets/glassbox-{debug,fix}-{light,dark}.png
//   podman stop shots
//
// SHOT_BASE overrides the target (default http://localhost:9010), OUT_DIR the
// destination (default the directory holding this script).
//
// Run the challenge on a port nothing else is using. A container someone else
// started on the usual 9000 will happily answer and you will screenshot their
// build, which looks exactly like success. This script launches its own chromium
// with a throwaway profile for the same reason: a warm profile serves the
// previous `codemirror-bundle.js` from disk cache, so a rebuilt editor silently
// produces the old screenshots.
//
// Needs: node (>= 22, for the built-in WebSocket), chromium, and optionally
// ImageMagick + pngquant for the padding and compression pass.

import { execFileSync, spawn } from 'node:child_process';
import { createServer } from 'node:net';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = process.env.SHOT_BASE || 'http://localhost:9010';
const OUT = process.env.OUT_DIR || dirname(fileURLToPath(import.meta.url));
const VIEWPORT = 900;
const THEMES = ['light', 'dark'];

// A payload that breaks the query without solving the challenge: the level-2
// panels then show the assembled SQL and the database's own error, which is the
// glass box, while the README still gives no answer away.
const SUBMIT_BROKEN_PAYLOAD = `
  (() => {
    const f = document.createElement('form');
    f.method = 'POST'; f.action = './?debug=2';
    for (const [k, v] of [['username', "admin'"], ['password', 'hunter2']]) {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i);
    }
    document.body.appendChild(f); f.submit();
  })();
`;

const SHOTS = [
  { name: 'glassbox-debug', url: `${BASE}/?debug=2`, action: SUBMIT_BROKEN_PAYLOAD },
  { name: 'glassbox-fix', url: `${BASE}/fix.php?debug=2` },
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const has = (bin) => { try { execFileSync('which', [bin], { stdio: 'ignore' }); return true; } catch { return false; } };

const freePort = () => new Promise((resolve, reject) => {
  const srv = createServer();
  srv.on('error', reject);
  srv.listen(0, '127.0.0.1', () => { const { port } = srv.address(); srv.close(() => resolve(port)); });
});

class Session {
  constructor(ws) {
    this.ws = ws; this.id = 0; this.pending = new Map(); this.handlers = new Map();
    ws.addEventListener('message', (e) => {
      const msg = JSON.parse(e.data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        msg.error ? reject(new Error(JSON.stringify(msg.error))) : resolve(msg.result);
      } else if (msg.method && this.handlers.has(msg.method)) {
        this.handlers.get(msg.method).forEach((fn) => fn(msg.params));
      }
    });
  }
  send(method, params = {}) {
    const id = ++this.id;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }
  once(method) {
    return new Promise((resolve) => {
      const fn = (p) => {
        this.handlers.set(method, (this.handlers.get(method) || []).filter((f) => f !== fn));
        resolve(p);
      };
      this.handlers.set(method, [...(this.handlers.get(method) || []), fn]);
    });
  }
}

async function connect(cdp) {
  const target = await (await fetch(`${cdp}/json/new?about:blank`, { method: 'PUT' })).json();
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((r) => ws.addEventListener('open', r, { once: true }));
  const s = new Session(ws);
  await s.send('Page.enable');
  await s.send('Runtime.enable');
  return { s, targetId: target.id };
}

async function capture(cdp, { name, theme, url, action }) {
  const { s, targetId } = await connect(cdp);
  await s.send('Emulation.setDeviceMetricsOverride', { width: VIEWPORT, height: 900, deviceScaleFactor: 2, mobile: false });
  await s.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: theme }] });
  const loaded = s.once('Page.loadEventFired');
  await s.send('Page.navigate', { url });
  await loaded;
  if (action) {
    const next = s.once('Page.loadEventFired');
    await s.send('Runtime.evaluate', { expression: action });
    await next;
  }
  await sleep(1600); // let the deferred CodeMirror bundles mount

  // Clip to the card, not the viewport: pico centres a short page in a tall
  // viewport, so a naive full-page shot is mostly dead space.
  const { result } = await s.send('Runtime.evaluate', {
    returnByValue: true,
    expression: `JSON.stringify((() => {
      const card = document.querySelector('main.container > article') || document.body;
      const r = card.getBoundingClientRect();
      const pad = 18;
      return {
        x: Math.max(0, Math.floor(r.left - pad)),
        y: Math.max(0, Math.floor(r.top + window.scrollY - pad)),
        w: Math.ceil(r.width + pad * 2),
        h: Math.ceil(r.height + pad * 2),
      };
    })())`,
  });
  const { x, y, w, h } = JSON.parse(result.value);
  const shot = await s.send('Page.captureScreenshot', {
    format: 'png',
    captureBeyondViewport: true,
    clip: { x, y, width: w, height: h, scale: 1 },
  });
  writeFileSync(join(OUT, `${name}-${theme}.png`), Buffer.from(shot.data, 'base64'));
  console.log(`captured ${name}-${theme}.png (${w}x${h} css px)`);
  s.ws.close();
  await fetch(`${cdp}/json/close/${targetId}`);
}

// The README shows the shots side by side, so both of a theme must be the same
// height or the captions below them stop lining up. Pad with the page's own
// background, taken from a pixel inside the padding.
function padAndCompress() {
  if (!has('magick') || !has('pngquant')) {
    console.warn('skipping pad/compress: needs ImageMagick and pngquant');
    return;
  }
  for (const theme of THEMES) {
    const files = SHOTS.map((s) => join(OUT, `${s.name}-${theme}.png`));
    const size = (f, fmt) => Number(execFileSync('magick', ['identify', '-format', fmt, f]).toString());
    const height = Math.max(...files.map((f) => size(f, '%h')));
    const width = Math.max(...files.map((f) => size(f, '%w')));
    for (const f of files) {
      const bg = execFileSync('magick', [f, '-format', '%[pixel:p{2,2}]', 'info:']).toString().trim();
      execFileSync('magick', [f, '-background', bg, '-gravity', 'north', '-extent', `${width}x${height}`, f]);
      execFileSync('pngquant', ['--quality', '70-95', '--speed', '1', '--force', '--output', f, f]);
    }
  }
  console.log(`padded to a common size and compressed`);
}

const profile = mkdtempSync(join(tmpdir(), 'glassbox-shots-'));
const port = await freePort();
const chromium = spawn('chromium', [
  '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`,
  '--no-first-run', '--hide-scrollbars', '--disable-gpu', 'about:blank',
], { stdio: 'ignore' });

try {
  const cdp = `http://127.0.0.1:${port}`;
  for (let i = 0; ; i++) {
    try { await fetch(`${cdp}/json/version`); break; } catch (e) {
      if (i > 40) throw new Error('chromium did not open its debugging port');
      await sleep(250);
    }
  }
  for (const theme of THEMES) for (const shot of SHOTS) await capture(cdp, { ...shot, theme });
  padAndCompress();
} finally {
  // Wait for chromium to actually exit: it is still writing into its profile
  // while it shuts down, and removing the directory underneath it fails.
  chromium.kill();
  await new Promise((r) => { chromium.once('exit', r); setTimeout(r, 5000); });
  rmSync(profile, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
}
