<?php

namespace App\Repositories;

use App\Config\Database;

/**
 * NoteRepository
 *
 * Handles all SQL for the `appointment_notes` table.
 * Note content is stored encrypted — encryption/decryption
 * is handled in NoteService so this layer stays pure SQL.
 */
class NoteRepository {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // ----------------------------------------------------------------
    // READ
    // ----------------------------------------------------------------

    /**
     * List all active notes for a given appointment, scoped to tenant.
     */
    public function findByAppointment(int $appointmentId, int $tenantId): array {
        $stmt = $this->db->prepare(
            'SELECT id, appointment_id, user_id, note, created_at, updated_at
             FROM   appointment_notes
             WHERE  appointment_id = :appointment_id
               AND  tenant_id      = :tenant_id
               AND  is_deleted     = 0
             ORDER BY created_at ASC'
        );
        $stmt->execute([
            'appointment_id' => $appointmentId,
            'tenant_id'      => $tenantId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Find a single note by ID, scoped to tenant.
     */
    public function findById(int $id, int $tenantId): ?array {
        $stmt = $this->db->prepare(
            'SELECT id, appointment_id, user_id, note, created_at, updated_at
             FROM   appointment_notes
             WHERE  id         = :id
               AND  tenant_id  = :tenant_id
               AND  is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    // ----------------------------------------------------------------
    // WRITE
    // ----------------------------------------------------------------

    /**
     * Insert a new note. Returns the new note ID.
     */
    public function create(int $appointmentId, int $tenantId, int $userId, string $encryptedNote): int {
        $stmt = $this->db->prepare(
            'INSERT INTO appointment_notes
             (appointment_id, tenant_id, user_id, note, created_at, updated_at)
             VALUES
             (:appointment_id, :tenant_id, :user_id, :note, NOW(), NOW())'
        );
        $stmt->execute([
            'appointment_id' => $appointmentId,
            'tenant_id'      => $tenantId,
            'user_id'        => $userId,
            'note'           => $encryptedNote,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update note content. Only updates if the note belongs to $userId.
     */
    public function update(int $id, int $tenantId, int $userId, string $encryptedNote): bool {
        $stmt = $this->db->prepare(
            'UPDATE appointment_notes
             SET    note       = :note,
                    updated_at = NOW()
             WHERE  id         = :id
               AND  tenant_id  = :tenant_id
               AND  user_id    = :user_id
               AND  is_deleted = 0'
        );
        $stmt->execute([
            'note'      => $encryptedNote,
            'id'        => $id,
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-delete a note. Only deletes if the note belongs to $userId.
     */
    public function delete(int $id, int $tenantId, int $userId): bool {
        $stmt = $this->db->prepare(
            'UPDATE appointment_notes
             SET    is_deleted = 1,
                    deleted_at = NOW()
             WHERE  id         = :id
               AND  tenant_id  = :tenant_id
               AND  user_id    = :user_id
               AND  is_deleted = 0'
        );
        $stmt->execute([
            'id'        => $id,
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
