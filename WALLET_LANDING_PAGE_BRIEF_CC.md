# 🚀 WALLET LANDING PAGE BRIEF (за Claude Code Session B)

**Версия:** 1.0  
**Дата:** 17.05.2026 (S150 prep)  
**Engineer:** Claude Code в **отделен** tmux сесия (CC-B)  
**Цел:** Marketing landing page за RunMyWallet — за FB / TikTok / Google ads  
**Изпълнение:** Локално в droplet, БЕЗ commit/push в края (друг CC работи паралелно).

---

## ⚠️ КРИТИЧНИ ИНСТРУКЦИИ

```
1. ИЗОЛАЦИЯ ОТ CC-A
   CC-A работи върху /var/www/runmystore/wallet/migrations/
   ТИ работиш изключително върху /var/www/runmystore/wallet/landing/
   НЕ пипай /var/www/runmystore/wallet/migrations/ или DB schema
   
2. БЕЗ git commit или push
   Тихол ще ревю и commit-не ръчно

3. Mockups като референция
   Всички 10 mockups в /var/www/runmystore/mockups/P*_runmywallet_*.html
   Особено: P20_runmywallet_home.html (за screenshots в landing)
   
4. БЕЗ backend dependencies
   Landing е чист HTML/CSS/JS — НЕ ползва DB, НЕ ползва PHP backend
   Signup форма само collect-ва email → Mailchimp/ConvertKit waitlist endpoint
   (за бета — preliminary waitlist преди launch)
```

---

## 📁 СТРУКТУРА НА ФАЙЛОВЕТЕ

```
/var/www/runmystore/wallet/landing/
├── index.html              ← главната landing page (single-file, ~1500 реда)
├── style.css               ← optional split ако стане прекалено голяма
├── script.js               ← optional, за carousels / smooth scroll
├── assets/
│   ├── og-image.png        ← 1200×630 OG image за social shares
│   ├── favicon.png         ← 192×192
│   ├── screenshot-1.png    ← Home page mockup screenshot
│   ├── screenshot-2.png    ← Voice overlay screenshot
│   ├── screenshot-3.png    ← Analysis screenshot
│   ├── screenshot-4.png    ← Goals screenshot
│   └── demo-video.mp4      ← optional 30-sec screen recording
└── README.md               ← deploy + Apache config instructions
```

---

## 🎯 LANDING PAGE СТРУКТУРА (HERO → FOOTER)

```
1. NAV BAR (sticky)
   Logo • Функции • Цена • FAQ • [Започни безплатно] CTA

2. HERO SECTION
   H1: "Финансите ти с глас. Без писане."
   H2: "Гласов финансов помощник за самонаети — AI следи парите вместо теб"
   Hero image: 3 phone mockups side-by-side (Home + Voice + Analysis)
   [Започни 14 дни безплатно] primary CTA (purple gradient + conic spin)
   [Виж демо видео] secondary CTA с play icon
   Trust line: "14 дни безплатно · Без карта · GDPR · EU центрове"

3. PROBLEM-SOLUTION (3 cards)
   ❌ "Хабиш часове в Excel"           → ✅ "Кажи "Хляб 3 лева" — готово"
   ❌ "Не знаеш дали ще преминеш ДДС"   → ✅ "AI следи прага и алармира"
   ❌ "Счетоводителят ти прави сметки през март" → ✅ "Прогнозен данък винаги наличен"

4. FEATURES (6 cards — 2 cols × 3 rows)
   🎤 Гласово въвеждане       — "3 секунди, без писане"
   📸 AI чете касови бележки  — "Снимаш, AI извлича всичко"  
   📊 Анализ + прогнози       — "Месечни, годишни, сезонност"
   ⚠️ ДДС праг аларма         — "При 70% от прага"
   🎯 Цели и спестявания      — "Резерв, отпуска, инвестиции"
   🤖 Данъчни напомняния      — "Осигуровки, аванси, декларация"

5. HOW IT WORKS (3-step)
   Step 1: 📱 Изтегли apliкацията (App Store / Play Store)
   Step 2: 🗣 Кажи разход или приход
   Step 3: 📈 AI показва картината

6. SOCIAL PROOF / TESTIMONIALS
   3 testimonials (placeholders за beta launch):
   - Стефан, IT freelancer: "За 1 месец видях €420 в скрити абонаменти"
   - Митко, фризьор: "Знам ДДС прага си преди счетоводителят"
   - ENI, шивачка: "Всеки ден 5 минути с гласа — вместо 2 часа Excel"

7. PRICING (3 cards)
   FREE (€0/мес)
   - До 20 записа/месец
   - Само ручен вход
   - 1 цел
   
   START (€4.99/мес) ⭐ Препоръчан
   - Неограничени записи
   - Гласово въвеждане + Снимки
   - Прогнозен данък + ДДС праг
   - Неограничени цели
   - 14 дни безплатно
   
   PRO (€9.99/мес) Скоро
   - Всичко в START +
   - Multi-business splits
   - Auto-bank sync (банкови flow)
   - Експорт за счетоводител
   - Приоритетна поддръжка
   
   [Започни 14 дни безплатно] под START
   "Без карта изисквана · отказ по всяко време"

8. FAQ (10 въпроса с accordion)
   - Безплатно ли е?
   - Сигурни ли са моите данни?
   - Какво е GDPR съответствие?
   - Работи ли offline?
   - Мога ли да изтегля данните си?
   - Кои банки поддържате?
   - Какво е разликата с конкурентите (YNAB/Spendee)?
   - Има ли counted приложение за десктоп?
   - Какви езици се поддържат?
   - Как да отменя абонамента?

9. FINAL CTA (full-width gradient banner)
   "Започни да управляваш финансите си с глас"
   [Започни 14 дни безплатно] голям CTA
   "14 дни безплатно · Без карта"

10. FOOTER
    Logo + tagline
    Колони: 
      ПРОДУКТ (Функции, Цени, Roadmap, App Store, Play Store)
      КОМПАНИЯ (За нас, Контакти, Партньорство, Press)
      ПРАВНИ (Условия, Поверителност, GDPR, Бисквитки)
      СОЦИАЛНИ (FB, IG, X, LinkedIn, YouTube)
    Copyright: "© 2026 RunMyStore.AI · BG"
```

---

## 🎨 ДИЗАЙН СИСТЕМА (1:1 от mockups)

```
Шрифт: Montserrat ONLY (същият от mockups)
       weights: 400, 500, 700, 800, 900
       font-variant-numeric: tabular-nums за числа

Цветове:
  Primary accent:  hsl(255 80% 60%)         (van violet)
  Secondary:       hsl(280 70% 55%)         (magic violet)
  Gain green:      hsl(145 65% 50%)
  Loss red:        hsl(0 75% 55%)
  Amber:           hsl(38 90% 55%)
  Text:            #2d3748 (light) / #f1f5f9 (dark)
  BG light:        #e0e5ec
  BG dark:         radial gradients (виж mockup-ите)

Patterns reuse:
  ✓ Sacred Glass canon на feature cards (4 spans pattern)
  ✓ Aurora 4 blobs background (auroraDrift 22s)
  ✓ Conic spin на CTAs (5s linear infinite)
  ✓ Brand shimmer на logo (rmsBrandShimmer 4s linear)
  ✓ Neumorphic depth (--shadow-card / --shadow-pressed)

ИКОНКИ: ONLY SVG inline (никакви emoji в production)
        Източник: lucide.dev (същият като в mockups)

Mobile-first: 375-480px primary breakpoint
Desktop: 1024px+ enhanced layout (multi-column)
```

---

## 📸 SCREENSHOTS GENERATION

Тих ще предостави screenshots от mockup-ите. Засега използвай placeholder boxes:

```html
<div class="screenshot-placeholder">
  <!-- TODO: Replace with screenshot from /var/www/runmystore/mockups/P20_runmywallet_home.html -->
  <span>📱 Home Page</span>
</div>
```

Когато screenshots са готови, добавяме ги в `/assets/screenshot-N.png`.

---

## 📝 КОПИ (на български — кратки + ясни)

### HERO

```
H1 (50px desktop / 36px mobile, weight 900):
"Финансите ти с глас.
Без писане."

H2 (18px, weight 600, text-muted):
"Гласов финансов помощник за самонаети в България.
AI следи парите вместо теб."

CTA primary:
"Започни 14 дни безплатно →"

CTA secondary:
"▶ Виж демо (30 сек)"

Trust line (14px, weight 500):
"14 дни безплатно · Без кредитна карта · GDPR · EU центрове"
```

### PROBLEM-SOLUTION

```
Card 1:
❌ "Хабиш часове в Excel"
   "Записваш всичко на ръка, забравяш половината, накрая месечната справка отнема цял ден"

✅ "Кажи 'Хляб 3 лева' — готово"
   "AI разпознава гласа за 3 секунди. Категория, сума, дата — всичко автоматично."

Card 2:
❌ "Не знаеш дали ще преминеш ДДС"
   "Прагът е €51 130 годишно — но кой го следи постоянно?"

✅ "AI следи прага и алармира"
   "При 70% от прага получаваш предупреждение. Time да помислиш дали да се регистрираш."

Card 3:
❌ "Счетоводителят ти прави сметки през март"
   "Декларацията се прави в апрелски паник, защото няма яснота преди това"

✅ "Прогнозен данък винаги наличен"
   "Знаеш точно колко ще дължиш в края на годината. Без изненади през април."
```

### FEATURES (по 1 ред short copy)

```
🎤 "Гласово въвеждане за 3 секунди"
📸 "AI чете касови бележки автоматично"  
📊 "Месечни, годишни анализи + прогнози"
⚠️ "ДДС праг наблюдение и аларма"
🎯 "Цели и спестявания с автоматични проследявания"
🤖 "Данъчни напомняния — осигуровки, аванси, декларация"
```

### TESTIMONIALS (за beta)

```
"За 1 месец видях €420 в скрити абонаменти и спрях да губя пари."
— Стефан, IT freelancer

"Знам точно по ДДС прага си преди счетоводителят да ми каже."  
— Митко, фризьор

"Всеки ден 5 минути с гласа — вместо 2 часа Excel в края на месеца."
— ENI, шивачка
```

### PRICING

```
FREE (€0/мес)
  ✓ До 20 записа на месец
  ✓ Само ручен вход
  ✓ 1 цел
  ✗ Без AI разпознаване
  ✗ Без снимки
  ✗ Без данъчни функции
  [Започни]

START (€4.99/мес) ⭐ ПРЕПОРЪЧАН
  ✓ Неограничени записи
  ✓ Гласово въвеждане + Снимки
  ✓ Прогнозен данък + ДДС праг
  ✓ Неограничени цели
  ✓ Месечни и годишни справки
  ✓ EU центрове за данни
  [Започни 14 дни безплатно]
  
PRO (€9.99/мес) СКОРО
  ✓ Всичко в START +
  ✓ Multi-business splits
  ✓ Auto-bank синхронизация
  ✓ Експорт за счетоводител
  ✓ Приоритетна поддръжка
  [Уведоми ме]
```

### FAQ (примерни 5)

```
Q: Безплатно ли е RunMyWallet?
A: Можеш да започнеш безплатно с план FREE — до 20 записа на месец без AI функции.
   START план е €4.99/мес с 14 дни безплатен trial. Без кредитна карта за trial-а.

Q: Сигурни ли са моите финансови данни?
A: Да. Всички данни се пазят в EU дата центрове (DigitalOcean Frankfurt). 
   Шифровани в trans и at rest. GDPR съответен. Никога не споделяме данни с трети страни.
   Можеш да изтриеш акаунта си по всяко време — всичко се изтрива в 30 дни.

Q: Какво е разликата с YNAB или Spendee?
A: RunMyWallet е първият гласов wallet за БГ самонаети. 
   YNAB е за budget зеленчуци. Spendee е за разходи без бизнес.
   Ние имаме българско ДДС, НПР, осигуровки и счетоводни decimal-и вградени.

Q: Работи ли без интернет?
A: Гласовото въвеждане изисква интернет (Whisper AI на сървъра).
   Преглед на минали записи — да, работи offline (cache).
   Снимки на бележки — изисква интернет за AI parsing.

Q: Какво ако реша да отменя?
A: Settings → План → Отмени. Без въпроси, без задържания.
   Данните ти остават достъпни до края на платения период.
   След това можеш да ги изтеглиш в CSV/PDF или ще се изтрият автоматично.
```

---

## 📋 SIGNUP FORM (за beta waitlist)

Преди реален launch, signup-ът е waitlist. Email collect:

```html
<form id="waitlist-form" onsubmit="submitWaitlist(event)">
  <input type="email" name="email" required placeholder="твой@email.bg">
  <input type="text" name="profession" placeholder="Какво работиш? (опционално)">
  <button type="submit">Влез в waitlist-а →</button>
</form>

<script>
function submitWaitlist(e){
  e.preventDefault();
  const data = new FormData(e.target);
  // POST към /api/waitlist (TODO: create endpoint when DB е готов)
  // За сега: console.log + thank you message
  console.log({
    email: data.get('email'),
    profession: data.get('profession'),
    source: 'landing'
  });
  e.target.innerHTML = '<div class="thanks">✓ Записан си в waitlist. Ще те notify при beta launch (юни 2026).</div>';
}
</script>
```

**ВАЖНО:** Backend endpoint `/api/waitlist` ще се direct от CC-A (друга задача в Phase 2).  
Засега форма само console.log + thank you UI.

---

## 🎯 SEO META TAGS

```html
<head>
<meta charset="UTF-8">
<title>RunMyWallet — Гласов финансов помощник за самонаети | RunMyStore.AI</title>
<meta name="description" content="Първият гласов wallet за самонаети в България. AI следи финансите ти, ДДС прага и данъчните задължения. 14 дни безплатно.">

<!-- OG / Facebook -->
<meta property="og:type" content="website">
<meta property="og:title" content="RunMyWallet — Финансите ти с глас, без писане">
<meta property="og:description" content="Гласов финансов помощник за самонаети. AI следи парите вместо теб.">
<meta property="og:image" content="https://runmywallet.ai/assets/og-image.png">
<meta property="og:url" content="https://runmywallet.ai">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="RunMyWallet — Финансите ти с глас">
<meta name="twitter:description" content="Гласов финансов помощник за самонаети в БГ. AI track-ва ДДС, осигуровки, данъчни. 14 дни trial.">
<meta name="twitter:image" content="https://runmywallet.ai/assets/og-image.png">

<!-- Favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="/assets/favicon.png">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<!-- Schema.org structured data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "RunMyWallet",
  "applicationCategory": "FinanceApplication",
  "operatingSystem": "iOS, Android, Web",
  "offers": {
    "@type": "Offer",
    "price": "4.99",
    "priceCurrency": "EUR"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "ratingCount": "12"
  }
}
</script>
</head>
```

---

## 📊 ANALYTICS

```html
<!-- Plausible (privacy-respecting, EU-based) -->
<script defer data-domain="runmywallet.ai" src="https://plausible.io/js/plausible.js"></script>

<!-- Track key conversions -->
<script>
function trackEvent(name, props = {}) {
  if (window.plausible) plausible(name, {props});
}

// Usage:
// trackEvent('cta-click', {location: 'hero'});
// trackEvent('waitlist-signup', {profession: 'it_digital'});
</script>
```

---

## 📋 README.md

```markdown
# RunMyWallet Landing Page

## Deploy

cp -r landing/ /var/www/runmywallet.ai/public_html/

## Apache vhost (вече настроен от Тихол):

<VirtualHost *:443>
  ServerName runmywallet.ai
  DocumentRoot /var/www/runmywallet.ai/public_html
  ...
</VirtualHost>

## Test locally (преди deploy):

php -S 127.0.0.1:8000 -t landing/

Отваряй http://127.0.0.1:8000

## TODO след launch:

- Замени screenshot placeholders с real screenshots от mockup-ите
- Свържи waitlist форма с /api/waitlist endpoint (когато DB готов)
- Замени testimonials placeholders с real testimonials от beta users
- Добави demo video (screen recording от Capacitor app)
```

---

## ✅ CC-B CHECKLIST

```
[ ] 1. Виж mockup-ите в /var/www/runmystore/mockups/P*_runmywallet_*.html за дизайн референция
[ ] 2. Създай /var/www/runmystore/wallet/landing/ структурата
[ ] 3. index.html — single file (~1500-2000 реда)
       Mobile-first, responsive до 1440px desktop
       10 sections (NAV → HERO → PROBLEM/SOLUTION → ... → FOOTER)
[ ] 4. Използвай Sacred Glass canon + Aurora blobs от mockup-ите
[ ] 5. SEO meta tags + OG image placeholders
[ ] 6. Plausible analytics inline
[ ] 7. Waitlist form с console.log placeholder
[ ] 8. README.md с deploy инструкции
[ ] 9. ТЕСТВАЙ на mobile + desktop (Chrome DevTools resize)
[ ] 10. ТЕСТВАЙ light + dark theme toggle
[ ] 11. ТЕСТВАЙ всички CTA buttons и FAQ accordion
[ ] 12. НЕ commit, НЕ push — само файлове локално
[ ] 13. Кажи на Тихол "ГОТОВО — landing page готов в /var/www/runmystore/wallet/landing/"
```

---

## ⚠️ ИЗОЛАЦИЯ ОТ CC-A

Друг Claude Code работи **паралелно** върху:

```
/var/www/runmystore/wallet/migrations/     ← CC-A (НЕ ПИПАЙ)
/var/www/runmystore/wallet/docs/           ← CC-A (НЕ ПИПАЙ)
DB schema (MySQL)                          ← CC-A (НЕ ПИПАЙ)
```

Ти работиш изключително върху:

```
/var/www/runmystore/wallet/landing/         ← ТВОЯ ЗОНА
```

Двете задачи са НАПЪЛНО изолирани. Можете да работите паралелно без конфликт.

---

**END OF BRIEF v1.0** (готов за CC-B изпълнение)
