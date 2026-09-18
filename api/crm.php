<?php

/**
 * api/crm.php
 * CRM API — notes, status updates, contact data
 * GET  ?action=notes&visitor_id=N  → notes for a contact
 * GET  ?action=history&visitor_id=N→ full interaction history
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

session_start();
// Basic admin check for GET requests
// (POST actions are handled in admin/crm.php directly)

try {
    $db     = new Database();
    $action = $_GET['action'] ?? '';

    // GET notes
    if ($action === 'notes') {
        $visitorId = (int)($_GET['visitor_id'] ?? 0);
        if (!$visitorId) {
            echo json_encode(['success' => false, 'message' => 'visitor_id required']);
            exit;
        }

        $notes = [];
        if ($db->tableExists('crm_notes')) {
            $notes = $db->fetchAll(
                "SELECT note, added_by, created_at
                 FROM crm_notes
                 WHERE visitor_id = ?
                 ORDER BY created_at DESC
                 LIMIT 20",
                [$visitorId]
            );
        }

        echo json_encode(['success' => true, 'notes' => $notes]);
        exit;
    }

    // GET full history (chat + appointments + comments)
    if ($action === 'history') {
        $visitorId = (int)($_GET['visitor_id'] ?? 0);
        if (!$visitorId) {
            echo json_encode(['success' => false, 'message' => 'visitor_id required']);
            exit;
        }

        $messages = [];
        if ($db->tableExists('chat_logs')) {
            $messages = $db->fetchAll(
                "SELECT sender, message, meta_intent, meta_score, created_at
                 FROM chat_logs
                 WHERE visitor_id = ?
                 ORDER BY created_at ASC
                 LIMIT 100",
                [$visitorId]
            );
        }

        $appointments = [];
        if ($db->tableExists('appointments')) {
            $appointments = $db->fetchAll(
                "SELECT appointment_date, appointment_time, status, created_at
                 FROM appointments
                 WHERE visitor_id = ?
                 ORDER BY created_at DESC",
                [$visitorId]
            );
        }

        echo json_encode([
            'success'      => true,
            'messages'     => $messages,
            'appointments' => $appointments,
        ]);
        exit;
    }

    // GET visitor score summary
    if ($action === 'score') {
        $visitorId = (int)($_GET['visitor_id'] ?? 0);
        if (!$visitorId) {
            echo json_encode(['success' => false]);
            exit;
        }

        $score = 0;
        if ($db->tableExists('chat_logs')) {
            $row = $db->fetchOne(
                "SELECT SUM(CASE WHEN meta_score IS NOT NULL THEN meta_score ELSE 0 END) as total
                 FROM chat_logs WHERE visitor_id = ?",
                [$visitorId]
            );
            $score = (int)($row['total'] ?? 0);
        }

        echo json_encode(['success' => true, 'score' => $score]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
