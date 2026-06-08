<?php

namespace App\Services;

use App\Config\Env;
use App\Repositories\NoteRepository;
use App\Security\AES;
use Exception;

/**
 * NoteService
 *
 * Business logic for appointment notes.
 *
 * Encryption: note content is AES-256-CBC encrypted before storing,
 * decrypted on every read — same pattern as PHI in PatientRepository.
 *
 * Ownership: only the user who created a note can edit or delete it.
 */
class NoteService {

    private $noteRepo;
    private $aesKey;

    public function __construct() {
        $this->noteRepo = new NoteRepository();
        $this->aesKey   = Env::get('AES_KEY');
    }

    // ----------------------------------------------------------------
    // List notes for an appointment
    // ----------------------------------------------------------------

    public function listNotes(int $appointmentId, int $tenantId): array {
        $rows = $this->noteRepo->findByAppointment($appointmentId, $tenantId);

        return array_map(function ($row) {
            return $this->decryptNote($row);
        }, $rows);
    }

    // ----------------------------------------------------------------
    // Add a note
    // ----------------------------------------------------------------

    public function addNote(array $data, int $appointmentId, int $tenantId, int $userId): int {
        if (empty($data['note'])) {
            throw new Exception('Field "note" is required', 422);
        }

        $encrypted = AES::encrypt($data['note'], $this->aesKey);

        return $this->noteRepo->create($appointmentId, $tenantId, $userId, $encrypted);
    }

    // ----------------------------------------------------------------
    // Edit a note (owner only)
    // ----------------------------------------------------------------

    public function editNote(int $id, array $data, int $tenantId, int $userId): void {
        if (empty($data['note'])) {
            throw new Exception('Field "note" is required', 422);
        }

        $existing = $this->noteRepo->findById($id, $tenantId);

        if (!$existing) {
            throw new Exception('Note not found', 404);
        }

        if ((int) $existing['user_id'] !== $userId) {
            throw new Exception('You can only edit your own notes', 403);
        }

        $encrypted = AES::encrypt($data['note'], $this->aesKey);
        $updated   = $this->noteRepo->update($id, $tenantId, $userId, $encrypted);

        if (!$updated) {
            throw new Exception('Failed to update note', 500);
        }
    }

    // ----------------------------------------------------------------
    // Delete a note (owner only, soft delete)
    // ----------------------------------------------------------------

    public function deleteNote(int $id, int $tenantId, int $userId): void {
        $existing = $this->noteRepo->findById($id, $tenantId);

        if (!$existing) {
            throw new Exception('Note not found', 404);
        }

        if ((int) $existing['user_id'] !== $userId) {
            throw new Exception('You can only delete your own notes', 403);
        }

        $deleted = $this->noteRepo->delete($id, $tenantId, $userId);

        if (!$deleted) {
            throw new Exception('Failed to delete note', 500);
        }
    }

    // ----------------------------------------------------------------
    // Private helper
    // ----------------------------------------------------------------

    private function decryptNote(array $row): array {
        $row['note'] = AES::decrypt($row['note'], $this->aesKey);
        return $row;
    }
}
