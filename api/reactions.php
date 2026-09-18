<?php

/**
 * api/reactions.php — CORREGIDO
 * Compatible con reactions.js real:
 * - POST action:'react' + type:'like'/'dislike'/'wow'
 * - POST action:'rate' + rating:1-5
 * - POST action:'comment'
 * - POST action:'like_comment'
 * - GET  action:'counts'
 * - GET  action:'comments'
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
    $v = $db->fetchOne("SELECT id FROM visitors WHERE ip_address = ? LIMIT 1", [$ip]);
    if ($v) return (int)$v['id'];
    return (int)$db->insert('visitors', [
        'full_name'   => 'Anonymous',
        'email'       => 'anonymous_' . time() . '@visitor.local',
        'ip_address'  => $ip,
        'session_id'  => bin2hex(random_bytes(16)),
        'first_visit' => date('Y-m-d H:i:s'),
        'last_visit'  => date('Y-m-d H:i:s'),
        'visit_count' => 1,
        'status'      => 'active',
    ]);
}

// Obtener los 3 contadores de reacciones para un proyecto
function getReactionCounts(Database $db, string $projectId): array
{
    try {
        $row = $db->fetchOne(
            "SELECT
                SUM(reaction_type='like')    AS likes,
                SUM(reaction_type='dislike') AS dislikes,
                SUM(reaction_type='wow')     AS wows
             FROM likes WHERE project_id = ?",
            [$projectId]
        );
        return [
            'like'    => (int)($row['likes']    ?? 0),
            'dislike' => (int)($row['dislikes'] ?? 0),
            'wow'     => (int)($row['wows']     ?? 0),
        ];
    } catch (Exception $e) {
        // Si no existe columna reaction_type, contar todo como like
        $total = $db->count('likes', 'project_id = ?', [$projectId]);
        return ['like' => $total, 'dislike' => 0, 'wow' => 0];
    }
}

// Obtener reacciones activas del visitor actual
function getUserReactions(Database $db, int $visitorId, string $projectId): array
{
    try {
        $rows = $db->fetchAll(
            "SELECT reaction_type FROM likes
             WHERE project_id = ? AND visitor_id = ?",
            [$projectId, $visitorId]
        );
        $active = ['like' => false, 'dislike' => false, 'wow' => false];
        foreach ($rows as $r) {
            if (isset($active[$r['reaction_type']])) $active[$r['reaction_type']] = true;
        }
        return $active;
    } catch (Exception $e) {
        return ['like' => false, 'dislike' => false, 'wow' => false];
    }
}

try {
    $db     = new Database();
    $ip     = getIp();
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET ───────────────────────────────────────────────────
    if ($method === 'GET') {
        $action     = $_GET['action']     ?? 'counts';
        $project_id = (string)($_GET['project_id'] ?? '');

        if (!$project_id) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'project_id required']);
            exit;
        }

        // GET counts — formato que espera reactions.js
        if ($action === 'counts') {
            $counts = getReactionCounts($db, $project_id);

            $visitorId = getOrCreateVisitor($db, $ip);
            $userReactions = getUserReactions($db, $visitorId, $project_id);

            // Conteo de comentarios aprobados
            $commentCount = $db->count(
                'comments',
                'project_id = ? AND is_approved = 1',
                [$project_id]
            );

            // Rating promedio desde tabla ratings
            $avgRating = null;
            $ratingCount = 0;
            try {
                $ratingRow = $db->fetchOne(
                    "SELECT AVG(rating) as avg, COUNT(*) as cnt
                     FROM ratings WHERE project_id = ?",
                    [$project_id]
                );
                if ($ratingRow && $ratingRow['avg']) {
                    $avgRating   = round((float)$ratingRow['avg'], 1);
                    $ratingCount = (int)$ratingRow['cnt'];
                }
            } catch (Exception $e) {
            }

            // reactions.js espera: data.counts.like, data.user_reactions.like
            echo json_encode([
                'success'        => true,
                'counts'         => $counts,           // {like:N, dislike:N, wow:N}
                'user_reactions' => $userReactions,    // {like:bool, dislike:bool, wow:bool}
                'comment_count'  => $commentCount,
                'avg_rating'     => $avgRating,
                'rating_count'   => $ratingCount,
                // compatibilidad con código viejo
                'like_count'     => $counts['like'],
                'user_liked'     => $userReactions['like'],
            ]);
            exit;
        }

        // GET comments
        if ($action === 'comments') {
            $perPage = min(20, (int)($_GET['per_page'] ?? 10));
            $page    = max(1,  (int)($_GET['page']     ?? 1));
            $offset  = ($page - 1) * $perPage;

            $comments = $db->fetchAll(
                "SELECT c.id, c.comment_text, c.rating, c.created_at,
                        COALESCE(v.full_name,'Anonymous') AS full_name,
                        0 AS like_count
                 FROM comments c
                 LEFT JOIN visitors v ON c.visitor_id = v.id
                 WHERE c.project_id = ? AND c.is_approved = 1
                 ORDER BY c.created_at DESC
                 LIMIT {$perPage} OFFSET {$offset}",
                [$project_id]
            );

            $total = $db->count(
                'comments',
                'project_id = ? AND is_approved = 1',
                [$project_id]
            );

            echo json_encode(['success' => true, 'comments' => $comments, 'total' => $total]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }

    // ── POST ──────────────────────────────────────────────────
    if ($method === 'POST') {
        $raw    = file_get_contents('php://input');
        $data   = json_decode($raw, true) ?? [];
        $action = $data['action'] ?? '';

        // POST action:'react' — reactions.js envía esto con type:'like'/'dislike'/'wow'
        if ($action === 'react') {
            $project_id    = (string)($data['project_id'] ?? '');
            $reaction_type = $data['type'] ?? 'like';

            if (!$project_id || !in_array($reaction_type, ['like', 'dislike', 'wow'])) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'project_id y type requeridos']);
                exit;
            }

            $visitorId = getOrCreateVisitor($db, $ip);

            // Toggle: si ya existe, quitar; si no, agregar
            try {
                $existing = $db->fetchOne(
                    "SELECT id FROM likes
                     WHERE project_id = ? AND visitor_id = ? AND reaction_type = ?",
                    [$project_id, $visitorId, $reaction_type]
                );
                if ($existing) {
                    $db->delete('likes', 'id = ?', [$existing['id']]);
                } else {
                    $db->insert('likes', [
                        'visitor_id'    => $visitorId,
                        'project_id'    => $project_id,
                        'reaction_type' => $reaction_type,
                        'liked_at'      => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (Exception $e) {
                // Si no existe reaction_type (tabla vieja), usar solo like
                $existing = $db->fetchOne(
                    "SELECT id FROM likes WHERE project_id=? AND visitor_id=?",
                    [$project_id, $visitorId]
                );
                if ($existing) {
                    $db->delete('likes', 'id = ?', [$existing['id']]);
                } else {
                    $db->insert('likes', [
                        'visitor_id' => $visitorId,
                        'project_id' => $project_id,
                        'liked_at'   => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $counts        = getReactionCounts($db, $project_id);
            $userReactions = getUserReactions($db, $visitorId, $project_id);

            echo json_encode([
                'success'        => true,
                'counts'         => $counts,
                'user_reactions' => $userReactions,
            ]);
            exit;
        }

        // POST action:'rate' — star rating
        if ($action === 'rate') {
            $project_id = (string)($data['project_id'] ?? '');
            $rating     = (int)($data['rating'] ?? 0);

            if (!$project_id || $rating < 1 || $rating > 5) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'project_id y rating(1-5) requeridos']);
                exit;
            }

            $visitorId = getOrCreateVisitor($db, $ip);

            // Upsert en tabla ratings
            try {
                $existing = $db->fetchOne(
                    "SELECT id FROM ratings WHERE project_id=? AND ip_address=?",
                    [$project_id, $ip]
                );
                if ($existing) {
                    $db->query(
                        "UPDATE ratings SET rating=?, updated_at=NOW() WHERE id=?",
                        [$rating, $existing['id']]
                    );
                } else {
                    $db->insert('ratings', [
                        'project_id' => $project_id,
                        'visitor_id' => $visitorId,
                        'ip_address' => $ip,
                        'rating'     => $rating,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                $ratingRow = $db->fetchOne(
                    "SELECT AVG(rating) as avg, COUNT(*) as cnt FROM ratings WHERE project_id=?",
                    [$project_id]
                );

                echo json_encode([
                    'success'      => true,
                    'avg_rating'   => $ratingRow ? round((float)$ratingRow['avg'], 1) : $rating,
                    'rating_count' => $ratingRow ? (int)$ratingRow['cnt'] : 1,
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Rating table error: ' . $e->getMessage()]);
            }
            exit;
        }

        // POST action:'comment'
        if ($action === 'comment') {
            $project_id   = trim((string)($data['project_id']   ?? ''));
            $full_name    = trim($data['full_name']    ?? '');
            $email        = trim($data['email']        ?? '');
            $comment_text = trim($data['comment_text'] ?? '');
            $rating       = isset($data['rating']) ? (int)$data['rating'] : null;

            if (!$project_id || !$full_name || !$comment_text) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'project_id, full_name y comment_text requeridos']);
                exit;
            }
            if (strlen($comment_text) > 500) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Máximo 500 caracteres']);
                exit;
            }

            $visitorId = getOrCreateVisitor($db, $ip);

            // Rate limit: 3/hora
            $recent = $db->count(
                'comments',
                'visitor_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                [$visitorId]
            );
            if ($recent >= 3) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Demasiados comentarios. Espera un momento.']);
                exit;
            }

            // Actualizar nombre del visitor
            $db->query(
                "UPDATE visitors SET full_name=?, last_visit=NOW() WHERE id=?",
                [htmlspecialchars($full_name), $visitorId]
            );

            $insertData = [
                'visitor_id'   => $visitorId,
                'project_id'   => $project_id,
                'comment_text' => htmlspecialchars($comment_text),
                'is_approved'  => 0,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ];
            if ($rating && $rating >= 1 && $rating <= 5) {
                $insertData['rating'] = $rating;
            }
            $db->insert('comments', $insertData);

            try {
                $db->insert('notifications', [
                    'type'       => 'comment',
                    'recipient'  => 'admin',
                    'subject'    => "Nuevo comentario: {$full_name} en proyecto {$project_id}",
                    'message'    => substr($comment_text, 0, 200),
                    'status'     => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {
            }

            echo json_encode(['success' => true, 'message' => 'Comentario enviado. Aparecerá tras revisión.']);
            exit;
        }

        // POST action:'like_comment' — reactions.js lo usa para likes en comentarios
        if ($action === 'like_comment') {
            $comment_id = (int)($data['comment_id'] ?? 0);
            if (!$comment_id) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'comment_id required']);
                exit;
            }
            // Simple toggle usando localStorage en el cliente
            // En el servidor solo retornamos éxito (no hay tabla comment_likes)
            echo json_encode(['success' => true, 'liked' => true, 'count' => 0]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción desconocida: ' . $action]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'debug'   => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : null,
    ]);
}
