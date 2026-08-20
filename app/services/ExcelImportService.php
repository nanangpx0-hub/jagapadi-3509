<?php
/**
 * Excel Import Service
 * Service untuk import data dari file Excel (xlsx, xls)
 * 
 * @version 1.1.0
 * @author JAGAPADI System
 */

class ExcelImportService {
    private $errors = [];
    private $warnings = [];
    private $successCount = 0;
    private $failedCount = 0;
    
    // Required columns mapping: Excel column header => database field
    private $columnMapping = [
        'tanggal' => 'tanggal',
        'date' => 'tanggal',
        'lokasi' => 'lokasi',
        'location' => 'lokasi',
        'kecepatan_angin' => 'kecepatan_angin',
        'kecepatan' => 'kecepatan_angin',
        'wind_speed' => 'kecepatan_angin',
        'arah_angin' => 'arah_angin',
        'arah' => 'arah_angin',
        'wind_direction' => 'arah_angin',
        'keterangan' => 'keterangan',
        'notes' => 'keterangan',
        // Harga Komoditas mappings
        'jenis_komoditas' => 'jenis_komoditas',
        'komoditas' => 'jenis_komoditas',
        'commodity' => 'jenis_komoditas',
        'harga' => 'harga',
        'price' => 'harga',
        'satuan' => 'satuan',
        'unit' => 'satuan',
        // BPS Data Pertanian mappings
        'tahun' => 'tahun',
        'year' => 'tahun',
        'kabupaten_kota' => 'kabupaten_kota',
        'kabupaten' => 'kabupaten_kota',
        'kota' => 'kabupaten_kota',
        'regency' => 'kabupaten_kota',
        'luas_panen' => 'luas_panen',
        'luas' => 'luas_panen',
        'harvest_area' => 'luas_panen',
        'produksi_gabah' => 'produksi_gabah',
        'gabah' => 'produksi_gabah',
        'produksi_beras' => 'produksi_beras',
        'beras' => 'produksi_beras',
        'produktivitas' => 'produktivitas',
        'productivity' => 'produktivitas',
        // Evaluasi Akurasi mappings
        'periode_bulan' => 'periode_bulan',
        'bulan' => 'periode_bulan',
        'periode_tahun' => 'periode_tahun',
        'nama_wilayah' => 'nama_wilayah',
        'wilayah' => 'nama_wilayah',
        'luas_estimasi_daerah' => 'luas_estimasi_daerah',
        'estimasi' => 'luas_estimasi_daerah',
        'luas_estimasi' => 'luas_estimasi_daerah',
        'luas_rilis_bps' => 'luas_rilis_bps',
        'rilis' => 'luas_rilis_bps',
        'rilis_bps' => 'luas_rilis_bps',
        'catatan_analisis' => 'catatan_analisis',
        'catatan' => 'catatan_analisis'
    ];
    
    // Valid commodity types
    private $validKomoditas = [
        'gabah_kering_panen', 'gabah_kering_giling',
        'beras_premium', 'beras_medium'
    ];
    
    // Required fields for import
    private $requiredFields = ['tanggal', 'kecepatan_angin'];
    
    /**
     * Import data from Excel file
     * 
     * @param string $filePath Path to the uploaded Excel file
     * @param string $dataType Type of data to import (kecepatan_angin, curah_hujan, harga_komoditas)
     * @return array Import result summary
     */
    public function import($filePath, $dataType = 'kecepatan_angin') {
        $this->resetCounters();
        
        try {
            // Validate file exists
            if (!file_exists($filePath)) {
                throw new Exception('File tidak ditemukan');
            }
            
            // Get file extension
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($extension, ['xlsx', 'csv'], true)) {
                throw new Exception('Format file tidak didukung. Gunakan xlsx atau csv');
            }
            
            // Parse the file
            $data = $this->parseFile($filePath, $extension);
            
            if (empty($data)) {
                throw new Exception('File tidak memiliki data yang valid');
            }
            
            // Process data based on type
            switch ($dataType) {
                case 'kecepatan_angin':
                    return $this->processKecepatanAnginData($data);
                case 'curah_hujan':
                    return $this->processCurahHujanData($data);
                case 'harga_komoditas':
                    return $this->processHargaKomoditasData($data);
                case 'data_pertanian_bps':
                    return $this->processBpsData($data);
                case 'evaluasi_akurasi':
                    return $this->processEvaluasiData($data);
                default:
                    throw new Exception('Tipe data tidak dikenal: ' . $dataType);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'successCount' => 0,
                'failedCount' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }
    
    /**
     * Generate preview data from Excel file
     * 
     * @param string $filePath Path to the uploaded Excel file
     * @param int $limit Number of rows to preview
     * @return array Preview data with headers and rows
     */
    public function generatePreview($filePath, $limit = 10) {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('File tidak ditemukan');
            }
            
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            if (!in_array($extension, ['xlsx', 'csv'], true)) {
                throw new Exception('Format file tidak didukung');
            }
            
            $data = $this->parseFile($filePath, $extension);
            
            if (empty($data)) {
                throw new Exception('File tidak memiliki data');
            }
            
            // Get headers from first row
            $headers = array_keys($data[0]);
            
            // Limit preview rows
            $previewData = array_slice($data, 0, $limit);
            
            return [
                'success' => true,
                'headers' => $headers,
                'data' => $previewData,
                'totalRows' => count($data),
                'previewRows' => count($previewData)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Parse Excel/CSV file into array
     */
    private function parseFile($filePath, $extension) {
        $data = [];
        
        if ($extension === 'csv') {
            $data = $this->parseCsv($filePath);
        } else {
            // XLSX adalah arsip ZIP/XML; format XLS biner sengaja tidak diterima.
            $data = $this->parseXlsx($filePath);
        }
        
        return $data;
    }
    
    /**
     * Parse CSV file
     */
    private function parseCsv($filePath) {
        $data = [];
        $headers = [];
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $row = 0;
            while (($rowData = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($row === 0) {
                    // First row is headers
                    $headers = array_map('strtolower', array_map('trim', $rowData));
                } else {
                    $item = [];
                    foreach ($headers as $index => $header) {
                        $item[$header] = $rowData[$index] ?? '';
                    }
                    $data[] = $item;
                }
                $row++;
            }
            fclose($handle);
        }
        
        return $data;
    }
    
    /**
     * Parse XLSX file using simple XML
     * This is a lightweight parser without external dependencies
     */
    private function parseXlsx($filePath) {
        $data = [];
        
        // Check if file is a valid ZIP (xlsx is a ZIP file)
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            throw new Exception('File Excel tidak dapat dibuka. Pastikan file tidak rusak.');
        }
        
        // Read shared strings (for string values)
        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $xml = simplexml_load_string($sharedStringsXml);
            foreach ($xml->si as $si) {
                $sharedStrings[] = (string)$si->t;
            }
        }
        
        // Read the first worksheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) {
            $zip->close();
            throw new Exception('Worksheet tidak ditemukan dalam file Excel');
        }
        
        $xml = simplexml_load_string($sheetXml);
        $rows = [];
        
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $value = '';
                $cellType = (string)$cell['t'];
                
                if ($cellType === 's') {
                    // Shared string
                    $stringIndex = (int)$cell->v;
                    $value = $sharedStrings[$stringIndex] ?? '';
                } else {
                    // Direct value
                    $value = (string)$cell->v;
                }
                
                // Get column letter from cell reference (e.g., A1 -> A)
                $cellRef = (string)$cell['r'];
                preg_match('/([A-Z]+)/', $cellRef, $matches);
                $colLetter = $matches[1];
                $colIndex = $this->columnLetterToIndex($colLetter);
                
                $rowData[$colIndex] = $value;
            }
            $rows[] = $rowData;
        }
        
        $zip->close();
        
        // Convert to associative array with headers
        if (count($rows) < 2) {
            throw new Exception('File Excel harus memiliki minimal header dan satu baris data');
        }
        
        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        
        for ($i = 1; $i < count($rows); $i++) {
            $item = [];
            foreach ($headers as $colIndex => $header) {
                if (!empty($header)) {
                    $item[$header] = $rows[$i][$colIndex] ?? '';
                }
            }
            if (!empty(array_filter($item))) {
                $data[] = $item;
            }
        }
        
        return $data;
    }
    
    /**
     * Convert column letter to index (A=0, B=1, etc)
     */
    private function columnLetterToIndex($letter) {
        $letter = strtoupper($letter);
        $index = 0;
        for ($i = 0; $i < strlen($letter); $i++) {
            $index = $index * 26 + (ord($letter[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
    
    /**
     * Process Kecepatan Angin data
     */
    private function processKecepatanAnginData($data) {
        require_once ROOT_PATH . '/app/models/KecepatanAngin.php';
        $model = new KecepatanAngin();
        
        $processedRows = [];
        
        foreach ($data as $rowIndex => $row) {
            $rowNum = $rowIndex + 2; // +2 because 0-indexed and header is row 1
            
            try {
                // Map columns
                $mappedData = $this->mapColumns($row);
                
                // Validate required fields
                $this->validateRequiredFields($mappedData, $rowNum);
                
                // Validate and format data
                $validatedData = $this->validateKecepatanAnginRow($mappedData, $rowNum);
                
                // Prepare data for insert
                $insertData = [
                    'tanggal' => $validatedData['tanggal'],
                    'lokasi' => $validatedData['lokasi'] ?? 'Jember',
                    'kode_wilayah' => $validatedData['kode_wilayah'] ?? '35.09',
                    'kecepatan_angin' => $validatedData['kecepatan_angin'],
                    'kecepatan_max' => $validatedData['kecepatan_max'] ?? null,
                    'arah_angin' => $validatedData['arah_angin'] ?? null,
                    'arah_angin_desc' => $validatedData['arah_angin_desc'] ?? null,
                    'satuan' => 'km/h',
                    'sumber_data' => 'Import Excel',
                    'keterangan' => $validatedData['keterangan'] ?? null
                ];
                
                // Insert to database
                $result = $model->insert($insertData);
                
                if ($result) {
                    $this->successCount++;
                    $processedRows[] = $insertData;
                } else {
                    throw new Exception('Gagal menyimpan ke database');
                }
                
            } catch (Exception $e) {
                $this->failedCount++;
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }
        
        // Log the import activity
        if ($this->successCount > 0) {
            $model->logActivity('import_excel', 'success', 'Import data dari Excel', [
                'processed' => count($data),
                'success' => $this->successCount,
                'failed' => $this->failedCount
            ]);
        }
        
        return [
            'success' => $this->successCount > 0,
            'successCount' => $this->successCount,
            'failedCount' => $this->failedCount,
            'totalProcessed' => count($data),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    /**
     * Process Curah Hujan data (for future use)
     */
    private function processCurahHujanData($data) {
        // Similar implementation for curah hujan
        return [
            'success' => false,
            'error' => 'Curah hujan import belum diimplementasi'
        ];
    }
    
    /**
     * Process Harga Komoditas data
     */
    private function processHargaKomoditasData($data) {
        require_once ROOT_PATH . '/app/models/HargaKomoditas.php';
        $model = new HargaKomoditas();
        
        $processedRows = [];
        $affectedCommodities = [];
        
        foreach ($data as $rowIndex => $row) {
            $rowNum = $rowIndex + 2; // +2 because 0-indexed and header is row 1
            
            try {
                // Map columns
                $mappedData = $this->mapColumns($row);
                
                // Validate required fields for harga komoditas
                if (empty($mappedData['tanggal'])) {
                    throw new Exception("Field 'tanggal' wajib diisi");
                }
                if (empty($mappedData['jenis_komoditas'])) {
                    throw new Exception("Field 'jenis_komoditas' wajib diisi");
                }
                if (!isset($mappedData['harga']) || $mappedData['harga'] === '') {
                    throw new Exception("Field 'harga' wajib diisi");
                }
                
                // Validate and format data
                $validatedData = $this->validateHargaKomoditasRow($mappedData, $rowNum);
                
                // Prepare data for insert
                $insertData = [
                    'tanggal' => $validatedData['tanggal'],
                    'jenis_komoditas' => $validatedData['jenis_komoditas'],
                    'harga' => $validatedData['harga'],
                    'satuan' => $validatedData['satuan'] ?? 'Rp/kg',
                    'lokasi' => $validatedData['lokasi'] ?? 'Jember',
                    'kode_wilayah' => '35.09',
                    'sumber_data' => 'Import Excel',
                    'metode_data' => 'manual',
                    'keterangan' => $validatedData['keterangan'] ?? null
                ];
                
                // Upsert mencegah import file yang sama menggandakan observasi.
                $result = $model->upsert($insertData, false);
                
                if ($result) {
                    $this->successCount++;
                    $processedRows[] = $insertData;
                    $affectedCommodities[$insertData['jenis_komoditas']] = true;
                } else {
                    throw new Exception('Gagal menyimpan ke database');
                }
                
            } catch (Exception $e) {
                $this->failedCount++;
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }

        foreach (array_keys($affectedCommodities) as $commodity) {
            $model->rebuildAlerts($commodity);
        }
        
        // Log the import activity
        if ($this->successCount > 0) {
            $model->logActivity('import_excel', 'success', 'Import data harga komoditas dari Excel', [
                'processed' => count($data),
                'success' => $this->successCount,
                'failed' => $this->failedCount
            ]);
        }
        
        return [
            'success' => $this->successCount > 0,
            'successCount' => $this->successCount,
            'failedCount' => $this->failedCount,
            'totalProcessed' => count($data),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    /**
     * Validate and format Harga Komoditas row data
     */
    private function validateHargaKomoditasRow($data, $rowNum) {
        $validated = [];
        
        // Validate and format tanggal
        $tanggal = $this->parseDate($data['tanggal']);
        if (!$tanggal) {
            throw new Exception("Format tanggal tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY)");
        }
        $validated['tanggal'] = $tanggal;
        
        // Validate jenis komoditas
        $komoditas = strtolower(trim($data['jenis_komoditas']));
        // Normalize common variations
        $komoditasMap = [
            'gkp' => 'gabah_kering_panen',
            'gabah kering panen' => 'gabah_kering_panen',
            'gkg' => 'gabah_kering_giling',
            'gabah kering giling' => 'gabah_kering_giling',
            'beras premium' => 'beras_premium',
            'premium' => 'beras_premium',
            'beras medium' => 'beras_medium',
            'medium' => 'beras_medium'
        ];
        
        if (isset($komoditasMap[$komoditas])) {
            $komoditas = $komoditasMap[$komoditas];
        }
        
        if (!in_array($komoditas, $this->validKomoditas)) {
            throw new Exception("Jenis komoditas tidak valid. Gunakan: " . implode(', ', $this->validKomoditas));
        }
        $validated['jenis_komoditas'] = $komoditas;
        
        // Validate harga (positive number)
        $harga = $this->normalizeNumber((string) $data['harga']);
        if ($harga === null || $harga <= 0) {
            throw new Exception("Harga harus lebih dari 0");
        }
        if ($harga > 100000) {
            throw new Exception("Harga melebihi batas Rp100.000 per kg");
        }
        $validated['harga'] = $harga;
        
        // Optional fields
        $validated['satuan'] = $data['satuan'] ?? 'Rp/kg';
        $validated['lokasi'] = $data['lokasi'] ?? 'Jember';
        $validated['keterangan'] = $data['keterangan'] ?? null;
        
        return $validated;
    }
    
    /**
     * Process BPS Data Pertanian
     */
    private function processBpsData($data) {
        require_once ROOT_PATH . '/app/models/DataPertanianBps.php';
        require_once ROOT_PATH . '/app/services/BpsDataService.php';
        $model = new DataPertanianBps();
        $dataService = new BpsDataService();
        
        $processedRows = [];
        
        foreach ($data as $rowIndex => $row) {
            $rowNum = $rowIndex + 2;
            
            try {
                // Map columns
                $mappedData = $this->mapColumns($row);
                
                // Validate required fields
                if (empty($mappedData['tahun'])) {
                    throw new Exception("Field 'tahun' wajib diisi");
                }
                if (empty($mappedData['kabupaten_kota'])) {
                    throw new Exception("Field 'kabupaten_kota' wajib diisi");
                }
                if (!isset($mappedData['luas_panen']) || $mappedData['luas_panen'] === '') {
                    throw new Exception("Field 'luas_panen' wajib diisi");
                }
                if (!isset($mappedData['produksi_gabah']) || $mappedData['produksi_gabah'] === '') {
                    throw new Exception("Field 'produksi_gabah' wajib diisi");
                }
                
                // Validate and format data
                $validatedData = $this->validateBpsRow($mappedData, $rowNum);
                $validation = $dataService->validateRecord($validatedData);
                if (!$validation['valid']) {
                    throw new Exception(implode('; ', $validation['issues']));
                }
                
                // Prepare data for insert
                $insertData = [
                    'tahun' => $validatedData['tahun'],
                    'kabupaten_kota' => $validatedData['kabupaten_kota'],
                    'kode_wilayah' => $validatedData['kode_wilayah'] ?? null,
                    'luas_panen' => $validatedData['luas_panen'],
                    'produksi_gabah' => $validatedData['produksi_gabah'],
                    'produksi_beras' => $validatedData['produksi_beras'],
                    'produktivitas' => $validatedData['produktivitas'],
                    'sumber_data' => 'Import Excel',
                    'sumber_data_type' => 'manual',
                    'tipe_skenario' => 'baseline',
                    'is_validated' => 1,
                    'validation_notes' => null,
                    'keterangan' => $validatedData['keterangan'] ?? null
                ];
                
                // Use upsert to handle duplicates
                $result = $model->upsert($insertData);
                
                if ($result) {
                    $this->successCount++;
                    $processedRows[] = $insertData;
                } else {
                    throw new Exception('Gagal menyimpan ke database');
                }
                
            } catch (Exception $e) {
                $this->failedCount++;
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }
        
        // Log the import activity
        if ($this->successCount > 0) {
            $model->logActivity('import_excel', 'success', 'Import data pertanian BPS dari Excel', [
                'processed' => count($data),
                'success' => $this->successCount,
                'failed' => $this->failedCount
            ]);
        }
        
        return [
            'success' => $this->successCount > 0,
            'successCount' => $this->successCount,
            'failedCount' => $this->failedCount,
            'totalProcessed' => count($data),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    /**
     * Validate and format BPS row data
     */
    private function validateBpsRow($data, $rowNum) {
        $validated = [];
        
        // Validate tahun (4-digit year, rentang dinamis 2000 s/d tahun depan)
        $tahunRaw = trim((string)($data['tahun'] ?? ''));
        $tahunMin = 2000;
        $tahunMax = (int)date('Y') + 1;
        $tahun = intval($tahunRaw);
        if ($tahun < $tahunMin || $tahun > $tahunMax) {
            throw new Exception("Tahun harus antara {$tahunMin}-{$tahunMax}");
        }
        $validated['tahun'] = $tahun;
        
        // Validate kabupaten_kota (normalisasi ke nama standar 38 kab/kota Jatim)
        $kabupaten = $this->normalizeKabupatenName((string)($data['kabupaten_kota'] ?? ''));
        if (strlen($kabupaten) < 3) {
            throw new Exception("Nama kabupaten/kota terlalu pendek");
        }
        $validated['kabupaten_kota'] = $kabupaten;
        
        // Normalisasi kode wilayah BPS: "35.09" / "3509" -> "3509"
        $kodeWilayah = $this->normalizeNumber((string)($data['kode_wilayah'] ?? ''));
        $validated['kode_wilayah'] = $kodeWilayah !== null ? (string)(int)$kodeWilayah : null;
        
        // Validate luas_panen (positive number)
        $luasPanen = $this->normalizeNumber((string)($data['luas_panen'] ?? ''));
        if ($luasPanen === null) {
            throw new Exception("Luas panen tidak valid");
        }
        if ($luasPanen < 0) {
            throw new Exception("Luas panen tidak boleh negatif");
        }
        if ($luasPanen > 500000) {
            $this->warnings[] = "Baris {$rowNum}: Luas panen terlalu besar ({$luasPanen} ha) - cek nilai";
        }
        $validated['luas_panen'] = $luasPanen;
        
        // Validate produksi_gabah (positive number)
        $produksiGabah = $this->normalizeNumber((string)($data['produksi_gabah'] ?? ''));
        if ($produksiGabah === null) {
            throw new Exception("Produksi gabah tidak valid");
        }
        if ($produksiGabah < 0) {
            throw new Exception("Produksi gabah tidak boleh negatif");
        }
        if ($produksiGabah > 5000000) {
            $this->warnings[] = "Baris {$rowNum}: Produksi gabah terlalu besar ({$produksiGabah} ton) - cek nilai";
        }
        $validated['produksi_gabah'] = $produksiGabah;
        
        // Calculate produksi_beras if not provided (57.7% of gabah)
        $produksiBeras = $this->normalizeNumber((string)($data['produksi_beras'] ?? ''));
        if ($produksiBeras !== null) {
            if ($produksiBeras < 0) {
                throw new Exception("Produksi beras tidak boleh negatif");
            }
            if ($produksiGabah > 0 && $produksiBeras > $produksiGabah) {
                throw new Exception(
                    "Produksi beras ({$produksiBeras} ton) tidak boleh melebihi produksi gabah ({$produksiGabah} ton)"
                );
            }
            $validated['produksi_beras'] = $produksiBeras;
        } else {
            $validated['produksi_beras'] = round($produksiGabah * 0.577, 2);
        }
        
        // Calculate produktivitas if not provided (gabah / luas_panen * 10)
        $produktivitas = $this->normalizeNumber((string)($data['produktivitas'] ?? ''));
        if ($produktivitas !== null) {
            if ($produktivitas < 0) {
                throw new Exception("Produktivitas tidak boleh negatif");
            }
            $validated['produktivitas'] = $produktivitas;
        } else {
            $validated['produktivitas'] = $luasPanen > 0 ? round(($produksiGabah / $luasPanen) * 10, 2) : 0;
        }
        
        $validated['keterangan'] = $data['keterangan'] ?? null;
        
        return $validated;
    }
    
    /**
     * Normalisasi angka Indonesia:
     * - "1.234,56" -> 1234.56 (ribuan + desimal koma)
     * - "1,234.56" -> 1234.56 (western)
     * - "1.234"    -> 1234 (titik ribuan)
     * - "12,5"     -> 12.5 (desimal koma)
     * Mengembalikan float atau null bila tidak valid/kosong.
     */
    private function normalizeNumber($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        
        $value = preg_replace('/^rp\.?\s*/i', '', $value);
        $value = preg_replace('/\s*\/\s*kg\s*$/i', '', $value);
        // Hapus satuan yang menempel (mis. "1.234 ha", "12,5 ku/ha")
        $value = preg_replace('/\s*(ha|ton|ku|ku\/ha|gkg|gkp)\s*$/i', '', $value);
        $value = str_replace(' ', '', $value);
        
        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            // Kedua separator: koma terakhir = desimal (format Indonesia)
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (strpos($value, ',') !== false) {
            // Hanya koma -> desimal
            $value = str_replace(',', '.', $value);
        } elseif (strpos($value, '.') !== false) {
            // Hanya titik: ribuan bila diikuti tepat 3 digit
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
                $value = str_replace('.', '', $value);
            }
        }
        
        if (!is_numeric($value)) {
            return null;
        }
        
        return (float)$value;
    }
    
    /**
     * Normalisasi nama kabupaten/kota ke daftar standar 38 kab/kota Jawa Timur.
     * - Hapus prefix "Kab.", "Kabupaten", "Prop." (prefix "Kota" dipertahankan)
     * - Title case
     * - "Surabaya"/"Batu" tanpa prefix -> "Kota Surabaya"/"Kota Batu"
     * Nama yang tidak dikenal dikembalikan apa adanya (title case) agar
     * tidak merusak data wilayah lain.
     */
    private function normalizeKabupatenName($value) {
        $clean = trim((string)$value);
        if ($clean === '') {
            return '';
        }
        
        $clean = preg_replace('/\s+/u', ' ', $clean);
        $clean = preg_replace('/^(kab\.?|kabupaten|prop\.?|provinsi|pemkab|pemkot)\s+/i', '', $clean);
        $clean = preg_replace('/\(.*?\)/', '', $clean);
        $clean = trim($clean);
        
        // Normalisasi: "35.09 JEMBER" -> "Jember", "JEMBER-3509" -> "Jember"
        $clean = preg_replace('/^\d{2}\.?\d{2}\s*/', '', $clean);
        $clean = preg_replace('/\s*-?\s*\d{4}$/', '', $clean);
        $clean = preg_replace('/[\.\s]+/u', ' ', trim($clean));
        
        $candidate = $this->titleCase($clean);
        if ($candidate === '') {
            return '';
        }
        
        $standar = [
            'Pacitan', 'Ponorogo', 'Trenggalek', 'Tulungagung', 'Blitar', 'Kediri',
            'Malang', 'Lumajang', 'Jember', 'Banyuwangi', 'Bondowoso', 'Situbondo',
            'Probolinggo', 'Pasuruan', 'Sidoarjo', 'Mojokerto', 'Jombang', 'Nganjuk',
            'Madiun', 'Magetan', 'Ngawi', 'Bojonegoro', 'Tuban', 'Lamongan', 'Gresik',
            'Bangkalan', 'Sampang', 'Pamekasan', 'Sumenep', 'Kota Kediri',
            'Kota Blitar', 'Kota Malang', 'Kota Probolinggo', 'Kota Pasuruan',
            'Kota Mojokerto', 'Kota Madiun', 'Kota Surabaya', 'Kota Batu',
        ];
        
        if (in_array($candidate, $standar, true)) {
            return $candidate;
        }
        
        // Nama kota tanpa prefix yang tidak ambigu (tidak ada kabupaten bernama sama)
        if (in_array($candidate, ['Surabaya', 'Batu'], true)) {
            $asKota = 'Kota ' . $candidate;
            if (in_array($asKota, $standar, true)) {
                return $asKota;
            }
        }
        
        return $candidate;
    }
    
    /**
     * Title case: huruf pertama tiap kata kapital.
     */
    private function titleCase($value) {
        $words = preg_split('/\s+/', trim($value));
        $result = [];
        foreach ($words as $word) {
            $lower = strtolower($word);
            $result[] = strtoupper(substr($lower, 0, 1)) . substr($lower, 1);
        }
        return implode(' ', $result);
    }
    
    /**
     * Process evaluasi akurasi data
     */
    private function processEvaluasiData($data) {
        require_once ROOT_PATH . '/app/models/EvaluasiAkurasi.php';
        $model = new EvaluasiAkurasi();
        
        $this->successCount = 0;
        $this->failedCount = 0;
        $this->errors = [];
        $this->warnings = [];
        
        $rowNum = 1;
        foreach ($data as $row) {
            $rowNum++;
            
            try {
                // Map columns
                $mappedRow = $this->mapColumns($row);
                
                // Validate required fields
                if (empty($mappedRow['periode_bulan']) || empty($mappedRow['periode_tahun']) || empty($mappedRow['nama_wilayah'])) {
                    throw new Exception('Kolom periode_bulan, periode_tahun, dan nama_wilayah wajib diisi');
                }
                
                // Validate and format
                $validatedData = $this->validateEvaluasiRow($mappedRow, $rowNum);
                
                // Prepare insert data
                $insertData = [
                    'periode_bulan' => $validatedData['periode_bulan'],
                    'periode_tahun' => $validatedData['periode_tahun'],
                    'nama_wilayah' => $validatedData['nama_wilayah'],
                    'wilayah_id' => $validatedData['wilayah_id'] ?? null,
                    'luas_estimasi_daerah' => $validatedData['luas_estimasi_daerah'] ?? 0,
                    'luas_rilis_bps' => $validatedData['luas_rilis_bps'] ?? null,
                    'catatan_analisis' => $validatedData['catatan_analisis'] ?? null
                ];
                
                // Insert record
                $result = $model->insert($insertData);
                
                if ($result['success']) {
                    $this->successCount++;
                } else {
                    throw new Exception($result['message']);
                }
                
            } catch (Exception $e) {
                $this->failedCount++;
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => $this->successCount > 0,
            'successCount' => $this->successCount,
            'failedCount' => $this->failedCount,
            'totalProcessed' => count($data),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    /**
     * Validate and format evaluasi row data
     */
    private function validateEvaluasiRow($data, $rowNum) {
        $validated = [];
        
        // Validate periode_bulan (1-12)
        $bulan = intval($data['periode_bulan']);
        if ($bulan < 1 || $bulan > 12) {
            throw new Exception("Bulan harus antara 1-12");
        }
        $validated['periode_bulan'] = $bulan;
        
        // Validate periode_tahun (4-digit year)
        $tahun = intval($data['periode_tahun']);
        if ($tahun < 2019 || $tahun > 2030) {
            throw new Exception("Tahun harus antara 2019-2030");
        }
        $validated['periode_tahun'] = $tahun;
        
        // Validate nama_wilayah
        $wilayah = trim($data['nama_wilayah']);
        if (strlen($wilayah) < 3) {
            throw new Exception("Nama wilayah terlalu pendek");
        }
        $validated['nama_wilayah'] = $wilayah;
        $validated['wilayah_id'] = $data['wilayah_id'] ?? null;
        
        // Validate luas_estimasi_daerah (positive number)
        $estimasi = floatval(str_replace(['.', ','], ['', '.'], $data['luas_estimasi_daerah'] ?? 0));
        if ($estimasi < 0) {
            throw new Exception("Luas estimasi tidak boleh negatif");
        }
        $validated['luas_estimasi_daerah'] = $estimasi;
        
        // Validate luas_rilis_bps (optional, positive number)
        if (!empty($data['luas_rilis_bps'])) {
            $rilis = floatval(str_replace(['.', ','], ['', '.'], $data['luas_rilis_bps']));
            if ($rilis < 0) {
                throw new Exception("Luas rilis BPS tidak boleh negatif");
            }
            $validated['luas_rilis_bps'] = $rilis;
        } else {
            $validated['luas_rilis_bps'] = null;
        }
        
        $validated['catatan_analisis'] = $data['catatan_analisis'] ?? null;
        
        return $validated;
    }
    
    /**
     * Map Excel columns to database fields
     */
    private function mapColumns($row) {
        $mapped = [];
        
        foreach ($row as $key => $value) {
            $key = strtolower(trim($key));
            if (isset($this->columnMapping[$key])) {
                $mapped[$this->columnMapping[$key]] = trim($value);
            } else {
                $mapped[$key] = trim($value);
            }
        }
        
        return $mapped;
    }
    
    /**
     * Validate required fields
     */
    private function validateRequiredFields($data, $rowNum) {
        foreach ($this->requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '{$field}' wajib diisi");
            }
        }
    }
    
    /**
     * Validate and format Kecepatan Angin row data
     */
    private function validateKecepatanAnginRow($data, $rowNum) {
        $validated = [];
        
        // Validate and format tanggal
        $tanggal = $this->parseDate($data['tanggal']);
        if (!$tanggal) {
            throw new Exception("Format tanggal tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY)");
        }
        $validated['tanggal'] = $tanggal;
        
        // Validate kecepatan angin (0-200 km/h)
        $kecepatan = floatval($data['kecepatan_angin']);
        if ($kecepatan < 0 || $kecepatan > 200) {
            throw new Exception("Kecepatan angin harus antara 0-200 km/h (nilai: {$kecepatan})");
        }
        $validated['kecepatan_angin'] = $kecepatan;
        
        // Optional: kecepatan max
        if (!empty($data['kecepatan_max'])) {
            $kecepatanMax = floatval($data['kecepatan_max']);
            if ($kecepatanMax < 0 || $kecepatanMax > 300) {
                $this->warnings[] = "Baris {$rowNum}: Kecepatan max di luar rentang normal";
            }
            $validated['kecepatan_max'] = $kecepatanMax;
        }
        
        // Optional: arah angin (0-360 degrees)
        if (!empty($data['arah_angin'])) {
            $arah = floatval($data['arah_angin']);
            if ($arah < 0 || $arah > 360) {
                throw new Exception("Arah angin harus antara 0-360 derajat");
            }
            $validated['arah_angin'] = $arah;
            $validated['arah_angin_desc'] = $this->getWindDirectionDesc($arah);
        }
        
        // Optional: lokasi
        $validated['lokasi'] = $data['lokasi'] ?? 'Jember';
        
        // Optional: keterangan
        $validated['keterangan'] = $data['keterangan'] ?? null;
        
        return $validated;
    }
    
    /**
     * Parse various date formats
     */
    private function parseDate($dateString) {
        if (empty($dateString)) {
            return null;
        }
        
        // Try different date formats
        $formats = [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'm/d/Y',
            'd F Y',
            'Y/m/d'
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        // Try strtotime as fallback
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        // Check if it's an Excel date serial number
        if (is_numeric($dateString)) {
            $excelDate = intval($dateString);
            if ($excelDate > 25569 && $excelDate < 50000) {
                // Excel date serial (days since 1900-01-01)
                $unixDate = ($excelDate - 25569) * 86400;
                return date('Y-m-d', $unixDate);
            }
        }
        
        return null;
    }
    
    /**
     * Get wind direction description from degrees
     */
    private function getWindDirectionDesc($degrees) {
        $directions = [
            'Utara', 'Timur Laut', 'Timur', 'Tenggara',
            'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'
        ];
        
        $index = round($degrees / 45) % 8;
        return $directions[$index];
    }
    
    /**
     * Reset counters
     */
    private function resetCounters() {
        $this->errors = [];
        $this->warnings = [];
        $this->successCount = 0;
        $this->failedCount = 0;
    }
    
    /**
     * Generate import template
     */
    public function generateTemplate($type = 'kecepatan_angin') {
        $headers = [];
        $sampleData = [];
        
        switch ($type) {
            case 'kecepatan_angin':
                $headers = ['tanggal', 'kecepatan_angin', 'arah_angin', 'lokasi', 'keterangan'];
                $sampleData = [
                    ['2025-01-01', '12.5', '180', 'Jember', 'Contoh data'],
                    ['2025-01-02', '15.3', '270', 'Jember', '']
                ];
                break;
            case 'curah_hujan':
                $headers = ['tanggal', 'curah_hujan', 'lokasi', 'keterangan'];
                $sampleData = [
                    ['2025-01-01', '25.5', 'Jember', 'Contoh data'],
                    ['2025-01-02', '10.0', 'Jember', '']
                ];
                break;
            case 'harga_komoditas':
                $headers = ['tanggal', 'jenis_komoditas', 'harga', 'satuan', 'lokasi', 'keterangan'];
                $sampleData = [
                    ['2025-01-01', 'gabah_kering_panen', '5500', 'Rp/kg', 'Jember', 'Contoh data GKP'],
                    ['2025-01-01', 'beras_premium', '12500', 'Rp/kg', 'Jember', 'Contoh data beras'],
                    ['2025-01-02', 'gabah_kering_giling', '5800', 'Rp/kg', 'Jember', '']
                ];
                break;
            case 'data_pertanian_bps':
                $headers = ['tahun', 'kabupaten_kota', 'luas_panen', 'produksi_gabah', 'produksi_beras', 'produktivitas', 'keterangan'];
                $sampleData = [
                    ['2025', 'Kab. Jember', '150000', '850000', '490450', '56.67', 'Contoh data Jember'],
                    ['2025', 'Kab. Banyuwangi', '120000', '680000', '392360', '56.67', ''],
                    ['2025', 'Kota Surabaya', '5000', '28000', '16156', '56.00', '']
                ];
                break;
            case 'evaluasi_akurasi':
                $headers = ['periode_bulan', 'periode_tahun', 'nama_wilayah', 'luas_estimasi_daerah', 'luas_rilis_bps', 'catatan_analisis'];
                $sampleData = [
                    ['1', '2026', 'Kab. Jember', '150000', '148500', 'Contoh data Januari'],
                    ['1', '2026', 'Kab. Banyuwangi', '120000', '', ''],
                    ['2', '2026', 'Kab. Jember', '145000', '144200', '']
                ];
                break;
        }
        
        // Generate CSV content
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($sampleData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}
