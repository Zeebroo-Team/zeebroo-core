'use strict';

// ── Offline mode ──────────────────────────────────────────────────────────
// Sits between the renderer and the API (main.js routes every 'api-request'
// through handle()). While the server is reachable it is a pass-through that
// also keeps a local copy of what the POS needs. When the internet drops:
//
//   - GETs are answered from disk: the full product catalog (searched and
//     paginated locally), customers, and the screens of every tab (downloaded
//     in the background, plus every screen the user opened), searched and
//     filtered locally;
//   - POST /online/checkout is saved to an outbox on disk and a local receipt
//     is returned, so the cashier keeps selling;
//   - any other change (bills, employees, invoices, service requests,
//     settings, …) is kept on this PC, shown at once, and uploaded in order;
//   - a background worker uploads the outbox when the server is back. Every
//     sale carries a client_uuid, so a retry can never create a duplicate.
//
// Everything lives in <userData>/offline/<business>-<branch>/ as JSON files
// written atomically, so it survives the PC being switched off. The server
// accepts offline sales up to 7 days old (PosOfflineSaleApiController::MAX_AGE_DAYS).

const fs     = require('fs');
const path   = require('path');
const crypto = require('crypto');

const MAX_OFFLINE_DAYS   = 7;
const PROBE_INTERVAL_MS  = 15 * 1000;        // how often to check while offline
const CATALOG_REFRESH_MS = 15 * 60 * 1000;   // background catalog refresh while online
const PRODUCT_REFRESH_MS = 3 * 60 * 1000;    // changed products only (prices / stock from other tills)
const OUTBOX_RETRY_MS    = 60 * 1000;        // retry pending sales after a server error
const CACHE_MAX_ENTRIES  = 2500;
const CACHE_MAX_BYTES    = 6 * 1024 * 1024;  // skip caching single responses bigger than this
// The screen cache is split over this many files, so saving one screen
// rewrites a small file instead of every screen at once.
const CACHE_SHARDS       = 32;
const SYNCED_KEEP_MS     = 3 * 24 * 3600 * 1000;
const BY_IDS_CHUNK       = 100;
// Signing in offline reuses the last online sign-in on this PC, for this long.
const OFFLINE_LOGIN_DAYS = 30;

// POS screens downloaded with the catalog so they open offline even if the
// user never visited them since the last restart. Paths must match api.js exactly.
const WARM_PATHS = [
  '/auth/me', '/me', '/businesses', '/online/branches', '/online/settings', '/online/features',
  '/online/invoice-setup', '/accounts', '/banks', '/bank-types', '/counters', '/customer-categories',
  '/cash-drawer', '/today-summary', '/eod', '/sales?q=', '/online/return-reasons', '/sale-reverts/reasons',
  '/online/sale-campaigns/products', '/service/pos/catalog?q=&category=',
  '/gift-cards?q=&status=', '/coupons?q=&status=',
  '/product-rentals?q=&status=all', '/product-rentals?q=&status=active', '/product-rentals?q=&status=overdue',
  // Home dashboard
  '/finance/flow', '/finance/overview', '/business-flow/summary', '/expenses/overview', '/expenses/bills',
  '/hr/employees', '/hr/departments', '/hr/payroll/cycles', '/crm/projects', '/notifications/settings',
];
// Every other tab (Home, Sales, Inventory, Finance, HR, Services, Settings):
// list screens downloaded too, refreshed less often than the POS ones.
const MODULE_WARM_PATHS = [
  // Sales
  '/invoices?q=&status=all', '/quotations?q=&status=all', '/sales-orders?q=&status=all',
  '/subscriptions?q=&status=all', '/sales/pending-credits', '/sale-reverts?page=1', '/sale-cheques?page=1',
  // Inventory
  '/categories?q=&status=&page=1', '/categories/parent-options', '/brands?q=&status=', '/discounts?q=&status=',
  '/sale-campaigns?q=&status=', '/gift-card-groups?q=&status=', '/stock-audits?page=1', '/stock-transfers?page=1&q=',
  '/cheques?filter=all', '/grns?q=&payment=all', '/grn-permissions', '/purchase-orders?q=&status=',
  '/suppliers?q=&page=1', '/supplier-categories', '/units', '/online/barcodes/products',
  // Finance
  '/loans', '/rentals', '/properties', '/expenses/rentals', '/expenses/modifications', '/expenses/investments',
  '/budgets', '/profit-report?period=30', '/payroll-overview', '/expenses/bill-assignment-targets',
  // HR
  '/hr/job-titles', '/hr/leave-requests', '/hr/leave-requests?status=pending', '/hr/leave-policies', '/hr/settings',
  '/hr/allowance-types', '/hr/payroll/rule-sets', '/hr/payroll/templates',
  // Services
  '/service/requests?q=&status=', '/service/catalog?q=', '/service/categories',
  // Settings, notifications, Home CRM card
  '/users', '/roles', '/branches', '/cashiers', '/mail/settings',
  '/notifications?limit=5', '/notifications?limit=1', '/notifications?status=all&limit=100', '/crm/overview',
];
const MODULE_WARM_MS = 30 * 60 * 1000;
// Lists whose most recent records are also downloaded in full (GET <list>/<id>),
// so their detail screens open offline.
const DETAIL_WARM_LISTS = [
  '/hr/employees', '/hr/departments', '/hr/payroll/cycles', '/hr/payroll/rule-sets',
  '/expenses/bills', '/loans', '/rentals', '/expenses/modifications', '/expenses/investments', '/budgets',
  '/invoices?q=&status=all', '/quotations?q=&status=all', '/sales-orders?q=&status=all',
  '/purchase-orders?q=&status=', '/grns?q=&payment=all', '/suppliers?q=&page=1',
  '/stock-audits?page=1', '/stock-transfers?page=1&q=', '/service/catalog?q=',
];
const DETAIL_WARM_MAX = 25;
const DETAIL_WARM_MS  = 6 * 3600 * 1000;
// Screens the user opened (with their own dates and filters) are refreshed too.
const LEARNED_WARM_MAX  = 200;
const LEARNED_WARM_MS   = 60 * 60 * 1000;
const LEARNED_KEEP_MS   = 14 * 24 * 3600 * 1000;
// How many of the most recent sales to keep in full (for returns / voids offline).
const WARM_SALE_DETAILS = 30;
// Per-user (not per-business) reads — also kept in the signed-in user's own cache,
// because they are read before a business is picked.
const USER_PATHS = ['/auth/me', '/me', '/businesses'];

// Keys the renderer adds to a checkout body only for offline use; never sent to the server.
const OFFLINE_ONLY_ITEM_KEYS = ['offline_unit_price', 'offline_name'];
const OFFLINE_ONLY_BODY_KEYS = ['offline_summary'];

function isUnreachable(status) {
  return status === 0 || status === 502 || status === 503 || status === 504;
}

function splitPath(p) {
  const i = p.indexOf('?');
  return i < 0 ? [p, new URLSearchParams()] : [p.slice(0, i), new URLSearchParams(p.slice(i + 1))];
}

function readJson(file, fallback) {
  try {
    if (fs.existsSync(file)) return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch (err) {
    console.error('[offline] could not read', file, err.message);
  }
  return fallback;
}

// Write to a temp file, flush it to disk, then rename over the old file — a
// power cut mid-write leaves the previous version intact instead of a half file.
function writeJsonAtomic(file, data) {
  const tmp = file + '.tmp';
  const fd = fs.openSync(tmp, 'w');
  try {
    fs.writeSync(fd, JSON.stringify(data));
    fs.fsyncSync(fd);
  } finally {
    fs.closeSync(fd);
  }
  fs.renameSync(tmp, file);
}

async function writeJsonAtomicAsync(file, data) {
  const tmp = `${file}.${process.pid}-${Date.now()}.tmp`;
  const fh = await fs.promises.open(tmp, 'w');
  try {
    await fh.writeFile(JSON.stringify(data));
    await fh.sync();
  } finally {
    await fh.close();
  }
  await fs.promises.rename(tmp, file);
}

const sleep = ms => new Promise(r => setTimeout(r, ms));

function tokenKey(token) {
  return token ? crypto.createHash('sha256').update(String(token)).digest('hex').slice(0, 24) : null;
}

function passwordHash(password, salt) {
  return crypto.scryptSync(String(password), salt, 32).toString('hex');
}

function passwordMatches(password, entry) {
  const a = Buffer.from(passwordHash(password, entry.salt), 'hex');
  const b = Buffer.from(entry.hash, 'hex');
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

const CUSTOMER_FIELDS = ['name', 'phone', 'email', 'address', 'notes', 'customer_type', 'customer_category_id'];

function pick(obj, keys) {
  const out = {};
  keys.forEach(k => { if (obj && obj[k] !== undefined) out[k] = obj[k]; });
  return out;
}

function shardOf(key) {
  let h = 0;
  for (let i = 0; i < key.length; i += 1) h = (h * 31 + key.charCodeAt(i)) | 0;
  return Math.abs(h) % CACHE_SHARDS;
}

// The screen cache, oldest first (the order evictions go by). Reads the
// shard files plus the single cache.json older versions wrote.
function loadCache(dir) {
  const entries = Object.entries(readJson(path.join(dir, 'cache.json'), {}));
  const shardDir = path.join(dir, 'cache');
  if (fs.existsSync(shardDir)) {
    for (const f of fs.readdirSync(shardDir)) {
      if (/^shard-\d+\.json$/.test(f)) entries.push(...Object.entries(readJson(path.join(shardDir, f), {})));
    }
  }
  entries.sort((a, b) => (a[1]?.at || 0) - (b[1]?.at || 0));
  return Object.fromEntries(entries);
}

// ── Generic offline changes (every tab other than the POS screens) ──────
// Ids made on this PC for records added offline: negative and far below any
// real number a form sends, so they can be found and swapped once uploaded.
const TEMP_ID_MAX = -1e11;
const isTempId = v => (typeof v === 'number' && v <= TEMP_ID_MAX) || (typeof v === 'string' && /^-\d{12,}$/.test(v));
const TEMP_ID_IN_PATH = /\/-\d{12,}(?=\/|$|\?)/;

// Writes that only make sense with the server there (AI, e-mail, sign-in,
// backups, imports that report per-row results, …): answered "needs internet".
const ONLINE_ONLY_WRITES = new RegExp([
  '^/(auth|backup|guide|businesses|developers|crm/lead-imports|online/barcodes)(/|$)',
  '^/mail/(send|sync|test|verify-credentials)$',
  '/(generate-ai|ai-chat|ai-content|ai-fill|scan|preview|stock-check|generate-skus|enable-share|notify|import|install|force-delete)$',
].join('|'));

// POSTs to a collection that are actions, not "add a record".
const COLLECTION_ACTIONS = /^(read-all|clear-all|reorder|positions|actions|resolve|sync|send|test)$/;

// Status a record shows right away after an action taken offline.
const ACTION_STATUS = {
  'mark-sent': 'sent', 'mark-paid': 'paid', 'mark-overdue': 'overdue', cancel: 'cancelled',
  accept: 'accepted', reject: 'rejected', confirm: 'confirmed', complete: 'completed', approve: 'approved',
};

// Which record a write is about: /hr/employees/12/access → collection
// /hr/employees, id 12, rest ['access'].
function parseTarget(method, pathname) {
  const segs = pathname.split('/').filter(Boolean);
  let idx = -1;
  for (let i = segs.length - 1; i >= 0; i -= 1) {
    if (/^-?\d+$/.test(segs[i])) { idx = i; break; }
  }
  if (idx < 0) {
    return { collection: `/${segs.join('/')}`, id: null, rest: [], create: method === 'POST' && !COLLECTION_ACTIONS.test(segs[segs.length - 1] || '') };
  }
  return { collection: `/${segs.slice(0, idx).join('/')}`, id: Number(segs[idx]), rest: segs.slice(idx + 1), create: false };
}

const isRows = a => Array.isArray(a) && (a.length === 0 || (a[0] !== null && typeof a[0] === 'object'));

// Where a list response keeps its rows: { data: [...] }, { data: { data: [...] } },
// { data: { employees: [...], stats } }, …
function findRows(body) {
  if (!body || typeof body !== 'object') return null;
  const names = ['items', 'rows', 'list', 'records', 'results'];
  if (isRows(body.data)) return { holder: body, key: 'data' };
  const d = body.data && typeof body.data === 'object' && !Array.isArray(body.data) ? body.data : null;
  if (d) {
    if (isRows(d.data)) return { holder: d, key: 'data' };
    for (const k of names) if (isRows(d[k])) return { holder: d, key: k };
    const withIds = Object.keys(d).filter(k => isRows(d[k]) && d[k].length && d[k][0].id !== undefined);
    if (withIds.length === 1) return { holder: d, key: withIds[0] };
  }
  for (const k of names) if (isRows(body[k])) return { holder: body, key: k };
  return null;
}

// Empty values shaped like an existing row, so a screen drawing a record
// added offline finds every field it expects.
function blankLike(sample, depth = 0) {
  const out = {};
  if (!sample || typeof sample !== 'object' || Array.isArray(sample)) return out;
  for (const [k, v] of Object.entries(sample)) {
    if (typeof v === 'number') out[k] = 0;
    else if (typeof v === 'string') out[k] = '';
    else if (typeof v === 'boolean') out[k] = false;
    else if (Array.isArray(v)) out[k] = [];
    else if (v && typeof v === 'object') out[k] = depth < 2 ? blankLike(v, depth + 1) : null;
    else out[k] = null;
  }
  return out;
}

function primitiveFields(body) {
  const out = {};
  for (const [k, v] of Object.entries(body || {})) {
    if (v === null || ['string', 'number', 'boolean'].includes(typeof v)) out[k] = v;
  }
  return out;
}

function deepMap(value, fn) {
  if (Array.isArray(value)) return value.map(v => deepMap(v, fn));
  if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, deepMap(v, fn)]));
  return fn(value);
}

// Text a row can be searched by: its own values and those one level down.
function rowText(row, depth = 0) {
  if (row === null || row === undefined) return '';
  if (typeof row !== 'object') return String(row);
  if (depth > 1) return '';
  return Object.values(row).map(v => rowText(v, depth + 1)).join(' ');
}

function genericLabel(method, t, body) {
  const noun = (t.collection.split('/').filter(Boolean).pop() || 'record')
    .replace(/-/g, ' ').replace(/ies$/, 'y').replace(/(ss)es$/, '$1').replace(/([^s])s$/, '$1');
  let verb;
  if (t.create) verb = `New ${noun}`;
  else if (!t.rest.length) verb = method === 'DELETE' ? `Delete ${noun}` : `Edit ${noun}`;
  else verb = `${t.rest.join(' ').replace(/-/g, ' ').replace(/^./, c => c.toUpperCase())} (${noun})`;
  const name = body?.name || body?.title || body?.full_name || body?.bill_name || body?.subject || '';
  if (name) return `${verb}: ${name}`;
  return t.id > 0 ? `${verb} #${t.id}` : verb;
}

const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
function randomCode(len) {
  let out = '';
  for (let i = 0; i < len; i += 1) out += CODE_ALPHABET[crypto.randomInt(CODE_ALPHABET.length)];
  return out;
}

// backgroundGapMs: pause between background requests, so the server and this
// PC stay free for the user (tests pass 0).
function createOffline({ userDataDir, getConfig, request, notify, backgroundGapMs = 250 }) {
  const st = {
    online: true,
    userInFlight: 0,
    lastOnlineAt: null,
    probeTimer: null,
    catalogTimer: null,
    outboxTimer: null,
    syncingOutbox: false,
    syncingCatalog: false,
    catalogProgress: null,
    lastError: null,
    dirKey: null,
    store: null,
    cacheWriteTimer: null,
    // Collections changed by uploaded offline writes: refreshed on the next download.
    refreshCollections: new Set(),
  };

  // ── Storage (one folder per business + branch) ─────────────────────────
  function scope() {
    const cfg = getConfig();
    if (!cfg.business_id) return null;
    return { businessId: cfg.business_id, branchId: cfg.branch_id || null };
  }

  function dirFor(businessId, branchId) {
    const dir = path.join(userDataDir, 'offline', `${businessId}-${branchId || 0}`);
    fs.mkdirSync(dir, { recursive: true });
    return dir;
  }

  function store() {
    const s = scope();
    if (!s) return null;
    const key = `${s.businessId}-${s.branchId || 0}`;
    if (st.dirKey === key && st.store) return st.store;
    flushCache();
    const dir = dirFor(s.businessId, s.branchId);
    st.dirKey = key;
    st.store = {
      dir,
      scope: s,
      catalog:   readJson(path.join(dir, 'catalog.json'),   { version: null, synced_at: null, products: {}, fp: {} }),
      meta:      readJson(path.join(dir, 'meta.json'),      { bootstrap: null, synced_at: null }),
      customers: readJson(path.join(dir, 'customers.json'), { synced_at: null, items: [], id_map: {} }),
      cache:     loadCache(dir),
      // Cache shard files changed since the last save; an old single
      // cache.json is rewritten as shards on the first save.
      cacheDirty: new Set(),
      legacyCache: fs.existsSync(path.join(dir, 'cache.json')),
      outbox:    readJson(path.join(dir, 'outbox.json'),    []),
      // Settings / customer changes made offline, uploaded before the sales.
      writes:    readJson(path.join(dir, 'writes.json'),    []),
    };
    return st.store;
  }

  // ── Signed-in user's own cache (reads made before a business is picked) ─
  const gst = { cache: null, timer: null };
  const globalCacheFile = () => path.join(userDataDir, 'offline', '_global', 'cache.json');

  function globalCache() {
    if (!gst.cache) gst.cache = readJson(globalCacheFile(), {});
    return gst.cache;
  }

  function rememberGlobal(p, body) {
    const tk = tokenKey(getConfig().token);
    if (!tk) return;
    const cache = globalCache();
    const key = `${tk}|${p}`;
    delete cache[key];
    cache[key] = { at: Date.now(), body };
    const keys = Object.keys(cache);
    if (keys.length > 200) keys.slice(0, keys.length - 200).forEach(k => delete cache[k]);
    if (gst.timer) return;
    gst.timer = setTimeout(() => {
      gst.timer = null;
      try {
        fs.mkdirSync(path.dirname(globalCacheFile()), { recursive: true });
        writeJsonAtomic(globalCacheFile(), globalCache());
      } catch (err) { console.error('[offline] global cache write', err.message); }
    }, 1000);
  }

  function globalGet(p) {
    const tk = tokenKey(getConfig().token);
    const hit = tk ? globalCache()[`${tk}|${p}`] : null;
    return hit ? { status: 200, offline: true, cachedAt: hit.at, body: hit.body } : null;
  }

  // ── Offline sign-in: a salted scrypt hash of the password from the last
  // successful online sign-in, plus that sign-in's response (the token).
  // Signing out only forgets the token in config.json; it is not revoked on
  // the server, so the remembered one still works once the internet is back.
  // ── Sync activity log (shown in the sync panel): connection changes,
  // uploads, downloads and failures, newest last, kept across restarts.
  const EVENTS_KEEP = 300;
  const eventsFile = () => path.join(userDataDir, 'offline', '_events.json');
  let events = null;
  function logEvent(type, text, extra = {}) {
    if (!events) events = readJson(eventsFile(), []);
    events.push({ at: new Date().toISOString(), type, text, business_id: getConfig().business_id || null, ...extra });
    if (events.length > EVENTS_KEEP) events = events.slice(-EVENTS_KEEP);
    try {
      fs.mkdirSync(path.dirname(eventsFile()), { recursive: true });
      writeJsonAtomic(eventsFile(), events);
    } catch (err) { console.error('[offline] events write', err.message); }
  }
  function listEvents() {
    if (!events) events = readJson(eventsFile(), []);
    return [...events].reverse();
  }

  const authFile = () => path.join(userDataDir, 'offline', '_auth.json');
  const loadAuth = () => readJson(authFile(), { entries: {} });
  function saveAuth(auth) {
    fs.mkdirSync(path.dirname(authFile()), { recursive: true });
    writeJsonAtomic(authFile(), auth);
  }

  // The product catalog and the screen cache can be many MB: they are written
  // in the background, a moment after the last change, so the app never
  // freezes while saving. Everything that must survive a power cut (queued
  // sales and changes, customers, receipt numbering) is still written at once.
  const BIG_FILES = ['catalog', 'cache'];
  const pendingBig = new Map();

  // save('cache') rewrites every shard; saveCacheKeys() only the ones holding those screens.
  function save(name) {
    const s = st.store;
    if (!s) return;
    if (name === 'cache') {
      for (let i = 0; i < CACHE_SHARDS; i += 1) s.cacheDirty.add(i);
      return saveLater(s, name);
    }
    if (BIG_FILES.includes(name)) return saveLater(s, name);
    writeJsonAtomic(path.join(s.dir, `${name}.json`), s[name]);
  }

  function saveCacheKeys(keys) {
    const s = st.store;
    if (!s) return;
    keys.forEach(k => s.cacheDirty.add(shardOf(k)));
    if (s.legacyCache) for (let i = 0; i < CACHE_SHARDS; i += 1) s.cacheDirty.add(i);
    saveLater(s, 'cache');
  }

  // The dirty shards' contents, taken now (later changes mark them dirty again).
  function takeCacheShards(s) {
    const shards = new Map([...s.cacheDirty].map(i => [i, {}]));
    s.cacheDirty.clear();
    for (const [k, v] of Object.entries(s.cache)) {
      const shard = shards.get(shardOf(k));
      if (shard) shard[k] = v;
    }
    return shards;
  }

  function dropLegacyCache(s) {
    if (!s.legacyCache) return;
    s.legacyCache = false;
    try { fs.unlinkSync(path.join(s.dir, 'cache.json')); } catch (_) {}
  }

  function writeCacheNow(s) {
    const dir = path.join(s.dir, 'cache');
    fs.mkdirSync(dir, { recursive: true });
    for (const [i, data] of takeCacheShards(s)) writeJsonAtomic(path.join(dir, `shard-${i}.json`), data);
    dropLegacyCache(s);
  }

  // One cache write at a time, so an older copy can never land after a newer one.
  function writeCacheLater(s) {
    s.cacheWriting = (s.cacheWriting || Promise.resolve()).then(async () => {
      const shards = takeCacheShards(s);
      if (!shards.size) return;
      const dir = path.join(s.dir, 'cache');
      await fs.promises.mkdir(dir, { recursive: true });
      try {
        for (const [i, data] of shards) await writeJsonAtomicAsync(path.join(dir, `shard-${i}.json`), data);
        dropLegacyCache(s);
      } catch (err) {
        shards.forEach((_, i) => s.cacheDirty.add(i));
        throw err;
      }
    }).catch(err => console.error('[offline] write cache', err.message));
  }

  function saveLater(s, name) {
    const key = `${s.dir}|${name}`;
    if (pendingBig.has(key)) return;
    pendingBig.set(key, {
      s, name,
      timer: setTimeout(() => {
        pendingBig.delete(key);
        if (name === 'cache') return writeCacheLater(s);
        writeJsonAtomicAsync(path.join(s.dir, `${name}.json`), s[name])
          .catch(err => console.error('[offline] write', name, err.message));
      }, 1500),
    });
  }

  // On quit / business switch: write what is still waiting, right now.
  function flushCache() {
    for (const [key, job] of pendingBig) {
      clearTimeout(job.timer);
      pendingBig.delete(key);
      try {
        if (job.name === 'cache') writeCacheNow(job.s);
        else writeJsonAtomic(path.join(job.s.dir, `${job.name}.json`), job.s[job.name]);
      } catch (err) { console.error('[offline] write', job.name, err.message); }
    }
  }

  // Outboxes of every business on this PC — a sale queued under business A
  // must still upload after the user switches to business B.
  function allOutboxes() {
    store(); // load the current folder first so its outbox is the in-memory copy
    const root = path.join(userDataDir, 'offline');
    if (!fs.existsSync(root)) return [];
    return fs.readdirSync(root).filter(key => /^\d+-\d+$/.test(key)).map(key => {
      const [businessId, branchId] = key.split('-').map(Number);
      const isCurrent = !!(st.dirKey === key && st.store);
      return {
        key,
        dir: path.join(root, key),
        businessId,
        branchId: branchId || null,
        outbox: isCurrent ? st.store.outbox : readJson(path.join(root, key, 'outbox.json'), []),
        writes: isCurrent ? st.store.writes : readJson(path.join(root, key, 'writes.json'), []),
        isCurrent,
      };
    });
  }

  function saveOutbox(box) {
    if (box.isCurrent) { st.store.outbox = box.outbox; st.store.writes = box.writes; save('outbox'); save('writes'); return; }
    writeJsonAtomic(path.join(box.dir, 'outbox.json'), box.outbox);
    writeJsonAtomic(path.join(box.dir, 'writes.json'), box.writes);
  }

  // ── Status pushed to the renderer ──────────────────────────────────────
  function status() {
    let pending = 0, failed = 0;
    for (const box of allOutboxes()) {
      for (const e of [...box.outbox, ...box.writes]) {
        if (e.status === 'pending') pending += 1;
        if (e.status === 'failed')  failed  += 1;
      }
    }
    const s = st.store;
    return {
      online:          st.online,
      lastOnlineAt:    st.lastOnlineAt,
      pending,
      failed,
      syncing:         st.syncingOutbox || st.syncingCatalog,
      catalogProgress: st.catalogProgress,
      productCount:    s ? Object.keys(s.catalog.products).length : 0,
      customerCount:   s ? s.customers.items.length : 0,
      screenCount:     s ? Object.keys(s.cache).length : 0,
      catalogSyncedAt: s ? s.catalog.synced_at : null,
      lastError:       st.lastError,
      maxOfflineDays:  MAX_OFFLINE_DAYS,
    };
  }

  function emit() {
    try { notify(status()); } catch (_) {}
  }

  function setOnline(flag) {
    if (flag) st.lastOnlineAt = new Date().toISOString();
    if (st.online === flag) return;
    st.online = flag;
    logEvent(flag ? 'online' : 'offline', flag ? 'Internet is back' : 'Internet connection lost — working offline');
    emit();
    if (flag) {
      stopProbe();
      syncOutbox().then(() => syncCatalog());
    } else {
      startProbe();
    }
  }

  // ── Network helpers ────────────────────────────────────────────────────
  async function net(method, p, body, opts = {}) {
    try {
      return await request(method, p, body, opts);
    } catch (err) {
      return { status: 0, body: { message: err.message } };
    }
  }

  // Background work (catalog download, uploads) always gives way to the
  // user: it waits while any screen is loading, then goes one request at a time.
  async function bgNet(method, p, body, opts = {}) {
    const since = Date.now();
    while (st.userInFlight > 0 && Date.now() - since < 60000) await sleep(200);
    if (backgroundGapMs) await sleep(backgroundGapMs);
    return net(method, p, body, opts);
  }

  function startProbe() {
    if (st.probeTimer) return;
    st.probeTimer = setInterval(async () => {
      if (!getConfig().token) return;
      const res = await net('GET', '/auth/me', null, { timeout: 8000 });
      if (!isUnreachable(res.status)) setOnline(true);
    }, PROBE_INTERVAL_MS);
  }

  function stopProbe() {
    if (st.probeTimer) { clearInterval(st.probeTimer); st.probeTimer = null; }
  }

  // The raw network error (e.g. "getaddrinfo ENOTFOUND …") is kept for logs
  // only — the user sees a plain message.
  function offlineResponse(netBody = {}) {
    return {
      status: 0,
      offline: true,
      body: {
        message: 'No internet connection. This one needs to be online — everything else keeps working offline and syncs automatically.',
        network_error: netBody?.message || null,
      },
    };
  }

  // ── Remembering responses while online ─────────────────────────────────
  // byUser: opened on a screen (not a background refresh) — such screens are
  // kept fresh in the background too (warmLearned).
  function remember(p, body, byUser = false) {
    if (body == null) return;
    if (USER_PATHS.includes(p) || !scope()) rememberGlobal(p, body);
    const s = store();
    if (!s) return;
    const [pathname] = splitPath(p);

    if (pathname === '/online/settings' && s.meta.bootstrap && body.data) {
      s.meta.bootstrap.settings = body.data;
      save('meta');
    }

    if (pathname === '/online/bootstrap') {
      const data = body.data || body;
      const { products = [], products_meta, ...meta } = data;
      s.meta.bootstrap = meta;
      s.meta.synced_at = new Date().toISOString();
      save('meta');
      // Fresh cards from the grid are newer than the cached ones; keep the
      // sync-only batch barcodes, which bootstrap cards don't carry.
      let changed = false;
      for (const card of products) {
        const old = s.catalog.products[card.id];
        s.catalog.products[card.id] = { ...card, batch_skus: old?.batch_skus || [] };
        changed = true;
      }
      if (changed) save('catalog');
      return;
    }

    let json;
    try { json = JSON.stringify(body); } catch (_) { return; }
    if (json.length > CACHE_MAX_BYTES) return;
    const used = byUser ? Date.now() : s.cache[p]?.used;
    delete s.cache[p];
    s.cache[p] = used ? { at: Date.now(), used, body } : { at: Date.now(), body };
    const touched = [p];
    const keys = Object.keys(s.cache);
    if (keys.length > CACHE_MAX_ENTRIES) {
      keys.slice(0, keys.length - CACHE_MAX_ENTRIES).forEach(k => { delete s.cache[k]; touched.push(k); });
    }
    saveCacheKeys(touched);
  }

  // ── Answering GETs from disk ───────────────────────────────────────────
  function matchesWords(card, words) {
    if (!words.length) return true;
    const hay = [
      card.name, card.sku,
      ...(card.batch_skus || []).map(b => b.sku),
      ...(card.layers || []).map(l => l.batch_sku),
    ].filter(Boolean).join(' ').toLowerCase();
    return words.every(w => hay.includes(w));
  }

  function sortCards(list, sort, lastSold) {
    const byName = (a, b) => String(a.name || '').localeCompare(String(b.name || ''));
    const price  = c => Number(c.discounted_sell_price ?? c.unit_sell_price ?? 0);
    const stock  = c => Number(c.stock_quantity ?? 0);
    const cmp = {
      name_desc:    (a, b) => byName(b, a),
      price_asc:    (a, b) => price(a) - price(b) || byName(a, b),
      price_desc:   (a, b) => price(b) - price(a) || byName(a, b),
      stock_asc:    (a, b) => stock(a) - stock(b) || byName(a, b),
      stock_desc:   (a, b) => stock(b) - stock(a) || byName(a, b),
      recent_sales: (a, b) => (lastSold[b.id] || 0) - (lastSold[a.id] || 0) || byName(a, b),
    }[sort] || byName;
    return list.sort(cmp);
  }

  function localBootstrap(s, params) {
    if (!s.meta.bootstrap || !Object.keys(s.catalog.products).length) return null;
    const words    = (params.get('q') || '').toLowerCase().split(/\s+/).filter(Boolean);
    const category = Number(params.get('category')) || 0;
    const perPage  = Math.max(1, Math.min(100, Number(params.get('per_page')) || 40));
    const stockSt  = params.get('stock_status');
    const lastSold = s.meta.last_sold || {};

    let list = Object.values(s.catalog.products).filter(c =>
      matchesWords(c, words) &&
      (!category || (c.category_ids || []).includes(category)) &&
      (params.get('discount_only') !== '1' || c.discount) &&
      (params.get('rental_only')   !== '1' || c.is_rental) &&
      (params.get('dynamic_only')  !== '1' || c.is_dynamic_pricing) &&
      (params.get('recent_sales')  !== '1' || lastSold[c.id]) &&
      (stockSt !== 'in_stock'     || Number(c.stock_quantity) > 5) &&
      (stockSt !== 'low_stock'    || (Number(c.stock_quantity) > 0 && Number(c.stock_quantity) <= 5)) &&
      (stockSt !== 'out_of_stock' || Number(c.stock_quantity) <= 0)
    );
    list = sortCards(list, params.get('sort') || 'name_asc', lastSold);

    const total    = list.length;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const page     = Math.min(Math.max(1, Number(params.get('page')) || 1), lastPage);
    return {
      status: 200,
      offline: true,
      body: {
        data: {
          ...s.meta.bootstrap,
          products: list.slice((page - 1) * perPage, page * perPage),
          products_meta: { current_page: page, last_page: lastPage, per_page: perPage, total },
        },
      },
    };
  }

  function localProductBySku(s, sku) {
    const needle = String(sku).trim().toLowerCase();
    const cards = Object.values(s.catalog.products);
    const exact = cards.find(c => String(c.sku || '').toLowerCase() === needle);
    if (exact) return { status: 200, offline: true, body: { data: exact } };
    for (const c of cards) {
      const hit = (c.batch_skus || []).find(b => String(b.sku).toLowerCase() === needle)
        || (c.layers || []).filter(l => l.batch_sku).map(l => ({ layer_id: l.id, sku: l.batch_sku }))
          .find(b => String(b.sku).toLowerCase() === needle);
      if (hit) {
        const layer = (c.layers || []).find(l => l.id === hit.layer_id);
        return {
          status: 200,
          offline: true,
          body: {
            data: {
              ...c,
              matched_layer_id: hit.layer_id,
              matched_batch_sku: hit.sku,
              layer_qty_remaining: layer ? Number(layer.quantity_remaining) : Number(c.stock_quantity || 0),
            },
          },
        };
      }
    }
    return { status: 404, offline: true, body: { message: `No product found for SKU: ${sku}` } };
  }

  function localProductSearch(s, params) {
    const words   = (params.get('q') || '').toLowerCase().split(/\s+/).filter(Boolean);
    const perPage = Math.max(1, Math.min(100, Number(params.get('per_page')) || 20));
    const list = sortCards(Object.values(s.catalog.products).filter(c => matchesWords(c, words)), 'name_asc', {});
    return {
      status: 200,
      offline: true,
      body: {
        data: list.slice(0, perPage),
        meta: { current_page: 1, last_page: 1, per_page: perPage, total: Math.min(list.length, perPage) },
      },
    };
  }

  function localCustomers(s, params) {
    if (!s.customers.synced_at) return null;
    const q        = (params.get('q') || '').toLowerCase().trim();
    const category = params.get('category_id');
    const type     = params.get('customer_type');
    const list = s.customers.items
      .filter(c => !q || [c.name, c.phone, c.email].some(v => String(v || '').toLowerCase().includes(q)))
      .filter(c => !category || String(c.customer_category_id) === String(category))
      .filter(c => !type || c.customer_type === type)
      .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
    const perPage  = 50;
    const lastPage = Math.max(1, Math.ceil(list.length / perPage));
    const page     = Math.min(Math.max(1, Number(params.get('page')) || 1), lastPage);
    return {
      status: 200,
      offline: true,
      body: {
        data: list.slice((page - 1) * perPage, page * perPage),
        meta: { total: list.length, current_page: page, last_page: lastPage },
      },
    };
  }

  // Last online "today" numbers plus the sales made offline since then.
  function localTodaySummary(s, hit) {
    const body = JSON.parse(JSON.stringify(hit.body));
    const sales = body?.data?.sales;
    if (!sales) return { status: 200, offline: true, body };
    const today = new Date().toDateString();
    if (new Date(hit.at).toDateString() !== today) {
      Object.assign(sales, { count: 0, revenue: 0, cogs: 0, gross_profit: 0, items_sold: 0, by_method: {} });
      sales.hourly = Array.from({ length: 24 }, () => ({ count: 0, revenue: 0 }));
    }
    const round = v => Math.round(v * 100) / 100;
    for (const e of s.outbox) {
      const soldAt = new Date(e.sold_at);
      if (e.voided || soldAt.toDateString() !== today) continue;
      // Already counted by the cached server numbers?
      if (e.status === 'synced' && new Date(e.synced_at).getTime() <= hit.at) continue;
      const total = Number(e.receipt?.total || 0);
      const m = e.body?.payment_method || 'cash';
      sales.count = (sales.count || 0) + 1;
      sales.revenue = round((sales.revenue || 0) + total);
      sales.items_sold = (sales.items_sold || 0) + (e.body?.items || []).reduce((a, i) => a + Number(i.quantity || 0), 0);
      sales.by_method = sales.by_method || {};
      const bm = sales.by_method[m] || { count: 0, total: 0 };
      sales.by_method[m] = { count: bm.count + 1, total: round(bm.total + total) };
      if (Array.isArray(sales.hourly) && sales.hourly[soldAt.getHours()]) {
        const h = sales.hourly[soldAt.getHours()];
        h.count += 1;
        h.revenue = round(h.revenue + total);
      }
    }
    return { status: 200, offline: true, cachedAt: hit.at, body };
  }

  // Service POS search: filter the cached full service list.
  function localServiceCatalog(s, params) {
    const base = s.cache['/service/pos/catalog?q=&category='];
    if (!base) return null;
    const body = JSON.parse(JSON.stringify(base.body));
    const holder = body.data && Array.isArray(body.data.services) ? body.data : body;
    if (!Array.isArray(holder.services)) return { status: 200, offline: true, body };
    const q = (params.get('q') || '').toLowerCase().trim();
    const cat = Number(params.get('category')) || 0;
    holder.services = holder.services.filter(sv =>
      (!q || String(sv.name || '').toLowerCase().includes(q)) &&
      (!cat || (sv.categories || []).some(c => Number(c.id) === cat)));
    return { status: 200, offline: true, body };
  }

  // Offline sales not on the server yet, as sales-list rows (formatSaleList shape).
  function offlineSaleRows(s, q) {
    const needle = String(q || '').toLowerCase().trim();
    return s.outbox
      .filter(e => e.status !== 'synced' && e.receipt)
      .map(e => ({
        id: e.local_id, sale_number: e.offline_ref, status: e.voided ? 'void' : 'completed',
        payment_method: e.body?.payment_method, channel: 'online', total: e.receipt.total,
        cost: null, profit: null, missing_cost: false, items_count: (e.receipt.items || []).length,
        sold_at: e.sold_at, customer_name: e.receipt.customer_name, pos_customer_id: e.receipt.pos_customer_id,
        amount_paid: e.receipt.amount_paid, due_amount: e.receipt.due_amount, credit_due_date: e.receipt.credit_due_date,
        is_overdue: false, cheque_status: null, offline: true,
      }))
      .filter(r => !needle || [r.sale_number, r.customer_name].some(v => String(v || '').toLowerCase().includes(needle)))
      .sort((a, b) => String(b.sold_at).localeCompare(String(a.sold_at)));
  }

  function withOfflineSales(s, p, res) {
    const [pathname, params] = splitPath(p);
    if (!['/sales', '/sales/history'].includes(pathname) || !Array.isArray(res?.body?.data)) return res;
    if (Number(params.get('page') || 1) > 1) return res;
    const rows = offlineSaleRows(s, params.get('q'));
    if (!rows.length) return res;
    const known = new Set(res.body.data.map(r => r.sale_number));
    return { ...res, body: { ...res.body, data: [...rows.filter(r => !known.has(r.sale_number)), ...res.body.data] } };
  }

  // Sales list search while offline: filter the last downloaded list.
  function localSalesList(s, p, params) {
    const hit = s.cache[p] || s.cache['/sales?q='];
    if (!hit) return null;
    const needle = String(params.get('q') || '').toLowerCase().trim();
    const body = JSON.parse(JSON.stringify(hit.body));
    if (!s.cache[p] && Array.isArray(body.data) && needle) {
      body.data = body.data.filter(r => [r.sale_number, r.offline_ref, r.customer_name].some(v => String(v || '').toLowerCase().includes(needle)));
    }
    const limit = Number(params.get('limit')) || 0;
    if (limit && Array.isArray(body.data)) body.data = body.data.slice(0, limit);
    return { status: 200, offline: true, cachedAt: hit.at, body };
  }

  function localCodeList(s, listPath, params, view) {
    const hit = s.cache[listPath];
    if (!hit || !Array.isArray(hit.body?.data)) return null;
    const q = String(params.get('q') || '').toLowerCase().trim();
    const status = params.get('status') || '';
    const data = hit.body.data.map(view).filter(x =>
      (!q || [x.name, x.code].some(v => String(v || '').toLowerCase().includes(q))) && (!status || x.status === status));
    return { status: 200, offline: true, cachedAt: hit.at, body: { data } };
  }

  // ── Any list screen, searched / filtered offline ───────────────────────
  // A list opened with a search or filter this PC never downloaded is answered
  // from a downloaded copy of the same list: rows matching the search words,
  // and rows whose field of the same name equals each filter (status=paid, …).
  // Dates, periods and grouping change what the server adds up, so those must
  // match a downloaded copy exactly; later pages are never made up.
  const EXACT_PARAMS = /^(date_from|date_to|from|to|period|month|year|start|end|start_date|end_date|group_by|branch_id|counter_id|box|scope|type|export)$/;
  const PAGING_PARAMS = ['page', 'per_page', 'limit'];
  const isWildcard = v => v === '' || v === 'all';

  function localListFallback(s, pathname, params) {
    if (Number(params.get('page') || 1) > 1) return null;
    let best = null;
    for (const [key, hit] of Object.entries(s.cache)) {
      const [kPath, kParams] = splitPath(key);
      if (kPath !== pathname || !findRows(hit.body)) continue;
      if (Number(kParams.get('page') || 1) !== 1) continue;
      let ok = true, score = 0;
      // Every filter the downloaded copy has, the request has too…
      for (const [k, v] of kParams) {
        if (PAGING_PARAMS.includes(k) || isWildcard(v)) continue;
        if (params.get(k) !== v) { ok = false; break; }
        score += 1;
      }
      // …and the same dates / grouping.
      for (const [k, v] of params) {
        if (ok && EXACT_PARAMS.test(k) && !isWildcard(v) && kParams.get(k) !== v) ok = false;
      }
      if (ok && (!best || score > best.score || (score === best.score && hit.at > best.hit.at))) best = { hit, score, kParams };
    }
    if (!best) return null;

    const body = JSON.parse(JSON.stringify(best.hit.body));
    const { holder, key } = findRows(body);
    const before = holder[key].length;
    const words = String(params.get('q') || params.get('search') || '').toLowerCase().split(/\s+/).filter(Boolean);
    let rows = holder[key];
    if (words.length && !best.kParams.get('q')) {
      rows = rows.filter(r => { const hay = rowText(r).toLowerCase(); return words.every(w => hay.includes(w)); });
    }
    for (const [k, v] of params) {
      if (k === 'q' || k === 'search' || PAGING_PARAMS.includes(k) || EXACT_PARAMS.test(k) || isWildcard(v)) continue;
      if (best.kParams.get(k) === v || !rows.some(r => r && r[k] !== undefined)) continue;
      rows = rows.filter(r => String(r?.[k]) === v);
    }
    holder[key] = rows;
    const meta = body.meta || (holder !== body ? holder.meta : null);
    if (meta && rows.length !== before) Object.assign(meta, { total: rows.length, current_page: 1, last_page: 1 });
    return { status: 200, offline: true, cachedAt: best.hit.at, body };
  }

  // Gift card / coupon "Generate" offline: same format as the server, not
  // already in the downloaded list (the server re-checks on upload).
  function localCode(s, pathname) {
    const isGift = pathname.startsWith('/gift-cards');
    const taken = new Set((s.cache[isGift ? GIFT_LIST : COUPON_LIST]?.body?.data || []).map(x => normCode(x.code)));
    let code;
    do {
      code = isGift ? `GC-${randomCode(4)}-${randomCode(4)}-${randomCode(4)}` : `CP-${randomCode(6)}`;
    } while (taken.has(code));
    return { status: 200, offline: true, body: { data: { code } } };
  }

  function localGet(p) {
    const s = store();
    if (!s) return globalGet(p);
    const [pathname, params] = splitPath(p);

    if (pathname === '/gift-cards/generate-code' || pathname === '/coupons/generate-code') return localCode(s, pathname);

    const offlineSale = pathname.match(/^\/sales\/(-\d+)$/);
    if (offlineSale) {
      const sale = knownSale(s, Number(offlineSale[1]));
      return sale ? { status: 200, offline: true, body: { data: sale } } : { status: 404, offline: true, body: { message: 'Sale not found.' } };
    }
    if (pathname === '/sales' || pathname === '/sales/history') {
      const local = localSalesList(s, p, params);
      if (local) return withOfflineSales(s, p, local);
      return { status: 200, offline: true, body: { data: offlineSaleRows(s, params.get('q')), meta: { current_page: 1, last_page: 1, total: 0 } } };
    }
    if (pathname === '/gift-cards/lookup' || pathname === '/coupons/lookup') {
      const isGift = pathname === '/gift-cards/lookup';
      const { list, hit } = findCode(s, isGift ? GIFT_LIST : COUPON_LIST, params.get('code'));
      if (!list) return null;
      if (!hit) return { status: 404, offline: true, body: { message: isGift ? 'Gift card not found.' : 'Coupon not found.' } };
      return { status: 200, offline: true, body: { data: isGift ? giftCardView(hit) : couponView(hit) } };
    }
    if (pathname === '/gift-cards') { const l = localCodeList(s, GIFT_LIST, params, giftCardView); if (l) return l; }
    if (pathname === '/coupons')    { const l = localCodeList(s, COUPON_LIST, params, couponView); if (l) return l; }
    if (pathname === '/cash-drawer') return { status: 200, offline: true, body: { data: localCashDrawer(s) } };
    if (pathname === '/eod' && s.cache['/eod']) return localEod(s, s.cache['/eod']);

    if (pathname === '/today-summary' && s.cache[p]) return localTodaySummary(s, s.cache[p]);
    if (pathname === '/service/pos/catalog' && !s.cache[p]) {
      const local = localServiceCatalog(s, params);
      if (local) return local;
    }

    if (pathname === '/online/bootstrap') return localBootstrap(s, params);
    if (pathname.startsWith('/online/products/sku/')) {
      if (!Object.keys(s.catalog.products).length) return null;
      return localProductBySku(s, decodeURIComponent(pathname.slice('/online/products/sku/'.length)));
    }
    if (pathname === '/online/products' && Object.keys(s.catalog.products).length) {
      return localProductSearch(s, params);
    }
    const productMatch = pathname.match(/^\/online\/products\/(\d+)$/);
    if (productMatch && !s.cache[p]) {
      const card = s.catalog.products[productMatch[1]];
      if (card) return { status: 200, offline: true, body: { data: card } };
    }
    if (pathname === '/customers') {
      const local = localCustomers(s, params);
      if (local) return local;
    }
    const customerMatch = pathname.match(/^\/customers\/(-?\d+)$/);
    if (customerMatch && !s.cache[p]) {
      const c = s.customers.items.find(x => String(x.id) === customerMatch[1]);
      if (c) return { status: 200, offline: true, body: { data: c } };
    }

    const hit = s.cache[p];
    if (hit) return { status: 200, offline: true, cachedAt: hit.at, body: hit.body };
    if (USER_PATHS.includes(p)) return globalGet(p);
    return localListFallback(s, pathname, params);
  }

  // ── Checkout (online, or queued) ───────────────────────────────────────
  function stripOfflineKeys(body) {
    const out = { ...body };
    OFFLINE_ONLY_BODY_KEYS.forEach(k => delete out[k]);
    out.items = (body.items || []).map(i => {
      const copy = { ...i };
      OFFLINE_ONLY_ITEM_KEYS.forEach(k => delete copy[k]);
      return copy;
    });
    return out;
  }

  // ── Gift cards and coupons from the downloaded lists ───────────────────
  const GIFT_LIST   = '/gift-cards?q=&status=';
  const COUPON_LIST = '/coupons?q=&status=';
  const normCode = c => String(c || '').replace(/\s+/g, '').toUpperCase();
  const todayYmd = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  };

  // Same rules as GiftCard::status() / Coupon::status(), judged today on this PC.
  function codeStatus(x, used) {
    const today = todayYmd();
    if (!x.is_active) return 'disabled';
    if (x.expires_at && x.expires_at < today) return 'expired';
    if (x.valid_from && x.valid_from > today) return 'scheduled';
    if (used) return 'used';
    return 'active';
  }

  function giftCardView(card) {
    const status = codeStatus(card, Number(card.balance) <= 0.005);
    return { ...card, status, is_redeemable: status === 'active', transactions: card.transactions || [] };
  }

  function couponView(c) {
    const remaining = Math.max(0, Number(c.quantity || 0) - Number(c.used_count || 0));
    const status = codeStatus(c, remaining <= 0);
    return { ...c, remaining, status, is_redeemable: status === 'active', redemptions: c.redemptions || [] };
  }

  function findCode(s, listPath, code) {
    const list = s.cache[listPath]?.body?.data;
    if (!Array.isArray(list)) return { list: null, hit: null };
    return { list, hit: list.find(x => normCode(x.code) === normCode(code)) || null };
  }

  function offlineBlockReason(body, s) {
    if (body.gift_card_code) {
      const { list, hit } = findCode(s, GIFT_LIST, body.gift_card_code);
      if (!list) return 'The gift card list has not been downloaded to this PC yet. Connect to the internet once, then gift cards work offline.';
      if (!hit) return 'Gift card not found.';
      const view = giftCardView(hit);
      if (!view.is_redeemable) return `Gift card ${hit.code} cannot be used (${view.status}).`;
      if (Number(body.gift_card_amount || 0) > Number(hit.balance) + 0.005) return `Gift card balance is only ${Number(hit.balance).toFixed(2)}.`;
    }
    if (body.coupon_code) {
      const { list, hit } = findCode(s, COUPON_LIST, body.coupon_code);
      if (!list) return 'The coupon list has not been downloaded to this PC yet. Connect to the internet once, then coupons work offline.';
      if (!hit) return 'Coupon not found.';
      const view = couponView(hit);
      if (!view.is_redeemable) return `Coupon ${hit.code} cannot be used (${view.status}).`;
    }
    if (body.pos_customer_id && !s.customers.items.some(c => c.id === body.pos_customer_id)) {
      // A customer created during this offline session would not exist on the server yet.
      return 'This customer is not in the offline customer list. Pick an existing customer, or sell without one.';
    }
    return null;
  }

  function nextOfflineRef(s) {
    const d = new Date();
    const ymd = `${String(d.getFullYear()).slice(2)}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`;
    const device = crypto.createHash('md5').update(String(getConfig().device_name || 'pos')).digest('hex').slice(0, 4).toUpperCase();
    s.meta.offline_seq = (s.meta.offline_seq || 0) + 1;
    save('meta');
    return `OFF-${device}-${ymd}-${String(s.meta.offline_seq).padStart(4, '0')}`;
  }

  // The receipt shown right away for a queued sale, shaped like
  // PosOnlineApiService::formatSale() so the receipt screen just works.
  function localSale(body, entry, s) {
    const summary = body.offline_summary || {};
    const customer = body.pos_customer_id ? s.customers.items.find(c => c.id === body.pos_customer_id) : null;
    const items = (body.items || []).map((i, idx) => {
      const card = i.product_id ? s.catalog.products[i.product_id] : null;
      const unit = Number(i.custom_unit_price ?? i.offline_unit_price ?? 0);
      const pct  = Number(i.item_discount_percent || 0);
      const sell = Math.round(unit * (1 - pct / 100) * 100) / 100;
      return {
        id: -(idx + 1),
        product_id: i.product_id || null,
        product_stock_layer_id: i.product_stock_layer_id || null,
        product_name: i.offline_name || card?.name || 'Item',
        sku: card?.sku || null,
        quantity: Number(i.quantity),
        unit_cost: null,
        discount_amount: Math.round((unit - sell) * 100) / 100,
        unit_sell_price: sell,
        line_total: Math.round(sell * Number(i.quantity) * 100) / 100,
        returned_quantity: 0,
        warranty_type: i.warranty_type || null,
        warranty_days: null,
        warranty_expires_at: i.warranty_date || null,
        custom_requirement_values: i.custom_requirement_values || [],
      };
    });
    const subtotal = summary.subtotal ?? items.reduce((a, l) => a + l.line_total, 0);
    const total    = summary.total ?? subtotal;
    const paid     = Number(body.amount_paid ?? total);
    const tendered = body.amount_tendered != null ? Number(body.amount_tendered) : null;
    const giftAmt  = body.gift_card_code ? Number(summary.gift_card_amount ?? body.gift_card_amount ?? 0) : 0;
    const gift     = body.gift_card_code ? findCode(s, GIFT_LIST, body.gift_card_code).hit : null;
    const coupon   = body.coupon_code ? findCode(s, COUPON_LIST, body.coupon_code).hit : null;
    return {
      // Negative = only on this PC; void / return / revert of it wait for the upload.
      id: entry.local_id,
      offline: true,
      client_uuid: entry.client_uuid,
      offline_ref: entry.offline_ref,
      sale_number: entry.offline_ref,
      status: 'completed',
      payment_method: body.payment_method,
      payment_method_label: { cash: 'Cash', card: 'Card', credit: 'Credit', cheque: 'Cheque' }[body.payment_method] || body.payment_method,
      channel: 'online',
      channel_label: 'POS',
      branch: null,
      subtotal: Math.round(subtotal * 100) / 100,
      discount_percent: body.discount_percent ?? null,
      discount_amount: Math.round((summary.discount_amount ?? 0) * 100) / 100,
      total: Math.round(total * 100) / 100,
      amount_paid: Math.round(paid * 100) / 100,
      gift_card_amount: Math.round(giftAmt * 100) / 100,
      gift_card: gift ? { id: gift.id, code: gift.code, name: gift.name, balance: Math.max(0, Math.round((Number(gift.balance) - giftAmt) * 100) / 100) } : null,
      coupon_discount: Math.round(Number(summary.coupon_discount || 0) * 100) / 100,
      coupon: coupon ? { id: coupon.id, code: coupon.code, name: coupon.name, discount_type: coupon.discount_type, discount_value: Number(coupon.discount_value) } : null,
      amount_tendered: tendered,
      change_amount: tendered != null ? Math.max(0, Math.round((tendered - total) * 100) / 100) : null,
      notes: body.notes || null,
      customer_name: customer?.name || null,
      pos_customer_id: body.pos_customer_id || null,
      due_amount: body.payment_method === 'credit' ? Math.max(0, Math.round((total - paid) * 100) / 100) : 0,
      credit_due_date: body.credit_due_date || null,
      is_overdue: false,
      credit_payments: [],
      sold_at: entry.sold_at,
      revert: null,
      cheque: null,
      credit_account: null,
      cashier: summary.cashier_name ? { id: null, name: summary.cashier_name } : null,
      items,
    };
  }

  // Take the sold quantity off the local cards so the grid shows the right
  // stock (and stops overselling) until the next catalog refresh.
  function consumeLocalStock(s, body) {
    s.meta.last_sold = s.meta.last_sold || {};
    for (const i of body.items || []) {
      const card = i.product_id ? s.catalog.products[i.product_id] : null;
      if (!card) continue;
      let qty = Number(i.quantity) || 0;
      s.meta.last_sold[card.id] = Date.now();
      card.stock_quantity = Math.round((Number(card.stock_quantity || 0) - qty) * 1000) / 1000;
      if (card.available_stock != null) card.available_stock = Math.max(0, Math.round((Number(card.available_stock) - qty) * 1000) / 1000);
      const layers = card.layers || [];
      const ordered = i.product_stock_layer_id
        ? [...layers.filter(l => l.id === i.product_stock_layer_id), ...layers.filter(l => l.id !== i.product_stock_layer_id)]
        : layers;
      for (const l of ordered) {
        if (qty <= 0) break;
        const take = Math.min(qty, Number(l.quantity_remaining || 0));
        l.quantity_remaining = Math.round((Number(l.quantity_remaining) - take) * 1000) / 1000;
        qty -= take;
      }
      card.layers = layers.filter(l => Number(l.quantity_remaining) > 0);
      card.layer_count = card.layers.length;
      card.has_layers = card.layers.length > 0;
    }
    save('catalog');
    save('meta');
  }

  function queueSale(body, s) {
    const blocked = offlineBlockReason(body, s);
    if (blocked) return { status: 422, offline: true, body: { message: blocked } };

    const entry = {
      client_uuid: body.client_uuid,
      local_id: -Date.now(),
      offline_ref: nextOfflineRef(s),
      sold_at: new Date().toISOString(),
      created_at: new Date().toISOString(),
      business_id: s.scope.businessId,
      branch_id: s.scope.branchId,
      status: 'pending',
      attempts: 0,
      error: null,
      sale_number: null,
      body: null,
      receipt: null,
    };

    // The replayed request: same body, plus when it happened and the unit
    // price the customer actually paid (the server keeps it even if the
    // catalog price changes before the upload).
    const replay = stripOfflineKeys(body);
    replay.sold_at = entry.sold_at;
    replay.offline_ref = entry.offline_ref;
    replay.items = replay.items.map((i, idx) => {
      const src = body.items[idx];
      if (i.item_type === 'service' || i.custom_unit_price != null || src.offline_unit_price == null) return i;
      return { ...i, custom_unit_price: Number(src.offline_unit_price) };
    });
    entry.body = replay;
    entry.receipt = localSale(body, entry, s);

    s.outbox.push(entry);
    save('outbox');
    logEvent('saved', `Sale saved on this PC: ${entry.offline_ref} · ${Number(entry.receipt.total || 0).toFixed(2)}`, { ref: entry.client_uuid });
    consumeLocalStock(s, body);
    // Spend the gift card / coupon on this PC's copy too, so it can't be
    // used past its balance while still offline.
    const gift = body.gift_card_code && findCode(s, GIFT_LIST, body.gift_card_code).hit;
    if (gift) gift.balance = Math.max(0, Math.round((Number(gift.balance) - Number(entry.receipt.gift_card_amount)) * 100) / 100);
    const coupon = body.coupon_code && findCode(s, COUPON_LIST, body.coupon_code).hit;
    if (coupon) coupon.used_count = Number(coupon.used_count || 0) + 1;
    if (gift || coupon) save('cache');
    emit();

    return {
      status: 201,
      offline: true,
      body: {
        message: `Saved offline as ${entry.offline_ref}. It will sync automatically when the internet is back.`,
        offline: true,
        data: entry.receipt,
      },
    };
  }

  async function checkout(body) {
    const s = store();
    body = { ...body, client_uuid: body.client_uuid || crypto.randomUUID() };
    if (!s) return net('POST', '/online/checkout', stripOfflineKeys(body));

    // A customer added offline: use its real id once uploaded; until then the
    // sale waits in the outbox and uploads right after the customer.
    if (body.pos_customer_id < 0) {
      const realId = s.customers.id_map?.[body.pos_customer_id];
      if (realId) body.pos_customer_id = realId;
      else return queueSale(body, s);
    }
    // Same for a product added offline.
    const productMap = s.meta.product_id_map || {};
    body.items = (body.items || []).map(i => {
      if (!(i.product_id < 0) || !productMap[i.product_id]) return i;
      const { product_stock_layer_id, ...rest } = i;
      return { ...rest, product_id: productMap[i.product_id] };
    });
    if (body.items.some(i => i.product_id < 0)) return queueSale(body, s);

    if (st.online) {
      const res = await net('POST', '/online/checkout', stripOfflineKeys(body), { timeout: 30000 });
      if (!isUnreachable(res.status)) {
        setOnline(true);
        if (res.status === 200 || res.status === 201) consumeLocalStock(s, body);
        return res;
      }
      setOnline(false);
    }
    // Unreachable (or a timeout — the server may even have saved it; the
    // client_uuid makes the later upload return that same sale).
    return queueSale(body, s);
  }

  // ── Changes made offline (settings, customers) ─────────────────────────
  const SAVED_OFFLINE_MSG = 'No internet — saved on this PC. It will upload automatically when the internet is back.';

  function addWrite(s, write) {
    s.writes.push({
      id: write.id || crypto.randomUUID(),
      status: 'pending',
      attempts: 0,
      error: null,
      created_at: new Date().toISOString(),
      ...write,
    });
    save('writes');
    logEvent('saved', `Saved on this PC: ${write.label || write.kind}`, { ref: s.writes[s.writes.length - 1].id });
    emit();
  }

  function queueSettings(method, body, s) {
    const current = s.cache['/online/settings']?.body?.data || s.meta.bootstrap?.settings || {};
    const merged = { ...current, ...body };
    s.cache['/online/settings'] = { at: Date.now(), body: { data: merged } };
    save('cache');
    if (s.meta.bootstrap) {
      s.meta.bootstrap.settings = { ...(s.meta.bootstrap.settings || {}), ...body };
      save('meta');
    }
    addWrite(s, { kind: 'settings', method, path: '/online/settings', body, label: 'POS / receipt settings change' });
    return { status: 200, offline: true, body: { message: SAVED_OFFLINE_MSG, data: merged } };
  }

  function queueCustomerCreate(body, s) {
    const fields = pick(body || {}, CUSTOMER_FIELDS);
    if (!String(fields.name || '').trim()) {
      return { status: 422, offline: true, body: { message: 'Customer name is required.', errors: { name: ['Customer name is required.'] } } };
    }
    const uuid = crypto.randomUUID();
    // Negative ids never clash with server ids; replaced when the customer uploads.
    const tempId = -Date.now();
    const row = {
      id: tempId, name: fields.name, phone: fields.phone ?? null, email: fields.email ?? null,
      address: fields.address ?? null, notes: fields.notes ?? null,
      customer_type: fields.customer_type || 'retail', customer_category_id: fields.customer_category_id ?? null,
      category_name: null, sales_count: 0, credit_limit: null, credit_outstanding: 0, credit_available: null,
      client_uuid: uuid, offline: true,
    };
    s.customers.items.push(row);
    save('customers');
    const replay = { client_uuid: uuid };
    Object.entries(fields).forEach(([k, v]) => { if (v !== null && v !== '') replay[k] = v; });
    addWrite(s, { id: uuid, kind: 'customer_create', method: 'POST', path: '/customers/offline', body: replay, temp_id: tempId, label: `New customer: ${fields.name}` });
    return { status: 201, offline: true, body: { message: SAVED_OFFLINE_MSG, data: row } };
  }

  function queueCustomerUpdate(id, body, s) {
    const fields = pick(body || {}, CUSTOMER_FIELDS);
    const row = s.customers.items.find(c => c.id === id);
    if (row) { Object.assign(row, fields); save('customers'); }
    const pendingCreate = id < 0 && s.writes.find(w => w.temp_id === id && w.status !== 'synced');
    if (pendingCreate) {
      Object.entries(fields).forEach(([k, v]) => { if (v === null || v === '') delete pendingCreate.body[k]; else pendingCreate.body[k] = v; });
      save('writes');
    } else {
      addWrite(s, { kind: 'customer_update', method: 'PATCH', path: `/customers/${id}`, body, label: `Customer change: ${row?.name || id}` });
    }
    return { status: 200, offline: true, body: { message: SAVED_OFFLINE_MSG, data: row || { id, ...fields } } };
  }

  // ── New product offline: sellable right away; uploads before its sales ──
  function queueProductCreate(body, s) {
    const name = String(body?.name || '').trim();
    const price = Number(body?.unit_price);
    if (!name) return { status: 422, offline: true, body: { message: 'Product name is required.', errors: { name: ['Product name is required.'] } } };
    if (!(price >= 0) || body.unit_price === null || body.unit_price === '') {
      return { status: 422, offline: true, body: { message: 'Selling price is required.', errors: { unit_price: ['Selling price is required.'] } } };
    }
    if (body.sku && Object.values(s.catalog.products).some(c => String(c.sku || '').toLowerCase() === String(body.sku).toLowerCase())) {
      return { status: 422, offline: true, body: { message: 'This SKU is already used by another product.', errors: { sku: ['This SKU is already used by another product.'] } } };
    }
    const tempId = -Date.now();
    const layers = (body.opening_batches || []).filter(b => Number(b.quantity) > 0).map((b, i) => ({
      id: tempId * 100 - i, // negative: replaced by real batches after upload
      batch_sku: null,
      label: 'Opening stock',
      quantity_remaining: Number(b.quantity),
      unit_cost: Number(b.cost_price ?? body.cost_price ?? 0),
      unit_sell_price: Number(b.selling_price ?? price),
      received_at: todayYmd(),
    }));
    const stock = layers.reduce((a, l) => a + l.quantity_remaining, 0);
    const prices = new Set(layers.map(l => l.unit_sell_price.toFixed(2)));
    const card = {
      id: tempId, name, sku: body.sku || null, unit: null, image_url: null,
      unit_sell_price: price, discounted_sell_price: null, discount: null,
      stock_quantity: stock, available_stock: stock, has_layers: layers.length > 0, layer_count: layers.length,
      requires_layer_pick: layers.length > 1 && prices.size > 1, has_multiple_prices: prices.size > 1,
      layers, selling_units: [], batch_skus: [], category_ids: body.product_category_ids || [],
      wholesale_price: body.wholesale_price ?? null, has_warranty: !!body.has_warranty, warranty_duration: body.warranty_duration ?? null,
      track_expiry: !!body.track_expiry, courier_delivery: false, loyalty_redeemable: !!body.loyalty_redeemable,
      is_subscription: !!body.is_subscription, subscription_recurring_period: body.subscription_recurring_period ?? null,
      subscription_free_trial: !!body.subscription_free_trial,
      is_rental: !!body.is_rental, rental_daily_rate: body.rental_daily_rate ?? null, rental_max_days: body.rental_max_days ?? null,
      rental_late_fee_multiplier: body.rental_late_fee_multiplier ?? null, rental_needs_cleaning: !!body.rental_needs_cleaning,
      is_dynamic_pricing: !!body.is_dynamic_pricing, dynamic_price_qty_linked: !!body.dynamic_price_qty_linked,
      is_changeable_price: !!body.is_changeable_price, offline: true,
    };
    // Bundles never show in the POS grid, so they only wait for the upload.
    if (!body.is_bundle && body.is_active !== false) {
      s.catalog.products[tempId] = card;
      save('catalog');
    }
    addWrite(s, { kind: 'product_create', method: 'POST', path: '/online/products', body, temp_id: tempId, label: `New product: ${name}` });
    return { status: 201, offline: true, body: { message: SAVED_OFFLINE_MSG, data: { ...card, batches_on_other_price: [] } } };
  }

  // A product created offline got its real id: fix the sales that sold it.
  function mapProduct(box, tempId, realId) {
    if (!tempId || !realId) return;
    const fix = items => (items || []).forEach(i => {
      if (i.product_id === tempId) {
        i.product_id = realId;
        // Its offline "opening stock" batch ids don't exist on the server — sell FIFO.
        if (i.product_stock_layer_id < 0) delete i.product_stock_layer_id;
      }
    });
    for (const e of box.outbox) { fix(e.body?.items); fix(e.receipt?.items); }
    if (box.isCurrent) {
      st.store.meta.product_id_map = { ...(st.store.meta.product_id_map || {}), [tempId]: realId };
      delete st.store.catalog.products[tempId];
      save('meta');
      save('catalog');
    } else {
      const metaFile = path.join(box.dir, 'meta.json');
      const meta = readJson(metaFile, {});
      meta.product_id_map = { ...(meta.product_id_map || {}), [tempId]: realId };
      writeJsonAtomic(metaFile, meta);
    }
  }

  // ── Customer CSV import offline: each row becomes an offline customer ───
  // (phone matches are linked to the existing customer on upload, as with
  // POST customers/offline from the mobile app).
  function customerImportPlan(s, rows, mode) {
    const cats = s.cache['/customer-categories']?.body?.data || [];
    const seen = new Set();
    return (rows || []).map((r, idx) => {
      const key = [String(r.phone || '').trim(), String(r.email || '').trim().toLowerCase()];
      const existing = s.customers.items.find(c =>
        (key[0] && String(c.phone || '').trim() === key[0]) || (key[1] && String(c.email || '').trim().toLowerCase() === key[1]));
      const dupInFile = key.some(k => k && seen.has(k));
      key.forEach(k => { if (k) seen.add(k); });
      const category = r.category ? cats.find(c => String(c.name).toLowerCase() === String(r.category).toLowerCase()) : null;
      let status = 'create', reason = null;
      if (!String(r.name || '').trim()) { status = 'error'; reason = 'Name is required.'; }
      else if (dupInFile) { status = 'skip'; reason = 'Repeated in this file.'; }
      else if (existing) { status = mode === 'update' ? 'update' : 'skip'; reason = mode === 'update' ? null : `Already a customer: ${existing.name}`; }
      return {
        row: idx + 1, name: r.name || null, phone: r.phone || null, email: r.email || null,
        status, reason, customer_id: existing?.id ?? null,
        fields: { ...pick(r, CUSTOMER_FIELDS), customer_category_id: category?.id ?? null },
      };
    });
  }

  function customerImportResponse(plan, preview) {
    const totals = { create: 0, update: 0, skip: 0, error: 0 };
    plan.forEach(p => { totals[p.status] += 1; });
    const notImported = plan.filter(p => p.status === 'skip' || p.status === 'error');
    return {
      status: 200,
      offline: true,
      body: {
        message: preview ? 'Checked on this PC (offline).' : SAVED_OFFLINE_MSG,
        imported: preview ? 0 : totals.create,
        updated: preview ? 0 : totals.update,
        skipped: totals.skip + totals.error,
        errors: notImported.map(p => ({ row: p.row, name: p.name || '', message: p.reason, type: p.status === 'skip' ? 'duplicate' : 'error' })),
        preview,
        totals,
        rows: plan.map(({ fields, ...row }) => row),
      },
    };
  }

  function queueCustomerImport(body, s) {
    const plan = customerImportPlan(s, body?.rows, body?.duplicates || 'skip');
    for (const p of plan) {
      if (p.status === 'create') queueCustomerCreate(p.fields, s);
      if (p.status === 'update') queueCustomerUpdate(p.customer_id, p.fields, s);
    }
    return customerImportResponse(plan, false);
  }

  // Try the server first; when it can't be reached, keep the change on this PC.
  async function writeOrQueue(method, p, body, queue) {
    if (st.online) {
      const res = await net(method, p, body, { timeout: 30000 });
      if (!isUnreachable(res.status)) {
        setOnline(true);
        return { res, queued: false };
      }
      setOnline(false);
    }
    return { res: queue(), queued: true };
  }

  function boxIdMap(box) {
    if (box.isCurrent) return st.store.customers.id_map || {};
    return readJson(path.join(box.dir, 'customers.json'), {}).id_map || {};
  }

  // A customer created offline got its real id: point everything at it.
  function mapCustomer(box, tempId, realId) {
    if (!tempId || !realId) return;
    for (const e of box.outbox) {
      if (e.body?.pos_customer_id === tempId) e.body.pos_customer_id = realId;
      if (e.receipt?.pos_customer_id === tempId) e.receipt.pos_customer_id = realId;
    }
    for (const w of box.writes) {
      if (w.path === `/customers/${tempId}`) w.path = `/customers/${realId}`;
    }
    const customers = box.isCurrent ? st.store.customers : readJson(path.join(box.dir, 'customers.json'), { items: [] });
    customers.id_map = { ...(customers.id_map || {}), [tempId]: realId };
    const row = customers.items.find(c => c.id === tempId);
    if (row) { row.id = realId; delete row.offline; }
    if (box.isCurrent) save('customers');
    else writeJsonAtomic(path.join(box.dir, 'customers.json'), customers);
  }

  // ── Sign-in and register-lock password, online or offline ──────────────
  function loginKey(pathname, body) {
    return pathname === '/cashier/login'
      ? `cashier:${String(body.slug || '').toLowerCase()}:${String(body.username || '').toLowerCase()}`
      : `user:${String(body.email || '').trim().toLowerCase()}`;
  }

  function loginToken(pathname, body) {
    return pathname === '/cashier/login'
      ? body?.data?.token
      : (body?.token || body?.data?.token || body?.access_token);
  }

  async function login(pathname, body = {}) {
    const key = loginKey(pathname, body);
    const res = await net('POST', pathname, body, { timeout: 20000 });
    if (!isUnreachable(res.status)) {
      st.online = true;
      if ((res.status === 200 || res.status === 201) && body.password && loginToken(pathname, res.body)) {
        const auth = loadAuth();
        const salt = crypto.randomBytes(16).toString('hex');
        auth.entries[key] = {
          salt,
          hash: passwordHash(body.password, salt),
          saved_at: new Date().toISOString(),
          token_key: tokenKey(loginToken(pathname, res.body)),
          response: res.body,
        };
        saveAuth(auth);
      }
      emit();
      return res;
    }

    setOnline(false);
    const entry = loadAuth().entries[key];
    if (!entry) {
      return { status: 503, offline: true, body: { message: 'No internet connection. The first sign-in on this PC needs internet — after that you can also sign in offline.' } };
    }
    if (Date.now() - new Date(entry.saved_at).getTime() > OFFLINE_LOGIN_DAYS * 24 * 3600 * 1000) {
      return { status: 503, offline: true, body: { message: `No internet connection, and your last online sign-in on this PC was more than ${OFFLINE_LOGIN_DAYS} days ago. Connect to the internet to sign in.` } };
    }
    if (!passwordMatches(body.password || '', entry)) {
      return { status: 422, offline: true, body: { message: 'Incorrect password.' } };
    }
    return { status: 200, offline: true, body: { ...entry.response, offline: true } };
  }

  async function verifyPassword(body = {}) {
    const tk = tokenKey(getConfig().token);
    if (st.online) {
      const res = await net('POST', '/verify-password', body, { timeout: 20000 });
      if (!isUnreachable(res.status)) {
        st.online = true;
        if (res.status === 200 && body.password && tk) {
          const auth = loadAuth();
          const salt = crypto.randomBytes(16).toString('hex');
          auth.entries[`token:${tk}`] = { salt, hash: passwordHash(body.password, salt), saved_at: new Date().toISOString(), token_key: tk };
          saveAuth(auth);
        }
        return res;
      }
      setOnline(false);
    }
    const entry = Object.values(loadAuth().entries)
      .filter(e => tk && e.token_key === tk)
      .sort((a, b) => String(b.saved_at).localeCompare(String(a.saved_at)))[0];
    if (!entry) {
      return { status: 503, offline: true, body: { message: 'No internet connection, and this password has not been checked on this PC yet. Connect to the internet to continue.' } };
    }
    return passwordMatches(body.password || '', entry)
      ? { status: 200, offline: true, body: { message: 'Password verified.' } }
      : { status: 422, offline: true, body: { message: 'Incorrect password.' } };
  }

  // ── POS actions: void / return / revert / end of day / cash drawer / … ──
  // Sent with an Idempotency-Key (the queued item's id) and, when queued,
  // occurred_at — the server runs each exactly once, dated when it happened.
  const POS_ACTIONS = [
    { kind: 'void',           re: /^\/sales\/(-?\d+)\/void$/,                  label: 'Void sale' },
    { kind: 'return',         re: /^\/sales\/(-?\d+)\/return$/,                label: 'Sale return' },
    { kind: 'revert',         re: /^\/sales\/(-?\d+)\/revert$/,                label: 'Revert bill' },
    { kind: 'eod',            re: /^\/eod\/settle$/,                           label: 'End-of-day settle' },
    { kind: 'cash_open',      re: /^\/cash-drawer\/open$/,                     label: 'Opening cash' },
    { kind: 'cash_withdraw',  re: /^\/cash-drawer\/withdraw$/,                 label: 'Cash withdrawal' },
    { kind: 'rental_return',  re: /^\/product-rentals\/(-?\d+)\/return$/,      label: 'Rental returned' },
    { kind: 'credit_payment', re: /^\/customers\/(-?\d+)\/credit-payments$/,   label: 'Credit payment received' },
  ];
  const SALE_REF_KINDS = ['void', 'return', 'revert'];

  function matchAction(method, pathname) {
    if (method !== 'POST') return null;
    for (const a of POS_ACTIONS) {
      const m = pathname.match(a.re);
      if (m) return { ...a, refId: m[1] !== undefined ? Number(m[1]) : null };
    }
    return null;
  }

  // Server ids for anything still pointing at a local (negative) sale or
  // customer. { waitFor } = not uploaded yet; { error } = can never upload.
  function resolveRefs(outbox, idMap, writes, kind, p, body) {
    const action = matchAction('POST', splitPath(p)[0]);
    const id = action?.refId;
    if (id == null || id >= 0) return { path: p, body };

    if (SALE_REF_KINDS.includes(kind)) {
      const sale = outbox.find(e => e.local_id === id);
      if (!sale) return { error: 'The offline sale this belongs to was discarded.' };
      if (sale.status === 'failed') return { error: `Its sale ${sale.offline_ref} could not upload.` };
      if (sale.status !== 'synced' || !sale.server_id) return { waitFor: sale.client_uuid };
      const out = { path: p.replace(`/sales/${id}/`, `/sales/${sale.server_id}/`), body: { ...body } };
      if (kind === 'return') {
        const mapped = mapReturnItems(sale, body.items || []);
        if (mapped.error) return { error: mapped.error };
        out.body.items = mapped.items;
      }
      return out;
    }
    if (kind === 'credit_payment') {
      if (idMap?.[id]) return { path: p.replace(`/customers/${id}/`, `/customers/${idMap[id]}/`), body };
      const create = writes.find(w => w.temp_id === id);
      if (!create || create.status === 'failed') return { error: 'Its new customer could not upload.' };
      return { waitFor: create.id };
    }
    return { error: 'This was created offline and has not uploaded yet.' };
  }

  // A return made against an offline receipt names its local line ids; the
  // server merged / split those lines by stock batch, so match by product.
  function mapReturnItems(sale, items) {
    const pool = (sale.server_items || []).map(i => ({ ...i, left: Number(i.quantity) }));
    const out = [];
    for (const line of items) {
      if (Number(line.sale_item_id) > 0) { out.push(line); continue; }
      const local = (sale.receipt?.items || []).find(i => i.id === Number(line.sale_item_id));
      if (!local) return { error: 'A returned line is not on the original receipt.' };
      let qty = Number(line.quantity);
      const candidates = pool
        .filter(i => i.product_id === local.product_id && i.left > 0)
        .sort((a, b) => (b.layer === local.product_stock_layer_id) - (a.layer === local.product_stock_layer_id));
      for (const c of candidates) {
        if (qty <= 0) break;
        const take = Math.min(qty, c.left);
        out.push({ sale_item_id: c.id, quantity: Math.round(take * 1000) / 1000 });
        c.left -= take;
        qty -= take;
      }
      if (qty > 0.0005) return { error: `Could not match the returned quantity of ${local.product_name}.` };
    }
    return { items: out };
  }

  // Sale detail as this PC knows it: an offline receipt, or the cached server copy.
  function knownSale(s, id) {
    if (id < 0) return s.outbox.find(e => e.local_id === id)?.receipt || null;
    return s.cache[`/sales/${id}`]?.body?.data || null;
  }

  function setKnownSaleStatus(s, id, status) {
    if (id < 0) {
      const e = s.outbox.find(x => x.local_id === id);
      if (e) { e.voided = status === 'void'; e.receipt.status = status; save('outbox'); }
      return;
    }
    const hit = s.cache[`/sales/${id}`];
    if (hit?.body?.data) hit.body.data.status = status;
    for (const [k, v] of Object.entries(s.cache)) {
      if (!/^\/sales(\/history)?\?/.test(k) || !Array.isArray(v.body?.data)) continue;
      v.body.data.forEach(r => { if (r.id === id) r.status = status; });
    }
    save('cache');
  }

  function restoreLocalStock(s, lines) {
    for (const l of lines) {
      const card = l.product_id ? s.catalog.products[l.product_id] : null;
      if (!card) continue;
      const qty = Number(l.quantity) || 0;
      card.stock_quantity = Math.round((Number(card.stock_quantity || 0) + qty) * 1000) / 1000;
      if (card.available_stock != null) card.available_stock = Math.round((Number(card.available_stock) + qty) * 1000) / 1000;
      const layer = (card.layers || []).find(x => x.id === l.product_stock_layer_id) || (card.layers || [])[0];
      if (layer) layer.quantity_remaining = Math.round((Number(layer.quantity_remaining) + qty) * 1000) / 1000;
    }
    save('catalog');
  }

  function settlementDeferred(s) {
    const settings = s.cache['/online/settings']?.body?.data || s.meta.bootstrap?.settings || {};
    return settings.payment_settlement_mode === 'end_of_day';
  }

  // Offline sales the server's cached numbers don't include yet.
  function unsyncedSince(s, at, filter = () => true) {
    const today = new Date().toDateString();
    return s.outbox.filter(e => !e.voided
      && new Date(e.sold_at).toDateString() === today
      && (e.status !== 'synced' || new Date(e.synced_at).getTime() > at)
      && filter(e));
  }

  function localCashDrawer(s) {
    const hit = s.cache['/cash-drawer'];
    const today = todayYmd();
    let data = hit?.body?.data ? JSON.parse(JSON.stringify(hit.body.data)) : null;
    if (!data || data.date !== today) {
      data = { date: today, is_opened: false, opening_float: null, cash_sales: 0, total_withdrawals: 0, balance: null, withdrawals: [] };
    }
    const extra = unsyncedSince(s, hit?.at || 0, e => e.body?.payment_method === 'cash')
      .reduce((a, e) => a + Number(e.receipt?.total || 0), 0);
    data.cash_sales = Math.round((Number(data.cash_sales || 0) + extra) * 100) / 100;
    data.balance = data.opening_float != null
      ? Math.round((Number(data.opening_float) + data.cash_sales - Number(data.total_withdrawals || 0)) * 100) / 100
      : null;
    return data;
  }

  // Changes the cached drawer (without the offline-sales overlay, which is added on read).
  function editCashDrawer(s, fn) {
    const today = todayYmd();
    const hit = s.cache['/cash-drawer'];
    let base = hit?.body?.data;
    if (!base || base.date !== today) {
      base = { date: today, is_opened: false, opening_float: null, cash_sales: 0, total_withdrawals: 0, balance: null, withdrawals: [] };
    }
    fn(base);
    s.cache['/cash-drawer'] = { at: hit && hit.body?.data?.date === today ? hit.at : Date.now(), body: { data: base } };
    save('cache');
    return localCashDrawer(s);
  }

  function localEod(s, hit) {
    const body = JSON.parse(JSON.stringify(hit.body));
    if (!settlementDeferred(s)) return { status: 200, offline: true, body };
    const rows = s.outbox.filter(e => !e.voided && !e.eod_local && e.status !== 'synced'
      && ['cash', 'card'].includes(e.body?.payment_method));
    body.unsettled = body.unsettled || [];
    body.summary = body.summary || { total_count: 0, total_amount: 0, by_method: {} };
    body.summary.by_method = body.summary.by_method || {};
    for (const e of rows) {
      const net = Math.round((Number(e.receipt.total) - Number(e.receipt.gift_card_amount || 0)) * 100) / 100;
      body.unsettled.unshift({ id: e.local_id, sale_number: e.offline_ref, payment_method: e.body.payment_method, total: net, sold_at: e.sold_at, account_label: null });
      body.summary.total_count += 1;
      body.summary.total_amount = Math.round((body.summary.total_amount + net) * 100) / 100;
      const bm = body.summary.by_method[e.body.payment_method] || { count: 0, total: 0 };
      body.summary.by_method[e.body.payment_method] = { count: bm.count + 1, total: Math.round((bm.total + net) * 100) / 100 };
    }
    return { status: 200, offline: true, body };
  }

  function applyLocalEffect(action, body, s) {
    const ok = (data, status = 200, extra = {}) => ({ status, offline: true, body: { message: SAVED_OFFLINE_MSG, data, ...extra } });
    switch (action.kind) {
      case 'void':
      case 'revert': {
        const sale = knownSale(s, action.refId);
        if (sale) restoreLocalStock(s, sale.items || []);
        setKnownSaleStatus(s, action.refId, 'void');
        const saleView = sale ? { ...sale, status: 'void' } : { id: action.refId, status: 'void' };
        if (action.kind === 'void') return ok(saleView);
        return ok({
          revert: { revert_number: 'Waiting to upload', reason_label: body.reason, notes: body.notes || null, amount_refunded: Number(sale?.amount_paid || 0), reverted_at: new Date().toISOString() },
          sale: saleView,
        }, 201);
      }
      case 'return': {
        const sale = knownSale(s, action.refId);
        const lines = (body.items || []).map(l => {
          const it = (sale?.items || []).find(i => i.id === Number(l.sale_item_id));
          return it ? { product_id: it.product_id, product_stock_layer_id: it.product_stock_layer_id, quantity: Number(l.quantity), price: Number(it.unit_sell_price) } : null;
        }).filter(Boolean);
        restoreLocalStock(s, lines);
        if (sale) {
          for (const l of body.items || []) {
            const it = sale.items.find(i => i.id === Number(l.sale_item_id));
            if (it) it.returned_quantity = Math.round((Number(it.returned_quantity || 0) + Number(l.quantity)) * 1000) / 1000;
          }
          action.refId < 0 ? save('outbox') : save('cache');
        }
        const total = Math.round(lines.reduce((a, l) => a + l.price * l.quantity, 0) * 100) / 100;
        return ok({ return_number: 'Waiting to upload', total, sale }, 201);
      }
      case 'eod': {
        const pending = s.outbox.filter(e => !e.voided && !e.eod_local && e.status !== 'synced' && ['cash', 'card'].includes(e.body?.payment_method));
        pending.forEach(e => { e.eod_local = true; });
        save('outbox');
        const hit = s.cache['/eod'];
        let settled = pending.length;
        if (hit?.body) {
          settled += Number(hit.body.summary?.total_count || 0);
          const amount = Number(hit.body.summary?.total_amount || 0) + pending.reduce((a, e) => a + Number(e.receipt.total) - Number(e.receipt.gift_card_amount || 0), 0);
          hit.body.history = [{ date: todayYmd(), sale_count: settled, total: Math.round(amount * 100) / 100 }, ...(hit.body.history || [])];
          hit.body.unsettled = [];
          hit.body.summary = { total_count: 0, total_amount: 0, by_method: { cash: { count: 0, total: 0 }, card: { count: 0, total: 0 } } };
          save('cache');
        }
        return { status: 200, offline: true, body: { message: `${settled} sale${settled === 1 ? '' : 's'} will settle to bank when the internet is back.`, settled, errors: [] } };
      }
      case 'cash_open':
        return ok(editCashDrawer(s, d => { d.is_opened = true; d.opening_float = Math.max(0, Number(body.opening_float) || 0); }));
      case 'cash_withdraw':
        return ok(editCashDrawer(s, d => {
          const amount = Math.max(0, Number(body.amount) || 0);
          d.withdrawals = [...(d.withdrawals || []), { id: null, amount, note: body.note || null, time: new Date().toISOString() }];
          d.total_withdrawals = Math.round((Number(d.total_withdrawals || 0) + amount) * 100) / 100;
        }));
      case 'rental_return': {
        let row = null;
        for (const [k, v] of Object.entries(s.cache)) {
          if (!k.startsWith('/product-rentals') || !Array.isArray(v.body?.data)) continue;
          v.body.data.forEach(r => { if (r.id === action.refId) { r.status = 'returned'; r.returned_at = todayYmd(); row = r; } });
        }
        save('cache');
        if (row) restoreLocalStock(s, [{ product_id: row.product_id, quantity: row.quantity }]);
        return ok(row || { id: action.refId, status: 'returned' });
      }
      case 'credit_payment': {
        const c = s.customers.items.find(x => x.id === action.refId);
        const amount = Number(body.amount) || 0;
        if (c) {
          c.credit_outstanding = Math.max(0, Math.round((Number(c.credit_outstanding || 0) - amount) * 100) / 100);
          if (c.credit_available != null) c.credit_available = Math.round((Number(c.credit_available) + amount) * 100) / 100;
          save('customers');
        }
        return ok({ amount });
      }
      default:
        return ok(null);
    }
  }

  async function performAction(action, method, p, body, s) {
    const key = crypto.randomUUID();
    const occurredAt = new Date().toISOString();
    const r = resolveRefs(s.outbox, s.customers.id_map, s.writes, action.kind, p, body);
    if (r.error) return { status: 422, offline: true, body: { message: r.error } };

    if (!r.waitFor && st.online) {
      const res = await net(method, r.path, r.body, { timeout: 30000, idempotencyKey: key });
      if (!isUnreachable(res.status)) {
        setOnline(true);
        return res;
      }
      setOnline(false);
      // Timed out: it may even have run — the same key on upload gets that answer back.
    }
    addWrite(s, { id: key, kind: action.kind, method, path: p, body: { ...body, occurred_at: occurredAt }, label: action.label, created_at: occurredAt });
    return applyLocalEffect(action, body, s);
  }

  // ── Every other change (bills, employees, invoices, service requests, …) ─
  // Tried online first; without internet it is kept on this PC, shown at once
  // in the downloaded lists, and uploaded in order with an Idempotency-Key.
  // A record added offline gets a temporary id; later changes that use it
  // (in the URL or the form) wait for it and then use its real id.
  function boxMeta(box) {
    return box.isCurrent ? st.store.meta : readJson(path.join(box.dir, 'meta.json'), {});
  }

  // Temporary id → server id, for everything this PC added offline.
  function tempIdMap(outbox, customerMap, meta) {
    const map = { ...(customerMap || {}), ...(meta?.product_id_map || {}), ...(meta?.generic_id_map || {}) };
    for (const e of outbox) if (e.server_id) map[e.local_id] = e.server_id;
    return map;
  }

  // { path, body } with real ids, or { waitFor } / { error } like resolveRefs.
  function resolveGeneric(p, body, map, outbox, writes) {
    let missing = null;
    const swap = v => {
      if (!isTempId(v)) return v;
      const real = map[String(Number(v))];
      if (real != null) return typeof v === 'string' ? String(real) : real;
      if (missing === null) missing = Number(v);
      return v;
    };
    const [pathname, query] = [splitPath(p)[0], p.includes('?') ? p.slice(p.indexOf('?')) : ''];
    const realPath = pathname.split('/').map(seg => (isTempId(seg) ? String(swap(seg)) : seg)).join('/') + query;
    const realBody = deepMap(body, swap);
    if (missing === null) return { path: realPath, body: realBody };
    const create = writes.find(w => w.temp_id === missing) || outbox.find(e => e.local_id === missing);
    if (!create) return { error: 'It uses a record added offline that was then discarded.' };
    if (create.status === 'failed') return { error: 'It uses a record added offline that could not upload.' };
    return { waitFor: create.id || create.client_uuid };
  }

  // Cached lists of a collection (any search / filter / page of it).
  function eachCachedList(s, collection, fn) {
    const keys = [];
    for (const [key, hit] of Object.entries(s.cache)) {
      if (splitPath(key)[0] !== collection) continue;
      const found = findRows(hit.body);
      if (found && fn(found.holder[found.key], found) !== false) keys.push(key);
    }
    return keys;
  }

  function cachedDetailSample(s, collection) {
    const re = new RegExp(`^${collection.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/\\d+$`);
    const key = Object.keys(s.cache).find(k => re.test(k));
    const data = key ? s.cache[key].body?.data : null;
    return data && typeof data === 'object' && !Array.isArray(data) ? data : null;
  }

  function applyGenericEffect(m, t, body, s, tempId) {
    const ok = (data, status = 200) => ({ status, offline: true, body: { message: SAVED_OFFLINE_MSG, data } });
    const fields = primitiveFields(body);
    const now = new Date().toISOString();
    let touched = [];
    let row = null;

    if (tempId != null) {
      // New record: on top of every downloaded list of its collection.
      const extras = offlineRecordExtras(t.collection, body, s);
      touched = eachCachedList(s, t.collection, rows => {
        const r = { ...blankLike(rows[0]), ...fields, ...extras.row, id: tempId, offline: true };
        if ('created_at' in r) r.created_at = now;
        rows.unshift(r);
        row = row || r;
      });
      row = row || { ...fields, ...extras.row, id: tempId, offline: true, created_at: now };
      const detailKey = `${t.collection}/${tempId}`;
      const detail = { ...blankLike(cachedDetailSample(s, t.collection)), ...row, ...extras.detail };
      s.cache[detailKey] = { at: Date.now(), body: { data: detail } };
      saveCacheKeys([...touched, detailKey]);
      return ok(JSON.parse(JSON.stringify(detail)), 201);
    }

    if (t.id === null) {
      // A single settings-like record (PUT /hr/settings, PATCH /mail/settings, …).
      const hit = s.cache[t.collection];
      if ((m === 'PUT' || m === 'PATCH') && hit?.body?.data && typeof hit.body.data === 'object' && !Array.isArray(hit.body.data)) {
        Object.assign(hit.body.data, fields);
        saveCacheKeys([t.collection]);
        return ok(hit.body.data);
      }
      return ok(null);
    }

    const detailKey = `${t.collection}/${t.id}`;
    const detail = s.cache[detailKey]?.body?.data;
    const status = t.rest[0] === 'status' && typeof body?.status === 'string' ? body.status
      : (m === 'POST' && t.rest.length === 1 ? ACTION_STATUS[t.rest[0]] : null);
    const change = r => {
      if (!t.rest.length) Object.assign(r, fields);
      else if (status) {
        r.status = status;
        if ('status_label' in r) r.status_label = status.charAt(0).toUpperCase() + status.slice(1);
      }
    };
    if (!t.rest.length && m === 'DELETE') {
      touched = eachCachedList(s, t.collection, (rows, found) => {
        const left = rows.filter(r => String(r.id) !== String(t.id));
        if (left.length === rows.length) return false;
        found.holder[found.key] = left;
      });
      if (s.cache[detailKey]) { delete s.cache[detailKey]; touched.push(detailKey); }
    } else if (!t.rest.length || status) {
      touched = eachCachedList(s, t.collection, rows => {
        const r = rows.find(x => String(x.id) === String(t.id));
        if (!r) return false;
        change(r);
        row = row || r;
      });
      if (detail && typeof detail === 'object') { change(detail); touched.push(detailKey); }
    }

    // Customers are listed from their own full copy, not the screen cache.
    if (t.collection === '/customers' && !t.rest.length && m === 'DELETE') {
      s.customers.items = s.customers.items.filter(c => String(c.id) !== String(t.id));
      save('customers');
    }
    // A product edited or deleted in Inventory: the POS grid follows at once.
    if (t.collection === '/online/products' && !t.rest.length && s.catalog.products[t.id]) {
      const card = s.catalog.products[t.id];
      if (m === 'DELETE' || body?.is_active === false) delete s.catalog.products[t.id];
      else {
        if (body?.name) card.name = body.name;
        if (body?.sku !== undefined) card.sku = body.sku;
        if (body?.unit_price != null && body.unit_price !== '') card.unit_sell_price = Number(body.unit_price);
      }
      save('catalog');
    }
    if (touched.length) saveCacheKeys(touched);
    return ok(row || (detail && typeof detail === 'object' ? detail : { id: t.id, ...fields }));
  }

  // Records added offline that a screen shows in full straight away — an
  // invoice printed after an offline sale: the fields the server works out
  // (ModulesSalesServicesInvoiceService::recalculateTotals). Its number is
  // the offline sale's until the server gives it a real one on upload.
  function offlineRecordExtras(collection, body, s) {
    if (collection !== '/invoices') return { row: {}, detail: {} };
    const r2 = v => Math.round(v * 100) / 100;
    const items = (body.items || []).map((i, idx) => {
      const quantity = Number(i.quantity) || 0;
      const unitPrice = Number(i.unit_price) || 0;
      return {
        id: -(idx + 1), product_id: i.product_id ?? null, item_type: i.item_type || 'custom', is_subscription: false,
        description: i.description || '', quantity, unit_price: unitPrice,
        discount_type: 'pct', discount_value: 0, tax_pct: 0, tax_type: 'pct', line_total: r2(quantity * unitPrice),
      };
    });
    const subtotal = r2(items.reduce((a, i) => a + i.line_total, 0));
    const discount = Number(body.discount_amount) || 0;
    const tax = Number(body.tax_amount) || 0;
    const customer = body.customer_id != null ? s.customers.items.find(c => String(c.id) === String(body.customer_id)) : null;
    return {
      row: {
        invoice_number: body.reference || 'Waiting to upload', status: 'draft', status_label: 'Draft',
        customer_name: customer?.name ?? null, reference: body.reference ?? null, issue_date: body.issue_date ?? null,
        due_date: body.due_date ?? null, is_overdue: false, total: r2(Math.max(0, subtotal - discount + tax)),
      },
      detail: {
        subtotal, discount_amount: r2(discount), tax_amount: r2(tax), notes: body.notes ?? null,
        customer_id: body.customer_id ?? null, is_editable: false, share_token: null, share_url: null, items,
      },
    };
  }

  function queueGeneric(m, p, body, s, key) {
    const t = parseTarget(m, splitPath(p)[0]);
    const tempId = t.create ? newTempId() : null;
    addWrite(s, {
      id: key, kind: 'generic', method: m, path: p, body,
      temp_id: tempId, collection: t.collection, label: genericLabel(m, t, body),
    });
    return applyGenericEffect(m, t, body, s, tempId);
  }

  let lastTempId = 0;
  function newTempId() {
    let id = -Date.now();
    if (id >= lastTempId) id = lastTempId - 1;
    lastTempId = id;
    return id;
  }

  async function genericWrite(m, p, body, s) {
    const key = crypto.randomUUID();
    const r = resolveGeneric(p, body, tempIdMap(s.outbox, s.customers.id_map, s.meta), s.outbox, s.writes);
    if (r.error) return { status: 422, offline: true, body: { message: r.error } };
    if (!r.waitFor && st.online) {
      // Same key as a later upload: if this times out after the server saved
      // it, the upload gets that answer back instead of saving it twice.
      const res = await net(m, r.path, r.body, { timeout: 30000, idempotencyKey: key });
      if (!isUnreachable(res.status)) {
        setOnline(true);
        if (res.status < 300 && CATALOG_WRITES.test(splitPath(p)[0])) catalogChanged();
        return res;
      }
      setOnline(false);
    }
    return queueGeneric(m, p, body, s, key);
  }

  // A record added offline got its real id.
  function mapGeneric(box, item, realId) {
    if (!item.temp_id || realId == null) return;
    const meta = boxMeta(box);
    meta.generic_id_map = { ...(meta.generic_id_map || {}), [item.temp_id]: realId };
    if (!box.isCurrent) { writeJsonAtomic(path.join(box.dir, 'meta.json'), meta); return; }
    save('meta');
    const s = st.store;
    const touched = eachCachedList(s, item.collection, rows => {
      const r = rows.find(x => x.id === item.temp_id);
      if (!r) return false;
      r.id = realId;
      delete r.offline;
    });
    const oldKey = `${item.collection}/${item.temp_id}`;
    if (s.cache[oldKey]) {
      const hit = s.cache[oldKey];
      delete s.cache[oldKey];
      if (hit.body?.data) { hit.body.data.id = realId; delete hit.body.data.offline; }
      s.cache[`${item.collection}/${realId}`] = hit;
      touched.push(oldKey, `${item.collection}/${realId}`);
    }
    saveCacheKeys(touched);
  }

  // ── Outbox upload ──────────────────────────────────────────────────────
  let outboxRun = null;
  function syncOutbox() {
    if (!outboxRun) outboxRun = runOutboxSync().finally(() => { outboxRun = null; });
    return outboxRun;
  }

  async function runOutboxSync() {
    if (!getConfig().token) return;
    st.syncingOutbox = true;
    emit();
    let uploaded = 0;
    const failedEvent = entry => logEvent('failed', `Not accepted: ${entry.label || entry.offline_ref} — ${entry.error}`, { ref: entry.client_uuid || entry.id });

    // Uploads one queued item. Returns false when syncing must stop for now.
    async function upload(box, entry, method, p, body, idempotencyKey) {
      const res = await bgNet(method, p, body, { timeout: 60000, businessId: box.businessId, branchId: box.branchId, idempotencyKey });
      entry.attempts = (entry.attempts || 0) + 1;
      entry.last_attempt_at = new Date().toISOString();
      if (isUnreachable(res.status)) {
        setOnline(false);
        saveOutbox(box);
        return { stop: true };
      }
      st.online = true;
      if (res.status >= 200 && res.status < 300) {
        entry.status = 'synced';
        entry.synced_at = new Date().toISOString();
        entry.error = null;
        uploaded += 1;
        return { res };
      }
      // An offline change this user may not make: only that change fails.
      if (res.status === 403 && entry.kind === 'generic') {
        entry.status = 'failed';
        entry.error = res.body?.message || 'You do not have permission for this.';
        failedEvent(entry);
        return {};
      }
      if (res.status === 401 || res.status === 402 || res.status === 403) {
        // Signed out / subscription / permission — nothing to retry until the user acts.
        entry.error = res.body?.message || `Server refused the upload (${res.status}).`;
        st.lastError = entry.error;
        saveOutbox(box);
        return { stop: true };
      }
      if (res.status === 422 || res.status === 404) {
        entry.status = 'failed';
        entry.error = res.body?.message || 'The server rejected this.';
        failedEvent(entry);
        return {};
      }
      entry.error = res.body?.message || `Server error (${res.status}). Will retry.`;
      st.lastError = entry.error;
      saveOutbox(box);
      scheduleOutboxRetry();
      return { stop: true };
    }

    try {
      for (const box of allOutboxes()) {
        const cutoff = Date.now() - SYNCED_KEEP_MS;
        const keep = e => e.status !== 'synced' || new Date(e.synced_at).getTime() > cutoff;
        const before = box.outbox.length + box.writes.length;
        box.outbox = box.outbox.filter(keep);
        box.writes = box.writes.filter(keep);
        if (box.outbox.length + box.writes.length !== before) saveOutbox(box);

        // Everything in the order it happened: a customer before the sale to
        // them, a sale before its return, sales before the end-of-day settle.
        const queue = [
          ...box.writes.map(item => ({ type: 'write', item, at: item.created_at })),
          ...box.outbox.map(item => ({ type: 'sale', item, at: item.created_at })),
        ].sort((a, b) => String(a.at).localeCompare(String(b.at)));

        for (const { type, item } of queue) {
          if (item.status !== 'pending') continue;

          if (type === 'sale') {
            if (item.body?.pos_customer_id < 0) {
              const create = box.writes.find(w => w.temp_id === item.body.pos_customer_id);
              if (!create || create.status === 'failed') {
                item.status = 'failed';
                item.error = 'Its new customer could not upload.';
              } else {
                item.error = 'Waiting for its new customer to upload first.';
              }
              continue;
            }
            const newProduct = (item.body?.items || []).find(i => i.product_id < 0);
            if (newProduct) {
              const create = box.writes.find(w => w.temp_id === newProduct.product_id && w.kind === 'product_create');
              if (!create || create.status === 'failed') {
                item.status = 'failed';
                item.error = 'A new product on this sale could not upload.';
              } else {
                item.error = 'Waiting for its new product to upload first.';
              }
              continue;
            }
            const r = await upload(box, item, 'POST', '/online/checkout', item.body);
            if (r.stop) return;
            if (r.res) {
              const data = r.res.body?.data || {};
              item.sale_number = data.sale_number || null;
              item.server_id = data.id || null;
              // For returns made against the offline receipt (mapReturnItems)
              item.server_items = (data.items || []).map(i => ({ id: i.id, product_id: i.product_id, layer: i.product_stock_layer_id, quantity: i.quantity }));
            }
          } else {
            const resolved = item.kind === 'generic'
              ? resolveGeneric(item.path, item.body, tempIdMap(box.outbox, boxIdMap(box), boxMeta(box)), box.outbox, box.writes)
              : resolveRefs(box.outbox, boxIdMap(box), box.writes, item.kind, item.path, item.body);
            if (resolved.error) {
              item.status = 'failed';
              item.error = resolved.error;
              failedEvent(item);
            } else if (resolved.waitFor) {
              item.error = 'Waiting for its sale or customer to upload first.';
              continue;
            } else {
              const r = await upload(box, item, item.method, resolved.path, resolved.body, item.id);
              if (r.stop) return;
              if (r.res && item.kind === 'customer_create') mapCustomer(box, item.temp_id, r.res.body?.data?.id);
              if (r.res && item.kind === 'product_create') mapProduct(box, item.temp_id, r.res.body?.data?.id);
              if (r.res && item.kind === 'generic') {
                mapGeneric(box, item, r.res.body?.data?.id ?? r.res.body?.id ?? null);
                // Its screens come back from the server on the next refresh.
                if (box.isCurrent) st.refreshCollections.add(item.collection);
              }
            }
          }
          saveOutbox(box);
          emit();
        }
      }
      st.lastError = null;
    } finally {
      st.syncingOutbox = false;
      if (uploaded > 0) logEvent('uploaded', `Uploaded ${uploaded} item${uploaded === 1 ? '' : 's'} saved offline`, { count: uploaded });
      emit();
    }
    if (uploaded > 0) syncCatalog();
  }

  function scheduleOutboxRetry() {
    if (st.outboxTimer) return;
    st.outboxTimer = setTimeout(() => { st.outboxTimer = null; syncOutbox(); }, OUTBOX_RETRY_MS);
  }

  // Sales are keyed by client_uuid, queued changes by id.
  function findEntry(id) {
    for (const box of allOutboxes()) {
      const sale = box.outbox.find(e => e.client_uuid === id);
      if (sale) return { box, entry: sale, list: 'outbox' };
      const write = box.writes.find(w => w.id === id);
      if (write) return { box, entry: write, list: 'writes' };
    }
    return null;
  }

  function retryEntry(id) {
    const hit = findEntry(id);
    if (!hit || hit.entry.status === 'synced') return false;
    hit.entry.status = 'pending';
    hit.entry.error = null;
    saveOutbox(hit.box);
    logEvent('retry', `Retrying: ${hit.entry.label || hit.entry.offline_ref}`, { ref: id });
    emit();
    syncOutbox();
    return true;
  }

  function discardEntry(id) {
    const hit = findEntry(id);
    if (!hit || hit.entry.status === 'synced') return false;
    hit.box[hit.list] = hit.box[hit.list].filter(e => (hit.list === 'outbox' ? e.client_uuid : e.id) !== id);
    saveOutbox(hit.box);
    // Its screens still show the change made on this PC: take the server's again.
    if (hit.entry.kind === 'generic' && hit.box.isCurrent) st.refreshCollections.add(hit.entry.collection);
    logEvent('discarded', `Discarded: ${hit.entry.label || hit.entry.offline_ref}`);
    emit();
    return true;
  }

  function listOutbox() {
    const changes = allOutboxes().flatMap(box => box.writes.map(w => ({
      kind: w.kind,
      client_uuid: w.id,
      offline_ref: w.label,
      sold_at: w.created_at,
      status: w.status,
      error: w.error,
      attempts: w.attempts,
      business_id: box.businessId,
      method: w.method || null,
      path: w.path || null,
      created_at: w.created_at,
      last_attempt_at: w.last_attempt_at || null,
      synced_at: w.synced_at || null,
    })));
    return [...changes, ...allOutboxes().flatMap(box => box.outbox.map(e => ({
      kind: 'sale',
      client_uuid: e.client_uuid,
      offline_ref: e.offline_ref,
      sold_at: e.sold_at,
      status: e.status,
      error: e.error,
      attempts: e.attempts,
      sale_number: e.sale_number,
      total: e.receipt?.total ?? null,
      payment_method: e.body?.payment_method,
      item_count: (e.body?.items || []).length,
      business_id: e.business_id,
      receipt: e.receipt,
      method: 'POST',
      path: '/online/checkout',
      customer_name: e.receipt?.customer_name || null,
      created_at: e.created_at,
      last_attempt_at: e.last_attempt_at || null,
      synced_at: e.synced_at || null,
    })))].sort((a, b) => String(b.sold_at).localeCompare(String(a.sold_at)));
  }

  // ── Catalog download (products, customers, POS settings) ───────────────
  // A run already in progress is shared, so "Sync now" waits for it to finish.
  let catalogRun = null;
  function syncCatalog(opts) {
    if (!catalogRun) catalogRun = runCatalogSync(opts).finally(() => { catalogRun = null; });
    return catalogRun;
  }

  // POS settings/categories (one tiny bootstrap page) + only the products
  // whose fingerprint changed. Returns false when the connection dropped.
  async function syncProducts(s, full = false) {
    const boot = await bgNet('GET', '/online/bootstrap?q=&category=&page=1&per_page=1', null, { timeout: 60000 });
    if (isUnreachable(boot.status)) { setOnline(false); return false; }
    if (boot.status === 200) remember('/online/bootstrap', boot.body);

    const idx = await bgNet('GET', '/online/products/sync-index', null, { timeout: 120000 });
    if (isUnreachable(idx.status)) { setOnline(false); return false; }
    if (idx.status !== 200) throw new Error(idx.body?.message || `sync-index failed (${idx.status})`);
    const { version, items = [] } = idx.body.data || {};
    if (full || version !== s.catalog.version) {
      s.catalog.fp = {};
      s.catalog.version = version;
    }
    const live = new Set(items.map(i => String(i.id)));
    for (const id of Object.keys(s.catalog.products)) {
      // Products added offline (negative ids) stay until they upload.
      if (!live.has(id) && Number(id) > 0) { delete s.catalog.products[id]; delete s.catalog.fp[id]; }
    }
    const changed = items.filter(i => s.catalog.fp[i.id] !== i.fp || !s.catalog.products[i.id]);
    st.productChanges = changed.length;
    st.catalogProgress = { step: 'products', done: 0, total: changed.length };
    emit();
    for (let k = 0; k < changed.length; k += BY_IDS_CHUNK) {
      const chunk = changed.slice(k, k + BY_IDS_CHUNK);
      const res = await bgNet('GET', `/online/products/by-ids?ids=${chunk.map(i => i.id).join(',')}`, null, { timeout: 120000 });
      if (isUnreachable(res.status)) { save('catalog'); setOnline(false); return false; }
      if (res.status !== 200) throw new Error(res.body?.message || `by-ids failed (${res.status})`);
      const fps = Object.fromEntries(chunk.map(i => [i.id, i.fp]));
      for (const card of res.body.data || []) {
        s.catalog.products[card.id] = card;
        s.catalog.fp[card.id] = fps[card.id];
      }
      st.catalogProgress = { step: 'products', done: Math.min(k + BY_IDS_CHUNK, changed.length), total: changed.length };
      emit();
    }
    s.catalog.synced_at = new Date().toISOString();
    save('catalog');
    return true;
  }

  // Quick product-only refresh (every few minutes, and soon after this PC
  // changes products or stock) — keeps the instant local search current.
  let productRun = null;
  let productSoonTimer = null;
  function syncProductsQuick() {
    const s = store();
    if (catalogRun || productRun || !s || !st.online || !getConfig().token) return catalogRun || productRun || Promise.resolve();
    productRun = syncProducts(s)
      .catch(err => console.error('[offline] product refresh', err.message))
      .finally(() => { productRun = null; st.catalogProgress = null; emit(); });
    return productRun;
  }
  function syncProductsSoon() {
    clearTimeout(productSoonTimer);
    productSoonTimer = setTimeout(() => { productSoonTimer = null; syncProductsQuick(); }, 3000);
  }

  async function runCatalogSync({ full = false } = {}) {
    const s = store();
    if (!s || !st.online || !getConfig().token) return;
    if (productRun) await productRun;
    st.syncingCatalog = true;
    st.catalogProgress = { step: 'products', done: 0, total: 0 };
    emit();
    try {
      if (!(await syncProducts(s, full))) return;

      // Customers (full list — they are small)
      st.catalogProgress = { step: 'customers', done: 0, total: 0 };
      emit();
      const customers = [];
      for (let page = 1, last = 1; page <= last; page += 1) {
        const res = await bgNet('GET', `/customers/offline-sync?page=${page}&per_page=500`, null, { timeout: 120000 });
        if (isUnreachable(res.status)) { setOnline(false); return; }
        if (res.status !== 200) throw new Error(res.body?.message || `customers failed (${res.status})`);
        customers.push(...(res.body.data || []));
        last = res.body.meta?.last_page || 1;
        st.catalogProgress = { step: 'customers', done: page, total: last };
        emit();
      }
      // Keep customers added offline that have not uploaded yet.
      const notUploaded = s.customers.items.filter(c => c.id < 0);
      s.customers = { synced_at: new Date().toISOString(), items: [...customers, ...notUploaded], id_map: s.customers.id_map || {} };
      save('customers');

      // POS screens — cached so they open offline, even right after a restart.
      st.catalogProgress = { step: 'screens', done: 0, total: WARM_PATHS.length };
      // Don't overwrite this PC's copy while its offline changes are still waiting to upload.
      const waiting = kinds => s.writes.some(w => kinds.includes(w.kind) && w.status !== 'synced');
      const salesWaiting = test => s.outbox.some(e => e.status !== 'synced' && test(e));
      const keepLocal = {
        '/online/settings':        () => waiting(['settings']),
        '/cash-drawer':            () => waiting(['cash_open', 'cash_withdraw']),
        '/eod':                    () => waiting(['eod']),
        '/gift-cards?q=&status=':  () => salesWaiting(e => e.body?.gift_card_code),
        '/coupons?q=&status=':     () => salesWaiting(e => e.body?.coupon_code),
      };
      // …nor the screens of a collection with offline changes waiting.
      const pendingCollections = s.writes.filter(w => w.kind === 'generic' && w.status !== 'synced').map(w => w.collection);
      const under = (p, cols) => { const pn = splitPath(p)[0]; return cols.some(c => pn === c || pn.startsWith(`${c}/`)); };
      const refreshed = [...st.refreshCollections];
      // Fetches one screen; false once the connection is gone.
      let reachable = true;
      const fetchScreen = async (p, maxAgeMs = 0) => {
        if (!reachable || under(p, pendingCollections)) return reachable;
        const hit = s.cache[p];
        if (!full && maxAgeMs && hit && Date.now() - hit.at < maxAgeMs && !under(p, refreshed)) return true;
        const res = await bgNet('GET', p, null, { timeout: 30000 });
        if (isUnreachable(res.status)) { reachable = false; return false; }
        if (res.status === 200) remember(p, res.body);
        return true;
      };

      for (const p of WARM_PATHS) {
        if (keepLocal[p]?.()) continue;
        if (p.startsWith('/product-rentals') && waiting(['rental_return'])) continue;
        if (!(await fetchScreen(p))) break;
      }
      const recent = Array.isArray(s.cache['/sales?q=']?.body?.data) ? s.cache['/sales?q='].body.data.slice(0, WARM_SALE_DETAILS) : [];
      for (const row of recent) {
        if (!(row.id > 0) || waiting(['void', 'return', 'revert'])) break;
        if (!(await fetchScreen(`/sales/${row.id}`))) break;
      }

      // Every other tab: its lists, the screens this PC opened, recent records in full.
      const warmed = new Set([...WARM_PATHS, ...MODULE_WARM_PATHS]);
      const learned = Object.entries(s.cache)
        .filter(([k, v]) => v.used && Date.now() - v.used < LEARNED_KEEP_MS && !warmed.has(k) && learnable(k))
        .sort((a, b) => b[1].used - a[1].used)
        .slice(0, LEARNED_WARM_MAX)
        .map(([k]) => k);
      const runSteps = async (step, paths, maxAgeMs) => {
        st.catalogProgress = { step, done: 0, total: paths.length };
        emit();
        for (let i = 0; i < paths.length && reachable; i += 1) {
          await fetchScreen(paths[i], maxAgeMs);
          if (i % 10 === 9) { st.catalogProgress = { step, done: i + 1, total: paths.length }; emit(); }
        }
      };
      await runSteps('screens', MODULE_WARM_PATHS, MODULE_WARM_MS);
      await runSteps('screens', learned, LEARNED_WARM_MS);
      // Records in full, now that their lists are in.
      const details = [];
      for (const list of DETAIL_WARM_LISTS) {
        const found = findRows(s.cache[list]?.body);
        const base = splitPath(list)[0];
        (found ? found.holder[found.key] : [])
          .filter(r => Number(r?.id) > 0).slice(0, DETAIL_WARM_MAX)
          .forEach(r => details.push(`${base}/${r.id}`));
      }
      await runSteps('records', details, DETAIL_WARM_MS);
      if (reachable) refreshed.forEach(c => st.refreshCollections.delete(c));
      flushCache();
      logEvent('downloaded', `Offline data refreshed — ${st.productChanges || 0} product${st.productChanges === 1 ? '' : 's'} updated, ${customers.length} customers, ${Object.keys(s.cache).length} screens`, { complete: reachable });
      st.lastError = null;
    } catch (err) {
      st.lastError = `Offline data refresh failed: ${err.message}`;
      logEvent('failed', st.lastError);
      console.error('[offline]', st.lastError);
    } finally {
      st.syncingCatalog = false;
      st.catalogProgress = null;
      emit();
    }
  }

  // How long a screen waits before falling back to this PC's copy. A dead or
  // half-dead connection must never leave a spinner up for minutes.
  const LONG_GETS = /export|report|backup|download|statement|pdf/i;

  // Screens worth refreshing in the background once the user has opened them:
  // not files, not one-off codes, not ids made on this PC, and not the
  // product grid / customer list (those come from the full local copies).
  function learnable(p) {
    const pathname = splitPath(p)[0];
    if (/export|backup|download|pdf|generate-code|next-ref/i.test(pathname) || /\/-\d+(\/|$)/.test(pathname)) return false;
    if (/^\/(online\/bootstrap|online\/products|customers)$/.test(pathname) || pathname.startsWith('/online/products/sku/')) return false;
    // POS screens changed by offline sales / returns are refreshed by their own rules above.
    if (/^\/(sales\/\d+|product-rentals|gift-cards|coupons|cash-drawer|eod)(\/|$)/.test(pathname)) return false;
    return !USER_PATHS.includes(p);
  }
  function getTimeout(pathname) {
    if (!st.online) return 4000; // already offline: just a quick "is it back?" try
    if (/^\/(online\/bootstrap|online\/products(\/sku\/.*)?|customers)$/.test(pathname)) return 15000;
    return LONG_GETS.test(pathname) ? 120000 : 30000;
  }

  // ── Entry point for every renderer API call ────────────────────────────
  // Counted while in flight so background work waits (bgNet).
  async function handle(method, p, body) {
    st.userInFlight += 1;
    try {
      return await handleRequest(method, p, body);
    } finally {
      st.userInFlight -= 1;
    }
  }

  async function handleRequest(method, p, body) {
    const m = String(method || 'GET').toUpperCase();
    const [pathname] = splitPath(p);

    if (m === 'POST' && pathname === '/online/checkout') return checkout(body || {});
    if (m === 'POST' && (pathname === '/auth/token' || pathname === '/cashier/login')) return login(pathname, body || {});
    if (m === 'POST' && pathname === '/verify-password') return verifyPassword(body || {});

    const s = store();
    if (s && (m === 'PATCH' || m === 'PUT') && pathname === '/online/settings') {
      const { res, queued } = await writeOrQueue(m, p, body, () => queueSettings(m, body || {}, s));
      if (!queued && res.status === 200) remember('/online/settings', res.body);
      return res;
    }
    if (s && m === 'POST' && pathname === '/customers') {
      const { res, queued } = await writeOrQueue(m, p, body, () => queueCustomerCreate(body, s));
      if (!queued && (res.status === 200 || res.status === 201) && res.body?.data?.id) {
        s.customers.items.push(res.body.data);
        save('customers');
      }
      return res;
    }
    if (s && m === 'POST' && pathname === '/online/products') {
      const { res, queued } = await writeOrQueue(m, p, body, () => queueProductCreate(body || {}, s));
      if (!queued && res.status < 300) catalogChanged();
      return res;
    }
    if (s && m === 'POST' && pathname === '/customers/import/preview') {
      return (await writeOrQueue(m, p, body, () => customerImportResponse(customerImportPlan(s, body?.rows, body?.duplicates || 'skip'), true))).res;
    }
    if (s && m === 'POST' && pathname === '/customers/import') {
      return (await writeOrQueue(m, p, body, () => queueCustomerImport(body || {}, s))).res;
    }
    const customerEdit = pathname.match(/^\/customers\/(-?\d+)$/);
    if (s && m === 'PATCH' && customerEdit) {
      let id = Number(customerEdit[1]);
      if (id < 0) {
        // Created offline: edit the queued copy, or the real one once uploaded.
        if (!s.customers.id_map?.[id]) return queueCustomerUpdate(id, body, s);
        id = s.customers.id_map[id];
      }
      const { res, queued } = await writeOrQueue(m, `/customers/${id}`, body, () => queueCustomerUpdate(id, body, s));
      if (!queued && res.status === 200 && res.body?.data) {
        const i = s.customers.items.findIndex(c => c.id === id);
        if (i >= 0) { s.customers.items[i] = { ...s.customers.items[i], ...res.body.data }; save('customers'); }
      }
      return res;
    }

    const action = s && matchAction(m, pathname);
    if (action) return performAction(action, m, p, body || {}, s);

    if (m === 'GET') {
      // Customers created offline only exist on this PC until they upload.
      if (/^\/customers\/-\d+/.test(pathname)) return localGet(p) || { status: 404, offline: true, body: { message: 'This customer was added offline and has not uploaded yet.' } };
      // Sales made offline: answered here until uploaded, then by their server id.
      const localSaleId = pathname.match(/^\/sales\/(-\d+)$/);
      if (s && localSaleId) {
        const e = s.outbox.find(x => x.local_id === Number(localSaleId[1]));
        if (!(e?.status === 'synced' && e.server_id && st.online)) return localGet(p);
        p = `/sales/${e.server_id}`;
      }
      // Any other record added offline: this PC's copy until it uploads, then its real id.
      if (s && TEMP_ID_IN_PATH.test(p)) {
        const map = tempIdMap(s.outbox, s.customers.id_map, s.meta);
        const real = p.replace(new RegExp(TEMP_ID_IN_PATH.source, 'g'), seg => (map[seg.slice(1)] != null ? `/${map[seg.slice(1)]}` : seg));
        if (!st.online || TEMP_ID_IN_PATH.test(real)) {
          return localGet(p) || { status: 404, offline: true, body: { message: 'This was added offline and has not uploaded yet.' } };
        }
        p = real;
      }
      // Product grid / search / barcode: answered from this PC's catalog even
      // while online — instant, instead of a server round trip per keystroke.
      if (s && st.online && localFirst(s, pathname, splitPath(p)[1])) {
        const local = localGet(p);
        if (local && local.status === 200) return { ...local, offline: false, fromLocalCatalog: true };
      }
      // Known offline: answer from disk at once instead of waiting on a dead connection.
      if (!st.online) {
        const local = localGet(p);
        if (local) return local;
      }
      const res = await net(m, p, body, { timeout: getTimeout(pathname) });
      if (isUnreachable(res.status)) {
        setOnline(false);
        return localGet(p) || offlineResponse(res.body);
      }
      setOnline(true);
      if (res.status === 200 && !/generate-code$/.test(pathname)) remember(p, res.body, true);
      return s && res.status === 200 ? withOfflineSales(s, p, res) : res;
    }

    if (s && !ONLINE_ONLY_WRITES.test(pathname)) return genericWrite(m, p, body || {}, s);

    const res = await net(m, p, body);
    if (isUnreachable(res.status)) {
      setOnline(false);
      return offlineResponse(res.body);
    }
    setOnline(true);
    if (res.status < 300 && CATALOG_WRITES.test(pathname)) catalogChanged();
    return res;
  }

  // Writes that change product cards (price, stock, categories, discounts…).
  const CATALOG_WRITES = /^\/(online\/products|online\/barcodes|grns|purchase-orders|stock-transfers|stock-audits|categories|brands|discounts|sale-campaigns|units)(\/|$)/;
  // Right after this PC changes products, the grid reads from the server for
  // a moment (so the change shows at once) while the local copy catches up.
  const SERVER_FIRST_MS = 20000;
  function catalogChanged() {
    st.serverFirstUntil = Date.now() + SERVER_FIRST_MS;
    syncProductsSoon();
  }

  function localFirst(s, pathname, params) {
    if (Date.now() < (st.serverFirstUntil || 0)) return false;
    if (!s.catalog.synced_at || !s.meta.bootstrap || !Object.keys(s.catalog.products).length) return false;
    if (pathname === '/online/bootstrap') {
      // "Recent" ordering and brand filters need the server's sales/brand data.
      return !params.get('brand_id') && params.get('recent_sales') !== '1' && params.get('sort') !== 'recent_sales';
    }
    return pathname === '/online/products' || pathname.startsWith('/online/products/sku/');
  }

  function start() {
    st.catalogTimer = setInterval(() => {
      if (st.online) { syncOutbox().then(() => syncCatalog()); }
    }, CATALOG_REFRESH_MS);
    // Products (price / stock) refresh more often, so the instant local search stays current.
    st.productTimer = setInterval(() => { if (st.online) syncProductsQuick(); }, PRODUCT_REFRESH_MS);
    // First refresh once the app has finished opening (it waits for the user anyway).
    setTimeout(() => { syncOutbox().then(() => syncCatalog()); }, 20000);
  }

  function stop() {
    stopProbe();
    if (st.catalogTimer) clearInterval(st.catalogTimer);
    if (st.productTimer) clearInterval(st.productTimer);
    if (st.outboxTimer) clearTimeout(st.outboxTimer);
    flushCache();
    if (gst.timer) {
      clearTimeout(gst.timer);
      gst.timer = null;
      try {
        fs.mkdirSync(path.dirname(globalCacheFile()), { recursive: true });
        writeJsonAtomic(globalCacheFile(), globalCache());
      } catch (_) {}
    }
  }

  return {
    handle,
    start,
    stop,
    status,
    listOutbox,
    listEvents,
    retryEntry,
    discardEntry,
    syncNow: async ({ full = false } = {}) => {
      // A manual "Sync now" also re-checks connectivity right away.
      if (!st.online) {
        const res = await net('GET', '/auth/me', null, { timeout: 8000 });
        if (isUnreachable(res.status)) return status();
        st.online = true;
        logEvent('online', 'Internet is back');
        stopProbe();
      }
      await syncOutbox();
      await syncCatalog({ full });
      return status();
    },
  };
}

module.exports = { createOffline, MAX_OFFLINE_DAYS };
