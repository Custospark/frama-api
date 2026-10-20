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

---

## 1. Environments & topology

Frama has **no staging environment** (deliberate - skipped for the first
deploy). Production is the only environment; keep a backup before every deploy.

| Piece | Host path | URL |
|---|---|---|
| Web (SPA) | `/home/u214605677/domains/frama.custospark.com/public_html` | `https://frama.custospark.com` |
| API (Laravel) | `/home/u214605677/domains/frama-api.custospark.com` | `https://frama-api.custospark.com/api/v1` |

**Hosting** (Hostinger shared hosting, account `u214605677` - same account as
Academy and Custosell):

- SSH: `147.79.103.136`, port `65002`, user `u214605677`.
- PHP binary: `/usr/bin/php` (PHP 8.2.33). Scheduler/cron path used by sibling
  apps: `/opt/alt/php82/usr/bin/php`.
- Composer: `/usr/local/bin/composer`.
- Python: **no `python3` on PATH**. Use `/opt/alt/python311/bin/python3.11`
  for scripts; on the developer machine `python` runs the helpers.
- No root, no `sudo`, no package manager. Nothing installs host-wide.
- Database: `u214605677_Frama_2026` (name == username), MariaDB, reachable only
  from `127.0.0.1` and only by its own user. New databases can only be created
  in hPanel.
- Credentials live in `Backend/.env` under `SSH_DEPLOY_*` (the same values
  Academy/Custosell use), read by `Backend/scripts/ssh_run.py`. Never typed,
  never printed.
- Server-side secrets are assembled locally in `Backend/.env.server`
  (gitignored) and uploaded to the server `.env` by `Backend/scripts/push_env.py`.

**Repos** (public, so the server pulls plain HTTPS with no deploy key):
- `github.com/Custospark/frama-api` - Laravel backend + the shipped web build
  (`public/production`), branch `main`.
- `github.com/Custospark/frama-frontend` - React SPA source, branch `main`.

---

## 2. Non-negotiables (violating ANY of these is a P0 incident)

1. **NEVER delete anything on the server.** Backups are additive: copy the old
   build aside (`cp -rT` to a timestamped name), then copy the new one over.
2. **NEVER run `migrate:fresh`, `migrate:refresh`, `db:wipe`, or drop a
   database.** Migrations are additive and forward-only.
3. **NEVER run `key:generate` after the first-time setup.** It invalidates
   every token and session. It is correct exactly once, on the brand-new empty
   database (section 5).
4. **NEVER edit or delete an existing migration.** To change schema, add a new
   forward migration.
5. **NEVER `git add -A` on the backend repo during a deploy.** Stage only the
   exact path; `deploy:web:production` does this for `public/production`.
6. **NEVER run `php artisan migrate` without `--force` on the server.**
7. **NEVER print secrets.** Read them from env files via scripts.
8. **NEVER commit `.env`, `.env.server`, `.env.development` or any `*.local`
   file.**
9. **NEVER touch another product's paths.** Everything under
   `/home/u214605677/domains/` that is not `frama*` is someone else's production.
10. **NEVER deploy from a dirty tree.** `git status --short` must be clean
    before and after.
11. **Never use globs (`cp -r dir/*`) for the web copy** - `.htaccess` silently
    dies and the SPA rewrite breaks. Always `cp -rT SRC DST`.

---

## 3. Mandatory deployment plan (the approval gate)

Every deploy - first-time or later - requires a written plan, presented to Oscar
and explicitly approved **before any command runs on the server**. The plan
states: scope (exact commits), files touched, migrations, backup location,
rollback commands, and the verification that proves success. Never "deploy and
see".

---

## 4. Local gates (before ANY push)

```bash
cd backend  && C:/xampp/php/php.exe artisan test     # 75 tests must pass
cd frontend && npm run typecheck && npm run lint && npm run test:unit
```

Push both repos to GitHub **before** deploying - the server only ever mirrors
`origin/main`.

---

## 5. First-time deploy (once only, THIS deployment)

### 5.1 Prerequisites
- [ ] Subdomains exist and resolve: `frama.custospark.com`,
      `frama-api.custospark.com` (created in hPanel).
- [ ] Database exists (hPanel only): `u214605677_Frama_2026`, user = same name.
- [ ] `Backend/.env` has `SSH_DEPLOY_*` filled (copied from Academy) -
      never printed.
- [ ] `Backend/.env.server` holds the production values (gitignored).
- [ ] Both repos pushed to GitHub (`main`).

### 5.2 Clone the app dir
```bash
cd backend
python scripts/ssh_run.py "git clone https://github.com/Custospark/frama-api.git /home/u214605677/domains/frama-api.custospark.com"
```

### 5.3 Server `.env` + APP_KEY (once, empty DB)
```bash
python scripts/push_env.py            # uploads Backend/.env.server -> server .env (0600)
python scripts/ssh_run.py "cd /home/u214605677/domains/frama-api.custospark.com && /usr/bin/php artisan key:generate"
```

### 5.4 Dependencies + storage link
`exec()` is disabled on this host, so `artisan storage:link` FAILS; use `ln -s`:
```bash
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  /usr/bin/php -d memory_limit=512M /usr/local/bin/composer install --no-dev --prefer-dist --no-interaction
  [ -L public/storage ] || ln -s \"\$PWD/storage/app/public\" public/storage
"
```

### 5.5 Migrate + seed (first-time only)
```bash
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  /usr/bin/php artisan migrate --force
  /usr/bin/php artisan db:seed --force
  /usr/bin/php artisan optimize:clear
"
```
Seeds: branches (RUBAGA, KIBUYA), 4 business types, ~90 report fields, admin
`FRAMA`/`9001`, executive `FRAMA`/`1234`, managers `RUBAGA`/`1234`,
`KIBUYA`/`1234`. **Seeded PINs are known values - flag them to Oscar to change
once the real users are onboarded.**

### 5.6 API docroot + storage symlinks (server, `ln -s` only)
Symbolic links are created **on the server with `ln -s`** - never with
`php artisan storage:link` (its `exec()` is disabled here) and never with a PHP
command:

```bash
# storage link (attachments live on the public disk)
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  [ -L public/storage ] || ln -s \"\$PWD/storage/app/public\" public/storage
"
# API docroot: the frama-api.custospark.com vhost must serve Laravel public/.
# Inspect first, report what is found, then wire if needed (same pattern as
# Academy/Custosell - a symlink created with ln -s, not a PHP command):
python scripts/ssh_run.py "ls -la /home/u214605677/domains/frama-api.custospark.com/ | head -20; ls -la /home/u214605677/domains/frama-api.custospark.com/public_html 2>/dev/null || true"
```

### 5.7 Ship the web build
```bash
cd frontend
npm run deploy:web:production   # typecheck -> build -> copy to backend/public/production
                                # -> commit ONLY that path -> push
```
On the server (copy over, never wipe):
```bash
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  git fetch origin && git reset --hard origin/main
  cd /home/u214605677/domains/frama.custospark.com
  TS=\$(date +%Y%m%d-%H%M%S)
  cp -rT public_html ../frama-backup-\$TS
  cp -rT /home/u214605677/domains/frama-api.custospark.com/public/production public_html
"
```

### 5.8 Verify (section 7) then report (section 10)

---

## 6. Deploying after the first time

### 6.1 API
```bash
# local: gates (section 4) -> commit exact paths -> git push origin main
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  git fetch origin && git reset --hard origin/main
  git status --short | grep -v '^??'        # must be empty
"
# only if composer.json/composer.lock changed:
python scripts/ssh_run.py "cd /home/u214605677/domains/frama-api.custospark.com && /usr/bin/php -d memory_limit=512M /usr/local/bin/composer install --no-dev --prefer-dist --no-interaction"
# only if database/migrations changed - ALWAYS dump first (see section 8), then:
python scripts/ssh_run.py "cd /home/u214605677/domains/frama-api.custospark.com && /usr/bin/php artisan migrate --force"
# every deploy:
python scripts/ssh_run.py "cd /home/u214605677/domains/frama-api.custospark.com && /usr/bin/php artisan optimize:clear"
```

### 6.2 Web
Same as section 5.7: `npm run deploy:web:production` locally, then server
backup + `cp -rT`.

---

## 7. Verification (all must pass, every deploy)

```bash
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama.custospark.com/public_html
  echo \"assets on disk: \$(ls assets | wc -l)\"
  echo \"refs in index:  \$(grep -oE 'assets/[a-zA-Z0-9_/-]+\.js' index.html | sort -u | wc -l)\"
  grep -oE 'assets/[a-zA-Z0-9_/-]+\.js' index.html | sort -u | while read f; do [ -f \"\$f\" ] || echo \"MISSING: \$f\"; done
  JS=\$(grep -oE 'assets/[a-zA-Z0-9_/-]+\.js' index.html | sort -u | head -1)
  curl -s -o /dev/null -w 'js:    %{http_code} %{content_type}\n' \"https://frama.custospark.com/\$JS\"
  curl -s -o /dev/null -w 'root:  %{http_code}\n' https://frama.custospark.com/
  curl -s -o /dev/null -w 'deep:  %{http_code}\n' https://frama.custospark.com/reports
  curl -s -o /dev/null -w 'api:   %{http_code}\n' https://frama-api.custospark.com/api/v1/health
  grep -rhoE 'https://[a-z-]*api[a-z-]*\.custospark\.com' assets/ | sort -u
"
```

Checks that must be true:

- [ ] `/api/v1/health` returns 200 and JSON
- [ ] Web root 200, deep SPA route 200
- [ ] JS served as a JavaScript MIME type, **never** `text/html`
- [ ] Zero `MISSING:` lines from the asset reference scan
- [ ] Bundle references `frama-api.custospark.com` only (no localhost)
- [ ] CORS preflight from `https://frama.custospark.com` accepts the Bearer flow
- [ ] Login smoke test with the seeded admin succeeds
- [ ] Attachment upload (form with a receipt) succeeds - check PHP
      `upload_max_filesize` / `post_max_size` allow 50 MB if it fails
- [ ] No new errors in `storage/logs/laravel.log`
- [ ] Shared-docroot sibling entries untouched (`ls` compare with pre-flight)

> A `text/html` MIME on a JS asset = the SPA rewrite answered for a file that is
> not on disk. Stop. Do not report it as deployed.

---

## 8. Backup & rollback

Pre-deploy backups (every deploy that touches the DB or the web build):

```bash
# Web docroot backup (done automatically in 5.7 / 6.2 before every copy)
python scripts/ssh_run.py "
  TS=\$(date +%Y%m%d-%H%M%S)
  cp -rT /home/u214605677/domains/frama.custospark.com/public_html /home/u214605677/domains/frama-backup-\$TS
  echo BACKUP=frama-backup-\$TS
"
# DB dump (before any migrate) - CRLF gotcha: strip \r, verify non-zero size
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  tr -d '\r' < .env > /tmp/fe.sh && set -a && . /tmp/fe.sh && set +a && rm -f /tmp/fe.sh
  TS=\$(date +%Y%m%d-%H%M%S)
  mkdir -p /home/u214605677/domains/backups
  BK=/home/u214605677/domains/backups/frama-db-\$TS.sql
  /usr/bin/mysqldump -h \"\${DB_HOST}\" -P \"\${DB_PORT}\" -u \"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" \"\$DB_DATABASE\" > \"\$BK\" 2>/dev/null
  ls -la \"\$BK\"        # MUST be non-zero, else STOP
"
```

Rollback:

```bash
# Web: copy the backup back (nothing is ever deleted)
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama.custospark.com
  cp -rT public_html ../frama-broken-\$(date +%Y%m%d-%H%M%S)
  cp -rT ../frama-backup-<timestamp> public_html
"
# API: mirror the previous known-good commit
python scripts/ssh_run.py "
  cd /home/u214605677/domains/frama-api.custospark.com
  git fetch origin
  git checkout <previous-known-good-commit> -- .
  /usr/bin/php artisan optimize:clear
"
# DB: restore the dump taken above
python scripts/ssh_run.py "
  tr -d '\r' < /home/u214605677/domains/frama-api.custospark.com/.env > /tmp/fe.sh && set -a && . /tmp/fe.sh && set +a && rm -f /tmp/fe.sh
  /usr/bin/mysql -h \"\${DB_HOST}\" -u \"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" \"\$DB_DATABASE\" < /home/u214605677/domains/backups/frama-db-<timestamp>.sql
"
```

---

## 9. Queue, cron and known server facts

- **No scheduled tasks exist yet** (`routes/console.php` has none). When
  WhatsApp/reminders land, add ONE hPanel cron, every minute, all five fields
  `*`:
  `/usr/bin/php /home/u214605677/domains/frama-api.custospark.com/artisan schedule:run`
  (`crontab` is not installed; cron is hPanel-only. Sibling apps use
  `/opt/alt/php82/usr/bin/php` if `/usr/bin/php` ever fails in cron.)
- **No supervisor.** Queue is `database`; if jobs are ever added, use the
  sibling pattern: one hPanel cron running
  `artisan queue:work --stop-when-empty --max-time=50 --sleep=3 --tries=3`.
- The server `.env` may have CRLF endings - `tr -d '\r'` before sourcing.
- The server `.env` is gitignored and survives `git reset --hard`.
- Large pushes can 408; raise `git config http.postBuffer 524288000`.
- SSL is hPanel AutoSSL at the vhost level; no certificate files, no https
  rules in `.htaccess`.
- A half-applied `git reset` produces missing JS / `text/html` MIME errors -
  verify the tree matches `origin/main` before copying a build.
- `exec()` disabled -> `artisan storage:link` fails; `ln -s` instead (5.4).

---

## 10. Deployment plan template (copy into the todo list)

```
DEPLOYMENT PLAN - Frama production - <YYYY-MM-DD>

SCOPE
- API commit(s):  <hash> -> <hash>  [what changed]
- Web commit(s):  <hash> -> <hash>  [what changed]

MIGRATIONS
- New migrations to run with --force: <list>
- Confirmation: NO destructive migration (no drop/refresh/fresh).

BACKUP
- Web: /home/u214605677/domains/frama-backup-<timestamp>
- DB:  /home/u214605677/domains/backups/frama-db-<timestamp>.sql

ROLLBACK
- Web: cp -rT ../frama-backup-<timestamp> public_html
- API: git checkout <previous-commit> -- . && artisan optimize:clear
- DB:  mysql < frama-db-<timestamp>.sql

STEPS
1. Local gates (artisan test; typecheck/lint/test:unit), clean trees
2. Push both repos
3. Server: git fetch + reset --hard
4. composer install (only if lock changed)
5. Dump DB, migrate --force (only if migrations changed)
6. optimize:clear
7. Backup + cp -rT web build (if frontend changed)
8. Verification per section 7

VERIFY (all must pass)
- [ ] /api/v1/health 200 JSON
- [ ] web root 200, deep route 200
- [ ] JS MIME = javascript, not text/html
- [ ] 0 missing assets
- [ ] bundle points at frama-api.custospark.com only
- [ ] login smoke test
- [ ] no new errors in storage/logs/laravel.log

APPROVAL
- Presented to Oscar: [ ] APPROVED   Date/time: ______
```
