<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: chat.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_name = trim($_POST['store_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (!$store_name || !$email || !$password) {
        $error = 'Всички полета са задължителни.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Невалиден имейл адрес.';
    } elseif (strlen($password) < 8) {
        $error = 'Паролата трябва да е поне 8 символа.';
    } else {
        $existing = DB::run('SELECT id FROM tenants WHERE email = ? LIMIT 1', [$email])->fetch();
        if ($existing) {
            $error = 'Този имейл вече е регистриран.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            DB::run(
                'INSERT INTO tenants (name, email, password, plan, trial_ends_at, country, language, currency, timezone, supato_mode, is_active)
                 VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?, ?, ?, ?, 1)',
                [$store_name, $email, $hashed, 'start', 'BG', 'bg', 'EUR', 'Europe/Sofia', 1]
            );
            $tenant_id = DB::lastInsertId();
            DB::run('INSERT INTO stores (tenant_id, name, is_active) VALUES (?, ?, 1)', [$tenant_id, $store_name]);
            $store_id = DB::lastInsertId();
            DB::run(
                'INSERT INTO users (tenant_id, store_id, name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)',
                [$tenant_id, $store_id, $store_name, $email, $hashed, 'owner']
            );
            $user_id = DB::lastInsertId();
            $_SESSION['user_id']     = $user_id;
            $_SESSION['tenant_id']   = $tenant_id;
            $_SESSION['store_id']    = $store_id;
            $_SESSION['role']        = 'owner';
            $_SESSION['supato_mode'] = 1;
            $_SESSION['currency']    = 'EUR';
            $_SESSION['language']    = 'bg';
            header('Location: onboarding.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Регистрация — RunMyStore.ai</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{height:100%;overflow:hidden}
body{background:#EDE8DC;font-family:'Montserrat',sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100dvh;padding:16px}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(circle at 15% 50%,rgba(218,165,32,.06) 0%,transparent 45%),radial-gradient(circle at 85% 20%,rgba(197,160,89,.07) 0%,transparent 45%),radial-gradient(circle at 50% 85%,rgba(230,194,122,.08) 0%,transparent 40%);pointer-events:none;z-index:0}

/* Header */
.hdr{position:fixed;top:0;left:0;right:0;z-index:50;background:rgba(237,232,220,.9);backdrop-filter:blur(20px);border-bottom:1px solid rgba(210,193,164,.7);padding:14px 20px;display:flex;align-items:center;justify-content:space-between}
.brand{font-size:17px;font-weight:900;background:linear-gradient(to right,#A67C00,#E6C27A,#A67C00);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite;text-decoration:none}
.hdr-link{font-size:13px;font-weight:700;color:#A67C00;text-decoration:none;padding:8px 16px;border:1px solid #D4AF37;border-radius:20px;background:#fff;transition:all .2s}
.hdr-link:active{background:#FAF7F0}

/* Card */
.card{position:relative;z-index:1;width:100%;max-width:380px;background:#ffffff;border:1px solid #EAE0D0;border-radius:24px;padding:32px 24px;box-shadow:0 8px 40px rgba(166,124,0,.08);margin-top:60px}

/* Title */
.card-title{font-size:22px;font-weight:900;color:#292524;margin-bottom:4px;text-align:center}
.card-sub{font-size:13px;color:#78716C;text-align:center;margin-bottom:24px}
.card-sub span{color:#C5A059;font-weight:700}

/* Error */
.error-box{background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:12px 16px;font-size:13px;color:#DC2626;margin-bottom:16px;font-weight:600}

/* Form */
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;font-weight:700;color:#78716C;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.field input{width:100%;background:#FAF7F0;border:1.5px solid #E6D5B8;border-radius:14px;color:#292524;font-size:15px;padding:13px 16px;font-family:'Montserrat',sans-serif;outline:none;transition:all .2s}
.field input:focus{border-color:#C5A059;background:#fff;box-shadow:0 0 0 3px rgba(197,160,89,.12)}
.field input::placeholder{color:#A8A29E;font-size:14px}

/* Password wrapper */
.pass-wrap{position:relative}
.pass-wrap input{padding-right:44px}
.eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#A8A29E;padding:4px;display:flex;align-items:center}
.eye-btn:hover{color:#A67C00}

/* Submit */
.btn-submit{width:100%;padding:15px;background:linear-gradient(to bottom,#D4AF37,#C5A059);border:none;border-radius:16px;color:#fff;font-size:15px;font-weight:800;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 6px 20px rgba(212,175,55,.35);transition:all .2s;margin-top:4px}
.btn-submit:active{transform:scale(.98);box-shadow:0 3px 10px rgba(212,175,55,.25)}

/* Footer */
.card-footer{text-align:center;margin-top:20px;font-size:13px;color:#78716C}
.card-footer a{color:#C5A059;font-weight:700;text-decoration:none}

/* Trial badge */
.trial-badge{display:flex;align-items:center;justify-content:center;gap:6px;background:linear-gradient(to right,rgba(212,175,55,.1),rgba(197,160,89,.15),rgba(212,175,55,.1));border:1px solid rgba(212,175,55,.3);border-radius:12px;padding:10px 16px;margin-bottom:20px;font-size:12px;font-weight:700;color:#A67C00}
.trial-badge svg{flex-shrink:0}

@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
</style>
</head>
<body>

<header class="hdr">
  <a class="brand" href="login.php">RunMyStore.ai</a>
  <a class="hdr-link" href="login.php">Вход</a>
</header>

<div class="card">
  <div class="card-title">Създай акаунт</div>
  <div class="card-sub"><span>30 дни безплатно.</span> Без карта.</div>

  <div class="trial-badge">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#A67C00" stroke-width="2.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
    </svg>
    Лоялната програма е безплатна завинаги
  </div>

  <?php if ($error): ?>
  <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="register.php">
    <div class="field">
      <label for="store_name">Име на магазина</label>
      <input id="store_name" name="store_name" type="text"
             placeholder="напр. Модна Къща Иванови"
             value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label for="email">Имейл</label>
      <input id="email" name="email" type="email"
             placeholder="your@email.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label for="password">Парола</label>
      <div class="pass-wrap">
        <input id="password" name="password" type="password"
               placeholder="Поне 8 символа" required>
        <button type="button" class="eye-btn" onclick="togglePass()">
          <svg id="eyeOpen" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
          </svg>
          <svg id="eyeClosed" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
            <line x1="1" y1="1" x2="23" y2="23"/>
          </svg>
        </button>
      </div>
    </div>
    <button type="submit" class="btn-submit">Регистрирай се безплатно →</button>
  </form>

  <div class="card-footer">
    Вече имаш акаунт? <a href="login.php">Влез тук</a>
  </div>
</div>

<script>
function togglePass(){
  var p=document.getElementById('password');
  var o=document.getElementById('eyeOpen');
  var c=document.getElementById('eyeClosed');
  if(p.type==='password'){p.type='text';o.style.display='none';c.style.display='block';}
  else{p.type='password';o.style.display='block';c.style.display='none';}
}
</script>
</body>
</html>
