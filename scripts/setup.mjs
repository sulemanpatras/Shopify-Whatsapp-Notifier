#!/usr/bin/env node
// Turns web/ into a complete Laravel app: creates a fresh Laravel skeleton and copies it
// underneath the app-specific files in web/ (existing files are never overwritten).
import { execSync, spawnSync } from 'node:child_process';
import { cpSync, existsSync, mkdtempSync, copyFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const web = join(root, 'web');
const has = (cmd) => spawnSync(cmd, ['--version'], { stdio: 'ignore', shell: true }).status === 0;

for (const cmd of ['php', 'composer']) {
  if (!has(cmd)) {
    console.error(`✖ "${cmd}" was not found on your PATH. Install PHP 8.2+ and Composer first (see README).`);
    process.exit(1);
  }
}

if (!existsSync(join(web, 'artisan'))) {
  console.log('▸ Creating Laravel skeleton…');
  const tmp = mkdtempSync(join(tmpdir(), 'laravel-'));
  execSync(`composer create-project laravel/laravel "${join(tmp, 'app')}" --prefer-dist --no-interaction`, { stdio: 'inherit' });
  cpSync(join(tmp, 'app'), web, { recursive: true, force: false, errorOnExist: false });
  rmSync(tmp, { recursive: true, force: true });
}

const run = (cmd) => execSync(cmd, { cwd: web, stdio: 'inherit' });

if (!existsSync(join(web, '.env'))) copyFileSync(join(web, '.env.example'), join(web, '.env'));
console.log('▸ Installing PHP dependencies…');
run('composer install --no-interaction');
run('php artisan key:generate --force');
if (!existsSync(join(web, 'database', 'database.sqlite'))) {
  execSync('php -r "touch(\'database/database.sqlite\');"', { cwd: web });
}
console.log('▸ Running migrations…');
run('php artisan migrate --force');
console.log('\n✔ Laravel app is ready. Next: npm install, npx shopify app config link, npm run dev');
