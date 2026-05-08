# Phase 1 — partials/ai-brain-pill.php Compliance Audit

**Session:** S114.AIBRAIN_AUDIT
**File audited:** `partials/ai-brain-pill.php` (105 LOC)
**Reference:** `DESIGN_SYSTEM.md` v4.1 BICHROMATIC + `design-kit/tokens.css`
**Mode:** Read-only. NO file modifications performed.

---

## 1. Automated check results — `design-kit/check-compliance.sh`

```
$ bash design-kit/check-compliance.sh partials/ai-brain-pill.php
→ Checking: partials/ai-brain-pill.php
  ⚠ WARN  partials/ai-brain-pill.php:23 [7.1-legacy-s87v3]
    Стар S87v3 animation class. v4.1 ползва :nth-child + fadeInUp directly.
  ⚠ WARN  partials/ai-brain-pill.php:37 [7.1-legacy-s87v3]
    Стар S87v3 animation class. v4.1 ползва :nth-child + fadeInUp directly.
  ⚠ PASS with warnings — 0 errors, 2 warnings
```

**Verdict (script-level):** PASS with warnings. The script does NOT catch the deeper violations the brief asks me to flag, because:
- Rule 1.1 only matches `#hex` patterns — pill uses `hsl(...)` literals.
- Rule 1.3 (shadow) regex requires `rgba|hsla|#` after the second px — pill uses `hsl(...)` (no `a`), so no match.
- Pill is a partial, so rules 3.2 / 3.4 (data-theme presence) do not gate it.

A manual audit per Bible v4.1 surfaces several real violations summarized below.

---

## 2. Manual audit — Bible v4.1 violations

### 2.1 Hardcoded `hsl(...)` color literals (28 occurrences)

```
:60   --hue1:280;--hue2:310;
:63   border:1px solid hsl(280 50% 35% / .55);
:70   stroke:hsl(290 90% 80%);
:72   filter:drop-shadow(0 0 8px hsl(290 90% 60% / .75));
:77   color:hsl(290 95% 92%);
:78   text-shadow:0 0 10px hsl(290 85% 60% / .5);
:81   color:hsl(290 65% 75%);
:91   background:linear-gradient(135deg,hsl(280 65% 45%),hsl(310 65% 38%));
:92   border:1px solid hsl(290 70% 60% / .55);
:93   box-shadow:0 6px 18px hsl(290 70% 50% / .45),inset 0 1px 0 rgba(255,255,255,0.12);
:101  filter:drop-shadow(0 0 6px hsl(290 90% 80% / .55));
```

**Why this fails Bible v4.1:**
- Bible §1.1 + tokens.css define palette via `--hue1` / `--hue2` and q-tokens. AI Brain owns the **magenta** band per Bible §4.5 (hue 280/310 ✅ correct band). But absolute literals like `hsl(290 95% 92%)` cannot be overridden by `[data-theme="light"]` — they will look identical in dark and light, breaking Bichromatic.
- The `--hue1:280;--hue2:310;` redefinition is **scoped to `.aibrain-pill`** and locally overrides global `--hue1:255` / `--hue2:222`. This is fine for the magenta accent, but every downstream value should derive from these via `hsl(var(--hue1) ...)` rather than naming `280` again.

**Fix sketch (proposal — DO NOT APPLY):**

```css
.aibrain-pill{
  --hue1:280; --hue2:310;       /* magenta accent band per Bible §4.5 */
  --aibq-line:hsl(var(--hue1) 50% 35% / .55);
  --aibq-text:hsl(var(--hue1) 95% 92%);
  --aibq-glow:hsl(var(--hue1) 85% 60% / .5);
  border:1px solid var(--aibq-line);
  /* ...all downstream uses reference these */
}
[data-theme="light"] .aibrain-pill{
  --aibq-line:hsl(var(--hue1) 60% 60% / .55);
  --aibq-text:hsl(var(--hue1) 80% 28%);
  --aibq-glow:hsl(var(--hue1) 75% 50% / .35);
}
```

### 2.2 Hardcoded `box-shadow` (line 93)

```
box-shadow:0 6px 18px hsl(290 70% 50% / .45),inset 0 1px 0 rgba(255,255,255,0.12);
```

**Violates** Bible §3 — every shadow should reference `var(--shadow-card)`, `var(--shadow-card-sm)`, or `var(--shadow-pressed)` (per check-compliance.sh rule 1.3 spirit, even if its regex misses). The mini-FAB needs a token-based shadow that re-skins under `[data-theme="light"]`.

**Fix sketch:** Define `--shadow-aibrain` in `tokens.css` (dark + light variants), reference it from `.aibrain-fab`.

### 2.3 No `[data-theme="light"]` branch — Bible §3.4

The pill stylesheet has **zero** `[data-theme="light"]` rules. In light mode:
- Magenta text on the still-magenta gradient background loses contrast (text becomes `hsl(290 95% 92%)` on a near-white card).
- The neon `drop-shadow` and `text-shadow` glows look wrong on white surfaces.
- mini-FAB gradient `linear-gradient(135deg,hsl(280 65% 45%),hsl(310 65% 38%))` reads almost black on white background — wrong contrast direction.

**Fix sketch:** Add a `[data-theme="light"] .aibrain-pill { ... }` block that lowers neon glow opacity, raises text lightness target (Bible §3 says light mode replaces `plus-lighter` with `multiply` and dims glows by ~50%).

### 2.4 Effect #9 — Iridescent shimmer NOT applied (Bible §6.9)

Bible §890 prescribes a **double-layer shimmer** on AI Brain pill:
- Layer 1: `::before` rotating conic-gradient (`conicSpin 4s linear infinite`)
- Layer 2: `::after` sweeping linear gradient (`shimmerSlide 3.5s ease-in-out infinite`)

The current pill has only `.shine` + `.glow` spans (which are Bible Effect #1, the .glass shine — different). The signature AI Brain shimmer is missing.

**Fix sketch (DO NOT APPLY):**

```css
.aibrain-pill{position:relative;overflow:hidden}
.aibrain-pill::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:conic-gradient(from 0deg,transparent,rgba(255,255,255,.25),transparent);
  animation:conicSpin 4s linear infinite;
  z-index:0;
}
.aibrain-pill::after{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(105deg,transparent 30%,rgba(255,255,255,.35) 50%,transparent 70%);
  animation:shimmerSlide 3.5s ease-in-out infinite;
  z-index:0;
}
.aibrain-pill > *{position:relative;z-index:1}  /* already in pill, line 66 */
```

Both keyframes already exist globally (DESIGN_SYSTEM.md §922 + §940), so **no new keyframes needed** — just wire the layers.

### 2.5 Legacy `s87v3-tap` class (script flagged — lines 23, 37)

`s87v3-tap` is the v3 tap-feedback class. Bible v4.1 replaced it with native `:active` transforms + `:nth-child + fadeInUp`. Two occurrences:
- `:23` on `aibrain-fab`
- `:37` on `aibrain-pill`

**Fix sketch:** Remove the class; rely on Bible v4.1 default `:active{transform:scale(.97)}` from base.css.

### 2.6 Hardcoded radii / dimensions

The pill uses raw `px` for all dimensions (`width:42px;height:42px;border-radius:50%` in `.aibrain-fab`). For the FAB, `border-radius:50%` is correct (it's a circle, not a token), but the pill itself relies on `.glass` for radius — which is fine. **No violation here**, just noting.

`min-width:200px` and `min-height:44px` on `.aibrain-pill` (line 64) — 44px is the Bible-mandated tap target, but should reference `var(--tap-target)` if such token exists. Currently `tokens.css` does **not** define `--tap-target`; this is a tokens.css gap, not a pill bug.

### 2.7 i18n & namespace — clean

- Strings via `t_aibrain('pill.aria')`, `t_aibrain('pill.label')`, `t_aibrain('pill.sub')` ✅
- No hardcoded BG strings ✅
- No vendor leak (Gemini/Claude/etc.) ✅
- CSS class names scoped `.aibrain-*` — no collisions with `.rec-ov` (sale.php) ✅

---

## 2.8 🚨 CRITICAL — Class-name mismatch: Bible-compliant CSS is orphaned

`life-board.php:1022-1080` already contains a fully-compliant `.ai-brain-pill` stylesheet:
- Uses `var(--hue1)` / `var(--hue2)` ✅
- Has `[data-theme="light"]` branch with `var(--accent)` ✅
- Implements Effect #9 — `::before` conic + `::after` shimmer ✅
- Token-based shadow `var(--shadow-card)` ✅

**But the actual pill renders with class `aibrain-pill` (no hyphen)** — see `partials/ai-brain-pill.php:37`:
```php
class="glass sm aibrain-pill s87v3-tap"
```

So `life-board.php` lines 1022-1080 are **dead CSS** — nothing in the rendered DOM matches `.ai-brain-pill` (the hyphenated form). The 105 LOC of non-compliant `.aibrain-pill` rules in the partial WIN by virtue of being the only CSS that matches the rendered class name.

**Impact:**
- `partials/ai-brain-pill.php` line 41-42 still emit the legacy `<span class="shine">` / `<span class="glow">` quartet (which are Bible Effect #1, the .glass shine — unrelated to the AI Brain Effect #9 shimmer that the orphaned life-board CSS would have provided).
- Visual identity of the AI Brain pill is **not** what Bible §6.9 prescribes. It looks like a generic .glass pill with magenta hue tweaks.

**Recommended fix path:**
- **Option A (preferred):** rename `.aibrain-pill` → `.ai-brain-pill` (+ children: `aibrain-pill-row`, `aibrain-pill-icon`, etc.) in the partial, then DELETE the duplicated CSS block from `partials/ai-brain-pill.php` and rely on the existing compliant block in `life-board.php`. Net: −80 LOC of CSS, gains Effect #9.
- **Option B:** keep `.aibrain-*` namespace but copy the Bible-compliant CSS into the partial. More LOC but isolated namespace; safer if someone later adds the pill outside life-board (e.g. mini-FAB in modules per Phase 3).
- Mini-FAB (`.aibrain-fab`) is genuinely standalone and stays — but should still gain `[data-theme="light"]` branch + tokens.

**Recommendation:** Option B for clarity, plus DELETE the orphaned `.ai-brain-pill` block in life-board.php once partial-side compliance lands. Pure rename (Option A) saves more LOC but couples the partial to life-board CSS — bad for Phase 3 where partial gets included in sale.php / products.php / deliveries.php.

---

## 3. Summary

| Severity | Count | Items |
|---|---|---|
| ❌ **ERROR** | 0 | Script: 0 errors |
| ⚠ **WARN** (script) | 2 | `s87v3-tap` ×2 |
| 🟡 **Bible v4.1 manual** | 5 | Hardcoded hsl literals (28×), hardcoded box-shadow, missing `[data-theme="light"]` branch, missing Effect #9 shimmer, legacy s87v3 class |
| 🚨 **CRITICAL** | 1 | Class-name mismatch — Bible-compliant `.ai-brain-pill` CSS in life-board.php is orphan dead-code; partial renders `.aibrain-pill` (no hyphen) with non-compliant rules |

**Net:** Pill is **functional** but **does not yet implement Bible v4.1 Bichromatic** for AI Brain. None of these changes are implemented in this audit. Recommended target session: **S115.AIBRAIN_PILL_MIGRATE** (after S113 lands to avoid rebase conflicts).

**Recommended migration order:**
1. Add `--aibq-*` tokens + light-theme branch to `tokens.css` (additive, zero risk).
2. Refactor pill CSS to reference tokens (purely visual, no JS change).
3. Wire Effect #9 shimmer (`::before` + `::after`).
4. Remove `s87v3-tap` class.
5. Manual visual diff in dark + light mode before commit.

**Estimated effort:** ~45 min implementation + 15 min visual review.
