import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const webDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const publicDir = join(webDir, 'public');
const router = join(webDir, 'scripts', 'router.php');
const port = process.env.BACKEND_PORT || process.env.PORT || '8081';

for (const p of [join(publicDir, 'index.php'), router, join(webDir, 'vendor', 'autoload.php')]) {
  if (!existsSync(p)) {
    console.error(`Missing ${p}. Run "composer install" inside the web folder first.`);
    process.exit(1);
  }
}

console.log(`Laravel listening on http://0.0.0.0:${port}  (docroot: ${publicDir})`);

const child = spawn('php', ['-S', `0.0.0.0:${port}`, '-t', publicDir, router], {
  cwd: webDir,
  stdio: 'inherit',
  env: { ...process.env, PHP_CLI_SERVER_WORKERS: '4' },
});

child.on('exit', (code) => process.exit(code ?? 0));
for (const sig of ['SIGINT', 'SIGTERM']) process.on(sig, () => child.kill(sig));