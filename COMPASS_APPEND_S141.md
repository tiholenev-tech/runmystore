# COMPASS APPEND — S141 (in progress)

**Дата:** 2026-05-12
**Сесия:** S141 = products.php redesign (SWAP strategy)
**Статус:** ⏸ PAUSED — продължава следваща сесия

---

## Кратко резюме

products.php redesign в ход. Стратегия = **SWAP** (както chat-v2 → chat в S140). Текущо завършен **shell** на `products-v2.php`. Очаква се Step 2 (P15 simple content) в следваща сесия.

## Главно откритие на S141

**design-kit/README.md казва "ЗАДЪЛЖИТЕЛНО импортирай 5 CSS файла". chat.php (canonical SWAP файл от S140) НЕ импортира НИЩО от design-kit/.**

Това обяснява всички CSS issues при предишни опити (S141 INJECT-ONLY провал — точки във фон, плосък дизайн, дублирани класове).

**Кодифицирано в:** `docs/MODULE_REDESIGN_PLAYBOOK_v1.md` (461 реда). Това е критичен документ за **всеки бъдещ чат** който работи върху модулен редизайн (sale, deliveries, orders, transfers, inventory, customers).

## Решения от Тих през S141

1. **P15 = canonical simple home** на products.php (не Bible §7.2.1 "Hybrid layout")
2. **Глобален "Инвентаризация nudge"** = нов закон за ВСЕКИ модул
3. **"Здраве на склада" → "Състояние на склада"** с breakdown (не плосък %)
4. **Detailed mode = ИСТИНСКИ разширен** — 17 идеи приети (sparklines, Парето, heatmap, donut, seasonality, ABC, multi-store, saved views, bulk actions, и т.н.)
5. **AI прогноза без числа** — qualitative само, всички числа от PHP queries (Закон №2)
6. **products redesign strategy: SWAP** (не INJECT-ONLY както S140 plan казваше)

## Backup tags активни

```
pre-S141-p15-home               (преди първи INJECT опит)
pre-S141-p15-simple-home        (използван за revert)
pre-products-v2-S141             (преди products-v2.php shell)
```

Emergency revert command:
```bash
cd /var/www/runmystore && git reset --hard pre-products-v2-S141 && git push origin main --force
```

## Файлове създадени през S141

| Файл | Размер | Статус |
|---|---|---|
| `docs/MODULE_REDESIGN_PLAYBOOK_v1.md` | 24 KB · 461 реда | ✅ Готов |
| `PRODUCTS_MASTER.md` | 96 KB · 2185 реда | ✅ Готов (16 секции) |
| `mockups/P2_v2_detailed_home.html` | 63 KB · 1853 реда | ✅ Готов |
| `products-v2.php` | 75 KB · 1380 реда | ⏳ Shell only (Step 1/6) |
| `daily_logs/DAILY_LOG_2026-05-12.md` | 5 KB | ✅ Готов |
| `COMPASS_APPEND_S141.md` | (този файл) | ✅ Готов |

## Файлове непокътнати (важно!)

- `products.php` — 14,074 реда — **НИЩО не е променено**. Стара визия, работи както винаги.
- `services/voice-tier2.php` — 333 реда — sacred, не пипано
- `ai-color-detect.php` — 296 реда — sacred, не пипано
- `js/capacitor-printer.js` — 2097 реда — sacred, не пипано
- `chat.php`, `life-board.php` — не пипани

## Pending за следваща сесия

### Step 2: P15 simple content в products-v2.php
- Replace placeholder в simple branch
- Add HTML от P15 mockup: тревоги (СВЪРШИЛИ + ЗАСТОЯЛИ 60+) → Добави артикул → AI поръчка → Help card (4 чипа + видео) → AI вижда 6 сигнала feed
- PHP queries: out_of_stock count, stale_60d count, AI insights from compute-insights.php

### Step 3: P2v2 detailed content (4 таба)
- Replace placeholder в detailed branch
- Add HTML от P2_v2_detailed_home.html mockup
- Tabs: Преглед / Графики / Управление / Артикули
- PHP queries за: stats by period (today/week/month/365), top sellers, by supplier, multi-store comparison, dead stock breakdown

### Step 4: Wizard extract
- Create `partials/products-wizard.php` от products.php wizard zone (ред ~7800-12900, ~5000 реда)
- 1:1 copy — НЕ модифициран (sacred: voice + color вътре)
- Include в products-v2.php: `<?php include 'partials/products-wizard.php'; ?>`

### Step 5: AJAX endpoints
- Copy от products.php в products-v2.php:
  - `?ajax=insights` — Life Board сигнали
  - `?ajax=storeStats` — Store stats
  - `?ajax=search` — Live search artikuli
  - `?ajax=load_products` — Pagination
- Add нови: `?ajax=alarms` (out_of_stock + stale_60d детайли)

### Step 6: SWAP
```bash
git tag pre-swap-S141
git mv products.php products.php.bak.S141
git mv products-v2.php products.php
git commit -m "S141 SWAP: products-v2 → production"
git push origin main
```

На droplet:
```bash
cd /var/www/runmystore && git pull origin main
```

## Известни конфликти за решение

1. **P2 mockup status** — Тих не реши дали P2_home_v2.html е canonical detailed home (PREBETA казва ДА) или legacy reference (HANDOFF казва НЕ). За момента ползваме P2_v2 (моят нов с 4 таба).
2. **Beta дата** — boot prompt explicitly казва игнорирай beta дати в memories. Тих ще реши кога е готова бетата (не 14-15 май както стари docs казват).

## Изводи (за бъдещи чатове)

1. **Не следвай design-kit/README.md буквално.** Следвай chat.php pattern (inline CSS, no imports). Виж `docs/MODULE_REDESIGN_PLAYBOOK_v1.md`.
2. **SWAP > INJECT-ONLY** за големи модули (>5000 реда). Чисто, изолирано, лесно за revert.
3. **Backup tag преди ВСЯКА голяма промяна.** Спасил си в S141 много време.
4. **Слушай критиката на Тих веднага.** Не защитавай решение — питай за яснота, действай.
5. **Минимум думи, максимум работа.** Тих е казал ясно: "не бъди многословен."

---

**Status:** S141 paused. products.php непокътнат. Готов за продължение в следваща сесия.
