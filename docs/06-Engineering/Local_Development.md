# Keryon Local Development

## 1. Supported Current Environment

Keryon's current, verified local development environment is **macOS with a Homebrew-managed toolchain**.

Laravel Herd is not part of this environment. Do not repair Herd, configure Keryon inside Herd, restore `keryon.test`, or use Herd's PHP/Node binaries. See §11 for the historical Windows/Herd note.

## 2. macOS Toolchain

| Component | Source |
|---|---|
| PHP | Homebrew |
| Composer | Homebrew |
| Node | Homebrew |
| npm | Homebrew |
| Database | MySQL (local install) |
| Frontend | Vite |

Required versions are defined by the repository's own dependency files (`composer.json`, `package.json`), not by this document. As a point-in-time example only, this environment has been verified working with PHP 8.5.8, Composer 2.10.2, Node v22.23.2, and npm 10.9.8 — treat these as evidence the toolchain works, not as pinned requirements.

Before running development commands, verify tool resolution in a fresh shell:

```bash
which php
which composer
which node
which npm
```

Active Keryon macOS tooling should resolve through the Homebrew environment, not through Laravel Herd. The important principle is correct Homebrew resolution — the specific path (e.g. `/usr/local/bin`) is not itself a requirement and may differ across Mac architectures.

## 3. Database

Local development uses MySQL. Do not replace it with SQLite.

`.env` is local-only and must never be committed. Use a dedicated local development account rather than `root` where practical.

Placeholder shape (no real credentials belong in this document):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=keryon
DB_USERNAME=<local development user>
DB_PASSWORD=<local secret>
```

## 4. Initial Setup

For a genuinely new local environment:

1. Create the `keryon` database.
2. Create or reuse a dedicated Keryon development user.
3. Grant that user access to `keryon`.
4. Configure local `.env` with that user's credentials.
5. Inspect the existing migration set (`database/migrations/`) before running anything.
6. Run migrations once the migration set is understood.

## 5. Starting Keryon

Terminal 1:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```bash
npm run dev
```

Port 8000 is not reserved for Keryon. If another local application is already using it, choose another available port and update `APP_URL` to match.

## 6. Local URLs

Current verified local application URL:

```
http://127.0.0.1:8000
```

`.env`'s `APP_URL` should match the actual local server URL. Do not assume `keryon.test` is required or available on macOS.

## 7. Verification Commands

```bash
php artisan --version
composer check-platform-reqs
php artisan migrate:status
php artisan route:list
php artisan test
npm run build
```

`php artisan migrate` is a deliberate, schema-changing action and does not belong in routine verification — see CLAUDE.md's "Claude must ask before... Running migrations."

## 8. Tests

```bash
php artisan test
```

## 9. Frontend Build

```bash
npm run build
```

## 10. Environment Safety

- `.env` is git-ignored, local-only, and must never be committed.
- User-facing changes require browser verification in addition to automated tests where relevant — see `docs/06-Engineering/Responsive_and_Accessibility_Standard.md` for the full responsive/accessibility verification standard.

## 11. Windows Historical Note

Earlier Keryon development on Windows used Laravel Herd and PowerShell.

Those instructions are platform-specific and must not be assumed to apply to the current macOS/Homebrew environment.
