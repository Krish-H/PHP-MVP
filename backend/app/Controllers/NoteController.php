<?php

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\NoteService;
use Exception;

class NoteController {

    private $noteService;

    public function __construct() {
        $this->noteService = new NoteService();
    }

    /**
     * GET /api/appointments/{id}/notes
     */
    public function index($params) {
        try {
            $notes = $this->noteService->listNotes(
                (int) $params['id']
            );
            Response::json(['notes' => $notes, 'count' => count($notes)], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * POST /api/appointments/{id}/notes
     */
    public function store($params) {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
            return;
        }

        try {
            $noteId = $this->noteService->addNote(
                $data,
                (int) $params['id'],
                (int) $_SESSION['current_user_id']
            );
            Response::json(['message' => 'Note added', 'note_id' => $noteId], 201);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * PUT /api/notes/{id}
     */
    public function update($params) {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
            return;
        }

        try {
            $this->noteService->editNote(
                (int) $params['id'],
                $data,
                (int) $_SESSION['current_user_id']
            );
            Response::json(['message' => 'Note updated'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * DELETE /api/notes/{id}
     */
    public function destroy($params) {
        try {
            $this->noteService->deleteNote(
                (int) $params['id'],
                (int) $_SESSION['current_user_id']
            );
            Response::json(['message' => 'Note deleted'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
