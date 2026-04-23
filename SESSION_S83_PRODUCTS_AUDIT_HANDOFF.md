S83 PRODUCTS AUDIT HANDOFF - 23.04.2026
==========================================

Git tag: v0.6.3-beta-ready-pending-audit
Priority: AFTER S79.SECURITY (api keys rotation still P0)

KNOWN BROKEN
------------
1. Barcode camera in Android APK. Works in browser.
   WebView blocks getUserMedia without MainActivity PermissionRequest handler.
   Three fix options:
   A. Snap-photo + ZXing decode (no rebuild, 2s delay)
   B. Patch MainActivity.java onPermissionRequest + APK rebuild
   C. Install capacitor-community barcode-scanner plugin + rebuild

2. Possible other bugs in products.php (Tihol feels it). 8394 lines, needs audit.

AUDIT CHECKLIST
---------------
- Wizard vs Edit flow consistency
- Mobile vs Desktop paths
- P0 bugs from PRODUCTS_MAIN_BUGS_S80.md
- Memory leaks (setInterval, listeners)
- Error handling
- Dead code (old 3-accordion wizard remains)
- Onboarding flow for new tenants
- Simple mode hiding

DONE TODAY (S82.CAPACITOR.3-22)
--------------------------------
BLE printing from APK works end-to-end.
Cyrillic via Canvas bitmap, speed CHUNK 100, full layout, Printer Settings modal, FAB.

DONELA.BG BETA TENANT READY
---------------------------
Tenant 8, login anisabeva@gmail.com / 7878, store 2, 1 product DON-001.

NEXT CHAT PROMPT
----------------
S83 PRODUCTS_AUDIT + BARCODE CAMERA

Bootstrap GitHub access (see CLAUDE_GITHUB_ACCESS.md).
Read MASTER_COMPASS.md and SESSION_S83_PRODUCTS_AUDIT_HANDOFF.md.
Decide barcode fix A/B/C first, then systematic products.php audit.

END
