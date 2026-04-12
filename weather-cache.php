<?php
/**
 * weather-cache.php — RunMyStore.ai S53
 * Daily cron: fetches Open-Meteo 16-day forecast
 * Auto-geocodes stores without coordinates
 * Cron: 0 6 * * * php /var/www/runmystore/weather-cache.php
 *
 * Open-Meteo: FREE, no API key, no registration
 * Forecast: https://api.open-meteo.com/v1/forecast
 * Geocoding: https://geocoding-api.open-meteo.com/v1/search
 */
require_once __DIR__ . '/config/database.php';

$start = microtime(true);
$log = function($msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n"; };
$log('=== Weather Cache Start ===');

// ═══════════════════════════════════════
// PHASE 1: Auto-geocode stores without coordinates
// ═══════════════════════════════════════
$noCoords = DB::run("
    SELECT s.id, s.name, s.city, s.tenant_id
    FROM stores s JOIN tenants t ON t.id = s.tenant_id
    WHERE (s.latitude IS NULL OR s.longitude IS NULL)
      AND s.city IS NOT NULL AND s.city != ''
")->fetchAll(PDO::FETCH_ASSOC);

if (!empty($noCoords)) {
    $log('Geocoding ' . count($noCoords) . ' store(s) without coordinates...');
    foreach ($noCoords as $s) {
        $coords = geocodeCity($s['city']);
        if ($coords) {
            DB::run("UPDATE stores SET latitude = ?, longitude = ? WHERE id = ?",
                [$coords['lat'], $coords['lng'], $s['id']]);
            $log("  Geocoded #{$s['id']} '{$s['name']}' ({$s['city']}) => {$coords['lat']},{$coords['lng']}");
        } else {
            $log("  WARN: Could not geocode '{$s['city']}' for store #{$s['id']}");
        }
        usleep(300000);
    }
}

// ═══════════════════════════════════════
// PHASE 2: Fetch forecast for all stores with coordinates
// ═══════════════════════════════════════
$stores = DB::run("
    SELECT s.id AS store_id, s.tenant_id, s.name, s.city, s.latitude, s.longitude
    FROM stores s JOIN tenants t ON t.id = s.tenant_id
    WHERE s.latitude IS NOT NULL AND s.longitude IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($stores)) {
    $log('No stores with coordinates found. Exiting.');
    exit(0);
}

// Deduplicate by location (2 stores same city = 1 API call)
$locGroups = [];
foreach ($stores as $s) {
    $key = round((float)$s['latitude'], 2) . ',' . round((float)$s['longitude'], 2);
    $locGroups[$key][] = $s;
}
$log('Found ' . count($stores) . ' store(s) in ' . count($locGroups) . ' unique location(s)');

$fetched = 0;
$errors = 0;

foreach ($locGroups as $locKey => $group) {
    $first = $group[0];
    $lat = round((float)$first['latitude'], 4);
    $lng = round((float)$first['longitude'], 4);

    // ── Fetch 16-day forecast ──
    $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude'      => $lat,
        'longitude'     => $lng,
        'daily'         => 'temperature_2m_max,temperature_2m_min,precipitation_probability_max,precipitation_sum,wind_speed_10m_max,weather_code,uv_index_max',
        'timezone'      => 'auto',
        'forecast_days' => 16
    ]);

    $log("  Fetching [{$locKey}] for " . count($group) . " store(s)...");

    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        $log("  ERROR: fetch failed for [{$locKey}]");
        $errors++;
        continue;
    }

    $data = json_decode($json, true);
    if (!isset($data['daily']['time'])) {
        $log("  ERROR: bad response for [{$locKey}]");
        $errors++;
        continue;
    }

    $days = [];
    $daily = $data['daily'];
    for ($i = 0; $i < count($daily['time']); $i++) {
        $days[] = [
            'date'        => $daily['time'][$i],
            'temp_max'    => $daily['temperature_2m_max'][$i] ?? null,
            'temp_min'    => $daily['temperature_2m_min'][$i] ?? null,
            'precip_prob' => $daily['precipitation_probability_max'][$i] ?? null,
            'precip_mm'   => $daily['precipitation_sum'][$i] ?? null,
            'wind_max'    => $daily['wind_speed_10m_max'][$i] ?? null,
            'weather_code'=> $daily['weather_code'][$i] ?? null,
            'uv_max'      => $daily['uv_index_max'][$i] ?? null,
            'source'      => 'forecast'
        ];
    }

    // Save for ALL stores at this location
    foreach ($group as $s) {
        saveForecast($s, $days);
    }
    $fetched += count($group);
    $log("  OK: " . count($days) . " days saved for " . count($group) . " store(s)");

    usleep(300000);
}

// ═══════════════════════════════════════
// PHASE 3: Cleanup old data (>45 days in the past)
// ═══════════════════════════════════════
$deleted = DB::run("DELETE FROM weather_forecast WHERE forecast_date < DATE_SUB(CURDATE(), INTERVAL 45 DAY)")->rowCount();
if ($deleted > 0) $log("Cleaned {$deleted} old forecast rows");

$elapsed = round(microtime(true) - $start, 2);
$log("=== Done: {$fetched} stores updated, {$errors} errors, {$elapsed}s ===");


// ═══════════════════════════════════════════════════════════════
// FUNCTIONS
// ═══════════════════════════════════════════════════════════════

/**
 * Geocode city name to lat/lng via Open-Meteo (free, no key)
 */
function geocodeCity(string $city): ?array {
    $url = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
        'name' => $city, 'count' => 1, 'language' => 'en'
    ]);
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;
    $data = json_decode($json, true);
    if (empty($data['results'][0])) return null;
    return [
        'lat'     => (float)$data['results'][0]['latitude'],
        'lng'     => (float)$data['results'][0]['longitude'],
        'country' => $data['results'][0]['country_code'] ?? null
    ];
}

/**
 * Save forecast days to DB (UPSERT)
 */
function saveForecast(array $store, array $days): void {
    $sql = "INSERT INTO weather_forecast
            (store_id, tenant_id, forecast_date, temp_max, temp_min,
             precipitation_prob, precipitation_mm, wind_speed_max,
             weather_code, uv_index_max, source, fetched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                temp_max=VALUES(temp_max), temp_min=VALUES(temp_min),
                precipitation_prob=VALUES(precipitation_prob),
                precipitation_mm=VALUES(precipitation_mm),
                wind_speed_max=VALUES(wind_speed_max),
                weather_code=VALUES(weather_code),
                uv_index_max=VALUES(uv_index_max),
                source=VALUES(source), fetched_at=NOW()";
    foreach ($days as $d) {
        try {
            DB::run($sql, [
                $store['store_id'], $store['tenant_id'], $d['date'],
                $d['temp_max'], $d['temp_min'], $d['precip_prob'],
                $d['precip_mm'], $d['wind_max'], $d['weather_code'],
                $d['uv_max'], $d['source']
            ]);
        } catch (Exception $e) {}
    }
}

/**
 * Get cached weather forecast for a store
 * Usage: $weather = getWeatherForecast($store_id, $tenant_id, 30);
 */
function getWeatherForecast(int $storeId, int $tenantId, int $days = 30): array {
    return DB::run("
        SELECT forecast_date, temp_max, temp_min, precipitation_prob,
               precipitation_mm, wind_speed_max, weather_code, uv_index_max, source
        FROM weather_forecast
        WHERE store_id = ? AND tenant_id = ?
          AND forecast_date >= CURDATE()
          AND forecast_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY forecast_date ASC
    ", [$storeId, $tenantId, $days])->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get weather summary text for AI prompt injection (build-prompt.php)
 * Usage: $summary = getWeatherSummary($store_id, $tenant_id, 14);
 */
function getWeatherSummary(int $storeId, int $tenantId, int $days = 14): string {
    $forecast = getWeatherForecast($storeId, $tenantId, $days);
    if (empty($forecast)) return '';

    $lines = ["WEATHER FORECAST (next {$days} days):"];
    $w1 = array_slice($forecast, 0, 7);
    $w2 = array_slice($forecast, 7, 7);

    if (!empty($w1)) {
        $mx1 = round(array_sum(array_column($w1, 'temp_max')) / count($w1));
        $mn1 = round(array_sum(array_column($w1, 'temp_min')) / count($w1));
        $r1 = count(array_filter($w1, fn($d) => ($d['precipitation_prob'] ?? 0) > 50));
        $lines[] = "Week 1: {$mn1}-{$mx1}C, {$r1} rainy days";
    }
    if (!empty($w2)) {
        $mx2 = round(array_sum(array_column($w2, 'temp_max')) / count($w2));
        $mn2 = round(array_sum(array_column($w2, 'temp_min')) / count($w2));
        $r2 = count(array_filter($w2, fn($d) => ($d['precipitation_prob'] ?? 0) > 50));
        $lines[] = "Week 2: {$mn2}-{$mx2}C, {$r2} rainy days";
        if (isset($mx1)) {
            $diff = $mx2 - $mx1;
            if ($diff >= 5) $lines[] = "TREND: Warming +{$diff}C. Summer items will move.";
            elseif ($diff <= -5) $lines[] = "TREND: Cooling {$diff}C. Winter items will pick up.";
        }
    }

    $lines[] = "Detail:";
    foreach (array_slice($forecast, 0, 5) as $d) {
        $dt = date('D d/m', strtotime($d['forecast_date']));
        $wc = weatherCodeToText((int)($d['weather_code'] ?? 0));
        $rn = ($d['precipitation_prob'] ?? 0) > 50 ? " RAIN {$d['precipitation_prob']}%" : '';
        $lines[] = "  {$dt}: {$d['temp_min']}-{$d['temp_max']}C {$wc}{$rn}";
    }
    return implode("\n", $lines);
}

/**
 * WMO Weather Code to short text
 */
function weatherCodeToText(int $code): string {
    return match(true) {
        $code === 0  => 'Clear',
        $code <= 3   => 'Cloudy',
        $code <= 48  => 'Fog',
        $code <= 57  => 'Drizzle',
        $code <= 67  => 'Rain',
        $code <= 77  => 'Snow',
        $code <= 82  => 'Showers',
        $code <= 86  => 'Snow showers',
        $code <= 99  => 'Thunderstorm',
        default      => '?'
    };
}
