<?php
declare(strict_types=1);

/**
 * Penyimpanan session di database (tabel `sessions`).
 *
 * Kenapa perlu? Di XAMPP, session disimpan sebagai file di komputer yang sama.
 * Di Vercel, setiap request bisa dilayani "mesin" (instance) yang berbeda dan
 * file-nya tidak dibagi, sehingga pengguna akan ter-logout acak. Dengan menyimpan
 * session di database, semua instance membaca data yang sama.
 *
 * Aktif hanya jika Environment Variable SESSION_DRIVER=db.
 */
final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = db()->prepare('SELECT data FROM sessions WHERE id = :id AND last_activity >= :min');
        } catch (PDOException $e) {
            // 1146 = tabel belum ada => instalasi database belum selesai; lanjutkan (Vercel saja).
            if (!on_vercel() || (int) ($e->errorInfo[1] ?? 0) !== 1146) {
                throw $e;
            }
            db_auto_install();
            $stmt = db()->prepare('SELECT data FROM sessions WHERE id = :id AND last_activity >= :min');
        }
        $stmt->execute([':id' => $id, ':min' => time() - $this->lifetime()]);
        $data = $stmt->fetchColumn();
        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $stmt = db()->prepare(
            'INSERT INTO sessions (id, data, last_activity) VALUES (:id, :data, :t)
             ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
        );
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':data', $data, PDO::PARAM_LOB);
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function destroy(string $id): bool
    {
        db()->prepare('DELETE FROM sessions WHERE id = :id')->execute([':id' => $id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = db()->prepare('DELETE FROM sessions WHERE last_activity < :min');
        $stmt->execute([':min' => time() - $max_lifetime]);
        return $stmt->rowCount();
    }

    /** Dipakai PHP saat session.use_strict_mode aktif: ID harus sudah ada. */
    public function validateId(string $id): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM sessions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    private function lifetime(): int
    {
        return max((int) ini_get('session.gc_maxlifetime'), SESSION_IDLE_SECONDS);
    }
}
