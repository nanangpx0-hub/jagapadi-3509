#!/usr/bin/env python3
import re

filepath = r'C:\laragon\www\jagapadi-3509\app/services/DashboardDataAggregator.php'

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace getCache
old_getCache = """    private function getCache($key, $type = 'weather') {
        $file = $this->cacheDir . $key . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $ttl = $this->cacheTTL[$type] ?? 3600;
        
        if (filemtime($file) + $ttl < time()) {
            unlink($file);
            return null;
        }
        
        $data = file_get_contents($file);
        return json_decode($data, true);
    }
    
    /**
     * Set cache data
     */
    private function setCache($key, $data, $type = 'weather') {
        $file = $this->cacheDir . $key . '.json';
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Clear cache by type or all
     */
    public function clearCache($type = null) {
        $files = glob($this->cacheDir . '*.json');
        
        foreach ($files as $file) {
            if ($type === null) {
                unlink($file);
                continue;
            } elseif (strpos(basename($file), $type) === 0) {
                unlink($file);
            }
        }
    }
    
    // =========================================
    // EXPORT HELPERS
"""
    
new_getCache = """    private function getCache($key, $type = 'weather') {
        $file = $this->cacheDir . sha1($key) . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $ttl = $this->cacheTTL[$type] ?? 3600;
        
        if (filemtime($file) + $ttl < time()) {
            unlink($file);
            return null;
        }
        
        $data = file_get_contents($file);
        if ($data === false) {
            return null;
        }
        $payload = json_decode($data, true);
        return is_array($payload) ? ($payload['data'] ?? null) : null;
    }
    
    /**
     * Set cache data
     */
    private function setCache($key, $data, $type = 'weather') {
        $payload = ['type' => $type, 'key' => $key, 'data' => $data, 'created' => time()];
        $file = $this->cacheDir . sha1($key) . '.json';
        file_put_contents($file, json_encode($payload), LOCK_EX);
    }
    
    /**
     * Clear cache by type or all
     */
    public function clearCache($type = null) {
        $files = glob($this->cacheDir . '*.json') ?: [];
        
        foreach ($files as $file) {
            if ($type === null) {
                @unlink($file);
                continue;
            }
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $payload = json_decode($raw, true);
            if (is_array($payload) && ($payload['type'] ?? '') === $type) {
                @unlink($file);
            }
        }
    }
    
    // =========================================
    // EXPORT HELPERS
"""

if old_getCache in content:
    content = content.replace(old_getCache, new_getCache)
    print("getCache patched successfully")
else:
    print("getCache oldString not found")

# Replace setCache (already included in the combined block, but let's verify)
# The new block already includes setCache, so we just need to make sure it's replaced

# Replace cacheTTL additions
old_ttl = "'hama' => 1800,         // 30 minutes"
new_ttl = "'hama' => 1800,         // 30 minutes\n        'hama_stats' => 300,    // 5 menit (petugas bisa submit laporan baru)\n        'hama_map'   => 300,    // 5 menit (data peta tidak perlu real-time)"

if old_ttl in content:
    content = content.replace(old_ttl, new_ttl)
    print("TTL patched successfully")
else:
    print("TTL oldString not found")

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(content)

print("Done")