<?php
require_once 'config/database.php';
session_start();

if (isset($_SESSION['tenant_id'])) {
    $t = DB::run("SELECT onboarding_done FROM tenants WHERE id=?", [$_SESSION['tenant_id']])->fetch();
    header('Location: ' . ($t && $t['onboarding_done'] ? 'chat.php' : 'onboarding.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $tenant = DB::run("SELECT * FROM tenants WHERE email = ? AND is_active = 1", [$email])->fetch();
        if ($tenant && password_verify($password, $tenant['password'])) {
            $user = DB::run("SELECT * FROM users WHERE tenant_id = ? AND email = ? AND is_active = 1", [$tenant['id'], $email])->fetch();
            $_SESSION['tenant_id']   = $tenant['id'];
            $_SESSION['user_id']     = $user['id'] ?? null;
            $_SESSION['role']        = $user['role'] ?? 'owner';
            $_SESSION['store_id']    = $user['store_id'] ?? null;
            $_SESSION['supato_mode'] = $tenant['supato_mode'] ?? 1;
            $_SESSION['currency']    = $tenant['currency'] ?? 'EUR';
            $_SESSION['language']    = $tenant['language'] ?? 'bg';
            DB::run("UPDATE tenants SET updated_at = NOW() WHERE id = ?", [$tenant['id']]);
            header('Location: ' . ($tenant['onboarding_done'] ? 'chat.php' : 'onboarding.php'));
            exit;
        }
    }
    $error = 'Грешен имейл или парола.';
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Вход — RunMyStore.ai</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{height:100%;overflow:hidden}
body{background:#EDE8DC;font-family:'Montserrat',sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100dvh;padding:16px}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(circle at 15% 50%,rgba(218,165,32,.06) 0%,transparent 45%),radial-gradient(circle at 85% 20%,rgba(197,160,89,.07) 0%,transparent 45%),radial-gradient(circle at 50% 85%,rgba(230,194,122,.08) 0%,transparent 40%);pointer-events:none;z-index:0}

.hdr{position:fixed;top:0;left:0;right:0;z-index:50;background:rgba(237,232,220,.9);backdrop-filter:blur(20px);border-bottom:1px solid rgba(210,193,164,.7);padding:14px 20px;display:flex;align-items:center;justify-content:space-between}
.brand{font-size:17px;font-weight:900;background:linear-gradient(to right,#A67C00,#E6C27A,#A67C00);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite;text-decoration:none}
.hdr-link{font-size:13px;font-weight:700;color:#A67C00;text-decoration:none;padding:8px 16px;border:1px solid #D4AF37;border-radius:20px;background:#fff;transition:all .2s}
.hdr-link:active{background:#FAF7F0}

.card{position:relative;z-index:1;width:100%;max-width:380px;background:#ffffff;border:1px solid #EAE0D0;border-radius:24px;padding:32px 24px;box-shadow:0 8px 40px rgba(166,124,0,.08);margin-top:60px}

.card-title{font-size:22px;font-weight:900;color:#292524;margin-bottom:4px;text-align:center}
.card-sub{font-size:13px;color:#78716C;text-align:center;margin-bottom:24px}

.error-box{background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:12px 16px;font-size:13px;color:#DC2626;margin-bottom:16px;font-weight:600}

.field{margin-bottom:16px}
.field-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.field label{display:block;font-size:12px;font-weight:700;color:#78716C;text-transform:uppercase;letter-spacing:.5px}
.field-forgot{font-size:12px;color:#A67C00;text-decoration:none;font-weight:600}
.field input{width:100%;background:#FAF7F0;border:1.5px solid #E6D5B8;border-radius:14px;color:#292524;font-size:15px;padding:13px 16px;font-family:'Montserrat',sans-serif;outline:none;transition:all .2s}
.field input:focus{border-color:#C5A059;background:#fff;box-shadow:0 0 0 3px rgba(197,160,89,.12)}
.field input::placeholder{color:#A8A29E;font-size:14px}

.pass-wrap{position:relative}
.pass-wrap input{padding-right:44px}
.eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#A8A29E;padding:4px;display:flex;align-items:center}
.eye-btn:hover{color:#A67C00}

.btn-submit{width:100%;padding:15px;background:linear-gradient(to bottom,#D4AF37,#C5A059);border:none;border-radius:16px;color:#fff;font-size:15px;font-weight:800;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 6px 20px rgba(212,175,55,.35);transition:all .2s;margin-top:4px}
.btn-submit:active{transform:scale(.98)}

.card-footer{text-align:center;margin-top:20px;font-size:13px;color:#78716C}
.card-footer a{color:#C5A059;font-weight:700;text-decoration:none}

@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
</style>
</head>
<body>

<header class="hdr">
  <a class="brand" href="login.php">RunMyStore.ai</a>
  <a class="hdr-link" href="register.php">Регистрация</a>
</header>

<div class="card">
  <div class="card-title">Добре дошъл 👋</div>
  <div class="card-sub">Влез в своя магазин</div>

  <?php if ($error): ?>
  <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <div class="field">
      <label for="email">Имейл</label>
      <input id="email" name="email" type="email"
             placeholder="твоят@имейл.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             required autocomplete="email">
    </div>
    <div class="field">
      <div class="field-top">
        <label for="password">Парола</label>
        <a class="field-forgot" href="reset-password.php">Забравена?</a>
      </div>
      <div class="pass-wrap">
        <input id="password" name="password" type="password"
               placeholder="••••••••" required autocomplete="current-password">
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
    <button type="submit" class="btn-submit">Влез →</button>
  </form>

  <div class="card-footer">
    Нямаш акаунт? <a href="register.php">Регистрирай се безплатно</a>
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
