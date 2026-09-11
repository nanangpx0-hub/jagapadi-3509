<?php
declare(strict_types=1);
namespace App\Core;

class FileQueue implements QueueInterface
{
    private string $baseDir;
    private const MAX_ATTEMPTS = 5;
    public function __construct(?string $baseDir = null) {
        $this->baseDir = $baseDir ?? dirname(__DIR__, 2) . '/storage/queue';
        if (!is_dir($this->baseDir)) @mkdir($this->baseDir, 0755, true);
    }
    private function qDir(string $q): string { $d=$this->baseDir.'/'.$q; if(!is_dir($d)) @mkdir($d,0755,true); return $d; }
    public function push(string $queue, array $payload, ?string $idempotencyKey = null, int $delaySeconds = 0): string {
        $id = $idempotencyKey ?? bin2hex(random_bytes(8));
        $file = $this->qDir($queue).'/'.$id.'.json';
        if ($idempotencyKey !== null && is_file($file)) return $id; // idempotent
        $job = ['id'=>$id,'payload'=>$payload,'attempts'=>0,'available_at'=>time()+$delaySeconds,'created_at'=>time()];
        file_put_contents($file, json_encode($job), LOCK_EX);
        return $id;
    }
    public function pop(string $queue): ?array {
        foreach (glob($this->qDir($queue).'/*.json') ?: [] as $f) {
            $fp=@fopen($f,'r+'); if(!$fp) continue;
            if(!flock($fp,LOCK_EX|LOCK_NB)){ fclose($fp); continue; }
            $data=json_decode(stream_get_contents($fp), true);
            if(!$data || $data['available_at']>time()){ flock($fp,LOCK_UN); fclose($fp); continue; }
            $data['attempts']++; ftruncate($fp,0); rewind($fp); fwrite($fp,json_encode($data)); fflush($fp);
            flock($fp,LOCK_UN); fclose($fp);
            return $data;
        }
        return null;
    }
    public function ack(string $queue, string $jobId): void { @unlink($this->qDir($queue).'/'.$jobId.'.json'); }
    public function retry(string $queue, string $jobId, string $reason): void {
        $f=$this->qDir($queue).'/'.$jobId.'.json'; if(!is_file($f)) return;
        $data=json_decode(file_get_contents($f), true);
        if($data['attempts'] >= self::MAX_ATTEMPTS){ $this->deadLetter($queue,$jobId,$reason); return; }
        $backoff = (int)(pow(2, $data['attempts']) * 1000000 + random_int(0,500000)); // jitter
        $data['available_at']=time()+ (int)($backoff/1000000);
        $data['last_error']=$reason;
        file_put_contents($f, json_encode($data), LOCK_EX);
    }
    public function deadLetter(string $queue, string $jobId, string $reason): void {
        $src=$this->qDir($queue).'/'.$jobId.'.json'; $dlq=$this->qDir($queue.'_dlq');
        if(!is_dir($dlq)) @mkdir($dlq,0755,true);
        if(is_file($src)){ $d=json_decode(file_get_contents($src), true); $d['dlq_reason']=$reason; file_put_contents($dlq.'/'.$jobId.'.json', json_encode($d), LOCK_EX); @unlink($src); }
    }
    public function size(string $queue): int { return count(glob($this->qDir($queue).'/*.json') ?: []); }
}
