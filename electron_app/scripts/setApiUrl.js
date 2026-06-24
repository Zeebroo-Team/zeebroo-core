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

// Match the default value only — avoids touching anything else in the file
const updated = original.replace(
  /(api_base_url:\s*['"])http:\/\/localhost[^'"]*(['"]) /,
  `$1${url}$2 `
);

if (updated === original) {
  // Already replaced or pattern changed — check if the desired URL is already there
  if (original.includes(url)) {
    console.log(`  setApiUrl: already set to ${url}`);
    process.exit(0);
  }
  console.error('  setApiUrl: ERROR — could not find api_base_url pattern in main.js');
  process.exit(1);
}

fs.writeFileSync(mainPath, updated, 'utf8');
console.log(`  setApiUrl: api_base_url → ${url}`);
