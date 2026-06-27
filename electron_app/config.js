'use strict';

// ── API Base URL ─────────────────────────────────────────────────────────────
// Change THIS one line to point the app at a different backend.
// The CI/CD pipeline replaces it automatically for production builds via
// the API_BASE_URL environment variable (see scripts/setApiUrl.js).
const API_BASE_URL = 'http://localhost:8000/api/v1/pos';

module.exports = { API_BASE_URL };
