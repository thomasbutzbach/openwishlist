<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

use PDO;
use PDOException;
use DateTimeInterface;

/**
 * DB-basierte Job-Queue mit vollständiger Status-Verfolgung.
 * Verwendet die erweiterte jobs-Tabelle mit status, priority und timing-Spalten.
 */
final class Jobs
{
    public function __construct(private PDO $pdo) {}

    /** Einen fälligen Job exklusiv claimen und auf processing setzen. */
    public function claimNext(string $type): ?array
    {
        try {
            $this->pdo->beginTransaction();

            // Suche nach queued Job des gewünschten Typs
            $sql = "SELECT id, type, payload FROM jobs 
                    WHERE type = :type AND status = 'queued' AND run_at <= NOW()
                    ORDER BY priority ASC, id ASC 
                    LIMIT 1 FOR UPDATE";
            
            $sel = $this->pdo->prepare($sql);
            $sel->execute(['type' => $type]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->pdo->commit();
                return null;
            }

            // Job auf processing setzen
            $upd = "UPDATE jobs SET status = 'processing', started_at = NOW(), 
                    attempts = attempts + 1 WHERE id = :id";
            $st = $this->pdo->prepare($upd);
            $st->execute(['id' => $row['id']]);

            $this->pdo->commit();

            $payloadRaw = $row['payload'] ?? '';
            $payload = json_decode((string)$payloadRaw, true);
            if (!is_array($payload)) $payload = [];

            return [
                'id'      => (int)$row['id'],
                'type'    => (string)$row['type'],
                'payload' => $payload,
            ];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Job erfolgreich abschließen. */
    public function complete(int $id): void
    {
        $sql = "UPDATE jobs SET status = 'completed', finished_at = NOW(), last_error = NULL WHERE id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute(['id' => $id]);
    }

    /**
     * Job fehlgeschlagen – Fehlertext speichern.
     * Mit max attempts Limit und exponential backoff.
     */
    public function fail(int $id, string $reason, int $retryInSeconds = 0, int $maxAttempts = 5): void
    {
        $reason = mb_substr($reason, 0, 500);
        
        // Aktuelle attempts holen
        $stmt = $this->pdo->prepare('SELECT attempts FROM jobs WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $attempts = (int)$stmt->fetchColumn();

        if ($attempts >= $maxAttempts) {
            // Nach max attempts: Job endgültig failed aber LÖSCHEN (nicht blockieren)
            $sql = "DELETE FROM jobs WHERE id = :id";
            $st = $this->pdo->prepare($sql);
            $st->execute(['id' => $id]);
            return;
        }

        if ($retryInSeconds > 0) {
            // Job für Retry zurück in die Queue mit exponential backoff
            $backoffSeconds = $retryInSeconds * pow(2, $attempts); // 120, 240, 480, 960...
            $sql = "UPDATE jobs SET status = 'queued', last_error = :err, 
                    run_at = DATE_ADD(NOW(), INTERVAL :sec SECOND), started_at = NULL 
                    WHERE id = :id";
            $st = $this->pdo->prepare($sql);
            $st->execute(['id' => $id, 'err' => $reason, 'sec' => $backoffSeconds]);
        } else {
            // Job sofort löschen (nicht failed status der System blockiert)
            $sql = "DELETE FROM jobs WHERE id = :id";
            $st = $this->pdo->prepare($sql);
            $st->execute(['id' => $id]);
        }
    }

    /** Job enqueuen (z. B. für image.fetch). */
    public function enqueue(string $type, array $payload = [], ?DateTimeInterface $runAt = null, int $priority = 100): void
    {
        $sql = "INSERT INTO jobs (type, payload, status, priority, run_at, attempts) 
                VALUES (:type, :payload, 'queued', :priority, :run_at, 0)";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            'type'     => $type,
            'payload'  => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'priority' => $priority,
            'run_at'   => ($runAt ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Optional: Seed für image.fetch – erzeugt Jobs für wishes mit image_mode='local',
     * die noch bearbeitet werden müssen. Idempotent bzgl. bereits vorhandener Jobs.
     */
    public function seedImageFetchBatch(int $limit = 200): int
    {
        $sql = "
            INSERT INTO jobs (type, payload, status, priority, run_at, attempts)
            SELECT 'image.fetch',
                   JSON_OBJECT('wishId', w.id),
                   'queued',
                   100,
                   NOW(),
                   0
              FROM wishes w
             WHERE w.image_mode = 'local'
               AND (w.image_status IS NULL OR w.image_status = 'pending')
               AND NOT EXISTS (
                   SELECT 1 FROM jobs j
                    WHERE j.type = 'image.fetch'
                      AND j.status IN ('queued', 'processing')
                      AND JSON_EXTRACT(j.payload, '$.wishId') = w.id
               )
             ORDER BY w.id ASC
             LIMIT :lim
        ";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->rowCount();
    }

    /** Statistiken für Admin-Dashboard. */
    public function getStats(): array
    {
        $sql = "SELECT status, COUNT(*) as count FROM jobs GROUP BY status";
        $stmt = $this->pdo->query($sql);
        
        $stats = ['queued' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int)$row['count'];
        }
        
        return $stats;
    }

    /** Alte completed/failed Jobs aufräumen (älter als X Tage). */
    public function cleanup(int $daysOld = 7): int
    {
        $sql = "DELETE FROM jobs 
                WHERE status IN ('completed', 'failed') 
                AND finished_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $st = $this->pdo->prepare($sql);
        $st->execute(['days' => $daysOld]);
        return $st->rowCount();
    }

    /** Entferne Jobs für nicht-existierende Wishes. */
    public function cleanupOrphanedJobs(): int
    {
        $sql = "DELETE FROM jobs 
                WHERE type = 'image.fetch' 
                AND JSON_EXTRACT(payload, '$.wishId') NOT IN (
                    SELECT id FROM wishes
                )";
        $st = $this->pdo->prepare($sql);
        $st->execute();
        return $st->rowCount();
    }
}