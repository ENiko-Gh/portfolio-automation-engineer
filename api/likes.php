<?php

/**
 * api/likes.php
 * Compatible con tu tabla likes real:
 * id, visitor_id, project_id (varchar), liked_at
 *
 * POST { project_id } → toggle like, devuelve count y estado
 * GET  ?project_id=X  → conteo de likes
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

function getOrCreateVisitor(Database $db, string $ip): int
{
    $v = $db->fetchOne(
        "SELECT id FROM visitors WHERE ip_address = ? LIMIT 1",
        [$ip]
    );
    if ($v) return (int)$v['id'];

    return (int)$db->insert('visitors', [
        'full_name'   => 'Anonymous',
        'email'       => 'anonymous@visitor.local',
        'ip_address'  => $ip,
        'session_id'  => bin2hex(random_bytes(16)),
        'first_visit' => date('Y-m-d H:i:s'),
        'last_visit'  => date('Y-m-d H:i:s'),
        'visit_count' => 1,
        'status'      => 'active',
    ]);
}

try {
    $db   = new Database();
    $ip   = getIp();
    $meth = $_SERVER['REQUEST_METHOD'];

    // ── GET — obtener conteo ──────────────────────────────────
    if ($meth === 'GET') {
        $projectId = $_GET['project_id'] ?? '';

        if ($projectId) {
            $count = $db->count('likes', 'project_id = ?', [$projectId]);
            $visitorRow = $db->fetchOne(
                "SELECT id FROM visitors WHERE ip_address = ? LIMIT 1",
                [$ip]
            );
            $userLiked = false;
            if ($visitorRow) {
                $userLiked = $db->count(
                    'likes',
                    'project_id = ? AND visitor_id = ?',
                    [$projectId, $visitorRow['id']]
                ) > 0;
            }
            echo json_encode([
                'success'    => true,
                'count'      => $count,
                'user_liked' => $userLiked,
                'project_id' => $projectId,
            ]);
        } else {
            // Todos los proyectos con sus conteos
            $rows = $db->fetchAll(
                "SELECT project_id, COUNT(*) as count FROM likes
                 GROUP BY project_id ORDER BY count DESC"
            );
            echo json_encode(['success' => true, 'likes' => $rows]);
        }
        exit;
    }

    // ── POST — toggle like ────────────────────────────────────
    if ($meth === 'POST') {
        $raw       = file_get_contents('php://input');
        $data      = json_decode($raw, true) ?? [];
        $projectId = trim($data['project_id'] ?? '');

        if (!$projectId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'project_id required']);
            exit;
        }

        $visitorId = getOrCreateVisitor($db, $ip);

        // Verificar si ya dio like
        $existing = $db->fetchOne(
            "SELECT id FROM likes WHERE project_id = ? AND visitor_id = ?",
            [$projectId, $visitorId]
        );

        if ($existing) {
            // Quitar like
            $db->delete('likes', 'id = ?', [$existing['id']]);
            $liked = false;
        } else {
            // Dar like
            $db->insert('likes', [
                'visitor_id' => $visitorId,
                'project_id' => $projectId,
                'liked_at'   => date('Y-m-d H:i:s'),
            ]);
            $liked = true;
        }

        $count = $db->count('likes', 'project_id = ?', [$projectId]);

        echo json_encode([
            'success'    => true,
            'liked'      => $liked,
            'count'      => $count,
            'project_id' => $projectId,
        ]);
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
