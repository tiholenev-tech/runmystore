<?php
/**
 * biz-coefficients.php — 300 бизнес типа с коефициенти + вариации
 * Сесия 22 — ПЪЛЕН МЕРДЖ (категорийни дефолти + overrides)
 *
 * Структура:
 *   $BIZ_COEFFICIENTS — ключ→коефициент за fuzzy matching (WOW момент)
 *   $BIZ_CATEGORY_DEFAULTS — дефолтни variant настройки по категория
 *   $BIZ_TYPES — 300 обекта с пълна информация
 *
 * Функции:
 *   findBizCoefficient($text) — fuzzy match → коефициент за загуби
 *   calculateLosses($coeff, $stores) — 5 WOW сценария
 *   findBizVariants($text) — fuzzy match → variant_fields, presets, units, typical_fields, ai_scan_detects
 *   findBizTypeById($id) — по id → пълна информация
 */

// ═══════════════════════════════════════════════════════════════
// ДЕФОЛТИ ПО КАТЕГОРИЯ (за variant info)
// ═══════════════════════════════════════════════════════════════
$BIZ_CATEGORY_DEFAULTS = [
    'clothing' => [
        'has_variants' => true,
        'variant_fields' => ['Размер', 'Цвят'],
        'variant_presets' => [
            'Размер' => ['XS','S','M','L','XL','2XL','3XL'],
            'Цвят' => ['Черен','Бял','Червен','Син','Розов','Бежов','Зелен','Сив']
        ],
        'units' => ['бр'],
        'typical_fields' => ['Марка', 'Материал', 'Сезон'],
        'ai_scan_detects' => ['тип дреха', 'цвят', 'марка/лого', 'материал'],
        'loss_coefficient' => 0.030,
        'monthly_loss_categories' => ['Кражби'=>0.30,'Повреди/брак'=>0.20,'Грешки в броенето'=>0.15,'Сезонно обезценяване'=>0.25,'Връщания/рекламации'=>0.10],
        'avg_items_count' => 500,
        'avg_item_price_eur' => 35,
    ],
    'shoes' => [
        'has_variants' => true,
        'variant_fields' => ['Размер', 'Цвят'],
        'variant_presets' => [
            'Размер' => ['35','36','37','38','39','40','41','42','43','44','45','46'],
            'Цвят' => ['Черен','Кафяв','Бял','Бежов','Червен','Син']
        ],
        'units' => ['чифт'],
        'typical_fields' => ['Марка', 'Материал', 'Сезон'],
        'ai_scan_detects' => ['тип обувка', 'цвят', 'марка/лого'],
        'loss_coefficient' => 0.030,
        'monthly_loss_categories' => ['Кражби'=>0.25,'Повреди/брак'=>0.20,'Грешки в броенето'=>0.15,'Сезонно обезценяване'=>0.30,'Връщания/рекламации'=>0.10],
        'avg_items_count' => 350,
        'avg_item_price_eur' => 60,
    ],
    'food' => [
        'has_variants' => false,
        'variant_fields' => [],
        'variant_presets' => [],
        'units' => ['бр', 'кг', 'гр'],
        'typical_fields' => ['Марка', 'Срок на годност', 'Произход', 'Тегло'],
        'ai_scan_detects' => ['етикет', 'марка', 'тип продукт'],
        'loss_coefficient' => 0.035,
        'monthly_loss_categories' => ['Изтекъл срок'=>0.35,'Повреди при транспорт'=>0.15,'Кражби'=>0.20,'Грешки в броенето'=>0.15,'Технологичен брак'=>0.15],
        'avg_items_count' => 500,
        'avg_item_price_eur' => 5,
    ],
    'food_fresh' => [
        'has_variants' => false,
        'variant_fields' => [],
        'variant_presets' => [],
        'units' => ['кг', 'гр'],
        'typical_fields' => ['Произход', 'Срок на годност', 'Вид'],
        'ai_scan_detects' => ['тип месо'],
        'loss_coefficient' => 0.050,
        'monthly_loss_categories' => ['Изтекъл срок'=>0.40,'Повреди при транспорт'=>0.15,'Кражби'=>0.10,'Грешки в броенето'=>0.15,'Технологичен брак'=>0.20],
        'avg_items_count' => 50,
        'avg_item_price_eur' => 10,
    ],
    'drinks' => [
        'has_variants' => false,
        'variant_fields' => [],
        'variant_presets' => [],
        'units' => ['бр'],
        'typical_fields' => ['Сорт', 'Произход', 'Обем', 'Годишна реколта'],
        'ai_scan_detects' => ['етикет', 'марка', 'вид напитка'],
        'loss_coefficient' => 0.020,
        'monthly_loss_categories' => ['Повреди при транспорт'=>0.25,'Кражби'=>0.25,'Грешки в броенето'=>0.15,'Изтекъл срок'=>0.20,'Обезценяване'=>0.15],
        'avg_items_count' => 300,
        'avg_item_price_eur' => 10,
    ],
    'cosmetics' => [
        'has_variants' => true,
        'variant_fields' => ['Нюанс'],
        'variant_presets' => ['Нюанс' => []],
        'units' => ['бр'],
        'typical_fields' => ['Марка', 'Обем', 'Тип кожа', 'Срок на годност'],
        'ai_scan_detects' => ['марка', 'тип продукт', 'нюанс'],
        'loss_coefficient' => 0.025,
        'monthly_loss_categories' => ['Изтекъл срок'=>0.30,'Повреди'=>0.15,'Кражби'=>0.20,'Тестери/мостри'=>0.15,'Грешки'=>0.20],
        'avg_items_count' => 500,
        'avg_item_price_eur' => 15,
    ],
    'electronics' => [
        'has_variants' => true,
        'variant_fields' => ['Цвят'],
        'variant_presets' => ['Цвят' => ['Черен','Бял','Сив','Син','Червен']],
        'units' => ['бр'],
        'typical_fields' => ['Марка', 'Модел', 'Гаранция'],
        'ai_scan_detects' => ['устройство', 'марка', 'модел'],
        'loss_coefficient' => 0.015,
        'monthly_loss_categories' => ['Кражби'=>0.30,'Дефектни върнати'=>0.20,'Остаряване на модели'=>0.25,'Транспортни повреди'=>0.10,'Грешки'=>0.15],
        'avg_items_count' => 200,
        'avg_item_price_eur' => 150,
    ],
    'home' => [
        'has_variants' => true,
        'variant_fields' => ['Цвят'],
        'variant_presets' => ['Цвят' => ['Бял','Черен','Бежов','Сив','Кафяв','Орех']],
        'units' => ['бр'],
        'typical_fields' => ['Материал', 'Размери ШxДxВ', 'Тегло'],
        'ai_scan_detects' => ['тип мебел/продукт', 'цвят', 'материал'],
        'loss_coefficient' => 0.012,
        'monthly_loss_categories' => ['Транспортни повреди'=>0.35,'Грешки в броенето'=>0.15,'Дефектни върнати'=>0.20,'Обезценяване'=>0.20,'Кражби'=>0.10],
        'avg_items_count' => 200,
        'avg_item_price_eur' => 80,
    ],
    'auto' => [
        'has_variants' => true,
        'variant_fields' => ['Модел кола'],
        'variant_presets' => ['Модел кола' => []],
        'units' => ['бр'],
        'typical_fields' => ['Марка', 'Модел', 'OEM номер', 'Година'],
        'ai_scan_detects' => ['тип част', 'марка', 'номер'],
        'loss_coefficient' => 0.015,
        'monthly_loss_categories' => ['Кражби'=>0.15,'Дефектни върнати'=>0.25,'Остаряване'=>0.20,'Грешки'=>0.20,'Транспортни повреди'=>0.20],
        'avg_items_count' => 500,
        'avg_item_price_eur' => 30,
    ],
    'hobby' => [
        'has_variants' => false,
        'variant_fields' => [],
        'variant_presets' => [],
        'units' => ['бр'],
        'typical_fields' => ['Марка', 'Модел', 'Описание'],
        'ai_scan_detects' => ['тип продукт', 'марка'],
        'loss_coefficient' => 0.020,
        'monthly_loss_categories' => ['Кражби'=>0.25,'Повреди'=>0.20,'Грешки в броенето'=>0.15,'Остаряване'=>0.25,'Връщания'=>0.15],
        'avg_items_count' => 300,
        'avg_item_price_eur' => 25,
    ],
    'kids' => [
        'has_variants' => true,
        'variant_fields' => ['Възраст'],
        'variant_presets' => ['Възраст' => ['0-6м','6-12м','1-2г','2-3г','3-5г','5-7г','7-10г','10-14г']],
        'units' => ['бр'],
        'typical_fields' => ['Марка', 'Възраст', 'Материал'],
        'ai_scan_detects' => ['тип играчка/продукт', 'марка'],
        'loss_coefficient' => 0.025,
        'monthly_loss_categories' => ['Кражби'=>0.20,'Повреди'=>0.25,'Грешки в броенето'=>0.15,'Сезонно обезценяване'=>0.25,'Връщания'=>0.15],
        'avg_items_count' => 400,
        'avg_item_price_eur' => 15,
    ],
    'pets' => [
        'has_variants' => true,
        'variant_fields' => ['Размер', 'Вкус'],
        'variant_presets' => ['Размер' => ['XS','S','M','L','XL'],'Вкус' => []],
        'units' => ['бр', 'кг'],
        'typical_fields' => ['Марка', 'Животно', 'Тегло', 'Срок на годност'],
        'ai_scan_detects' => ['етикет', 'марка', 'тип продукт'],
        'loss_coefficient' => 0.020,
        'monthly_loss_categories' => ['Изтекъл срок'=>0.25,'Повреди'=>0.15,'Кражби'=>0.15,'Грешки в броенето'=>0.20,'Връщания'=>0.25],
        'avg_items_count' => 400,
        'avg_item_price_eur' => 10,
    ],
    'office' => [
        'has_variants' => false,
        'variant_fields' => [],
        'variant_presets' => [],
        'units' => ['бр', 'кутия'],
        'typical_fields' => ['Марка', 'Модел', 'Описание'],
        'ai_scan_detects' => ['тип продукт', 'марка', 'модел'],
        'loss_coefficient' => 0.015,
        'monthly_loss_categories' => ['Кражби'=>0.15,'Повреди'=>0.20,'Грешки в броенето'=>0.20,'Остаряване'=>0.25,'Връщания'=>0.20],
        'avg_items_count' => 500,
        'avg_item_price_eur' => 20,
    ],
    'specialized' => [
        'has_variants' => false,
        'variant_fields' => [],
        'variant_presets' => [],
        'units' => ['бр'],
        'typical_fields' => ['Марка', 'Описание'],
        'ai_scan_detects' => ['тип продукт'],
        'loss_coefficient' => 0.020,
        'monthly_loss_categories' => ['Кражби'=>0.25,'Повреди'=>0.20,'Грешки'=>0.15,'Обезценяване'=>0.25,'Връщания'=>0.15],
        'avg_items_count' => 300,
        'avg_item_price_eur' => 20,
    ],
    'service' => [
        'has_variants' => false,
        'variant_fields' => [],
        'variant_presets' => [],
        'units' => ['бр'],
        'typical_fields' => ['Марка', 'Описание', 'Цена услуга'],
        'ai_scan_detects' => ['тип продукт'],
        'loss_coefficient' => 0.010,
        'monthly_loss_categories' => ['Изтекъл срок'=>0.20,'Повреди'=>0.20,'Кражби'=>0.15,'Грешки'=>0.25,'Обезценяване'=>0.20],
        'avg_items_count' => 150,
        'avg_item_price_eur' => 15,
    ],
];

// ═══════════════════════════════════════════════════════════════
// 300 БИЗНЕС ТИПА — id → [business_type, category_key, overrides]
// category_key реферира към $BIZ_CATEGORY_DEFAULTS
// overrides = само полетата които се различават от дефолта
// ═══════════════════════════════════════════════════════════════
$BIZ_TYPES_RAW = [
    // 1-35: МОДА
    1 => ['Магазин за дамски дрехи','Women\'s clothing store','Мода и облекло','clothing',['avg_item_price_eur'=>35,'loss_coefficient'=>0.035]],
    2 => ['Магазин за мъжки дрехи','Men\'s clothing store','Мода и облекло','clothing',['avg_item_price_eur'=>40]],
    3 => ['Магазин за детски дрехи','Children\'s clothing store','Мода и облекло','clothing',['avg_item_price_eur'=>20,'avg_items_count'=>600,'variant_presets'=>['Размер'=>['86','92','98','104','110','116','122','128','134','140','146','152','158','164'],'Цвят'=>['Черен','Бял','Розов','Син','Червен','Зелен','Жълт']],'typical_fields'=>['Марка','Материал','Сезон','Възраст']]],
    4 => ['Магазин за бебешки дрехи','Baby clothing store','Мода и облекло','clothing',['avg_item_price_eur'=>15,'variant_presets'=>['Размер'=>['56','62','68','74','80','86','92','98'],'Цвят'=>['Бял','Розов','Син','Бежов','Жълт']],'typical_fields'=>['Марка','Материал','Възраст']]],
    5 => ['Магазин за дамски обувки','Women\'s shoe store','Мода и облекло','shoes',['variant_presets'=>['Размер'=>['35','36','37','38','39','40','41','42'],'Цвят'=>['Черен','Бял','Бежов','Червен','Кафяв','Розов']],'avg_item_price_eur'=>55]],
    6 => ['Магазин за мъжки обувки','Men\'s shoe store','Мода и облекло','shoes',['variant_presets'=>['Размер'=>['39','40','41','42','43','44','45','46'],'Цвят'=>['Черен','Кафяв','Син','Сив','Бял']],'avg_item_price_eur'=>65]],
    7 => ['Магазин за детски обувки','Children\'s shoe store','Мода и облекло','shoes',['variant_presets'=>['Размер'=>['20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35'],'Цвят'=>['Черен','Бял','Розов','Син','Червен']],'avg_item_price_eur'=>35,'typical_fields'=>['Марка','Материал','Сезон','Възраст']]],
    8 => ['Магазин за спортни дрехи','Sportswear store','Мода и облекло','clothing',['avg_item_price_eur'=>45,'typical_fields'=>['Марка','Материал','Спорт','Сезон']]],
    9 => ['Магазин за спортни обувки','Sports shoe store','Мода и облекло','shoes',['avg_item_price_eur'=>80,'typical_fields'=>['Марка','Модел','Спорт'],'ai_scan_detects'=>['тип обувка','цвят','марка/лого','модел']]],
    10 => ['Магазин за дамско бельо','Women\'s lingerie store','Мода и облекло','clothing',['avg_item_price_eur'=>25,'avg_items_count'=>600,'variant_presets'=>['Размер'=>['XS','S','M','L','XL','2XL','70A','70B','75A','75B','75C','80A','80B','80C','85B','85C','85D'],'Цвят'=>['Черен','Бял','Бежов','Червен','Розов','Лилав']],'typical_fields'=>['Марка','Материал','Тип'],'ai_scan_detects'=>['тип бельо','цвят','марка/лого']]],
    11 => ['Магазин за мъжко бельо','Men\'s underwear store','Мода и облекло','clothing',['avg_item_price_eur'=>15,'avg_items_count'=>400,'loss_coefficient'=>0.025,'variant_presets'=>['Размер'=>['S','M','L','XL','2XL','3XL'],'Цвят'=>['Черен','Бял','Сив','Син']]]],
    12 => ['Магазин за чорапи','Socks store','Мода и облекло','clothing',['avg_item_price_eur'=>5,'avg_items_count'=>800,'loss_coefficient'=>0.020,'units'=>['чифт','бр'],'variant_presets'=>['Размер'=>['35-38','39-42','43-46'],'Цвят'=>['Черен','Бял','Сив','Син','Цветни']]]],
    13 => ['Бижутериен магазин','Jewelry store','Мода и облекло','clothing',['avg_item_price_eur'=>45,'avg_items_count'=>800,'loss_coefficient'=>0.018,'variant_fields'=>['Размер','Цвят метал'],'variant_presets'=>['Размер'=>['48','50','52','54','56','58','60'],'Цвят метал'=>['Злато','Сребро','Розово злато','Бяло злато']],'typical_fields'=>['Материал','Проба','Камъни','Тегло'],'ai_scan_detects'=>['тип бижу','цвят метал','камъни'],'monthly_loss_categories'=>['Кражби'=>0.40,'Повреди/брак'=>0.10,'Грешки в броенето'=>0.20,'Обезценяване'=>0.15,'Връщания'=>0.15]]],
    14 => ['Магазин за ръчна бижутерия','Handmade jewelry store','Мода и облекло','clothing',['avg_item_price_eur'=>25,'avg_items_count'=>300,'loss_coefficient'=>0.015,'variant_fields'=>['Цвят'],'variant_presets'=>['Цвят'=>[]],'typical_fields'=>['Материал','Стил','Камъни'],'ai_scan_detects'=>['тип бижу','цвят','материал']]],
    15 => ['Магазин за часовници','Watch store','Мода и облекло','electronics',['avg_item_price_eur'=>120,'avg_items_count'=>200,'variant_fields'=>['Цвят каишка'],'variant_presets'=>['Цвят каишка'=>['Черен','Кафяв','Сребрист','Златист']],'typical_fields'=>['Марка','Модел','Механизъм','Водоустойчивост'],'ai_scan_detects'=>['марка','тип часовник','цвят']]],
    16 => ['Магазин за очила','Eyewear store','Мода и облекло','clothing',['avg_item_price_eur'=>80,'avg_items_count'=>300,'loss_coefficient'=>0.015,'variant_fields'=>['Цвят рамка'],'variant_presets'=>['Цвят рамка'=>['Черен','Кафяв','Златист','Сребрист','Син','Червен']],'typical_fields'=>['Марка','Тип','Диоптър'],'ai_scan_detects'=>['тип очила','цвят рамка','марка']]],
    17 => ['Магазин за чанти','Bag store','Мода и облекло','clothing',['avg_item_price_eur'=>50,'avg_items_count'=>300,'variant_fields'=>['Цвят'],'variant_presets'=>['Цвят'=>['Черен','Кафяв','Бежов','Червен','Син','Бял']],'typical_fields'=>['Марка','Материал','Размер','Стил'],'ai_scan_detects'=>['тип чанта','цвят','марка/лого','материал']]],
    18 => ['Магазин за шапки','Hat store','Мода и облекло','clothing',['avg_item_price_eur'=>18,'avg_items_count'=>400,'loss_coefficient'=>0.020,'variant_presets'=>['Размер'=>['S','M','L','XL','One Size'],'Цвят'=>['Черен','Бял','Сив','Син','Червен','Бежов']]]],
    19 => ['Магазин за шалове','Scarf store','Мода и облекло','clothing',['avg_item_price_eur'=>20,'avg_items_count'=>350,'loss_coefficient'=>0.020,'variant_fields'=>['Цвят'],'variant_presets'=>['Цвят'=>['Черен','Бял','Червен','Син','Бежов','Розов']],'typical_fields'=>['Материал','Сезон']]],
    20 => ['Сватбен салон','Bridal shop','Мода и облекло','clothing',['avg_item_price_eur'=>400,'avg_items_count'=>100,'loss_coefficient'=>0.015,'variant_presets'=>['Размер'=>['XS','S','M','L','XL','2XL'],'Цвят'=>['Бял','Слонова кост','Шампанско','Розов']],'typical_fields'=>['Дизайнер','Стил','Дължина'],'ai_scan_detects'=>['тип рокля','цвят','стил'],'monthly_loss_categories'=>['Повреди/брак'=>0.30,'Сезонно обезценяване'=>0.25,'Остаряване на модели'=>0.25,'Връщания'=>0.10,'Грешки'=>0.10]]],
    21 => ['Магазин за абитуриентски рокли','Prom dress store','Мода и облекло','clothing',['avg_item_price_eur'=>200,'avg_items_count'=>150,'loss_coefficient'=>0.020,'variant_presets'=>['Размер'=>['XS','S','M','L','XL'],'Цвят'=>['Черен','Червен','Син','Розов','Златист','Сребрист']],'typical_fields'=>['Дизайнер','Стил','Дължина']]],
    22 => ['Магазин за работно облекло','Workwear store','Мода и облекло','clothing',['avg_item_price_eur'=>30,'variant_presets'=>['Размер'=>['S','M','L','XL','2XL','3XL'],'Цвят'=>['Черен','Син','Сив','Зелен','Оранжев']],'typical_fields'=>['Марка','Материал','Защита']]],
    23 => ['Секонд хенд','Second hand store','Мода и облекло','clothing',['avg_item_price_eur'=>8,'avg_items_count'=>1500,'loss_coefficient'=>0.015,'units'=>['бр','кг'],'variant_fields'=>['Размер'],'variant_presets'=>['Размер'=>['XS','S','M','L','XL','2XL']],'typical_fields'=>['Състояние','Марка','Тип']]],
    24 => ['Vintage мода','Vintage fashion store','Мода и облекло','clothing',['avg_item_price_eur'=>40,'avg_items_count'=>300,'loss_coefficient'=>0.015,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[],'typical_fields'=>['Ера','Състояние','Марка','Материал'],'ai_scan_detects'=>['тип дреха','цвят','стил','ера']]],
    25 => ['Plus size магазин','Plus size store','Мода и облекло','clothing',['avg_item_price_eur'=>40,'variant_presets'=>['Размер'=>['XL','2XL','3XL','4XL','5XL','6XL'],'Цвят'=>['Черен','Бял','Син','Червен','Бежов']]]],
    26 => ['Магазин за спортни аксесоари','Sports accessories store','Мода и облекло','clothing',['avg_item_price_eur'=>25,'loss_coefficient'=>0.025,'typical_fields'=>['Марка','Спорт','Материал']]],
    27 => ['Магазин за кожени якета','Leather jacket store','Мода и облекло','clothing',['avg_item_price_eur'=>180,'avg_items_count'=>150,'loss_coefficient'=>0.020,'variant_presets'=>['Размер'=>['S','M','L','XL','2XL','3XL'],'Цвят'=>['Черен','Кафяв','Бордо','Бежов']],'ai_scan_detects'=>['тип яке','цвят','марка/лого','материал']]],
    28 => ['Магазин за дънки','Denim store','Мода и облекло','clothing',['avg_item_price_eur'=>50,'variant_presets'=>['Размер'=>['28','29','30','31','32','33','34','36','38'],'Цвят'=>['Тъмносин','Светлосин','Черен','Сив']],'typical_fields'=>['Марка','Модел','Кройка']]],
    29 => ['Магазин за тениски с щампи','Printed t-shirt store','Мода и облекло','clothing',['avg_item_price_eur'=>20,'avg_items_count'=>600,'typical_fields'=>['Дизайн','Материал','Щампа']]],
    30 => ['Магазин за бански','Swimwear store','Мода и облекло','clothing',['avg_item_price_eur'=>30]],
    31 => ['Магазин за пижами','Pajama store','Мода и облекло','clothing',['avg_item_price_eur'=>22,'avg_items_count'=>350,'loss_coefficient'=>0.020]],
    32 => ['Магазин за термо бельо','Thermal underwear store','Мода и облекло','clothing',['avg_item_price_eur'=>35,'avg_items_count'=>300,'variant_presets'=>['Размер'=>['S','M','L','XL','2XL'],'Цвят'=>['Черен','Сив','Бял']],'typical_fields'=>['Марка','Материал','Топлинна степен']]],
    33 => ['Магазин за маратонки','Sneaker store','Мода и облекло','shoes',['avg_item_price_eur'=>100,'avg_items_count'=>300,'typical_fields'=>['Марка','Модел','Колекция'],'ai_scan_detects'=>['марка','модел','цвят']]],
    34 => ['Магазин за ортопедични обувки','Orthopedic shoe store','Мода и облекло','shoes',['avg_item_price_eur'=>90,'avg_items_count'=>200,'loss_coefficient'=>0.015,'variant_fields'=>['Размер','Ширина'],'variant_presets'=>['Размер'=>['35','36','37','38','39','40','41','42','43','44','45','46'],'Ширина'=>['Нормална','Широка','Екстра широка']],'typical_fields'=>['Марка','Тип','Стелка']]],
    35 => ['Магазин за танцови обувки','Dance shoe store','Мода и облекло','shoes',['avg_item_price_eur'=>65,'avg_items_count'=>200,'loss_coefficient'=>0.020,'variant_presets'=>['Размер'=>['34','35','36','37','38','39','40','41','42'],'Цвят'=>['Черен','Бежов','Бял','Червен','Сребрист']],'typical_fields'=>['Танц','Марка','Ток']]],

    // 36-60: ХРАНИ
    36 => ['Хранителен магазин','Grocery store','Храни и напитки','food',['avg_items_count'=>2000,'avg_item_price_eur'=>3,'units'=>['бр','кг','литър']]],
    37 => ['Био магазин','Organic food store','Храни и напитки','food',['avg_items_count'=>800,'avg_item_price_eur'=>6,'typical_fields'=>['Марка','Срок на годност','Произход','Сертификат']]],
    38 => ['Месарница','Butcher shop','Храни и напитки','food_fresh',['ai_scan_detects'=>['тип месо'],'typical_fields'=>['Произход','Срок на годност','Вид месо']]],
    39 => ['Рибен магазин','Fish shop','Храни и напитки','food_fresh',['loss_coefficient'=>0.055,'avg_item_price_eur'=>12,'ai_scan_detects'=>['тип риба'],'typical_fields'=>['Произход','Срок на годност','Вид риба']]],
    40 => ['Магазин за плодове и зеленчуци','Fruit & vegetable store','Храни и напитки','food_fresh',['loss_coefficient'=>0.045,'avg_items_count'=>100,'avg_item_price_eur'=>3,'units'=>['кг','гр','бр'],'ai_scan_detects'=>['вид плод/зеленчук'],'typical_fields'=>['Произход','Сезон','Вид']]],
    41 => ['Магазин за млечни продукти','Dairy store','Храни и напитки','food',['loss_coefficient'=>0.040,'avg_items_count'=>150,'avg_item_price_eur'=>4,'typical_fields'=>['Марка','Срок на годност','Произход','Мастни %']]],
    42 => ['Пекарна','Bakery','Храни и напитки','food_fresh',['loss_coefficient'=>0.060,'avg_items_count'=>50,'avg_item_price_eur'=>2,'units'=>['бр','кг'],'ai_scan_detects'=>['тип хляб/печиво'],'typical_fields'=>['Тегло','Състав']]],
    43 => ['Сладкарница','Confectionery','Храни и напитки','food_fresh',['loss_coefficient'=>0.055,'avg_items_count'=>80,'avg_item_price_eur'=>5,'units'=>['бр','кг'],'ai_scan_detects'=>['тип сладкиш'],'typical_fields'=>['Тегло','Състав','Алергени']]],
    44 => ['Магазин за шоколад и бонбони','Chocolate & candy store','Храни и напитки','food',['avg_items_count'=>300,'avg_item_price_eur'=>6]],
    45 => ['Магазин за чай и кафе','Tea & coffee shop','Храни и напитки','food',['loss_coefficient'=>0.020,'avg_items_count'=>250,'avg_item_price_eur'=>8,'typical_fields'=>['Произход','Сорт','Обработка','Грамаж']]],
    46 => ['Магазин за вино и алкохол','Wine & spirits store','Храни и напитки','drinks',['avg_items_count'=>400,'avg_item_price_eur'=>15]],
    47 => ['Магазин за крафт бира','Craft beer store','Храни и напитки','drinks',['avg_items_count'=>200,'avg_item_price_eur'=>5,'typical_fields'=>['Пивоварна','Стил','Алкохол %','Обем']]],
    48 => ['Магазин за ядки и сушени плодове','Nuts & dried fruit store','Храни и напитки','food',['avg_items_count'=>150,'avg_item_price_eur'=>8,'units'=>['кг','гр','бр']]],
    49 => ['Магазин за подправки и деликатеси','Spice & deli store','Храни и напитки','food',['loss_coefficient'=>0.020,'avg_items_count'=>400,'avg_item_price_eur'=>6]],
    50 => ['Веган магазин','Vegan store','Храни и напитки','food',['avg_items_count'=>400,'avg_item_price_eur'=>5,'typical_fields'=>['Марка','Срок на годност','Произход','Сертификат']]],
    51 => ['Магазин за замразени храни','Frozen food store','Храни и напитки','food',['loss_coefficient'=>0.030,'avg_items_count'=>300,'avg_item_price_eur'=>4]],
    52 => ['Магазин за диетични храни','Diet food store','Храни и напитки','food',['avg_items_count'=>300,'avg_item_price_eur'=>6]],
    53 => ['Магазин за бебешки храни','Baby food store','Храни и напитки','food',['avg_items_count'=>200,'avg_item_price_eur'=>4,'typical_fields'=>['Марка','Срок на годност','Възраст','Състав']]],
    54 => ['Магазин за суплементи','Supplement store','Храни и напитки','food',['loss_coefficient'=>0.020,'avg_items_count'=>300,'avg_item_price_eur'=>20]],
    55 => ['Магазин за пчелни продукти','Bee products store','Храни и напитки','food',['loss_coefficient'=>0.015,'avg_items_count'=>100,'avg_item_price_eur'=>12,'units'=>['бр','кг']]],
    56 => ['Магазин за масла','Oil store','Храни и напитки','food',['loss_coefficient'=>0.015,'avg_items_count'=>150,'avg_item_price_eur'=>10,'units'=>['бр','литър']]],
    57 => ['Магазин за сирена','Cheese store','Храни и напитки','food_fresh',['avg_items_count'=>80,'avg_item_price_eur'=>12,'ai_scan_detects'=>['тип сирене','етикет'],'typical_fields'=>['Произход','Срок на годност','Вид','Мастни %']]],
    58 => ['Магазин за колбаси','Deli meat store','Храни и напитки','food_fresh',['avg_items_count'=>60,'avg_item_price_eur'=>10]],
    59 => ['Тортена работилница','Custom cake shop','Храни и напитки','food_fresh',['loss_coefficient'=>0.040,'avg_items_count'=>30,'avg_item_price_eur'=>25,'units'=>['бр'],'ai_scan_detects'=>['тип торта'],'typical_fields'=>['Порции','Състав','Алергени','Повод']]],
    60 => ['Кетъринг','Catering service','Храни и напитки','food',['loss_coefficient'=>0.035,'avg_items_count'=>100,'avg_item_price_eur'=>8]],

    // 61-80: КРАСОТА
    61 => ['Козметичен магазин','Cosmetics store','Красота и здраве','cosmetics',[]],
    62 => ['Магазин за натурална козметика','Natural cosmetics store','Красота и здраве','cosmetics',['typical_fields'=>['Марка','Обем','Състав','Сертификат']]],
    63 => ['Парфюмерия','Perfume store','Красота и здраве','cosmetics',['loss_coefficient'=>0.015,'avg_item_price_eur'=>50,'avg_items_count'=>300,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[],'typical_fields'=>['Марка','Обем','Тип аромат'],'ai_scan_detects'=>['марка','тип','обем']]],
    64 => ['Магазин за фризьорски консумативи','Hairdressing supplies store','Красота и здраве','cosmetics',['typical_fields'=>['Марка','Обем','Тип коса']]],
    65 => ['Магазин за маникюр консумативи','Nail supply store','Красота и здраве','cosmetics',['typical_fields'=>['Марка','Тип','Цвят']]],
    66 => ['Магазин за мъжка козметика','Men\'s grooming store','Красота и здраве','cosmetics',['avg_items_count'=>300]],
    67 => ['Дермокозметика','Dermocosmetics store','Красота и здраве','cosmetics',['avg_item_price_eur'=>25,'typical_fields'=>['Марка','Обем','Тип кожа','Активна съставка']]],
    68 => ['Магазин за етерични масла','Essential oil store','Красота и здраве','cosmetics',['avg_item_price_eur'=>12,'avg_items_count'=>200,'typical_fields'=>['Произход','Обем','Растение']]],
    69 => ['Магазин за соларна козметика','Tanning cosmetics store','Красота и здраве','cosmetics',[]],
    70 => ['Магазин за професионална козметика','Professional cosmetics store','Красота и здраве','cosmetics',['avg_item_price_eur'=>25]],
    71 => ['Магазин за ортопедични пособия','Orthopedic aids store','Красота и здраве','specialized',['avg_item_price_eur'=>50,'typical_fields'=>['Размер','Тип','Марка']]],
    72 => ['Магазин за медицински консумативи','Medical supplies store','Красота и здраве','specialized',['avg_item_price_eur'=>10,'avg_items_count'=>500]],
    73 => ['Оптика','Optician store','Красота и здраве','clothing',['avg_item_price_eur'=>120,'avg_items_count'=>250,'loss_coefficient'=>0.015,'variant_fields'=>['Цвят рамка'],'variant_presets'=>['Цвят рамка'=>['Черен','Кафяв','Златист','Сребрист']],'typical_fields'=>['Марка','Диоптър','Тип'],'ai_scan_detects'=>['тип очила','цвят рамка','марка']]],
    74 => ['Магазин за слухови апарати','Hearing aid store','Красота и здраве','electronics',['avg_item_price_eur'=>300,'avg_items_count'=>50,'loss_coefficient'=>0.010]],
    75 => ['Магазин за билки','Herbal store','Красота и здраве','food',['loss_coefficient'=>0.020,'avg_items_count'=>300,'avg_item_price_eur'=>5,'units'=>['бр','гр','кг'],'typical_fields'=>['Произход','Тегло','Вид билка']]],
    76 => ['Хомеопатия','Homeopathy store','Красота и здраве','specialized',['avg_item_price_eur'=>12,'avg_items_count'=>200]],
    77 => ['Магазин за йога аксесоари','Yoga accessories store','Красота и здраве','hobby',['avg_item_price_eur'=>25,'avg_items_count'=>150]],
    78 => ['Магазин за масажни уреди','Massage equipment store','Красота и здраве','electronics',['avg_item_price_eur'=>60,'avg_items_count'=>100]],
    79 => ['Магазин за козметика за коса','Hair care store','Красота и здраве','cosmetics',['typical_fields'=>['Марка','Обем','Тип коса']]],
    80 => ['Магазин за бръснарски консумативи','Barber supplies store','Красота и здраве','cosmetics',['avg_items_count'=>200]],

    // 81-100: ДОМ
    81 => ['Магазин за мебели','Furniture store','Дом и градина','home',['avg_item_price_eur'=>200,'avg_items_count'=>150,'loss_coefficient'=>0.012,'variant_fields'=>['Цвят','Материал'],'variant_presets'=>['Цвят'=>['Бял','Бежов','Орех','Дъб','Венге','Сив'],'Материал'=>['ПДЧ','МДФ','Масив','Метал']]]],
    82 => ['Магазин за матраци и спално бельо','Mattress & bedding store','Дом и градина','home',['avg_item_price_eur'=>150,'avg_items_count'=>120,'variant_fields'=>['Размер'],'variant_presets'=>['Размер'=>['90x190','90x200','120x200','140x200','160x200','180x200']]]],
    83 => ['Магазин за завеси','Curtain store','Дом и градина','home',['avg_item_price_eur'=>30,'avg_items_count'=>300,'variant_fields'=>['Цвят','Размер'],'variant_presets'=>['Цвят'=>['Бял','Бежов','Сив','Кремав'],'Размер'=>[]],'units'=>['бр','м'],'typical_fields'=>['Материал','Ширина','Дължина']]],
    84 => ['Магазин за килими','Carpet store','Дом и градина','home',['avg_item_price_eur'=>80,'avg_items_count'=>200,'variant_fields'=>['Размер','Цвят'],'variant_presets'=>['Размер'=>['80x150','120x170','160x230','200x290'],'Цвят'=>[]]]],
    85 => ['Магазин за осветление','Lighting store','Дом и градина','home',['avg_item_price_eur'=>45,'avg_items_count'=>300,'variant_fields'=>['Цвят'],'variant_presets'=>['Цвят'=>['Черен','Бял','Златист','Хром','Месинг']]]],
    86 => ['Магазин за домашен текстил','Home textile store','Дом и градина','home',['avg_item_price_eur'=>20,'avg_items_count'=>400,'variant_fields'=>['Цвят','Размер'],'variant_presets'=>['Цвят'=>['Бял','Бежов','Сив','Розов','Син'],'Размер'=>[]]]],
    87 => ['Магазин за декорация','Home decor store','Дом и градина','home',['avg_item_price_eur'=>25,'avg_items_count'=>500,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[]]],
    88 => ['Магазин за свещи','Candle store','Дом и градина','specialized',['avg_item_price_eur'=>10,'avg_items_count'=>300,'has_variants'=>true,'variant_fields'=>['Аромат'],'variant_presets'=>['Аромат'=>['Ванилия','Лавандула','Роза','Цитрус','Бор']],'typical_fields'=>['Аромат','Размер','Време на горене']]],
    89 => ['Магазин за кухненски принадлежности','Kitchenware store','Дом и градина','home',['avg_item_price_eur'=>15,'avg_items_count'=>500,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[]]],
    90 => ['Магазин за порцелан','Porcelain store','Дом и градина','home',['avg_item_price_eur'=>20,'avg_items_count'=>400]],
    91 => ['Магазин за строителни материали','Building materials store','Дом и градина','home',['avg_item_price_eur'=>15,'avg_items_count'=>1000,'loss_coefficient'=>0.015,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[],'units'=>['бр','кг','м'],'typical_fields'=>['Марка','Размер','Тегло']]],
    92 => ['Магазин за бои','Paint store','Дом и градина','home',['avg_item_price_eur'=>15,'avg_items_count'=>300,'has_variants'=>true,'variant_fields'=>['Цвят','Обем'],'variant_presets'=>['Цвят'=>[],'Обем'=>['0.75л','2.5л','5л','10л']],'units'=>['бр','литър']]],
    93 => ['Магазин за инструменти','Tool store','Дом и градина','home',['avg_item_price_eur'=>25,'avg_items_count'=>500,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[],'typical_fields'=>['Марка','Тип','Мощност']]],
    94 => ['Магазин за ВиК части','Plumbing store','Дом и градина','home',['avg_item_price_eur'=>8,'avg_items_count'=>1000,'has_variants'=>true,'variant_fields'=>['Размер'],'variant_presets'=>['Размер'=>['1/2"','3/4"','1"','5/4"','6/4"']],'typical_fields'=>['Материал','Размер','Налягане']]],
    95 => ['Магазин за електроматериали','Electrical supplies store','Дом и градина','home',['avg_item_price_eur'=>5,'avg_items_count'=>1000,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[]]],
    96 => ['Магазин за отоплителна техника','Heating equipment store','Дом и градина','electronics',['avg_item_price_eur'=>200,'avg_items_count'=>100,'loss_coefficient'=>0.010]],
    97 => ['Магазин за врати и прозорци','Door & window store','Дом и градина','home',['avg_item_price_eur'=>250,'avg_items_count'=>80,'loss_coefficient'=>0.010,'variant_fields'=>['Цвят','Размер'],'variant_presets'=>['Цвят'=>['Бял','Златен дъб','Орех','Махагон'],'Размер'=>[]]]],
    98 => ['Магазин за подови покрития','Flooring store','Дом и градина','home',['avg_item_price_eur'=>20,'avg_items_count'=>200,'variant_fields'=>['Цвят'],'variant_presets'=>['Цвят'=>[]],'units'=>['кв.м','бр'],'typical_fields'=>['Материал','Клас','Дебелина']]],
    99 => ['Магазин за градинска техника','Garden equipment store','Дом и градина','home',['avg_item_price_eur'=>80,'avg_items_count'=>200,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[],'typical_fields'=>['Марка','Мощност','Тип']]],
    100 => ['Магазин за семена','Seed store','Дом и градина','food',['loss_coefficient'=>0.020,'avg_items_count'=>400,'avg_item_price_eur'=>2,'typical_fields'=>['Сорт','Период на засяване','Произход']]],

    // 101-125: ЕЛЕКТРОНИКА
    101 => ['Магазин за телефони','Phone store','Електроника','electronics',['avg_item_price_eur'=>300,'variant_fields'=>['Памет','Цвят'],'variant_presets'=>['Памет'=>['64GB','128GB','256GB','512GB','1TB'],'Цвят'=>['Черен','Бял','Син','Червен','Зелен','Лилав']]]],
    102 => ['Магазин за компютри','Computer store','Електроника','electronics',['avg_item_price_eur'=>500,'avg_items_count'=>100]],
    103 => ['Магазин за таблети','Tablet store','Електроника','electronics',['avg_item_price_eur'=>250,'variant_fields'=>['Памет','Цвят'],'variant_presets'=>['Памет'=>['64GB','128GB','256GB'],'Цвят'=>['Черен','Бял','Сив','Син']]]],
    104 => ['Магазин за телевизори','TV store','Електроника','electronics',['avg_item_price_eur'=>400,'avg_items_count'=>80,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[],'typical_fields'=>['Марка','Размер','Резолюция']]],
    105 => ['Магазин за фототехника','Photo equipment store','Електроника','electronics',['avg_item_price_eur'=>350,'avg_items_count'=>150]],
    106 => ['Гейминг магазин','Gaming store','Електроника','electronics',['avg_item_price_eur'=>50,'avg_items_count'=>400]],
    107 => ['Магазин за дронове','Drone store','Електроника','electronics',['avg_item_price_eur'=>300,'avg_items_count'=>80]],
    108 => ['Магазин за компоненти','Computer components store','Електроника','electronics',['avg_item_price_eur'=>60,'avg_items_count'=>500,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[]]],
    109 => ['Магазин за принтери','Printer store','Електроника','electronics',['avg_item_price_eur'=>150,'avg_items_count'=>100]],
    110 => ['Smart home магазин','Smart home store','Електроника','electronics',['avg_item_price_eur'=>40,'avg_items_count'=>300]],
    111 => ['Магазин за електрически тротинетки','E-scooter store','Електроника','electronics',['avg_item_price_eur'=>400,'avg_items_count'=>50]],
    112 => ['Магазин за батерии','Battery store','Електроника','electronics',['avg_item_price_eur'=>5,'avg_items_count'=>500,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[]]],
    113 => ['Магазин за кабели','Cable store','Електроника','electronics',['avg_item_price_eur'=>8,'avg_items_count'=>400,'has_variants'=>true,'variant_fields'=>['Дължина'],'variant_presets'=>['Дължина'=>['0.5м','1м','2м','3м','5м']]]],
    114 => ['Магазин за калъфи','Phone case store','Електроника','electronics',['avg_item_price_eur'=>12,'avg_items_count'=>600,'variant_fields'=>['Модел телефон','Цвят'],'variant_presets'=>['Модел телефон'=>[],'Цвят'=>['Черен','Бял','Прозрачен','Син','Розов']]]],
    115 => ['Refurbished магазин','Refurbished electronics store','Електроника','electronics',['avg_item_price_eur'=>200,'typical_fields'=>['Марка','Модел','Състояние','Гаранция']]],
    116 => ['LED магазин','LED store','Електроника','electronics',['avg_item_price_eur'=>10,'avg_items_count'=>400]],
    117 => ['Магазин за видеонаблюдение','CCTV store','Електроника','electronics',['avg_item_price_eur'=>80,'avg_items_count'=>200]],
    118 => ['Магазин за малки уреди','Small appliance store','Електроника','electronics',['avg_item_price_eur'=>40,'avg_items_count'=>300]],
    119 => ['Магазин за големи уреди','Large appliance store','Електроника','electronics',['avg_item_price_eur'=>400,'avg_items_count'=>80]],
    120 => ['Магазин за климатици','AC store','Електроника','electronics',['avg_item_price_eur'=>500,'avg_items_count'=>50]],
    121 => ['Магазин за соларни панели','Solar panel store','Електроника','electronics',['avg_item_price_eur'=>200,'avg_items_count'=>80]],
    122 => ['Магазин за 3D принтери','3D printer store','Електроника','electronics',['avg_item_price_eur'=>300,'avg_items_count'=>50]],
    123 => ['Магазин за радио електроника','Radio electronics store','Електроника','electronics',['avg_item_price_eur'=>15,'avg_items_count'=>500,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[]]],
    124 => ['Магазин за роботика','Robotics store','Електроника','electronics',['avg_item_price_eur'=>60,'avg_items_count'=>150]],
    125 => ['Магазин за озвучаване','Audio equipment store','Електроника','electronics',['avg_item_price_eur'=>100,'avg_items_count'=>200]],

    // 126-145: АВТО
    126 => ['Магазин за нови авточасти','New auto parts store','Авто и мото','auto',[]],
    127 => ['Магазин за авточасти втора употреба','Used auto parts store','Авто и мото','auto',['avg_item_price_eur'=>20,'typical_fields'=>['Марка кола','Модел','Година','Състояние']]],
    128 => ['Магазин за автоаксесоари','Car accessories store','Авто и мото','auto',['avg_item_price_eur'=>20,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[]]],
    129 => ['Магазин за гуми и джанти','Tire & wheel store','Авто и мото','auto',['avg_item_price_eur'=>80,'avg_items_count'=>300,'variant_fields'=>['Размер'],'variant_presets'=>['Размер'=>['185/65R15','195/65R15','205/55R16','225/45R17','235/40R18']],'typical_fields'=>['Марка','Размер','Сезон','DOT']]],
    130 => ['Магазин за автокозметика','Car care store','Авто и мото','auto',['avg_item_price_eur'=>10,'avg_items_count'=>300,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[]]],
    131 => ['Магазин за масла','Oil store','Авто и мото','auto',['avg_item_price_eur'=>15,'avg_items_count'=>200,'has_variants'=>true,'variant_fields'=>['Вискозитет','Обем'],'variant_presets'=>['Вискозитет'=>['5W-30','5W-40','10W-40','0W-20'],'Обем'=>['1л','4л','5л']]]],
    132 => ['Магазин за автоелектроника','Car electronics store','Авто и мото','electronics',['avg_item_price_eur'=>50,'avg_items_count'=>200]],
    133 => ['Магазин за мото части','Motorcycle parts store','Авто и мото','auto',['avg_item_price_eur'=>40]],
    134 => ['Магазин за мото облекло','Motorcycle clothing store','Авто и мото','clothing',['avg_item_price_eur'=>120,'avg_items_count'=>200,'typical_fields'=>['Марка','Защита','Материал']]],
    135 => ['Магазин за велосипеди','Bicycle store','Авто и мото','auto',['avg_item_price_eur'=>350,'avg_items_count'=>80,'variant_fields'=>['Размер рамка','Цвят'],'variant_presets'=>['Размер рамка'=>['XS','S','M','L','XL'],'Цвят'=>['Черен','Бял','Червен','Син','Зелен']],'typical_fields'=>['Марка','Тип','Скорости','Размер колело']]],
    136 => ['Магазин за вело части','Bicycle parts store','Авто и мото','auto',['avg_item_price_eur'=>15,'avg_items_count'=>500]],
    137 => ['Магазин за скутери и ATV','Scooter & ATV store','Авто и мото','auto',['avg_item_price_eur'=>600,'avg_items_count'=>30]],
    138 => ['Магазин за камиони','Truck parts store','Авто и мото','auto',['avg_item_price_eur'=>60,'avg_items_count'=>400]],
    139 => ['Тунинг магазин','Tuning store','Авто и мото','auto',['avg_item_price_eur'=>80,'avg_items_count'=>300]],
    140 => ['Магазин за лодки','Boat store','Авто и мото','auto',['avg_item_price_eur'=>500,'avg_items_count'=>50]],
    141 => ['Магазин за багажници','Roof rack store','Авто и мото','auto',['avg_item_price_eur'=>100,'avg_items_count'=>100]],
    142 => ['Магазин за автотапицерия','Car upholstery store','Авто и мото','auto',['avg_item_price_eur'=>30,'avg_items_count'=>200]],
    143 => ['Магазин за авто ароматизатори','Car air freshener store','Авто и мото','auto',['avg_item_price_eur'=>5,'avg_items_count'=>300,'has_variants'=>true,'variant_fields'=>['Аромат'],'variant_presets'=>['Аромат'=>['Ванилия','Лимон','Нов автомобил','Горски плодове','Бор']]]],
    144 => ['Магазин за автоинструменти','Auto tool store','Авто и мото','auto',['avg_item_price_eur'=>30,'avg_items_count'=>400]],
    145 => ['Магазин за автостъкла','Auto glass store','Авто и мото','auto',['avg_item_price_eur'=>120,'avg_items_count'=>100]],

    // 146-175: ХОБИ
    146 => ['Книжарница','Bookstore','Хоби и свободно време','hobby',['avg_item_price_eur'=>12,'avg_items_count'=>1000,'typical_fields'=>['Автор','Издателство','ISBN','Жанр'],'ai_scan_detects'=>['заглавие','автор','корица']]],
    147 => ['Канцеларски магазин','Stationery store','Хоби и свободно време','office',['avg_items_count'=>800,'avg_item_price_eur'=>5]],
    148 => ['Магазин за арт материали','Art supplies store','Хоби и свободно време','hobby',['avg_item_price_eur'=>10,'avg_items_count'=>500]],
    149 => ['DIY/Handmade магазин','DIY/Handmade store','Хоби и свободно време','hobby',['avg_item_price_eur'=>8,'avg_items_count'=>600]],
    150 => ['Магазин за прежди','Yarn store','Хоби и свободно време','hobby',['avg_item_price_eur'=>6,'avg_items_count'=>400,'has_variants'=>true,'variant_fields'=>['Цвят'],'variant_presets'=>['Цвят'=>[]],'units'=>['бр','кг'],'typical_fields'=>['Материал','Тегло','Дължина нишка']]],
    151 => ['Магазин за шевни машини','Sewing machine store','Хоби и свободно време','electronics',['avg_item_price_eur'=>250,'avg_items_count'=>50]],
    152 => ['Магазин за музикални инструменти','Music store','Хоби и свободно време','hobby',['avg_item_price_eur'=>200,'avg_items_count'=>200,'typical_fields'=>['Марка','Тип','Материал']]],
    153 => ['Фитнес магазин','Fitness equipment store','Хоби и свободно време','hobby',['avg_item_price_eur'=>80,'avg_items_count'=>200]],
    154 => ['Къмпинг магазин','Camping store','Хоби и свободно време','hobby',['avg_item_price_eur'=>40,'avg_items_count'=>400]],
    155 => ['Магазин за лов и риболов','Hunting & fishing store','Хоби и свободно време','hobby',['avg_item_price_eur'=>40,'avg_items_count'=>500]],
    156 => ['Магазин за алпинизъм','Climbing store','Хоби и свободно време','hobby',['avg_item_price_eur'=>50]],
    157 => ['Магазин за водни спортове','Water sports store','Хоби и свободно време','hobby',['avg_item_price_eur'=>60,'avg_items_count'=>200]],
    158 => ['Ски и сноуборд магазин','Ski & snowboard store','Хоби и свободно време','hobby',['avg_item_price_eur'=>120,'avg_items_count'=>200,'has_variants'=>true,'variant_fields'=>['Размер'],'variant_presets'=>['Размер'=>['140','145','150','155','160','165','170']],'typical_fields'=>['Марка','Ниво','Сезон']]],
    159 => ['Тенис магазин','Tennis store','Хоби и свободно време','hobby',['avg_item_price_eur'=>50,'avg_items_count'=>200]],
    160 => ['Голф магазин','Golf store','Хоби и свободно време','hobby',['avg_item_price_eur'=>100,'avg_items_count'=>200]],
    161 => ['Магазин за настолни игри','Board game store','Хоби и свободно време','hobby',['avg_item_price_eur'=>25,'avg_items_count'=>300]],
    162 => ['Магазин за модели и макети','Model & hobby store','Хоби и свободно време','hobby',['avg_item_price_eur'=>30,'avg_items_count'=>300]],
    163 => ['Колекционерски магазин','Collectibles store','Хоби и свободно време','hobby',['avg_item_price_eur'=>50,'avg_items_count'=>500]],
    164 => ['Магазин за комикси','Comic book store','Хоби и свободно време','hobby',['avg_item_price_eur'=>10,'avg_items_count'=>800]],
    165 => ['Магазин за винил','Vinyl record store','Хоби и свободно време','hobby',['avg_item_price_eur'=>25,'avg_items_count'=>500]],
    166 => ['Магазин за фото аксесоари','Photo accessories store','Хоби и свободно време','hobby',['avg_item_price_eur'=>30]],
    167 => ['Магазин за телескопи','Telescope store','Хоби и свободно време','electronics',['avg_item_price_eur'=>200,'avg_items_count'=>50]],
    168 => ['Пчеларски магазин','Beekeeping store','Хоби и свободно време','hobby',['avg_item_price_eur'=>20,'avg_items_count'=>200]],
    169 => ['Магазин за градинарство','Garden store','Хоби и свободно време','hobby',['avg_item_price_eur'=>10,'avg_items_count'=>500]],
    170 => ['Магазин за аквариуми','Aquarium store','Хоби и свободно време','pets',['avg_item_price_eur'=>20,'avg_items_count'=>300,'typical_fields'=>['Обем','Материал','Тип']]],
    171 => ['Магазин за терариуми','Terrarium store','Хоби и свободно време','pets',['avg_item_price_eur'=>25,'avg_items_count'=>150]],
    172 => ['Магазин за птици','Bird store','Хоби и свободно време','pets',['avg_item_price_eur'=>15,'avg_items_count'=>200]],
    173 => ['Магазин за дартс и билярд','Darts & pool store','Хоби и свободно време','hobby',['avg_item_price_eur'=>30,'avg_items_count'=>200]],
    174 => ['Скейтборд магазин','Skateboard store','Хоби и свободно време','hobby',['avg_item_price_eur'=>60,'avg_items_count'=>200,'has_variants'=>true,'variant_fields'=>['Размер'],'variant_presets'=>['Размер'=>['7.75"','8.0"','8.25"','8.5"']]]],
    175 => ['Еърсофт магазин','Airsoft store','Хоби и свободно време','hobby',['avg_item_price_eur'=>80,'avg_items_count'=>300]],

    // 176-200: ДЕЦА
    176 => ['Магазин за играчки','Toy store','Деца','kids',['avg_item_price_eur'=>15,'avg_items_count'=>600]],
    177 => ['Магазин за образователни играчки','Educational toy store','Деца','kids',['avg_item_price_eur'=>20]],
    178 => ['Магазин за бебешки аксесоари','Baby accessories store','Деца','kids',['avg_item_price_eur'=>25,'avg_items_count'=>500]],
    179 => ['Магазин за бебешки дрехи','Baby clothing store (retail)','Деца','clothing',['avg_item_price_eur'=>15,'variant_presets'=>['Размер'=>['56','62','68','74','80','86','92','98'],'Цвят'=>['Бял','Розов','Син','Бежов']]]],
    180 => ['Магазин за детски книги','Children\'s bookstore','Деца','hobby',['avg_item_price_eur'=>8,'avg_items_count'=>500]],
    181 => ['Магазин за рожденни дни','Birthday party store','Деца','specialized',['avg_item_price_eur'=>3,'avg_items_count'=>500]],
    182 => ['Магазин за детски костюми','Children\'s costume store','Деца','clothing',['avg_item_price_eur'=>20,'avg_items_count'=>300,'variant_presets'=>['Размер'=>['2-3г','4-5г','6-7г','8-10г','10-12г'],'Цвят'=>[]]]],
    183 => ['Магазин за детски мебели','Children\'s furniture store','Деца','home',['avg_item_price_eur'=>100,'avg_items_count'=>100]],
    184 => ['Магазин за детски спорт','Children\'s sports store','Деца','kids',['avg_item_price_eur'=>20]],
    185 => ['LEGO магазин','LEGO store','Деца','kids',['avg_item_price_eur'=>40,'avg_items_count'=>300,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[],'typical_fields'=>['Серия','Брой части','Възраст']]],
    186 => ['Магазин за кукли','Doll store','Деца','kids',['avg_item_price_eur'=>20]],
    187 => ['Магазин за електронни играчки','Electronic toy store','Деца','kids',['avg_item_price_eur'=>30]],
    188 => ['Магазин за бебешка хигиена','Baby hygiene store','Деца','kids',['avg_item_price_eur'=>8,'avg_items_count'=>300,'has_variants'=>false,'variant_fields'=>[],'variant_presets'=>[]]],
    189 => ['Магазин за ученически пособия','School supplies store','Деца','office',['avg_item_price_eur'=>5,'avg_items_count'=>600]],
    190 => ['Магазин за детска безопасност','Child safety store','Деца','kids',['avg_item_price_eur'=>15]],
    191 => ['Магазин за детски велосипеди','Children\'s bicycle store','Деца','auto',['avg_item_price_eur'=>120,'avg_items_count'=>80,'variant_fields'=>['Размер колело'],'variant_presets'=>['Размер колело'=>['12"','14"','16"','20"','24"']]]],
    192 => ['Магазин за детски обувки','Children\'s shoe store (retail)','Деца','shoes',['avg_item_price_eur'=>30,'variant_presets'=>['Размер'=>['20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35'],'Цвят'=>['Черен','Бял','Розов','Син']]]],
    193 => ['Магазин за бебешки текстил','Baby textile store','Деца','home',['avg_item_price_eur'=>12,'avg_items_count'=>300]],
    194 => ['Магазин за детски очила','Children\'s eyewear store','Деца','clothing',['avg_item_price_eur'=>60,'avg_items_count'=>150,'variant_fields'=>['Цвят рамка'],'variant_presets'=>['Цвят рамка'=>['Черен','Син','Розов','Червен','Зелен']]]],
    195 => ['Магазин за детски часовници','Children\'s watch store','Деца','kids',['avg_item_price_eur'=>20,'avg_items_count'=>200]],
    196 => ['Магазин за парти аксесоари','Party accessories store','Деца','specialized',['avg_item_price_eur'=>3,'avg_items_count'=>600]],
    197 => ['Магазин за детски сувенири','Children\'s souvenir store','Деца','specialized',['avg_item_price_eur'=>5]],
    198 => ['Магазин за детски палатки','Children\'s tent store','Деца','kids',['avg_item_price_eur'=>35,'avg_items_count'=>100]],
    199 => ['Магазин за водни играчки','Water toy store','Деца','kids',['avg_item_price_eur'=>10,'avg_items_count'=>300]],
    200 => ['Магазин за бебешка храна','Baby food store (retail)','Деца','food',['avg_items_count'=>200,'avg_item_price_eur'=>4]],

    // 201-220: ДОМАШНИ ЛЮБИМЦИ
    201 => ['Зоомагазин','Pet store','Домашни любимци','pets',[]],
    202 => ['Магазин за храна за кучета','Dog food store','Домашни любимци','pets',['avg_item_price_eur'=>15]],
    203 => ['Магазин за храна за котки','Cat food store','Домашни любимци','pets',['avg_item_price_eur'=>12]],
    204 => ['Магазин за аксесоари за кучета','Dog accessories store','Домашни любимци','pets',['has_variants'=>true,'variant_fields'=>['Размер'],'variant_presets'=>['Размер'=>['XS','S','M','L','XL']]]],
    205 => ['Магазин за аксесоари за котки','Cat accessories store','Домашни любимци','pets',[]],
    206 => ['Аквариумистика','Aquarium supplies store','Домашни любимци','pets',['avg_item_price_eur'=>15]],
    207 => ['Магазин за птици','Bird supplies store','Домашни любимци','pets',['avg_item_price_eur'=>10]],
    208 => ['Магазин за гризачи','Rodent supplies store','Домашни любимци','pets',['avg_item_price_eur'=>8]],
    209 => ['Магазин за влечуги','Reptile supplies store','Домашни любимци','pets',['avg_item_price_eur'=>20]],
    210 => ['Магазин за ветеринарни продукти','Veterinary products store','Домашни любимци','pets',['avg_item_price_eur'=>15]],
    211 => ['Грууминг магазин','Grooming supplies store','Домашни любимци','pets',['avg_item_price_eur'=>12]],
    212 => ['Магазин за дрехи за кучета','Dog clothing store','Домашни любимци','pets',['has_variants'=>true,'variant_fields'=>['Размер'],'variant_presets'=>['Размер'=>['XS','S','M','L','XL']],'avg_item_price_eur'=>15]],
    213 => ['Магазин за органична храна за любимци','Organic pet food store','Домашни любимци','pets',['avg_item_price_eur'=>18]],
    214 => ['Магазин за тренировъчни аксесоари','Pet training store','Домашни любимци','pets',[]],
    215 => ['Магазин за пътни аксесоари за любимци','Pet travel store','Домашни любимци','pets',[]],
    216 => ['Козметика за любимци','Pet cosmetics store','Домашни любимци','pets',['avg_item_price_eur'=>10]],
    217 => ['Магазин за лакомства за любимци','Pet treats store','Домашни любимци','pets',['avg_item_price_eur'=>5]],
    218 => ['Магазин за GPS тракери за любимци','Pet GPS tracker store','Домашни любимци','electronics',['avg_item_price_eur'=>40,'avg_items_count'=>50]],
    219 => ['Мемориални продукти за любимци','Pet memorial store','Домашни любимци','specialized',['avg_item_price_eur'=>25,'avg_items_count'=>100]],
    220 => ['Застраховки за любимци','Pet insurance store','Домашни любимци','service',[]],

    // 221-240: ОФИС
    221 => ['Магазин за офис мебели','Office furniture store','Офис и бизнес','home',['avg_item_price_eur'=>150,'avg_items_count'=>150]],
    222 => ['Магазин за офис техника','Office equipment store','Офис и бизнес','electronics',['avg_item_price_eur'=>100]],
    223 => ['Магазин за офис консумативи','Office supplies store','Офис и бизнес','office',['avg_items_count'=>800]],
    224 => ['Магазин за печати и визитки','Stamp & business card store','Офис и бизнес','office',['avg_item_price_eur'=>10]],
    225 => ['Магазин за рекламни материали','Promotional materials store','Офис и бизнес','office',['avg_item_price_eur'=>5]],
    226 => ['Магазин за опаковки','Packaging store','Офис и бизнес','office',['avg_item_price_eur'=>3,'avg_items_count'=>600]],
    227 => ['Магазин за етикети','Label store','Офис и бизнес','office',['avg_item_price_eur'=>5]],
    228 => ['Магазин за POS системи','POS systems store','Офис и бизнес','electronics',['avg_item_price_eur'=>200,'avg_items_count'=>50]],
    229 => ['Магазин за работно облекло bulk','Bulk workwear store','Офис и бизнес','clothing',['avg_item_price_eur'=>15,'avg_items_count'=>500]],
    230 => ['Магазин за ЛПС','PPE store','Офис и бизнес','specialized',['avg_item_price_eur'=>8,'avg_items_count'=>400]],
    231 => ['Магазин за почистващи препарати','Cleaning products store','Офис и бизнес','office',['avg_item_price_eur'=>5,'avg_items_count'=>400]],
    232 => ['Магазин за хигиенни продукти bulk','Bulk hygiene store','Офис и бизнес','office',['avg_item_price_eur'=>8]],
    233 => ['Вендинг магазин','Vending machine store','Офис и бизнес','electronics',['avg_item_price_eur'=>1500,'avg_items_count'=>20]],
    234 => ['Хотелски консумативи','Hotel supplies store','Офис и бизнес','office',['avg_item_price_eur'=>5,'avg_items_count'=>500]],
    235 => ['Ресторантско оборудване','Restaurant equipment store','Офис и бизнес','electronics',['avg_item_price_eur'=>200,'avg_items_count'=>100]],
    236 => ['Бар оборудване','Bar equipment store','Офис и бизнес','electronics',['avg_item_price_eur'=>80,'avg_items_count'=>150]],
    237 => ['Хладилно оборудване','Refrigeration equipment store','Офис и бизнес','electronics',['avg_item_price_eur'=>500,'avg_items_count'=>30]],
    238 => ['Медицинско оборудване','Medical equipment store','Офис и бизнес','electronics',['avg_item_price_eur'=>200,'avg_items_count'=>100]],
    239 => ['Стоматологично оборудване','Dental equipment store','Офис и бизнес','electronics',['avg_item_price_eur'=>300,'avg_items_count'=>80]],
    240 => ['Фризьорско оборудване','Hair salon equipment store','Офис и бизнес','electronics',['avg_item_price_eur'=>60,'avg_items_count'=>200]],

    // 241-275: СПЕЦИАЛИЗИРАНИ
    241 => ['Еротичен магазин','Adult store','Специализирани','specialized',['avg_item_price_eur'=>25]],
    242 => ['Вейп магазин','Vape store','Специализирани','specialized',['avg_item_price_eur'=>15,'avg_items_count'=>400,'has_variants'=>true,'variant_fields'=>['Никотин','Вкус'],'variant_presets'=>['Никотин'=>['0mg','3mg','6mg','12mg','18mg'],'Вкус'=>[]]]],
    243 => ['Тютюнев магазин','Tobacco store','Специализирани','specialized',['avg_item_price_eur'=>8]],
    244 => ['Магазин за подаръци','Gift store','Специализирани','specialized',['avg_item_price_eur'=>15,'avg_items_count'=>500]],
    245 => ['Цветарски магазин','Flower shop','Специализирани','food_fresh',['loss_coefficient'=>0.060,'avg_items_count'=>100,'avg_item_price_eur'=>5,'units'=>['бр','букет'],'ai_scan_detects'=>['вид цвете','цвят'],'typical_fields'=>['Вид','Цвят','Произход']]],
    246 => ['Магазин за сувенири','Souvenir store','Специализирани','specialized',['avg_item_price_eur'=>8,'avg_items_count'=>600]],
    247 => ['Магазин за религиозни стоки','Religious goods store','Специализирани','specialized',['avg_item_price_eur'=>10]],
    248 => ['Магазин за пиротехника','Fireworks store','Специализирани','specialized',['avg_item_price_eur'=>8,'avg_items_count'=>300,'loss_coefficient'=>0.015]],
    249 => ['Антиквариат','Antique store','Специализирани','specialized',['avg_item_price_eur'=>100,'avg_items_count'=>200,'has_variants'=>false]],
    250 => ['Магазин за тъкани','Fabric store','Специализирани','specialized',['avg_item_price_eur'=>12,'avg_items_count'=>400,'has_variants'=>true,'variant_fields'=>['Цвят'],'variant_presets'=>['Цвят'=>[]],'units'=>['м','см'],'typical_fields'=>['Ширина','Материал','Състав']]],
    251 => ['Галантерия','Haberdashery store','Специализирани','specialized',['avg_item_price_eur'=>3,'avg_items_count'=>1000]],
    252 => ['Магазин за ключове и брави','Lock & key store','Специализирани','specialized',['avg_item_price_eur'=>15,'avg_items_count'=>300]],
    253 => ['Магазин за знамена','Flag store','Специализирани','specialized',['avg_item_price_eur'=>15,'avg_items_count'=>100]],
    254 => ['Магазин за куфари','Luggage store','Специализирани','specialized',['avg_item_price_eur'=>60,'avg_items_count'=>150,'has_variants'=>true,'variant_fields'=>['Размер','Цвят'],'variant_presets'=>['Размер'=>['S','M','L'],'Цвят'=>['Черен','Син','Червен','Сив']]]],
    255 => ['Магазин за чадъри','Umbrella store','Специализирани','specialized',['avg_item_price_eur'=>15,'avg_items_count'=>200]],
    256 => ['Магазин за раници','Backpack store','Специализирани','clothing',['avg_item_price_eur'=>30,'avg_items_count'=>200,'variant_fields'=>['Цвят'],'variant_presets'=>['Цвят'=>['Черен','Син','Червен','Зелен','Сив']]]],
    257 => ['Магазин за каски','Helmet store','Специализирани','auto',['avg_item_price_eur'=>60,'avg_items_count'=>150,'variant_fields'=>['Размер','Цвят'],'variant_presets'=>['Размер'=>['XS','S','M','L','XL'],'Цвят'=>['Черен','Бял','Червен','Син']]]],
    258 => ['Магазин за тротинетки','Scooter store','Специализирани','electronics',['avg_item_price_eur'=>200,'avg_items_count'=>80]],
    259 => ['Оръжеен магазин','Weapons store','Специализирани','specialized',['avg_item_price_eur'=>300,'avg_items_count'=>150,'loss_coefficient'=>0.010]],
    260 => ['Магазин за ножове','Knife store','Специализирани','specialized',['avg_item_price_eur'=>30,'avg_items_count'=>300]],
    261 => ['Магазин за палатки','Tent store','Специализирани','hobby',['avg_item_price_eur'=>80,'avg_items_count'=>100]],
    262 => ['Фермерски магазин','Farm store','Специализирани','food',['avg_items_count'=>200,'avg_item_price_eur'=>5]],
    263 => ['Магазин за органични продукти','Organic products store','Специализирани','food',['avg_items_count'=>400,'avg_item_price_eur'=>6]],
    264 => ['Магазин за безглутенови продукти','Gluten-free store','Специализирани','food',['avg_items_count'=>300,'avg_item_price_eur'=>5]],
    265 => ['Магазин за спортно хранене','Sports nutrition store','Специализирани','food',['loss_coefficient'=>0.020,'avg_items_count'=>250,'avg_item_price_eur'=>20]],
    266 => ['Магазин за еспресо машини','Espresso machine store','Специализирани','electronics',['avg_item_price_eur'=>250,'avg_items_count'=>60]],
    267 => ['Магазин за прахосмукачки','Vacuum cleaner store','Специализирани','electronics',['avg_item_price_eur'=>150,'avg_items_count'=>80]],
    268 => ['Магазин за перални','Washing machine store','Специализирани','electronics',['avg_item_price_eur'=>400,'avg_items_count'=>40]],
    269 => ['Магазин за батерии','Battery store (all types)','Специализирани','electronics',['avg_item_price_eur'=>5,'avg_items_count'=>500]],
    270 => ['Магазин за крушки','Light bulb store','Специализирани','electronics',['avg_item_price_eur'=>5,'avg_items_count'=>300]],
    271 => ['Магазин за тапети','Wallpaper store','Специализирани','home',['avg_item_price_eur'=>15,'avg_items_count'=>300,'has_variants'=>true,'variant_fields'=>['Дизайн'],'variant_presets'=>['Дизайн'=>[]],'units'=>['ролка','м']]],
    272 => ['Магазин за санитария','Sanitary store','Специализирани','home',['avg_item_price_eur'=>80,'avg_items_count'=>200]],
    273 => ['Магазин за басейни','Pool store','Специализирани','home',['avg_item_price_eur'=>200,'avg_items_count'=>80]],
    274 => ['Магазин за огради','Fence store','Специализирани','home',['avg_item_price_eur'=>20,'avg_items_count'=>200,'units'=>['бр','м']]],
    275 => ['Палети B2B','Pallet B2B store','Специализирани','specialized',['avg_item_price_eur'=>500,'avg_items_count'=>50]],

    // 276-300: УСЛУГИ+ПРОДУКТИ
    276 => ['Фризьорски салон','Hair salon','Услуги+Продукти','service',[]],
    277 => ['Козметичен салон','Beauty salon','Услуги+Продукти','service',[]],
    278 => ['Тату студио','Tattoo studio','Услуги+Продукти','service',['avg_item_price_eur'=>20,'typical_fields'=>['Марка','Цвят','Тип']]],
    279 => ['Фитнес зала','Gym','Услуги+Продукти','service',['avg_item_price_eur'=>25]],
    280 => ['Йога студио','Yoga studio','Услуги+Продукти','service',['avg_item_price_eur'=>20]],
    281 => ['Фото студио','Photo studio','Услуги+Продукти','service',['avg_item_price_eur'=>30]],
    282 => ['Ателие за рокли','Dress atelier','Услуги+Продукти','service',['avg_item_price_eur'=>100,'typical_fields'=>['Тип','Материал','Размер']]],
    283 => ['Ателие за обувки','Shoe atelier','Услуги+Продукти','service',['avg_item_price_eur'=>80]],
    284 => ['Автосервиз','Auto repair shop','Услуги+Продукти','auto',['avg_item_price_eur'=>20,'avg_items_count'=>500]],
    285 => ['Велосервиз','Bicycle repair shop','Услуги+Продукти','auto',['avg_item_price_eur'=>10,'avg_items_count'=>200]],
    286 => ['GSM сервиз','Phone repair shop','Услуги+Продукти','electronics',['avg_item_price_eur'=>15,'avg_items_count'=>300]],
    287 => ['Компютърен сервиз','Computer repair shop','Услуги+Продукти','electronics',['avg_item_price_eur'=>20,'avg_items_count'=>200]],
    288 => ['Печатница','Print shop','Услуги+Продукти','office',['avg_item_price_eur'=>5,'avg_items_count'=>200]],
    289 => ['Копирен център','Copy center','Услуги+Продукти','office',['avg_item_price_eur'=>3]],
    290 => ['Химическо чистене','Dry cleaning','Услуги+Продукти','service',[]],
    291 => ['Ключарски услуги','Locksmith service','Услуги+Продукти','specialized',['avg_item_price_eur'=>10,'avg_items_count'=>300]],
    292 => ['Оптик','Optician','Услуги+Продукти','clothing',['avg_item_price_eur'=>120,'avg_items_count'=>200,'variant_fields'=>['Цвят рамка'],'variant_presets'=>['Цвят рамка'=>['Черен','Кафяв','Златист','Сребрист']],'typical_fields'=>['Марка','Диоптър','Тип']]],
    293 => ['Ветеринарна клиника','Veterinary clinic','Услуги+Продукти','service',['avg_item_price_eur'=>15,'avg_items_count'=>200]],
    294 => ['Градинарски услуги','Gardening service','Услуги+Продукти','hobby',['avg_item_price_eur'=>10]],
    295 => ['Музикално студио','Music studio','Услуги+Продукти','electronics',['avg_item_price_eur'=>50,'avg_items_count'=>100]],
    296 => ['Ловно-рибарски магазин','Hunting & fishing store (service)','Услуги+Продукти','hobby',['avg_item_price_eur'=>40,'avg_items_count'=>500]],
    297 => ['Дайвинг магазин','Diving store','Услуги+Продукти','hobby',['avg_item_price_eur'=>60,'avg_items_count'=>200,'has_variants'=>true,'variant_fields'=>['Размер'],'variant_presets'=>['Размер'=>['XS','S','M','L','XL','2XL']]]],
    298 => ['Боулинг','Bowling alley','Услуги+Продукти','service',[]],
    299 => ['Escape room','Escape room','Услуги+Продукти','service',[]],
    300 => ['Кафене','Coffee shop','Услуги+Продукти','food',['avg_items_count'=>100,'avg_item_price_eur'=>3]],
];


// ═══════════════════════════════════════════════════════════════
// ФУНКЦИИ
// ═══════════════════════════════════════════════════════════════

/**
 * Разгъва BIZ_TYPES_RAW запис → пълен обект с category defaults + overrides
 */
function expandBizType(int $id): ?array {
    global $BIZ_TYPES_RAW, $BIZ_CATEGORY_DEFAULTS;
    if (!isset($BIZ_TYPES_RAW[$id])) return null;

    $raw = $BIZ_TYPES_RAW[$id];
    $catKey = $raw[3];
    $defaults = $BIZ_CATEGORY_DEFAULTS[$catKey] ?? $BIZ_CATEGORY_DEFAULTS['specialized'];
    $overrides = $raw[4] ?? [];

    $result = array_merge($defaults, $overrides);
    $result['id'] = $id;
    $result['business_type'] = $raw[0];
    $result['business_type_en'] = $raw[1];
    $result['category'] = $raw[2];
    $result['category_key'] = $catKey;

    return $result;
}

/**
 * Намери бизнес тип по ID
 */
function findBizTypeById(int $id): ?array {
    return expandBizType($id);
}

/**
 * Намери най-близкия коефициент за даден бизнес текст (fuzzy matching)
 */
function findBizCoefficient(string $bizText): array {
    global $BIZ_COEFFICIENTS, $BIZ_DEFAULT_COEFF;

    $text = mb_strtolower(trim($bizText));

    if (isset($BIZ_COEFFICIENTS[$text])) {
        return ['match' => $text, 'coeff' => $BIZ_COEFFICIENTS[$text]];
    }

    $bestMatch = '';
    $bestCoeff = $BIZ_DEFAULT_COEFF;

    foreach ($BIZ_COEFFICIENTS as $key => $coeff) {
        if (mb_strpos($text, $key) !== false && mb_strlen($key) > mb_strlen($bestMatch)) {
            $bestMatch = $key;
            $bestCoeff = $coeff;
        }
    }

    if (!$bestMatch) {
        foreach ($BIZ_COEFFICIENTS as $key => $coeff) {
            if (mb_strpos($key, $text) !== false && mb_strlen($key) > mb_strlen($bestMatch)) {
                $bestMatch = $key;
                $bestCoeff = $coeff;
            }
        }
    }

    return ['match' => $bestMatch ?: 'друг ритейл бизнес', 'coeff' => $bestCoeff];
}

/**
 * Изчислява 5 WOW сценария с конкретни EUR суми
 */
function calculateLosses(int $coeff, int $stores): array {
    $base = $coeff * $stores;
    return [
        'monthly'    => $base,
        'yearly'     => $base * 12,
        'zombie'     => round($base * 0.30),
        'sizes'      => round($base * 0.20),
        'outofstock' => round($base * 0.25),
        'upsell'     => round($base * 0.10),
        'discounts'  => round($base * 0.15),
    ];
}

/**
 * Намери variant info за бизнес тип (fuzzy match по business_type)
 */
function findBizVariants(string $bizText): array {
    global $BIZ_TYPES_RAW, $BIZ_CATEGORY_DEFAULTS;

    $text = mb_strtolower(trim($bizText));
    $bestId = null;
    $bestLen = 0;

    foreach ($BIZ_TYPES_RAW as $id => $raw) {
        $bt = mb_strtolower($raw[0]);
        // Точно съвпадение
        if ($bt === $text) { $bestId = $id; break; }
        // Частично съвпадение — по-дълъг match = по-добър
        if (mb_strpos($text, $bt) !== false && mb_strlen($bt) > $bestLen) {
            $bestId = $id; $bestLen = mb_strlen($bt);
        }
        if (mb_strpos($bt, $text) !== false && mb_strlen($bt) > $bestLen) {
            $bestId = $id; $bestLen = mb_strlen($bt);
        }
    }

    if ($bestId !== null) {
        $full = expandBizType($bestId);
        return [
            'match' => $full['business_type'],
            'has_variants' => $full['has_variants'],
            'variant_fields' => $full['variant_fields'],
            'variant_presets' => $full['variant_presets'],
            'units' => $full['units'],
            'typical_fields' => $full['typical_fields'],
            'ai_scan_detects' => $full['ai_scan_detects'],
        ];
    }

    // Default
    $def = $BIZ_CATEGORY_DEFAULTS['specialized'];
    return [
        'match' => 'друг бизнес',
        'has_variants' => $def['has_variants'],
        'variant_fields' => $def['variant_fields'],
        'variant_presets' => $def['variant_presets'],
        'units' => $def['units'],
        'typical_fields' => $def['typical_fields'],
        'ai_scan_detects' => $def['ai_scan_detects'],
    ];
}

/**
 * Връща всички 300 бизнес типа за dropdown/autocomplete
 */
function getAllBizTypes(): array {
    global $BIZ_TYPES_RAW;
    $result = [];
    foreach ($BIZ_TYPES_RAW as $id => $raw) {
        $result[] = ['id' => $id, 'name' => $raw[0], 'name_en' => $raw[1], 'category' => $raw[2]];
    }
    return $result;
}
