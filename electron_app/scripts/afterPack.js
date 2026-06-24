'use strict';

const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

/**
 * Ad-hoc sign the .app when no Apple Developer certificate is configured.
 *
 * WHY this exists:
 *   macOS dyld enforces that all code loaded into a process shares the same
 *   Team ID.  Electron bundles its own Framework (signed with GitHub's Team ID).
 *   If we sign only the outer .app with ad-hoc (team ID = ""), dyld sees a
 *   mismatch between the main executable ("") and Electron Framework
 *   (GitHub's team) and crashes with:
 *     "different Team IDs"
 *
 * WHY we don't use `codesign --deep`:
 *   --deep does not reliably re-sign nested frameworks that already carry a
 *   hardened-runtime signature from a different team.  We sign every component
 *   individually, from deepest to shallowest, so each item is signed before
 *   its parent bundle seals it.
 *
 * WHY no `--options runtime`:
 *   Hardened runtime requires ALL components to share a real Team ID.
 *   Ad-hoc signing ("") has no Team ID, so adding --options runtime would
 *   re-introduce the team-ID mismatch we are trying to fix.
 */
exports.default = async function afterPack(context) {
  if (context.electronPlatformName !== 'darwin') return;

  const hasCert = process.env.CSC_LINK || process.env.MAC_CERTIFICATE;
  if (hasCert) return;

  const appName = context.packager.appInfo.productFilename;
  const appPath = path.join(context.appOutDir, `${appName}.app`);

  console.log(`\n  • ad-hoc signing (no cert) — ${appPath}`);

  const sign = (target) => {
    if (!fs.existsSync(target)) return;
    try {
      execSync(`codesign --force --sign - "${target}"`, { stdio: 'pipe' });
    } catch (err) {
      // Some items may fail (e.g. symlinks); keep going.
      process.stdout.write(`    skip: ${path.basename(target)}\n`);
    }
  };

  const find = (args) =>
    execSync(`find "${appPath}" ${args} 2>/dev/null`, { encoding: 'utf8' })
      .trim()
      .split('\n')
      .filter(Boolean)
      .filter(fs.existsSync);

  // ── Step 1: dylibs and .so files (deepest leaves) ──────────────────────
  find('-name "*.dylib"').forEach(sign);
  find('-name "*.so"').forEach(sign);

  // ── Step 2: Electron Framework — sign internals before the bundle ───────
  //    Electron Framework ships its own helpers, chrome-sandbox, etc.
  const efRoot = path.join(
    appPath, 'Contents', 'Frameworks', 'Electron Framework.framework'
  );
  if (fs.existsSync(efRoot)) {
    // Individual binaries inside the framework
    const efBinaries = execSync(
      `find "${efRoot}" -type f \\( -name "chrome-sandbox" -o -name "Electron Framework" \\) 2>/dev/null`,
      { encoding: 'utf8' }
    ).trim().split('\n').filter(Boolean);
    efBinaries.forEach(sign);

    // Any helper .app bundles inside the framework
    execSync(`find "${efRoot}" -name "*.app" 2>/dev/null`, { encoding: 'utf8' })
      .trim().split('\n').filter(Boolean).forEach(sign);

    // The framework bundle itself
    sign(efRoot);
  }

  // ── Step 3: all other .framework bundles (sorted deepest first) ─────────
  find('-name "*.framework"')
    .filter(f => f !== efRoot)
    .sort((a, b) => b.split('/').length - a.split('/').length)
    .forEach(sign);

  // ── Step 4: remaining helper .app bundles ───────────────────────────────
  find(`-name "*.app" -not -path "${appPath}"`)
    .sort((a, b) => b.split('/').length - a.split('/').length)
    .forEach(sign);

  // ── Step 5: the top-level .app — must be last ───────────────────────────
  sign(appPath);

  console.log(`  • ad-hoc signing complete\n`);
};
