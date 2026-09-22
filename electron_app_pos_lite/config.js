'use strict';

// ── API Base URL ─────────────────────────────────────────────────────────
// Points the lite client at the same Laravel POS API used by the full
// desktop app (Modules/Pos/routes/api.php, prefix v1/pos). Change this one
// line to target a different backend (e.g. a production URL).
const API_BASE_URL = 'http://localhost:8000/api/v1/pos';

module.exports = { API_BASE_URL };
