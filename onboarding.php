<?php
// onboarding.php — Настройка на бизнеса (еднократно)
session_start();
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
require_once 'config/config.php';

$tenant_id = $_SESSION['tenant_id'];
$t = DB::run("SELECT onboarding_done FROM tenants WHERE id=?", [$tenant_id])->fetch();
if ($t && $t['onboarding_done']) { header('Location: chat.php'); exit; }

// POST — запази
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $btype           = $_POST['business_type'] ?? 'other';
    $units           = json_decode($_POST['units_json'] ?? '[]', true);
    $variants        = json_decode($_POST['variants_json'] ?? '[]', true);
    $is_perishable   = (int)($_POST['is_perishable'] ?? 0);
    $wholesale       = (int)($_POST['wholesale_enabled'] ?? 0);
    $tax_group       = (float)($_POST['tax_group'] ?? 20);
    $custom_label    = $_POST['custom_label'] ?? '';

    DB::run(
        "UPDATE tenants SET business_type=?, onboarding_done=1, units_config=?, variants_config=?, is_perishable=?, wholesale_enabled=?, tax_group=? WHERE id=?",
        [$btype, json_encode($units), json_encode($variants), $is_perishable, $wholesale, $tax_group, $tenant_id]
    );

    // Зареди категории
    $presets = getPresets();
    $cats = isset($presets[$btype]) ? $presets[$btype]['categories'] : [];
    foreach ($cats as $cat) {
        DB::run(
            "INSERT IGNORE INTO categories (tenant_id, name, variant_type) VALUES (?,?,?)",
            [$tenant_id, $cat['name'], $cat['variant_type']]
        );
    }

    $_SESSION['onboarding_done'] = 1;
    header('Location: chat.php'); exit;
}

function getPresets() {
    return [
      'fashion' => [
        'label'=>'Дрехи и мода','icon'=>'shirt','sub'=>'размери, цветове, сезони',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Тениски','variant_type'=>'size_color'],['name'=>'Блузи','variant_type'=>'size_color'],
          ['name'=>'Ризи','variant_type'=>'size_color'],['name'=>'Панталони','variant_type'=>'size_color'],
          ['name'=>'Дънки','variant_type'=>'size_color'],['name'=>'Рокли','variant_type'=>'size_color'],
          ['name'=>'Якета','variant_type'=>'size_color'],['name'=>'Бельо','variant_type'=>'size'],
          ['name'=>'Аксесоари','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт'],
        'all_units'=>['бр','к-кт','чифт','сет','дузина','кг','гр','л','мл','м','кутия','пакет','палет','ролка'],
        'variants'=>[
          ['name'=>'Размер','type'=>'size_letter','values'=>['XS','S','M','L','XL','XXL','3XL'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Сезон','type'=>'custom','values'=>['Пролет/Лято','Есен/Зима'],'active'=>false],
          ['name'=>'Материя','type'=>'custom','values'=>['Памук','Лен','Полиестер','Коприна'],'active'=>false],
        ],
      ],
      'footwear' => [
        'label'=>'Обувки','icon'=>'shoe','sub'=>'EU размери, цветове',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Маратонки','variant_type'=>'size_color'],['name'=>'Обувки','variant_type'=>'size_color'],
          ['name'=>'Боти','variant_type'=>'size_color'],['name'=>'Ботуши','variant_type'=>'size_color'],
          ['name'=>'Сандали','variant_type'=>'size_color'],['name'=>'Чехли','variant_type'=>'size_color'],
        ],
        'units'=>['чифт','бр'],
        'all_units'=>['чифт','бр','к-кт','сет','дузина','кг','гр','л','мл','м','кутия','пакет','палет','ролка'],
        'variants'=>[
          ['name'=>'Размер','type'=>'size_numeric','values'=>['35','36','37','38','39','40','41','42','43','44','45','46'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Материал','type'=>'custom','values'=>['Естествена кожа','Еко кожа','Текстил','Велур'],'active'=>false],
        ],
      ],
      'grocery' => [
        'label'=>'Хранителен магазин','icon'=>'grocery','sub'=>'кг, л, гр, срок на годност',
        'is_perishable'=>1,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Млечни','variant_type'=>'none'],['name'=>'Хляб и тестени','variant_type'=>'none'],
          ['name'=>'Месни','variant_type'=>'none'],['name'=>'Плодове и зеленчуци','variant_type'=>'none'],
          ['name'=>'Напитки','variant_type'=>'none'],['name'=>'Сладки изделия','variant_type'=>'none'],
          ['name'=>'Консерви','variant_type'=>'none'],['name'=>'Алкохол/Цигари','variant_type'=>'none'],
        ],
        'units'=>['бр','кг','л','стек','пакет'],
        'all_units'=>['бр','кг','л','стек','пакет','гр','мл','кутия','чувал','палет','ролка'],
        'variants'=>[
          ['name'=>'Грамаж/Обем','type'=>'custom','values'=>['200гр','500гр','1кг','500мл','1л','1.5л','2л'],'active'=>true],
          ['name'=>'Произход','type'=>'custom','values'=>['България','Внос'],'active'=>false],
        ],
      ],
      'cosmetics' => [
        'label'=>'Козметика и парфюми','icon'=>'cosmetics','sub'=>'аромат, обем, тип',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Парфюми','variant_type'=>'none'],['name'=>'Грижа за лице','variant_type'=>'none'],
          ['name'=>'Грижа за коса','variant_type'=>'none'],['name'=>'Грим','variant_type'=>'none'],
          ['name'=>'Хигиена','variant_type'=>'none'],['name'=>'Подаръчни комплекти','variant_type'=>'none'],
        ],
        'units'=>['бр','мл','к-кт'],
        'all_units'=>['бр','мл','л','к-кт','пакет','кутия'],
        'variants'=>[
          ['name'=>'Обем','type'=>'custom','values'=>['30мл','50мл','100мл','150мл','200мл'],'active'=>true],
          ['name'=>'Цвят/Нюанс','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Тип кожа','type'=>'custom','values'=>['Нормална','Суха','Мазна','Чувствителна'],'active'=>false],
        ],
      ],
      'electronics' => [
        'label'=>'Електроника','icon'=>'electronics','sub'=>'модел, памет, цвят',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Телефони','variant_type'=>'none'],['name'=>'Лаптопи','variant_type'=>'none'],
          ['name'=>'Аксесоари','variant_type'=>'none'],['name'=>'Периферия','variant_type'=>'none'],
          ['name'=>'Аудио','variant_type'=>'none'],['name'=>'Кабели','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт'],
        'all_units'=>['бр','к-кт','пакет','кутия'],
        'variants'=>[
          ['name'=>'Капацитет','type'=>'custom','values'=>['64GB','128GB','256GB','512GB','1TB'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Гаранция','type'=>'custom','values'=>['12 м.','24 м.','36 м.'],'active'=>false],
        ],
      ],
      'sports' => [
        'label'=>'Спортни стоки','icon'=>'sports','sub'=>'размери, тегло, цвят',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Спортни дрехи','variant_type'=>'size_color'],['name'=>'Фитнес уреди','variant_type'=>'none'],
          ['name'=>'Аксесоари','variant_type'=>'none'],['name'=>'Хранителни добавки','variant_type'=>'none'],
          ['name'=>'Екипировка','variant_type'=>'none'],
        ],
        'units'=>['бр','кг','чифт','к-кт'],
        'all_units'=>['бр','кг','чифт','к-кт','л','пакет'],
        'variants'=>[
          ['name'=>'Размер','type'=>'size_letter','values'=>['S','M','L','XL','XXL'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Тегло','type'=>'custom','values'=>['2кг','5кг','10кг','20кг'],'active'=>false],
        ],
      ],
      'pharmacy' => [
        'label'=>'Аптека / здраве','icon'=>'pharmacy','sub'=>'дозировка, разфасовка',
        'is_perishable'=>1,'wholesale_enabled'=>0,'tax_group'=>9,
        'categories'=>[
          ['name'=>'Лекарства','variant_type'=>'none'],['name'=>'Витамини','variant_type'=>'none'],
          ['name'=>'Билки','variant_type'=>'none'],['name'=>'Медицински консумативи','variant_type'=>'none'],
          ['name'=>'Хигиена','variant_type'=>'none'],['name'=>'Детски храни','variant_type'=>'none'],
        ],
        'units'=>['бр','пакет','мл'],
        'all_units'=>['бр','пакет','мл','л','кг','гр','к-кт'],
        'variants'=>[
          ['name'=>'Разфасовка','type'=>'custom','values'=>['10 табл.','20 табл.','30 табл.','60 табл.'],'active'=>true],
          ['name'=>'Дозировка','type'=>'custom','values'=>['5mg','10mg','20mg','500mg','1000mg'],'active'=>false],
        ],
      ],
      'stationery' => [
        'label'=>'Книжарница / канцеларски','icon'=>'book','sub'=>'формат, цвят',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>9,
        'categories'=>[
          ['name'=>'Тетрадки','variant_type'=>'none'],['name'=>'Хартия','variant_type'=>'none'],
          ['name'=>'Пишещи','variant_type'=>'none'],['name'=>'Книги','variant_type'=>'none'],
          ['name'=>'Ученически пособия','variant_type'=>'none'],['name'=>'Офис консумативи','variant_type'=>'none'],
        ],
        'units'=>['бр','пакет','сет'],
        'all_units'=>['бр','пакет','сет','к-кт','кутия'],
        'variants'=>[
          ['name'=>'Формат','type'=>'custom','values'=>['A3','A4','A5','B5'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Корица','type'=>'custom','values'=>['Мека','Твърда'],'active'=>false],
        ],
      ],
      'construction' => [
        'label'=>'Строителни материали','icon'=>'construction','sub'=>'разфасовка, размер',
        'is_perishable'=>0,'wholesale_enabled'=>1,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Инструменти','variant_type'=>'none'],['name'=>'Крепежи','variant_type'=>'none'],
          ['name'=>'Бои и лакове','variant_type'=>'none'],['name'=>'Електро материали','variant_type'=>'none'],
          ['name'=>'ВиК','variant_type'=>'none'],['name'=>'Изолация','variant_type'=>'none'],
          ['name'=>'Сухи смеси','variant_type'=>'none'],
        ],
        'units'=>['бр','кг','л','м','м²'],
        'all_units'=>['бр','кг','л','м','м²','к-кт','пакет','палет','чувал','кутия'],
        'variants'=>[
          ['name'=>'Разфасовка','type'=>'custom','values'=>['1кг','5кг','25кг','1л','5л','10л'],'active'=>true],
          ['name'=>'Размер','type'=>'custom','values'=>['10мм','20мм','50мм','100мм'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>false],
        ],
      ],
      'auto_parts' => [
        'label'=>'Авто части','icon'=>'car','sub'=>'вискозитет, страна',
        'is_perishable'=>0,'wholesale_enabled'=>1,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Масла','variant_type'=>'none'],['name'=>'Филтри','variant_type'=>'none'],
          ['name'=>'Спирачна система','variant_type'=>'none'],['name'=>'Окачване','variant_type'=>'none'],
          ['name'=>'Осветление','variant_type'=>'none'],['name'=>'Акумулатори','variant_type'=>'none'],
          ['name'=>'Аксесоари','variant_type'=>'none'],
        ],
        'units'=>['бр','л','к-кт'],
        'all_units'=>['бр','л','к-кт','пакет','кутия'],
        'variants'=>[
          ['name'=>'Вискозитет','type'=>'custom','values'=>['5W-30','5W-40','10W-40','0W-20'],'active'=>true],
          ['name'=>'Страна','type'=>'custom','values'=>['Лява','Дясна','Предна','Задна'],'active'=>true],
          ['name'=>'Волтаж','type'=>'custom','values'=>['12V','24V'],'active'=>false],
        ],
      ],
      'flowers' => [
        'label'=>'Цветарница','icon'=>'flower','sub'=>'размер, цвят, повод',
        'is_perishable'=>1,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Букети','variant_type'=>'none'],['name'=>'Саксийни цветя','variant_type'=>'none'],
          ['name'=>'Рязан цвят','variant_type'=>'none'],['name'=>'Подаръци','variant_type'=>'none'],
          ['name'=>'Торове','variant_type'=>'none'],['name'=>'Почви','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт','кг'],
        'all_units'=>['бр','к-кт','кг','пакет'],
        'variants'=>[
          ['name'=>'Размер','type'=>'custom','values'=>['Малък','Среден','Голям'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Повод','type'=>'custom','values'=>['Рожден ден','Сватба','Юбилей','Без повод'],'active'=>false],
        ],
      ],
      'toys' => [
        'label'=>'Играчки','icon'=>'toy','sub'=>'възраст, материал',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Плюшени играчки','variant_type'=>'none'],['name'=>'Конструктори','variant_type'=>'none'],
          ['name'=>'Пъзели','variant_type'=>'none'],['name'=>'Кукли','variant_type'=>'none'],
          ['name'=>'Колички','variant_type'=>'none'],['name'=>'Образователни','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт'],
        'all_units'=>['бр','к-кт','пакет','сет'],
        'variants'=>[
          ['name'=>'Възраст','type'=>'custom','values'=>['0-3 г.','3-6 г.','6-12 г.','12+ г.'],'active'=>true],
          ['name'=>'Материал','type'=>'custom','values'=>['Пластмаса','Дърво','Плюш','Метал'],'active'=>true],
        ],
      ],
      'jewelry' => [
        'label'=>'Бижута и аксесоари','icon'=>'jewelry','sub'=>'материал, размер, камък',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Пръстени','variant_type'=>'size'],['name'=>'Обеци','variant_type'=>'none'],
          ['name'=>'Колиета','variant_type'=>'none'],['name'=>'Гривни','variant_type'=>'none'],
          ['name'=>'Часовници','variant_type'=>'none'],['name'=>'Аксесоари за коса','variant_type'=>'none'],
        ],
        'units'=>['бр','чифт','к-кт'],
        'all_units'=>['бр','чифт','к-кт','сет'],
        'variants'=>[
          ['name'=>'Материал','type'=>'custom','values'=>['Злато','Сребро','Стомана','Бижутерийна сплав'],'active'=>true],
          ['name'=>'Размер (пръстен)','type'=>'custom','values'=>['48','50','52','54','56','58'],'active'=>true],
          ['name'=>'Камък','type'=>'custom','values'=>['Диамант','Цирконий','Перла','Без камък'],'active'=>false],
        ],
      ],
      'home_goods' => [
        'label'=>'Домашни потреби','icon'=>'home','sub'=>'размер, материал, цвят',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Кухня','variant_type'=>'none'],['name'=>'Баня','variant_type'=>'none'],
          ['name'=>'Текстил за дома','variant_type'=>'size_color'],['name'=>'Осветление','variant_type'=>'none'],
          ['name'=>'Декорация','variant_type'=>'none'],['name'=>'Почистване','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт','чифт'],
        'all_units'=>['бр','к-кт','чифт','пакет','л','кг'],
        'variants'=>[
          ['name'=>'Размер','type'=>'custom','values'=>['S','M','L','XL','200x220','160x200'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Материал','type'=>'custom','values'=>['Стъкло','Керамика','Метал','Текстил'],'active'=>false],
        ],
      ],
    ];
}

$presets = getPresets();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Настройка — RunMyStore.ai</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{background:#030712;color:#e2e8f0;font-family:'Montserrat',sans-serif;margin:0;min-height:100vh;overflow-x:hidden}
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);width:700px;height:500px;background:radial-gradient(ellipse,rgba(99,102,241,.1) 0%,transparent 70%);pointer-events:none;z-index:0}

.wrap{max-width:480px;margin:0 auto;padding:24px 16px 40px;position:relative;z-index:1}

/* LOGO */
.logo{text-align:center;margin-bottom:28px}
.logo-name{font-size:20px;font-weight:800;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
.logo-sub{font-size:12px;color:#6b7280;margin-top:2px}

/* PROGRESS */
.prog-wrap{display:flex;gap:6px;margin-bottom:28px}
.prog-step{flex:1;height:4px;border-radius:2px;background:rgba(99,102,241,.15);transition:background .4s}
.prog-step.on{background:#6366f1;box-shadow:0 0 8px rgba(99,102,241,.4)}

/* STEP HEADER */
.step-header{margin-bottom:20px}
.step-num{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.step-title{font-size:22px;font-weight:800;color:#f1f5f9;margin-bottom:6px}
.step-sub{font-size:13px;color:#6b7280;line-height:1.5}

/* BUSINESS CARDS */
.biz-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}
.biz-card{background:rgba(15,15,40,.7);border:1px solid rgba(99,102,241,.15);border-radius:16px;padding:14px 12px;cursor:pointer;transition:all .2s;-webkit-user-select:none;user-select:none;position:relative;overflow:hidden}
.biz-card::before{content:'';position:absolute;inset:0;border-radius:inherit;background:linear-gradient(135deg,rgba(99,102,241,.06),transparent);pointer-events:none}
.biz-card:active{transform:scale(.97)}
.biz-card.selected{border-color:#6366f1;background:rgba(99,102,241,.15);box-shadow:0 0 20px rgba(99,102,241,.2)}
.biz-icon{width:36px;height:36px;border-radius:10px;background:rgba(99,102,241,.15);display:flex;align-items:center;justify-content:center;margin-bottom:8px}
.biz-name{font-size:13px;font-weight:700;color:#f1f5f9;margin-bottom:2px}
.biz-sub{font-size:10px;color:#6b7280;line-height:1.4}
.biz-check{position:absolute;top:10px;right:10px;width:18px;height:18px;border-radius:50%;background:#6366f1;display:none;align-items:center;justify-content:center}
.biz-card.selected .biz-check{display:flex}

/* SECTION LABEL */
.sec-label{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin:16px 0 8px}

/* AI BOX */
.ai-box{background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(168,85,247,.07));border:1px solid rgba(99,102,241,.2);border-radius:14px;padding:12px 14px;margin-bottom:14px}
.ai-label{font-size:10px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}
.ai-text{font-size:13px;color:#a5b4fc;line-height:1.6}

/* SEARCH */
.search-box{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#e2e8f0;font-size:14px;padding:10px 14px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s;margin-bottom:10px}
.search-box:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.search-box::placeholder{color:#4b5563}

/* CHIPS */
.chip-group{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px}
.chip{padding:6px 12px;border-radius:10px;font-size:12px;font-weight:600;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:transparent;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s}
.chip.on{background:rgba(99,102,241,.18);border-color:#6366f1;color:#a5b4fc}
.chip.add-chip{border-style:dashed;color:#6366f1;border-color:rgba(99,102,241,.3)}

/* ADD ROW */
.add-row{display:flex;gap:8px;margin-top:8px}
.add-input{flex:1;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#e2e8f0;font-size:13px;padding:8px 12px;font-family:'Montserrat',sans-serif;outline:none}
.add-input::placeholder{color:#4b5563}
.add-btn{padding:8px 14px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#a5b4fc;font-size:12px;font-weight:600;cursor:pointer;font-family:'Montserrat',sans-serif;white-space:nowrap}

/* VARIANT CARDS */
.variant-card{display:flex;align-items:flex-start;gap:12px;background:rgba(15,15,40,.7);border:1px solid rgba(99,102,241,.12);border-radius:14px;padding:12px 14px;margin-bottom:8px;cursor:pointer;transition:all .2s}
.variant-card.on{border-color:rgba(99,102,241,.4);background:rgba(99,102,241,.08)}
.v-check{width:20px;height:20px;border-radius:6px;border:1.5px solid rgba(99,102,241,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;transition:all .2s}
.variant-card.on .v-check{background:#6366f1;border-color:#6366f1}
.v-body{flex:1}
.v-name{font-size:14px;font-weight:700;color:#f1f5f9;margin-bottom:2px}
.v-ex{font-size:11px;color:#6b7280;margin-bottom:6px}
.v-vals{display:flex;gap:4px;flex-wrap:wrap}
.v-val{padding:2px 8px;border-radius:6px;font-size:11px;background:rgba(99,102,241,.1);color:#a5b4fc;border:1px solid rgba(99,102,241,.15)}

/* OTHER TYPE */
.textarea-box{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#e2e8f0;font-size:14px;padding:12px 14px;font-family:'Montserrat',sans-serif;outline:none;resize:none;line-height:1.6;margin-bottom:12px}
.textarea-box::placeholder{color:#4b5563}
.textarea-box:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}

/* CONFIRM BTNS */
.confirm-row{display:flex;gap:8px;margin-top:4px}
.btn-yes{flex:1;padding:11px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);border-radius:12px;color:#22c55e;font-size:14px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif}
.btn-change{flex:1;padding:11px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#a5b4fc;font-size:14px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif}

/* NOTE */
.note{font-size:11px;color:#4b5563;text-align:center;margin-top:8px;line-height:1.5}

/* BUTTONS */
.btn-next{width:100%;padding:14px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:14px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(99,102,241,.35),inset 0 1px 0 rgba(255,255,255,.16);margin-top:8px;transition:all .2s}
.btn-next:active{transform:scale(.98)}
.btn-next.green{background:linear-gradient(to bottom,#22c55e,#16a34a);box-shadow:0 4px 20px rgba(34,197,94,.35)}
.btn-back{width:100%;padding:12px;background:transparent;border:1px solid rgba(99,102,241,.15);border-radius:14px;color:#6b7280;font-size:13px;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:8px}

/* STEP CONTENT */
.step-content{display:none}
.step-content.active{display:block;animation:fadeUp .3s ease}

/* LOADING */
.loading-box{text-align:center;padding:30px;color:#6b7280}
.loading-spinner{width:36px;height:36px;border:3px solid rgba(99,102,241,.2);border-top-color:#6366f1;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 12px}

@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>

<div class="wrap">
  <div class="logo">
    <div class="logo-name">RunMyStore.ai</div>
    <div class="logo-sub">Нека настроим твоя магазин</div>
  </div>

  <div class="prog-wrap">
    <div class="prog-step on" id="prog1"></div>
    <div class="prog-step" id="prog2"></div>
    <div class="prog-step" id="prog3"></div>
  </div>

  <form method="POST" id="mainForm">
    <input type="hidden" name="business_type" id="f_btype">
    <input type="hidden" name="units_json" id="f_units">
    <input type="hidden" name="variants_json" id="f_variants">
    <input type="hidden" name="is_perishable" id="f_perishable" value="0">
    <input type="hidden" name="wholesale_enabled" id="f_wholesale" value="0">
    <input type="hidden" name="tax_group" id="f_tax" value="20">
    <input type="hidden" name="custom_label" id="f_custom_label">

    <!-- ═══ СТЪПКА 1 ═══ -->
    <div class="step-content active" id="step1">
      <div class="step-header">
        <div class="step-num">Стъпка 1 от 3</div>
        <div class="step-title">Какъв е твоят магазин?</div>
        <div class="step-sub">AI настройва категориите, мерните единици и вариантите автоматично</div>
      </div>

      <div class="biz-grid" id="bizGrid">
        <?php foreach ($presets as $key => $p): ?>
        <div class="biz-card" data-type="<?= $key ?>" onclick="selectBiz('<?= $key ?>')">
          <div class="biz-icon"><?= getBizIcon($key) ?></div>
          <div class="biz-name"><?= $p['label'] ?></div>
          <div class="biz-sub"><?= $p['sub'] ?></div>
          <div class="biz-check">
            <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5"><path d="M2 6l3 3 5-5"/></svg>
          </div>
        </div>
        <?php endforeach; ?>
        <!-- Друг тип -->
        <div class="biz-card" data-type="other" onclick="selectBiz('other')">
          <div class="biz-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M12 8v4l3 3"/></svg></div>
          <div class="biz-name">Друг тип</div>
          <div class="biz-sub">опиши → AI настройва</div>
          <div class="biz-check"><svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5"><path d="M2 6l3 3 5-5"/></svg></div>
        </div>
      </div>

      <!-- Друг тип textarea -->
      <div id="otherTypeBox" style="display:none">
        <div class="sec-label">Опиши твоя бизнес</div>
        <textarea class="textarea-box" id="customBizDesc" rows="3" placeholder="напр. Продавам части за велосипеди, риболовни принадлежности..."></textarea>
        <button type="button" class="btn-next" onclick="generateCustomPreset()">AI анализира →</button>
        <div id="aiGeneratingBox" style="display:none" class="loading-box">
          <div class="loading-spinner"></div>
          <div>AI анализира твоя бизнес...</div>
        </div>
        <div id="aiPreviewBox" style="display:none">
          <div class="ai-box">
            <div class="ai-label">✦ AI предложи</div>
            <div class="ai-text" id="aiPreviewText"></div>
          </div>
          <div class="confirm-row">
            <button type="button" class="btn-yes" onclick="confirmCustom()">Да, продължи ✓</button>
            <button type="button" class="btn-change" onclick="resetCustom()">Промени</button>
          </div>
        </div>
      </div>

      <button type="button" class="btn-next" id="step1Next" onclick="goStep(2)" style="display:none">Напред →</button>
    </div>

    <!-- ═══ СТЪПКА 2 ═══ -->
    <div class="step-content" id="step2">
      <div class="step-header">
        <div class="step-num">Стъпка 2 от 3</div>
        <div class="step-title">Мерни единици</div>
        <div class="step-sub" id="step2Sub">AI избра подходящите. Добави или премахни.</div>
      </div>

      <div class="ai-box">
        <div class="ai-label">✦ AI избра за твоя бизнес</div>
        <div class="ai-text" id="unitsAiText"></div>
      </div>

      <div class="sec-label">Избрани</div>
      <div class="chip-group" id="selectedUnits"></div>

      <div class="sec-label">Всички налични</div>
      <input type="text" class="search-box" placeholder="Търси мерна единица..." oninput="filterUnits(this.value)" id="unitsSearch">
      <div class="chip-group" id="allUnits"></div>

      <div class="sec-label">Добави своя</div>
      <div class="add-row">
        <input type="text" class="add-input" id="customUnitInput" placeholder="напр. пал, ринг, торба...">
        <button type="button" class="add-btn" onclick="addCustomUnit()">+ Добави</button>
      </div>

      <button type="button" class="btn-next" onclick="goStep(3)">Напред →</button>
      <button type="button" class="btn-back" onclick="goStep(1)">← Назад</button>
    </div>

    <!-- ═══ СТЪПКА 3 ═══ -->
    <div class="step-content" id="step3">
      <div class="step-header">
        <div class="step-num">Стъпка 3 от 3</div>
        <div class="step-title">Характеристики</div>
        <div class="step-sub">Маркирай кои да се показват при добавяне на артикул</div>
      </div>

      <div class="ai-box">
        <div class="ai-label">✦ AI избра за твоя бизнес</div>
        <div class="ai-text" id="variantsAiText"></div>
      </div>

      <div id="variantsList"></div>

      <div class="sec-label">Добави своя характеристика</div>
      <div class="add-row">
        <input type="text" class="add-input" id="customVariantInput" placeholder="напр. Колекция, Сезон, Марка...">
        <button type="button" class="add-btn" onclick="addCustomVariant()">+ Добави</button>
      </div>

      <div class="note">Можеш да промениш по всяко време от Настройки</div>

      <button type="button" class="btn-next green" onclick="saveAndFinish()">Готово — влизам в RunMyStore ✓</button>
      <button type="button" class="btn-back" onclick="goStep(2)">← Назад</button>
    </div>
  </form>
</div>

<script>
const PRESETS = <?= json_encode($presets, JSON_UNESCAPED_UNICODE) ?>;
const ALL_UNITS = ['бр','к-кт','чифт','сет','дузина','кг','гр','мг','т','л','мл','м³','м','см','мм','м²','стек','кашон','кутия','пакет','палет','ролка','чувал'];

let selectedBiz = null;
let selectedUnits = [];
let allAvailableUnits = [];
let variantsData = [];
let customPresetData = null;

function selectBiz(type) {
  document.querySelectorAll('.biz-card').forEach(c => c.classList.remove('selected'));
  document.querySelector(`[data-type="${type}"]`).classList.add('selected');
  selectedBiz = type;

  const otherBox = document.getElementById('otherTypeBox');
  const nextBtn = document.getElementById('step1Next');

  if (type === 'other') {
    otherBox.style.display = 'block';
    nextBtn.style.display = 'none';
  } else {
    otherBox.style.display = 'none';
    nextBtn.style.display = 'block';
  }
}

function goStep(n) {
  if (n === 2) prepareStep2();
  if (n === 3) prepareStep3();

  for (let i = 1; i <= 3; i++) {
    document.getElementById('step' + i).classList.toggle('active', i === n);
    document.getElementById('prog' + i).classList.toggle('on', i <= n);
  }
  window.scrollTo(0, 0);
}

function prepareStep2() {
  const preset = customPresetData || PRESETS[selectedBiz];
  if (!preset) return;

  selectedUnits = [...preset.units];
  allAvailableUnits = [...new Set([...ALL_UNITS, ...(preset.all_units || [])])];

  document.getElementById('unitsAiText').textContent =
    'За ' + preset.label + ' обикновено се ползват: ' + preset.units.join(', ');

  renderUnits();
}

function renderUnits(filter = '') {
  const sel = document.getElementById('selectedUnits');
  const all = document.getElementById('allUnits');

  sel.innerHTML = selectedUnits.map(u =>
    `<span class="chip on" onclick="toggleUnit('${u}')">${u} ✓</span>`
  ).join('');

  const filtered = allAvailableUnits.filter(u =>
    u.toLowerCase().includes(filter.toLowerCase())
  );

  all.innerHTML = filtered.map(u => {
    const isOn = selectedUnits.includes(u);
    return `<span class="chip ${isOn ? 'on' : ''}" onclick="toggleUnit('${u}')">${u}</span>`;
  }).join('');
}

function filterUnits(val) { renderUnits(val); }

function toggleUnit(u) {
  if (selectedUnits.includes(u)) {
    selectedUnits = selectedUnits.filter(x => x !== u);
  } else {
    selectedUnits.push(u);
  }
  renderUnits(document.getElementById('unitsSearch').value);
}

function addCustomUnit() {
  const val = document.getElementById('customUnitInput').value.trim();
  if (!val) return;
  if (!allAvailableUnits.includes(val)) allAvailableUnits.push(val);
  if (!selectedUnits.includes(val)) selectedUnits.push(val);
  document.getElementById('customUnitInput').value = '';
  renderUnits();
}

function prepareStep3() {
  const preset = customPresetData || PRESETS[selectedBiz];
  if (!preset) return;

  variantsData = JSON.parse(JSON.stringify(preset.variants));

  document.getElementById('variantsAiText').textContent =
    'За ' + preset.label + ': ' +
    variantsData.filter(v => v.active).map(v => v.name).join(', ') +
    ' са включени по подразбиране.';

  renderVariants();
}

function renderVariants() {
  const list = document.getElementById('variantsList');
  list.innerHTML = variantsData.map((v, i) => `
    <div class="variant-card ${v.active ? 'on' : ''}" onclick="toggleVariant(${i})">
      <div class="v-check">${v.active ? '<svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5"><path d="M2 6l3 3 5-5"/></svg>' : ''}</div>
      <div class="v-body">
        <div class="v-name">${v.name}</div>
        <div class="v-ex">${v.type === 'color' ? 'Галерия с цветове — винаги налична' : (v.values.slice(0, 4).join(', ') + (v.values.length > 4 ? '...' : ''))}</div>
        ${v.values.length > 0 ? `<div class="v-vals">${v.values.slice(0, 5).map(val => `<span class="v-val">${val}</span>`).join('')}${v.values.length > 5 ? '<span class="v-val">+</span>' : ''}</div>` : ''}
      </div>
    </div>
  `).join('');
}

function toggleVariant(i) {
  variantsData[i].active = !variantsData[i].active;
  renderVariants();
}

function addCustomVariant() {
  const val = document.getElementById('customVariantInput').value.trim();
  if (!val) return;
  variantsData.push({ name: val, type: 'custom', values: [], active: true });
  document.getElementById('customVariantInput').value = '';
  renderVariants();
}

// Друг тип — AI генерира
async function generateCustomPreset() {
  const desc = document.getElementById('customBizDesc').value.trim();
  if (!desc) { alert('Опиши твоя бизнес'); return; }

  document.querySelector('#otherTypeBox .btn-next').style.display = 'none';
  document.getElementById('aiGeneratingBox').style.display = 'block';
  document.getElementById('aiPreviewBox').style.display = 'none';

  try {
    const response = await fetch('chat-send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        message: `Генерирай JSON конфигурация за RunMyStore.ai за бизнес тип: "${desc}".
Върни САМО валиден JSON без обяснения в следния формат:
{
  "label": "Наименование на бизнеса",
  "categories": [{"name":"Категория1","variant_type":"none"}, ...],
  "units": ["бр", "кг"],
  "all_units": ["бр", "кг", "л"],
  "variants": [{"name":"Вариант","type":"custom","values":["стойност1","стойност2"],"active":true}],
  "is_perishable": false,
  "wholesale_enabled": false,
  "tax_group": 20
}
variant_type може да е: none, size_color, size, color. Отговори САМО с JSON.`
      })
    });

    const data = await response.json();
    const text = (data.response || data.message || '');
    const match = text.match(/\{[\s\S]*\}/);

    if (match) {
      customPresetData = JSON.parse(match[0]);
      customPresetData.variants = customPresetData.variants || [];

      document.getElementById('aiPreviewText').innerHTML =
        `<strong>${customPresetData.label}</strong><br>` +
        `Категории: ${customPresetData.categories.slice(0,4).map(c=>c.name).join(', ')}...<br>` +
        `Мерни единици: ${customPresetData.units.join(', ')}<br>` +
        `Характеристики: ${customPresetData.variants.filter(v=>v.active).map(v=>v.name).join(', ')}`;

      document.getElementById('aiGeneratingBox').style.display = 'none';
      document.getElementById('aiPreviewBox').style.display = 'block';
    } else {
      throw new Error('Invalid JSON');
    }
  } catch (e) {
    document.getElementById('aiGeneratingBox').style.display = 'none';
    document.querySelector('#otherTypeBox .btn-next').style.display = 'block';
    alert('AI не успя да анализира. Опитай отново или избери тип от списъка.');
  }
}

function confirmCustom() {
  document.getElementById('step1Next').style.display = 'none';
  goStep(2);
}

function resetCustom() {
  customPresetData = null;
  document.getElementById('aiPreviewBox').style.display = 'none';
  document.querySelector('#otherTypeBox .btn-next').style.display = 'block';
}

function saveAndFinish() {
  if (!selectedBiz) { alert('Избери тип бизнес'); return; }

  const preset = customPresetData || PRESETS[selectedBiz];

  document.getElementById('f_btype').value = selectedBiz;
  document.getElementById('f_units').value = JSON.stringify(selectedUnits);
  document.getElementById('f_variants').value = JSON.stringify(variantsData);
  document.getElementById('f_perishable').value = preset.is_perishable ? 1 : 0;
  document.getElementById('f_wholesale').value = preset.wholesale_enabled ? 1 : 0;
  document.getElementById('f_tax').value = preset.tax_group || 20;
  document.getElementById('f_custom_label').value = preset.label || '';

  document.getElementById('mainForm').submit();
}
</script>
</body>
</html>
<?php
function getBizIcon($key) {
  $icons = [
    'fashion' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.86H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.86l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>',
    'footwear' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><path d="M2 12l4-4 4 2 4-4 4 2v6H2v-2z"/></svg>',
    'grocery' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>',
    'cosmetics' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>',
    'electronics' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
    'sports' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14M12 2a10 10 0 010 20"/></svg>',
    'pharmacy' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="9" y1="12" x2="15" y2="12"/></svg>',
    'stationery' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>',
    'construction' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><path d="M2 20h20M4 20V10l8-8 8 8v10"/><path d="M9 20v-5h6v5"/></svg>',
    'auto_parts' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    'flowers' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><path d="M12 22V12m0 0C12 7 7 4 7 4s0 5 5 8zm0 0c0-5 5-8 5-8s0 5-5 8z"/><circle cx="12" cy="12" r="3"/></svg>',
    'toys' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M8 8H4a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2v-6a2 2 0 00-2-2h-4"/></svg>',
    'jewelry' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
    'home_goods' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  ];
  return $icons[$key] ?? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>';
}
?>
