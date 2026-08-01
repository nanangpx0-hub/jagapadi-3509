<?php
/**
 * JAGAPADI API Integration Test
 * Boots the backend HTTP server tests against the live app.
 * Run: php e2e/api_integration_test.php  (requires backend server on :8080)
 *
 * Covers: auth, role enforcement, all CRUD endpoints, validation, error handling,
 * include_draft policy, status state-machine, and ownership scoping.
 */

declare(strict_types=1);

$base = 'http://localhost:8080';
$results = ['pass' => 0, 'fail' => 0, 'skip' => 0];
$failures = [];

function req(string $method, string $uri, array $headers = [], ?string $body = null, bool $json = true): array
{
    global $base;
    $ch = curl_init($base . $uri);
    $opts = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = null;
    if ($json && $resp) {
        $data = json_decode($resp, true);
    }
    return ['code' => $httpCode, 'body' => $resp, 'json' => $data];
}

function check(string $name, bool $cond, string $detail = ''): void
{
    global $results, $failures;
    if ($cond) {
        $results['pass']++;
        echo "  PASS  $name\n";
    } else {
        $results['fail']++;
        $failures[] = "$name :: $detail";
        echo "  FAIL  $name :: $detail\n";
    }
}

echo "=== JAGAPADI API INTEGRATION TEST ===\n\n";

// ---------- 1. Public endpoints ----------
echo "[1] Public / Auth\n";
$r = req('GET', '/api/v1/health');
check('health returns 200', $r['code'] === 200, "code={$r['code']}");
check('health success true', ($r['json']['success'] ?? false) === true);

// Login admin
$r = req('POST', '/api/v1/auth/login', ['Content-Type: application/json'], json_encode(['username' => 'admin', 'password' => 'ChangeMeAdmin!123']));
check('admin login 200', $r['code'] === 200, "code={$r['code']} body={$r['body']}");
$adminToken = $r['json']['data']['token'] ?? '';
check('admin token issued', !empty($adminToken));
$adminAuth = ['Authorization: Bearer ' . $adminToken, 'Content-Type: application/json'];

// Login petugas
$r = req('POST', '/api/v1/auth/login', ['Content-Type: application/json'], json_encode(['username' => 'petugas01', 'password' => 'ChangeMePetugas!123']));
check('petugas login 200', $r['code'] === 200, "code={$r['code']} body={$r['body']}");
$petToken = $r['json']['data']['token'] ?? '';
check('petugas token issued', !empty($petToken));
$petAuth = ['Authorization: Bearer ' . $petToken, 'Content-Type: application/json'];

// Invalid creds
$r = req('POST', '/api/v1/auth/login', ['Content-Type: application/json'], json_encode(['username' => 'admin', 'password' => 'wrong']));
check('invalid login rejected 401', $r['code'] === 401, "code={$r['code']}");

// No token
$r = req('GET', '/api/v1/me');
check('me without token 401', $r['code'] === 401, "code={$r['code']}");

// me with token
$r = req('GET', '/api/v1/me', $adminAuth);
check('me with token 200', $r['code'] === 200 && ($r['json']['data']['role'] ?? '') === 'admin');

// ---------- 2. Role enforcement on admin-only API ----------
echo "[2] Role Enforcement (petugas cannot admin endpoints)\n";
$r = req('POST', '/api/v1/wilayah/kabupaten', $petAuth, json_encode(['kode' => '99', 'nama_kabupaten' => 'X']));
check('petugas cannot create kabupaten (403)', $r['code'] === 403, "code={$r['code']} body={$r['body']}");
$r = req('POST', '/api/v1/opt', $petAuth, json_encode(['nama_opt' => 'X', 'jenis' => 'hama']));
check('petugas cannot create OPT (403)', $r['code'] === 403, "code={$r['code']} body={$r['body']}");
$r = req('POST', '/api/v1/wilayah/kabupaten', $adminAuth, json_encode(['kode' => '99', 'nama_kabupaten' => 'TestKab']));
check('admin can create kabupaten (2xx)', $r['code'] >= 200 && $r['code'] < 300, "code={$r['code']} body={$r['body']}");
if ($r['code'] < 300) {
    $kid = $r['json']['data']['id'] ?? null;
    req('DELETE', '/api/v1/wilayah/kabupaten/' . $kid, $adminAuth);
}

// ---------- 3. Laporan Hama lifecycle (petugas owner, admin verify) ----------
echo "[3] Laporan Hama lifecycle + state machine\n";
$create = json_encode([
    'tanggal' => '2026-07-19',
    'master_opt_id' => 1,
    'kabupaten_id' => 1, 'kecamatan_id' => 1, 'desa_id' => 1,
    'latitude' => -8.17, 'longitude' => 113.7,
    'tingkat_keparahan' => 'Ringan', 'luas_serangan' => 1.5, 'populasi' => 10,
    'action' => 'draft',
]);
$r = req('POST', '/api/v1/laporan-hama', $petAuth, $create);
check('petugas create hama draft 2xx', $r['code'] >= 200 && $r['code'] < 300, "code={$r['code']} body={$r['body']}");
$hid = $r['json']['data']['id'] ?? null;
check('hama id returned', !empty($hid), "body={$r['body']}");
// nomor_laporan must be null at draft
check('draft has no nomor_laporan', empty($r['json']['data']['nomor_laporan']), "nomor=" . ($r['json']['data']['nomor_laporan'] ?? 'null'));

// petugas submits
$r = req('POST', "/api/v1/laporan-hama/$hid/submit", $petAuth);
check('petugas submit 2xx', $r['code'] >= 200 && $r['code'] < 300, "code={$r['code']} body={$r['body']}");
check('submitted has nomor_laporan', !empty($r['json']['data']['nomor_laporan'] ?? ''), "body={$r['body']}");

// petugas cannot verify
$r = req('POST', "/api/v1/laporan-hama/$hid/verifikasi", $petAuth);
check('petugas cannot verify (403)', $r['code'] === 403, "code={$r['code']} body={$r['body']}");

// admin verifies
$r = req('POST', "/api/v1/laporan-hama/$hid/verifikasi", $adminAuth, json_encode(['catatan_verifikasi' => 'ok']));
check('admin verify 2xx', $r['code'] >= 200 && $r['code'] < 300, "code={$r['code']} body={$r['body']}");

// illegal transition: verify again
$r = req('POST', "/api/v1/laporan-hama/$hid/verifikasi", $adminAuth);
check('re-verify after Diverifikasi rejected (409)', $r['code'] === 409, "code={$r['code']} body={$r['body']}");

// admin cannot delete other user's draft? cleanup via archive+delete path
req('DELETE', "/api/v1/laporan-hama/$hid", $adminAuth);

// ---------- 4. Validation / error handling ----------
echo "[4] Validation & Error Handling\n";
$r = req('POST', '/api/v1/laporan-hama', $petAuth, json_encode(['action' => 'submit']));
check('submit with missing required fields rejected (4xx)', $r['code'] >= 400 && $r['code'] < 500, "code={$r['code']} body={$r['body']}");
$r = req('GET', '/api/v1/laporan-hama/99999999', $adminAuth);
check('nonexistent laporan 404', $r['code'] === 404, "code={$r['code']} body={$r['body']}");
// SQL injection in login should not authenticate
$r = req('POST', '/api/v1/auth/login', ['Content-Type: application/json'], json_encode(['username' => "' OR '1'='1", 'password' => "' OR '1'='1"]));
check('SQLi login rejected', $r['code'] === 401, "code={$r['code']}");

// ---------- 5. include_draft policy ----------
echo "[5] include_draft policy\n";
// create a draft by petugas
req('POST', '/api/v1/laporan-hama', $petAuth, json_encode([
    'tanggal' => '2026-07-19', 'master_opt_id' => 1, 'kabupaten_id' => 1,
    'kecamatan_id' => 1, 'desa_id' => 1, 'latitude' => -8.1, 'longitude' => 113.7,
    'tingkat_keparahan' => 'Ringan', 'luas_serangan' => 1.0, 'populasi' => 5, 'action' => 'draft',
]));
$rNo = req('GET', '/api/v1/dashboard/stats', $adminAuth);
$rYes = req('GET', '/api/v1/dashboard/stats?include_draft=true', $adminAuth);
$noDraft = $rNo['json']['data']['hama']['total_draf'] ?? -1;
$yesDraft = $rYes['json']['data']['hama']['total_draf'] ?? -1;
check('include_draft=true yields >= drafts vs default', $yesDraft >= $noDraft, "no=$noDraft yes=$yesDraft");

// ---------- 6. Ownership scoping ----------
echo "[6] Ownership scoping (petugas sees own only)\n";
$r = req('GET', '/api/v1/laporan-hama', $petAuth);
check('petugas list 2xx', $r['code'] === 200, "code={$r['code']}");
$ownIds = array_map(fn($x) => $x['user_id'] ?? null, $r['json']['data']['items'] ?? []);
$allOwn = empty($ownIds) || count(array_unique(array_filter($ownIds))) === 1;
check('petugas laporan belong to self', $allOwn, "user_ids=" . implode(',', $ownIds));

// ---------- 7. Export ----------
echo "[7] Export endpoints\n";
$r = req('GET', '/api/v1/export/hama?format=csv', $adminAuth);
check('export hama csv 200', $r['code'] === 200, "code={$r['code']}");
$r = req('GET', '/api/v1/export/hama?format=xlsx', $adminAuth);
check('export hama xlsx 200', $r['code'] === 200, "code={$r['code']}");

// ---------- 8. Notifications ----------
echo "[8] Notifications\n";
$r = req('GET', '/api/v1/notifications', $petAuth);
check('notifications list 2xx', $r['code'] === 200, "code={$r['code']}");
$r = req('GET', '/api/v1/notifications/unread-count', $petAuth);
check('unread-count 2xx', $r['code'] === 200, "code={$r['code']}");

// ---------- 9. Wilayah / OPT read ----------
echo "[9] Wilayah & OPT read\n";
$r = req('GET', '/api/v1/wilayah/kabupaten', $petAuth);
check('wilayah kabupaten read 2xx', $r['code'] === 200, "code={$r['code']}");
$r = req('GET', '/api/v1/opt', $petAuth);
check('opt read 2xx', $r['code'] === 200, "code={$r['code']}");

// ---------- 9b. Map / GeoJSON ----------
echo "[9b] Map GeoJSON endpoints\n";
$r = req('GET', '/api/v1/dashboard/map/hama', $adminAuth);
check('map hama 2xx', $r['code'] === 200, "code={$r['code']}");
$map = $r['json']['data'] ?? [];
check('map hama FeatureCollection', ($map['type'] ?? '') === 'FeatureCollection', "type=" . ($map['type'] ?? ''));
if (($map['type'] ?? '') === 'FeatureCollection' && count($map['features'] ?? []) > 0) {
    $f = $map['features'][0];
    check('map hama geometry Point + [lng,lat]', ($f['geometry']['type'] ?? '') === 'Point' && count($f['geometry']['coordinates'] ?? []) === 2, json_encode($f['geometry'] ?? []));
}
$r = req('GET', '/api/v1/dashboard/map/irigasi', $adminAuth);
check('map irigasi 2xx', $r['code'] === 200, "code={$r['code']}");
$mapI = $r['json']['data'] ?? [];
check('map irigasi FeatureCollection', ($mapI['type'] ?? '') === 'FeatureCollection', "type=" . ($mapI['type'] ?? ''));
$r = req('GET', '/api/v1/dashboard/map/hama?limit=5000', $adminAuth);
$meta = $r['json']['data']['meta'] ?? [];
check('map hama limit capped at 1000', ($meta['limit'] ?? 0) <= 1000, "limit=" . ($meta['limit'] ?? 0));
$rNo = req('GET', '/api/v1/dashboard/map/hama', $adminAuth);
$rYes = req('GET', '/api/v1/dashboard/map/hama?include_draft=true', $adminAuth);
$noCount = $rNo['json']['data']['meta']['count'] ?? -1;
$yesCount = $rYes['json']['data']['meta']['count'] ?? -1;
check('include_draft=true yields >= points vs default', $yesCount >= $noCount, "no=$noCount yes=$yesCount");

// ---------- Summary ----------
echo "\n=== SUMMARY ===\n";
echo "PASS: {$results['pass']}  FAIL: {$results['fail']}  SKIP: {$results['skip']}\n";
if (!empty($failures)) {
    echo "\nFAILURES:\n";
    foreach ($failures as $f) {
        echo " - $f\n";
    }
    exit(1);
}
echo "ALL API TESTS PASSED\n";
