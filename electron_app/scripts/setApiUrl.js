'use strict';

/**
 * Pre-build script: replaces the default localhost api_base_url in main.js
 * with the value of the API_BASE_URL environment variable.
 *
 * Run automatically via the "prebuild" npm script before electron-builder.
 * If API_BASE_URL is not set the script exits silently — localhost stays.
 */

const fs   = require('fs');
const path = require('path');

const url = process.env.API_BASE_URL;

if (!url) {
  console.log('  setApiUrl: API_BASE_URL not set — keeping default (localhost)');
  process.exit(0);
}

const mainPath = path.resolve(__dirname, '..', 'main.js');
const original = fs.readFileSync(mainPath, 'utf8');

// Simple string replace — the localhost URL is unique in main.js
const DEFAULT = 'http://localhost:8000/api/v1/pos';
const updated = original.replace(DEFAULT, url);

if (updated === original) {
  if (original.includes(url)) {
    console.log(`  setApiUrl: already set to ${url}`);
    process.exit(0);
  }
  console.error(`  setApiUrl: ERROR — default URL "${DEFAULT}" not found in main.js`);
  process.exit(1);
}

fs.writeFileSync(mainPath, updated, 'utf8');
console.log(`  setApiUrl: api_base_url → ${url}`);
