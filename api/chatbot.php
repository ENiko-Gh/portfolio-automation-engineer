<?php

/**
 * api/chatbot.php — CORREGIDO
 * Devuelve AMBOS formatos para compatibilidad con chatbot.js:
 * - result.reply.text  (nuevo formato)
 * - result.data.message (formato original que espera tu chatbot.js)
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

// Carga segura — no falla si faltan credenciales
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

// Cargar whatsapp/email solo si existen y tienen config
$hasWhatsApp = false;
$hasEmail    = false;
try {
    if (file_exists(__DIR__ . '/../includes/whatsapp.php')) {
        require_once __DIR__ . '/../includes/whatsapp.php';
        $hasWhatsApp = function_exists('sendWhatsAppMessage');
    }
} catch (Exception $e) {
    error_log('whatsapp.php load error: ' . $e->getMessage());
}

try {
    if (file_exists(__DIR__ . '/../includes/email.php')) {
        require_once __DIR__ . '/../includes/email.php';
        $hasEmail = function_exists('sendEmail');
    }
} catch (Exception $e) {
    error_log('email.php load error: ' . $e->getMessage());
}

$BOT_OWNER_WA    = defined('ADMIN_WHATSAPP') ? ADMIN_WHATSAPP : 'whatsapp:+593991054445';
$BOT_OWNER_EMAIL = defined('ADMIN_EMAIL')    ? ADMIN_EMAIL    : 'enguamialama@espe.edu.ec';
$SCORE_ALERT     = 70;

function getIp(): string
{
    return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0];
}

function getOrCreateVisitor(Database $db, string $ip, ?string $sessionId, ?int $visitorId): array
{
    // Buscar por visitor_id directo
    if ($visitorId) {
        $v = $db->fetchOne("SELECT * FROM visitors WHERE id = ? LIMIT 1", [$visitorId]);
        if ($v) {
            try {
                $db->query("UPDATE visitors SET last_visit=NOW(), visit_count=visit_count+1 WHERE id=?", [$v['id']]);
            } catch (Exception $e) {
            }
            return $v;
        }
    }
    // Buscar por session_id
    if ($sessionId) {
        $v = $db->fetchOne("SELECT * FROM visitors WHERE session_id = ? LIMIT 1", [$sessionId]);
        if ($v) {
            try {
                $db->query("UPDATE visitors SET last_visit=NOW(), visit_count=visit_count+1 WHERE id=?", [$v['id']]);
            } catch (Exception $e) {
            }
            return $v;
        }
    }
    // Buscar por IP
    $v = $db->fetchOne("SELECT * FROM visitors WHERE ip_address = ? LIMIT 1", [$ip]);
    if ($v) {
        try {
            $db->query("UPDATE visitors SET last_visit=NOW(), visit_count=visit_count+1 WHERE id=?", [$v['id']]);
        } catch (Exception $e) {
        }
        return $v;
    }

    // Crear anónimo
    $newId = (int)$db->insert('visitors', [
        'full_name'   => 'Anonymous',
        'email'       => 'anonymous_' . time() . '@portfolio.local',
        'ip_address'  => $ip,
        'session_id'  => $sessionId ?: bin2hex(random_bytes(16)),
        'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'first_visit' => date('Y-m-d H:i:s'),
        'last_visit'  => date('Y-m-d H:i:s'),
        'visit_count' => 1,
        'status'      => 'active',
    ]);
    return ['id' => $newId, 'full_name' => 'Anonymous'];
}

function saveLog(Database $db, int $visitorId, string $message, string $sender, string $intent = ''): void
{
    try {
        $db->insert('chat_logs', [
            'visitor_id' => $visitorId,
            'message'    => substr($message, 0, 2000),
            'sender'     => $sender,
            'intent'     => $intent,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        error_log('chat_logs error: ' . $e->getMessage());
    }
}

function searchFAQ(Database $db, string $msg, string $lang): ?string
{
    try {
        $faqs = $db->fetchAll(
            "SELECT question, answer, keywords FROM faqs
             WHERE is_active = 1 AND (language = ? OR language = 'all' OR language = 'en')
             ORDER BY priority ASC LIMIT 20",
            [$lang]
        );
    } catch (Exception $e) {
        return null;
    }

    $msgLower = mb_strtolower($msg);
    foreach ($faqs as $faq) {
        if (!empty($faq['keywords'])) {
            foreach (array_map('trim', explode(',', mb_strtolower($faq['keywords']))) as $kw) {
                if ($kw && str_contains($msgLower, $kw)) return $faq['answer'];
            }
        }
        $qWords = array_filter(explode(' ', mb_strtolower($faq['question'])), fn($w) => strlen($w) > 3);
        $hits = 0;
        foreach ($qWords as $w) {
            if (str_contains($msgLower, $w)) $hits++;
        }
        if ($hits >= 2) return $faq['answer'];
    }
    return null;
}

function analyzeMessage(string $msg): array
{
    $lower  = mb_strtolower($msg);
    $score  = 0;
    $intent = 'general';
    $type   = 'visitor';

    $recruiter = [
        'recruiter',
        'hiring',
        'position',
        'role',
        'job',
        'salary',
        'interview',
        'linkedin',
        'resume',
        'cv',
        'reclutador',
        'vacante',
        'entrevista'
    ];
    $client    = [
        'project',
        'budget',
        'quote',
        'hire',
        'consulting',
        'develop',
        'build',
        'implement',
        'automate',
        'security',
        'network',
        'proyecto',
        'presupuesto'
    ];
    $schedule  = [
        'schedule',
        'meeting',
        'appointment',
        'call',
        'zoom',
        'teams',
        'book',
        'agendar',
        'reunión',
        'cita',
        'llamada',
        'calendly'
    ];
    $risky     = [
        'free',
        'gratis',
        'cheap',
        'no budget',
        'sin presupuesto',
        'just testing',
        'unpaid',
        'sin pago'
    ];

    foreach ($recruiter as $kw) {
        if (str_contains($lower, $kw)) {
            $score += 20;
            $type = 'recruiter';
            $intent = 'job';
            break;
        }
    }
    foreach ($client    as $kw) {
        if (str_contains($lower, $kw)) {
            $score += 25;
            if ($type !== 'recruiter') $type = 'client';
            $intent = 'service';
            break;
        }
    }
    foreach ($schedule  as $kw) {
        if (str_contains($lower, $kw)) {
            $score += 30;
            $intent = 'schedule';
            break;
        }
    }
    foreach ($risky     as $kw) {
        if (str_contains($lower, $kw)) {
            $score -= 50;
            $type = 'risky';
            $intent = 'risky';
            break;
        }
    }
    if (preg_match('/[\w._%+-]+@[\w.-]+\.[a-z]{2,}/i', $msg)) $score += 15;
    if (preg_match('/\+?[\d\s\-().]{8,}/', $msg))               $score += 10;

    return ['score' => $score, 'intent' => $intent, 'type' => $type];
}

function buildResponse(string $msg, array $analysis, string $lang): array
{
    $es     = $lang === 'es';
    $intent = $analysis['intent'];

    $responses = [
        'job' => [
            'en' => "Thanks for reaching out! 👋\n\nI'm Edison Guamialama, IT Engineer (ITIN · ESPE, GPA 18.86/20).\n\nI specialize in:\n🔐 Security & ISO 27001\n🌐 MPLS Networks & IaC\n💻 Full-Stack PHP/Laravel/React\n📊 Power BI & Data Analytics\n\n100% remote, US time zones (MT/ET/PT). Could you tell me more about the opportunity?",
            'es' => "¡Gracias por contactarme! 👋\n\nSoy Edison Guamialama, Ingeniero en TI (ITIN · ESPE, promedio 18.86/20).\n\nMe especializo en:\n🔐 Seguridad e ISO 27001\n🌐 Redes MPLS e IaC\n💻 Full-Stack PHP/Laravel/React\n📊 Power BI y Análisis de Datos\n\n100% remoto, zonas horarias de EE.UU. ¿Me puedes contar más sobre la oportunidad?",
        ],
        'service' => [
            'en' => "I offer remote consulting in 4 areas:\n\n1️⃣ Security & ISO 27001 — ISMS audits, OT/IT hardening\n2️⃣ Network & IaC — MPLS L3VPN, Ansible, AWS\n3️⃣ Full-Stack Dev — PHP, Laravel, React, MySQL\n4️⃣ Data & BI — Power BI, RapidMiner, ETL\n\nRates start at $300 USD. Which area do you need?\n\nFree 15-min discovery call: calendly.com/edhissonguami",
            'es' => "Ofrezco consultoría remota en 4 áreas:\n\n1️⃣ Seguridad e ISO 27001 — auditorías SGSI, hardening\n2️⃣ Redes e IaC — MPLS L3VPN, Ansible, AWS\n3️⃣ Full-Stack — PHP, Laravel, React, MySQL\n4️⃣ Datos y BI — Power BI, RapidMiner, ETL\n\nDesde $300 USD. ¿Qué área necesitas?\n\nLlamada gratuita de 15 min: calendly.com/edhissonguami",
        ],
        'schedule' => [
            'en' => "Happy to schedule a call! 📅\n\nI work MT/ET/PT time zones, Mon–Fri 8am–8pm.\n\n👉 Book directly: calendly.com/edhissonguami\n\nOr send me:\n📧 Your email\n🕐 Preferred timezone & time\n📋 Main topic\n\nI respond within 15 minutes during business hours.",
            'es' => "¡Con gusto agendamos! 📅\n\nTrabajo en MT/ET/PT, Lun–Vie 8am–8pm.\n\n👉 Agenda directamente: calendly.com/edhissonguami\n\nO envíame:\n📧 Tu correo\n🕐 Zona horaria y horario preferido\n📋 Tema principal\n\nRespondo en menos de 15 minutos en horario laboral.",
        ],
        'risky' => [
            'en' => "I understand you're exploring options.\n\nMy consulting starts at $300 USD per project.\n\nIf you have a defined budget and concrete scope, I'd be happy to discuss. Free 15-min call: calendly.com/edhissonguami",
            'es' => "Entiendo que estás explorando opciones.\n\nMi consultoría comienza en $300 USD por proyecto.\n\nSi tienes presupuesto definido y alcance concreto, con gusto lo conversamos. Llamada gratuita: calendly.com/edhissonguami",
        ],
        'general' => [
            'en' => "Hello! 👋 I'm the AI assistant for Edison Guamialama — IT Engineer & Remote Consultant.\n\nI can help you:\n🔍 Learn about services & case studies\n📅 Schedule a discovery call\n💼 Explore job opportunities\n💰 Get pricing information\n\nWhat brings you here today?",
            'es' => "¡Hola! 👋 Soy el asistente de Edison Guamialama — Ingeniero en TI y Consultor Remoto.\n\nPuedo ayudarte a:\n🔍 Conocer servicios y casos de estudio\n📅 Agendar una llamada\n💼 Explorar oportunidades laborales\n💰 Obtener información de precios\n\n¿Qué te trae por aquí hoy?",
        ],
    ];

    $text = $responses[$intent][$lang]
        ?? $responses[$intent]['en']
        ?? $responses['general'][$lang]
        ?? $responses['general']['en'];

    // quick_replies para el nuevo chatbot.js (con formato {label, value})
    $quickReplies = match ($intent) {
        'job'      => $es
            ? [
                ['label' => '📄 Ver CV/Dossier', 'value' => '¿Puedo ver tu dossier?'],
                ['label' => '📅 Agendar entrevista', 'value' => 'Me gustaría agendar una entrevista'],
                ['label' => '💼 ¿Disponible remoto?', 'value' => '¿Estás disponible para trabajo remoto?']
            ]
            : [
                ['label' => '📄 View Resume/Dossier', 'value' => 'Can I see your dossier?'],
                ['label' => '📅 Schedule interview', 'value' => "I'd like to schedule an interview"],
                ['label' => '💼 Remote availability', 'value' => 'Are you available for remote work?']
            ],
        'service'  => [
            ['label' => '🔐 Security/ISO 27001', 'value' => $es ? 'Necesito consultoría de seguridad ISO 27001' : 'I need ISO 27001 security consulting'],
            ['label' => '🌐 Networks/IaC', 'value' => $es ? 'Necesito diseño de red o IaC' : 'I need network design or IaC'],
            ['label' => '💻 Full-Stack Dev', 'value' => $es ? 'Necesito desarrollo web full-stack' : 'I need full-stack web development'],
            ['label' => '📊 Power BI/Data', 'value' => $es ? 'Necesito un dashboard de datos' : 'I need a data dashboard'],
        ],
        'schedule' => $es
            ? [
                ['label' => '📅 Abrir Calendly', 'value' => 'Quiero agendar en calendly.com/edhissonguami'],
                ['label' => '📧 Dar mi email', 'value' => 'Mi email es: '],
                ['label' => '🕐 Esta semana', 'value' => 'Estoy disponible esta semana']
            ]
            : [
                ['label' => '📅 Open Calendly', 'value' => 'I want to book at calendly.com/edhissonguami'],
                ['label' => '📧 Share email', 'value' => 'My email is: '],
                ['label' => '🕐 This week', 'value' => 'I am available this week']
            ],
        default    => $es
            ? [
                ['label' => '💼 Tengo un proyecto', 'value' => 'Tengo un proyecto para consultoría'],
                ['label' => '👔 Soy reclutador', 'value' => 'Soy reclutador y tengo una oportunidad'],
                ['label' => '📅 Agendar reunión', 'value' => 'Me gustaría agendar una reunión'],
                ['label' => '💰 Ver precios', 'value' => '¿Cuáles son tus tarifas de consultoría?']
            ]
            : [
                ['label' => '💼 I have a project', 'value' => 'I have a project for consulting'],
                ['label' => '👔 I\'m a recruiter', 'value' => "I'm a recruiter with an opportunity"],
                ['label' => '📅 Schedule a call', 'value' => "I'd like to schedule a discovery call"],
                ['label' => '💰 See pricing', 'value' => 'What are your consulting rates?']
            ],
    };

    // suggestions en formato simple (array de strings) para compatibilidad con chatbot.js original
    $suggestions = array_map(fn($r) => $r['label'], $quickReplies);

    return [
        'text'         => $text,
        'quick_replies' => $quickReplies,
        'suggestions'  => $suggestions,
        'intent'       => $intent,
    ];
}

// ── MAIN ─────────────────────────────────────────────────────
try {
    $db   = new Database();
    $ip   = getIp();
    $meth = $_SERVER['REQUEST_METHOD'];

    // GET — historial
    if ($meth === 'GET') {
        $sessionId  = $_GET['session_id']  ?? '';
        $visitorId  = (int)($_GET['visitor_id'] ?? 0);
        if (!$sessionId && !$visitorId) {
            echo json_encode(['success' => true, 'messages' => []]);
            exit;
        }
        $visitor = $visitorId
            ? $db->fetchOne("SELECT id FROM visitors WHERE id = ? LIMIT 1", [$visitorId])
            : $db->fetchOne("SELECT id FROM visitors WHERE session_id = ? LIMIT 1", [$sessionId]);

        if (!$visitor) {
            echo json_encode(['success' => true, 'messages' => []]);
            exit;
        }

        $logs = $db->fetchAll(
            "SELECT sender, message, intent, created_at FROM chat_logs
             WHERE visitor_id = ? ORDER BY created_at ASC LIMIT 50",
            [$visitor['id']]
        );
        echo json_encode(['success' => true, 'messages' => $logs]);
        exit;
    }

    // POST — procesar
    if ($meth === 'POST') {
        $raw       = file_get_contents('php://input');
        $data      = json_decode($raw, true) ?? [];
        $msg       = trim($data['message']    ?? '');
        $sessionId = trim($data['session_id'] ?? '');
        $visitorId = (int)($data['visitor_id'] ?? 0);
        $lang      = $data['lang'] ?? $data['language'] ?? 'en';
        $lang      = in_array($lang, ['en', 'es']) ? $lang : 'en';

        if (!$msg) {
            echo json_encode(['success' => false, 'message' => 'Empty message']);
            exit;
        }

        // Obtener/crear visitor
        $visitor   = getOrCreateVisitor($db, $ip, $sessionId ?: null, $visitorId ?: null);
        $vid       = (int)$visitor['id'];

        // Log del mensaje del usuario
        $analysis = analyzeMessage($msg);
        saveLog($db, $vid, $msg, 'user', $analysis['intent']);

        // Buscar FAQ primero
        $faqAnswer = searchFAQ($db, $msg, $lang);
        if ($faqAnswer) {
            saveLog($db, $vid, $faqAnswer, 'bot', 'faq');
            echo json_encode([
                'success' => true,
                'reply'   => ['text' => $faqAnswer, 'intent' => 'faq', 'quick_replies' => [], 'suggestions' => []],
                'data'    => ['message' => $faqAnswer, 'suggestions' => [], 'action' => null],
                'session_id' => $sessionId,
                'visitor_id' => $vid,
            ]);
            exit;
        }

        // Construir respuesta
        $reply = buildResponse($msg, $analysis, $lang);
        saveLog($db, $vid, $reply['text'], 'bot', $reply['intent']);

        // Score para alertas
        try {
            $scoreRow = $db->fetchOne(
                "SELECT COUNT(*) as msgs FROM chat_logs WHERE visitor_id = ?",
                [$vid]
            );
            $sessionScore = $analysis['score'] * max(1, (int)($scoreRow['msgs'] ?? 1));

            if ($sessionScore >= $SCORE_ALERT && $hasWhatsApp) {
                $alerted = $db->count(
                    'notifications',
                    "recipient='admin' AND type='alert' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                );
                if (!$alerted) {
                    $waMsg = "🚨 *Portfolio Alert*\n👤 {$visitor['full_name']}\n📊 Score: {$sessionScore}\n💬 \"{$msg}\"";
                    try {
                        sendWhatsAppMessage($BOT_OWNER_WA, $waMsg);
                    } catch (Exception $e) {
                    }
                    try {
                        $db->insert('notifications', [
                            'type'       => 'alert',
                            'recipient'  => 'admin',
                            'subject'    => "Alert: {$visitor['full_name']} — Score {$sessionScore}",
                            'message'    => "{$visitor['full_name']} | {$msg}",
                            'status'     => 'pending',
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    } catch (Exception $e) {
                    }
                }
            }
        } catch (Exception $e) {
        }

        // Devuelve AMBOS formatos
        echo json_encode([
            'success'    => true,
            // Formato nuevo (document 6 chatbot.js)
            'reply'      => [
                'text'         => $reply['text'],
                'intent'       => $reply['intent'],
                'quick_replies' => $reply['quick_replies'],
                'action'       => null,
            ],
            // Formato original (chatbot.js del usuario que espera result.data.message)
            'data'       => [
                'message'     => $reply['text'],
                'suggestions' => $reply['suggestions'],
                'action'      => null,
            ],
            'session_id'  => $sessionId,
            'visitor_id'  => $vid,
            'visitor_type' => $analysis['type'],
            'score'       => $analysis['score'],
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Exception $e) {
    error_log('chatbot.php fatal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'data'    => ['message' => 'Server error. Try WhatsApp: +1 (575) 888-5484', 'suggestions' => [], 'action' => null],
        'debug'   => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : null,
    ]);
}
