# PROMPT за утрешния Claude Opus чат — S99 VOICE PROPER REWRITE

**Цел:** настройваме Whisper Tier 2 правилно, веднъж завинаги. Multi-language готов.

**Време:** 1 цял ден Code Code Opus, на свежа глава.

═══════════════════════════════════════════════════════════════

## КОНТЕКСТ

RunMyStore е складова SaaS. Beta launch 14-15.05.2026 (10 дни). Voice е критичен защото складова програма = много цифри (цени, бройки, баркодове). Беше пробвано Web Speech bg-BG → max 80% точност (4 patch цикли, всички capped). Беше пробвано Whisper два пъти набързо → halucinations + race conditions.

Сега искаме **правилен Whisper setup** — multi-language готов, 95%+ точност за BG, готов за RO/EL/SR/HR/MK expansion.

## АРХИТЕКТУРА (задължителни компоненти)

### 1. Voice Activity Detection (VAD)
- Auto-stop при тишина >1.5 sec
- RMS audio level threshold detect
- Без fixed timeout (5s беше грешка)
- Поведение: tap mic → recording стартира → user говори → пауза 1.5s → auto-stop → POST към Whisper

### 2. Pre-record buffer (200ms)
- Stream винаги active в background докато wizard е отворен
- Buffer rolling 200ms аудио
- При tap mic → буферът се приключва към recording → захваща началото на първата дума
- Решава: "едно/една/едно" се губи в Web Speech bg-BG

### 3. Sequential queue
- Само 1 активен Whisper request в момента
- Tap по време на pending request → cancel предишния, старти нов
- Решава race condition: "пет" в Цена → tap Брой → "пет" се появява в Брой instead

### 4. Locale-aware prompt context
config/voice-locales.json:
```json
{
  "bg": {
    "lang": "bg",
    "currency_words": ["лева", "лв", "стотинки", "стот"],
    "number_hints": "Числа на български: едно, две, три, петнайсе, двайсе, трийсе, петдесет, сто, сто двайсе и пет лева и петдесет стотинки.",
    "field_hints": {
      "retail_price": "Цена в лева. Например: пет лева, двайсе и пет, сто лева.",
      "quantity": "Брой. Цяло число от 1 до 999.",
      "barcode": "Баркод 8 или 13 цифри."
    },
    "parser": "_bgPrice"
  },
  "ro": {
    "lang": "ro",
    "currency_words": ["lei", "leu", "bani"],
    "number_hints": "Numere în română: unu, doi, trei, douăzeci și cinci de lei.",
    "field_hints": { "retail_price": "Preț în lei.", "quantity": "Cantitate." },
    "parser": "_roPrice"
  },
  "el": { ... },
  "sr": { ... },
  "hr": { ... },
  "mk": { ... }
}
```

### 5. Confidence threshold
- Whisper response има avg_logprob → confidence
- < 0.7 → toast "Не разпознах ясно, повтори" + не записва нищо
- 0.7-0.85 → жълт toast (warning) + записва
- > 0.85 → зелен toast (success) + записва

### 6. Post-process парсер per locale
- _bgPrice (existing) — extend за edge cases
- _roPrice (нов) — Romanian numerals + lei/bani
- _elPrice (нов) — Greek numerals + ευρώ/λεπτά
- All return null at fail → fallback chain

### 7. Fallback chain
1. Whisper Tier 2 (primary)
2. Web Speech (fallback ако Whisper fail/timeout)  
3. Numpad UI (если Web Speech also fail)
4. Toast "Въведи ръчно"

### 8. Multi-language config
- Settings → Voice Language dropdown
- Per-tenant setting в `tenants.voice_locale`
- Wizard reads locale → loads parser + hints
- Default 'bg' за нови tenants

## ИНТЕГРАЦИЯ

- services/voice-tier2.php (existing) — backend готов, supports 'hints' param
- products.php wizard — replace _wizMicWebSpeech за numeric branches с Whisper
- New file: js/voice-engine.js (Whisper client + VAD + buffer + queue)
- New file: services/voice-locales.json (config)
- Settings page edit за voice_locale dropdown

## SAFE MODE

- Phase 0: read-only inventory на existing voice code (wizMic, _wizMicApply, _bgPrice, _wizPriceParse, voice-tier2.php)
- Backup всички пипани файлове
- 5 commits разделени:
  1. Locale config + Romanian/Greek parsers
  2. VAD + pre-record buffer
  3. Sequential queue + Whisper client
  4. Wizard wire-up + fallback chain  
  5. Settings UI + tenant migration

- Browser test между всеки commit от Тихол
- Rollback готов на всеки етап

## DOD (browser test от Тихол)

BG accuracy targets:
- "пет лева" → 5.00 ✅
- "едно и петдесет" → 1.50 ✅ (днес fail)
- "двайсе и пет" → 25.00 ✅
- "сто двайсе и пет лева и петдесет стотинки" → 125.50 ✅
- "и двайсе" → toast "Кажи цялата цена" (low confidence) ✅
- "пет" в Брой → 5 ✅
- "петнайсе" в Брой → 15 ✅

Edge cases:
- Tap mic → не казва нищо 3s → toast "Не чух нищо" (без halucination) ✅
- Tap mic Цена → започва говорене → tap mic Брой → cancel + restart правилно ✅
- Network fail → fallback Web Speech без crash ✅

Multi-language smoke test (без реален RO/EL tenant):
- Switch Settings → Voice = Romana
- Tap mic → spoken Romanian numeral → правилно parsed (ако parser работи)

## БЮДЖЕТ

- LOC ceiling: ~600 общо (5 commits × ~120 LOC всеки)
- Time ceiling: 8 часа на свежа глава
- Cost: Whisper Groq ~$0.0001/sec → 100 tenants × 50 артикула/седмица × 5 numeric fields × 3s = $7.50/седмица = $30/месец → приемливо

## АКО ПРОТИВ ВСИЧКИ ОЧАКВАНИЯ ПАК НЕ СТАВА

Numpad fallback в wizard. Голям, видим до mic icon. Tap [+] [-] или цифри [1][2][3]...[9][.][0]. ~30 LOC, 1 час. Гарантира че Пешо ВИНАГИ може да въведе цифрата ръчно ако glas не работи.

