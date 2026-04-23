SESSION S83-PRE SECURITY HANDOFF - 23.04.2026
================================================

Git tag: v0.6.2-s82-ready-for-security
Focus: P0 SECURITY fix before real beta client

CRISIS (2 leaks)
----------------

1. API keys leaked in git history -> auto-revoked by Google and OpenAI
   Evidence in git log:
     8922081 "remove leaked API key"
     3f883dc "Update config.php"
     6e896f7 "Update config.php"
   Removing in a newer commit does NOT clean history.

2. MySQL password leaked on 23.04
   Shell tokenized a mysql command into filenames:
     ***REMOVED_DB_PASSWORD***,
     localhost,
     runmystore,
     utf8mb4,
   Files already removed, but password is in bash history + prior Claude contexts.

ROTATE (Tihol does in parallel in dashboards)
---------------------------------------------

- Google AI Studio: 2 keys (used for 429 rotation)
- OpenAI API key (if used)
- Anthropic API key (if used)
- fal.ai API key (background removal + try-on)
- MySQL password (ALTER USER ... IDENTIFIED BY ...)
- Check billing usage in all dashboards for anomalies

FIRST COMMAND IN NEW CHAT
-------------------------

cd /var/www/runmystore
grep -rn "AIza\|sk-\|GEMINI\|OPENAI\|0okm9" config/ *.php 2>/dev/null | head -20
cat .gitignore 2>/dev/null
git ls-files | grep -iE "config|env|secret"
git log --all --oneline -- config.php config/database.php | head -10

FULL PLAN
---------

Phase 1 - Tihol: rotate in dashboards, check billing usage.

Phase 2 - Claude fixes code:
  1. Create /var/www/runmystore/.env (chmod 600, root only)
  2. Move ALL secrets to .env (DB + all API keys)
  3. Add .env to .gitignore
  4. git rm --cached config.php config/database.php
  5. Update config/database.php + config.php to use getenv()
  6. BFG Repo Cleaner OR force-rebase for git history cleanup
  7. Verify: git log --all -p config.php | grep AIza  -> must be empty
  8. Runtime verify: all AI and DB functions work with new keys

Phase 3 - Cleanup:
  - history -c && history -w on droplet
  - No more untracked sensitive files

WHAT S82.CAPACITOR FINISHED (23.04.2026)
----------------------------------------

DTM-5811 BLE printing from Android APK. Production-ready.

Hotfixes S82.CAPACITOR.3 -> .18:
  .3 ACCESS_FINE_LOCATION without maxSdkVersion (Android 12+ BLE)
  .5 lblPrint (edit drawer) -> lblPrintMobile on native
  .6 capacitor-head.php include in products.php
  .7 CFG.storeName from DB (not hardcoded "RunMyStore")
 .10 Canvas -> TSPL BITMAP rasterize (cyrillic works!)
 .11 EAN13 barcode auto-detect + BLE reconnect
 .12 Loading overlay with progress counter
 .13 Speed: CHUNK 20 -> 100, sleep 15ms -> 5ms, keep-alive
 .16-.18 Final layout: 28/38px fonts, narrow=3-4 barcode, spacing

Tag: v0.6.1-s82-capacitor-complete

AFTER SECURITY - BY PRIORITY
----------------------------

1. S82.BACK_BUTTON (15 min)
   Android back button closes APP instead of drawer.
   Fix: Capacitor App plugin + addListener backButton handler.

2. S82.AI_STUDIO diagnostic
   openImageStudio(productId) in products.php around line 4460.
   Uses fal.ai for background removal + try-on.
   Verify it works after API rotation. Update config if needed.

3. S82.PRODUCTS_AUDIT - full audit
   products.php is 8394 lines. Check for:
   - Dead code / unused functions
   - P0 bugs from PRODUCTS_MAIN_BUGS_S80.md
   - Memory leaks in event listeners
   - Consistency wizard flow vs edit flow
   - Mobile vs desktop paths (lblPrint/lblPrintMobile split exists)

4. S82.BETA_READINESS checklist
   Tihol wants 1 real client who ONLY:
   - Adds products
   - Prints labels
   - NO sales, orders, deliveries yet
   
   Verify:
   - Onboarding flow works for new tenant (not tenant=7)
   - Wizard end-to-end for product add
   - Label print in APK (DONE)
   - Hide unused modules in UI
   - Error boundaries (no white screen on errors)
   - Auto DB backup cron

GITHUB ACCESS IN NEW CHAT
-------------------------

cd /tmp
git clone --depth=1 https://github.com/tiholenev-tech/runmystore.git gh_cache/tiholenev-tech_runmystore 2>/dev/null || git -C gh_cache/tiholenev-tech_runmystore pull --quiet
cp gh_cache/tiholenev-tech_runmystore/tools/gh_fetch.py /tmp/gh.py

Usage: python3 /tmp/gh.py PATH

START PROMPT FOR NEW CHAT
-------------------------

S79.SECURITY P0 - API keys leaked + DB password leaked crisis.

Bootstrap GitHub access from CLAUDE_GITHUB_ACCESS.md.
Read MASTER_COMPASS.md and SESSION_S83_PRE_SECURITY_HANDOFF.md.
Start with diagnostic command from handoff.
Tihol rotates keys in parallel in dashboards.

END OF HANDOFF
