# S79.VISUAL_REWRITE — HANDOFF

**Дата:** 23.04.2026
**Сесия:** CHAT 4 (Opus) — VISUAL_REWRITE
**Commit:** 44aafab (Update chat.php)
**Tag:** v0.5.5-s79-visual

## Свършено

**chat.php — пълен visual rewrite** (2094 реда, +489 спрямо v7):
- Neon Glass pattern (conic-gradient shine + glow, 4 слоя на карта)
- 3 × 75vh overlays (Chat/Signal Detail/Browser) с blur отдолу, same modern design
- Хардуерен back бутон (history.pushState + popstate), swipe down close, ESC
- Всички S79 patches запазени (insightAction 3-level, proactive top-strip, mark-insight-shown, fq-badge q1-q6)
- Revenue 4 periods + owner profit mode
- Weather 7-day forecast + fashion/non-fashion suggestion
- Voice SpeechRecognition bg-BG
- Ghost pills FREE/START

## Решени в сесията
- tenant 7 plan_effective=NULL → UPDATE всички на PRO trial до 2027
- ai_shown cooldown cleared за tenant 7
- Store dropdown работи (store_id=1 → 7 insights, store_id=47 → 8)

## Known limitations (pending)
- S79.SECURITY — exposed DB creds в git history (отложен)
- fq-badge не в briefing sig-card (по mockup)
- addToOrderDraft() е placeholder до S83 orders.php

## За S80 (следваща сесия — wizard rewrite)
Чете: MASTER_COMPASS + BIBLE CORE §1 + BIBLE TECH §2 + BIBLE APPENDIX §6 + DESIGN_SYSTEM + PRODUCTS_DESIGN_LOGIC + този handoff

НЕ пипай: chat.php, build-prompt.php, compute-insights.php, mark-insight-shown.php

---

## ⚠️ P0 BLOCKER discovered (23.04.2026)

**"AI е временно недостъпен" съобщение при chat** — backend API ключове проблем:

- **Gemini API Key #1:** HTTP 403 — "Your API key was reported as leaked" (Google автоматично блокиран)
- **Gemini API Key #2:** не тестван — вероятно също leaked
- **OpenAI API Key:** HTTP 401 — "Incorrect API key provided"

**Root cause:** `config/config.php` е в git репото с exposed credentials. Google скенерът е намерил ключовете публично и ги е маркирал като compromised.

**Impact:** chat-send.php fallback-ва към "AI е временно недостъпен" → chat-ът не отговаря на потребителски въпроси. Proactive pills + insight briefing работят (те са от PHP + SQL, не AI call), но свободният чат не работи.

**Fix (следваща сесия S79.SECURITY):**
1. Deactivate старите ключове в Google AI Studio + OpenAI dashboard
2. Генерирай нови ключове (Gemini × 2, OpenAI × 1)
3. Update `config/config.php` локално (без commit)
4. Add `config/config.php` към `.gitignore`
5. `git rm --cached config/config.php` + commit
6. Git history rewrite с `git filter-repo` или BFG Repo-Cleaner (премахва старите ключове от цялата история)
7. Force push → `git push origin main --force-with-lease`
8. Тествай chat с нов ключ → HTTP 200

**Временен workaround:** нищо — чат просто не работи до Стъпка 3.

