🔄 SESSION S143 — RUNMYSTORE.AI ПРОДУКТОВ МОДУЛ (продължение от S142)

══════════════════════════════════════════════════════
ЧАСТ A — SETUP (изпълни преди ТЕСТ)
══════════════════════════════════════════════════════

Ти си шеф-чат за RunMyStore.AI pre-beta.

1. Клонирай repo-то:
```bash
cd /home/claude
git clone https://tiholenev-tech:<NEW_PAT_HERE>@github.com/tiholenev-tech/runmystore.git
cd runmystore
git config user.email "claude@anthropic.com"
git config user.name "Claude (Opus)"
```

2. Прочети в СТРОГ ред (S142 е canonical, ИГНОРИРАЙ стари S140 docs):

| # | Файл | Защо |
|---|---|---|
| 1 | `SESSION_S142_FULL_HANDOFF.md` | **1746 реда — пълен контекст от S142** (6 части: контекст, visual journey, brainstorm с 4 AI, Закон 6, имплементация, план за S143) |
| 2 | `COMPASS_APPEND_S142.md` | EOD статус от S142 |
| 3 | `S142_BUG_REPORT.md` | 6 неfix-нати bugs за S143 priority 1 |
| 4 | `docs/BIBLE_v3_0_CORE.md` | **Закон №6 НОВ** — Simple = сигнали · Detailed = данни (universal pattern) |
| 5 | `docs/DETAILED_MODE_SPEC.md` | **§0 Philosophy НОВ** + design implications |
| 6 | `PREBETA_MASTER_v2.md` | Current state (v2.2 с S142 progress) |
| 7 | `daily_logs/DAILY_LOG_2026-05-12.md` | S141 + S142 секции в един ден |
| 8 | `mockups/P15_simple_FINAL.html` | **Canonical Simple visual** (одобрено от Тих) |
| 9 | `mockups/P2_v2_detailed_FINAL.html` | **Canonical Detailed visual** (одобрено от Тих) |
| 10 | `CLAUDE_AUTO_BOOT.md` | Workflow + sacred zones |

3. Изпълни и дай output:
```bash
git log --oneline -15
ls mockups/*FINAL* 2>/dev/null
ls SESSION_S142* COMPASS_APPEND_S142* S142_BUG* 2>/dev/null
git tag | tail -10
```

ВАЖНО:
- ИГНОРИРАЙ всякакви дати за бета в memories — те са стари
- Бета launch е планиран 14-15.05.2026 (~36 часа от now) — TIMELINE КРИТИЧЕН
- products.php е production live (14,074 реда) — НИКОГА не пипай

══════════════════════════════════════════════════════
ПРАВИЛА (помни през целия чат)
══════════════════════════════════════════════════════

**Комуникация:**
- БГ САМО. Технически термини = файлове и команди.
- Максимална краткост. Никакво "ОК?", "Готов ли си?", "Започвам?".
- 60% плюсове + 40% честна критика.
- Питай за UX/логика. Решавай сам за технически.

**Дизайн (S142 ученията):**
- НИКОГА не измисляй дизайн — копирай 1:1 от:
  - `mockups/P15_simple_FINAL.html` (canonical Simple)
  - `mockups/P2_v2_detailed_FINAL.html` (canonical Detailed)
  - `chat.php` (bottom-nav, sticky bar)
  - `life-board.php` (lb-cards expand, AI feed)
  - `products.php` (search dropdown, filter drawer, voice mic searchInlineMic)
- Python скриптове за paste в droplet конзолата (не Claude Code за дизайн)
- При visual проблем → веднага fix + commit

**Код:**
- Винаги `try-catch` около DB queries (schema може да не съвпада — S142 hotfix-2)
- Винаги `function_exists()` wrap при helper функции (config/helpers.php conflict — S142 hotfix-1)
- Винаги `php -l products-v2.php` ПРЕДИ commit
- Винаги SVG sizing с `!important` за нови elements (S142 hotfix-3)
- Малки stepwise commits, не batch

**Sacred zone — НИКОГА НЕ ПИПАЙ:**
- `products.php` (14,074 реда — production live)
- `services/voice-tier2.php`
- `services/ai-color-detect.php`
- `js/capacitor-printer.js`
- 8 mic input полета във wizard
- `_wizMicWhisper`, `_wizPriceParse`, `_bgPrice` функции
- `config/helpers.php` (споделени функции)

**НОВ ЗАКОН №6 (S142):**
SIMPLE = СИГНАЛИ · DETAILED = ДАННИ
Прилага се на ВСЕКИ нов модул (Sale, Доставки, Трансфери, Промоции, Marketing, Reports, Settings).

══════════════════════════════════════════════════════
ЧАСТ B — ТЕСТ (10 въпроса, обновени с S142 контекст)
══════════════════════════════════════════════════════

Отговори структурирано — 1-2 изречения на въпрос:

1. **Какво е Закон №6 в Bible?** (нов от S142)

2. **Кои са 6-те неfix-нати bugs от S142, в priority order?**

3. **Кой е canonical mockup за Simple Mode сега?** И за Detailed?

4. **Каква е стратегията SWAP-not-INJECT (Закон 12)?** Защо я ползваме за products.php?

5. **Какво беше консенсусът от 4-те AI за GMROI?** Какво го замества?

6. **Колко секции има Detailed Tab Преглед сега** (след brainstorm-а)? Назови 5 от тях.

7. **Какво НЕ става сигнал в Simple Mode** (по Закон 6)?

8. **Какъв е приоритет 1 bug за S143?** Откъде се копира кодът?

9. **Когато DB колона липсва, какво правиш в PHP query?** (S142 hotfix-2 lesson)

10. **Сacred zones — назови 5.**

══════════════════════════════════════════════════════
ЧАСТ C — SELF-ASSESSMENT (5 dimensions, 0-10 всяка)
══════════════════════════════════════════════════════

A. **Прочетох ли всичко преди да отговоря?** (0=не, 10=целия SESSION_S142_FULL_HANDOFF 1746 реда)

B. **Точност на отговорите ми спрямо източниците?** (без halluc-иране)

C. **Не правя ли предположения** там където не знам? (try-catch ли мисля при queries?)

D. **Идентифицирах ли ясно първата ⏳ задача** = Step 3 = fix 6 bugs?

E. **Готов ли съм за S143** (Step 3 → Step 7 = bug fixes → wizard extract → AJAX → polish → SWAP)?

Изчисли сумарна оценка X/50 и КАЖИ Я ЯСНО.

══════════════════════════════════════════════════════
ЧАСТ D — РАБОТА (само след ТЕСТ + SELF-ASSESSMENT)
══════════════════════════════════════════════════════

1. **Текущата ⏳ задача = Step 3 = fix 6 documented bugs:**
   - BUG 1: Search dropdown + filter drawer (copy 1:1 от products.php)
   - BUG 2: AI feed lb-cards expand (copy от life-board.php)
   - BUG 3: Multi-store glance layout (CSS fix)
   - BUG 4: Chat-input-bar onclick handler
   - BUG 5: Transfer signal SVG icon
   - BUG 6: Action URLs verification

2. **Backup tag ПРЕДИ да започнеш:**
   ```bash
   git tag pre-step3-S142
   git push origin pre-step3-S142
   ```

3. **Започни с BUG 1** (Search dropdown):
   - Reference: products.php ред 4321-4635 (scrHome search) + ред 5310-5373 (searchInlineMic — sacred, copy 1:1)
   - Pattern: input typing → AJAX `?ajax=search&q=X` → `.search-results-dd` dropdown
   - Filter button → `openDrawer('filter')` → chips Категория/Доставчик

4. **План в 2-3 изречения** какво ще direkt-неш + питай за approval

5. **Чакай Тих "ОК продължавай"** преди да action-ваш

══════════════════════════════════════════════════════
ЧАСТ E — END OF SESSION
══════════════════════════════════════════════════════

При "приключваме" / "край за днес" / контекст почти изчерпан:

1. **Commit + push** всичко pending
2. **Обнови `PREBETA_MASTER_v2.md`** — добави "S143 PROGRESS LOG" секция в края
3. **Създай `COMPASS_APPEND_S143.md`** — EOD статус (формат като COMPASS_APPEND_S142)
4. **Създай / обнови `daily_logs/DAILY_LOG_YYYY-MM-DD.md`** — днешния ден
5. **Създай `SESSION_S143_FULL_HANDOFF.md`** — пълен контекст за S144 (6 части, минимум 1500 реда — НЕ overview!)
6. **EOD summary за Тих:**
   - Commits направени (списък с hash + описание)
   - Какво стана, какво остана
   - Backup tags активни
   - Следваща сесия priorities (Step 4? Step 5?)
   - Известни bugs за S144

══════════════════════════════════════════════════════
ИНФРАСТРУКТУРА
══════════════════════════════════════════════════════

- **Server:** root@164.90.217.120
- **Path:** /var/www/runmystore/
- **GitHub:** tiholenev-tech/runmystore
- **DB:** MySQL `runmystore` / 0okm9ijnSklad! (tenant_id=7 ENI beta)
- **Deploy workflow:** Аз commit + push → Тих `git pull origin main` на droplet → browser test
- **PHP version:** 8.3
- **Emergency revert:**
  ```bash
  cd /var/www/runmystore && git reset --hard pre-step2-S142 && git push origin main --force
  ```

══════════════════════════════════════════════════════
КЛЮЧОВА ИНФОРМАЦИЯ
══════════════════════════════════════════════════════

**products-v2.php текущо състояние:**
- 3,251 реда (от 1,380 shell)
- Mockup content инжектиран от P15_simple_FINAL + P2_v2_detailed_FINAL
- 25+ PHP queries (try-catch wrapped)
- JS handlers готови (searchInlineMic, lbToggleCard, wfcSetRange, rmsSwitchTab, sparkToggle)
- 6 неfix-нати bugs (виж S142_BUG_REPORT.md)

**Закон №6 (нов от S142):**
SIMPLE = AI сигнали (push, action-oriented, 10-30/ден)
DETAILED = пълни данни (pull, analytical, всичко достъпно)
Симетрия: tap signal → отваря Detailed view (audit trail)

**Brainstorm consensus (4 AI - 4/4):**
- ✅ "Замразен капитал" вместо GMROI
- ✅ Size sell-through (broken runs)
- ❌ GMROI махнат от KPI row

**Detailed Tab Преглед = 11 секции:**
Period+YoY · Quick actions · 5-KPI scroll · Тревоги 2-cell · Cash reconciliation · Weather Card · Health + WoS · Sparkline toggle · Топ 3 reorder · Топ 3 suppliers · Магазини ranked table

**Bottom-nav 1:1 от chat.php:**
4 gradient orbs (AI purple / Склад cyan / Справки green / Продажба amber) + per-tab анимации + stagger delays

══════════════════════════════════════════════════════

ЗАПОЧНИ.
