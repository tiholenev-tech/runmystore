# 📘 DOC 07 — POWER SAVE + WORKFLOW ЗАЩИТИ

## Батерия, voice UX, 10 ежедневни сценария

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 3

---

## 📑 СЪДЪРЖАНИЕ

1. Защо power save е critical
2. 3-нивова battery policy
3. Camera battery drain
4. Voice loop UX
5. 10 ежедневни workflow сценария
6. Workflow защити

---

# 1. ЗАЩО POWER SAVE Е CRITICAL

Пешо използва RunMyStore през деня на телефон. iPhone SE или среден Android. Батерия 2,500-3,000 mAh. В 15:00 телефонът е на 20%. Ако RunMyStore го изцеди до 10% в 16:00 — Пешо изгубва доверие.

**Правило:** RunMyStore не тегли > 5% батерия на час при активна употреба.

---

# 2. 3-НИВОВА BATTERY POLICY

## 2.1 Detection

```javascript
navigator.getBattery().then(battery => {
    function updateBatteryUI() {
        const level = battery.level * 100;

        if (level > 40) {
            document.body.classList.remove('battery-low', 'battery-critical');
            enableAllFeatures();
        } else if (level > 15) {
            document.body.classList.add('battery-low');
            reduceNonEssentials();
        } else {
            document.body.classList.add('battery-critical');
            emergencyMode();
        }
    }

    battery.addEventListener('levelchange', updateBatteryUI);
    updateBatteryUI();
});
```

## 2.2 Ниво 1 (>40%) — Normal

- Всички features активни
- Camera auto-start в sale.php
- Live voice transcription
- Animation на pills

## 2.3 Ниво 2 (15-40%) — Low

- Camera не стартира автоматично (ръчно tap)
- Animations off
- Refresh interval: 5 min вместо 30 sec
- Push notifications свалени приоритет

## 2.4 Ниво 3 (<15%) — Critical

- Camera disabled напълно
- Voice disabled (user може да тапне force enable)
- Само cached data
- Background sync off
- AI chat disabled — само pills/signals от cache
- Голям warning banner: „Батерия критична. Заредете телефона."

---

# 3. CAMERA BATTERY DRAIN

## 3.1 Проблемът

`getUserMedia({video: true})` в sale.php continuously scanning за barcodes. Това е 5-10% батерия на час.

## 3.2 Решение

```javascript
let cameraStream = null;
let cameraActive = false;

function startCamera() {
    if (cameraActive) return;
    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: 'environment',
            width: { ideal: 1280 },
            height: { ideal: 720 }
        }
    }).then(stream => {
        cameraStream = stream;
        cameraActive = true;
        document.getElementById('camera').srcObject = stream;
    });
}

function stopCamera() {
    if (!cameraActive) return;
    cameraStream.getTracks().forEach(track => track.stop());
    cameraStream = null;
    cameraActive = false;
}

// Auto-stop след 30 сек inactivity
let inactivityTimer;
function resetInactivity() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(stopCamera, 30000);
}

// Stop при tab change
document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopCamera();
});

// Stop на critical battery
if (document.body.classList.contains('battery-critical')) {
    stopCamera();
}
```

---

# 4. VOICE LOOP UX

## 4.1 Проблемът

Voice винаги отворен = microphone always on = battery drain + privacy.

## 4.2 Решение

**Push-to-talk**, не continuous listening.

```javascript
const voiceBtn = document.getElementById('voice-fab');
let recognition = null;

voiceBtn.addEventListener('mousedown', startListening);
voiceBtn.addEventListener('touchstart', startListening);
voiceBtn.addEventListener('mouseup', stopListening);
voiceBtn.addEventListener('touchend', stopListening);

function startListening() {
    recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
    recognition.lang = tenantLang;
    recognition.continuous = false;
    recognition.interimResults = true;
    recognition.onresult = handleTranscript;
    recognition.start();
    showRecordingIndicator();
}

function stopListening() {
    if (recognition) {
        recognition.stop();
        recognition = null;
    }
    hideRecordingIndicator();
}
```

**Изключение:** onboarding voice flow — там user изчитам няколко отговора подред, continuous listening е ок.

---

# 5. 10 ЕЖЕДНЕВНИ WORKFLOW СЦЕНАРИИ

## 5.1 Сценарий 1: Грешен бутон при бързане

Пешо бърза. Иска „Продажба", тапа „Поръчка".

**Защита:**
- Back бутон винаги видим
- AI detect: „Понякога тапваш Поръчка вместо Продажба. Да ги преместя?"
- Undo action винаги в последните 30 сек

## 5.2 Сценарий 2: Voice грешка в шум

Пешо казва „две черни тениски", AI чува „двадесет черни тениски".

**Защита:**
- Под-закон №1A (показване на транскрипция)
- Confidence < 0.8 → warning yellow
- Fuzzy match на числа (2 ≠ 20 — warning)
- **Sanity check на общата сума (Kimi):** ако количество × цена е нереалистично
  (напр. 20 × 40 лв = 800 лв за една артикулна линия) →
  AI пита: "Сигурен ли си? Това е 20 броя × 40 лв = 800 лв."
- Threshold: ако total > 10× средна продажба на tenant → задължителен confirm

## 5.3 Сценарий 3: Бързащ клиент на касата

Клиент бърза. Пешо не иска да чака AI да изчисли.

**Защита (стандартни):**
- Sale е чист SQL — 100ms response
- AI toast е async — появява се **след** продажбата, не блокира
- При battery-low — AI toast изобщо не се показва

**Quick Sale режим (Kimi):**
- **Double tap на [Продай]** = мигновена продажба на последния артикул с последна цена
  - Полезно при repeat клиенти (купува същото)
  - Записва се в `sale_type='quick_repeat'` за аудит
- **Barcode scan = auto-add в кошница** без потвърждение
  - Scan → веднага в кошница → следващ scan → следващ scan → [Плати] накрая
- **Swipe to confirm продажба** (алтернатива на tap)
  - Плъзгаш наляво = отказ, надясно = потвърждение

## 5.4 Сценарий 4: Стари данни (offline)

Телефонът е offline от 2 часа. Пешо не знае.

**Защита:**
- Offline detection → banner „Работиш офлайн"
- Sales работят, qeued за sync
- Stats показват (cached) индикатор
- При online → auto-sync + confirm

## 5.5 Сценарий 5: Подобна стока (duplicate detection)

Пешо добавя „Nike 42 черна" но вече има „Nike 42 черен".

**Защита:**
- AI fuzzy match на добавяне → „Имаш ли предвид „Nike 42 черен"?"
- Merge option

## 5.6 Сценарий 6: Matrix picker грешка

Пешо добавя артикул с размери, случайно въвежда 10 броя вместо 1 за XS.

**Защита:**
- Preview на matrix с totals
- Warning ако total > 100 за един артикул
- Undo last edit
- **Big tap buttons за variation selection (Kimi):**
  - В sale flow когато Пешо избира размер/цвят — grid с thumbnails, не dropdown
  - Min tap target 60px × 60px
- **Recent bias sorting:**
  - Ако Пешо продава черното XL по-често → то е първо в grid
  - Формула: `ORDER BY sale_count_30d DESC, alphabetical`

## 5.7 Сценарий 7: Нов служител

Seller за първи път. Не знае как работи.

**Защита:**
- First login → AI voice onboarding (2 минути)
- „Как искаш да питаш за помощ? Кажи „помогни" по всяко време"
- Всеки screen има „Как?" бутон

## 5.8 Сценарий 8: Сезонна промяна

Зима → пролет. Цените на якета падат. Пешо не променя.

**Защита:**
- AI seasonal detector: „Януарски артикули на склад, пролет идва. Обмисли намаление."
- Автоматичен отчет „застояла зимна стока"

## 5.9 Сценарий 9: 2 каси едновременно (+ multi-store)

Пешо + продавачка + N магазини + online работят паралелно.

**Защита:**
- Row-level locking (DOC 05 § 8)
- Idempotency keys (DOC 05 § 14)
- Negative stock guard
- **Pusher/Ably broadcast** към tenant channel → всички устройства update < 1s
- **User-facing message при конфликт (Kimi):**
  - Ако 2 каси продават последната бройка едновременно:
  - Първата каса → успех
  - Втората каса → toast: "Съжалявам, артикулът току-що беше продаден. Има ли подобен?"
  - AI автоматично предлага подобни (fuzzy match на size/color/category)

**⚠️ ВАЖНО:** За пълна multi-device архитектура виж `REAL_TIME_SYNC.md`.

## 5.10 Сценарий 10: Празник / промоция

Коледа, Великден, Black Friday. Разпродажба.

**Защита:**
- Promotion engine — правила (20% off, 50% off)
- Margin warning ако > 30% отстъпка
- AI alert след промо: „Продажбите +120% — проверявай stock всеки час"
- Cart abandonment tracking

---

# 6. WORKFLOW ЗАЩИТИ (ОБОБЩЕНИЕ)

| Защита | Покрива |
|---|---|
| Undo last 30s | Грешни тапове |
| Voice transcript display | Voice грешки |
| Async AI toasts | Бавен UX |
| Offline queue | Мрежови проблеми |
| Fuzzy match duplicates | Data hygiene |
| Row locking | Race conditions |
| Idempotency keys | Double submits |
| Seasonal AI | Забравяне на тренд |
| Rate limits | Abuse / bugs |
| Circuit breakers | External service failures |

---

**КРАЙ НА DOC 07**


---

## 5.11 Сценарий 11: Voice интерпретация в последователност („още едно")

Пешо казва: "Продай черна тениска L". AI продава.
Пешо казва: "Още едно".

**Проблем:** Ако AI ходи към Gemini за "още едно" → латенция 1-3 сек, cost.

**Защита (Conversation State Machine):**
- PHP помни последния intent в `$_SESSION['last_voice_intent']`
- "още едно", "пак", "и още" → recognized by PHP без AI call
- `$_SESSION['last_product_id']` → +1 към кошницата
- **0 AI cost, < 50ms response**

Това е ключова Закон №2 optimization — месечно AI cost намалява с 15-20%.
