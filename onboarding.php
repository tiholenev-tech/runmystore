<?php
// onboarding.php — Настройка на бизнеса (еднократно)
// Multi-select бизнес типове, Cruip filled icons

session_start();
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
require_once 'config/config.php';

$tenant_id = $_SESSION['tenant_id'];
$t = DB::run("SELECT onboarding_done FROM tenants WHERE id=?", [$tenant_id])->fetch();
if ($t && $t['onboarding_done']) { header('Location: chat.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $btypes        = json_decode($_POST['business_types'] ?? '[]', true);
    $btype         = implode(',', $btypes);
    $units         = json_decode($_POST['units_json'] ?? '[]', true);
    $variants      = json_decode($_POST['variants_json'] ?? '[]', true);
    $is_perishable = (int)($_POST['is_perishable'] ?? 0);
    $wholesale     = (int)($_POST['wholesale_enabled'] ?? 0);
    $tax_group     = (float)($_POST['tax_group'] ?? 20);

    DB::run(
        "UPDATE tenants SET business_type=?, onboarding_done=1, units_config=?, variants_config=?, is_perishable=?, wholesale_enabled=?, tax_group=? WHERE id=?",
        [$btype, json_encode($units), json_encode($variants), $is_perishable, $wholesale, $tax_group, $tenant_id]
    );

    $presets = getPresets();
    $addedCats = [];
    foreach ($btypes as $bt) {
        $cats = $presets[$bt]['categories'] ?? [];
        foreach ($cats as $cat) {
            $key = $cat['name'];
            if (!in_array($key, $addedCats)) {
                DB::run("INSERT IGNORE INTO categories (tenant_id, name, variant_type) VALUES (?,?,?)",
                    [$tenant_id, $cat['name'], $cat['variant_type']]);
                $addedCats[] = $key;
            }
        }
    }

    $_SESSION['onboarding_done'] = 1;
    header('Location: chat.php'); exit;
}

function getPresets() {
    return [
      'fashion' => [
        'label'=>'Дрехи','sub'=>'размери, цветове',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Тениски','variant_type'=>'size_color'],
          ['name'=>'Блузи','variant_type'=>'size_color'],
          ['name'=>'Ризи','variant_type'=>'size_color'],
          ['name'=>'Панталони','variant_type'=>'size_color'],
          ['name'=>'Дънки','variant_type'=>'size_color'],
          ['name'=>'Рокли','variant_type'=>'size_color'],
          ['name'=>'Якета','variant_type'=>'size_color'],
          ['name'=>'Бельо','variant_type'=>'size'],
          ['name'=>'Аксесоари','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт'],
        'variants'=>[
          ['name'=>'Размер (буквен)','type'=>'size_letter','values'=>['XS','S','M','L','XL','XXL','3XL'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Сезон','type'=>'custom','values'=>['Пролет/Лято','Есен/Зима'],'active'=>false],
          ['name'=>'Материя','type'=>'custom','values'=>['Памук','Лен','Полиестер'],'active'=>false],
        ],
      ],
      'footwear' => [
        'label'=>'Обувки','sub'=>'EU размери, цветове',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Маратонки','variant_type'=>'size_color'],
          ['name'=>'Обувки','variant_type'=>'size_color'],
          ['name'=>'Боти','variant_type'=>'size_color'],
          ['name'=>'Ботуши','variant_type'=>'size_color'],
          ['name'=>'Сандали','variant_type'=>'size_color'],
          ['name'=>'Чехли','variant_type'=>'size_color'],
        ],
        'units'=>['чифт','бр'],
        'variants'=>[
          ['name'=>'Размер (EU)','type'=>'size_numeric','values'=>['35','36','37','38','39','40','41','42','43','44','45','46'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Материал','type'=>'custom','values'=>['Естествена кожа','Еко кожа','Текстил','Велур'],'active'=>false],
        ],
      ],
      'grocery' => [
        'label'=>'Хранителен','sub'=>'кг, л, срок годност',
        'is_perishable'=>1,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Млечни','variant_type'=>'none'],
          ['name'=>'Хляб и тестени','variant_type'=>'none'],
          ['name'=>'Месни','variant_type'=>'none'],
          ['name'=>'Плодове и зеленчуци','variant_type'=>'none'],
          ['name'=>'Напитки','variant_type'=>'none'],
          ['name'=>'Консерви','variant_type'=>'none'],
          ['name'=>'Алкохол/Цигари','variant_type'=>'none'],
        ],
        'units'=>['бр','кг','л','стек','пакет'],
        'variants'=>[
          ['name'=>'Грамаж/Обем','type'=>'custom','values'=>['200гр','500гр','1кг','500мл','1л','2л'],'active'=>true],
          ['name'=>'Произход','type'=>'custom','values'=>['България','Внос'],'active'=>false],
        ],
      ],
      'cosmetics' => [
        'label'=>'Козметика','sub'=>'аромат, обем',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Парфюми','variant_type'=>'none'],
          ['name'=>'Грижа за лице','variant_type'=>'none'],
          ['name'=>'Грижа за коса','variant_type'=>'none'],
          ['name'=>'Грим','variant_type'=>'none'],
          ['name'=>'Хигиена','variant_type'=>'none'],
          ['name'=>'Подаръчни комплекти','variant_type'=>'none'],
        ],
        'units'=>['бр','мл','к-кт'],
        'variants'=>[
          ['name'=>'Обем','type'=>'custom','values'=>['30мл','50мл','100мл','150мл','200мл'],'active'=>true],
          ['name'=>'Нюанс/Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Тип кожа','type'=>'custom','values'=>['Нормална','Суха','Мазна','Чувствителна'],'active'=>false],
        ],
      ],
      'electronics' => [
        'label'=>'Електроника','sub'=>'модел, памет, цвят',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Телефони','variant_type'=>'none'],
          ['name'=>'Лаптопи','variant_type'=>'none'],
          ['name'=>'Аксесоари','variant_type'=>'none'],
          ['name'=>'Аудио','variant_type'=>'none'],
          ['name'=>'Кабели','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт'],
        'variants'=>[
          ['name'=>'Капацитет','type'=>'custom','values'=>['64GB','128GB','256GB','512GB','1TB'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Гаранция','type'=>'custom','values'=>['12 м.','24 м.','36 м.'],'active'=>false],
        ],
      ],
      'sports' => [
        'label'=>'Спортни стоки','sub'=>'размери, тегло',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Спортни дрехи','variant_type'=>'size_color'],
          ['name'=>'Фитнес уреди','variant_type'=>'none'],
          ['name'=>'Аксесоари','variant_type'=>'none'],
          ['name'=>'Хранителни добавки','variant_type'=>'none'],
          ['name'=>'Екипировка','variant_type'=>'none'],
        ],
        'units'=>['бр','кг','чифт','к-кт'],
        'variants'=>[
          ['name'=>'Размер','type'=>'size_letter','values'=>['S','M','L','XL','XXL'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Тегло','type'=>'custom','values'=>['2кг','5кг','10кг','20кг'],'active'=>false],
        ],
      ],
      'pharmacy' => [
        'label'=>'Аптека','sub'=>'дозировка, разфасовка',
        'is_perishable'=>1,'wholesale_enabled'=>0,'tax_group'=>9,
        'categories'=>[
          ['name'=>'Лекарства','variant_type'=>'none'],
          ['name'=>'Витамини','variant_type'=>'none'],
          ['name'=>'Билки','variant_type'=>'none'],
          ['name'=>'Медицински консумативи','variant_type'=>'none'],
          ['name'=>'Хигиена','variant_type'=>'none'],
        ],
        'units'=>['бр','пакет','мл'],
        'variants'=>[
          ['name'=>'Разфасовка','type'=>'custom','values'=>['10 табл.','20 табл.','30 табл.','60 табл.'],'active'=>true],
          ['name'=>'Дозировка','type'=>'custom','values'=>['5mg','10mg','500mg','1000mg'],'active'=>false],
        ],
      ],
      'stationery' => [
        'label'=>'Книжарница','sub'=>'формат, цвят',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>9,
        'categories'=>[
          ['name'=>'Тетрадки','variant_type'=>'none'],
          ['name'=>'Хартия','variant_type'=>'none'],
          ['name'=>'Пишещи','variant_type'=>'none'],
          ['name'=>'Книги','variant_type'=>'none'],
          ['name'=>'Офис консумативи','variant_type'=>'none'],
        ],
        'units'=>['бр','пакет','сет'],
        'variants'=>[
          ['name'=>'Формат','type'=>'custom','values'=>['A3','A4','A5','B5'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Корица','type'=>'custom','values'=>['Мека','Твърда'],'active'=>false],
        ],
      ],
      'construction' => [
        'label'=>'Строителни','sub'=>'разфасовка, размер',
        'is_perishable'=>0,'wholesale_enabled'=>1,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Инструменти','variant_type'=>'none'],
          ['name'=>'Крепежи','variant_type'=>'none'],
          ['name'=>'Бои и лакове','variant_type'=>'none'],
          ['name'=>'Електро материали','variant_type'=>'none'],
          ['name'=>'ВиК','variant_type'=>'none'],
          ['name'=>'Сухи смеси','variant_type'=>'none'],
        ],
        'units'=>['бр','кг','л','м','м²'],
        'variants'=>[
          ['name'=>'Разфасовка','type'=>'custom','values'=>['1кг','5кг','25кг','1л','5л','10л'],'active'=>true],
          ['name'=>'Размер','type'=>'custom','values'=>['10мм','20мм','50мм','100мм'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>false],
        ],
      ],
      'auto_parts' => [
        'label'=>'Авто части','sub'=>'вискозитет, страна',
        'is_perishable'=>0,'wholesale_enabled'=>1,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Масла','variant_type'=>'none'],
          ['name'=>'Филтри','variant_type'=>'none'],
          ['name'=>'Спирачна система','variant_type'=>'none'],
          ['name'=>'Окачване','variant_type'=>'none'],
          ['name'=>'Акумулатори','variant_type'=>'none'],
          ['name'=>'Аксесоари','variant_type'=>'none'],
        ],
        'units'=>['бр','л','к-кт'],
        'variants'=>[
          ['name'=>'Вискозитет','type'=>'custom','values'=>['5W-30','5W-40','10W-40','0W-20'],'active'=>true],
          ['name'=>'Страна','type'=>'custom','values'=>['Лява','Дясна','Предна','Задна'],'active'=>true],
          ['name'=>'Волтаж','type'=>'custom','values'=>['12V','24V'],'active'=>false],
        ],
      ],
      'flowers' => [
        'label'=>'Цветарница','sub'=>'размер, цвят, повод',
        'is_perishable'=>1,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Букети','variant_type'=>'none'],
          ['name'=>'Саксийни цветя','variant_type'=>'none'],
          ['name'=>'Рязан цвят','variant_type'=>'none'],
          ['name'=>'Подаръци','variant_type'=>'none'],
          ['name'=>'Торове','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт','кг'],
        'variants'=>[
          ['name'=>'Размер','type'=>'custom','values'=>['Малък','Среден','Голям'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Повод','type'=>'custom','values'=>['Рожден ден','Сватба','Юбилей'],'active'=>false],
        ],
      ],
      'toys' => [
        'label'=>'Играчки','sub'=>'възраст, материал',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Плюшени','variant_type'=>'none'],
          ['name'=>'Конструктори','variant_type'=>'none'],
          ['name'=>'Пъзели','variant_type'=>'none'],
          ['name'=>'Кукли','variant_type'=>'none'],
          ['name'=>'Колички','variant_type'=>'none'],
          ['name'=>'Образователни','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт'],
        'variants'=>[
          ['name'=>'Възраст','type'=>'custom','values'=>['0-3 г.','3-6 г.','6-12 г.','12+ г.'],'active'=>true],
          ['name'=>'Материал','type'=>'custom','values'=>['Пластмаса','Дърво','Плюш','Метал'],'active'=>true],
        ],
      ],
      'jewelry' => [
        'label'=>'Бижута','sub'=>'материал, размер',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Пръстени','variant_type'=>'size'],
          ['name'=>'Обеци','variant_type'=>'none'],
          ['name'=>'Колиета','variant_type'=>'none'],
          ['name'=>'Гривни','variant_type'=>'none'],
          ['name'=>'Часовници','variant_type'=>'none'],
        ],
        'units'=>['бр','чифт','к-кт'],
        'variants'=>[
          ['name'=>'Материал','type'=>'custom','values'=>['Злато','Сребро','Стомана','Бижутерийна сплав'],'active'=>true],
          ['name'=>'Размер (пръстен)','type'=>'custom','values'=>['48','50','52','54','56','58'],'active'=>true],
          ['name'=>'Камък','type'=>'custom','values'=>['Диамант','Цирконий','Перла','Без камък'],'active'=>false],
        ],
      ],
      'home_goods' => [
        'label'=>'Домашни потреби','sub'=>'размер, материал',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[
          ['name'=>'Кухня','variant_type'=>'none'],
          ['name'=>'Баня','variant_type'=>'none'],
          ['name'=>'Текстил за дома','variant_type'=>'size_color'],
          ['name'=>'Осветление','variant_type'=>'none'],
          ['name'=>'Декорация','variant_type'=>'none'],
        ],
        'units'=>['бр','к-кт','чифт'],
        'variants'=>[
          ['name'=>'Размер','type'=>'custom','values'=>['S','M','L','XL','200x220','160x200'],'active'=>true],
          ['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],
          ['name'=>'Материал','type'=>'custom','values'=>['Стъкло','Керамика','Метал','Текстил'],'active'=>false],
        ],
      ],
    ];
}

$presets = getPresets();
$ALL_UNITS = ['бр','к-кт','чифт','сет','дузина','кг','гр','мг','т','л','мл','м³','м','см','мм','м²','стек','кашон','кутия','пакет','палет','ролка','чувал'];
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

.wrap{max-width:480px;margin:0 auto;padding:28px 16px 48px;position:relative;z-index:1}

/* LOGO */
.logo{text-align:center;margin-bottom:32px}
.logo-name{font-size:22px;font-weight:900;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
.logo-sub{font-size:12px;color:#6b7280;margin-top:3px}

/* PROGRESS */
.prog-wrap{display:flex;gap:6px;margin-bottom:32px}
.prog-step{flex:1;height:4px;border-radius:2px;background:rgba(99,102,241,.15);transition:background .4s}
.prog-step.on{background:#6366f1;box-shadow:0 0 8px rgba(99,102,241,.5)}

/* STEP HEADER */
.step-num{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}
.step-title{font-size:24px;font-weight:800;color:#f1f5f9;margin-bottom:6px;line-height:1.2}
.step-sub{font-size:13px;color:#6b7280;line-height:1.5;margin-bottom:22px}

/* BIZ GRID — 2 columns */
.biz-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:8px}

/* BIZ CARD */
.biz-card{
  background:rgba(15,15,40,.7);
  border:1px solid rgba(99,102,241,.12);
  border-radius:16px;padding:16px 14px;
  cursor:pointer;transition:all .2s;
  -webkit-user-select:none;user-select:none;
  position:relative;
}
.biz-card::before{
  content:'';position:absolute;inset:0;border-radius:inherit;
  background:linear-gradient(135deg,rgba(99,102,241,.05),transparent);
  pointer-events:none;
}
.biz-card:active{transform:scale(.97)}
.biz-card.selected{
  border-color:#6366f1;
  background:rgba(99,102,241,.14);
  box-shadow:0 0 24px rgba(99,102,241,.2);
}
.biz-icon{margin-bottom:10px;display:flex;align-items:center}
.biz-name{font-size:13px;font-weight:700;color:#f1f5f9;margin-bottom:2px}
.biz-sub-text{font-size:10px;color:#6b7280;line-height:1.4}
.biz-check{
  position:absolute;top:10px;right:10px;
  width:18px;height:18px;border-radius:50%;
  background:#6366f1;
  display:none;align-items:center;justify-content:center;
  box-shadow:0 0 8px rgba(99,102,241,.6);
}
.biz-card.selected .biz-check{display:flex}

/* HINT */
.multi-hint{text-align:center;font-size:11px;color:#6b7280;margin-bottom:16px}
.multi-hint span{color:#6366f1}

/* SEC LABEL */
.sec-label{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin:16px 0 8px}

/* AI BOX */
.ai-box{background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(168,85,247,.07));border:1px solid rgba(99,102,241,.18);border-radius:14px;padding:12px 14px;margin-bottom:14px}
.ai-label{font-size:10px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px}
.ai-text{font-size:13px;color:#a5b4fc;line-height:1.6}

/* SEARCH */
.search-box{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#e2e8f0;font-size:14px;padding:10px 14px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s;margin-bottom:10px}
.search-box:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.search-box::placeholder{color:#4b5563}

/* CHIPS */
.chip-group{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
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
.v-name{font-size:14px;font-weight:700;color:#f1f5f9;margin-bottom:2px}
.v-ex{font-size:11px;color:#6b7280;margin-bottom:5px}
.v-vals{display:flex;gap:4px;flex-wrap:wrap}
.v-val{padding:2px 8px;border-radius:6px;font-size:11px;background:rgba(99,102,241,.1);color:#a5b4fc;border:1px solid rgba(99,102,241,.15)}

/* OTHER TYPE */
.textarea-box{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#e2e8f0;font-size:14px;padding:12px 14px;font-family:'Montserrat',sans-serif;outline:none;resize:none;line-height:1.6;margin-bottom:12px}
.textarea-box:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.textarea-box::placeholder{color:#4b5563}
.confirm-row{display:flex;gap:8px}
.btn-yes{flex:1;padding:11px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);border-radius:12px;color:#22c55e;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif}
.btn-change{flex:1;padding:11px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#a5b4fc;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif}

/* LOADING */
.loading-box{text-align:center;padding:24px;color:#6b7280}
.spinner{width:32px;height:32px;border:3px solid rgba(99,102,241,.2);border-top-color:#6366f1;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 10px}

/* NOTE */
.note{font-size:11px;color:#4b5563;text-align:center;margin-top:8px;line-height:1.5}

/* BUTTONS */
.btn-next{width:100%;padding:14px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:14px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(99,102,241,.35),inset 0 1px 0 rgba(255,255,255,.16);margin-top:10px;transition:all .2s}
.btn-next:active{transform:scale(.98)}
.btn-next.green{background:linear-gradient(to bottom,#22c55e,#16a34a);box-shadow:0 4px 20px rgba(34,197,94,.35)}
.btn-back{width:100%;padding:12px;background:transparent;border:1px solid rgba(99,102,241,.15);border-radius:14px;color:#6b7280;font-size:13px;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:8px}

/* STEPS */
.step-content{display:none}
.step-content.active{display:block;animation:fadeUp .3s ease}

@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
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
    <input type="hidden" name="business_types" id="f_btypes" value="[]">
    <input type="hidden" name="units_json" id="f_units">
    <input type="hidden" name="variants_json" id="f_variants">
    <input type="hidden" name="is_perishable" id="f_perishable" value="0">
    <input type="hidden" name="wholesale_enabled" id="f_wholesale" value="0">
    <input type="hidden" name="tax_group" id="f_tax" value="20">

    <!-- ═══ СТЪПКА 1 ═══ -->
    <div class="step-content active" id="step1">
      <div class="step-num">Стъпка 1 от 3</div>
      <div class="step-title">Какво продаваш?</div>
      <div class="step-sub">Избери един или повече типа — AI настройва всичко автоматично</div>

      <div class="multi-hint">Можеш да избереш <span>повече от един</span> ако имаш смесен магазин</div>

      <div class="biz-grid">
        <?php foreach ($presets as $key => $p): ?>
        <div class="biz-card" data-type="<?= $key ?>" onclick="toggleBiz('<?= $key ?>')">
          <div class="biz-icon"><?= getBizIcon($key) ?></div>
          <div class="biz-name"><?= $p['label'] ?></div>
          <div class="biz-sub-text"><?= $p['sub'] ?></div>
          <div class="biz-check">
            <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5"><path d="M2 6l3 3 5-5"/></svg>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="biz-card" data-type="other" onclick="toggleBiz('other')">
          <div class="biz-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
              <path fill="#6366f1" fill-opacity=".48" d="M12 9.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z"/>
              <path fill="#6366f1" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2Zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8Zm-1-4h2v2h-2zm0-8h2v6h-2z"/>
            </svg>
          </div>
          <div class="biz-name">Друг тип</div>
          <div class="biz-sub-text">опиши → AI настройва</div>
          <div class="biz-check"><svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5"><path d="M2 6l3 3 5-5"/></svg></div>
        </div>
      </div>

      <!-- Друг тип -->
      <div id="otherBox" style="display:none;margin-top:8px">
        <textarea class="textarea-box" id="customDesc" rows="3" placeholder="напр. Продавам риболовни принадлежности и части за велосипеди..."></textarea>
        <button type="button" class="btn-next" onclick="generateAI()" id="aiBtn">AI анализира →</button>
        <div class="loading-box" id="aiLoading" style="display:none">
          <div class="spinner"></div>
          <div>AI анализира твоя бизнес...</div>
        </div>
        <div id="aiPreview" style="display:none">
          <div class="ai-box">
            <div class="ai-label">✦ AI предложи</div>
            <div class="ai-text" id="aiPreviewText"></div>
          </div>
          <div class="confirm-row">
            <button type="button" class="btn-yes" onclick="confirmAI()">Да, продължи ✓</button>
            <button type="button" class="btn-change" onclick="resetAI()">Промени</button>
          </div>
        </div>
      </div>

      <button type="button" class="btn-next" id="step1Btn" onclick="goStep(2)" style="display:none">Напред →</button>
    </div>

    <!-- ═══ СТЪПКА 2 ═══ -->
    <div class="step-content" id="step2">
      <div class="step-num">Стъпка 2 от 3</div>
      <div class="step-title">Мерни единици</div>
      <div class="step-sub">AI избра подходящите. Добави или премахни.</div>

      <div class="ai-box">
        <div class="ai-label">✦ AI предложи</div>
        <div class="ai-text" id="unitsAiText"></div>
      </div>

      <div class="sec-label">Избрани</div>
      <div class="chip-group" id="selectedUnitsEl"></div>

      <div class="sec-label">Всички налични</div>
      <input type="text" class="search-box" placeholder="Търси мерна единица..." oninput="filterUnits(this.value)">
      <div class="chip-group" id="allUnitsEl"></div>

      <div class="sec-label">Добави своя</div>
      <div class="add-row">
        <input type="text" class="add-input" id="customUnit" placeholder="напр. ринг, торба, пал...">
        <button type="button" class="add-btn" onclick="addCustomUnit()">+ Добави</button>
      </div>

      <button type="button" class="btn-next" onclick="goStep(3)">Напред →</button>
      <button type="button" class="btn-back" onclick="goStep(1)">← Назад</button>
    </div>

    <!-- ═══ СТЪПКА 3 ═══ -->
    <div class="step-content" id="step3">
      <div class="step-num">Стъпка 3 от 3</div>
      <div class="step-title">Характеристики</div>
      <div class="step-sub">Маркирай кои да се показват при добавяне на артикул</div>

      <div class="ai-box">
        <div class="ai-label">✦ AI предложи</div>
        <div class="ai-text" id="variantsAiText"></div>
      </div>

      <div id="variantsList"></div>

      <div class="sec-label">Добави своя</div>
      <div class="add-row">
        <input type="text" class="add-input" id="customVariant" placeholder="напр. Колекция, Марка, Стил...">
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
const ALL_UNITS_BASE = <?= json_encode($ALL_UNITS, JSON_UNESCAPED_UNICODE) ?>;

let selectedTypes = [];
let customPreset = null;
let selectedUnits = [];
let allUnitsPool = [];
let variantsData = [];

function toggleBiz(type) {
  const card = document.querySelector(`[data-type="${type}"]`);
  const idx = selectedTypes.indexOf(type);
  if (idx === -1) {
    selectedTypes.push(type);
    card.classList.add('selected');
  } else {
    selectedTypes.splice(idx, 1);
    card.classList.remove('selected');
  }

  // Показване на "Друг тип" textarea
  document.getElementById('otherBox').style.display =
    selectedTypes.includes('other') ? 'block' : 'none';

  // Показване на Напред бутона
  const hasNonOther = selectedTypes.some(t => t !== 'other');
  const hasOther = selectedTypes.includes('other');
  document.getElementById('step1Btn').style.display =
    (hasNonOther || (hasOther && customPreset)) ? 'block' : 'none';
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

function mergePresets() {
  let units = new Set();
  let allUnits = new Set(ALL_UNITS_BASE);
  let variantMap = {};
  let isPerishable = 0, wholesale = 0, taxGroup = 20;

  const types = selectedTypes.filter(t => t !== 'other');
  for (const t of types) {
    const p = PRESETS[t];
    if (!p) continue;
    p.units.forEach(u => units.add(u));
    (p.all_units || []).forEach(u => allUnits.add(u));
    p.variants.forEach(v => {
      if (!variantMap[v.name]) variantMap[v.name] = {...v};
      else if (v.active) variantMap[v.name].active = true;
    });
    if (p.is_perishable) isPerishable = 1;
    if (p.wholesale_enabled) wholesale = 1;
    if (p.tax_group < taxGroup) taxGroup = p.tax_group;
  }

  if (customPreset) {
    customPreset.units.forEach(u => units.add(u));
    customPreset.variants.forEach(v => {
      if (!variantMap[v.name]) variantMap[v.name] = {...v};
    });
    if (customPreset.is_perishable) isPerishable = 1;
    if (customPreset.wholesale_enabled) wholesale = 1;
  }

  document.getElementById('f_perishable').value = isPerishable;
  document.getElementById('f_wholesale').value = wholesale;
  document.getElementById('f_tax').value = taxGroup;

  return {
    units: [...units],
    allUnits: [...allUnits],
    variants: Object.values(variantMap),
    labels: types.map(t => PRESETS[t]?.label || '').join(', ')
  };
}

function prepareStep2() {
  const merged = mergePresets();
  selectedUnits = [...merged.units];
  allUnitsPool = [...new Set([...ALL_UNITS_BASE, ...merged.allUnits])];

  const types = selectedTypes.filter(t => t !== 'other').map(t => PRESETS[t]?.label).filter(Boolean);
  const customLabel = customPreset?.label || '';
  const allLabels = [...types, customLabel].filter(Boolean).join(', ');

  document.getElementById('unitsAiText').textContent =
    'За ' + (allLabels || 'твоя бизнес') + ' предлагам: ' + merged.units.join(', ');

  renderUnits('');
}

function renderUnits(filter) {
  const sel = document.getElementById('selectedUnitsEl');
  const all = document.getElementById('allUnitsEl');
  sel.innerHTML = selectedUnits.map(u =>
    `<span class="chip on" onclick="toggleUnit('${u}')">${u} ✓</span>`
  ).join('');
  const filtered = allUnitsPool.filter(u => u.toLowerCase().includes(filter.toLowerCase()));
  all.innerHTML = filtered.map(u =>
    `<span class="chip ${selectedUnits.includes(u)?'on':''}" onclick="toggleUnit('${u}')">${u}</span>`
  ).join('');
}

function filterUnits(v) { renderUnits(v); }

function toggleUnit(u) {
  selectedUnits.includes(u)
    ? selectedUnits = selectedUnits.filter(x => x !== u)
    : selectedUnits.push(u);
  renderUnits(document.querySelector('.search-box')?.value || '');
}

function addCustomUnit() {
  const v = document.getElementById('customUnit').value.trim();
  if (!v) return;
  if (!allUnitsPool.includes(v)) allUnitsPool.push(v);
  if (!selectedUnits.includes(v)) selectedUnits.push(v);
  document.getElementById('customUnit').value = '';
  renderUnits('');
}

function prepareStep3() {
  const merged = mergePresets();
  variantsData = merged.variants;
  const active = variantsData.filter(v => v.active).map(v => v.name).join(', ');
  document.getElementById('variantsAiText').textContent =
    'Включени по подразбиране: ' + (active || 'никой — маркирай сам');
  renderVariants();
}

function renderVariants() {
  document.getElementById('variantsList').innerHTML = variantsData.map((v, i) => `
    <div class="variant-card ${v.active?'on':''}" onclick="toggleVariant(${i})">
      <div class="v-check">${v.active?'<svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5"><path d="M2 6l3 3 5-5"/></svg>':''}</div>
      <div style="flex:1">
        <div class="v-name">${v.name}</div>
        <div class="v-ex">${v.type==='color'?'Галерия с цветове':v.values.slice(0,4).join(', ')+(v.values.length>4?'...':'')}</div>
        ${v.values.length?`<div class="v-vals">${v.values.slice(0,5).map(x=>`<span class="v-val">${x}</span>`).join('')}${v.values.length>5?'<span class="v-val">+</span>':''}</div>`:''}
      </div>
    </div>`).join('');
}

function toggleVariant(i) {
  variantsData[i].active = !variantsData[i].active;
  renderVariants();
}

function addCustomVariant() {
  const v = document.getElementById('customVariant').value.trim();
  if (!v) return;
  variantsData.push({name:v,type:'custom',values:[],active:true});
  document.getElementById('customVariant').value = '';
  renderVariants();
}

// Друг тип — AI
async function generateAI() {
  const desc = document.getElementById('customDesc').value.trim();
  if (!desc) return;
  document.getElementById('aiBtn').style.display = 'none';
  document.getElementById('aiLoading').style.display = 'block';
  document.getElementById('aiPreview').style.display = 'none';

  try {
    const r = await fetch('chat-send.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({message:`Генерирай JSON конфигурация за RunMyStore.ai за бизнес: "${desc}". Върни САМО валиден JSON без обяснения:
{"label":"Наименование","categories":[{"name":"Кат1","variant_type":"none"}],"units":["бр"],"variants":[{"name":"Вариант","type":"custom","values":["ст1"],"active":true}],"is_perishable":false,"wholesale_enabled":false,"tax_group":20}`})
    });
    const d = await r.json();
    const text = d.response || d.message || '';
    const match = text.match(/\{[\s\S]*\}/);
    if (match) {
      customPreset = JSON.parse(match[0]);
      document.getElementById('aiPreviewText').innerHTML =
        `<b>${customPreset.label}</b><br>` +
        `Категории: ${customPreset.categories.slice(0,4).map(c=>c.name).join(', ')}<br>` +
        `Единици: ${customPreset.units.join(', ')}<br>` +
        `Характеристики: ${customPreset.variants.filter(v=>v.active).map(v=>v.name).join(', ')}`;
      document.getElementById('aiLoading').style.display = 'none';
      document.getElementById('aiPreview').style.display = 'block';
    } else throw new Error();
  } catch {
    document.getElementById('aiLoading').style.display = 'none';
    document.getElementById('aiBtn').style.display = 'block';
    alert('AI не успя. Опитай отново.');
  }
}

function confirmAI() {
  document.getElementById('step1Btn').style.display = 'block';
}

function resetAI() {
  customPreset = null;
  document.getElementById('aiPreview').style.display = 'none';
  document.getElementById('aiBtn').style.display = 'block';
}

function saveAndFinish() {
  if (!selectedTypes.length) return;
  document.getElementById('f_btypes').value = JSON.stringify(selectedTypes);
  document.getElementById('f_units').value = JSON.stringify(selectedUnits);
  document.getElementById('f_variants').value = JSON.stringify(variantsData);
  document.getElementById('mainForm').submit();
}
</script>
</body>
</html>
<?php
function getBizIcon($key) {
  $icons = [
    'fashion' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M16 2a4 4 0 0 1-8 0L3.62 3.46A2 2 0 0 0 2.28 5.7l.58 3.56A1 1 0 0 0 3.85 10H6v12h12V10h2.15a1 1 0 0 0 .99-.74l.58-3.56a2 2 0 0 0-1.34-2.24L16 2Z"/><path fill="#6366f1" d="M9 3.8A4.01 4.01 0 0 0 16 2l4.38 1.46A2 2 0 0 1 21.72 5.7l-.58 3.56A1 1 0 0 1 20.15 10H18v12H6V10H3.85a1 1 0 0 1-.99-.74L2.28 5.7A2 2 0 0 1 3.62 3.46L8 2a4.01 4.01 0 0 0 1 1.8Z"/></svg>',
    'footwear' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M2 14h8l2-4 4 2 4-2v4H2v-2z"/><path fill="#6366f1" d="M2 16h18a2 2 0 0 0 2-2v-2l-4 2-4-2-2 4H2v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2H2v2z"/></svg>',
    'grocery' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M3 6h18l-1.5 9H4.5L3 6Z"/><path fill="#6366f1" d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Zm3 9a3 3 0 0 0 6 0"/></svg>',
    'cosmetics' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M8 2h8v4H8zM7 6h10l1 16H6L7 6z"/><path fill="#6366f1" d="M9 2h6a1 1 0 0 1 1 1v3H8V3a1 1 0 0 1 1-1ZM6 6h12l1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L6 6Z"/></svg>',
    'electronics' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M4 5h16v10H4z"/><path fill="#6366f1" d="M2 3h20a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm1 2v10h18V5H3Zm5 12h8v2H8v-2Z"/></svg>',
    'sports' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2Z"/><path fill="#6366f1" d="M12 4a8 8 0 0 1 5.66 13.66L6.34 6.34A7.97 7.97 0 0 1 12 4ZM6.34 17.66A8 8 0 0 1 17.66 6.34L6.34 17.66Z"/></svg>',
    'pharmacy' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M12 3 4 6v6c0 5.25 3.5 10.15 8 11.35C16.5 22.15 20 17.25 20 12V6l-8-3Z"/><path fill="#6366f1" d="M11 10V8h2v2h2v2h-2v2h-2v-2H9v-2h2Z"/></svg>',
    'stationery' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M6.5 4A2.5 2.5 0 0 0 4 6.5v11A2.5 2.5 0 0 0 6.5 20H20V4H6.5Z"/><path fill="#6366f1" d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-2H6.5A.5.5 0 0 1 6 19.5v-.5h14V2H6.5A2.5 2.5 0 0 0 4 4.5v15ZM8 8h8v1.5H8V8Zm0 3h6v1.5H8V11Z"/></svg>',
    'construction' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M3 10h18v11H3z"/><path fill="#6366f1" d="M12 2 3 8v14h18V8L12 2ZM5 20v-9h14v9H5Zm7-16 7 4.67V9H5V8.67L12 4Z"/></svg>',
    'auto_parts' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M4 9h16l1 5H3L4 9Z"/><path fill="#6366f1" d="M7 5h10l1 4H6L7 5Zm-3 6h16l.5 3H3.5L4 11Zm-.5 5h17l.5 3H3l.5-3ZM5.5 19a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Zm13 0a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z"/></svg>',
    'flowers' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M12 22v-8c0-3-2-5-5-6 1 3 2 5 5 6V2c0 3 1 5 4 6-3 1-4 3-4 6v8h0Z"/><path fill="#6366f1" d="M12 2c0 4 2 6 5 7-3 1-5 3-5 7v6h2v-6c0-3 1.5-4.5 4-5.5C15.5 9.5 14 7.5 14 4l-2-2Zm-2 2C8 7.5 6.5 9.5 4 10.5 6.5 11.5 8 13 8 16v6h2v-6c0-4-2-6-5-7 3-1 5-3 5-5V4Z"/></svg>',
    'toys' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M4 12h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Z"/><path fill="#6366f1" d="M12 2a4 4 0 0 1 4 4H8a4 4 0 0 1 4-4ZM4 10h16a2 2 0 0 1 2 2v1H2v-1a2 2 0 0 1 2-2Zm2 6h4v2H6v-2Zm8 0h4v2h-4v-2Z"/></svg>',
    'jewelry' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="m12 2 3 6h6l-5 4 2 7-6-4-6 4 2-7L3 8h6l3-6Z"/><path fill="#6366f1" d="M12 4.24 9.7 9H5.24l3.83 2.94-1.47 5.06L12 14.08l4.4 2.92-1.47-5.06L18.76 9H14.3L12 4.24Z"/></svg>',
    'home_goods' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#6366f1" fill-opacity=".48" d="M5 12v9h6v-5h2v5h6v-9l-7-7-7 7Z"/><path fill="#6366f1" d="M12 3.17 22 11h-2v11h-7v-5h-2v5H4V11H2l10-7.83ZM10 13v6h4v-6h-4Z"/></svg>',
  ];
  return $icons[$key] ?? '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle fill="#6366f1" fill-opacity=".48" cx="12" cy="12" r="8"/><path fill="#6366f1" d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm-1-5h2v2h-2zm0-8h2v6h-2z"/></svg>';
}
?>
