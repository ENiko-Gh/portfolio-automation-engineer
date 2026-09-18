<?php

/**
 * api/comments.php
 * Compatible con tu tabla comments real:
 * id, visitor_id, project_id (varchar), comment_text, rating (int),
 * is_approved (tinyint), created_at, updated_at
 *
 * GET    → listar comentarios aprobados (público) o todos (admin)
 * POST   → enviar nuevo comentario
 * PUT    → aprobar/rechazar (admin)
 * DELETE → eliminar (admin)
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

function getIp(): string
{
    return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0];
}

try {
    $db   = new Database();
    $ip   = getIp();
    $meth = $_SERVER['REQUEST_METHOD'];

    // ── GET — listar comentarios ──────────────────────────────
    if ($meth === 'GET') {
        $projectId = $_GET['project_id'] ?? null;
        $all       = isset($_GET['all']); // solo admin
        $limit     = min(50, (int)($_GET['limit']  ?? 20));
        $offset    = max(0,  (int)($_GET['offset'] ?? 0));

        $where  = $all ? '1=1' : 'c.is_approved = 1';
        $params = [];

        if ($projectId) {
            $where   .= ' AND c.project_id = ?';
            $params[] = $projectId;
        }

        $comments = $db->fetchAll(
            "SELECT c.id, c.project_id, c.comment_text, c.rating,
                    c.is_approved, c.created_at,
                    COALESCE(v.full_name, 'Anonymous') AS full_name,
                    COALESCE(v.email, '') AS email
             FROM comments c
             LEFT JOIN visitors v ON c.visitor_id = v.id
             WHERE {$where}
             ORDER BY c.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        $total = $db->count(
            'comments',
            $all ? '1=1' : 'is_approved = 1'
        );

        echo json_encode([
            'success'  => true,
            'comments' => $comments,
            'total'    => $total,
        ]);
        exit;
    }

    // ── POST — nuevo comentario ───────────────────────────────
    if ($meth === 'POST') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? [];

        $projectId   = trim($data['project_id']   ?? '');
        $fullName    = trim(htmlspecialchars($data['full_name'] ?? 'Anonymous'));
        $email       = trim($data['email']         ?? '');
        $commentText = trim(htmlspecialchars($data['comment_text'] ?? ''));
        $rating      = isset($data['rating']) ? (int)$data['rating'] : null;

        if (!$commentText) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'comment_text is required']);
            exit;
        }

        if (strlen($commentText) > 500) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Max 500 characters']);
            exit;
        }

        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            $rating = null;
        }

        // Rate limit: 3 comentarios por hora por IP
        $visitorRow = $db->fetchOne(
            "SELECT id FROM visitors WHERE ip_address = ? LIMIT 1",
            [$ip]
        );
        if ($visitorRow) {
            $recent = $db->count(
                'comments',
                'visitor_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                [$visitorRow['id']]
            );
            if ($recent >= 3) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Too many comments. Please wait.']);
                exit;
            }
        }

        // Obtener o crear visitor
        if ($visitorRow) {
            $visitorId = (int)$visitorRow['id'];
            if ($fullName !== 'Anonymous') {
                $db->update(
                    'visitors',
                    ['full_name' => $fullName, 'last_visit' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$visitorId]
                );
            }
        } else {
            $visitorId = (int)$db->insert('visitors', [
                'full_name'   => $fullName,
                'email'       => $email ?: 'anonymous@visitor.local',
                'ip_address'  => $ip,
                'session_id'  => bin2hex(random_bytes(16)),
                'first_visit' => date('Y-m-d H:i:s'),
                'last_visit'  => date('Y-m-d H:i:s'),
                'visit_count' => 1,
                'status'      => 'active',
            ]);
        }

        // Insertar comentario
        $insertData = [
            'visitor_id'   => $visitorId,
            'project_id'   => $projectId ?: null,
            'comment_text' => $commentText,
            'is_approved'  => 0,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        if ($rating !== null) $insertData['rating'] = $rating;

        $commentId = $db->insert('comments', $insertData);

        // Notificación
        try {
            $db->insert('notifications', [
                'type'       => 'comment',
                'recipient'  => 'admin',
                'subject'    => "New comment from {$fullName}" . ($projectId ? " on project {$projectId}" : ''),
                'message'    => substr($commentText, 0, 200),
                'status'     => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
        }

        echo json_encode([
            'success' => true,
            'message' => 'Comment submitted! It will appear after review.',
            'id'      => $commentId,
        ]);
        exit;
    }

    // ── PUT — aprobar/rechazar (admin) ────────────────────────
    if ($meth === 'PUT') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? [];

        $commentId  = (int)($data['comment_id'] ?? 0);
        $isApproved = isset($data['is_approved']) ? (int)$data['is_approved'] : null;

        if (!$commentId || $isApproved === null) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'comment_id and is_approved required']);
            exit;
        }

        $rows = $db->update(
            'comments',
            ['is_approved' => $isApproved, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$commentId]
        );

        echo json_encode([
            'success' => true,
            'message' => $isApproved ? 'Comment approved' : 'Comment rejected',
            'rows'    => $rows,
        ]);
        exit;
    }

    // ── DELETE — eliminar (admin) ─────────────────────────────
    if ($meth === 'DELETE') {
        $commentId = (int)($_GET['id'] ?? 0);

        if (!$commentId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'id required']);
            exit;
        }

        $rows = $db->delete('comments', 'id = ?', [$commentId]);
        echo json_encode(['success' => true, 'message' => 'Comment deleted', 'rows' => $rows]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'debug'   => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : null,
    ]);
}
