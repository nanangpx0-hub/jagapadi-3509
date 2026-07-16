<?php
/**
 * BPS Simulation Service
 * Service untuk generate data simulasi berbasis publikasi resmi BPS Jawa Timur
 * 
 * Memisahkan logika simulasi dari BpsScraper untuk separation of concerns
 * 
 * @version 2.0.0
 * @author JAGAPADI System
 */

class BpsSimulationService {
    
    // East Java province code
    private const PROV_CODE = '35';
    
    // Regency codes for East Java (BPS standard)
    private const KABUPATEN_CODES = [
        'Bangkalan' => '3526', 'Banyuwangi' => '3510', 'Blitar' => '3505',
        'Bojonegoro' => '3522', 'Bondowoso' => '3511', 'Gresik' => '3525',
        'Jember' => '3509', 'Jombang' => '3517', 'Kediri' => '3506',
        'Kota Batu' => '3579', 'Kota Blitar' => '3572', 'Kota Kediri' => '3571',
        'Kota Madiun' => '3577', 'Kota Malang' => '3573', 'Kota Mojokerto' => '3576',
        'Kota Pasuruan' => '3575', 'Kota Probolinggo' => '3574', 'Kota Surabaya' => '3578',
        'Lamongan' => '3524', 'Lumajang' => '3508', 'Madiun' => '3519',
        'Magetan' => '3520', 'Malang' => '3507', 'Mojokerto' => '3516',
        'Nganjuk' => '3518', 'Ngawi' => '3521', 'Pacitan' => '3501',
        'Pamekasan' => '3528', 'Pasuruan' => '3514', 'Ponorogo' => '3502',
        'Probolinggo' => '3513', 'Sampang' => '3527', 'Sidoarjo' => '3515',
        'Situbondo' => '3512', 'Sumenep' => '3529', 'Trenggalek' => '3503',
        'Tuban' => '3523', 'Tulungagung' => '3504'
    ];
    
    // Base production data (based on BPS 2024 Jawa Timur - 9.27 juta ton GKG)
    // Distribution percentages based on actual regional productivity
    private const BASE_PRODUCTION = [
        'Bangkalan' => ['luas' => 25000, 'produksi' => 140000, 'produktivitas' => 56],
        'Banyuwangi' => ['luas' => 85000, 'produksi' => 510000, 'produktivitas' => 60],
        'Blitar' => ['luas' => 42000, 'produksi' => 248000, 'produktivitas' => 59],
        'Bojonegoro' => ['luas' => 95000, 'produksi' => 560000, 'produktivitas' => 59],
        'Bondowoso' => ['luas' => 38000, 'produksi' => 220000, 'produktivitas' => 58],
        'Gresik' => ['luas' => 35000, 'produksi' => 195000, 'produktivitas' => 56],
        'Jember' => ['luas' => 120000, 'produksi' => 720000, 'produktivitas' => 60],
        'Jombang' => ['luas' => 48000, 'produksi' => 290000, 'produktivitas' => 60],
        'Kediri' => ['luas' => 52000, 'produksi' => 310000, 'produktivitas' => 60],
        'Kota Batu' => ['luas' => 1500, 'produksi' => 8500, 'produktivitas' => 57],
        'Kota Blitar' => ['luas' => 2000, 'produksi' => 11000, 'produktivitas' => 55],
        'Kota Kediri' => ['luas' => 1800, 'produksi' => 10000, 'produktivitas' => 56],
        'Kota Madiun' => ['luas' => 1200, 'produksi' => 6800, 'produktivitas' => 57],
        'Kota Malang' => ['luas' => 1000, 'produksi' => 5500, 'produktivitas' => 55],
        'Kota Mojokerto' => ['luas' => 800, 'produksi' => 4400, 'produktivitas' => 55],
        'Kota Pasuruan' => ['luas' => 600, 'produksi' => 3300, 'produktivitas' => 55],
        'Kota Probolinggo' => ['luas' => 1500, 'produksi' => 8200, 'produktivitas' => 55],
        'Kota Surabaya' => ['luas' => 500, 'produksi' => 2500, 'produktivitas' => 50],
        'Lamongan' => ['luas' => 105000, 'produksi' => 620000, 'produktivitas' => 59],
        'Lumajang' => ['luas' => 55000, 'produksi' => 330000, 'produktivitas' => 60],
        'Madiun' => ['luas' => 45000, 'produksi' => 265000, 'produktivitas' => 59],
        'Magetan' => ['luas' => 32000, 'produksi' => 188000, 'produktivitas' => 59],
        'Malang' => ['luas' => 68000, 'produksi' => 408000, 'produktivitas' => 60],
        'Mojokerto' => ['luas' => 38000, 'produksi' => 224000, 'produktivitas' => 59],
        'Nganjuk' => ['luas' => 62000, 'produksi' => 370000, 'produktivitas' => 60],
        'Ngawi' => ['luas' => 78000, 'produksi' => 468000, 'produktivitas' => 60],
        'Pacitan' => ['luas' => 28000, 'produksi' => 165000, 'produktivitas' => 59],
        'Pamekasan' => ['luas' => 22000, 'produksi' => 120000, 'produktivitas' => 55],
        'Pasuruan' => ['luas' => 58000, 'produksi' => 348000, 'produktivitas' => 60],
        'Ponorogo' => ['luas' => 48000, 'produksi' => 285000, 'produktivitas' => 59],
        'Probolinggo' => ['luas' => 52000, 'produksi' => 310000, 'produktivitas' => 60],
        'Sampang' => ['luas' => 28000, 'produksi' => 155000, 'produktivitas' => 55],
        'Sidoarjo' => ['luas' => 18000, 'produksi' => 100000, 'produktivitas' => 56],
        'Situbondo' => ['luas' => 35000, 'produksi' => 195000, 'produktivitas' => 56],
        'Sumenep' => ['luas' => 45000, 'produksi' => 250000, 'produktivitas' => 56],
        'Trenggalek' => ['luas' => 32000, 'produksi' => 190000, 'produktivitas' => 59],
        'Tuban' => ['luas' => 75000, 'produksi' => 440000, 'produktivitas' => 59],
        'Tulungagung' => ['luas' => 42000, 'produksi' => 250000, 'produktivitas' => 60]
    ];
    
    // Scenario adjustment factors
    private const SCENARIO_FACTORS = [
        'baseline' => ['luas' => 1.0, 'produksi' => 1.0],
        'optimis' => ['luas' => 1.05, 'produksi' => 1.08],
        'pesimis' => ['luas' => 0.95, 'produksi' => 0.92]
    ];
    
    /**
     * Generate simulated data for a single kabupaten
     * 
     * @param int $tahun Year to generate data for
     * @param string $kabupaten Kabupaten name
     * @param string $skenario Scenario type: baseline, optimis, pesimis
     * @return array Generated data record
     */
    public function generateData($tahun, $kabupaten, $skenario = 'baseline') {
        $baseData = self::BASE_PRODUCTION[$kabupaten] ?? [
            'luas' => 30000,
            'produksi' => 175000,
            'produktivitas' => 58
        ];
        
        $scenarioFactor = self::SCENARIO_FACTORS[$skenario] ?? self::SCENARIO_FACTORS['baseline'];
        
        // Apply year variation (±5% based on year difference from 2024)
        $yearDiff = $tahun - 2024;
        $yearFactor = 1 + ($yearDiff * rand(-30, 30) / 1000);
        
        // Apply random variation (±3%)
        $randomFactor = 1 + (rand(-30, 30) / 1000);
        
        // Calculate adjusted values
        $luasPanen = round($baseData['luas'] * $yearFactor * $randomFactor * $scenarioFactor['luas']);
        $produksiGabah = round($baseData['produksi'] * $yearFactor * $randomFactor * $scenarioFactor['produksi']);
        $produktivitas = round($baseData['produktivitas'] * (1 + (rand(-20, 20) / 1000)), 2);
        
        return [
            'tahun' => $tahun,
            'kabupaten_kota' => $kabupaten,
            'kode_wilayah' => self::KABUPATEN_CODES[$kabupaten] ?? null,
            'luas_panen' => $luasPanen,
            'produksi_gabah' => $produksiGabah,
            'produktivitas' => $produktivitas,
            'tipe_skenario' => $skenario,
            'sumber_data_type' => 'simulasi',
            'keterangan' => sprintf(
                'Simulasi %s berdasarkan publikasi BPS Jawa Timur 2024. Variasi tahun: %.1f%%, Random: %.1f%%',
                ucfirst($skenario),
                ($yearFactor - 1) * 100,
                ($randomFactor - 1) * 100
            )
        ];
    }
    
    /**
     * Generate data for all kabupaten
     * 
     * @param int $tahun Year to generate data for
     * @param string $skenario Scenario type
     * @return array Array of generated records
     */
    public function generateAllKabupaten($tahun, $skenario = 'baseline') {
        $results = [];
        
        foreach (array_keys(self::KABUPATEN_CODES) as $kabupaten) {
            $results[] = $this->generateData($tahun, $kabupaten, $skenario);
        }
        
        return $results;
    }
    
    /**
     * Get list of kabupaten with BPS codes
     * 
     * @return array
     */
    public function getKabupatenList() {
        return self::KABUPATEN_CODES;
    }
    
    /**
     * Get available scenarios
     * 
     * @return array
     */
    public function getAvailableScenarios() {
        return array_keys(self::SCENARIO_FACTORS);
    }
    
    /**
     * Get base production data for reference
     * 
     * @param string|null $kabupaten Optional specific kabupaten
     * @return array
     */
    public function getBaseProductionData($kabupaten = null) {
        if ($kabupaten) {
            return self::BASE_PRODUCTION[$kabupaten] ?? null;
        }
        return self::BASE_PRODUCTION;
    }
}
