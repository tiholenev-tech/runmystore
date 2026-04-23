<?php
/**
 * S82.CAPACITOR.2 — Capacitor runtime include
 *
 * Include this in the <head> of any page that needs window.Capacitor /
 * BLE plugin access. Safe in regular browsers too — falls back to
 * Capacitor.getPlatform()==='web' with BLE throwing UNAVAILABLE.
 *
 * Usage:
 *   <?php require __DIR__ . '/includes/capacitor-head.php'; ?>
 */
?>
<script src="/js/capacitor/native-bridge.js"></script>
<script src="/js/capacitor/core.js"></script>
<script src="/js/capacitor/ble.js"></script>
<script src="/js/capacitor-bundle.js"></script>
