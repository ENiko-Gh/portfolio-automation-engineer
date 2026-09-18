<?php

/**
 * api/contact.php — CORREGIDO
 * Usa new Database(), compatible con BD real
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/whatsapp.php';

function getIp(): string
{
    return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $db  = new Database();
    $ip  = getIp();
    $raw = file_get_contents('php://input');
    $data = !empty($raw) ? (json_decode($raw, true) ?? []) : $_POST;

    $name    = trim(htmlspecialchars($data['name']    ?? $data['full_name'] ?? ''));
    $email   = trim($data['email']   ?? '');
    $phone   = trim($data['phone']   ?? '');
    $subject = trim(htmlspecialchars($data['subject'] ?? 'General Consulting'));
    $message = trim(htmlspecialchars($data['message'] ?? ''));
    $company = trim(htmlspecialchars($data['company'] ?? ''));

    if (!$name || !$email || !$message) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Name, email and message are required']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }

    // Guardar visitor
    $existing = $db->fetchOne("SELECT id FROM visitors WHERE email = ? LIMIT 1", [$email]);
    if ($existing) {
        $visitorId = (int)$existing['id'];
        $db->query(
            "UPDATE visitors SET full_name=?, phone=?, company=?, interest=?, last_visit=NOW(), status='active' WHERE id=?",
            [$name, $phone ?: null, $company ?: null, $subject, $visitorId]
        );
    } else {
        $visitorId = (int)$db->insert('visitors', [
            'full_name'   => $name,
            'email'       => $email,
            'phone'       => $phone ?: null,
            'company'     => $company ?: null,
            'interest'    => $subject,
            'ip_address'  => $ip,
            'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'referrer'    => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255),
            'session_id'  => bin2hex(random_bytes(16)),
            'visit_count' => 1,
            'first_visit' => date('Y-m-d H:i:s'),
            'last_visit'  => date('Y-m-d H:i:s'),
            'status'      => 'active',
        ]);
    }

    // Notificación
    try {
        $db->insert('notifications', [
            'type'       => 'contact',
            'recipient'  => 'admin',
            'subject'    => "[Contact] {$subject} — {$name}",
            'message'    => "From: {$name} <{$email}>\n{$message}",
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
    }

    // Email al owner
    $ownerEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'enguamialama@espe.edu.ec';
    try {
        sendEmail(
            $ownerEmail,
            "[Portfolio] New Contact: {$name}",
            "<h2>New Contact</h2><p><b>{$name}</b> ({$email})</p><p>{$message}</p>",
            $email
        );
    } catch (Exception $e) {
        error_log('Email failed: ' . $e->getMessage());
    }

    // WhatsApp
    $ownerWa = defined('ADMIN_WHATSAPP') ? ADMIN_WHATSAPP : 'whatsapp:+593991054445';
    try {
        sendWhatsAppMessage(
            $ownerWa,
            "📩 *New Contact*\n👤 {$name}\n📧 {$email}\n📋 {$subject}\n💬 " . mb_substr($message, 0, 150)
        );
    } catch (Exception $e) {
        error_log('WA failed: ' . $e->getMessage());
    }

    // Confirmación al visitante
    try {
        sendEmail(
            $email,
            "Message received — Edison Guamialama",
            "<h2>✅ Message Received!</h2><p>Hi {$name}, I'll respond within 24 hours.</p><p>— Edison Guamialama</p>"
        );
    } catch (Exception $e) {
    }

    echo json_encode(['success' => true, 'message' => "Message sent! I'll respond within 24 hours.", 'visitor_id' => $visitorId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'debug' => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : null
    ]);
}
