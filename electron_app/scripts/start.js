// Cross-platform replacement for `env -u ELECTRON_RUN_AS_NODE electron .`
// (the `env` command isn't available on native Windows shells).
const { spawn } = require('child_process');
const path = require('path');
const electronPath = require('electron');

const env = { ...process.env };
delete env.ELECTRON_RUN_AS_NODE;

const child = spawn(electronPath, [path.join(__dirname, '..')], {
  env,
  stdio: 'inherit',
});

child.on('close', (code) => process.exit(code ?? 0));
