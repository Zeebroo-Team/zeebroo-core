'use strict';

/**
 * Pre-build script: replaces the API_BASE_URL constant in config.js
 * with the value of the API_BASE_URL environment variable.
 *
 * Run automatically via the "prebuild" npm script before electron-builder.
 * If API_BASE_URL is not set the script exits silently — localhost stays.
 *
 * To change the URL for development: edit electron_app/config.js directly.
 * For CI/CD builds: set the API_BASE_URL environment variable.
 */

const fs   = require('fs');
const path = require('path');

const url = process.env.API_BASE_URL;

if (!url) {
  console.log('  setApiUrl: API_BASE_URL not set — keeping default (localhost)');
  process.exit(0);
}

const configPath = path.resolve(__dirname, '..', 'config.js');
const original   = fs.readFileSync(configPath, 'utf8');

const DEFAULT = 'http://localhost:8000/api/v1/pos';
const updated = original.replace(DEFAULT, url);

if (updated === original) {
  if (original.includes(url)) {
    console.log(`  setApiUrl: already set to ${url}`);
    process.exit(0);
  }
  console.error(`  setApiUrl: ERROR — default URL "${DEFAULT}" not found in config.js`);
  process.exit(1);
}

fs.writeFileSync(configPath, updated, 'utf8');
console.log(`  setApiUrl: API_BASE_URL → ${url}`);
