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
<!doctype html>
<html lang="bg">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <meta charset="utf-8" />
    <title>Регистрация — RunMyStore.ai</title>
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no" />
    <link href="./css/vendors/aos.css" rel="stylesheet" />
    <link href="./style.css" rel="stylesheet" />
</head>
<body class="bg-gray-950 font-inter text-base text-gray-200 antialiased">

<div class="flex min-h-screen flex-col overflow-hidden">

    <!-- Header -->
    <header class="z-30 mt-2 w-full md:mt-5">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="relative flex h-14 items-center justify-between rounded-2xl bg-gray-900/90 px-3 before:pointer-events-none before:absolute before:inset-0 before:rounded-[inherit] before:border before:border-transparent before:[background:linear-gradient(to_right,var(--color-gray-800),var(--color-gray-700),var(--color-gray-800))_border-box] before:[mask-composite:exclude_!important] before:[mask:linear-gradient(white_0_0)_padding-box,_linear-gradient(white_0_0)] after:absolute after:inset-0 after:-z-10 after:backdrop-blur-xs">
                <a href="login.php" class="inline-flex items-center gap-2">
                    <span class="animate-[gradient_6s_linear_infinite] bg-[linear-gradient(to_right,var(--color-gray-200),var(--color-indigo-200),var(--color-gray-50),var(--color-indigo-300),var(--color-gray-200))] bg-[length:200%_auto] bg-clip-text font-nacelle text-lg font-semibold text-transparent">RunMyStore.ai</span>
                </a>
                <a class="btn-sm relative bg-linear-to-b from-gray-800 to-gray-800/60 bg-[length:100%_100%] bg-[bottom] py-[5px] text-gray-300 before:pointer-events-none before:absolute before:inset-0 before:rounded-[inherit] before:border before:border-transparent before:[background:linear-gradient(to_right,var(--color-gray-800),var(--color-gray-700),var(--color-gray-800))_border-box] before:[mask-composite:exclude_!important] before:[mask:linear-gradient(white_0_0)_padding-box,_linear-gradient(white_0_0)] hover:bg-[length:100%_150%]" href="login.php">Вход</a>
            </div>
        </div>
    </header>

    <!-- Main -->
    <main class="relative grow">

        <!-- Background illustration -->
        <div class="pointer-events-none absolute left-1/2 top-0 -z-10 -translate-x-1/4" aria-hidden="true">
            <img class="max-w-none" src="./images/page-illustration.svg" width="846" height="594" alt="" />
        </div>
        <div class="pointer-events-none absolute left-1/2 top-[400px] -z-10 -mt-20 -translate-x-full opacity-50" aria-hidden="true">
            <img class="max-w-none" src="./images/blurred-shape-gray.svg" width="760" height="668" alt="" />
        </div>
        <div class="pointer-events-none absolute left-1/2 top-[440px] -z-10 -translate-x-1/3" aria-hidden="true">
            <img class="max-w-none" src="./images/blurred-shape.svg" width="760" height="668" alt="" />
        </div>

        <section>
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="py-12 md:py-20">

                    <!-- Title -->
                    <div class="pb-12 text-center">
                        <h1 class="animate-[gradient_6s_linear_infinite] bg-[linear-gradient(to_right,var(--color-gray-200),var(--color-indigo-200),var(--color-gray-50),var(--color-indigo-300),var(--color-gray-200))] bg-[length:200%_auto] bg-clip-text font-nacelle text-3xl font-semibold text-transparent md:text-4xl">
                            Създай акаунт
                        </h1>
                        <p class="mt-3 text-indigo-200/65"><span class="text-indigo-400 font-semibold">30 дни безплатно.</span> Без карта.</p>
                    </div>

                    <!-- Trial badge -->
                    <div class="mx-auto mb-8 max-w-[400px]">
                        <div class="flex items-center justify-center gap-2 rounded-lg border border-indigo-500/20 bg-indigo-500/10 px-4 py-3 text-sm font-medium text-indigo-400">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            Лоялната програма е безплатна завинаги
                        </div>
                    </div>

                    <!-- Form -->
                    <form class="mx-auto max-w-[400px]" method="POST" action="register.php">

                        <?php if ($error): ?>
                        <div class="mb-5 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>

                        <div class="space-y-5">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-indigo-200/65" for="store_name">Име на магазина</label>
                                <input
                                    id="store_name"
                                    name="store_name"
                                    type="text"
                                    class="form-input w-full"
                                    placeholder="напр. Модна Къща Иванови"
                                    value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>"
                                    required
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-indigo-200/65" for="email">Имейл</label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    class="form-input w-full"
                                    placeholder="твоят@имейл.com"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    required
                                    autocomplete="email"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-indigo-200/65" for="password">Парола</label>
                                <div class="relative">
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        class="form-input w-full"
                                        placeholder="Поне 8 символа"
                                        required
                                    />
                                    <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-600 hover:text-indigo-400 transition" onclick="togglePass()">
                                        <svg id="eyeOpen" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg id="eyeClosed" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 space-y-5">
                            <button type="submit" class="btn w-full bg-linear-to-t from-indigo-600 to-indigo-500 bg-[length:100%_100%] bg-[bottom] text-white shadow-[inset_0px_1px_0px_0px_--theme(--color-white/.16)] hover:bg-[length:100%_150%]">
                                Регистрирай се безплатно →
                            </button>
                        </div>
                    </form>

                    <!-- Bottom link -->
                    <div class="mt-6 text-center text-sm text-indigo-200/65">
                        Вече имаш акаунт?
                        <a class="font-medium text-indigo-500" href="login.php">Влез тук</a>
                    </div>

                </div>
            </div>
        </section>
    </main>

</div>

<script src="./js/vendors/alpinejs.min.js" defer></script>
<script src="./js/vendors/aos.js"></script>
<script src="./js/main.js"></script>
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
