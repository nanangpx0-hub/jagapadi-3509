# GitHub Copilot Instructions for JAGAPADI (3509)

You are assisting development on **JAGAPADI**, the agricultural monitoring and reporting system for Jember Regency.

## Core Technical Architecture
- **Language**: PHP 8.2 (native, strict types, no heavy framework)
- **Database**: MariaDB / MySQL with PDO, UTF-8 (`utf8mb4_unicode_ci`)
- **Web Interface**: Server-rendered HTML with Tailwind / Bootstrap CSS, native session, CSRF protection
- **Mobile Client**: Flutter Android communicating via REST API + JWT Bearer tokens

## Dual-Runtime Warning
This codebase has two distinct runtimes:
1. **Root Runtime (`/index.php`)**:
   - Web application and administrative dashboard.
   - Routing defined in `config/web_routes.php` + fallback dispatch in `index.php`.
   - **CRITICAL**: The route count in `config/web_routes.php` is strictly frozen at **136 routes** (enforced by `tests/Compatibility/RootCompatibilityTest.php`). Do NOT add routes to this file. New controller actions are automatically handled by the `index.php` fallback (`/controllerName/actionName`).
2. **Backend v1 Runtime (`backend/public/index.php`)**:
   - Canonical REST API target at `/api/v1`.
   - Routing defined in `backend/config/routes.php`.

## Coding Standards & Conventions
- Always include `declare(strict_types=1);` at the top of PHP files.
- Follow PSR-12 coding standard: 4 spaces indent, LF line endings, strict type hinting for parameters and returns.
- **SQL Security**: ALWAYS use PDO prepared statements with parameter binding. NEVER concatenate raw variables into SQL queries.
- **XSS Prevention**: Always escape dynamic output in HTML templates using `htmlspecialchars((string)$var, ENT_QUOTES, 'UTF-8')` or the `e()` helper.
- **CSRF**: Any POST/PUT/DELETE web action must validate CSRF tokens (`$this->validateCsrfToken()`).

## Business Logic & Report Workflow
The official report status lifecycle is:
`Draf` -> `Submitted` -> `Diverifikasi`
              |-> `Ditolak` -> `Draf`
                          |-> `Submitted` (owner resubmit)

- DB status values: `'Draf'`, `'Submitted'`, `'Diverifikasi'`, `'Ditolak'` (status `'Diarsipkan'` has been removed).
- UI label for `'Submitted'` can be displayed as `'Dikirim'`, but DB/API query values MUST remain `'Submitted'`.
- Default aggregate queries (charts, maps, statistics, exports) MUST exclude `'Draf'`.
- Verification can only be done by role `admin` on reports in `'Submitted'` status. Reports are finalized upon reaching `'Diverifikasi'`.
- Officers (`petugas`) can only view and manage their own reports (Ownership check via session `$_SESSION['user_id']`).
