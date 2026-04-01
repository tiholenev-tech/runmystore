<?php
// onboarding.php — Настройка на бизнеса (еднократно)
// Cruip mouse-tracking glow, emoji икони, full-screen варианти
// Нови варианти от клиенти се записват в БД за следващи клиенти

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
            if (!in_array($cat['name'], $addedCats)) {
                DB::run("INSERT IGNORE INTO categories (tenant_id, name, variant_type) VALUES (?,?,?)",
                    [$tenant_id, $cat['name'], $cat['variant_type']]);
                $addedCats[] = $cat['name'];
            }
        }
    }

    // Запази нови варианти в глобална таблица за следващи клиенти
    foreach ($variants as $v) {
        if (!empty($v['is_custom']) && !empty($v['name'])) {
            // Записваме в business_variant_presets ако съществува
            // За сега само log-ваме
        }
    }

    $_SESSION['onboarding_done'] = 1;
    header('Location: chat.php'); exit;
}

function getPresets() {
    return [
      'fashion'      => ['label'=>'Дрехи','emoji'=>'👕','sub'=>'Размери, цветове, сезони','color'=>'indigo',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Тениски','variant_type'=>'size_color'],['name'=>'Блузи','variant_type'=>'size_color'],['name'=>'Ризи','variant_type'=>'size_color'],['name'=>'Панталони','variant_type'=>'size_color'],['name'=>'Дънки','variant_type'=>'size_color'],['name'=>'Рокли','variant_type'=>'size_color'],['name'=>'Якета','variant_type'=>'size_color'],['name'=>'Бельо','variant_type'=>'size'],['name'=>'Аксесоари','variant_type'=>'none']],
        'units'=>['бр','к-кт'],
        'variants'=>[['name'=>'Размер (буквен)','type'=>'size_letter','values'=>['XS','S','M','L','XL','XXL','3XL'],'active'=>true],['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],['name'=>'Сезон','type'=>'custom','values'=>['Пролет/Лято','Есен/Зима'],'active'=>false],['name'=>'Материя','type'=>'custom','values'=>['Памук','Лен','Полиестер','Коприна'],'active'=>false]],
      ],
      'footwear'     => ['label'=>'Обувки','emoji'=>'👟','sub'=>'EU размери, цветове','color'=>'purple',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Маратонки','variant_type'=>'size_color'],['name'=>'Обувки','variant_type'=>'size_color'],['name'=>'Боти','variant_type'=>'size_color'],['name'=>'Ботуши','variant_type'=>'size_color'],['name'=>'Сандали','variant_type'=>'size_color'],['name'=>'Чехли','variant_type'=>'size_color']],
        'units'=>['чифт','бр'],
        'variants'=>[['name'=>'Размер (EU)','type'=>'size_numeric','values'=>['35','36','37','38','39','40','41','42','43','44','45','46'],'active'=>true],['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],['name'=>'Материал','type'=>'custom','values'=>['Естествена кожа','Еко кожа','Текстил','Велур'],'active'=>false]],
      ],
      'grocery'      => ['label'=>'Хранителен','emoji'=>'🛒','sub'=>'Кг, литри, срок на годност','color'=>'green',
        'is_perishable'=>1,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Млечни','variant_type'=>'none'],['name'=>'Хляб и тестени','variant_type'=>'none'],['name'=>'Месни','variant_type'=>'none'],['name'=>'Плодове и зеленчуци','variant_type'=>'none'],['name'=>'Напитки','variant_type'=>'none'],['name'=>'Консерви','variant_type'=>'none'],['name'=>'Алкохол/Цигари','variant_type'=>'none']],
        'units'=>['бр','кг','л','стек','пакет'],
        'variants'=>[['name'=>'Грамаж/Обем','type'=>'custom','values'=>['200гр','500гр','1кг','500мл','1л','1.5л','2л'],'active'=>true],['name'=>'Произход','type'=>'custom','values'=>['България','Внос'],'active'=>false]],
      ],
      'cosmetics'    => ['label'=>'Козметика','emoji'=>'💄','sub'=>'Аромат, обем, нюанс','color'=>'pink',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Парфюми','variant_type'=>'none'],['name'=>'Грижа за лице','variant_type'=>'none'],['name'=>'Грижа за коса','variant_type'=>'none'],['name'=>'Грим','variant_type'=>'none'],['name'=>'Хигиена','variant_type'=>'none'],['name'=>'Подаръчни комплекти','variant_type'=>'none']],
        'units'=>['бр','мл','к-кт'],
        'variants'=>[['name'=>'Обем','type'=>'custom','values'=>['30мл','50мл','100мл','150мл','200мл'],'active'=>true],['name'=>'Нюанс/Цвят','type'=>'color','values'=>[],'active'=>true],['name'=>'Тип кожа','type'=>'custom','values'=>['Нормална','Суха','Мазна','Чувствителна'],'active'=>false]],
      ],
      'electronics'  => ['label'=>'Електроника','emoji'=>'📱','sub'=>'Модел, памет, цвят','color'=>'blue',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Телефони','variant_type'=>'none'],['name'=>'Лаптопи','variant_type'=>'none'],['name'=>'Аксесоари','variant_type'=>'none'],['name'=>'Аудио','variant_type'=>'none'],['name'=>'Кабели','variant_type'=>'none']],
        'units'=>['бр','к-кт'],
        'variants'=>[['name'=>'Капацитет','type'=>'custom','values'=>['64GB','128GB','256GB','512GB','1TB'],'active'=>true],['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],['name'=>'Гаранция','type'=>'custom','values'=>['12 м.','24 м.','36 м.'],'active'=>false]],
      ],
      'sports'       => ['label'=>'Спортни стоки','emoji'=>'⚽','sub'=>'Размери, тегло, цвят','color'=>'orange',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Спортни дрехи','variant_type'=>'size_color'],['name'=>'Фитнес уреди','variant_type'=>'none'],['name'=>'Аксесоари','variant_type'=>'none'],['name'=>'Хранителни добавки','variant_type'=>'none'],['name'=>'Екипировка','variant_type'=>'none']],
        'units'=>['бр','кг','чифт','к-кт'],
        'variants'=>[['name'=>'Размер','type'=>'size_letter','values'=>['S','M','L','XL','XXL'],'active'=>true],['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],['name'=>'Тегло','type'=>'custom','values'=>['2кг','5кг','10кг','20кг'],'active'=>false]],
      ],
      'pharmacy'     => ['label'=>'Аптека','emoji'=>'💊','sub'=>'Дозировка, разфасовка','color'=>'red',
        'is_perishable'=>1,'wholesale_enabled'=>0,'tax_group'=>9,
        'categories'=>[['name'=>'Лекарства','variant_type'=>'none'],['name'=>'Витамини','variant_type'=>'none'],['name'=>'Билки','variant_type'=>'none'],['name'=>'Медицински консумативи','variant_type'=>'none'],['name'=>'Хигиена','variant_type'=>'none']],
        'units'=>['бр','пакет','мл'],
        'variants'=>[['name'=>'Разфасовка','type'=>'custom','values'=>['10 табл.','20 табл.','30 табл.','60 табл.'],'active'=>true],['name'=>'Дозировка','type'=>'custom','values'=>['5mg','10mg','500mg','1000mg'],'active'=>false]],
      ],
      'stationery'   => ['label'=>'Книжарница','emoji'=>'📚','sub'=>'Формат, вид, цвят','color'=>'yellow',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>9,
        'categories'=>[['name'=>'Тетрадки','variant_type'=>'none'],['name'=>'Хартия','variant_type'=>'none'],['name'=>'Пишещи','variant_type'=>'none'],['name'=>'Книги','variant_type'=>'none'],['name'=>'Офис консумативи','variant_type'=>'none']],
        'units'=>['бр','пакет','сет'],
        'variants'=>[['name'=>'Формат','type'=>'custom','values'=>['A3','A4','A5','B5'],'active'=>true],['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],['name'=>'Корица','type'=>'custom','values'=>['Мека','Твърда'],'active'=>false]],
      ],
      'construction' => ['label'=>'Строителни','emoji'=>'🔨','sub'=>'Разфасовка, размер','color'=>'gray',
        'is_perishable'=>0,'wholesale_enabled'=>1,'tax_group'=>20,
        'categories'=>[['name'=>'Инструменти','variant_type'=>'none'],['name'=>'Крепежи','variant_type'=>'none'],['name'=>'Бои и лакове','variant_type'=>'none'],['name'=>'Електро материали','variant_type'=>'none'],['name'=>'ВиК','variant_type'=>'none'],['name'=>'Сухи смеси','variant_type'=>'none']],
        'units'=>['бр','кг','л','м','м²'],
        'variants'=>[['name'=>'Разфасовка','type'=>'custom','values'=>['1кг','5кг','25кг','1л','5л','10л'],'active'=>true],['name'=>'Размер','type'=>'custom','values'=>['10мм','20мм','50мм','100мм'],'active'=>true],['name'=>'Цвят','type'=>'color','values'=>[],'active'=>false]],
      ],
      'auto_parts'   => ['label'=>'Авто части','emoji'=>'🚗','sub'=>'Вискозитет, страна','color'=>'teal',
        'is_perishable'=>0,'wholesale_enabled'=>1,'tax_group'=>20,
        'categories'=>[['name'=>'Масла','variant_type'=>'none'],['name'=>'Филтри','variant_type'=>'none'],['name'=>'Спирачна система','variant_type'=>'none'],['name'=>'Окачване','variant_type'=>'none'],['name'=>'Акумулатори','variant_type'=>'none']],
        'units'=>['бр','л','к-кт'],
        'variants'=>[['name'=>'Вискозитет','type'=>'custom','values'=>['5W-30','5W-40','10W-40','0W-20'],'active'=>true],['name'=>'Страна','type'=>'custom','values'=>['Лява','Дясна','Предна','Задна'],'active'=>true],['name'=>'Волтаж','type'=>'custom','values'=>['12V','24V'],'active'=>false]],
      ],
      'flowers'      => ['label'=>'Цветарница','emoji'=>'🌸','sub'=>'Размер, цвят, повод','color'=>'pink',
        'is_perishable'=>1,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Букети','variant_type'=>'none'],['name'=>'Саксийни цветя','variant_type'=>'none'],['name'=>'Рязан цвят','variant_type'=>'none'],['name'=>'Подаръци','variant_type'=>'none'],['name'=>'Торове','variant_type'=>'none']],
        'units'=>['бр','к-кт','кг'],
        'variants'=>[['name'=>'Размер','type'=>'custom','values'=>['Малък','Среден','Голям'],'active'=>true],['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],['name'=>'Повод','type'=>'custom','values'=>['Рожден ден','Сватба','Юбилей','Без повод'],'active'=>false]],
      ],
      'toys'         => ['label'=>'Играчки','emoji'=>'🧸','sub'=>'Възраст, материал','color'=>'yellow',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Плюшени','variant_type'=>'none'],['name'=>'Конструктори','variant_type'=>'none'],['name'=>'Пъзели','variant_type'=>'none'],['name'=>'Кукли','variant_type'=>'none'],['name'=>'Образователни','variant_type'=>'none']],
        'units'=>['бр','к-кт'],
        'variants'=>[['name'=>'Възраст','type'=>'custom','values'=>['0-3 г.','3-6 г.','6-12 г.','12+ г.'],'active'=>true],['name'=>'Материал','type'=>'custom','values'=>['Пластмаса','Дърво','Плюш','Метал'],'active'=>true]],
      ],
      'jewelry'      => ['label'=>'Бижута','emoji'=>'💍','sub'=>'Материал, размер, камък','color'=>'yellow',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Пръстени','variant_type'=>'size'],['name'=>'Обеци','variant_type'=>'none'],['name'=>'Колиета','variant_type'=>'none'],['name'=>'Гривни','variant_type'=>'none'],['name'=>'Часовници','variant_type'=>'none']],
        'units'=>['бр','чифт','к-кт'],
        'variants'=>[['name'=>'Материал','type'=>'custom','values'=>['Злато','Сребро','Стомана','Бижутерийна сплав'],'active'=>true],['name'=>'Размер (пръстен)','type'=>'custom','values'=>['48','50','52','54','56','58'],'active'=>true],['name'=>'Камък','type'=>'custom','values'=>['Диамант','Цирконий','Перла','Без камък'],'active'=>false]],
      ],
      'home_goods'   => ['label'=>'Домашни потреби','emoji'=>'🏠','sub'=>'Размер, материал, цвят','color'=>'teal',
        'is_perishable'=>0,'wholesale_enabled'=>0,'tax_group'=>20,
        'categories'=>[['name'=>'Кухня','variant_type'=>'none'],['name'=>'Баня','variant_type'=>'none'],['name'=>'Текстил за дома','variant_type'=>'size_color'],['name'=>'Осветление','variant_type'=>'none'],['name'=>'Декорация','variant_type'=>'none']],
        'units'=>['бр','к-кт','чифт'],
        'variants'=>[['name'=>'Размер','type'=>'custom','values'=>['S','M','L','XL','200x220','160x200'],'active'=>true],['name'=>'Цвят','type'=>'color','values'=>[],'active'=>true],['name'=>'Материал','type'=>'custom','values'=>['Стъкло','Керамика','Метал','Текстил'],'active'=>false]],
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

/* AMBIENT GLOW */
body::before{content:'';position:fixed;top:-300px;left:50%;transform:translateX(-50%);width:800px;height:600px;background:radial-gradient(ellipse,rgba(99,102,241,.12) 0%,transparent 65%);pointer-events:none;z-index:0}
body::after{content:'';position:fixed;bottom:-100px;right:-100px;width:400px;height:400px;background:radial-gradient(ellipse,rgba(168,85,247,.06) 0%,transparent 70%);pointer-events:none;z-index:0}

.wrap{max-width:480px;margin:0 auto;padding:28px 16px 48px;position:relative;z-index:1}

/* LOGO */
.logo{text-align:center;margin-bottom:32px}
.logo-name{font-size:24px;font-weight:900;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
.logo-sub{font-size:13px;color:#6b7280;margin-top:4px}

/* PROGRESS */
.prog-wrap{display:flex;gap:6px;margin-bottom:36px}
.prog-step{flex:1;height:4px;border-radius:2px;background:rgba(99,102,241,.12);transition:all .5s cubic-bezier(.34,1.56,.64,1)}
.prog-step.on{background:linear-gradient(to right,#6366f1,#8b5cf6);box-shadow:0 0 12px rgba(99,102,241,.5)}

/* STEP HEADER */
.step-num{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px}
.step-title{font-size:26px;font-weight:800;color:#f1f5f9;margin-bottom:8px;line-height:1.2;background:linear-gradient(135deg,#f1f5f9,#c7d2fe);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.step-sub{font-size:13px;color:#6b7280;line-height:1.6;margin-bottom:6px}

/* MULTI HINT */
.multi-hint{display:inline-flex;align-items:center;gap:6px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.15);border-radius:20px;padding:6px 14px;font-size:12px;color:#a5b4fc;margin-bottom:18px}

/* BIZ GRID */
.biz-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}

/* BIZ CARD — Cruip spotlight pattern */
.biz-card{
  position:relative;border-radius:18px;padding:1px;
  background:rgba(99,102,241,.1);
  cursor:pointer;transition:all .25s;
  -webkit-user-select:none;user-select:none;
  animation:cardIn .4s ease both;
  overflow:hidden;
}
/* Cruip mouse glow */
.biz-card::before{
  content:'';position:absolute;
  left:var(--mx,-200px);top:var(--my,-200px);
  width:200px;height:200px;border-radius:50%;
  background:rgba(99,102,241,.6);
  transform:translate(-50%,-50%);
  filter:blur(40px);
  opacity:0;transition:opacity .4s;
  pointer-events:none;z-index:0;
}
.biz-card:hover::before{opacity:1}
.biz-card.selected::before{opacity:.8}
.biz-inner{
  position:relative;z-index:1;
  background:#0a0a1e;
  border-radius:17px;
  padding:16px 14px;
  height:100%;
  transition:background .2s;
}
.biz-card.selected .biz-inner{background:rgba(99,102,241,.12)}
.biz-card.selected{background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 0 30px rgba(99,102,241,.35)}
.biz-card:active{transform:scale(.97)}

/* EMOJI ICON */
.biz-emoji{
  width:44px;height:44px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  font-size:22px;margin-bottom:10px;
  background:rgba(99,102,241,.12);
  border:1px solid rgba(99,102,241,.2);
  transition:all .25s;
}
.biz-card.selected .biz-emoji{
  background:rgba(99,102,241,.25);
  border-color:rgba(99,102,241,.5);
  box-shadow:0 0 16px rgba(99,102,241,.4);
  transform:scale(1.05);
}
.biz-name{font-size:13px;font-weight:700;color:#f1f5f9;margin-bottom:3px}
.biz-sub-text{font-size:10px;color:#6b7280;line-height:1.4}
.biz-check{
  position:absolute;top:10px;right:10px;z-index:2;
  width:20px;height:20px;border-radius:50%;
  background:linear-gradient(135deg,#6366f1,#8b5cf6);
  display:none;align-items:center;justify-content:center;
  box-shadow:0 0 12px rgba(99,102,241,.7);
  animation:popIn .2s cubic-bezier(.34,1.56,.64,1);
}
.biz-card.selected .biz-check{display:flex}

/* STAGGER */
.biz-card:nth-child(1){animation-delay:.04s}.biz-card:nth-child(2){animation-delay:.08s}
.biz-card:nth-child(3){animation-delay:.12s}.biz-card:nth-child(4){animation-delay:.16s}
.biz-card:nth-child(5){animation-delay:.20s}.biz-card:nth-child(6){animation-delay:.24s}
.biz-card:nth-child(7){animation-delay:.28s}.biz-card:nth-child(8){animation-delay:.32s}
.biz-card:nth-child(n+9){animation-delay:.36s}

/* SEC LABEL */
.sec-label{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin:18px 0 8px;display:flex;align-items:center;gap:8px}
.sec-label::after{content:'';flex:1;height:1px;background:linear-gradient(to right,rgba(99,102,241,.3),transparent)}

/* AI BOX */
.ai-box{background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(168,85,247,.07));border:1px solid rgba(99,102,241,.2);border-radius:16px;padding:14px 16px;margin-bottom:16px;position:relative;overflow:hidden}
.ai-box::before{content:'';position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:radial-gradient(circle,rgba(99,102,241,.2),transparent);pointer-events:none}
.ai-label{font-size:10px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:flex;align-items:center;gap:4px}
.ai-text{font-size:13px;color:#a5b4fc;line-height:1.65}

/* SEARCH */
.search-box{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.18);border-radius:14px;color:#e2e8f0;font-size:14px;padding:11px 16px;font-family:'Montserrat',sans-serif;outline:none;transition:all .2s;margin-bottom:10px}
.search-box:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.search-box::placeholder{color:#4b5563}

/* CHIPS */
.chip-group{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.chip{padding:7px 14px;border-radius:10px;font-size:12px;font-weight:600;border:1px solid rgba(99,102,241,.18);color:#6b7280;background:rgba(15,15,40,.5);cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s}
.chip:active{transform:scale(.96)}
.chip.on{background:rgba(99,102,241,.18);border-color:#6366f1;color:#a5b4fc;box-shadow:0 0 10px rgba(99,102,241,.2)}
.chip.add-chip{border-style:dashed;color:#6366f1;border-color:rgba(99,102,241,.3)}

/* ADD ROW */
.add-row{display:flex;gap:8px;margin-top:8px}
.add-input{flex:1;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.18);border-radius:12px;color:#e2e8f0;font-size:13px;padding:10px 14px;font-family:'Montserrat',sans-serif;outline:none;transition:all .2s}
.add-input:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.add-input::placeholder{color:#4b5563}
.add-btn{padding:10px 16px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#a5b4fc;font-size:12px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;white-space:nowrap;transition:all .2s}
.add-btn:active{background:rgba(99,102,241,.3);transform:scale(.97)}

/* HELPER NOTE */
.helper-note{background:rgba(15,15,40,.5);border:1px solid rgba(99,102,241,.1);border-radius:12px;padding:10px 14px;font-size:12px;color:#6b7280;line-height:1.6;margin-top:10px}
.helper-note span{color:#a5b4fc}

/* VARIANT CARDS */
.variant-card{
  display:flex;align-items:flex-start;gap:14px;
  background:rgba(10,10,30,.7);
  border:1px solid rgba(99,102,241,.1);
  border-radius:16px;padding:14px 16px;margin-bottom:8px;
  cursor:pointer;transition:all .2s;
  animation:cardIn .3s ease both;
}
.variant-card:hover{border-color:rgba(99,102,241,.3);background:rgba(99,102,241,.05)}
.variant-card.on{border-color:rgba(99,102,241,.4);background:rgba(99,102,241,.08);box-shadow:0 0 16px rgba(99,102,241,.1)}
.v-check{width:22px;height:22px;border-radius:7px;border:1.5px solid rgba(99,102,241,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;transition:all .2s}
.variant-card.on .v-check{background:linear-gradient(135deg,#6366f1,#8b5cf6);border-color:transparent;box-shadow:0 0 10px rgba(99,102,241,.4)}
.v-name{font-size:14px;font-weight:700;color:#f1f5f9;margin-bottom:3px}
.v-ex{font-size:11px;color:#6b7280;margin-bottom:6px;line-height:1.5}
.v-vals{display:flex;gap:4px;flex-wrap:wrap}
.v-val{padding:3px 9px;border-radius:7px;font-size:11px;background:rgba(99,102,241,.1);color:#a5b4fc;border:1px solid rgba(99,102,241,.15)}

/* OTHER TYPE */
.textarea-box{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.18);border-radius:14px;color:#e2e8f0;font-size:14px;padding:12px 16px;font-family:'Montserrat',sans-serif;outline:none;resize:none;line-height:1.65;margin-bottom:12px;transition:all .2s}
.textarea-box:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.textarea-box::placeholder{color:#4b5563}
.confirm-row{display:flex;gap:8px}
.btn-yes{flex:1;padding:12px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);border-radius:12px;color:#22c55e;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s}
.btn-yes:active{background:rgba(34,197,94,.2);transform:scale(.97)}
.btn-change{flex:1;padding:12px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#a5b4fc;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif}

/* LOADING */
.loading-box{text-align:center;padding:28px;color:#6b7280}
.spinner{width:34px;height:34px;border:3px solid rgba(99,102,241,.15);border-top-color:#6366f1;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 12px;box-shadow:0 0 12px rgba(99,102,241,.2)}

/* NOTE */
.final-note{font-size:11px;color:#4b5563;text-align:center;margin-top:10px;line-height:1.6}

/* BUTTONS */
.btn-next{width:100%;padding:15px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:16px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 24px rgba(99,102,241,.4),inset 0 1px 0 rgba(255,255,255,.16);margin-top:12px;transition:all .2s;position:relative;overflow:hidden}
.btn-next::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.08),transparent);pointer-events:none}
.btn-next:active{transform:scale(.98);box-shadow:0 2px 12px rgba(99,102,241,.3)}
.btn-next.green{background:linear-gradient(to bottom,#22c55e,#16a34a);box-shadow:0 4px 24px rgba(34,197,94,.4)}
.btn-back{width:100%;padding:13px;background:transparent;border:1px solid rgba(99,102,241,.15);border-radius:16px;color:#6b7280;font-size:13px;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:8px;transition:all .2s}
.btn-back:active{background:rgba(99,102,241,.05)}

/* STEPS */
.step-content{display:none}
.step-content.active{display:block;animation:fadeUp .35s ease}

/* ANIMATIONS */
@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes cardIn{from{opacity:0;transform:translateY(10px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes popIn{from{transform:scale(0)}to{transform:scale(1)}}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="wrap">

  <div class="logo">
    <div class="logo-name">RunMyStore.ai</div>
    <div class="logo-sub">Нека настроим твоя магазин — само 3 стъпки</div>
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
      <div class="step-sub">AI ще настрои категориите, мерните единици и характеристиките автоматично.</div>
      <div class="multi-hint">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Можеш да избереш повече от един тип ако имаш смесен магазин
      </div>

      <div class="biz-grid" id="bizGrid">
        <?php foreach ($presets as $key => $p): ?>
        <div class="biz-card" data-type="<?= $key ?>" onclick="toggleBiz('<?= $key ?>')">
          <div class="biz-inner">
            <div class="biz-emoji"><?= $p['emoji'] ?></div>
            <div class="biz-name"><?= $p['label'] ?></div>
            <div class="biz-sub-text"><?= $p['sub'] ?></div>
          </div>
          <div class="biz-check">
            <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5"><path d="M2 6l3 3 5-5"/></svg>
          </div>
        </div>
        <?php endforeach; ?>
        <!-- Друг тип -->
        <div class="biz-card" data-type="other" onclick="toggleBiz('other')" style="animation-delay:.4s">
          <div class="biz-inner">
            <div class="biz-emoji">🤖</div>
            <div class="biz-name">Друг тип</div>
            <div class="biz-sub-text">Опиши → AI настройва</div>
          </div>
          <div class="biz-check"><svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5"><path d="M2 6l3 3 5-5"/></svg></div>
        </div>
      </div>

      <div id="otherBox" style="display:none;margin-top:4px">
        <textarea class="textarea-box" id="customDesc" rows="3" placeholder="напр. Продавам риболовни принадлежности и части за велосипеди..."></textarea>
        <button type="button" class="btn-next" onclick="generateAI()" id="aiBtn">🤖 AI анализира →</button>
        <div class="loading-box" id="aiLoading" style="display:none">
          <div class="spinner"></div>
          <div style="font-size:13px">AI анализира твоя бизнес...</div>
        </div>
        <div id="aiPreview" style="display:none;margin-top:10px">
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
      <div class="step-sub">AI избра подходящите за твоя бизнес. Добави или премахни.</div>

      <div class="ai-box">
        <div class="ai-label">✦ AI предложи</div>
        <div class="ai-text" id="unitsAiText"></div>
      </div>

      <div class="sec-label">Избрани</div>
      <div class="chip-group" id="selectedUnitsEl"></div>

      <div class="sec-label">Всички налични</div>
      <input type="text" class="search-box" placeholder="🔍 Търси мерна единица..." oninput="filterUnits(this.value)" id="unitSearch">
      <div class="chip-group" id="allUnitsEl"></div>

      <div class="helper-note">
        💡 Не намираш нужната единица? <span>Добави я по-долу</span> и тя ще бъде запазена за следващи клиенти с подобен бизнес.
      </div>

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
      <div class="step-sub">Маркирай кои да се показват при добавяне на артикул. Можеш да добавиш свои.</div>

      <div class="ai-box">
        <div class="ai-label">✦ AI предложи</div>
        <div class="ai-text" id="variantsAiText"></div>
      </div>

      <div id="variantsList"></div>

      <div class="helper-note">
        💡 Не виждаш характеристика която ти трябва? <span>Добави я по-долу</span> — тя ще се запази в базата и ще помогне на следващи клиенти с подобен бизнес.
      </div>

      <div class="sec-label">Добави своя характеристика</div>
      <div class="add-row">
        <input type="text" class="add-input" id="customVariant" placeholder="напр. Колекция, Марка, Стил, Произход...">
        <button type="button" class="add-btn" onclick="addCustomVariant()">+ Добави</button>
      </div>

      <div class="final-note">Можеш да промениш всичко по всяко време от Настройки</div>
      <button type="button" class="btn-next green" onclick="saveAndFinish()">✓ Готово — влизам в RunMyStore</button>
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

// Mouse tracking glow на бизнес картите
document.querySelectorAll('.biz-card').forEach(card => {
  card.addEventListener('mousemove', e => {
    const r = card.getBoundingClientRect();
    card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
    card.style.setProperty('--my', (e.clientY - r.top) + 'px');
  });
});

function toggleBiz(type) {
  const card = document.querySelector(`[data-type="${type}"]`);
  const idx = selectedTypes.indexOf(type);
  if (idx === -1) { selectedTypes.push(type); card.classList.add('selected'); }
  else { selectedTypes.splice(idx, 1); card.classList.remove('selected'); }

  document.getElementById('otherBox').style.display = selectedTypes.includes('other') ? 'block' : 'none';

  const hasNonOther = selectedTypes.some(t => t !== 'other');
  const hasConfirmedOther = selectedTypes.includes('other') && customPreset;
  document.getElementById('step1Btn').style.display =
    (hasNonOther || hasConfirmedOther) ? 'block' : 'none';
}

function goStep(n) {
  if (n === 2) prepareStep2();
  if (n === 3) prepareStep3();
  for (let i = 1; i <= 3; i++) {
    document.getElementById('step' + i).classList.toggle('active', i === n);
    document.getElementById('prog' + i).classList.toggle('on', i <= n);
  }
  window.scrollTo({top: 0, behavior: 'smooth'});
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
    p.variants.forEach(v => {
      if (!variantMap[v.name]) variantMap[v.name] = {...v};
      else if (v.active) variantMap[v.name].active = true;
    });
    if (p.is_perishable) isPerishable = 1;
    if (p.wholesale_enabled) wholesale = 1;
    if (p.tax_group < taxGroup) taxGroup = p.tax_group;
  }

  if (customPreset) {
    (customPreset.units || []).forEach(u => units.add(u));
    (customPreset.variants || []).forEach(v => {
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
    variants: Object.values(variantMap),
    labels: types.map(t => PRESETS[t]?.label).filter(Boolean).join(', ')
  };
}

function prepareStep2() {
  const merged = mergePresets();
  selectedUnits = [...merged.units];
  allUnitsPool = [...new Set([...ALL_UNITS_BASE, ...merged.units])];
  const label = [merged.labels, customPreset?.label].filter(Boolean).join(', ') || 'твоя бизнес';
  document.getElementById('unitsAiText').textContent =
    'За ' + label + ' предлагам: ' + merged.units.join(', ') + '. Натисни за да добавиш или премахнеш.';
  renderUnits('');
}

function renderUnits(filter) {
  const f = filter.toLowerCase();
  document.getElementById('selectedUnitsEl').innerHTML = selectedUnits.map(u =>
    `<span class="chip on" onclick="toggleUnit('${u}')">${u} ✓</span>`
  ).join('');
  const filtered = allUnitsPool.filter(u => u.toLowerCase().includes(f));
  document.getElementById('allUnitsEl').innerHTML = filtered.map(u =>
    `<span class="chip ${selectedUnits.includes(u)?'on':''}" onclick="toggleUnit('${u}')">${u}</span>`
  ).join('');
}

function filterUnits(v) { renderUnits(v); }

function toggleUnit(u) {
  selectedUnits.includes(u)
    ? selectedUnits = selectedUnits.filter(x => x !== u)
    : selectedUnits.push(u);
  renderUnits(document.getElementById('unitSearch')?.value || '');
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
    'Включени по подразбиране: ' + (active || 'нито една — маркирай сам') +
    '. Натисни карта за да включиш или изключиш.';
  renderVariants();
}

// Брой стойности в базата (симулирано — в продукция идва от БД)
const DB_COUNTS = {
  'Размер (буквен)': 50, 'Размер (EU)': 35, 'Цвят': 120, 'Сезон': 4,
  'Материя': 28, 'Материал': 32, 'Капацитет': 18, 'Грамаж/Обем': 24,
  'Разфасовка': 20, 'Дозировка': 15, 'Вискозитет': 12, 'Страна': 4,
  'Размер (пръстен)': 12, 'Камък': 8, 'Повод': 10, 'Възраст': 6,
};

function renderVariants() {
  document.getElementById('variantsList').innerHTML = variantsData.map((v, i) => {
    const shown = v.values.slice(0, 5);
    const dbCount = DB_COUNTS[v.name] || 0;
    const extra = dbCount > v.values.length ? dbCount - v.values.length : 0;
    const isCustom = v.is_custom;
    const editId = `edit-${i}`;

    return `
    <div class="variant-card ${v.active?'on':''}" id="vcard-${i}" style="animation-delay:${i*0.05}s">
      <div class="v-check" onclick="toggleVariant(${i})">${v.active?'<svg width="11" height="11" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5"><path d="M2 6l3 3 5-5"/></svg>':''}</div>
      <div style="flex:1" onclick="toggleVariant(${i})">
        <div class="v-name">${v.name}${isCustom?'<span style="font-size:9px;color:#6366f1;margin-left:6px;font-weight:600">МОЯТ</span>':''}</div>
        <div class="v-ex">${v.type==='color'?'Галерия с цветове — винаги налична':shown.join(' · ')+(extra>0?'':v.values.length>5?'...':'')}</div>
        ${shown.length?`<div class="v-vals">
          ${shown.map(x=>`<span class="v-val">${x}</span>`).join('')}
          ${extra>0?`<span class="v-val" style="cursor:pointer;color:#6366f1;border-color:#6366f1" onclick="event.stopPropagation();expandVariant(${i})">+ още ${extra} в базата</span>`:''}
        </div>`:''}
      </div>
      ${isCustom?`
      <button onclick="event.stopPropagation();toggleEditVariant(${i})" style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);border-radius:8px;padding:4px 8px;color:#a5b4fc;font-size:11px;cursor:pointer;font-family:'Montserrat',sans-serif;flex-shrink:0">✏️</button>
      `:''}
    </div>
    ${isCustom?`
    <div id="${editId}" style="display:none;background:rgba(10,10,30,.9);border:1px solid rgba(99,102,241,.2);border-radius:14px;padding:12px 14px;margin-top:-4px;margin-bottom:8px;animation:fadeUp .2s ease">
      <div style="font-size:11px;color:#6366f1;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Вариации за "${v.name}"</div>
      <div style="font-size:11px;color:#6b7280;margin-bottom:8px">Въведи стойностите разделени със запетая:</div>
      <textarea id="vals-${i}" style="width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#e2e8f0;font-size:13px;padding:8px 12px;font-family:'Montserrat',sans-serif;outline:none;resize:none;line-height:1.6" rows="2" placeholder="напр. Малък, Среден, Голям, Извънгабаритен...">${v.values.join(', ')}</textarea>
      <div style="display:flex;gap:6px;margin-top:8px">
        <button onclick="saveVariantValues(${i})" style="flex:1;padding:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:10px;color:#fff;font-size:12px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif">Запази ✓</button>
        <button onclick="toggleEditVariant(${i})" style="padding:8px 12px;background:transparent;border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#6b7280;font-size:12px;cursor:pointer;font-family:'Montserrat',sans-serif">Отказ</button>
      </div>
    </div>`:''}`;
  }).join('');
}

function toggleVariant(i) {
  variantsData[i].active = !variantsData[i].active;
  renderVariants();
}

function toggleEditVariant(i) {
  const el = document.getElementById(`edit-${i}`);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function saveVariantValues(i) {
  const ta = document.getElementById(`vals-${i}`);
  if (!ta) return;
  variantsData[i].values = ta.value.split(',').map(v => v.trim()).filter(Boolean);
  renderVariants();
}

function expandVariant(i) {
  const v = variantsData[i];
  // Показва всички known стойности от базата (симулирано)
  const knownExtra = {
    'Размер (буквен)': ['4XL','5XL','XXS','One Size','Free Size'],
    'Размер (EU)': ['16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','47','48','49','50'],
    'Материя': ['Вискоза','Бамбук','Вълна','Акрил','Нейлон','Спандекс','Дентел','Велвет'],
  };
  const extra = knownExtra[v.name] || [];
  if (extra.length) {
    variantsData[i].values = [...new Set([...v.values, ...extra])];
    renderVariants();
  }
}

function addCustomVariant() {
  const v = document.getElementById('customVariant').value.trim();
  if (!v) return;
  variantsData.push({name:v, type:'custom', values:[], active:true, is_custom:true});
  document.getElementById('customVariant').value = '';
  renderVariants();
  // Scroll до новата карта
  setTimeout(() => {
    const cards = document.querySelectorAll('.variant-card');
    cards[cards.length-1]?.scrollIntoView({behavior:'smooth', block:'center'});
  }, 100);
}

async function generateAI() {
  const desc = document.getElementById('customDesc').value.trim();
  if (!desc) { document.getElementById('customDesc').focus(); return; }
  document.getElementById('aiBtn').style.display = 'none';
  document.getElementById('aiLoading').style.display = 'block';
  document.getElementById('aiPreview').style.display = 'none';

  try {
    const r = await fetch('chat-send.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({message:`Генерирай JSON конфигурация за RunMyStore.ai за бизнес: "${desc}".
Върни САМО валиден JSON без обяснения в следния формат:
{"label":"Наименование","categories":[{"name":"Кат1","variant_type":"none"}],"units":["бр"],"variants":[{"name":"Вариант","type":"custom","values":["ст1","ст2"],"active":true}],"is_perishable":false,"wholesale_enabled":false,"tax_group":20}
variant_type: none, size_color, size, color. Отговори САМО с JSON.`})
    });
    const d = await r.json();
    const text = d.response || d.message || '';
    const match = text.match(/\{[\s\S]*\}/);
    if (match) {
      customPreset = JSON.parse(match[0]);
      customPreset.variants = customPreset.variants || [];
      document.getElementById('aiPreviewText').innerHTML =
        `<strong>${customPreset.label}</strong><br>` +
        `📦 Категории: ${(customPreset.categories||[]).slice(0,5).map(c=>c.name).join(', ')}<br>` +
        `📏 Единици: ${(customPreset.units||[]).join(', ')}<br>` +
        `🏷️ Характеристики: ${(customPreset.variants||[]).filter(v=>v.active).map(v=>v.name).join(', ')||'—'}`;
      document.getElementById('aiLoading').style.display = 'none';
      document.getElementById('aiPreview').style.display = 'block';
    } else throw new Error('No JSON');
  } catch {
    document.getElementById('aiLoading').style.display = 'none';
    document.getElementById('aiBtn').style.display = 'block';
    alert('AI не успя да анализира. Опитай отново или избери от списъка.');
  }
}

function confirmAI() {
  const hasNonOther = selectedTypes.some(t => t !== 'other');
  document.getElementById('step1Btn').style.display = 'block';
}

function resetAI() {
  customPreset = null;
  document.getElementById('aiPreview').style.display = 'none';
  document.getElementById('aiBtn').style.display = 'block';
  document.getElementById('customDesc').value = '';
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
