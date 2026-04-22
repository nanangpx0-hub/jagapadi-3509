<?php
/**
 * Wilayah Controller
 * 
 * Controller untuk menangani data wilayah hierarkis (Kabupaten → Kecamatan → Desa)
 * Digunakan oleh cascading dropdown di halaman laporan/create
 * 
 * @version 1.1.0
 * @author JAGAPADI System
 */
class WilayahController extends Controller {
    private $kabModel;
    private $kecModel;
    private $desaModel;
    
    public function __construct() {
        $this->kabModel = $this->model('MasterKabupaten');
        $this->kecModel = $this->model('MasterKecamatan');
        $this->desaModel = $this->model('MasterDesa');
    }
    
    /**
     * Get all kabupaten
     * GET /wilayah/kabupaten
     */
    public function kabupaten() {
        try {
            $q = $_GET['q'] ?? null;
            $limit = $_GET['limit'] ?? 100;
            $data = $q ? $this->kabModel->search($q, $limit) : $this->kabModel->getAllOrdered();
            $this->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            error_log("WilayahController::kabupaten error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'Gagal mengambil data kabupaten', 'data' => []], 500);
        }
    }
    
    /**
     * Get kecamatan by kabupaten ID
     * GET /wilayah/kecamatan/{kabupaten_id}
     */
    public function kecamatan($kabupatenId = null) {
        $kabupatenId = $kabupatenId ?? ($_GET['kabupaten_id'] ?? null);
        
        if (!$kabupatenId) {
            $this->json(['status' => 'error', 'message' => 'kabupaten_id required', 'data' => []], 400);
            return;
        }
        
        try {
            $q = $_GET['q'] ?? null;
            $limit = $_GET['limit'] ?? 100;
            $data = $this->kecModel->getByKabupaten($kabupatenId, $q, $limit);
            
            if (empty($data)) {
                $this->json(['status' => 'success', 'message' => 'Data kecamatan tidak ditemukan untuk kabupaten ini', 'data' => []]);
                return;
            }
            
            $this->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            error_log("WilayahController::kecamatan error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'Gagal mengambil data kecamatan', 'data' => []], 500);
        }
    }
    
    /**
     * Get desa by kecamatan ID
     * GET /wilayah/desa/{kecamatan_id}
     */
    public function desa($kecamatanId = null) {
        $kecamatanId = $kecamatanId ?? ($_GET['kecamatan_id'] ?? null);
        
        if (!$kecamatanId) {
            $this->json(['status' => 'error', 'message' => 'kecamatan_id required', 'data' => []], 400);
            return;
        }
        
        try {
            $q = $_GET['q'] ?? null;
            $limit = $_GET['limit'] ?? 200;
            $data = $this->desaModel->getByKecamatan($kecamatanId, $q, $limit);
            
            if (empty($data)) {
                $this->json(['status' => 'success', 'message' => 'Data desa tidak ditemukan untuk kecamatan ini', 'data' => []]);
                return;
            }
            
            $this->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            error_log("WilayahController::desa error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'Gagal mengambil data desa', 'data' => []], 500);
        }
    }
}