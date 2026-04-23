# SESSION S79.SECURITY - HANDOFF

**Data:** 23.04.2026
**Status:** DONE (done)
**Type:** security incident response, executed early (planned for S109)
**Time:** ~2 hours
**Tihol + CHAT 2 (Opus 4.7)

---

## PROBLEM

Compromised secrets in public GitHub repo tiholenev-tech/runmystore:

- DB password in config/database.php
- Gemini API Key 1 in config/config.php
- Gemini API Key 2 in config/config.php
- OpenAI API Key in config/config.php (auto-disabled by OpenAI)
- Old password in .claude/settings.local.json (untracked)

Scope: 23 occurrences of compromised secrets in git history.

---

## EXECUTED (in order)

1. **MySQL rotation:** new password (policy-compliant) for root@localhost + runmystore@localhost. Test: SELECT COUNT(*) FROM products = 732.
2. **Env files:* /etc/runmystore/db.env + /etc/runmystore/api.env (chmod 600, www-data).
3. **Config rewrites:** config/database.php + config/config.php use parse_ini_file() + fail-fast error handling.
4. **API keys rotation:** 3 old Gemini (VW8c, VojqSU, _S5Gc) deleted, 2 new created; OpenAI auto-disabled, new created (runmystore-prod).
5. **Repo hardening:** .gitignore updated (wildcards for *.env, .env.*, !env.example, .claude/settings.local.json); config/database.php.save deleted; .claude/settings.local.json deleted; .env.example added (public template).
6. **History scrub:** git-filter-repo rewrote 662 commits in 2.91 seconds; 23 -> 0 secrets verified.
7. **Force push:** + 85c5a43...e15f719 main -> main (forced update).
8. **Verification:** public config/database.php + config/config.php clean; https://runmystore.ai/ -- HTTP 302.

---

## FILES CHANGED

- config/database.php -- modified
- config/config.php -- modified
- .gitignore -- modified
- .env.example -- new
- config/database.php.save -- deleted
- docs/SESSION_S79_SECURITY_HANDOFF.md -- new
- docs/compass/MASTER_COMPASS.md -- updated

NOT in git but important:
- /etc/runmystore/db.env
- /etc/runmystore/api.env
- /root/runmystore_git_backup_20260423_0945/ + .tar.gz
- /root/database.php.bak.20260423_0906

---

## TIHOL MUST DO

1. Close / reset all local clones (Windows, Mac, Cursor) using git fetch origin && git reset --hard origin/main
2. CHAT 1 and any other active chat must run the same command before new work.
3. Check OpenAI billing for suspicious charges while old key was exposed.
4. (Optional) gitleaks pre-commit hook -- S80 task.

---

## REWORK QUEUE IMPACT

REWORK #13 (DB credentials rotation) -- planned for S109 --> DONE early.

All API keys (Gemini x2, OpenAI) rotated as part of the same incident.

---

## SECURITY MODEL AFTER S79

All secrets live in /etc/runmystore/*.env (chmod 600, www-data).
PHP reads via parse_ini_file(). No key lives in any git-tracked file.
.gitignore blocks *.env, secrets/, *.local.php.
.env.example placeholders serve as a public template for new deployments.

Rule: if Claude adds a new API key, it always goes in the env file -- never hardcoded.

Insight: History scrub is cosmetic. ROTATION IS THE REAL FIX. Since all exposed keys are now dead, archived copies of the old history (forks, Wayback, caches) are harmless.

---

## NEXT

S80 or S82.CAPACITOR (Tihol choice). Normal development resumes.
First step for any new session: git reset --hard origin/main.

---

END S79.SECURITY HANDOFF
