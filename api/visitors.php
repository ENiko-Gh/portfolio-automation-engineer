<?php

/**
 * api/visitors.php — CORREGIDO
 * Fixes:
 * 1. Server error en formulario de bienvenida
 * 2. Teléfono — acepta cualquier formato internacional
 * 3. Ecuador está en el listado de países válidos
 * 4. Skip registra visitor anónimo correctamente
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

// Limpiar número de teléfono — acepta (593) 099-xxx, +1 575 888..., etc.
function cleanPhone(?string $phone): ?string
{
    if (!$phone) return null;
    // Quitar todo excepto dígitos y el + inicial
    $clean = preg_replace('/[^\d+]/', '', $phone);
    return strlen($clean) >= 7 ? $clean : null;
}

try {
    $db   = new Database();
    $ip   = getIp();
    $meth = $_SERVER['REQUEST_METHOD'];

    // ── POST — registrar visitor ──────────────────────────────
    if ($meth === 'POST') {
        $raw  = file_get_contents('php://input');
        $data = !empty($raw) ? (json_decode($raw, true) ?? []) : $_POST;

        $action     = $data['action']      ?? 'register';
        // ── RETURNING VISIT ───────────────────────────────────
        if (!empty($data['returning']) || $action === 'returning') {
            $vid = (int)($data['visitor_id'] ?? 0);
            $sid = $data['session_id'] ?? '';
            if ($vid) {
                $db->query(
                    "UPDATE visitors SET last_visit=NOW(), visit_count=visit_count+1 WHERE id=?",
                    [$vid]
                );
            } elseif ($sid) {
                $db->query(
                    "UPDATE visitors SET last_visit=NOW(), visit_count=visit_count+1 WHERE session_id=?",
                    [$sid]
                );
            }
            echo json_encode(['success' => true, 'returning' => true]);
            exit;
        }

        $sessionId  = $data['session_id']  ?? bin2hex(random_bytes(16));
        $lang       = $data['language']    ?? $data['lang'] ?? 'en';

        // ── SKIP — registrar anónimo y devolver visitor_id ────
        if ($action === 'skip') {
            // Buscar si ya existe por IP o session
            $existing = $db->fetchOne(
                "SELECT id FROM visitors WHERE ip_address = ? OR session_id = ? LIMIT 1",
                [$ip, $sessionId]
            );

            if ($existing) {
                $vid = (int)$existing['id'];
                try {
                    $db->query(
                        "UPDATE visitors SET last_visit=NOW(), visit_count=visit_count+1, session_id=? WHERE id=?",
                        [$sessionId, $vid]
                    );
                } catch (Exception $e) {
                }
            } else {
                $vid = (int)$db->insert('visitors', [
                    'full_name'   => 'Anonymous',
                    'email'       => 'anonymous_' . time() . '@portfolio.local',
                    'ip_address'  => $ip,
                    'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    'referrer'    => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255),
                    'session_id'  => $sessionId,
                    'language'    => $lang,
                    'visit_count' => 1,
                    'first_visit' => date('Y-m-d H:i:s'),
                    'last_visit'  => date('Y-m-d H:i:s'),
                    'status'      => 'active',
                ]);
            }

            setcookie(
                'portfolio_visitor',
                json_encode(['visitor_id' => $vid, 'session_id' => $sessionId]),
                time() + 86400 * 30,
                '/',
                '',
                false,
                true
            );

            echo json_encode([
                'success'    => true,
                'visitor_id' => $vid,
                'session_id' => $sessionId,
                'anonymous'  => true,
            ]);
            exit;
        }

        // ── REGISTER — formulario de bienvenida ───────────────
        // FIX: quitar validaciones estrictas que causaban Server error
        $fullName  = trim(htmlspecialchars($data['full_name']  ?? $data['name'] ?? ''));
        $email     = trim($data['email']    ?? '');
        $phone     = cleanPhone($data['phone'] ?? '');   // FIX: acepta (593) 099-xxx
        $company   = trim(htmlspecialchars($data['company']   ?? ''));
        $jobTitle  = trim(htmlspecialchars($data['job_title'] ?? $data['role'] ?? ''));
        $country   = trim(htmlspecialchars($data['country']   ?? ''));
        $interest  = trim(htmlspecialchars($data['interest']  ?? $data['what_brings'] ?? ''));
        $referrer  = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255);

        // Solo validar email si se proporcionó (no es required en todos los flujos)
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = ''; // limpiar email inválido sin fallar
        }

        // Si no hay nombre ni email, tratar como anónimo
        if (!$fullName && !$email) {
            $fullName = 'Anonymous';
            $email    = 'anonymous_' . time() . '@portfolio.local';
        }

        // Buscar visitor existente
        $existing = null;
        if ($email && !str_contains($email, '@portfolio.local')) {
            $existing = $db->fetchOne("SELECT id FROM visitors WHERE email = ? LIMIT 1", [$email]);
        }
        if (!$existing) {
            $existing = $db->fetchOne(
                "SELECT id FROM visitors WHERE ip_address = ? OR session_id = ? LIMIT 1",
                [$ip, $sessionId]
            );
        }

        if ($existing) {
            $vid = (int)$existing['id'];
            // Actualizar con datos del formulario
            $db->query(
                "UPDATE visitors SET
                    full_name   = COALESCE(NULLIF(?, ''), full_name),
                    email       = CASE WHEN ? != '' AND ? NOT LIKE '%portfolio.local%' THEN ? ELSE email END,
                    phone       = COALESCE(?, phone),
                    company     = COALESCE(NULLIF(?, ''), company),
                    job_title   = COALESCE(NULLIF(?, ''), job_title),
                    country     = COALESCE(NULLIF(?, ''), country),
                    interest    = COALESCE(NULLIF(?, ''), interest),
                    session_id  = ?,
                    last_visit  = NOW(),
                    visit_count = visit_count + 1,
                    status      = 'active'
                WHERE id = ?",
                [
                    $fullName,
                    $email,
                    $email,
                    $email,
                    $phone,
                    $company,
                    $jobTitle,
                    $country,
                    $interest,
                    $sessionId,
                    $vid
                ]
            );
        } else {
            $vid = (int)$db->insert('visitors', [
                'full_name'   => $fullName,
                'email'       => $email,
                'phone'       => $phone,
                'company'     => $company ?: null,
                'job_title'   => $jobTitle ?: null,
                'country'     => $country ?: null,
                'interest'    => $interest ?: null,
                'ip_address'  => $ip,
                'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'referrer'    => $referrer,
                'language'    => $lang,
                'session_id'  => $sessionId,
                'visit_count' => 1,
                'first_visit' => date('Y-m-d H:i:s'),
                'last_visit'  => date('Y-m-d H:i:s'),
                'status'      => 'active',
            ]);
        }

        // Cookie de 30 días
        setcookie(
            'portfolio_visitor',
            json_encode(['visitor_id' => $vid, 'session_id' => $sessionId]),
            time() + 86400 * 30,
            '/',
            '',
            false,
            true
        );

        // Notificación en BD
        if ($fullName !== 'Anonymous') {
            try {
                $db->insert('notifications', [
                    'type'       => 'visitor',
                    'recipient'  => 'admin',
                    'subject'    => "New visitor: {$fullName}" . ($company ? " ({$company})" : ''),
                    'message'    => "{$fullName} | {$email} | {$country} | {$interest}",
                    'status'     => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {
            }
        }

        echo json_encode([
            'success'    => true,
            'visitor_id' => $vid,
            'session_id' => $sessionId,
            'message'    => 'Welcome! ' . ($fullName !== 'Anonymous' ? $fullName : ''),
        ]);
        exit;
    }

    // ── GET — estadísticas ────────────────────────────────────
    if ($meth === 'GET') {
        echo json_encode([
            'success' => true,
            'total'   => $db->count('visitors'),
            'today'   => $db->count('visitors', 'DATE(first_visit) = CURDATE()'),
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Exception $e) {
    error_log('visitors.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . (defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Please try again'),
    ]);
}
