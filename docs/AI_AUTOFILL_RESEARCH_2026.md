# Стратегически и технологичен анализ: Осъществимост на AI-базирана POS система (RunMyStore.AI) за физическата търговия на дребно на Балканите

## Въведение в пазарната и технологична рамка

В условията на динамични промени в сектора на търговията на дребно през 2026 г., малките и средни предприятия (МСП) на Балканите са изправени пред безпрецедентен натиск за оптимизация на разходите и повишаване на оперативната ефективност.1 Докладът анализира икономическата, техническата и регулаторната жизнеспособност на иновативната SaaS POS система RunMyStore.AI, таргетираща микро и малки търговци в България, Румъния и Гърция. Основният фокус е поставен върху финансовата разумност на интеграцията на усъвършенствани AI операции, по-специално функцията за автоматично попълване на атрибути на артикули чрез анализ на изображения (Image-to-Attributes), както и съпътстващи технологии като гласово разпознаване (Voice-to-Text), генеративни текстови и визуални модели. Анализът разглежда разходната структура на водещите foundation модели към второто тримесечие на 2026 г., сравнява ги с конкурентните алтернативи на пазара и предлага конкретен финансов модел за скалиране на бизнес плановете от €0 до €109 месечно.

## ЧАСТ 1: AI Модели и ценови структури (Анализ на конкретни параметри)

Разгръщането на мултимодални AI функции изисква прецизен архитектурен избор между водещите доставчици на езикови и визуални модели. През 2026 г. пазарът предлага значителна диференциация в ценообразуването, латентността и възможностите за кеширане на контекста.2

### A. Анализ на визуални модели (Image-to-Attributes)

Функцията за автоматично извличане на атрибути от снимка (категория, цвят, материал, марка) и структурирането им в JSON формат изисква мултимодален модел, способен на висока прецизност при Zero-shot или Few-shot заявки. За целите на изчислението се приема стандартна заявка, съдържаща 1 изображение (конвертирано в базов брой входни токени) и генерираща около 500 изходни токена (output tokens).

| **Доставчик и Модел** | **Входна цена (за 1М токена)** | **Изходна цена (за 1М токена)** | **Очаквана цена за 1 заявка (Image + 500 out)** | **Латентност и Точност** |
| --- | --- | --- | --- | --- |
| **Google Gemini 2.5 Flash** | $0.30 | $2.50 | ~$0.0015 | Изключително ниска латентност (<1s). Отлична базова точност.5 |
| **Google Gemini 2.5 Pro** | $1.25 | $10.00 | ~$0.006 - $0.008 | По-бавна реакция, но с най-висока точност при сложни детайли.5 |
| **Google Gemini Nano** | Локално (Edge) | Локално (Edge) | $0.00 | Изисква мощен хардуер на устройството, неподходящ за стари смартфони.7 |
| **Anthropic Claude 4.5 Haiku** | $1.00 | $5.00 | ~$0.0035 | Много бърз, отлична структура на JSON, висока точност за материали.3 |
| **Anthropic Claude 4.6 Sonnet** | $3.00 | $15.00 | ~$0.0105 | Премиум точност за сложни класификации, но висок COGS.3 |
| **OpenAI GPT-4o mini** | $0.15 | $0.60 | ~$0.0004 | Най-добра цена сред комерсиалните API, силна поддръжка на български.8 |
| **OpenAI GPT-5.4 Standard** | $2.50 | $15.00 | ~$0.01 | Лидер на пазара, използва tile-based изчисление за снимки (min 210 токена).9 |
| **Mistral Pixtral Large** | $2.00 | $6.00 | ~$0.005 | Отворен код, добър за общи задачи, но изостава в специализиран OCR.10 |
| **DeepSeek VL2** | $0.14 | $0.28 | ~$0.00025 | Най-евтиният модел на пазара, но с по-слаби резултати на e-commerce бенчмаркове.11 |
| **Qwen 2.5-VL 72B (API)** | $0.25 | $0.75 | ~$0.0006 | Флагмански open-source, превъзхожда DeepSeek в извличането на атрибути.11 |

Опциите за допълнително обучение (fine-tuning) се предлагат от OpenAI и Google, като цените обикновено стартират от $1.50 - $3.00 за милион тренировъчни токена.9 Въпреки това, при настоящата архитектура на моделите (включително Qwen 2.5-VL и Gemini 2.5 Flash), способността за структуриране на изхода (JSON Mode / Structured Outputs) е достатъчно надеждна без допълнително обучение, ако се използват прецизни системни инструкции (system prompts).13 Лимитите на заявките (Rate limits) за платените нива (Tier 2/3) при Google и OpenAI надвишават 2000 заявки на минута, което е напълно достатъчно за таргетирания обем.14 Всички посочени модели поддържат кирилица и български език без сериозни халюцинации.5

### B. Системи за гласово разпознаване (Voice-to-Text за БГ/РО/ГР)

Гласовото въвеждане е критично за бързото обслужване. Архитектурата изисква избор между асинхронна обработка (Batch) и поточно предаване в реално време (Streaming).

| **STT Доставчик** | **Цена на минута** | **Поддържани езици (БГ/РО/ГР)** | **Латентност** | **Точност (WER) и Специфики** |
| --- | --- | --- | --- | --- |
| **OpenAI Whisper API** | $0.006 | Пълна поддръжка | Висока (само Batch) | Най-висока точност (~3% WER), но не поддържа реален стрийминг.16 |
| **Deepgram (Nova-3)** | $0.0043 (Batch) - $0.0077 (Stream) | Пълна поддръжка | Много ниска (<300ms) | Оптимизиран за гласови агенти. Изключително бърз стрийминг.17 |
| **AssemblyAI (Universal-2)** | $0.0025 (Batch) - $0.0025 (Stream) | Пълна поддръжка | Ниска (~300ms) | Висока точност (до 30% по-малко халюцинации от Whisper), сложна интеграция.18 |
| **Google Cloud Speech** | $0.024 | Пълна поддръжка | Средна (1-3s) | Скъп и с по-ниска точност при силен фонов шум.16 |
| **Azure Speech** | $0.024 | Пълна поддръжка | Средна (1-3s) | Висока корпоративна сигурност, но финансово нерентабилен за стартъпи.16 |

За нуждите на търговците, където се диктуват команди в шумна среда, Deepgram Nova-3 се позиционира като оптимален избор поради баланса между цена, поддръжка на стрийминг и устойчивост на акценти и шум.17

### C. Генерация на текст (Бизнес сигнали, чат и SEO описания)

Генерирането на текстови съвети (напр. "Тази седмица търсенето на рокли нараства") и дълги SEO описания изисква икономичен модел с голям контекст.

Цените за 1000 изходни токена (около 750 думи) на български език варират значително:

- **Gemini 3.1 Flash-Lite:** $0.0015 за 1000 токена.5 Този модел е създаден за микро-задачи и fuzzy matching на категории.

- **Gemini 2.5 Flash:** $0.0025 за 1000 токена.5

- **GPT-4o mini:** $0.0006 за 1000 токена.8 Това го прави безспорен лидер по себестойност за текстови генерации в платформата.

- **Claude Haiku 4.5:** $0.005 за 1000 токена.3

- **GPT-5.4 Standard:** $0.015 за 1000 токена.9 Твърде скъп за масови SEO описания.

### D. Генерация на маркетингови изображения

Създаването на рекламни материали директно през POS терминала добавя стойност, но е ресурсоемко.

- **DALL-E 3:** Фиксирана цена от $0.04 на изображение (стандартна резолюция).20 Интеграцията е лесна чрез OpenAI API.

- **Imagen 3 / 4 (Google):** Цените варират от $0.02 до $0.06 в зависимост от качеството, средно $0.039 на изображение.2

- **Midjourney API:** Няма официален директен API за плащане на изображение, а се изисква месечен абонамент (напр. Standard план от $30/месец за 15 часа GPU време).22 Интеграцията е сложна и нерегламентирана за препродажба в SaaS.

- **Stable Diffusion XL:** Хостингът на собствени инстанции (self-hosted) изисква наемане на A100 или H100 сървъри, чиято цена е между $1.50 и $3.40 на час.23 За стартъп инфраструктура е по-рентабилно да се използва managed API (напр. чрез FAL.ai) на цена около $0.01 - $0.03 на изображение.25

## ЧАСТ 2: Моделиране на потреблението и анализ на разходите (Сценарии)

За да се оцени рентабилността, трябва да се изчисли месечната себестойност на продадените стоки (COGS) по отношение на AI инфраструктурата за един типичен физически магазин за дрехи в България. Профилът на потребление включва:

- **Image-to-attributes:** 100 нови артикула месечно.

- **Voice-to-text (STT):** 3 продавачи x 10 минути на ден = 30 минути дневно (около 900 минути месечно).

- **Chat Generation:** 500 AI сигнала и 200 прогнози за поръчки (~210,000 входни/изходни токена).

- **Text Generation:** 50 дълги SEO описания (~25,000 токена).

- **Image Generation:** 10 промоционални снимки.

### Изчисляване на месечния AI разход за един обект

При използване на оптимизиран микс от доставчици (GPT-4o mini за атрибути/текст, Deepgram за STT, DALL-E 3 за снимки), разходите се оформят по следния начин:

- **AI Auto-fill (GPT-4o mini):** 100 заявки * ~$0.0004 = **$0.04**.

- **Гласови команди (Deepgram Nova-3):** 900 мин * $0.0043 = **$3.87**.

- **AI Сигнали и Прогнози (GPT-4o mini):** 210,000 токена общо * ~$0.0006/1K = **$0.13**.

- **SEO Описания (GPT-4o mini):** 25,000 токена * ~$0.0006/1K = **$0.02**.

- **Маркетингови снимки (DALL-E 3):** 10 снимки * $0.04 = **$0.40**.

**Общ директен AI разход за един активен обект:** **~$4.46 (около €4.15) месечно.**

Анализът ясно показва, че *гласовото разпознаване генерира над 85% от разходите*, докато функцията за автоматично попълване на артикули (AI auto-fill) струва под €0.05 месечно, което я прави изключително финансово разумна за бизнес модела.

### Профилиране на абонаментните планове

Разпределянето на тези разходи върху четирите планирани абонаментни нива е критично за запазване на брутния марж.

#### 1. FREE План (€0 / месец)

Този план има за цел аквизиция на потребители (Product-led growth).

- **Разрешени AI функции:** До 20 AI auto-fill сканирания месечно. Липса на гласово разпознаване и маркетингови генерации.

- **Очакван AI разход:** **€0.01 - €0.02**.

- **Стратегия:** Функцията служи като "Aha! moment", демонстрирайки стойността на софтуера без да генерира материални загуби за компанията.

#### 2. START План (€19 / месец)

Насочен към самостоятелни собственици на малки обекти.

- **Включени AI функции:** До 150 AI auto-fill сканирания. Ограничение на гласовите команди до 150 минути месечно. Базови чат сигнали.

- **Очакван AI разход:** **€0.80 - €1.00**.

- **Брутен марж:** ~94%. Изключително рентабилен план, където AI разходите са напълно абсорбирани.

#### 3. PRO План (€49 / месец - до 5 магазина)

Насочен към развиващи се локални мрежи от обекти.

- **Включени AI функции:** До 1000 AI auto-fill сканирания. Гласови команди до 1000 минути месечно. SEO описания и 10 маркетингови изображения.

- **Очакван AI разход:** **€5.00 - €6.00**.

- **Брутен марж:** ~88%. Добавената стойност за търговеца (спестяване на десетки часове ръчен труд) е огромна спрямо таксата от €49.

#### 4. BUSINESS План (€109 / месец - неограничено)

За утвърдени вериги и по-големи търговци.

- **Включени AI функции:** Политика за честно използване (Fair Use Policy), позволяваща до 5000 минути STT и неограничен (кеширан) AI auto-fill.

- **Очакван AI разход:** **€20.00 - €25.00**.

- **Брутен марж:** ~75-80%.

**Препоръки за Pay-per-use монетизация:**

Функциите, свързани с генериране на визуално съдържание (Image generation) и интензивен гласов стрийминг, създават риск от злоупотреби. Препоръчва се въвеждането на система от "кредити". Всичко над лимитите в PRO плана трябва да се таксува отделно (например пакет от 50 AI снимки за €5 или 500 допълнителни STT минути за €4).

## ЧАСТ 3: Конкурентен анализ на POS системи на локалните и глобални пазари

Интеграцията на генеративен AI в POS системите е в начален стадий, което предоставя на RunMyStore.AI съществен прозорец за стратегическо позициониране.26

| **Конкурент** | **Фокус / Пазар** | **Месечна цена (Малък обект)** | **Наличие и тип на AI функции** | **Сравнителен анализ спрямо RunMyStore** |
| --- | --- | --- | --- | --- |
| **1. Square** | Глобален, САЩ, ЕС | Безплатен базов софтуер; Retail Plus е **€45 - €60** на локация.28 | Има вграден AI за автоматично изчистване на фона на снимки и базово генериране на описания.30 | Square е еталон, но липсва дълбока локализация за Балканите (езици, фискализации). Липсва AI auto-fill за инвентар от нулата. |
| **2. Shopify POS** | Глобален | Basic **€39** + POS Pro **€89** на локация (Общо **€128/мес**).29 | Shopify Magic генерира описания и анализи, но фокусът е онлайн.26 | Твърде скъпо и сложно за малък квартален физически магазин без голям онлайн оборот. |
| **3. Lightspeed Retail** | Глобален, ЕС | От **€109** до **€289** месечно.31 | Има AI за преводи и форматиране на описания.32 | Решение за големи инвентари, ценово недостъпно за балкаснките микро-търговци. |
| **4. Loyverse** | Глобален (много силен в БГ) | **€0** базов. Модули: Employee (**€25**), Inventory (**€25**).33 | **Няма AI функции.** Класически софтуер.33 | Най-прекият конкурент на FREE и START плановете. RunMyStore може да го победи чрез спестяването на време от AI въвеждането. |
| **5. MyPOS** | БГ, РО, ГР | **€0** месечно (печелят от 1.1% такса транзакция).34 | **Няма AI.** | Това е платежно решение с базов касов софтуер. Липсва интелигентно управление на склад. |
| **6. Vend (Lightspeed)** | Глобален | (Слято с Lightspeed X-Series, виж по-горе).32 | Виж Lightspeed. | - |
| **7. Toast** | Ресторанти | От **€69** месечно.36 | Фокус върху ресторантски операции, AI не е водещ.36 | Не е пряк конкурент поради различния сектор. |
| **8. Storebox** | Източна Европа | Индивидуални оферти.37 | Локален SEO и CRM фокус, слаб AI. | Ограничен пазарен дял, не представлява технологична заплаха. |
| **9. Smartbill** | Румъния | **€5.44 - €21.84** месечно.38 | Няма генеративен AI. Разполага с алгоритми за валидация към ANAF (Румънската НАП).38 | Доминантен в Румъния за счетоводство, но POS функциите му са базови и изискват ръчно въвеждане. |
| **10. Epsilon/Singular** | Гърция | Непубликувани абонаменти, изискват интеграция.39 | Маркетират "EPSILON AI", но се фокусира върху фискална интеграция (myDATA).39 | Хегемон в Гърция заради регулациите. RunMyStore трябва да гарантира myDATA свързаност, за да се конкурира. |

Изводът от конкурентния анализ е, че на пазарите в България, Румъния и Гърция **липсва софтуер от нисък ценови клас (€19 - €49), който да интегрира генеративен AI за намаляване на физическия труд**. Настоящите решения (Loyverse, Smartbill) принуждават търговците ръчно да въвеждат стотици артикули.

## ЧАСТ 4: Специфики на пазарите (България, Румъния, Гърция)

Локализацията и разбирането на микроикономиката в региона диктуват успеха на SaaS продуктите.

### България

Българският пазар на дребно отчита висока волатилност, но и значителни ръстове (до 12.4% увеличение на обемите в началото на 2026 г.).41 Въпреки това, малките търговци страдат от хронична липса на персонал и натиск за повишаване на минималната работна заплата.

- **Покупателна способност за софтуер:** Малкият магазинер в България е изключително ценово чувствителен. Проучвания показват, че над 60% от електронните търговци и физически обекти харчат под 2000 лв годишно за технологичен стак.42 Месечен бюджет от €19 до €49 се възприема като приемлив само ако софтуерът очевидно замества човешки труд.

- **Готовност за плащане за AI:** Самият термин "AI" не продава на собственика на квартален бутик. Те купуват "автоматично създаване на стоки със снимка", защото им спестява часове работа след затваряне на обекта.

- **Размер на пазара:** Десетки хиляди микро-предприятия, които в момента използват остарели локални ERP системи (напр. Микроинвест) или базови облачни аппликации (Loyverse).

### Румъния

Румънският пазар е най-големият в региона, но отчете спадове в обема на търговията на дребно с 4.6% на годишна база в края на 2025/началото на 2026 г. поради инфлация и намалена покупателна способност.43

- **Специфики на средата:** Пазарът е доминиран от строги фискални регулации (RO e-Factura), което прави локалния играч Smartbill почти монополист при микро-бизнесите.44

- **Готовност за плащане:** Румънските потребители имат по-развита SaaS култура от българските. Според данни, около 53.8% от търговците все още използват базови таблици за инвентар, а само 30.8% разчитат на автоматизирани системи.45 Има висок нереализиран потенциал за AI функции в ценовия клас €20-€50.

### Гърция

Гръцкият пазар отбеляза ръст от 4.6% в търговията на дребно.46 Той е силно фрагментиран поради географските особености (острови, силно застъпен туризъм).

- **Специфики:** Задължителната интеграция с електронните книги на гръцката данъчна агенция (myDATA) е бариера за навлизане на нови системи. Местни гиганти като Epsilon Net и SingularLogic държат пазара.40

- **Готовност за плащане:** Търговците са свикнали да плащат абонаментни такси и услуги за интеграция. AI функциите за автоматичен превод на артикули за нуждите на туристите и бързото сканиране представляват огромна добавена стойност.

## ЧАСТ 5: Техническа и регулаторна архитектура

### 1. ДДС и данъчно третиране на AI API услуги (За българско дружество)

Ако RunMyStore.AI оперира чрез българско юридическо лице (ЕООД/ООД), регистрирано по ЗДДС, закупуването на API услуги от OpenAI (САЩ) или Google/Anthropic (чиито европейски централи са в Ирландия) попада под правилата за търговия с услуги B2B.

- **Механизъм:** Прилага се механизмът на **обратно начисляване (Reverse Charge)**.48 Чуждестранният доставчик издава фактура с 0% ДДС. Българското дружество си самоначислява 20% ДДС чрез издаване на протокол. Ако софтуерът се използва за облагаеми доставки (продажба на абонаменти), дружеството има право на пълен данъчен кредит, като реално не внася този данък в бюджета.

- *Уточнение за 2026 г.:* Широко коментираните промени в българския ЗДДС от 1 януари 2026 г., които отменят reverse charge механизма, се отнасят **изключително за доставка на стоки с монтаж и инсталация** от доставчици в ЕС, и *не засягат* доставката на дигитални услуги и API достъп.49

### 2. GDPR и локализация на данните (Data Residency)

Обработката на снимки на стоки не представлява риск от гледна точка на личните данни. Въпреки това, гласовите записи от Voice-to-text и данните от поведението на продавачите попадат под обхвата на GDPR и българския Закон за защита на личните данни (ЗЗЛД). Всички доставчици (OpenAI, Anthropic, Google Cloud) трябва да бъдат конфигурирани с опции за Data Residency в Европейския съюз 3, като се подпишат съответните Data Processing Agreements (DPA). Функцията за задържане на данни (zero-data retention) при API доставчиците е задължителна.

### 3. EU AI Act — Регулаторна рамка (влизаща в сила август 2026 г.)

Европейският акт за изкуствения интелект ще се прилага напълно от 2 август 2026 г..53 Ключовият момент за RunMyStore.AI е класификацията на риска 55:

- **Minimal Risk (Минимален риск):** AI-асистираното управление на инвентар, fuzzy matching и прогнозиране на продажби спадат към тази категория. Те остават нерегулирани от гледна точка на тежки рестрикции.53

- **Limited Risk (Ограничен риск):** Генеративният AI (като чат сигнали и създаване на маркетингови изображения) спада тук. Единственото задължение на компанията е **прозрачност** – потребителите трябва да са информирани, че взаимодействат с AI и че дадено изображение/текст е синтетично генерирано.53 Системата *не е* High-Risk и не изисква скъпи процедури за оценка на съответствието (Conformity Assessments) или регистрации в европейски бази данни.58

### 4. Оптимизационни стратегии (Cache & Batching)

За да се запази маржът на печалба, архитектурата трябва да внедри две ключови стратегии:

- **Prompt Caching:** Кеширането на контекст при модели като Claude и Gemini намалява цената на входните токени с до 90%.2 Например, ако се подава един и същ системен prompt (списък от съществуващи категории в магазина) с всяка снимка, той се кешира и не се заплаща на пълна цена.

- **Perceptual Hashing (AI Snapshot Caching):** Преди изпращане на снимка към скъпото Vision API, системата хешира изображението. Ако друг търговец вече е заснел абсолютно същия баркод или бутилка безалкохолно, системата връща готовия JSON от локалната база данни, свеждайки разхода до €0.

### 5. Локални модели (Local Models) срещу API

Модели като Qwen 2.5-VL 72B са с отворен код. Инфраструктурният анализ обаче показва, че наемането на dedicated GPU (напр. NVIDIA A100 или H100) за самостоятелен хостинг (self-hosting) струва между $1.50 и $3.40 на час (над $1,000 месечно).23 Практиката доказва, че API моделът (плащане на токен) е много по-изгоден за 87% от бизнес сценариите, освен ако платформата не обработва над 11 милиарда токена месечно.61 За стартиращ SaaS с под 2000 клиента, API интеграцията е единственият финансов разумен път.

## ЧАСТ 6: Финансова прогноза и Roadmap (ARPU vs AI COGS)

Следният модел илюстрира очакваните приходи и директните разходи за AI операции (Cost of Goods Sold - COGS) при скалиране на клиентската база. Предположенията се базират на среден приход от потребител (ARPU) от **€35** (балансиран микс предимно от START и PRO планове).

| **Брой активни платени обекти** | **10 обекта (MVP / Beta)** | **100 обекта (Начален ръст)** | **500 обекта (Стабилен бизнес)** | **2000 обекта (Скалиране)** |
| --- | --- | --- | --- | --- |
| **Месечни приходи (MRR)** | €350 | €3,500 | €17,500 | €70,000 |
| **AI Разходи (Worst-case)** * | €80 | €800 | €4,000 | €16,000 |
| **AI Разходи (Expected)** ** | €45 | €450 | €2,250 | €9,000 |
| **AI Разходи (Best-case)** *** | €20 | €200 | €1,000 | €4,000 |
| **Брутна печалба (Expected)** | **€305** | **€3,050** | **€15,250** | **€61,000** |
| **AI COGS като % от прихода** | ~12.8% | ~12.8% | ~12.8% | ~12.8% |

** Worst-case:* Клиентите достигат максималните лимити на своите планове и се използват премиум модели (GPT-5.4/Gemini Pro) без кеширане.

*Expected:* Използване на Gemini 2.5 Flash / GPT-4o mini, имплементирано кеширане и типично поведение на търговец (не всеки ден се въвеждат стотици нови стоки).

**** Best-case:* Оптимизация чрез Perceptual Hashing, високо ниво на преизползване на базата данни и използване на Batch API за асинхронни задачи.

AI разходите са "под контрол" от ден първи благодарение на Pay-as-you-go модела на доставчиците. Риск за кеш-флоуа създават единствено потребителите на FREE плана, чийто брой може да нарасне експоненциално. Тяхното потребление трябва да бъде агресивно лимитирано (напр. спиране на достъпа до AI след 20-тия артикул).

## ЧАСТ 7: Финални препоръки и стратегически изводи

Въз основа на изчерпателния анализ, изводите за развитието на RunMyStore.AI са следните:

### 1. Финансово разумна ли е функцията AI auto-fill?

**Абсолютно да.** Разходът за генериране на атрибути от изображение през ефективни модели (Gemini 2.5 Flash или Qwen 2.5-VL през API) варира между €0.001 и €0.002 на артикул.2 Месечният разход от €0.15 за 100 артикула е пренебрежим на фона на абонаментната такса от €19, докато добавената стойност за търговеца (спестени часове досаден труд) е основен двигател за продажбите на софтуера. Гласовото разпознаване (Voice-to-text) е истинският генератор на разходи (~85% от AI бюджета) и трябва да бъде стриктно лимитирано или монетизирано чрез кредити.

### 2. Избор на модел и стратегия

- **За Image-to-Attributes:** Препоръчва се интеграцията на **Google Gemini 2.5 Flash** или **Qwen 2.5-VL 72B** (през евтин API провайдър като DeepInfra) поради недостижимото им съотношение между скорост, цена и визуално разбиране.5

- **За Voice-to-Text:** Интегрирайте **Deepgram** за заявки, изискващи светкавична реакция на касата (под 300ms), а **Whisper API** използвайте за асинхронни задачи (напр. диктуване на описания след затваряне на обекта).17

- **Софтуерна архитектура:** Внедрете междинен слой (middleware) за хеширане на изображенията. Това ще предпази системата от излишни API извиквания при сканиране на един и същ продукт от различни търговци.

### 3. Алтернативи при ценови шокове

В случай на драстично повишаване на API цените или злоупотреби, системата трябва плавно да превключва към **Batch API обработка**.2 В този сценарий търговецът снима артикулите, но те се обработват асинхронно в рамките на 24 часа с 50% по-ниска себестойност. Друга алтернатива е локално кеширан каталог от EAN баркодове за бързооборотни стоки, заобикаляйки AI изцяло.

### 4. Критични тестове преди пазарен дебют

Преди стартиране на кампании към клиенти, архитектурата трябва да валидира:

- **Fuzzy Matching и Халюцинации:** Дали моделът класифицира правилно според фиксирана таксономия (напр. да не създава категория "Лятна рокля", ако вече съществува "Рокли").

- **Гласово разпознаване с акценти:** Тестване на STT двигателите с различни диалекти и фонов шум (напр. шум от хладилни витрини или музика в обекта).

- **Локализация на OCR:** Успешно разпознаване на размазан текст на кирилица и румънски от лошо осветени етикети.

### 5. Анализ на точката на безубитъчност (Break-even)

Тъй като маржът на AI операциите надхвърля 85-90%, точката на безубитъчност зависи почти изцяло от фиксираните разходи на стартъпа (сървъри за база данни, поддръжка, маркетинг, заплати). При хипотетични фиксирани разходи от €1,500 на месец и среден нетен приход от клиент €32 (€35 такса минус €3 AI COGS), **са необходими едва около 45-50 платени клиента**, за да се покрият оперативните разходи. Това демонстрира изключителната финансова гъвкавост и жизнеспособност на бизнес модела на RunMyStore.AI на Балканите през 2026 г.

#### Цитирани творби

- central & eastern europe - retail q2 2025 - Cushman & Wakefield Echinox, осъществен достъп на май 14, 2026, [https://cwechinox.com/app/uploads/2025/10/2025_q2_cee_retail-marketbeat.pdf](https://cwechinox.com/app/uploads/2025/10/2025_q2_cee_retail-marketbeat.pdf)

- Gemini API Pricing 2026: Complete Cost Guide for All Models ..., осъществен достъп на май 14, 2026, [https://www.metacto.com/blogs/the-true-cost-of-google-gemini-a-guide-to-api-pricing-and-integration](https://www.metacto.com/blogs/the-true-cost-of-google-gemini-a-guide-to-api-pricing-and-integration)

- Pricing - Claude API Docs, осъществен достъп на май 14, 2026, [https://platform.claude.com/docs/en/about-claude/pricing](https://platform.claude.com/docs/en/about-claude/pricing)

- OpenAI API Cost In 2026: Every Model Compared - CloudZero, осъществен достъп на май 14, 2026, [https://www.cloudzero.com/blog/openai-pricing/](https://www.cloudzero.com/blog/openai-pricing/)

- Gemini Developer API pricing, осъществен достъп на май 14, 2026, [https://ai.google.dev/gemini-api/docs/pricing](https://ai.google.dev/gemini-api/docs/pricing)

- Models overview - Claude API Docs, осъществен достъп на май 14, 2026, [https://platform.claude.com/docs/en/about-claude/models/overview](https://platform.claude.com/docs/en/about-claude/models/overview)

- Learn about supported models | Firebase AI Logic - Google, осъществен достъп на май 14, 2026, [https://firebase.google.com/docs/ai-logic/models](https://firebase.google.com/docs/ai-logic/models)

- GPT 4o mini API Pricing 2026 - Costs, Performance & Providers - Price Per Token, осъществен достъп на май 14, 2026, [https://pricepertoken.com/pricing-page/model/openai-gpt-4o-mini](https://pricepertoken.com/pricing-page/model/openai-gpt-4o-mini)

- API Pricing - OpenAI, осъществен достъп на май 14, 2026, [https://openai.com/api/pricing/](https://openai.com/api/pricing/)

- DeepSeek-V2.5 vs Pixtral Large — Pricing, Benchmarks & Performance Compared, осъществен достъп на май 14, 2026, [https://anotherwrapper.com/tools/llm-pricing/deepseek-v2.5/pixtral-large](https://anotherwrapper.com/tools/llm-pricing/deepseek-v2.5/pixtral-large)

- DeepSeek VL2 vs Qwen2.5 VL 72B Instruct Comparison - LLM Stats, осъществен достъп на май 14, 2026, [https://llm-stats.com/models/compare/deepseek-vl2-vs-qwen2.5-vl-72b](https://llm-stats.com/models/compare/deepseek-vl2-vs-qwen2.5-vl-72b)

- Qwen2.5 VL 72B Instruct API Pricing 2026 - Costs, Performance & Providers, осъществен достъп на май 14, 2026, [https://pricepertoken.com/pricing-page/model/qwen-qwen2.5-vl-72b-instruct](https://pricepertoken.com/pricing-page/model/qwen-qwen2.5-vl-72b-instruct)

- Fine-Tuning Florence-2 for Structured Fashion Attribute Extraction - arXiv, осъществен достъп на май 14, 2026, [https://arxiv.org/html/2605.09827v1](https://arxiv.org/html/2605.09827v1)

- Gemini 3.1 Pro API Pricing & Performance: The Complete 2026 ..., осъществен достъп на май 14, 2026, [https://www.glbgpt.com/hub/gemini-3-1-pro-api-pricing-performance-the-complete-guide-for-developers/](https://www.glbgpt.com/hub/gemini-3-1-pro-api-pricing-performance-the-complete-guide-for-developers/)

- Rate limits - Claude API Docs, осъществен достъп на май 14, 2026, [https://platform.claude.com/docs/en/api/rate-limits](https://platform.claude.com/docs/en/api/rate-limits)

- Best Speech to Text APIs for Developers in 2026 - Voicy, осъществен достъп на май 14, 2026, [https://usevoicy.com/blog/best-speech-to-text-api](https://usevoicy.com/blog/best-speech-to-text-api)

- Deepgram vs Google vs AssemblyAI: 2026 Comparison, осъществен достъп на май 14, 2026, [https://deepgram.com/learn/deepgram-vs-assemblyai-vs-whisper](https://deepgram.com/learn/deepgram-vs-assemblyai-vs-whisper)

- Best AI Transcription Services 2026 - BrassTranscripts, осъществен достъп на май 14, 2026, [https://brasstranscripts.com/blog/best-ai-transcription-services-2026](https://brasstranscripts.com/blog/best-ai-transcription-services-2026)

- Benchmarks - AssemblyAI, осъществен достъп на май 14, 2026, [https://www.assemblyai.com/benchmarks](https://www.assemblyai.com/benchmarks)

- Image Generation Model Comparison: DALL-E 3 vs Midjourney vs Stable Diffusion, осъществен достъп на май 14, 2026, [https://www.curify-ai.com/blog/image-generation-model-comparison](https://www.curify-ai.com/blog/image-generation-model-comparison)

- Introducing Gemini 2.5 Flash Image, our state-of-the-art image model, осъществен достъп на май 14, 2026, [https://developers.googleblog.com/en/introducing-gemini-2-5-flash-image/](https://developers.googleblog.com/en/introducing-gemini-2-5-flash-image/)

- Comparing Midjourney Plans, осъществен достъп на май 14, 2026, [https://docs.midjourney.com/hc/en-us/articles/27870484040333-Comparing-Midjourney-Plans](https://docs.midjourney.com/hc/en-us/articles/27870484040333-Comparing-Midjourney-Plans)

- NVIDIA A100 Pricing (May 2026): Cheapest On-demand GPU Instances - Thunder Compute, осъществен достъп на май 14, 2026, [https://www.thundercompute.com/blog/nvidia-a100-pricing](https://www.thundercompute.com/blog/nvidia-a100-pricing)

- GPU Cloud Pricing Comparison 2026: Every Major Provider Side by Side | Spheron Blog, осъществен достъп на май 14, 2026, [https://www.spheron.network/blog/gpu-cloud-pricing-comparison-2026/](https://www.spheron.network/blog/gpu-cloud-pricing-comparison-2026/)

- AI Image & Video API Pricing Comparison 2026: FAL.AI vs Replicate vs OpenAI, осъществен достъп на май 14, 2026, [https://www.teamday.ai/blog/ai-api-pricing-comparison-2026](https://www.teamday.ai/blog/ai-api-pricing-comparison-2026)

- Shopify POS 2026: Pricing, Hardware, Setup & Complete Guide | Ask Phill, осъществен достъп на май 14, 2026, [https://askphill.com/blogs/blog/shopify-pos-whitepaper](https://askphill.com/blogs/blog/shopify-pos-whitepaper)

- Best POS System for Small Business (2026) - Talk Shop, осъществен достъп на май 14, 2026, [https://www.letstalkshop.com/blog/best-pos-system-for-small-business](https://www.letstalkshop.com/blog/best-pos-system-for-small-business)

- Square vs. Shopify 2026 (Comparison) – Forbes Advisor, осъществен достъп на май 14, 2026, [https://www.forbes.com/advisor/business/software/square-vs-shopify/](https://www.forbes.com/advisor/business/software/square-vs-shopify/)

- Shopify POS vs Square: Complete Comparison (2026) - EasyApps Ecommerce, осъществен достъп на май 14, 2026, [https://easyappsecom.com/guides/shopify-pos-vs-square.html](https://easyappsecom.com/guides/shopify-pos-vs-square.html)

- Square Releases: New Retail Features & Updates, осъществен достъп на май 14, 2026, [https://squareup.com/us/en/releases/retail](https://squareup.com/us/en/releases/retail)

- Shopify POS vs Lightspeed POS: The Complete 2026 Comparison for Growing Retailers, осъществен достъп на май 14, 2026, [https://www.fyresite.com/shopify-pos-vs-lightspeed-pos-the-complete-2026-comparison-for-growing-retailers/](https://www.fyresite.com/shopify-pos-vs-lightspeed-pos-the-complete-2026-comparison-for-growing-retailers/)

- Creating, formatting, and translating product descriptions for Lightspeed eCom with AI, осъществен достъп на май 14, 2026, [https://x-series-support.lightspeedhq.com/hc/en-us/articles/39765420774043-Creating-formatting-and-translating-product-descriptions-for-Lightspeed-eCom-with-AI](https://x-series-support.lightspeedhq.com/hc/en-us/articles/39765420774043-Creating-formatting-and-translating-product-descriptions-for-Lightspeed-eCom-with-AI)

- POS System Pricing - Loyverse, осъществен достъп на май 14, 2026, [https://loyverse.com/pricing](https://loyverse.com/pricing)

- myPOS: Payment Solutions for Small Businesses, осъществен достъп на май 14, 2026, [https://www.mypos.com/en-gb](https://www.mypos.com/en-gb)

- List of fees for merchants - myPOS, осъществен достъп на май 14, 2026, [https://www.mypos.com/en-mt/pricing-and-fees/fees](https://www.mypos.com/en-mt/pricing-and-fees/fees)

- Toast Pricing 2026, осъществен достъп на май 14, 2026, [https://www.g2.com/products/toast/pricing](https://www.g2.com/products/toast/pricing)

- Storebox AI Pricing 2026, осъществен достъп на май 14, 2026, [https://www.g2.com/products/storebox-ai/pricing](https://www.g2.com/products/storebox-ai/pricing)

- SmartBill Software Pricing, Alternatives & More 2026 | Capterra, осъществен достъп на май 14, 2026, [https://www.capterra.com/p/10033061/SmartBill/](https://www.capterra.com/p/10033061/SmartBill/)

- Epsilon Smart | EPSILONNET, осъществен достъп на май 14, 2026, [https://epsilonnet.gr/en/products/epsilon-smart/](https://epsilonnet.gr/en/products/epsilon-smart/)

- EPSILON ALL in ONE | EPSILONNET, осъществен достъп на май 14, 2026, [https://epsilonnet.gr/en/products/epsilon-all-in-one/](https://epsilonnet.gr/en/products/epsilon-all-in-one/)

- Bulgaria reported a 12.4% increase in retail volumes in March, the highest in the EU, осъществен достъп на май 14, 2026, [https://europe-data.com/bulgaria-reported-a-12-4-increase-in-retail-volumes-in-march-the-highest-in-the-eu/](https://europe-data.com/bulgaria-reported-a-12-4-increase-in-retail-volumes-in-march-the-highest-in-the-eu/)

- 44% of Bulgarian Online Stores Don't Sell Abroad, While 75% Rely on Facebook: Key Insights from the Bulgarian eCommerce Market, осъществен достъп на май 14, 2026, [https://balkanecommerce.com/44-of-bulgarian-online-stores-dont-sell-abroad-while-75-rely-on-facebook-key-insights-from-the-bulgarian-ecommerce-market/](https://balkanecommerce.com/44-of-bulgarian-online-stores-dont-sell-abroad-while-75-rely-on-facebook-key-insights-from-the-bulgarian-ecommerce-market/)

- Eurostat: Romania records sharpest annual decline in retail volume - Business Forum, осъществен достъп на май 14, 2026, [https://www.businessforum.ro/economy/20260109/eurostat-romania-records-sharpest-annual-decline-in-retail-volume-2727](https://www.businessforum.ro/economy/20260109/eurostat-romania-records-sharpest-annual-decline-in-retail-volume-2727)

- Smartbill Review 2026: Pricing, Features, Pros & Cons, Ratings & More - Research.com, осъществен достъп на май 14, 2026, [https://research.com/software/reviews/smartbill](https://research.com/software/reviews/smartbill)

- Insights from the Romanian eCommerce Market: Over 40% of Romanian Stores Have No Content Budget, While Most Invest in Ads to Drive Growth, осъществен достъп на май 14, 2026, [https://balkanecommerce.com/insights-from-the-romanian-ecommerce-market-over-40-of-romanian-stores-have-no-content-budget-while-most-invest-in-ads-to-drive-growth/](https://balkanecommerce.com/insights-from-the-romanian-ecommerce-market-over-40-of-romanian-stores-have-no-content-budget-while-most-invest-in-ads-to-drive-growth/)

- Greece Retail Sales YoY - Trading Economics, осъществен достъп на май 14, 2026, [https://tradingeconomics.com/greece/retail-sales-annual](https://tradingeconomics.com/greece/retail-sales-annual)

- Products Index | EPSILONNET, осъществен достъп на май 14, 2026, [https://epsilonnet.gr/en/products-index/](https://epsilonnet.gr/en/products-index/)

- Bulgaria for SaaS Companies: VAT, OSS & Digital Services Tax Guide (2026), осъществен достъп на май 14, 2026, [https://innovires.com/tax-residency/blog/bulgaria-saas-company-vat-oss-guide.html](https://innovires.com/tax-residency/blog/bulgaria-saas-company-vat-oss-guide.html)

- Changes to the VAT Act effective from 1 January 2026 - KPMG International, осъществен достъп на май 14, 2026, [https://kpmg.com/bg/en/insights/2026/01/changes-to-the-vat-act-effective-from-1-january-2026.html](https://kpmg.com/bg/en/insights/2026/01/changes-to-the-vat-act-effective-from-1-january-2026.html)

- Bulgaria Ends Reverse Charge for Supply and Installation Contracts from 2026, осъществен достъп на май 14, 2026, [https://marosavat.com/vat-news/bulgaria-ends-reverse-charge-supply-and-installation-2026](https://marosavat.com/vat-news/bulgaria-ends-reverse-charge-supply-and-installation-2026)

- Bulgaria VAT Reform 2026: What Businesses Need to Know - Eurofast, осъществен достъп на май 14, 2026, [https://eurofast.eu/bulgaria-vat-reform-2026-what-businesses-need-to-know/](https://eurofast.eu/bulgaria-vat-reform-2026-what-businesses-need-to-know/)

- Gemini 2.5 Pro | Gemini Enterprise Agent Platform - Google Cloud Documentation, осъществен достъп на май 14, 2026, [https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/gemini/2-5-pro](https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/gemini/2-5-pro)

- AI Act | Shaping Europe's digital future - European Union, осъществен достъп на май 14, 2026, [https://digital-strategy.ec.europa.eu/en/policies/regulatory-framework-ai](https://digital-strategy.ec.europa.eu/en/policies/regulatory-framework-ai)

- New Guidance under the EU AI Act Ahead of its Next Enforcement Date - Pearl Cohen, осъществен достъп на май 14, 2026, [https://www.pearlcohen.com/new-guidance-under-the-eu-ai-act-ahead-of-its-next-enforcement-date/](https://www.pearlcohen.com/new-guidance-under-the-eu-ai-act-ahead-of-its-next-enforcement-date/)

- Article 6: Classification Rules for High-Risk AI Systems | EU Artificial Intelligence Act, осъществен достъп на май 14, 2026, [https://artificialintelligenceact.eu/article/6/](https://artificialintelligenceact.eu/article/6/)

- EU AI Act Compliance Requirements for Companies: What to Prepare for 2026, осъществен достъп на май 14, 2026, [https://www.complianceandrisks.com/blog/eu-ai-act-compliance-requirements-for-companies-what-to-prepare-for-2026/](https://www.complianceandrisks.com/blog/eu-ai-act-compliance-requirements-for-companies-what-to-prepare-for-2026/)

- осъществен достъп на май 14, 2026, [https://digital-strategy.ec.europa.eu/en/consultations/consultation-draft-guidelines-transparency-obligations-under-ai-act#:~:text=The%20rules%20will%20become%20applicable,as%20AI%20generated%20or%20manipulated.](https://digital-strategy.ec.europa.eu/en/consultations/consultation-draft-guidelines-transparency-obligations-under-ai-act#:~:text=The%20rules%20will%20become%20applicable,as%20AI%20generated%20or%20manipulated.)

- AI Risk Classification: Guide to EU AI Act Risk Categories - GDPR Local, осъществен достъп на май 14, 2026, [https://gdprlocal.com/ai-risk-classification/](https://gdprlocal.com/ai-risk-classification/)

- The Ultimate Guide to the EU AI Act - Hyperproof, осъществен достъп на май 14, 2026, [https://hyperproof.io/ultimate-guide-to-the-eu-ai-act/](https://hyperproof.io/ultimate-guide-to-the-eu-ai-act/)

- NVIDIA H100 Price Guide 2026: GPU Costs, Cloud Pricing & Buy vs Rent - Jarvis Labs, осъществен достъп на май 14, 2026, [https://jarvislabs.ai/blog/h100-price](https://jarvislabs.ai/blog/h100-price)

- Self-Hosting vs API LLM Costs (2026): The Break-Even Math Most Companies Get Wrong, осъществен достъп на май 14, 2026, [https://abhyashsuchi.in/api-vs-self-hosting-llm-cost/](https://abhyashsuchi.in/api-vs-self-hosting-llm-cost/)

- Top tools for live transcription - AssemblyAI, осъществен достъп на май 14, 2026, [https://www.assemblyai.com/blog/top-tools-for-live-transcription](https://www.assemblyai.com/blog/top-tools-for-live-transcription)