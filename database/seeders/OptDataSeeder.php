<?php
/**
 * OPT Data Seeder
 * Seeds the master_opt table with real Indonesian OPT (Organisme Pengganggu Tumbuhan) data
 * Based on references from BPTP, Balitbangtan, and other official Indonesian agricultural sources
 *
 * Schema master_opt: id, nama_opt, jenis (hama|penyakit|gulma), etl_acuan,
 *                    satuan_etl, foto_url, deskripsi, aktif, created_at, updated_at
 * Upsert key: nama_opt (UNIQUE)
 */

require_once __DIR__ . '/../../config/database.php';

echo "<h2>OPT Data Seeder</h2>\n";
echo "<pre>\n";

try {
    $db = Database::getInstance()->getConnection();

    // Real Indonesian OPT data
    $optData = [
        // === HAMA (Pests) ===
        [
            'nama_opt' => 'Wereng Coklat',
            'jenis' => 'hama',
            'deskripsi' => 'Wereng coklat merupakan hama utama tanaman padi di Indonesia. Serangga ini mengisap cairan tanaman sehingga menyebabkan daun dan batang menguning (hopperburn). Populasi tinggi dapat menyebabkan puso (gagal panen total).',
            'etl_acuan' => 10,
            'satuan_etl' => 'ekor/ibatang'
        ],
        [
            'nama_opt' => 'Penggerek Batang Padi',
            'jenis' => 'hama',
            'deskripsi' => 'Larva menggerek batang padi menyebabkan sundep (fase vegetatif) atau beluk (fase generatif). Gejala sundep: tunas mati. Gejala beluk: malai hampa berwarna putih.',
            'etl_acuan' => 5,
            'satuan_etl' => 'gerek/ha'
        ],
        [
            'nama_opt' => 'Walang Sangit',
            'jenis' => 'hama',
            'deskripsi' => 'Serangga yang menghisap cairan bulir padi yang sedang mengisi. Menyebabkan bulir hampa atau beras menjadi berwarna hitam (spotty grain). Memiliki bau tidak sedap bila diganggu.',
            'etl_acuan' => 2,
            'satuan_etl' => 'ekor/malai'
        ],
        [
            'nama_opt' => 'Tikus Sawah',
            'jenis' => 'hama',
            'deskripsi' => 'Hama vertebrata paling merusak pada pertanaman padi. Menyerang mulai dari persemaian hingga panen. Dapat menyebabkan kerusakan total area pertanaman.',
            'etl_acuan' => 0,
            'satuan_etl' => 'lubang/ha'
        ],
        [
            'nama_opt' => 'Ulat Grayak',
            'jenis' => 'hama',
            'deskripsi' => 'Ulat yang menyerang daun padi secara berkelompok. Pada populasi tinggi dapat bermigrasi secara massal dan memakan habis daun tanaman.',
            'etl_acuan' => 5,
            'satuan_etl' => 'ekor/tanaman'
        ],
        [
            'nama_opt' => 'Keong Mas',
            'jenis' => 'hama',
            'deskripsi' => 'Moluska air tawar invasif yang memakan bibit padi muda. Sangat merusak pada fase pesemaian dan awal tanam. Berkembang biak cepat dengan telur berwarna merah muda.',
            'etl_acuan' => 0,
            'satuan_etl' => 'ekor/m2'
        ],

        // === PENYAKIT (Diseases) ===
        [
            'nama_opt' => 'Blast (Blas)',
            'jenis' => 'penyakit',
            'deskripsi' => 'Penyakit jamur paling merusak pada padi. Menyerang daun (leaf blast), leher malai (neck blast), dan ruas batang. Gejala berupa bercak belah ketupat dengan pusat abu-abu.',
            'etl_acuan' => 0,
            'satuan_etl' => 'gejala/ha'
        ],
        [
            'nama_opt' => 'Hawar Daun Bakteri',
            'jenis' => 'penyakit',
            'deskripsi' => 'Penyakit bakteri yang menyebabkan hawar pada daun padi. Gejala awal berupa bercak hijau kelabu di tepi daun yang meluas. Pada fase kresek, daun mengering seperti terbakar.',
            'etl_acuan' => 0,
            'satuan_etl' => 'gejala/ha'
        ],
        [
            'nama_opt' => 'Tungro',
            'jenis' => 'penyakit',
            'deskripsi' => 'Penyakit virus yang ditularkan oleh wereng hijau (Nephotettix virescens). Menyebabkan pertumbuhan kerdil, daun menguning hingga oranye, dan malai hampa.',
            'etl_acuan' => 0,
            'satuan_etl' => 'gejala/ha'
        ],
        [
            'nama_opt' => 'Busuk Batang',
            'jenis' => 'penyakit',
            'deskripsi' => 'Penyakit jamur yang menyerang pelepah daun dan batang bagian bawah. Menyebabkan batang mudah rebah dan malai tidak berkembang sempurna.',
            'etl_acuan' => 0,
            'satuan_etl' => 'gejala/ha'
        ],
        [
            'nama_opt' => 'Bercak Coklat',
            'jenis' => 'penyakit',
            'deskripsi' => 'Penyakit jamur dengan gejala bercak bulat lonjong berwarna coklat pada daun. Sering menyerang tanaman yang kekurangan unsur hara atau stres air.',
            'etl_acuan' => 0,
            'satuan_etl' => 'gejala/ha'
        ],

        // === GULMA (Weeds) ===
        [
            'nama_opt' => 'Eceng Gondok',
            'jenis' => 'gulma',
            'deskripsi' => 'Gulma air yang berkembang biak sangat cepat. Menyumbat saluran irigasi, mengurangi kadar oksigen air, dan bersaing dengan tanaman padi untuk sinar matahari.',
            'etl_acuan' => 0,
            'satuan_etl' => 'tanaman/m2'
        ],
        [
            'nama_opt' => 'Teki',
            'jenis' => 'gulma',
            'deskripsi' => 'Gulma berdaun sempit yang sangat sulit dikendalikan karena memiliki umbi (nut) di dalam tanah. Berkompetisi kuat dengan tanaman padi untuk air, hara, dan cahaya.',
            'etl_acuan' => 0,
            'satuan_etl' => 'rumpun/m2'
        ],
        [
            'nama_opt' => 'Rumput Sawah',
            'jenis' => 'gulma',
            'deskripsi' => 'Gulma rumput yang mirip tanaman padi saat muda. Sangat kompetitif terutama pada fase awal pertumbuhan padi. Dapat menurunkan hasil panen secara signifikan.',
            'etl_acuan' => 0,
            'satuan_etl' => 'tanaman/m2'
        ],
        [
            'nama_opt' => 'Genjer',
            'jenis' => 'gulma',
            'deskripsi' => 'Gulma air yang dapat dimakan sebagai sayuran. Meskipun berkompetisi dengan padi, dampaknya relatif ringan dan tanaman ini memiliki nilai ekonomi sebagai pangan.',
            'etl_acuan' => 0,
            'satuan_etl' => 'tanaman/m2'
        ],
    ];

    echo "Memulai seeding data OPT...\n";
    echo str_repeat('-', 60) . "\n";

    $insertCount = 0;
    $updateCount = 0;
    $skipCount = 0;

    foreach ($optData as $data) {
        // Check if already exists by nama_opt (UNIQUE)
        $stmt = $db->prepare("SELECT id FROM master_opt WHERE nama_opt = ?");
        $stmt->execute([$data['nama_opt']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing record
            $sql = "UPDATE master_opt SET
                jenis = ?, deskripsi = ?, etl_acuan = ?, satuan_etl = ?, aktif = 1, updated_at = NOW()
                WHERE nama_opt = ?";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['jenis'], $data['deskripsi'], $data['etl_acuan'], $data['satuan_etl'], $data['nama_opt']
            ]);

            echo "UPDATE: {$data['nama_opt']}\n";
            $updateCount++;
        } else {
            // Insert new record
            $sql = "INSERT INTO master_opt
                (nama_opt, jenis, deskripsi, etl_acuan, satuan_etl, aktif, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['nama_opt'], $data['jenis'], $data['deskripsi'], $data['etl_acuan'], $data['satuan_etl']
            ]);

            echo "INSERT: {$data['nama_opt']}\n";
            $insertCount++;
        }
    }
    
    echo str_repeat('=', 60) . "\n";
    echo "Seeding completed!\n";
    echo "Inserted: $insertCount, Updated: $updateCount, Skipped: $skipCount\n";
    echo str_repeat('=', 60) . "\n";
    
    // Show summary
    echo "\nData Summary:\n";
    $stats = $db->query("SELECT jenis, COUNT(*) as total FROM master_opt GROUP BY jenis")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stats as $stat) {
        echo "  - {$stat['jenis']}: {$stat['total']} records\n";
    }
    
    $total = $db->query("SELECT COUNT(*) as total FROM master_opt")->fetch()['total'];
    echo "\nTotal OPT records: $total\n";
    
    echo "\n✅ OPT data seeding completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Seeding failed: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
echo "<p><a href='" . (defined('BASE_URL') ? BASE_URL : '/jagapadi/') . "opt'>← View OPT List</a></p>";
?>
