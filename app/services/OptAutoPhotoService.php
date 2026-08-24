<?php

declare(strict_types=1);

final class OptAutoPhotoService
{
    private const API_URL = 'https://commons.wikimedia.org/w/api.php';
    private const ALLOWED_IMAGE_HOST = 'upload.wikimedia.org';
    private const MAX_BYTES = 10_485_760;

    /** Nama pencarian yang lebih presisi daripada nama lokal di master.
     * Nilai boleh string (satu query) atau array (kandidat berurutan;
     * diambil yang pertama menghasilkan gambar valid). */
    private const SEARCH_TERMS = [
        'wereng batang coklat' => 'Nilaparvata lugens brown planthopper',
        'penggerek batang padi' => 'Scirpophaga incertulas rice stem borer',
        'tikus sawah' => 'Rattus argentiventer',
        'walang sangit' => 'Leptocorisa oratorius',
        'ulat grayak' => 'Spodoptera frugiperda',
        'belalang hama' => 'grasshopper Acrididae',
        'keong mas' => 'Pomacea canaliculata',
        'siput murbai' => 'Pomacea canaliculata',
        'orong-orong' => 'Gryllotalpa orientalis mole cricket',
        'ulat tanah' => 'Agrotis ipsilon cutworm',
        'lalat bibit' => 'Atherigona soccata',
        'hama putih' => 'Parapoynx stagnalis rice caseworm',
        'hama putih palsu' => 'Cnaphalocrocis medinalis',
        'wereng hijau' => 'Nephotettix virescens',
        'wereng punggung putih' => [
            'Sogatella planthopper',
            'Sogatella furcifera',
            'white-backed planthopper rice',
        ],
        'kepinding tanah' => 'Scotinophara coarctata',
        'thrips' => 'Thrips insect',
        'kutu daun' => 'Aphididae aphid',
        'pengorok daun' => 'Liriomyza leaf miner',
        'lembing batu' => 'Dicladispa armigera rice hispa',
        'blas (blast)' => [
            'Magnaporthe oryzae',
            'rice blast disease',
            'rice blast lesion',
        ],
        'hawar daun bakteri' => 'bacterial leaf blight rice',
        'tungro' => 'rice tungro disease',
        'hawar pelepah' => 'rice sheath blight disease',
        'busuk pelepah' => 'rice sheath rot disease',
        'busuk batang' => [
            'Sarocladium oryzae',
            'rice sheath rot symptoms',
        ],
        'bercak cokelat' => [
            'Bipolaris oryzae',
            'Cochliobolus miyabeanus',
            'brown spot of rice leaf',
        ],
        'bercak garis cokelat' => [
            'Cercospora oryzae narrow brown leaf spot',
            'narrow brown leaf spot rice',
            'Cercospora janseana',
        ],
        'bakteri daun bergaris' => 'bacterial leaf streak rice',
        'kerdil rumput' => [
            'rice grassy stunt virus symptoms',
            'grassy stunt virus rice',
        ],
        'kerdil hampa' => 'rice ragged stunt disease',
        'gosong palsu' => 'rice false smut disease',
        'noda palsu' => 'rice false smut disease',
        'gulma rumput teki' => 'Cyperus rotundus',
        'gulma rumput' => 'Echinochloa crus-galli',
        'gulma daun lebar' => 'Monochoria vaginalis',
        'gulma teki' => 'Cyperus iria sedge',
        'bulai' => [
            'Peronosclerospora maydis',
            'maize downy mildew symptom',
        ],
        'penggerek batang jagung' => 'Ostrinia furnacalis Asian corn borer',
        'karatan daun' => [
            'Puccinia sorghi',
            'maize common rust leaf',
            'Puccinia polysora',
        ],
        'jangkrik tanah' => 'field cricket Gryllidae',
        'ngengat gudang / ketep' => 'Sitotroga cerealella grain moth',
        'uret / gayas' => 'white grub Scarabaeidae larva',
        'burung pipit (lonchura spp.)' => 'Lonchura munia',
        'burung gereja (passer montanus)' => 'Passer montanus',
        'burung manyar (ploceus spp.)' => 'Ploceus weaver bird',
        'babi hutan (sus scrofa)' => 'Sus scrofa wild boar',
        'monyet ekor panjang (macaca fascicularis)' => 'Macaca fascicularis',
        'kepiting sawah (parathelphusa convexa)' => 'Parathelphusa convexa',
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array{processed:int,updated:int,failed:int,last_id:int,errors:array<int,string>} */
    public function fillMissing(int $limit = 15, int $afterId = 0): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->prepare(
            'SELECT id, nama_opt, nama_ilmiah, nama_lokal, referensi FROM master_opt '
            . "WHERE (foto_url IS NULL OR TRIM(foto_url) = '') AND id > ? ORDER BY id ASC LIMIT {$limit}"
        );
        $stmt->execute([max(0, $afterId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updated = 0;
        $errors = [];
        foreach ($rows as $row) {
            try {
                $image = null;
                $lastError = 'gambar cocok tidak ditemukan';
                foreach ($this->searchTerms($row) as $query) {
                    try {
                        $image = $this->findCommonsImage($query);
                    } catch (Throwable $e) {
                        // layanan sementara tidak tersedia untuk query ini; coba kandidat berikutnya
                        $lastError = $e->getMessage();
                        $image = null;
                    }
                    if ($image !== null) {
                        break;
                    }
                }
                if ($image === null) {
                    throw new RuntimeException($lastError);
                }
                $relativePath = $this->downloadImage($image['url'], (string) $row['nama_opt']);
                $attribution = sprintf(
                    'Foto: Wikimedia Commons, %s, lisensi %s, %s',
                    $image['title'],
                    $image['license'] ?: 'lihat halaman sumber',
                    $image['description_url']
                );
                $reference = trim((string) ($row['referensi'] ?? ''));
                // Hindari duplikasi atribusi untuk sumber yang sama
                if ($reference === '' || !str_contains($reference, $image['description_url'])) {
                    if ($reference !== '') {
                        $reference .= "\n";
                    }
                    $reference .= $attribution;
                }

                $stmt = $this->db->prepare(
                    "UPDATE master_opt SET foto_url = ?, referensi = ? WHERE id = ? AND (foto_url IS NULL OR TRIM(foto_url) = '')"
                );
                $stmt->execute([$relativePath, $reference, (int) $row['id']]);
                if ($stmt->rowCount() === 1) {
                    $updated++;
                } else {
                    @unlink(ROOT_PATH . '/public/' . $relativePath);
                }
            } catch (Throwable $e) {
                $errors[] = (string) $row['nama_opt'] . ': ' . $e->getMessage();
            }
            usleep(350000);
        }

        MasterOptService::clearMasterOptCache();

        return [
            'processed' => count($rows),
            'updated' => $updated,
            'failed' => count($errors),
            'last_id' => $rows === [] ? $afterId : (int) end($rows)['id'],
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row
     * @return list<string> kandidat query berurutan (nama ilmiah diprioritaskan) */
    private function searchTerms(array $row): array
    {
        $candidates = [];
        $scientific = trim((string) ($row['nama_ilmiah'] ?? ''));
        if ($scientific !== '') {
            $candidates[] = $scientific;
        }

        $name = mb_strtolower(trim((string) $row['nama_opt']));
        $mapped = self::SEARCH_TERMS[$name] ?? null;
        foreach ((array) ($mapped ?? []) as $term) {
            $candidates[] = (string) $term;
        }

        $fallback = trim((string) ($row['nama_lokal'] ?: $row['nama_opt']));
        if ($fallback !== '') {
            $candidates[] = $fallback;
        }

        // buang duplikat & kosong, pertahankan urutan
        return array_values(array_unique(array_filter(array_map('trim', $candidates))));
    }

    /** Kata umum yang tidak boleh dijadikan bukti kecocokan judul. */
    private const GENERIC_TOKENS = [
        'rice', 'padi', 'leaf', 'leaves', 'daun', 'disease', 'penyakit', 'symptom',
        'symptoms', 'gejala', 'crop', 'plant', 'plants', 'photo', 'file', 'image',
        'jpg', 'jpeg', 'png', 'closeup',
    ];

    /** @return array{url:string,title:string,license:string,description_url:string}|null */
    private function findCommonsImage(string $query): ?array
    {
        $params = [
            'action' => 'query', 'format' => 'json', 'generator' => 'search',
            'gsrnamespace' => 6, 'gsrlimit' => 10, 'gsrsearch' => $query,
            'prop' => 'imageinfo', 'iiprop' => 'url|mime|size|extmetadata', 'iiurlwidth' => 1200,
            'iiextmetadatafilter' => 'LicenseShortName|LicenseUrl',
        ];
        $payload = $this->request(self::API_URL . '?' . http_build_query($params));
        $tokens = $this->queryTokens($query);
        $pages = $payload['query']['pages'] ?? [];
        $best = null;
        $bestScore = -1;
        foreach ($pages as $page) {
            $info = $page['imageinfo'][0] ?? null;
            $url = (string) ($info['thumburl'] ?? $info['url'] ?? '');
            $mime = strtolower((string) ($info['thumbmime'] ?? $info['mime'] ?? ''));
            if ($url === '' || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }
            // Lewati gambar terlalu kecil (ikon/miniatur) agar lolos validasi dimensi unduhan
            if ((int) ($info['width'] ?? 0) < 320 || (int) ($info['height'] ?? 0) < 240) {
                continue;
            }
            // Validasi relevansi: sebagian token query harus muncul di judul berkas
            $score = $this->relevanceScore((string) ($page['title'] ?? ''), $tokens);
            if ($score <= 0) {
                continue;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'url' => $url,
                    'title' => (string) ($page['title'] ?? ''),
                    'license' => strip_tags((string) ($info['extmetadata']['LicenseShortName']['value'] ?? '')),
                    'description_url' => (string) ($info['descriptionurl'] ?? ''),
                ];
            }
        }
        return $best;
    }

    /** @return list<string> token bermakna dari query pencarian */
    private function queryTokens(string $query): array
    {
        $normalized = mb_strtolower($query);
        $raw = preg_split('/[^a-z0-9]+/', $normalized) ?: [];
        $tokens = array_filter($raw, static fn(string $t): bool => mb_strlen($t) >= 4 && !in_array($t, self::GENERIC_TOKENS, true));
        return array_values(array_unique($tokens));
    }

    /** Jumlah token query yang muncul di judul berkas (≥0). */
    private function relevanceScore(string $title, array $tokens): int
    {
        if ($tokens === []) {
            return 1; // tanpa token bermakna: tidak bisa dinilai, izinkan (perilaku lama)
        }
        $haystack = mb_strtolower($title);
        $score = 0;
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $score++;
            }
        }
        return $score;
    }

    /** @return array<string,mixed> */
    private function request(string $url): array
    {
        $body = false;
        $status = 0;
        $error = '';
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'JAGAPADI/1.0 (OPT photo enrichment)',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if (is_string($body) && $status === 200) {
                break;
            }
            if ($attempt < 3) {
                usleep($attempt * 1_500_000);
            }
        }
        if (!is_string($body) || $status !== 200) {
            throw new RuntimeException('layanan gambar tidak tersedia' . ($error !== '' ? ': ' . $error : ''));
        }
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : [];
    }

    private function downloadImage(string $url, string $label): string
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || strtolower((string) ($parts['host'] ?? '')) !== self::ALLOWED_IMAGE_HOST) {
            throw new RuntimeException('host gambar tidak diizinkan');
        }

        // Unduhan dapat terputus sesaat; coba maksimal 2 kali.
        $lastError = 'gagal mengunduh gambar';
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return $this->downloadImageOnce($url, $label);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
                // Validasi konten gagal permanen (bukan gambar/kecil) -> jangan diulang
                if (str_contains($lastError, 'bukan gambar valid')) {
                    throw $e;
                }
                if ($attempt < 2) {
                    usleep(800_000);
                }
            }
        }
        throw new RuntimeException($lastError);
    }

    private function downloadImageOnce(string $url, string $label): string
    {

        $temp = tempnam(sys_get_temp_dir(), 'jagapadi-opt-');
        if ($temp === false) {
            throw new RuntimeException('gagal membuat file sementara');
        }
        $handle = fopen($temp, 'wb');
        $bytes = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'JAGAPADI/1.0 (OPT photo enrichment)',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($handle, &$bytes): int {
                $bytes += strlen($chunk);
                if ($bytes > self::MAX_BYTES) {
                    return 0;
                }
                return fwrite($handle, $chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($handle);

        try {
            if ($ok !== true || $status !== 200 || $bytes === 0 || $bytes > self::MAX_BYTES) {
                throw new RuntimeException('gagal mengunduh gambar');
            }
            $info = @getimagesize($temp);
            $mime = strtolower((string) ($info['mime'] ?? ''));
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime]) || ($info[0] ?? 0) < 200 || ($info[1] ?? 0) < 200) {
                throw new RuntimeException('berkas bukan gambar valid atau terlalu kecil');
            }

            $dir = ROOT_PATH . '/public/uploads/opt/' . date('Y/m') . '/';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('gagal membuat direktori gambar');
            }
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($label)) ?: 'opt';
            $filename = trim($slug, '-') . '-auto-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
            if (!rename($temp, $dir . $filename)) {
                throw new RuntimeException('gagal menyimpan gambar');
            }
            return 'uploads/opt/' . date('Y/m') . '/' . $filename;
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }
}
