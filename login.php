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
<!doctype html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Вход — RunMyStore.ai</title>
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
    <meta name="theme-color" content="#08090d">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/theme.css?v=<?= @filemtime(__DIR__.'/css/theme.css') ?: 1 ?>">
    <script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>
    <style>
    /* ═══ S81.BUGFIX.V3.EXT — login.php visual rewrite (chat.php design parity) ═══ */
    :root{
        --hue1:255; --hue2:222;
        --border:1px; --border-color:hsl(var(--hue2),12%,20%);
        --radius:22px; --radius-sm:14px;
        --ease:cubic-bezier(0.5,1,0.89,1);
        --bg-main:#08090d;
        --text-primary:#f1f5f9;
        --text-secondary:rgba(255,255,255,.6);
        --text-muted:rgba(255,255,255,.4);
    }
    *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
    html,body{
        background:var(--bg-main);
        color:var(--text-primary);
        font-family:'Montserrat',Inter,system-ui,sans-serif;
        min-height:100vh;overflow-x:hidden;
        -webkit-user-select:none;user-select:none;
    }
    body{
        background:
            radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / .22) 0%,transparent 60%),
            radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / .22) 0%,transparent 60%),
            linear-gradient(180deg,#0a0b14 0%,#050609 100%);
        background-attachment:fixed;
        min-height:100vh;
        position:relative;
        display:flex;align-items:center;justify-content:center;
        padding:24px 16px;
    }
    body::before{
        content:'';
        position:fixed;inset:0;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
        opacity:.03;
        pointer-events:none;z-index:1;mix-blend-mode:overlay;
    }

    /* ─── GLASS BASE (copied from chat.php) ─── */
    .glass{
        position:relative;
        border-radius:var(--radius);
        border:var(--border) solid var(--border-color);
        background:
            linear-gradient(235deg,hsl(var(--hue1) 50% 10% / .8),hsl(var(--hue1) 50% 10% / 0) 33%),
            linear-gradient(45deg,hsl(var(--hue2) 50% 10% / .8),hsl(var(--hue2) 50% 10% / 0) 33%),
            linear-gradient(hsl(220deg 25% 4.8% / .78));
        backdrop-filter:blur(12px);
        -webkit-backdrop-filter:blur(12px);
        box-shadow:hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
        isolation:isolate;
    }
    .glass .shine,.glass .glow{--hue:var(--hue1)}
    .glass .shine-bottom,.glass .glow-bottom{--hue:var(--hue2);--conic:135deg}
    .glass .shine,
    .glass .shine::before,
    .glass .shine::after{
        pointer-events:none;
        border-radius:0;
        border-top-right-radius:inherit;
        border-bottom-left-radius:inherit;
        border:1px solid transparent;
        width:75%;aspect-ratio:1;
        display:block;position:absolute;
        right:calc(var(--border) * -1);top:calc(var(--border) * -1);
        left:auto;z-index:1;
        --start:12%;
        background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,80%),var(--lit,60%)),transparent var(--end,50%)) border-box;
        mask:linear-gradient(transparent),linear-gradient(black);
        mask-repeat:no-repeat;
        mask-clip:padding-box,border-box;
        mask-composite:subtract;
    }
    .glass .shine::before,.glass .shine::after{content:"";width:auto;inset:-2px;mask:none}
    .glass .shine::after{z-index:2;--start:17%;--end:33%;background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,80%),var(--lit,85%)),transparent var(--end,50%))}
    .glass .shine-bottom{top:auto;bottom:calc(var(--border) * -1);left:calc(var(--border) * -1);right:auto}
    .glass .glow{
        pointer-events:none;
        border-top-right-radius:calc(var(--radius) * 2.5);
        border-bottom-left-radius:calc(var(--radius) * 2.5);
        border:calc(var(--radius) * 1.25) solid transparent;
        inset:calc(var(--radius) * -2);
        width:75%;aspect-ratio:1;
        display:block;position:absolute;
        left:auto;bottom:auto;
        mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='240'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='3' seed='5'/%3E%3CfeColorMatrix values='0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        mask-mode:luminance;mask-size:29%;
        opacity:1;
        filter:blur(12px) saturate(1.25) brightness(0.5);
        mix-blend-mode:plus-lighter;
        z-index:3;
    }
    .glass .glow.glow-bottom{inset:calc(var(--radius) * -2);top:auto;right:auto}
    .glass .glow::before,.glass .glow::after{
        content:"";position:absolute;inset:0;
        border:inherit;border-radius:inherit;
        background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,95%),var(--lit,60%)),transparent var(--end,50%)) border-box;
        mask:linear-gradient(transparent),linear-gradient(black);
        mask-repeat:no-repeat;mask-clip:padding-box,border-box;mask-composite:subtract;
        filter:saturate(2) brightness(1);
    }
    .glass .glow::after{
        --lit:70%;--sat:100%;--start:15%;--end:35%;
        border-width:calc(var(--radius) * 1.75);
        border-radius:calc(var(--radius) * 2.75);
        inset:calc(var(--radius) * -.25);
        z-index:4;opacity:.75;
    }

    /* ─── LAYOUT ─── */
    .wrap{
        position:relative;z-index:2;
        width:100%;max-width:420px;
    }
    .brand{
        text-align:center;
        margin-bottom:26px;
    }
    .brand-name{
        font-size:11px;font-weight:900;
        letter-spacing:.14em;
        color:hsl(var(--hue1) 50% 72%);
        text-shadow:0 0 10px hsl(var(--hue1) 60% 50% / .35);
        margin-bottom:10px;
    }
    .brand-title{
        font-size:26px;font-weight:700;line-height:1.2;
        background:linear-gradient(110deg,#e2e8f0 0%,#a5b4fc 35%,#e2e8f0 70%);
        -webkit-background-clip:text;background-clip:text;
        -webkit-text-fill-color:transparent;color:transparent;
        letter-spacing:-.01em;
        margin-bottom:6px;
    }
    .brand-sub{
        font-size:13px;color:var(--text-secondary);
        font-weight:500;
    }

    .card{padding:26px 22px 22px}

    /* ─── FORM ─── */
    .alert-error{
        margin-bottom:16px;
        padding:11px 14px;
        border-radius:12px;
        background:rgba(239,68,68,.10);
        border:1px solid rgba(239,68,68,.28);
        color:#fca5a5;
        font-size:12.5px;
        display:flex;align-items:center;gap:8px;
    }
    .alert-error svg{flex-shrink:0}

    .fg{margin-bottom:14px}
    .fl-row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px}
    .fl{
        display:block;
        font-size:10.5px;font-weight:700;
        color:var(--text-secondary);
        text-transform:uppercase;letter-spacing:.08em;
    }
    .fl-link{
        font-size:11px;color:hsl(var(--hue1) 60% 72%);
        text-decoration:none;font-weight:500;letter-spacing:0;text-transform:none;
    }
    .fl-link:hover{color:#c4b5fd}

    .fc{
        width:100%;padding:12px 14px;
        border-radius:12px;
        border:1px solid rgba(255,255,255,.08);
        background:rgba(20,22,36,.65);
        color:var(--text-primary);
        font-size:14px;outline:none;
        font-family:inherit;
        transition:border-color .2s,box-shadow .2s,background .2s;
        -webkit-user-select:text;user-select:text;
    }
    .fc:focus{
        border-color:hsl(var(--hue1) 60% 55% / .55);
        box-shadow:0 0 0 3px hsl(var(--hue1) 60% 50% / .12);
        background:rgba(20,22,36,.85);
    }
    .fc::placeholder{color:var(--text-muted)}

    .pwrap{position:relative}
    .pwrap .fc{padding-right:44px}
    .eye-btn{
        position:absolute;right:8px;top:50%;transform:translateY(-50%);
        width:30px;height:30px;
        background:transparent;border:none;cursor:pointer;
        color:var(--text-muted);
        display:flex;align-items:center;justify-content:center;
        border-radius:8px;transition:color .15s,background .15s;
    }
    .eye-btn:hover{color:#a5b4fc;background:rgba(255,255,255,.04)}

    .btn-primary{
        width:100%;padding:14px 16px;margin-top:6px;
        border-radius:14px;
        background:linear-gradient(180deg,hsl(var(--hue1) 72% 62%),hsl(var(--hue1) 72% 48%));
        border:1px solid hsl(var(--hue1) 72% 55% / .6);
        color:#fff;font-size:14px;font-weight:700;
        letter-spacing:.03em;
        cursor:pointer;font-family:inherit;
        box-shadow:
            0 0 20px hsl(var(--hue1) 72% 50% / .38),
            inset 0 1px 0 rgba(255,255,255,.12);
        transition:transform .12s,box-shadow .2s,filter .15s;
        display:flex;align-items:center;justify-content:center;gap:6px;
    }
    .btn-primary:hover{
        transform:translateY(-1px);
        box-shadow:
            0 0 26px hsl(var(--hue1) 72% 50% / .52),
            inset 0 1px 0 rgba(255,255,255,.16);
    }
    .btn-primary:active{transform:translateY(0);filter:brightness(.95)}

    .foot{
        margin-top:22px;
        text-align:center;
        font-size:13px;color:var(--text-secondary);
    }
    .foot a{color:hsl(var(--hue1) 60% 72%);text-decoration:none;font-weight:600}
    .foot a:hover{color:#c4b5fd}
    </style>
</head>
<body>

<div class="wrap">

    <!-- Brand -->
    <div class="brand">
        <div class="brand-name">RUNMYSTORE.AI</div>
        <div class="brand-title">Добре дошъл 👋</div>
        <div class="brand-sub">Влез в своя магазин</div>
    </div>

    <!-- Glass card -->
    <div class="glass card">
        <span class="shine shine-top"></span><span class="shine shine-bottom"></span>
        <span class="glow glow-top"></span><span class="glow glow-bottom"></span>

        <form method="POST" action="">

            <?php if ($error): ?>
            <div class="alert-error">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <div class="fg">
                <div class="fl-row">
                    <label class="fl" for="email">Имейл</label>
                </div>
                <input
                    id="email"
                    name="email"
                    type="email"
                    class="fc"
                    placeholder="твоят@имейл.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="email"
                />
            </div>

            <div class="fg">
                <div class="fl-row">
                    <label class="fl" for="password">Парола</label>
                    <a class="fl-link" href="reset-password.php">Забравена?</a>
                </div>
                <div class="pwrap">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="fc"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    />
                    <button type="button" class="eye-btn" onclick="togglePass()" aria-label="Покажи парола">
                        <svg id="eyeOpen" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="eyeClosed" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary">
                Влез
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>
    </div>

    <!-- Bottom link -->
    <div class="foot">
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
