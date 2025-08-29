<?php
declare(strict_types=1);

namespace OpenWishlist\Domain;

use PDO;
use RuntimeException;
use OpenWishlist\Support\Storage;

final class ImageProcessor
{
    public function __construct(
        private PDO $pdo,
        private array $settings // from Settings::load($pdo)
    ) {}

    /** Process one wish in local-image mode. Throws on failure. */
    public function processWish(int $wishId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wishes WHERE id=:id FOR UPDATE');
        $this->pdo->beginTransaction();
        $stmt->execute(['id'=>$wishId]);
        $wish = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wish) { $this->pdo->rollBack(); throw new RuntimeException('wish not found'); }
        if ($wish['image_mode'] !== 'local') { $this->pdo->rollBack(); return; }
        $url = (string)($wish['image_url'] ?? '');
        $this->pdo->commit();

        $timeout = (int)($this->settings['uploads.timeoutSec'] ?? 15);
        $maxBytes = (int)($this->settings['uploads.maxBytes'] ?? 5*1024*1024);
        $allowed  = (array)($this->settings['uploads.allowedMimes'] ?? ['image/jpeg','image/png','image/webp','image/gif']);

        [$bytes, $mime] = $this->download($url, $timeout, $maxBytes);
        if (!in_array($mime, $allowed, true)) throw new RuntimeException("disallowed mime: $mime");

        $info = @getimagesizefromstring($bytes);
        if ($info === false) throw new RuntimeException('not an image');
        [$width,$height] = [$info[0] ?? null, $info[1] ?? null];

        $hash = hash('sha256', $bytes);
        $ext = Storage::extForMime($mime);
        $uploadsDir = rtrim((string)($this->settings['uploads.dir'] ?? (__DIR__.'/../../public/uploads')), '/');
        Storage::ensureDir($uploadsDir);
        [$abs, $rel] = Storage::buildImagePath($uploadsDir, $hash, $ext);
        if (!file_exists($abs)) file_put_contents($abs, $bytes);

        $upd = $this->pdo->prepare('UPDATE wishes SET image_path=:p, image_mime=:m, image_bytes=:b, image_width=:w, image_height=:h, image_hash=:hsh, image_status="ok", image_last_error=NULL WHERE id=:id');
        $upd->execute(['p'=>$rel,'m'=>$mime,'b'=>strlen($bytes),'w'=>$width,'h'=>$height,'hsh'=>$hash,'id'=>$wishId]);
    }

    /** @return array{0:string,1:string} [bytes, mime] */
    private function download(string $url, int $timeout, int $maxBytes): array
    {
        if (empty($url)) throw new RuntimeException('empty URL');
        
        $ctx = stream_context_create([
            'http' => ['method'=>'GET','timeout'=>$timeout,'header'=>"User-Agent: OpenWishlist/worker\r\nAccept: image/*\r\n",'follow_location'=>1,'max_redirects'=>5],
            'ssl'  => ['verify_peer'=>true,'verify_peer_name'=>true],
        ]);
        
        $fh = @fopen($url, 'rb', false, $ctx);
        if (!$fh) {
            $error = error_get_last();
            throw new RuntimeException('failed to open stream: ' . ($error['message'] ?? 'unknown error'));
        }
        
        $meta = stream_get_meta_data($fh);
        $mime = 'application/octet-stream';
        foreach (($meta['wrapper_data'] ?? []) as $h) {
            if (stripos($h, 'content-type:') === 0) {
                $mime = trim(strtolower(explode(':', $h, 2)[1] ?? ''));
                $mime = trim(explode(';', $mime, 2)[0]);
            }
        }
        
        $buf=''; 
        while (!feof($fh)) { 
            $chunk=fread($fh,8192); 
            if ($chunk===false) break; 
            $buf.=$chunk; 
            if (strlen($buf)>$maxBytes) { 
                fclose($fh); 
                throw new RuntimeException("exceeds max bytes ($maxBytes)"); 
            } 
        }
        fclose($fh);
        
        if (empty($buf)) throw new RuntimeException('empty response');
        
        return [$buf,$mime];
    }
}