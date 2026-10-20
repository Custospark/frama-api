# Frama Investments - Deployment Runbook & Safety Guardrails

**OWNERSHIP:** Every agent that touches production (`frama.custospark.com`,
`frama-api.custospark.com`) **must read this file in full** before writing a
deployment plan or executing any deploy.

**Golden rule: there is no room for error in production.** If anything here is
unclear, stop and ask Oscar. Never guess, never "just try it" on a live
environment.

This account is shared. Custosell, Custocare, Custospark Academy, Custograde and
others run on the same host. A command that looks harmless to Frama can break
them.

> **NEXT AGENT: the first deploy is DONE (2026-10-20). You almost certainly want
> section 6 ("Second & later deploys"). Do NOT re-run section 10 (first-time
> setup) - especially never `key:generate` or `db:seed` again.**

---

## 1. Current state (after the first deploy - 2026-10-20)

| Fact | Value |
|---|---|
| API clone | `/home/u214605677/domains/frama-api.custospark.com` (branch `main`) |
| Web docroot | `/home/u214605677/domains/custospark.com/public_html/frama` (subfolder of the SHARED docroot) |
| API docroot | symlink `/home/u214605677/domains/custospark.com/public_html/frama-api` -> `~/domains/frama-api.custospark.com/public` (created with `ln -s`) |
| Storage link | `public/storage` -> `storage/app/public` (created with `ln -s`, `exec()` is disabled so the artisan command fails) |
| Server `.env` | `<clone>/.env`, mode 600, uploaded from local `Backend/.env.server`; **APP_KEY generated ONCE on 2026-10-20 - never generate again** |
| Database | `u214605677_Frama_2026` (name == username), migrated + seeded 2026-10-20: 4 users, 2 branches, 75 custom fields, 4 business types |
| `.htaccess` | proven Academy/Custograde copy with bounce -> `frama.custospark.com`; source of truth = `Frontend/deploy/htaccess.production` (the build ships it, so every `deploy:web:production` re-carries it) |
| First-deploy commits | backend `e9b8c59`..`3eba069`, frontend `591d1e3`..`0da8059` |
| Backups (on server) | web placeholder: `~/domains/frama-backup-20261020-144402` · old `.htaccess`: `~/domains/frama-htaccess-backup-20261020-174620` |
| Cron | NONE yet - `routes/console.php` schedules nothing. Add one hPanel cron (`schedule:run`, every minute) only when schedules exist |
| Seeded logins (change when real users onboard) | admin `FRAMA`/`9001` · executive `FRAMA`/`1234` · managers `RUBAGA`/`1234`, `KIBUYA`/`1234` |

First-deploy verification (all passed): web root/deep/login 200, JS served as
`application/x-javascript`, 0 missing assets, `/api/v1/health` 200 JSON,
CORS preflight 204 `access-control-allow-origin: *`, admin login smoke 200,
main-domain bounce `https://custospark.com/frama/login` -> 301
`https://frama.custospark.com/login`, 0 error logs, PHP limits 2048M,
siblings untouched.

---

## 2. Environments & topology

Frama has **no staging environment**. Production is the only environment;
keep a backup before every deploy.

| Piece | Host path | URL |
|---|---|---|
| Web (SPA) | `/home/u214605677/domains/custospark.com/public_html/frama` | `https://frama.custospark.com` |
| API (Laravel) | `/home/u214605677/domains/frama-api.custospark.com` (served via the `public_html/frama-api` symlink) | `https://frama-api.custospark.com/api/v1` |

> Frama's subdomains are **subfolders of the shared `custospark.com` docroot**
> (Academy pattern), NOT `domains/<subdomain>/public_html` (Custograde
> pattern). Everything under `public_html/` that is not `frama*` belongs to
> another product.

**Hosting** (Hostinger shared hosting, account `u214605677` - same account as
Academy and Custosell):

- SSH: `147.79.103.136`, port `65002`, user `u214605677`.
- PHP: `/usr/bin/php` (8.2.34 CLI; limits 2048M). Cron path fallback for
  siblings: `/opt/alt/php82/usr/bin/php`.
- Composer: `/usr/local/bin/composer`.
- Python: no `python3` on PATH on the server; on the dev machine `python` runs
  the helpers (paramiko).
- No root, no `sudo`, no package manager. New databases only via hPanel.
- Credentials live in `Backend/.env` under `SSH_DEPLOY_*` (same values Academy/
  Custosell use), read by `Backend/scripts/ssh_run.py`. Never typed, never
  printed.
- Server-side secrets are assembled locally in `Backend/.env.server`
  (gitignored) and uploaded to the server `.env` by `Backend/scripts/push_env.py`.

**Repos** (public, so the server pulls plain HTTPS with no deploy key):
- `github.com/Custospark/frama-api` - Laravel backend + shipped web build
  (`public/production`), branch `main`.
- `github.com/Custospark/frama-frontend` - React SPA source, branch `main`.

---

## 3. Non-negotiables (violating ANY of these is a P0 incident)

1. **NEVER delete anything on the server.** Backups are additive: `cp -rT` the
   old state to a timestamped name, then copy the new one over the top.
2. **NEVER run `migrate:fresh`, `migrate:refresh`, `db:wipe`, or drop a
   database.** Migrations are additive and forward-only.
3. **NEVER run `key:generate` again.** It was done once (2026-10-20, empty
   DB). Re-running invalidates every token and session.
4. **NEVER run `db:seed` on production again** (it would re-`firstOrCreate`
   over live edits). Seeding happened once (2026-10-20).
5. **NEVER edit or delete an existing migration.** Add a new forward migration.
6. **NEVER `git add -A` on the backend repo** during a deploy - stage only
   exact paths. `npm run deploy:web:production` stages only `public/production`.
7. **NEVER run `php artisan migrate` without `--force` on the server.**
8. **NEVER print secrets.** Read them from env files via scripts; mask output.
9. **NEVER commit `.env`, `.env.server`, `.env.development` or `*.local`.**
10. **NEVER touch another product's paths** - everything under
    `/home/u214605677/domains/` and the shared `public_html/` that is not
    `frama*`.
11. **Never `rm -rf` the web docroot and never copy with globs** (`cp -r dir/*`
    silently drops `.htaccess` and kills the SPA rewrite). Always
    `cp -rT SRC DST`.
12. **NEVER deploy from a dirty tree.** `git status --short` clean before and
    after, locally and on the server.

---

## 4. Mandatory deployment plan (the approval gate)

Every deploy requires a written plan presented to Oscar and explicitly approved
**before any server command runs**: scope (exact commits), files touched,
migrations, backup location, rollback commands, verification. Never "deploy and
see". Template in section 11.

---

## 5. Local gates (before ANY push)

```bash
cd backend  && php artisan test          # 75 tests (170 assertions) must pass
cd frontend && npm run typecheck && npm run lint && npm run test:unit   # 46 tests
```

Push both repos to GitHub **before** deploying - the server only mirrors
`origin/main`.

---

## 6. SECOND & LATER DEPLOYS (the canonical path)

> Pick what changed. Run section 6.0, then 6.1 (backend) and/or 6.2 (web).
> Finish with section 6.3 + section 7 verification.

### 6.0 Pre-flight
```bash
# local
cd backend   && git status --short   # clean
cd frontend  && git status --short   # clean
```
Present the section 11 plan; wait for Oscar's approval.

### 6.1 Backend changed (code / migrations / composer.lock)
```bash
# LOCAL: gates (section 5), then commit exact paths and push
cd backend
git add <exact paths> && git commit -m "<message>" && git push origin main
```
```bash
# SERVER: mirror
cd backend
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  git fetch origin && git reset --hard origin/main
  git log --oneline -1
  git status --short | grep -v '^??'        # must be empty
"
# ONLY if composer.json/composer.lock changed:
python scripts/ssh_run.py "cd /home/u214605677/domains/frama-api.custospark.com && /usr/bin/php -d memory_limit=512M /usr/local/bin/composer install --no-dev --prefer-dist --no-interaction"
# ONLY if database/migrations changed - dump FIRST, verify non-zero, then migrate:
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  tr -d '\r' < .env > /tmp/fe.sh && set -a && . /tmp/fe.sh && set +a && rm -f /tmp/fe.sh
  TS=\$(date +%Y%m%d-%H%M%S); BK=/home/u214605677/domains/backups/frama-db-\$TS.sql
  mkdir -p /home/u214605677/domains/backups
  /usr/bin/mysqldump -h \"\${DB_HOST}\" -P \"\${DB_PORT}\" -u \"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" \"\$DB_DATABASE\" > \"\$BK\" 2>/dev/null
  ls -la \"\$BK\"        # MUST be non-zero, else STOP
  /usr/bin/php artisan migrate --force
"
# ALWAYS (every backend deploy):
python scripts/ssh_run.py "cd /home/u214605677/domains/frama-api.custospark.com && /usr/bin/php artisan optimize:clear"
```
> CRLF gotcha: the server `.env` may have CRLF endings - always `tr -d '\r'`
> before sourcing, always verify dump size.

### 6.2 Web changed (frontend source)
```bash
# LOCAL: gates (section 5), then build -> backend/public/production -> commit ONLY that path -> push
cd frontend
npm run deploy:web:production
```
```bash
# SERVER: mirror + copy over (NEVER wipe the docroot)
cd backend
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  git fetch origin && git reset --hard origin/main
  cd /home/u214605677/domains/custospark.com/public_html
  TS=\$(date +%Y%m%d-%H%M%S)
  cp -rT frama /home/u214605677/domains/frama-backup-\$TS
  echo BACKUP=frama-backup-\$TS
  cp -rT /home/u214605677/domains/frama-api.custospark.com/public/production frama
"
```
- `cp -rT SRC DST` copies contents **including dotfiles** (`-r .../*` drops
  `.htaccess` = broken SPA). Stale hashed assets stay on disk on purpose; the
  new `index.html` stops referencing them.
- The build carries `.htaccess` (from `Frontend/deploy/htaccess.production`), so
  the copy always re-carries the right rewrite. If you edit that file locally,
  ALSO commit the regenerated `Backend/public/production/.htaccess` so repo
  bytes == server bytes.
- The docroot directory is **never** `rm -rf`'d; `default.php` placeholders
  were already removed in the first deploy (nothing to remove again).

### 6.3 Done - run verification (section 7), then report per section 11.

### 6.4 If `.env` keys changed
Do NOT edit `.env` on the server. Update local `Backend/.env.server`, then:
```bash
python scripts/push_env.py     # uploads .env.server -> server .env (0600)
python scripts/ssh_run.py "cd /home/u214605677/domains/frama-api.custospark.com && /usr/bin/php artisan optimize:clear"
```
(Never re-run `key:generate`; `.env.server` keeps `APP_KEY=` empty on purpose -
the server `.env` already holds the key.)

---

## 7. Verification (all must pass, every deploy)

```bash
python scripts/ssh_run.py "
  cd /home/u214605677/domains/custospark.com/public_html/frama
  echo \"assets on disk: \$(ls assets | wc -l)\"
  echo \"refs in index:  \$(grep -oE 'assets/[a-zA-Z0-9_/-]+\.js' index.html | sort -u | wc -l)\"
  grep -oE 'assets/[a-zA-Z0-9_/-]+\.js' index.html | sort -u | while read f; do [ -f \"\$f\" ] || echo \"MISSING: \$f\"; done
  JS=\$(grep -oE 'assets/[a-zA-Z0-9_/-]+\.js' index.html | sort -u | head -1)
  curl -s -o /dev/null -w 'js:    %{http_code} %{content_type}\n' \"https://frama.custospark.com/\$JS\"
  curl -s -o /dev/null -w 'root:  %{http_code}\n' https://frama.custospark.com/
  curl -s -o /dev/null -w 'deep:  %{http_code}\n' https://frama.custospark.com/reports
  curl -s -o /dev/null -w 'api:   %{http_code} %{content_type}\n' https://frama-api.custospark.com/api/v1/health
  grep -rhoE 'https://[a-z-]*api[a-z-]*\.custospark\.com/api/v1' assets/ | sort -u
  ls -la /home/u214605677/domains/custospark.com/public_html/frama-api
  find /home/u214605677/domains/frama-api.custospark.com/storage/logs -name 'laravel-*.log' -newermt '-30 minutes' -exec tail -20 {} \; 2>/dev/null | grep -icE 'error|exception' || true
"
```

Checks that must be true:

- [ ] `/api/v1/health` 200 + JSON
- [ ] Web root, deep SPA route, `/login` all 200
- [ ] JS served as a JavaScript MIME (`application/x-javascript`), **never**
      `text/html` (text/html = rewrite answering for a missing file: STOP)
- [ ] Zero `MISSING:` lines; refs in `index.html` resolve on disk
- [ ] Bundle references `https://frama-api.custospark.com/api/v1` only (no
      localhost, no other product's API)
- [ ] `frama-api` is still a symlink -> `<clone>/public`
- [ ] CORS: `OPTIONS` from `https://frama.custospark.com` returns 204 with
      `access-control-allow-origin` (login is a cross-origin Bearer flow)
- [ ] Login smoke: `POST /api/v1/auth/login` `{"access_code":"FRAMA","pin":"9001"}`
      -> 200 + token (never print the token) - do this at least after auth/route
      changes
- [ ] Bounce: `https://custospark.com/frama/login` -> 301
      `https://frama.custospark.com/login`
- [ ] No new errors in `storage/logs/laravel*.log`
- [ ] Shared `public_html/` sibling entries untouched (`ls` compare)

---

## 8. Backup & rollback

Backups live OUTSIDE the shared docroot, in `~/domains/`:

| What | Path pattern |
|---|---|
| Web docroot (auto before every copy, section 6.2) | `/home/u214605677/domains/frama-backup-<timestamp>` |
| `.htaccess` (first deploy) | `/home/u214605677/domains/frama-htaccess-backup-20261020-174620` |
| DB dump (before every migrate, section 6.1) | `/home/u214605677/domains/backups/frama-db-<timestamp>.sql` |

Rollback:

```bash
# Web: copy the backup forward (nothing deleted)
python scripts/ssh_run.py "
  cd /home/u214605677/domains/custospark.com/public_html
  cp -rT frama /home/u214605677/domains/frama-broken-\$(date +%Y%m%d-%H%M%S)
  cp -rT /home/u214605677/domains/frama-backup-<timestamp> frama
"
# API: mirror the previous known-good commit
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  git fetch origin
  git checkout <previous-known-good-commit> -- .
  /usr/bin/php artisan optimize:clear
"
# DB: restore the dump
python scripts/ssh_run.py "
  tr -d '\r' < /home/u214605677/domains/frama-api.custospark.com/.env > /tmp/fe.sh && set -a && . /tmp/fe.sh && set +a && rm -f /tmp/fe.sh
  /usr/bin/mysql -h \"\${DB_HOST}\" -u \"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" \"\$DB_DATABASE\" < /home/u214605677/domains/backups/frama-db-<timestamp>.sql
"
```

---

## 9. Queue, cron and known server facts

- **No scheduled tasks exist** (`routes/console.php`). When WhatsApp/reminders
  land, add ONE hPanel cron, every minute, all five fields `*`:
  `/usr/bin/php /home/u214605677/domains/frama-api.custospark.com/artisan schedule:run`
  (`crontab` is not installed; cron is hPanel-only.)
- **No supervisor.** Queue is `database`; if jobs are added, use the sibling
  pattern: one hPanel cron running
  `artisan queue:work --stop-when-empty --max-time=50 --sleep=3 --tries=3`.
- `exec()` is disabled -> `artisan storage:link` FAILS. Symlinks are made with
  `ln -s` on the server (storage + API docroot are both already wired - see
  section 1).
- Server `.env`: gitignored, survives `git reset --hard`, mode 600, may be CRLF.
- SSL = hPanel AutoSSL at the vhost level; no cert files, no https rules in
  `.htaccess`.
- Large pushes can 408 -> `git config http.postBuffer 524288000`.
- A half-applied `git reset` produces missing JS / `text/html` MIME errors -
  verify the tree matches `origin/main` before copying a build.
- Attachments allow up to 50 MB/file; PHP on this host reports 2048M limits.
- `python` (paramiko) helpers on the dev machine: `Backend/scripts/ssh_run.py`
  (run remote command), `Backend/scripts/push_env.py` (upload `.env.server`).
  Both read `Backend/.env` `SSH_DEPLOY_*`; never print those values.

---

## 10. First-time setup (REFERENCE ONLY - already executed 2026-10-20)

Do not re-run. Kept for disaster recovery (fresh DB / new account):

1. hPanel: subdomains `frama.custospark.com` + `frama-api.custospark.com`
   (docroots = `public_html/frama` + `public_html/frama-api`), database
   `u214605677_Frama_2026` (user == name).
2. `git clone https://github.com/Custospark/frama-api.git ~/domains/frama-api.custospark.com`
3. `python scripts/push_env.py` (uploads `.env.server`; keep `APP_KEY=` empty in
   `.env.server`), then `composer install`, then `php artisan key:generate`
   **once on the empty DB only**.
4. `[ -L public/storage ] || ln -s "$PWD/storage/app/public" public/storage`
5. `php artisan migrate --force && php artisan db:seed --force && php artisan optimize:clear`
6. API docroot: `rm -f public_html/frama-api/default.php && rmdir public_html/frama-api && ln -s <clone>/public frama-api`
7. Web: `npm run deploy:web:production`, then backup + `cp -rT` build ->
   `public_html/frama` (after removing `frama/default.php`).
8. Verify (section 7); change the seeded PINs when real users onboard.

---

## 11. Deployment plan template (copy into the todo list)

```
DEPLOYMENT PLAN - Frama production - <YYYY-MM-DD>

SCOPE
- API commit(s):  <hash> -> <hash>  [what changed]
- Web commit(s):  <hash> -> <hash>  [what changed]

MIGRATIONS
- New migrations to run with --force: <list> (or: none)
- Confirmation: NO destructive migration (no drop/refresh/fresh).

BACKUP
- Web: /home/u214605677/domains/frama-backup-<timestamp> (auto, section 6.2)
- DB:  /home/u214605677/domains/backups/frama-db-<timestamp>.sql (before migrate)

ROLLBACK
- Web: cp -rT <frama-backup-<timestamp>> frama
- API: git checkout <previous-commit> -- . && artisan optimize:clear
- DB:  mysql < frama-db-<timestamp>.sql

STEPS
1. Local gates (artisan test; typecheck/lint/test:unit), clean trees
2. Commit exact paths, push both repos
3. Server: git fetch + reset --hard
4. composer install (only if lock changed)
5. Dump DB (verify non-zero), migrate --force (only if migrations changed)
6. optimize:clear
7. Web: deploy:web:production + server backup/cp -rT (if frontend changed)
8. Verification per section 7

VERIFY (all must pass)
- [ ] /api/v1/health 200 JSON
- [ ] web root / deep / login 200
- [ ] JS MIME = javascript, not text/html; 0 missing assets
- [ ] bundle points at frama-api.custospark.com/api/v1 only
- [ ] frama-api symlink intact; CORS + login smoke (if auth touched)
- [ ] bounce 301 works; no new storage/logs errors; siblings untouched

APPROVAL
- Presented to Oscar: [ ] APPROVED   Date/time: ______
```
