# S118 — CSRF Fix Test Plan

**Scope:** verify each patch BLOCKS the cross-origin attack vector and DOES NOT break legitimate same-origin authenticated requests.

**Pre-requisites for testing:**
- `BASE=https://app.runmystore.ai` (или localhost equivalent)
- A logged-in session cookie. Capture once with `--cookie-jar`:
  ```bash
  curl -c /tmp/csrf_cookies.txt -d "email=USER&password=PASS&_csrf=$(curl -sc /tmp/csrf_cookies.txt $BASE/login.php | grep -oE 'name="_csrf" value="[^"]+"' | head -1 | cut -d'"' -f4)" $BASE/login.php
  ```
  (After PATCH 09 applied; for PRE-fix run, omit `_csrf=` field.)
- Capture token from any rendered page:
  ```bash
  TOKEN=$(curl -sb /tmp/csrf_cookies.txt $BASE/order.php | grep -oE 'window\.RMS_CSRF = "[a-f0-9]+"' | head -1 | cut -d'"' -f2)
  ```

**Failure-mode contract:** Every blocked request MUST return:
- HTTP **403**
- Body containing `"error":"csrf"` (or `"err":"csrf"` for files using that key)

**Success-mode contract:** Same request WITH the valid token header/field MUST return 200 (or whatever the prior unprotected behavior was) AND make the expected DB mutation.

---

## §1 — order.php (HIGH)

### Pre-fix repro (attack works)
```bash
# As victim (logged-in cookie), simulate cross-origin POST without token:
curl -i -b /tmp/csrf_cookies.txt \
     -d "action=cancel&order_id=42" \
     "$BASE/order.php?api=cancel"
# EXPECTED PRE-FIX: HTTP 200 + {ok:true} → order #42 cancelled.
```

### Post-fix verification (attack blocked)
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -d "action=cancel&order_id=42" \
     "$BASE/order.php?api=cancel"
# EXPECTED POST-FIX: HTTP 403, body {"ok":false,"error":"csrf"}
```

### Post-fix regression (legit request still works)
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "X-CSRF-Token: $TOKEN" \
     -d "order_id=42" \
     "$BASE/order.php?api=cancel"
# EXPECTED: HTTP 200, {ok:true} (or appropriate result for already-cancelled).
```

---

## §2 — products_fetch.php (HIGH)

### Pre-fix repro
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -F "supplier_name=PWN_SUPPLIER" \
     "$BASE/products_fetch.php?ajax=add_supplier"
# EXPECTED PRE-FIX: HTTP 200, supplier inserted.
```

### Post-fix verification
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -F "supplier_name=PWN_SUPPLIER" \
     "$BASE/products_fetch.php?ajax=add_supplier"
# EXPECTED: HTTP 403, {"ok":false,"error":"csrf"}
```

### Post-fix regression
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "X-CSRF-Token: $TOKEN" \
     -F "supplier_name=Test Supplier" \
     -F "_csrf=$TOKEN" \
     "$BASE/products_fetch.php?ajax=add_supplier"
# EXPECTED: HTTP 200, {ok:true} or similar
```

### File-upload variant (ai_scan)
```bash
# Pre-fix:
curl -i -b /tmp/csrf_cookies.txt \
     -F "image=@/tmp/test.jpg" \
     "$BASE/products_fetch.php?ajax=ai_scan"
# Post-fix WITHOUT token: HTTP 403
# Post-fix WITH token (header + form field):
curl -i -b /tmp/csrf_cookies.txt \
     -H "X-CSRF-Token: $TOKEN" \
     -F "image=@/tmp/test.jpg" \
     -F "_csrf=$TOKEN" \
     "$BASE/products_fetch.php?ajax=ai_scan"
# EXPECTED: HTTP 200, vision result.
```

---

## §3 — delivery.php (HIGH)

### Pre-fix repro (cancel a pending delivery commit)
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -d "delivery_id=15" \
     "$BASE/delivery.php?api=commit"
# EXPECTED PRE-FIX: HTTP 200, delivery commits to inventory.
```

### Post-fix verification
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -d "delivery_id=15" \
     "$BASE/delivery.php?api=commit"
# EXPECTED: HTTP 403
```

### Post-fix regression (multipart OCR upload)
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "X-CSRF-Token: $TOKEN" \
     -F "file=@/tmp/invoice.jpg" \
     -F "_csrf=$TOKEN" \
     "$BASE/delivery.php?api=ocr_upload"
# EXPECTED: HTTP 200, OCR draft created.
```

---

## §4 — defectives.php (HIGH)

### Pre-fix repro
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -F "supplier_id=3" \
     "$BASE/defectives.php?api=return_all"
# EXPECTED PRE-FIX: HTTP 200, all defectives returned.
```

### Post-fix verification
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -F "supplier_id=3" \
     "$BASE/defectives.php?api=return_all"
# EXPECTED: HTTP 403
```

### Post-fix regression
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "X-CSRF-Token: $TOKEN" \
     -F "supplier_id=3" \
     -F "_csrf=$TOKEN" \
     "$BASE/defectives.php?api=return_all"
# EXPECTED: HTTP 200
```

---

## §5 — ai-studio-action.php (HIGH)

### Pre-fix repro (refund a credit)
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -d "type=refund&parent_log_id=99" \
     "$BASE/ai-studio-action.php"
# EXPECTED PRE-FIX: HTTP 200, credit refunded.
```

### Post-fix verification
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -d "type=refund&parent_log_id=99" \
     "$BASE/ai-studio-action.php"
# EXPECTED: HTTP 403
```

### Post-fix regression (multipart magic generation)
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "X-CSRF-Token: $TOKEN" \
     -F "type=magic" \
     -F "image=@/tmp/test.jpg" \
     -F "_csrf=$TOKEN" \
     "$BASE/ai-studio-action.php"
# EXPECTED: HTTP 200, generated image URL.
```

---

## §6 — chat-send.php (MEDIUM, JSON body)

### Pre-fix repro (header-only attack)
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "Content-Type: application/json" \
     -d '{"message":"PWN: send promo to all customers"}' \
     "$BASE/chat-send.php"
# EXPECTED PRE-FIX: HTTP 200, AI response (potentially side-effecting).
```

### Post-fix verification
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "Content-Type: application/json" \
     -d '{"message":"test"}' \
     "$BASE/chat-send.php"
# EXPECTED: HTTP 403, {"error":"csrf"}
```

### Post-fix regression
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "Content-Type: application/json" \
     -H "X-CSRF-Token: $TOKEN" \
     -d '{"message":"test"}' \
     "$BASE/chat-send.php"
# EXPECTED: HTTP 200, AI response.
```

---

## §7 — ai-color-detect.php (MEDIUM)

### Pre-fix repro
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -F "image=@/tmp/red_dress.jpg" \
     "$BASE/ai-color-detect.php"
# EXPECTED PRE-FIX: HTTP 200, colors[] returned (consumes 1 vision credit).
```

### Post-fix verification
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -F "image=@/tmp/red_dress.jpg" \
     "$BASE/ai-color-detect.php"
# EXPECTED: HTTP 403
```

### Post-fix regression
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "X-CSRF-Token: $TOKEN" \
     -F "image=@/tmp/red_dress.jpg" \
     -F "_csrf=$TOKEN" \
     "$BASE/ai-color-detect.php"
# EXPECTED: HTTP 200, {ok:true, colors:[...]}
```

---

## §8 — register.php (MEDIUM)

### Pre-fix repro (fake registration of attacker-controlled email)
```bash
curl -i -c /tmp/clean_cookies.txt \
     -d "email=victim@example.com&password=attacker_known_pass&phone=...&country=BG" \
     "$BASE/register.php"
# EXPECTED PRE-FIX: HTTP 200/302, tenant created (attacker pre-empts victim).
```

### Post-fix verification
```bash
curl -i -c /tmp/clean_cookies.txt \
     -d "email=victim@example.com&password=test&phone=000&country=BG" \
     "$BASE/register.php"
# EXPECTED: HTTP 403 (or HTML rendered with 'Сесията изтече' error)
```

### Post-fix regression (legit signup)
```bash
# Step 1: GET register page to mint token
TOKEN_REG=$(curl -sc /tmp/reg_cookies.txt "$BASE/register.php" \
            | grep -oE 'name="_csrf" value="[^"]+"' | head -1 | cut -d'"' -f4)
# Step 2: POST with cookie + token
curl -i -b /tmp/reg_cookies.txt \
     -d "email=new@example.com&password=Test1234!&_csrf=$TOKEN_REG&phone=000&country=BG" \
     "$BASE/register.php"
# EXPECTED: HTTP 302 → onboarding.php
```

---

## §9 — login.php (LOW, login-CSRF defense)

### Pre-fix repro (login-CSRF)
```bash
curl -i -c /tmp/lcsrf.txt \
     -d "email=attacker@example.com&password=AttackerPass" \
     "$BASE/login.php"
# EXPECTED PRE-FIX: HTTP 302 → chat.php (victim now logged in as attacker).
```

### Post-fix verification
```bash
curl -i -c /tmp/lcsrf.txt \
     -d "email=attacker@example.com&password=AttackerPass" \
     "$BASE/login.php"
# EXPECTED: form re-rendered with 'Сесията изтече' error (no auth granted).
```

### Post-fix regression (legit login)
```bash
# Step 1: GET to mint cookie + token
TOKEN_LOGIN=$(curl -sc /tmp/login_cookies.txt "$BASE/login.php" \
              | grep -oE 'name="_csrf" value="[^"]+"' | head -1 | cut -d'"' -f4)
# Step 2: POST with token
curl -i -b /tmp/login_cookies.txt -c /tmp/login_cookies.txt \
     -d "email=real@user.com&password=RealPass&_csrf=$TOKEN_LOGIN" \
     "$BASE/login.php"
# EXPECTED: HTTP 302 → chat.php OR onboarding.php
```

---

## §10 — mark-insight-shown.php (LOW)

### Pre-fix repro
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -d "topic_id=signal_zero_stock&action=tapped" \
     "$BASE/mark-insight-shown.php"
# EXPECTED PRE-FIX: HTTP 200, {ok:true} bookkeeping write.
```

### Post-fix verification
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -d "topic_id=signal_zero_stock&action=tapped" \
     "$BASE/mark-insight-shown.php"
# EXPECTED: HTTP 403, {"ok":false,"err":"csrf"}
```

### Post-fix regression
```bash
curl -i -b /tmp/csrf_cookies.txt \
     -H "X-CSRF-Token: $TOKEN" \
     -d "topic_id=signal_zero_stock&action=tapped" \
     "$BASE/mark-insight-shown.php"
# EXPECTED: HTTP 200
```

---

## §11 — dev-exec.php

**N/A — file is QUARANTINED (`dev-exec.php.QUARANTINED`).**

### Confirm route is unreachable
```bash
curl -i "$BASE/dev-exec.php"
# EXPECTED: HTTP 404 (Apache/Nginx default for missing file)

curl -i "$BASE/dev-exec.php.QUARANTINED"
# EXPECTED: HTTP 200 with raw file content (because .QUARANTINED isn't in PHP handler list)
# → still leaks the file contents! See PATCH 11 §RECOMMENDATION for full removal.
```

If the second curl returns the PHP source, immediately add to `.htaccess` or nginx config:
```
location ~ \.QUARANTINED$ { deny all; }
```

---

## Smoke test summary (run after ALL patches applied)

```bash
#!/usr/bin/env bash
# Save as /tmp/csrf_fix/smoke_all.sh
set -e
BASE="${BASE:-https://app.runmystore.ai}"
COOK=/tmp/csrf_cookies.txt

echo "=== PHASE 1: Each endpoint REJECTS no-token POST ==="
for tuple in \
  "order.php?api=cancel|action=cancel&order_id=99999" \
  "products_fetch.php?ajax=add_supplier|supplier_name=PWN" \
  "delivery.php?api=commit|delivery_id=99999" \
  "defectives.php?api=write_off|supplier_id=99999" \
  "ai-studio-action.php|type=refund&parent_log_id=99999" \
  "ai-color-detect.php|" \
  "mark-insight-shown.php|topic_id=zzz&action=tapped" \
; do
    URL="${tuple%|*}"; DATA="${tuple#*|}"
    code=$(curl -s -o /dev/null -w '%{http_code}' -b $COOK -d "$DATA" "$BASE/$URL")
    if [ "$code" = "403" ]; then
        echo "  ✓ $URL → 403"
    else
        echo "  ✗ $URL → $code (expected 403)"
    fi
done

# JSON-body endpoint
code=$(curl -s -o /dev/null -w '%{http_code}' -b $COOK \
       -H 'Content-Type: application/json' -d '{}' "$BASE/chat-send.php")
[ "$code" = "403" ] && echo "  ✓ chat-send.php → 403" || echo "  ✗ chat-send.php → $code"

echo ""
echo "=== PHASE 2: Login form re-renders with error on bad token ==="
body=$(curl -s -d "email=x&password=x" "$BASE/login.php")
echo "$body" | grep -q 'Сесията изтече' && echo "  ✓ login rejects" || echo "  ✗ login accepted bad token"

echo ""
echo "=== Done. Manual UI smoke test (open chat.php in browser, exercise all flows) recommended. ==="
```

---

## Final acceptance criteria

- ☐ All 10 endpoints (excluding QUARANTINED dev-exec) return HTTP 403 on no-token POST.
- ☐ All 10 return HTTP 200 (or original behavior) WITH valid token header/field.
- ☐ Browser UI flows: chat send, AI scan, ai-color-detect, ai-studio retry,
     order create/cancel, delivery commit, defectives return — all functional.
- ☐ Login + register: forms display token field; submission with stale token shows
     "Сесията изтече" error; submission with fresh token succeeds.
- ☐ No JS console errors related to fetch/CSRF in DevTools across the 5 main flows.
