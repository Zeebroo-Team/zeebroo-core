'use strict';

const { execSync } = require('child_process');
const path = require('path');

/**
 * Ad-hoc sign the .app when no Apple Developer certificate is configured.
 *
 * Without this, macOS Gatekeeper shows "damaged and can't be opened" for
 * unsigned arm64/Sonoma builds.  Ad-hoc signing converts that hard block
 * into the softer "unidentified developer" warning, which users can bypass
 * with right-click → Open (or System Settings → Privacy & Security → Open Anyway).
 *
 * When a real certificate IS present (CSC_LINK or MAC_CERTIFICATE set),
 * electron-builder handles signing itself and this hook exits immediately.
 */
exports.default = async function afterPack(context) {
  if (context.electronPlatformName !== 'darwin') return;

  const hasCert = process.env.CSC_LINK || process.env.MAC_CERTIFICATE;
  if (hasCert) return;

  const appName = context.packager.appInfo.productFilename;
  const appPath = path.join(context.appOutDir, `${appName}.app`);

  console.log(`  • ad-hoc signing (no certificate configured)`);
  console.log(`    path=${appPath}`);

  execSync(
    `codesign --deep --force --sign - --options runtime "${appPath}"`,
    { stdio: 'inherit' }
  );
};
