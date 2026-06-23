<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditLog
{
    public function __construct(private PDO $pdo) {}

    public function record(?int $actorId, string $action, ?string $target = null, ?string $ip = null, ?string $detail = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_log (actor_id, action, target, ip_address, detail)
                 VALUES (:a,:k,:t,:ip,:d)'
            );
            $stmt->execute([
                ':a'  => $actorId,
                ':k'  => $action,
                ':t'  => $target,
                ':ip' => $ip,
                ':d'  => $detail !== null ? mb_substr($detail, 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the main flow.
            error_log('[Audit] ' . $e->getMessage());
        }
    }
}
