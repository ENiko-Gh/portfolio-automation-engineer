<?php

/**
 * Clase para enviar notificaciones por WhatsApp usando Twilio
 */

require_once __DIR__ . '/../vendor/autoload.php'; // Twilio SDK
use Twilio\Rest\Client;

class WhatsAppNotification
{
    private $client;
    private $fromNumber;
    private $toNumber;

    public function __construct()
    {
        if (defined('TWILIO_SID') && defined('TWILIO_TOKEN')) {
            $this->client = new Client(TWILIO_SID, TWILIO_TOKEN);
            $this->fromNumber = TWILIO_WHATSAPP;
            $this->toNumber = ADMIN_WHATSAPP;
        }
    }

    /**
     * Enviar notificación de nuevo visitante
     */
    public function sendNewVisitorNotification($visitorData)
    {
        if (!$this->client) return false;

        $message = "🆕 *Nuevo Visitante en tu Portfolio*\n\n";
        $message .= "👤 *Nombre:* {$visitorData['full_name']}\n";
        $message .= "📧 *Email:* {$visitorData['email']}\n";

        if (!empty($visitorData['company'])) {
            $message .= "🏢 *Empresa:* {$visitorData['company']}\n";
        }

        if (!empty($visitorData['job_title'])) {
            $message .= "💼 *Cargo:* {$visitorData['job_title']}\n";
        }

        if (!empty($visitorData['country'])) {
            $message .= "🌍 *País:* {$visitorData['country']}\n";
        }

        $message .= "\n📊 Revisa el panel de admin para más detalles.";

        return $this->sendMessage($message);
    }

    /**
     * Enviar notificación de nueva cita
     */
    public function sendAppointmentNotification($appointmentData, $visitorData)
    {
        if (!$this->client) return false;

        $date = date('d/m/Y', strtotime($appointmentData['appointment_date']));
        $time = date('h:i A', strtotime($appointmentData['appointment_time']));

        $message = "📅 *Nueva Cita Agendada*\n\n";
        $message .= "👤 *Cliente:* {$visitorData['full_name']}\n";
        $message .= "📧 *Email:* {$visitorData['email']}\n";

        if (!empty($visitorData['phone'])) {
            $message .= "📱 *Teléfono:* {$visitorData['phone']}\n";
        }

        $message .= "\n📆 *Fecha:* {$date}\n";
        $message .= "🕐 *Hora:* {$time}\n";
        $message .= "🎯 *Tipo:* " . ucfirst(str_replace('_', ' ', $appointmentData['meeting_type'])) . "\n";

        if (!empty($appointmentData['description'])) {
            $message .= "\n💬 *Descripción:*\n{$appointmentData['description']}";
        }

        return $this->sendMessage($message);
    }

    /**
     * Enviar notificación de nuevo comentario
     */
    public function sendNewCommentNotification($commentData, $visitorData)
    {
        if (!$this->client) return false;

        $message = "💬 *Nuevo Comentario en tu Portfolio*\n\n";
        $message .= "👤 *De:* {$visitorData['full_name']}\n";
        $message .= "📧 *Email:* {$visitorData['email']}\n";

        if (!empty($commentData['rating'])) {
            $stars = str_repeat('⭐', $commentData['rating']);
            $message .= "⭐ *Rating:* {$stars}\n";
        }

        $message .= "\n💭 *Comentario:*\n{$commentData['comment_text']}\n";
        $message .= "\n🔗 Apruébalo desde el panel de admin.";

        return $this->sendMessage($message);
    }

    /**
     * Recordatorio de cita (24 horas antes)
     */
    public function sendAppointmentReminder($appointmentData, $visitorData)
    {
        // Enviar al cliente (si tiene WhatsApp en el registro)
        if (!empty($visitorData['phone'])) {
            $clientNumber = 'whatsapp:' . $visitorData['phone'];

            $date = date('d/m/Y', strtotime($appointmentData['appointment_date']));
            $time = date('h:i A', strtotime($appointmentData['appointment_time']));

            $message = "👋 Hola {$visitorData['full_name']},\n\n";
            $message .= "Este es un recordatorio de tu cita:\n\n";
            $message .= "📆 *Fecha:* {$date}\n";
            $message .= "🕐 *Hora:* {$time} ({$appointmentData['timezone']})\n";
            $message .= "🎯 *Tipo:* " . ucfirst(str_replace('_', ' ', $appointmentData['meeting_type'])) . "\n\n";
            $message .= "Nos vemos mañana! 😊\n\n";
            $message .= "Si necesitas reagendar, responde a este mensaje.";

            try {
                $this->client->messages->create(
                    $clientNumber,
                    [
                        'from' => $this->fromNumber,
                        'body' => $message
                    ]
                );
                return true;
            } catch (Exception $e) {
                error_log("WhatsApp Reminder Error: " . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    /**
     * Enviar mensaje genérico
     */
    private function sendMessage($message)
    {
        try {
            $this->client->messages->create(
                $this->toNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => $message
                ]
            );
            return true;
        } catch (Exception $e) {
            error_log("WhatsApp Send Error: " . $e->getMessage());
            return false;
        }
    }
}
