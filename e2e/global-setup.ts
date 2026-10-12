import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

/*
 * One clean, seeded database before the suite starts (docs/E2E.md). The fixture
 * lives in a dedicated, destructive `gilatennis_e2e` MySQL database and is
 * reseeded here, so the suite can assume a known starting state. Assets are
 * built once so `php artisan serve` (the webServer) has a manifest to serve.
 */

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

export default function globalSetup(): void {
    ensureEnvFile();

    const host = process.env.E2E_DB_HOST ?? '127.0.0.1';
    const port = process.env.E2E_DB_PORT ?? '3306';
    const user = process.env.E2E_DB_USER ?? 'root';
    const database = process.env.E2E_DB_DATABASE ?? 'gilatennis_e2e';

    execFileSync(
        'mysql',
        [
            '-h',
            host,
            '-P',
            port,
            '-u',
            user,
            '-e',
            `CREATE DATABASE IF NOT EXISTS \`${database}\``,
        ],
        { stdio: 'inherit' },
    );

    run('php', ['artisan', 'migrate:fresh', '--env=e2e', '--force']);
    run('php', [
        'artisan',
        'db:seed',
        '--class=E2ESeeder',
        '--env=e2e',
        '--force',
    ]);
    run('npm', ['run', 'build']);
}

function run(command: string, args: string[]): void {
    execFileSync(command, args, { stdio: 'inherit', cwd: root });
}

function ensureEnvFile(): void {
    const envFile = resolve(root, '.env.e2e');
    const example = resolve(root, '.env.e2e.example');

    if (!existsSync(envFile)) {
        copyFileSync(example, envFile);
    }
}
