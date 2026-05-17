# 🛑 ФАЗА 2 — RECON ЗАВЪРШЕН, STOP ПРИ ФУНДАМЕНТАЛНА НЕЯСНОТА

**Branch:** `s148-cc-phase2-photo` (creat-нат, празен)
**Backup tag:** `pre-phase2-start-20260517_0722` (pushed)
**Дата:** 2026-05-17 07:22

═══════════════════════════════════════════════════════════════
## ✅ КАКВО ПРОЧЕТОХ (recon, без write)

| Файл | Редове | Защо |
|---|---|---|
| `WIZARD_v6_IMPLEMENTATION_HANDOFF.md` | 1-1152 (изцяло) | Контекст за фазите, AI schemas, DB migration |
| `services/voice-tier2.php` | 1-333 (изцяло) | Контракт на sacred STT endpoint |
| `services/price-ai.php` | 1-92 (изцяло) | Контракт на sacred BG price parser cloud-fallback |
| `ai-helper.php` | 197-224 (`callGeminiVision`) | Готов Gemini multimodal wrapper за ФАЗА 2b |
| `products.php` | 14341-14510 | `_wizMicWhisper`, `_wizPriceParse`, `_bgPrice`, `_wizMicApply`, `_wizPriceCloudFallback` |
| Apache vhost-ове | 4 файла | Localhost routing context за verify check #5 |
| Repo file inventory | — | Намерих `ai-color-detect.php` в root (не `services/`); `ai-helper.php` съществува |

**Sacred SHA check:** ✅ 5/5 непроменени.

═══════════════════════════════════════════════════════════════
## 🚨 ФУНДАМЕНТАЛНА НЕЯСНОТА В ЖЕЛЯЗНОТО ПРАВИЛО

Твоят iron rule казва:

> *"3. ИЗКЛЮЧЕНИЯ — НЕ копираш, викаш през bridge:
>    - _wizMicWhisper (ред 14341) → services/wizard-bridge.php?action=mic_whisper
>    - _wizPriceParse (ред 14458) → bridge?action=price_parse
>    - _bgPrice (ред 14418) → bridge?action=bg_price"*

**Но след прочитане — тези три функции са ВСИЧКИ pure JavaScript в products.php, не PHP endpoint-и.** PHP bridge не може директно да ги "извика".

### Какво всъщност прави всяка от тях:

**`_wizMicWhisper(field, lang)`** — ред 14341 (33 реда JS)
- Records audio via `MediaRecorder` (browser API)
- POSTs `multipart/form-data` към **`/services/voice-tier2.php`** (вече sacred PHP endpoint)
- Recv-ва transcript, callback-ва `_wizMicApply(field, text)`
- Fallback на Web Speech API ако recording/upload fail

**`_wizPriceParse(text)`** — ред 14458 (37 реда чисто-JS)
- Локален regex-based BG number parser ("четири и петдесет" → 4.50)
- Word→digit lookup table `_BG_WORD_NUMS`
- Cyrillic word boundary regex
- **Няма HTTP call.** Чист local computation.

**`_bgPrice(t, forcePrice)`** — ред 14418 (19 реда чисто-JS)
- По-стара/проста версия на `_wizPriceParse`, used от `_wizMicApply` за quantity/min_quantity
- Същата природа: чист local regex+lookup, no HTTP.

### Cloud fallback (вече съществува):
`/services/price-ai.php` (sacred) — Gemini-based parser, ползва се само когато local `_wizPriceParse` не разпознае (`_wizPriceCloudFallback` ред 14499).

═══════════════════════════════════════════════════════════════
## 🤔 ВЪПРОС — КОЯ ИНТЕРПРЕТАЦИЯ Е ВЯРНА?

### Интерпретация 1: Бридж само за PHP endpoint-и
`services/wizard-bridge.php` форвардва САМО към existing PHP sacred endpoints:
- `?action=mic_whisper` → forward POST към `/services/voice-tier2.php`
- `?action=price_parse_cloud` → forward POST към `/services/price-ai.php`
- `?action=color_detect` → forward POST към `/ai-color-detect.php`

За JS функциите (`_wizPriceParse`, `_bgPrice`, audio recording логиката от `_wizMicWhisper`):
- **Option 1A:** Wizard-v6.php е ОТДЕЛНА страница; пише собствена JS audio recording + posts to bridge. **Локалният BG parser се прави на 0 (PHP)** — всеки voice input отива на cloud (price-ai.php). По-бавно, по-скъпо, ама минимална JS дублирация.
- **Option 1B:** Extract `_wizPriceParse`/`_bgPrice` JS функциите в НОВ `js/wizard-shared.js`. И wizard-v6.php и products.php го load-ват. Но *products.php load-ва това* би означавало edit на sacred файл → нарушение на §2.2 от handoff-а.
- **Option 1C:** Wizard-v6.php inline-ва "copy" на `_wizPriceParse`/`_bgPrice` JS-а 1:1. Това **е copy** → нарушение на твоето iron rule.

### Интерпретация 2: Server-side reimplementation
`services/wizard-bridge.php` има 3 actions, всяка с PHP версия на JS логиката:
- `?action=mic_whisper` → forward to voice-tier2.php
- `?action=price_parse` → **НОВА PHP функция** `bg_price_parse_local()` която replicate-ва regex логиката от `_wizPriceParse` (37 реда), fallback на price-ai.php
- `?action=bg_price` → **НОВА PHP функция** която replicate-ва `_bgPrice` (19 реда)

Wizard-v6.php JS остава тривиален: всеки voice input → bridge → response. **Цена:** server roundtrip за всеки voice parse (~30-100ms добавено latency), и на teh PHP-страна имам "copy" на JS regex логика.

### Интерпретация 3: Premiss-ът е грешен
Бридж концепцията предполагаше тези са PHP функции (вероятно от недоразумение). Реалният сценарий: wizard-v6.php е standalone HTML/JS файл; не може да избегне inline JS за audio recording и local parsing. Тогава:
- Sacred PHP endpoints се извикват директно от wizard-v6.php JS (или през бридж за чист URL)
- JS local-parser код **трябва** да се дублира (като сме съгласни че duplication-ът е unavoidable за standalone file)

═══════════════════════════════════════════════════════════════
## 🎯 МОЯ ПРЕПОРЪКА

**Интерпретация 1 (clarified):**

`services/wizard-bridge.php` — НОВ файл, тънък router-only:
```
POST ?action=mic_whisper  → forward FormData към /services/voice-tier2.php
POST ?action=price_parse  → forward JSON към /services/price-ai.php (cloud-only)
POST ?action=color_detect → forward FormData към /ai-color-detect.php
POST ?action=ai_vision    → forward FormData към /services/ai-vision.php (нов в 2b)
POST ?action=ai_markup    → forward JSON към /services/ai-markup.php (нов в 2c)
```

За **local BG parser** в wizard-v6 JS:
- **Опция X (моят default):** Extract `_wizPriceParse` + `_bgPrice` в `js/wizard-parser.js` (НОВ файл, 60 реда JS, 1:1 копие от products.php редове 14418-14495). Wizard-v6.php го include-ва. `products.php` НЕ се менаs — той има inline копия там. → "Copy" но в нов файл, нямам sacred edit. Това *технически* е copy, ама единственото alternative е cloud-only (slow + €) или sacred edit (забранено).
- **Опция Y:** Cloud-only — wizard-v6 JS поста всеки voice input на bridge?action=price_parse (Gemini). Latency 600-1100ms на voice input. Cost ~€0.0001/call. Без local copy.

═══════════════════════════════════════════════════════════════
## 📋 ТРИ КОНКРЕТНИ ВЪПРОСА

**Q1.** Кое intent-но беше — Интерпретация 1, 2 или 3?

**Q2.** За JS local parser-а — Опция X (NEW js/wizard-parser.js с extract от products.php) или Опция Y (cloud-only през price-ai.php)?

**Q3.** За `?action=bg_price` action в bridge-а — да го имам ли изобщо? (`_bgPrice` е по-стара/проста версия на `_wizPriceParse`; на практика се ползват само в `_wizMicApply` за **quantity** и **min_quantity** полета, които са integer-only. Може да се замени с тривиален `parseInt(text.replace(/[^\d]/g,''))` за тези два use case-а.)

═══════════════════════════════════════════════════════════════
## 🔍 ВТОРОСТЕПЕННИ НЕЯСНОТИ (по-малки, ама свързани)

### Q4. DB таблици — `pricing_patterns` съществува ли?
Handoff §5.1 описва schema. Аз казах в предишен турн "ai_snapshots вече CREATED" но не съм проверил `pricing_patterns`. Без таблицата ai-markup.php (2c) ще fail-ва.

**Опции:**
- A: Аз пускам `mysql runmystore < migrations/...` (production write, иска одобрение)
- B: Ти потвърждаваш че таблицата вече съществува
- C: Аз правя ai-markup.php с graceful degrade — ако таблицата няма, връща global default 2.0 × .90

### Q5. Perceptual hash function за ai_snapshots cache (2b)
Handoff §5.2 ползва `perceptual_hash($image_data)` като черна кутия. Не съществува в codebase. Опции:
- A: `md5($image_data)` — точен match по identical файл (no actual perceptual matching, no de-dup по similar images)
- B: GD-based aHash (8×8 average hash, ~20 реда PHP) — perceptual но изисква GD extension
- C: Imagick-based pHash (по-точен ама зависимост)

Препоръка: **B** (aHash в pure PHP/GD, ~20 реда, no external deps). За beta това стига.

### Q6. wizard-v6.php JS architecture — глобална state machine?
Products.php има `S.wizData` глобален state (handoff §2.4 — sacred). Wizard-v6.php е отделна страница; не може да share-ва runtime state с products.php. Опции:
- A: Wizard-v6.php има собствен `S.wizData` (различен `S` обект, идентичен schema)
- B: Wizard-v6.php комуникира с products.php през postMessage/localStorage за state sync
- C: Wizard-v6.php е напълно standalone; на save POST-ва directly to products.php?ajax=save-product

Препоръка: **A + C** — собствен local S.wizData, при save POST до съществуващия sacred ajax endpoint в products.php.

═══════════════════════════════════════════════════════════════
## 🛑 КАКВО НЕ НАПРАВИХ (per "STOP при неяснота")

- ❌ Не написах `services/wizard-bridge.php` (Q1/Q2 определят shape-а)
- ❌ Не написах `services/ai-vision.php` (Q5 определя phash избор; също иска тестов път през DB)
- ❌ Не написах `services/ai-markup.php` (Q4 определя дали таблицата ще съществува)
- ❌ Не пипнах `wizard-v6.php` (sub-steps 2d-2g зависят от 2a-2c)

═══════════════════════════════════════════════════════════════
## ✅ КАКВО НАПРАВИХ

- Pull `main` (FF от ФАЗА 1.5 merge `56bc57e`)
- Create branch `s148-cc-phase2-photo` (празен, само recon)
- Create backup tag `pre-phase2-start-20260517_0722` + push
- Recon на всички референсни файлове изброени горе
- Sacred SHA verify — 5/5 OK

═══════════════════════════════════════════════════════════════
## 🎬 СЛЕДВАЩО ДЕЙСТВИЕ

**Чакам отговор на Q1, Q2, Q3** (минимално задължителни за 2a).
Q4, Q5, Q6 могат да се решат със стартирането на съответната sub-step ако bridge интерпретацията е изяснена.

Това е branch-only file — ще го pushна когато създам PR.
