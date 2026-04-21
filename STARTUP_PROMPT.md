# 🚀 STARTUP PROMPT — ЗА ВСЕКИ НОВ CHAT

## Когато Тихол отвори нов chat (Opus/Sonnet/Haiku):

**Пействай този ред:**

```
Чети https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/docs/compass/MASTER_COMPASS.md и следвай СТАРТОВИЯ ПРОТОКОЛ.
```

## Какво прави chat-ът при пействането

1. Фетчва MASTER_COMPASS.md през web_fetch
2. Следва "СТАРТОВ ПРОТОКОЛ" секцията:
   - Чете COMPASS целия
   - Фетчва DOC_01_PARVI_PRINCIPI.md
   - Фетчва последния SESSION_XX_HANDOFF.md (виж "Последна завършена сесия" в COMPASS)
   - Фетчва specific файлове за текущата задача (от "ЧЕТИВО ЗА СЛЕДВАЩАТА СЕСИЯ" таблица)
3. Казва на Тихол:
   - Текущо състояние
   - Какво работи / какво не работи
   - Следваща задача
   - **БЕЗ да пита "готов ли си"**
4. Започва работа директно.

---

## Алтернативи (ако COMPASS не е още в GitHub)

### Когато MASTER_COMPASS още НЕ е в repo:

```
Чети MASTER_COMPASS.md от project knowledge и следвай СТАРТОВИЯ ПРОТОКОЛ.
```

### Когато искаш бърз продължение от последната сесия:

```
продължи
```

Chat-ът има контекст от COMPASS → знае следващата задача → действа.

---

## Troubleshooting

### "Chat ми задава въпроси"
→ Chat-ът не е прочел COMPASS правилно. Напомни:  
*"Виж СТАРТОВ ПРОТОКОЛ в MASTER_COMPASS — трябва да казваш състояние без въпроси."*

### "Chat не знае последната сесия"
→ Последната сесия е в top header на COMPASS ("Последна завършена сесия"). Ако е stale → update го в края на всяка сесия.

### "Chat прави нещо което не съм искал"
→ Стоп. Кажи:  
*"Върни се назад. Виж LOGIC CHANGE LOG в COMPASS — засегнати ли са модули които не исках?"*

### "Промених логика в единия chat, другият не знае"
→ Проверка:  
*"В COMPASS LOGIC CHANGE LOG последната entry дали е добавена? Push-нат ли е commit-ът?"*  
Следващите chat-ове четат актуалния COMPASS от GitHub.

---

## Деплой на COMPASS в GitHub

Когато завърши сесия и COMPASS е обновен:

```bash
cd /var/www/runmystore
# COMPASS живее тук:
mkdir -p docs/compass
cp MASTER_COMPASS.md docs/compass/MASTER_COMPASS.md
git add docs/compass/MASTER_COMPASS.md
git commit -m "COMPASS: update after S[XX]"
git push origin main
```

URL pattern:
```
https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/docs/compass/MASTER_COMPASS.md
```

---

**КРАЙ НА STARTUP_PROMPT.md**
