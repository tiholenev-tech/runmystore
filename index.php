<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['tenant_id'])) {
    $t = DB::run("SELECT onboarding_done FROM tenants WHERE id=?", [$_SESSION['tenant_id']])->fetch();
    header('Location: ' . (($t && $t['onboarding_done']) ? 'chat.php' : 'onboarding.php'));
    exit;
}
header('Location: login.php');
exit;
