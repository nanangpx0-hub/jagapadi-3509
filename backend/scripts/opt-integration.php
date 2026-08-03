<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

$envPath = BASE_PATH . '/.env';
if (file_exists($envPath)) {
    Env::load($envPath);
}

const AUDIT_FILE = BASE_PATH . '/tmp/opt-audit-findings.json';
const RESEARCH_FILE = BASE_PATH . '/tmp/opt-research-findings.json';
const LOG_FILE = BASE_PATH . '/tmp/opt-integration-log.json';

function loadJsonFile(string $path): array
{
    if (!file_exists($path)) {
        throw new RuntimeException("File not found: {$path}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Failed to read file: {$path}");
    }

    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in {$path}: " . json_last_error_msg());
    }

    return $data;
}

function getExistingOptNames(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, nama_opt, jenis FROM master_opt');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[strtolower(trim($row['nama_opt']))] = [
            'id' => (int) $row['id'],
            'jenis' => $row['jenis'],
        ];
    }
    return $map;
}

function normalizeOptName(string $name): string
{
    return trim(strtolower($name));
}

function isDuplicate(string $namaOpt, array $existingNames): bool
{
    return isset($existingNames[normalizeOptName($namaOpt)]);
}

function inferTingkatBahaya(string $impact): string
{
    $impactLower = strtolower($impact);
    if (strpos($impactLower, 'mati') !== false || strpos($impactLower, '65%') !== false || strpos($impactLower, 'turun 30%') !== false) {
        return 'Tinggi';
    }
    if (strpos($impactLower, 'serabut membusuk') !== false || strpos($impactLower, 'penyebab utama') !== false) {
        return 'Tinggi';
    }
    if (strpos($impactLower, 'gugur') !== false || strpos($impactLower, 'bercak kuning') !== false) {
        return 'Sedang';
    }
    if (strpos($impactLower, 'mengering') !== false) {
        return 'Sedang';
    }
    if (strpos($impactLower, '≤ 25%') !== false || strpos($impactLower, 'ringan') !== false) {
        return 'Rendah';
    }
    return 'Sedang';
}

function inferSatuanEtl(string $namaOpt, string $impact): string
{
    $impactLower = strtolower($impact);
    if (strpos($impactLower, 'buah') !== false || strpos($impactLower, 'lubang') !== false) {
        return '%';
    }
    if (strpos($impactLower, 'akar') !== false) {
        return 'tanaman';
    }
    if (strpos($impactLower, 'daun') !== false || strpos($impactLower, 'bercak') !== false) {
        return '%';
    }
    return '%';
}

function inferEtlAcuan(string $impact): ?float
{
    if (preg_match('/(\d+)%/', $impact, $matches)) {
        return (float) $matches[1];
    }
    return null;
}

function extractResearchOptData(array $researchData): array
{
    $findings = [];

    if (!isset($researchData['jember_specific_findings']['major_opt_by_crop'])) {
        return $findings;
    }

    $cropData = $researchData['jember_specific_findings']['major_opt_by_crop'];

    $cropCategories = [
        'kopi' => 'hama',
        'tembakau' => 'hama',
        'padi' => 'hama',
        'jeruk' => 'hama',
    ];

    foreach ($cropCategories as $crop => $defaultJenis) {
        if (!isset($cropData[$crop]['primary_pests'])) {
            continue;
        }

        $pests = $cropData[$crop]['primary_pests'];
        if (!is_array($pests)) {
            continue;
        }

        foreach ($pests as $pest) {
            if (!isset($pest['nama_opt'])) {
                continue;
            }

            $namaOpt = trim($pest['nama_opt']);
            if ($namaOpt === '') {
                continue;
            }

            $namaIlmiah = $pest['nama_ilmiah'] ?? null;
            $localName = $pest['local_name'] ?? null;

            $taxonomy = $pest['taxonomy'] ?? [];
            $kingdom = $taxonomy['kingdom'] ?? null;

            $faseSerangan = $pest['fase_serangan'] ?? null;
            $impact = $pest['impact'] ?? '';

            $findings[] = [
                'nama_opt' => $namaOpt,
                'nama_ilmiah' => $namaIlmiah,
                'nama_lokal' => $localName,
                'jenis' => $defaultJenis,
                'status_karantina' => 'Tidak',
                'tingkat_bahaya' => inferTingkatBahaya($impact),
                'kategori' => 'utama',
                'kingdom' => $kingdom,
                'etl_acuan' => inferEtlAcuan($impact),
                'satuan_etl' => inferSatuanEtl($namaOpt, $impact),
                'deskripsi' => $impact,
                'fase_serangan' => $faseSerangan,
                'source' => $researchData['metadata']['sources'][0] ?? 'Research findings',
                'source_date' => $researchData['metadata']['date'] ?? null,
            ];
        }
    }

    return $findings;
}

function generateKodeOpt(PDO $pdo, string $jenis): string
{
    $prefix = match ($jenis) {
        'hama' => 'H',
        'penyakit' => 'P',
        'gulma' => 'G',
        default => 'X',
    };

    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(kode_opt, 2) AS UNSIGNED)) AS max_num FROM master_opt WHERE kode_opt LIKE :prefix");
    $stmt->execute([':prefix' => $prefix . '%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $maxNum = (int) ($row['max_num'] ?? 0);
    $nextNum = $maxNum + 1;

    return $prefix . str_pad((string) $nextNum, 2, '0', STR_PAD_LEFT);
}

function insertOptRecord(PDO $pdo, array $finding, string $sourceFile): array
{
    $kodeOpt = generateKodeOpt($pdo, $finding['jenis']);

    $stmt = $pdo->prepare(
        'INSERT INTO master_opt (kode_opt, nama_opt, nama_ilmiah, nama_lokal, jenis, status_karantina, tingkat_bahaya, kategori, kingdom, etl_acuan, satuan_etl, deskripsi, aktif) VALUES (:kode_opt, :nama_opt, :nama_ilmiah, :nama_lokal, :jenis, :status_karantina, :tingkat_bahaya, :kategori, :kingdom, :etl_acuan, :satuan_etl, :deskripsi, :aktif)'
    );

    $stmt->execute([
        ':kode_opt' => $kodeOpt,
        ':nama_opt' => $finding['nama_opt'],
        ':nama_ilmiah' => $finding['nama_ilmiah'],
        ':nama_lokal' => $finding['nama_lokal'],
        ':jenis' => $finding['jenis'],
        ':status_karantina' => $finding['status_karantina'],
        ':tingkat_bahaya' => $finding['tingkat_bahaya'],
        ':kategori' => $finding['kategori'],
        ':kingdom' => $finding['kingdom'],
        ':etl_acuan' => $finding['etl_acuan'],
        ':satuan_etl' => $finding['satuan_etl'],
        ':deskripsi' => $finding['deskripsi'],
        ':aktif' => 1,
    ]);

    $newId = (int) $pdo->lastInsertId();

    return [
        'status' => 'inserted',
        'id' => $newId,
        'kode_opt' => $kodeOpt,
        'nama_opt' => $finding['nama_opt'],
        'jenis' => $finding['jenis'],
        'source_file' => $sourceFile,
        'source' => $finding['source'] ?? null,
    ];
}

function logChanges(array $logData): void
{
    $logData['completed_at'] = date('Y-m-d H:i:s');
    $json = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents(LOG_FILE, $json . PHP_EOL);
}

function main(): void
{
    $log = [
        'script' => 'opt-integration.php',
        'started_at' => date('Y-m-d H:i:s'),
        'audit_file' => AUDIT_FILE,
        'research_file' => RESEARCH_FILE,
        'total_findings_processed' => 0,
        'total_inserted' => 0,
        'total_skipped_duplicate' => 0,
        'total_skipped_test_data' => 0,
        'total_skipped_invalid' => 0,
        'errors' => [],
        'inserted_records' => [],
        'skipped_records' => [],
        'audit_summary' => [],
    ];

    try {
        $pdo = Database::connect();
    } catch (Throwable $e) {
        $log['errors'][] = 'Database connection failed: ' . $e->getMessage();
        logChanges($log);
        fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
        exit(1);
    }

    $existingNames = getExistingOptNames($pdo);

    $auditData = [];
    $researchData = [];

    try {
        $auditData = loadJsonFile(AUDIT_FILE);
    } catch (Throwable $e) {
        $log['errors'][] = 'Failed to load audit file: ' . $e->getMessage();
    }

    try {
        $researchData = loadJsonFile(RESEARCH_FILE);
    } catch (Throwable $e) {
        $log['errors'][] = 'Failed to load research file: ' . $e->getMessage();
    }

    if (!empty($auditData)) {
        $log['audit_summary'] = [
            'database' => $auditData['database'] ?? 'unknown',
            'audit_date' => $auditData['audit_date'] ?? 'unknown',
            'tables_audited' => $auditData['tables_audited'] ?? [],
            'master_opt_total_records' => $auditData['master_opt']['total_records'] ?? 0,
            'master_opt_jenis_distribution' => $auditData['master_opt']['jenis_distribution'] ?? [],
            'test_dummy_records_count' => count($auditData['master_opt']['test_dummy_data'] ?? []),
            'inactive_records_count' => count($auditData['master_opt']['inactive_records'] ?? []),
            'null_fields_summary' => $auditData['master_opt']['null_fields'] ?? [],
        ];
    }

    $researchFindings = extractResearchOptData($researchData);
    $log['total_findings_processed'] = count($researchFindings);

    $pdo->beginTransaction();

    try {
        foreach ($researchFindings as $finding) {
            if (isDuplicate($finding['nama_opt'], $existingNames)) {
                $log['total_skipped_duplicate']++;
                $log['skipped_records'][] = [
                    'nama_opt' => $finding['nama_opt'],
                    'reason' => 'Duplicate: already exists in master_opt',
                    'source_file' => 'opt-research-findings.json',
                    'existing_id' => $existingNames[normalizeOptName($finding['nama_opt'])]['id'] ?? null,
                ];
                continue;
            }

            $result = insertOptRecord($pdo, $finding, 'opt-research-findings.json');
            $log['total_inserted']++;
            $log['inserted_records'][] = $result;
            $existingNames[normalizeOptName($finding['nama_opt'])] = [
                'id' => $result['id'],
                'jenis' => $finding['jenis'],
            ];
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $log['errors'][] = 'Transaction rolled back: ' . $e->getMessage();
        logChanges($log);
        fwrite(STDERR, "Transaction failed: {$e->getMessage()}\n");
        exit(1);
    }

    logChanges($log);

    echo "OPT integration completed.\n";
    echo "Total findings processed: {$log['total_findings_processed']}\n";
    echo "Inserted: {$log['total_inserted']}\n";
    echo "Skipped (duplicate): {$log['total_skipped_duplicate']}\n";
    echo "Skipped (test data from audit): {$log['total_skipped_test_data']}\n";
    echo "Skipped (invalid): {$log['total_skipped_invalid']}\n";
    echo "Errors: " . count($log['errors']) . "\n";
    echo "Log written to: " . LOG_FILE . "\n";
}

main();