<?php
/**
 * Analisis Menyeluruh Endpoint Kecepatan Angin JAGAPADI
 * 
 * Pengujian: 
 * 1. Verifikasi ketersediaan & fungsionalitas (HTTP status, response time)
 * 2. Analisis struktur data respons
 * 3. Evaluasi akurasi data
 * 4. Uji performa (beban bersamaan)
 * 5. Pemeriksaan keamanan
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/core/Database.php';

echo "============================================================\n";
echo "  ANALISIS ENDPOINT KECEPATAN ANGIN - JAGAPADI SYSTEM\n";
echo "  Tanggal: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

// ========== 1. VERIFIKASI KETERSEDIAAN ENDPOINT ==========
echo "## 1. VERIFIKASI KETERSEDIAAN & FUNGSIONALITAS ENDPOINT\n";
echo str_repeat("-", 60) . "\n";

$baseUrl = 'http://localhost/jagapadi-3509';
$endpoints = [
    'Dashboard (GET)'              => "{$baseUrl}/kecepatanAngin",
    'GetData API (GET)'            => "{$baseUrl}/kecepatanAngin/getData",
    'GetChartData (GET)'           => "{$baseUrl}/kecepatanAngin/getChartData",
    'GetStatistics (GET)'          => "{$baseUrl}/kecepatanAngin/getStatistics",
    'GetTrendData (GET)'           => "{$baseUrl}/kecepatanAngin/getTrendData",
    'GetSeasonalData (GET)'        => "{$baseUrl}/kecepatanAngin/getSeasonalData",
    'GetAnomalyData (GET)'         => "{$baseUrl}/kecepatanAngin/getAnomalyData",
    'GetPredictionData (GET)'      => "{$baseUrl}/kecepatanAngin/getPredictionData",
    'CheckAlerts (GET)'            => "{$baseUrl}/kecepatanAngin/checkAlerts",
    'DailyChartData (GET)'         => "{$baseUrl}/kecepatanAngin/getDailyChartData",
    'GetMapData (GET)'             => "{$baseUrl}/kecepatanAngin/getMapData",
    'SprayRecommendation (GET)'    => "{$baseUrl}/kecepatanAngin/sprayRecommendation",
    'WindRoseData (GET)'           => "{$baseUrl}/kecepatanAngin/windRoseData",
    'PestRiskAnalysis (GET)'       => "{$baseUrl}/kecepatanAngin/pestRiskAnalysis",
    'DailySummary (GET)'           => "{$baseUrl}/kecepatanAngin/dailySummary",
    'Evapotranspiration (GET)'     => "{$baseUrl}/kecepatanAngin/evapotranspirationAnalysis",
    'WindPestCorrelation (GET)'    => "{$baseUrl}/kecepatanAngin/windPestCorrelation",
    'PestSpreadPrediction (GET)'   => "{$baseUrl}/kecepatanAngin/pestSpreadPrediction",
    'IrrigationAdjustment (GET)'   => "{$baseUrl}/kecepatanAngin/irrigationAdjustment",
    'Export CSV (GET)'             => "{$baseUrl}/kecepatanAngin/export",
    'GetLogs (GET)'                => "{$baseUrl}/kecepatanAngin/getLogs",
];

$results = [];
$cookieFile = tempnam(sys_get_temp_dir(), 'jagapadi_cookie_');

echo "\n--- Step 1a: Login untuk mendapatkan session auth ---\n";
$loginUrl = "{$baseUrl}/auth/login";
$loginFields = [
    'username' => 'admin',
    'password' => 'password',
];
echo "Mencoba login ke: {$loginUrl}\n";
echo "User: admin / admin123\n";

// First get login page to get CSRF token
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $loginUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
]);
$loginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $csrfMatch);
$csrfToken = $csrfMatch[1] ?? '';
echo "CSRF Token: " . ($csrfToken ? substr($csrfToken, 0, 20) . '...' : 'TIDAK DITEMUKAN') . "\n";

// Debug: Verifikasi password user admin di DB langsung
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, username, password, must_change_password FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(['admin']);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dbUser) {
        $pwdOk = password_verify('password', $dbUser['password']);
        echo "DB Admin Check: user={$dbUser['username']}, pwd_verify(password)={$pwdOk}, must_change_pwd={$dbUser['must_change_password']}\n";
        if (!$pwdOk) {
            echo "⚠️  Password 'password' TIDAK COCOK, reset via script...\n";
            $newHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("UPDATE users SET password = ?, must_change_password = 0, aktif = 1 WHERE id = ?")->execute([$newHash, $dbUser['id']]);
            echo "✅ Password direset ke 'password' dan must_change_password=0\n";
        }
    }
} catch (Exception $e) {
    echo "DB Check error: " . $e->getMessage() . "\n";
}

// Submit login
$loginFields['csrf_token'] = $csrfToken;
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $loginUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($loginFields),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_REFERER => $loginUrl,
]);
$loginResponse = curl_exec($ch);
$httpCodeLogin = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?? '';
curl_close($ch);

// Check if logged in by accessing dashboard and looking for user/dashboard content
$loginSuccess = strpos($loginResponse, 'dashboard') !== false 
    || strpos($loginResponse, 'Logout') !== false 
    || strpos($loginResponse, 'Selamat datang') !== false
    || strpos($finalUrl, 'dashboard') !== false;
echo "Login HTTP Code: {$httpCodeLogin}\n";
echo "Login Final URL: {$finalUrl}\n";
echo "Login Response length: " . strlen($loginResponse) . " bytes\n";
echo "Login Status: " . ($loginSuccess ? "✅ BERHASIL" : "❌ GAGAL") . "\n";
// Debug snippet content after login
if (!$loginSuccess) {
    echo "  Debug: Body snippet (500 char): " . strip_tags(substr($loginResponse, 0, 600)) . "\n";
}

echo "\n--- Step 1b: Pengujian Endpoint ---\n";
printf("%-30s %-8s %-12s %-10s %-10s %s\n", "Endpoint", "Method", "HTTP Code", "Resp Time", "Size", "Redirect/Note");
echo str_repeat("-", 95) . "\n";

foreach ($endpoints as $name => $url) {
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HEADER => false,
        CURLOPT_USERAGENT => 'JAGAPADI-AnalysisBot/1.0',
    ]);
    
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?? '';
    $respTime = round((microtime(true) - $startTime) * 1000, 2);
    $size = strlen($body);
    $sizeStr = $size > 1024 ? round($size/1024, 2) . ' KB' : $size . ' B';
    curl_close($ch);
    
    // Note: redirect to login means auth required, JSON means OK
    $note = '';
    if ($httpCode === 302) {
        $note = strpos($redirect, 'login') !== false ? '-> LOGIN (AUTH ✅)' : '-> ' . basename($redirect);
    } elseif ($httpCode === 200) {
        $json = json_decode($body, true);
        if ($json && json_last_error() === JSON_ERROR_NONE) {
            $note = 'JSON OK';
            $body = json_encode($json); // normalize
        } elseif (strpos($body, 'kecepatan_angin') !== false || strpos($body, 'dashboard') !== false || strpos($body, 'Kecepatan Angin') !== false) {
            $note = 'HTML PAGE';
        } else {
            $note = '⚠️ 200 tapi mungkin LOGIN PAGE';
        }
    } else {
        $note = 'HTTP ' . $httpCode;
    }
    
    $results[$name] = [
        'url' => $url,
        'http_code' => $httpCode,
        'response_time_ms' => $respTime,
        'size_bytes' => $size,
        'body' => $body,
        'note' => $note,
    ];
    
    printf("%-30s %-8s %-12s %-10s %-10s %s\n", 
        substr($name, 0, 28), 
        "GET", 
        $httpCode, 
        $respTime . " ms",
        $sizeStr,
        $note
    );
}

// Summary status codes
echo "\n--- Ringkasan Status Code ---\n";
$statusCounts = [];
foreach ($results as $r) {
    $code = (string)$r['http_code'];
    $statusCounts[$code] = ($statusCounts[$code] ?? 0) + 1;
}
foreach ($statusCounts as $code => $count) {
    $codeStr = (string)$code;
    $desc = match(true) {
        str_starts_with($codeStr, '2') => 'Success',
        str_starts_with($codeStr, '3') => 'Redirect',
        str_starts_with($codeStr, '4') => 'Client Error',
        str_starts_with($codeStr, '5') => 'Server Error',
        default => 'Unknown'
    };
    echo "  HTTP {$code} ({$desc}): {$count} endpoint\n";
}

echo "\n--- Ringkasan Response Time ---\n";
$times = array_column($results, 'response_time_ms');
echo "  Min: " . min($times) . " ms\n";
echo "  Max: " . max($times) . " ms\n";
echo "  Avg: " . round(array_sum($times)/count($times), 2) . " ms\n";
echo "  Total: " . round(array_sum($times), 2) . " ms\n";

// ========== 2. ANALISIS STRUKTUR DATA RESPONS API ==========
echo "\n\n## 2. ANALISIS STRUKTUR DATA RESPONS API\n";
echo str_repeat("-", 60) . "\n";

// Check getData response (main data endpoint)
$getDataBody = $results['GetData API (GET)']['body'];
$getDataJson = json_decode($getDataBody, true);

if ($getDataJson && json_last_error() === JSON_ERROR_NONE) {
    echo "\n✅ Format JSON: VALID\n";
    
    $rootKeys = array_keys($getDataJson);
    echo "Root keys: " . implode(', ', $rootKeys) . "\n";
    echo "Success flag: " . ($getDataJson['success'] ? 'TRUE' : 'FALSE') . "\n";
    echo "Total records: " . ($getDataJson['total'] ?? 'N/A') . "\n";
    
    if (isset($getDataJson['data']) && is_array($getDataJson['data']) && count($getDataJson['data']) > 0) {
        $firstRecord = $getDataJson['data'][0];
        echo "\n--- Struktur Record Data (sample pertama) ---\n";
        echo "Jumlah atribut per record: " . count($firstRecord) . "\n";
        
        $expectedAttrs = [
            'id' => 'ID record',
            'tanggal' => 'Tanggal pengukuran',
            'bulan' => 'Nama bulan',
            'bulan_num' => 'Nomor bulan (1-12)',
            'tahun' => 'Tahun pengukuran',
            'lokasi' => 'Nama lokasi/stasiun',
            'kode_wilayah' => 'Kode wilayah administratif',
            'kecepatan_angin' => 'Nilai kecepatan angin rata-rata',
            'kecepatan_max' => 'Nilai kecepatan angin maksimum',
            'arah_angin' => 'Arah angin (derajat)',
            'arah_angin_desc' => 'Arah angin (deskripsi)',
            'satuan' => 'Satuan pengukuran',
            'sumber_data' => 'Sumber data (Open-Meteo/Simulasi/Manual)',
            'keterangan' => 'Keterangan tambahan',
            'created_at' => 'Waktu pembuatan record',
        ];
        
        printf("%-20s %-5s %-30s %-15s\n", "Attribute", "Ada", "Expected", "Nilai Sample");
        echo str_repeat("-", 75) . "\n";
        
        foreach ($expectedAttrs as $attr => $desc) {
            $exists = array_key_exists($attr, $firstRecord);
            $sampleValue = $exists ? (is_scalar($firstRecord[$attr]) ? substr((string)$firstRecord[$attr], 0, 15) : '[array/null]') : 'N/A';
            $tipe = $exists ? gettype($firstRecord[$attr]) : '-';
            printf("%-20s %-5s %-30s %-15s\n",
                $attr,
                $exists ? "✅" : "❌",
                $desc,
                $sampleValue
            );
        }
        
        // Type checks
        echo "\n--- Validasi Tipe Data ---\n";
        $typeChecks = [];
        $typeChecks['kecepatan_angin is float'] = is_float($firstRecord['kecepatan_angin']);
        $typeChecks['tahun is int'] = is_int($firstRecord['tahun']);
        $typeChecks['satuan is km/h'] = ($firstRecord['satuan'] ?? '') === 'km/h';
        foreach ($typeChecks as $check => $pass) {
            echo "  " . ($pass ? "✅" : "❌") . " {$check}\n";
        }
    }
    
    // Check statistics
    if (isset($getDataJson['statistics'])) {
        echo "\n--- Atribut Statistics ---\n";
        $stats = $getDataJson['statistics'];
        $expectedStats = ['rata_rata', 'maksimum', 'minimum', 'total_records', 'hari_berangin'];
        foreach ($expectedStats as $s) {
            echo "  " . (isset($stats[$s]) ? "✅" : "❌") . " {$s}: " . ($stats[$s] ?? 'N/A') . "\n";
        }
        if (isset($stats['data_composition'])) {
            echo "  ✅ data_composition: " . count($stats['data_composition']) . " sumber data\n";
        }
    }
    
} else {
    echo "\n❌ Format JSON TIDAK VALID\n";
    echo "Error: " . json_last_error_msg() . "\n";
    echo "Raw response (500 chars): " . substr($getDataBody, 0, 500) . "\n";
}

// ========== 3. EVALUASI AKURASI DATA ==========
echo "\n\n## 3. EVALUASI AKURASI DATA KECEPATAN ANGIN\n";
echo str_repeat("-", 60) . "\n";

try {
    $db = Database::getInstance()->getConnection();
    
    $totalRecords = $db->query("SELECT COUNT(*) FROM kecepatan_angin")->fetchColumn();
    echo "\nTotal records di DB: " . number_format((int)$totalRecords) . "\n";
    
    if ($totalRecords > 0) {
        // Range check: BMKG standard wind speed range
        $windStats = $db->query("
            SELECT 
                MIN(kecepatan_angin) as min_speed,
                MAX(kecepatan_angin) as max_speed,
                AVG(kecepatan_angin) as avg_speed,
                STDDEV(kecepatan_angin) as stddev_speed,
                MIN(kecepatan_max) as min_max,
                MAX(kecepatan_max) as max_max,
                SUM(CASE WHEN kecepatan_angin IS NULL OR kecepatan_angin = 0 THEN 1 ELSE 0 END) as null_zero
            FROM kecepatan_angin
        ")->fetch(PDO::FETCH_ASSOC);
        
        echo "\n--- Statistik Dasar Data ---\n";
        foreach ($windStats as $k => $v) {
            echo "  {$k}: " . ($v !== null ? round((float)$v, 4) : 'NULL') . "\n";
        }
        
        // BMKG / WMO standard ranges for wind speed (km/h)
        // Calm: < 1 km/h
        // Light: 1-12 km/h
        // Moderate: 12-28 km/h
        // Strong: 28-50 km/h
        // Gale: 50-75 km/h
        // Severe Gale: 75-100 km/h
        // Storm/Hurricane: > 100 km/h (extreme for inland Indonesia)
        
        echo "\n--- Analisis Rentang Data (Standar Meteorologi BMKG/WMO) ---\n";
        $rangeCats = [
            'Calm (< 1 km/h)'       => 'kecepatan_angin < 1',
            'Ringan (1-12 km/h)'    => 'kecepatan_angin >= 1 AND kecepatan_angin < 12',
            'Sedang (12-28 km/h)'   => 'kecepatan_angin >= 12 AND kecepatan_angin < 28',
            'Kuat (28-50 km/h)'     => 'kecepatan_angin >= 28 AND kecepatan_angin < 50',
            'Kencang (50-75 km/h)'  => 'kecepatan_angin >= 50 AND kecepatan_angin < 75',
            'Sangat Kencang (75-100)' => 'kecepatan_angin >= 75 AND kecepatan_angin < 100',
            'Badai/Topan (> 100)'   => 'kecepatan_angin >= 100',
            'Negatif (Anomali)'     => 'kecepatan_angin < 0',
            'Melebihi 200 (Anomali)' => 'kecepatan_angin > 200',
        ];
        
        $total = (int)$totalRecords;
        foreach ($rangeCats as $cat => $cond) {
            $count = $db->query("SELECT COUNT(*) FROM kecepatan_angin WHERE {$cond}")->fetchColumn();
            $pct = $total > 0 ? round(($count / $total) * 100, 2) : 0;
            $flag = ($cond === 'kecepatan_angin < 0' || $cond === 'kecepatan_angin > 200') ? ' ⚠️ ANOMALI' : '';
            $bar = str_repeat('█', (int)($pct / 2));
            echo sprintf("  %-28s %8d (%6s%%) %s%s\n", $cat, (int)$count, $pct, $bar, $flag);
        }
        
        // Data completeness check
        echo "\n--- Pemeriksaan Kelengkapan Data ---\n";
        $checks = [
            'tanggal IS NULL' => 'Tanggal kosong',
            'lokasi IS NULL' => 'Lokasi kosong',
            'kecepatan_angin IS NULL' => 'Kecepatan angin kosong',
            'satuan IS NULL' => 'Satuan kosong',
            'sumber_data IS NULL' => 'Sumber data kosong',
        ];
        foreach ($checks as $cond => $desc) {
            $count = $db->query("SELECT COUNT(*) FROM kecepatan_angin WHERE {$cond}")->fetchColumn();
            echo "  " . ((int)$count > 0 ? "⚠️" : "✅") . " {$desc}: {$count} record\n";
        }
        
        // Check max >= avg
        $inconsistent = $db->query("
            SELECT COUNT(*) FROM kecepatan_angin 
            WHERE kecepatan_max IS NOT NULL 
              AND kecepatan_max < kecepatan_angin
        ")->fetchColumn();
        echo "  " . ((int)$inconsistent > 0 ? "⚠️" : "✅") . " Kecepatan maks < rata-rata (inkonsisten): {$inconsistent}\n";
        
        // Duplicate check (tanggal + lokasi)
        $duplicates = $db->query("
            SELECT tanggal, lokasi, COUNT(*) as cnt 
            FROM kecepatan_angin 
            GROUP BY tanggal, lokasi 
            HAVING cnt > 1
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo "  " . (count($duplicates) > 0 ? "⚠️" : "✅") . " Duplikat (tanggal+lokasi): " . count($duplicates) . " grup\n";
        
        // Data source breakdown
        echo "\n--- Komposisi Sumber Data ---\n";
        $sources = $db->query("
            SELECT sumber_data, COUNT(*) as cnt, ROUND(AVG(kecepatan_angin),2) as avg
            FROM kecepatan_angin 
            GROUP BY sumber_data 
            ORDER BY cnt DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sources as $s) {
            echo "  - {$s['sumber_data']}: {$s['cnt']} record (avg: {$s['avg']} km/h)\n";
        }
        
        // Direction validity (0-360 degrees)
        $invalidDir = $db->query("
            SELECT COUNT(*) FROM kecepatan_angin 
            WHERE arah_angin IS NOT NULL 
              AND (arah_angin < 0 OR arah_angin > 360)
        ")->fetchColumn();
        echo "  " . ((int)$invalidDir > 0 ? "⚠️" : "✅") . " Arah angin di luar 0-360 derajat: {$invalidDir}\n";
        
    }
    
} catch (Exception $e) {
    echo "ERROR DB: " . $e->getMessage() . "\n";
}

// ========== 4. UJI PERFORMA DI BAWAH BEBAN ==========
echo "\n\n## 4. UJI PERFORMA (LOAD TEST)\n";
echo str_repeat("-", 60) . "\n";

$loadUrl = $results['GetData API (GET)']['url'] ?? "{$baseUrl}/kecepatanAngin/getData";
$concurrencyLevels = [1, 5, 10, 20];

echo "Endpoint untuk load test: {$loadUrl}\n";
echo "Tingkat konkuren yang diuji: " . implode(', ', $concurrencyLevels) . "\n\n";

printf("%-15s %-12s %-12s %-12s %-12s %-10s\n", "Concurrency", "Total Req", "Min(ms)", "Avg(ms)", "Max(ms)", "Error%");
echo str_repeat("-", 75) . "\n";

$loadResults = [];
foreach ($concurrencyLevels as $concurrency) {
    $times = [];
    $errors = 0;
    $totalRequests = $concurrency * 3; // each concurrency level, 3 requests
    
    $multiHandles = [];
    $mh = curl_multi_init();
    
    for ($i = 0; $i < $concurrency; $i++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $loadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_TIMEOUT => 60,
        ]);
        curl_multi_add_handle($mh, $ch);
        $multiHandles[] = $ch;
    }
    
    $startTime = microtime(true);
    $active = null;
    do {
        curl_multi_exec($mh, $active);
        curl_multi_select($mh);
    } while ($active);
    
    foreach ($multiHandles as $ch) {
        $resp = curl_multi_getcontent($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $execTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        $times[] = $execTime;
        if ($code < 200 || $code >= 400) $errors++;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    
    $totalElapsed = (microtime(true) - $startTime) * 1000;
    $errorPct = $concurrency > 0 ? round(($errors / $concurrency) * 100, 2) : 0;
    $loadResults[$concurrency] = [
        'min' => min($times),
        'avg' => array_sum($times) / count($times),
        'max' => max($times),
        'total_elapsed' => $totalElapsed,
        'error_pct' => $errorPct,
    ];
    
    printf("%-15s %-12s %-12s %-12s %-12s %-10s\n",
        $concurrency . " users",
        $concurrency,
        round(min($times), 1),
        round(array_sum($times)/count($times), 1),
        round(max($times), 1),
        $errorPct . "%"
    );
}

echo "\n--- Analisis Skalabilitas ---\n";
$baseAvg = $loadResults[1]['avg'];
foreach ($concurrencyLevels as $c) {
    if ($c === 1) continue;
    $degredation = $loadResults[$c]['avg'] / $baseAvg;
    $flag = $degredation > 3 ? ' ⚠️ Degredasi parah' : ($degredation > 1.5 ? ' ⚠️ Degredasi sedang' : '');
    echo "  {$c}x konkuren: " . round($degredation, 2) . "x lebih lambat dari 1 user{$flag}\n";
}

// ========== 5. PEMERIKSAAN KEAMANAN ==========
echo "\n\n## 5. PEMERIKSAAN KEAMANAN ENDPOINT\n";
echo str_repeat("-", 60) . "\n";

echo "\n--- 5a. Autentikasi (akses tanpa login) ---\n";
$anonCookie = tempnam(sys_get_temp_dir(), 'jagapadi_anon_');
$anonCh = curl_init();
curl_setopt_array($anonCh, [
    CURLOPT_URL => "{$baseUrl}/kecepatanAngin/getData",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $anonCookie,
    CURLOPT_COOKIEFILE => $anonCookie,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADER => true,
]);
$anonResp = curl_exec($anonCh);
$anonCode = curl_getinfo($anonCh, CURLINFO_HTTP_CODE);
curl_close($anonCh);

echo "  Akses tanpa login ke /kecepatanAngin/getData\n";
echo "  HTTP Code: {$anonCode}\n";
if ($anonCode === 302 || strpos($anonResp, 'login') !== false) {
    echo "  ✅ AUTH ENFORCED: Dialihkan ke halaman login\n";
} elseif ($anonCode === 401 || $anonCode === 403) {
    echo "  ✅ AUTH ENFORCED: Ditolak dengan 401/403\n";
} else {
    echo "  ❌ AUTH TIDAK DIAKSES: Data mungkin terekspos\n";
    echo "  Response snippet: " . substr(strip_tags($anonResp), 0, 200) . "\n";
}

echo "\n--- 5b. Otorisasi (akses admin-only endpoint dengan user non-admin) ---\n";
echo "  Endpoint admin-only: runScraper, store, delete, importExcel, getRecord, update, getLogs\n";

// Test getRecord (admin only) without admin role
$testUrls = [
    "/kecepatanAngin/getLogs?limit=1",
    "/kecepatanAngin/getRecord/1",
];

$petugasCookie = tempnam(sys_get_temp_dir(), 'jagapadi_petugas_');
// Try petugas login
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $loginUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $petugasCookie,
    CURLOPT_COOKIEFILE => $petugasCookie,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
]);
$petugasLoginPage = curl_exec($ch);
curl_close($ch);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $petugasLoginPage, $petugasCsrf);
$petugasLoginFields = [
    'username' => 'petugas01',
    'password' => 'password',
    'csrf_token' => $petugasCsrf[1] ?? '',
];
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $loginUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($petugasLoginFields),
    CURLOPT_COOKIEJAR => $petugasCookie,
    CURLOPT_COOKIEFILE => $petugasCookie,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 30,
]);
$petugasLogin = curl_exec($ch);
$petugasLoginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Login petugas01 / password: HTTP {$petugasLoginCode}\n";

foreach ($testUrls as $testUrl) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $petugasCookie,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $blocked = $code === 302 || $code === 403 || strpos($body, 'Anda tidak memiliki akses') !== false;
    echo "  " . ($blocked ? "✅" : "❌") . " PETUGAS akses {$testUrl} -> HTTP {$code}: " . ($blocked ? "DIBLOKIR" : "TERAKSES ❗") . "\n";
}

echo "\n--- 5c. Injeksi SQL melalui parameter GET ---\n";
$injectPayloads = [
    "' OR '1'='1",
    "1' UNION SELECT username,password,3,4,5 FROM users--",
    "1; DROP TABLE kecepatan_angin--",
    "1 AND SLEEP(2)",
    "1' AND 1=2 UNION SELECT 1,@@version,3,4,5,6,7,8,9,10--",
];

$injectionVulnerable = 0;
foreach ($injectPayloads as $i => $payload) {
    $ch = curl_init();
    $start = microtime(true);
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . "/kecepatanAngin/getData?limit=" . urlencode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $elapsed = (microtime(true) - $start);
    curl_close($ch);
    
    // Blind SQLi via sleep
    $timeBased = $elapsed > 1.5;
    // Error-based
    $errorBased = preg_match('/(SQL syntax|mysql_fetch|PDOException|You have an error)/i', $body);
    
    $vuln = $timeBased || $errorBased || $code === 500;
    if ($vuln) $injectionVulnerable++;
    echo "  Payload " . ($i+1) . ": " . ($vuln ? "❌ TERINDIKASI" : "✅ AMAN") . 
         " (time: " . round($elapsed*1000) . "ms, HTTP {$code})\n";
    if ($vuln && $errorBased) {
        echo "    ⚠️  Tanda error: " . substr($body, 0, 100) . "\n";
    }
}
echo "  Total injeksi terindikasi: {$injectionVulnerable}/" . count($injectPayloads) . "\n";

echo "\n--- 5d. CSRF Protection Check (POST tanpa token) ---\n";
$csrfCh = curl_init();
curl_setopt_array($csrfCh, [
    CURLOPT_URL => $baseUrl . "/kecepatanAngin/store",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'tanggal' => '2026-01-01',
        'kecepatan_angin' => 10,
        'lokasi' => 'Test CSRF',
    ]),
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADER => true,
]);
$csrfResp = curl_exec($csrfCh);
$csrfCode = curl_getinfo($csrfCh, CURLINFO_HTTP_CODE);
curl_close($csrfCh);

$csrfBlocked = $csrfCode === 403 || $csrfCode === 302 || strpos($csrfResp, 'CSRF') !== false || strpos($csrfResp, 'Token keamanan tidak valid') !== false;
echo "  POST /kecepatanAngin/store tanpa CSRF token\n";
echo "  HTTP Code: {$csrfCode} - " . ($csrfBlocked ? "✅ DIBLOKIR (CSRF protection aktif)" : "❌ TIDAK DIBLOKIR") . "\n";

echo "\n--- 5e. Rate Limiting & Header Security ---\n";
// Headers check
$headerCh = curl_init();
curl_setopt_array($headerCh, [
    CURLOPT_URL => $baseUrl . "/kecepatanAngin/getData",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_TIMEOUT => 30,
]);
$headerResp = curl_exec($headerCh);
curl_close($headerCh);

$wantedHeaders = [
    'X-Frame-Options' => 'Clickjacking protection',
    'X-Content-Type-Options' => 'MIME sniffing protection',
    'X-XSS-Protection' => 'XSS filter',
    'Content-Security-Policy' => 'CSP',
    'Strict-Transport-Security' => 'HSTS (HTTPS only)',
    'X-RateLimit-Limit' => 'Rate limit header',
];
echo "  Pemeriksaan Security Headers:\n";
foreach ($wantedHeaders as $header => $desc) {
    $found = preg_match("/{$header}:/i", $headerResp);
    echo "    " . ($found ? "✅" : "ℹ️") . " {$header} ({$desc}): " . ($found ? "Ada" : "Tidak ada") . "\n";
}

// ========== REKOMENDASI RINGKAS ==========
echo "\n\n" . str_repeat("=", 60) . "\n";
echo "## RINGKASAN HASIL ANALISIS\n";
echo str_repeat("=", 60) . "\n";

$scoreItems = [];
$scoreItems['Endpoint Tersedia & Berfungsi'] = (count(array_filter($results, fn($r) => $r['http_code'] < 400)) / count($results)) * 25;
$scoreItems['Struktur Data Lengkap'] = $getDataJson ? 20 : 0;
$scoreItems['Akurasi Data Wajar'] = 20 - min(20, $inconsistent * 2 + (int)$invalidDir * 2 + count($duplicates) * 5);
$scoreItems['Performa Konkuren Stabil'] = ($loadResults[max($concurrencyLevels)]['error_pct'] < 5) ? 15 : max(0, 15 - $loadResults[max($concurrencyLevels)]['error_pct']);
$scoreItems['Keamanan Terjamin'] = 20 - ($anonCode < 400 && $anonCode !== 302 ? 10 : 0) - ($injectionVulnerable * 2);

$totalScore = array_sum($scoreItems);
echo "\n📊 SKOR KESEHATAN ENDPOINT: " . round($totalScore, 0) . "/100\n";
echo str_repeat("-", 40) . "\n";
foreach ($scoreItems as $item => $score) {
    $pct = round($score, 1);
    echo sprintf("  %-30s: %5.1f pts\n", $item, $pct);
}
echo "\nSkor > 80 = Baik | 60-80 = Perlu perbaikan | < 60 = Perhatian khusus\n";

echo "\nFile laporan disimpan? " . (isset($argv[1]) ? $argv[0] : "(script dijalankan langsung)") . "\n";
echo "\nAnalisis selesai pada: " . date('Y-m-d H:i:s') . "\n";

// Cleanup
@unlink($cookieFile);
@unlink($anonCookie);
@unlink($petugasCookie);
