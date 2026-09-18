<?php

/**
 * api/appointments.php
 * Compatible con tu tabla appointments real:
 * id, visitor_id, appointment_date, appointment_time, timezone,
 * meeting_type (enum), description, status (enum), google_calendar_id,
 * reminder_sent, created_at, updated_at
 *
 * meeting_type enum values — ajustar según tu ENUM real en la BD
 * status enum: pending, confirmed, cancelled, completed
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    $db   = new Database();
    $ip   = getIp();
    $meth = $_SERVER['REQUEST_METHOD'];

    // ── POST — crear cita ─────────────────────────────────────
    if ($meth === 'POST') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? [];

        // Campos requeridos
        $fullName  = trim(htmlspecialchars($data['full_name']  ?? $data['name'] ?? ''));
        $email     = trim($data['email']    ?? '');
        $date      = trim($data['appointment_date'] ?? $data['date'] ?? '');
        $time      = trim($data['appointment_time'] ?? $data['time'] ?? '09:00');
        $timezone  = trim($data['timezone'] ?? 'America/Denver');
        $meetType  = trim($data['meeting_type'] ?? $data['type'] ?? 'video_call');
        $desc      = trim(htmlspecialchars($data['description'] ?? $data['message'] ?? $data['topic'] ?? ''));
        $phone     = trim($data['phone']    ?? '');
        $sessionId = trim($data['session_id'] ?? '');

        if (!$fullName || !$email) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Name and email are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            exit;
        }

        // Validar fecha si se proporcionó
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d', strtotime($date) ?: strtotime('+2 days'));
        }
        if (!$date) $date = date('Y-m-d', strtotime('+2 days'));

        // Normalizar time
        if (!preg_match('/^\d{2}:\d{2}/', $time)) $time = '09:00:00';
        if (strlen($time) === 5) $time .= ':00';

        // Obtener o crear visitor
        $visitor = $db->fetchOne(
            "SELECT id FROM visitors WHERE email = ? LIMIT 1",
            [$email]
        );
        if ($visitor) {
            $visitorId = (int)$visitor['id'];
            $db->update(
                'visitors',
                ['last_visit' => date('Y-m-d H:i:s'), 'phone' => $phone ?: null],
                'id = ?',
                [$visitorId]
            );
        } else {
            $visitorId = (int)$db->insert('visitors', [
                'full_name'   => $fullName,
                'email'       => $email,
                'phone'       => $phone ?: null,
                'ip_address'  => $ip,
                'session_id'  => $sessionId ?: bin2hex(random_bytes(16)),
                'first_visit' => date('Y-m-d H:i:s'),
                'last_visit'  => date('Y-m-d H:i:s'),
                'visit_count' => 1,
                'status'      => 'active',
            ]);
        }

        // Verificar duplicado: misma persona, misma fecha
        $duplicate = $db->fetchOne(
            "SELECT id FROM appointments
             WHERE visitor_id = ? AND appointment_date = ?
             AND status NOT IN ('cancelled')
             LIMIT 1",
            [$visitorId, $date]
        );
        if ($duplicate) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'You already have an appointment on this date. Check your email for details.',
            ]);
            exit;
        }

        // Crear appointment
        $aptId = (int)$db->insert('appointments', [
            'visitor_id'       => $visitorId,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'timezone'         => $timezone,
            'meeting_type'     => $meetType,
            'description'      => $desc,
            'status'           => 'pending',
            'reminder_sent'    => 0,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        // Notificación en BD
        try {
            $db->insert('notifications', [
                'type'       => 'appointment',
                'recipient'  => 'admin',
                'subject'    => "[Appointment] {$fullName} — {$date} {$time}",
                'message'    => "Name: {$fullName}\nEmail: {$email}\nPhone: {$phone}\nDate: {$date} {$time} {$timezone}\nType: {$meetType}\nTopic: {$desc}",
                'status'     => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
        }

        // Email al owner
        $ownerEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'enguamialama@espe.edu.ec';
        $emailBody  = "
            <h2>📅 New Appointment Request</h2>
            <table>
                <tr><td><b>Name</b></td><td>{$fullName}</td></tr>
                <tr><td><b>Email</b></td><td>{$email}</td></tr>
                <tr><td><b>Phone</b></td><td>" . ($phone ?: '—') . "</td></tr>
                <tr><td><b>Date</b></td><td>{$date} at {$time}</td></tr>
                <tr><td><b>Timezone</b></td><td>{$timezone}</td></tr>
                <tr><td><b>Meeting type</b></td><td>{$meetType}</td></tr>
                <tr><td><b>Topic</b></td><td>{$desc}</td></tr>
            </table>
            <p><a href='mailto:{$email}'>Reply to {$fullName} →</a></p>
        ";
        try {
            sendEmail($ownerEmail, "[Portfolio] New Appointment: {$fullName}", $emailBody, $email);
        } catch (Exception $e) {
            error_log('Apt email failed: ' . $e->getMessage());
        }

        // WhatsApp al owner
        $ownerWa = defined('ADMIN_WHATSAPP') ? ADMIN_WHATSAPP : 'whatsapp:+593991054445';
        $waMsg   = "📅 *New Appointment Request*\n\n" .
            "👤 {$fullName}\n📧 {$email}\n" .
            ($phone ? "📞 {$phone}\n" : "") .
            "📅 {$date} at {$time} ({$timezone})\n" .
            "📋 {$meetType}\n💬 " . substr($desc, 0, 150);
        try {
            sendWhatsAppMessage($ownerWa, $waMsg);
        } catch (Exception $e) {
            error_log('Apt WA failed: ' . $e->getMessage());
        }

        // Email de confirmación al solicitante
        $confirmBody = "
            <h2>📅 Appointment Request Received</h2>
            <p>Hi <b>{$fullName}</b>,</p>
            <p>Your appointment request has been received for <b>{$date} at {$time} ({$timezone})</b>.</p>
            <p>I'll confirm or suggest an alternative time within 24 hours. Check your inbox!</p>
            <p>— Edison Nicolas Guamialama Haro<br>IT Engineer · Remote Consultant<br>
            📱 <a href='https://wa.me/593991054445'>WhatsApp</a></p>
        ";
        try {
            sendEmail($email, "Appointment request confirmed — Edison Guamialama", $confirmBody);
        } catch (Exception $e) {
        }

        // Actualizar notificación como enviada
        try {
            $db->query(
                "UPDATE notifications SET status='sent', sent_at=NOW()
                 WHERE type='appointment' AND recipient='admin'
                 ORDER BY created_at DESC LIMIT 1"
            );
        } catch (Exception $e) {
        }

        echo json_encode([
            'success'        => true,
            'message'        => 'Appointment request received! I\'ll confirm within 24 hours.',
            'appointment_id' => $aptId,
        ]);
        exit;
    }

    // ── PUT — actualizar status (desde admin) ─────────────────
    if ($meth === 'PUT') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? [];

        $aptId  = (int)($data['appointment_id'] ?? 0);
        $status = $data['status'] ?? '';
        $valid  = ['pending', 'confirmed', 'cancelled', 'completed'];

        if (!$aptId || !in_array($status, $valid)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid appointment_id or status']);
            exit;
        }

        $updated = $db->update(
            'appointments',
            ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$aptId]
        );

        // Si se confirma, notificar al visitante
        if ($status === 'confirmed') {
            $apt = $db->fetchOne(
                "SELECT a.*, v.email, v.full_name FROM appointments a
                 LEFT JOIN visitors v ON a.visitor_id = v.id
                 WHERE a.id = ? LIMIT 1",
                [$aptId]
            );
            if ($apt && !empty($apt['email'])) {
                $confirmBody = "
                    <h2>✅ Appointment Confirmed!</h2>
                    <p>Hi <b>{$apt['full_name']}</b>,</p>
                    <p>Your appointment is confirmed for <b>{$apt['appointment_date']} at {$apt['appointment_time']} ({$apt['timezone']})</b>.</p>
                    <p>I'll send the meeting link shortly. See you then!</p>
                    <p>— Edison Nicolas Guamialama Haro</p>
                ";
                try {
                    sendEmail($apt['email'], "✅ Appointment Confirmed — Edison Guamialama", $confirmBody);
                } catch (Exception $e) {
                }
            }
        }

        echo json_encode(['success' => true, 'message' => "Status updated to {$status}", 'rows' => $updated]);
        exit;
    }

    // ── GET — listar (para admin) ─────────────────────────────
    if ($meth === 'GET') {
        $status = $_GET['status'] ?? 'all';
        $limit  = min(50, (int)($_GET['limit'] ?? 20));

        $where  = $status !== 'all' ? 'a.status = ?' : '1=1';
        $params = $status !== 'all' ? [$status] : [];

        $apts = $db->fetchAll(
            "SELECT a.*, v.full_name, v.email, v.phone
             FROM appointments a
             LEFT JOIN visitors v ON a.visitor_id = v.id
             WHERE {$where}
             ORDER BY a.appointment_date ASC, a.appointment_time ASC
             LIMIT {$limit}",
            $params
        );

        echo json_encode(['success' => true, 'appointments' => $apts, 'total' => count($apts)]);
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
