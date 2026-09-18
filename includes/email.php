<?php

/**
 * Clase para enviar notificaciones por Email usando PHPMailer
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailNotification
{
    private $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }

    private function configureMailer()
    {
        try {
            // Configuración del servidor SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USER;
            $this->mailer->Password = SMTP_PASS;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = SMTP_PORT;

            // Configuración del remitente
            $this->mailer->setFrom(SMTP_USER, ADMIN_NAME);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            error_log("Email Configuration Error: " . $e->getMessage());
        }
    }

    /**
     * Enviar notificación de nuevo visitante al admin
     */
    public function sendNewVisitorNotification($visitorData)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress(ADMIN_EMAIL, ADMIN_NAME);

            $this->mailer->Subject = '🆕 Nuevo Visitante en tu Portfolio - ' . $visitorData['full_name'];

            $body = $this->getNewVisitorTemplate($visitorData);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email Send Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar confirmación de cita al visitante
     */
    public function sendAppointmentConfirmation($appointmentData, $visitorData)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($visitorData['email'], $visitorData['full_name']);

            $this->mailer->Subject = '✅ Cita Confirmada - ' . ADMIN_NAME;

            $body = $this->getAppointmentConfirmationTemplate($appointmentData, $visitorData);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email Send Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación de nueva cita al admin
     */
    public function sendNewAppointmentNotification($appointmentData, $visitorData)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress(ADMIN_EMAIL, ADMIN_NAME);

            $this->mailer->Subject = '📅 Nueva Cita Agendada - ' . $visitorData['full_name'];

            $body = $this->getNewAppointmentAdminTemplate($appointmentData, $visitorData);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email Send Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar actualización de estado de cita
     */
    public function sendAppointmentStatusUpdate($appointmentData)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($appointmentData['email'], $appointmentData['full_name']);

            $statusMessages = [
                'confirmed' => '✅ Tu cita ha sido confirmada',
                'cancelled' => '❌ Tu cita ha sido cancelada',
                'completed' => '✔️ Cita completada'
            ];

            $subject = $statusMessages[$appointmentData['status']] ?? 'Actualización de tu cita';
            $this->mailer->Subject = $subject . ' - ' . ADMIN_NAME;

            $body = $this->getAppointmentStatusTemplate($appointmentData);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email Send Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación de nuevo comentario al admin
     */
    public function sendNewCommentNotification($commentData, $visitorData)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress(ADMIN_EMAIL, ADMIN_NAME);

            $this->mailer->Subject = '💬 Nuevo Comentario en tu Portfolio - ' . $visitorData['full_name'];

            $body = $this->getNewCommentTemplate($commentData, $visitorData);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email Send Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar formulario de contacto
     */
    public function sendContactForm($formData)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress(ADMIN_EMAIL, ADMIN_NAME);
            $this->mailer->addReplyTo($formData['email'], $formData['name']);

            $this->mailer->Subject = '📬 Nuevo Mensaje de Contacto - ' . $formData['name'];

            $body = $this->getContactFormTemplate($formData);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email Send Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * TEMPLATES DE EMAIL
     */

    private function getEmailHeader()
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #f4f4f4;
                }
                .email-container {
                    background: white;
                    margin: 20px;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .email-header {
                    background: linear-gradient(135deg, #0070F3 0%, #0060D9 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .email-header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .email-body {
                    padding: 30px;
                }
                .info-box {
                    background: #f8f9fa;
                    border-left: 4px solid #0070F3;
                    padding: 15px;
                    margin: 20px 0;
                    border-radius: 5px;
                }
                .info-row {
                    display: flex;
                    padding: 10px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                .info-row:last-child {
                    border-bottom: none;
                }
                .info-label {
                    font-weight: bold;
                    width: 140px;
                    color: #495057;
                }
                .info-value {
                    color: #212529;
                }
                .button {
                    display: inline-block;
                    padding: 12px 30px;
                    background: #0070F3;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .email-footer {
                    background: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #6c757d;
                }
                .rating-stars {
                    color: #ffc107;
                    font-size: 20px;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
        ';
    }

    private function getEmailFooter()
    {
        return '
                <div class="email-footer">
                    <p>Este es un email automático del sistema de portfolio.</p>
                    <p>© ' . date('Y') . ' ' . ADMIN_NAME . '. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }

    private function getNewVisitorTemplate($data)
    {
        $html = $this->getEmailHeader();
        $html .= '
            <div class="email-header">
                <h1>🆕 Nuevo Visitante</h1>
            </div>
            <div class="email-body">
                <p>Has recibido un nuevo visitante en tu portfolio:</p>
                
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">👤 Nombre:</span>
                        <span class="info-value">' . htmlspecialchars($data['full_name']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📧 Email:</span>
                        <span class="info-value">' . htmlspecialchars($data['email']) . '</span>
                    </div>';

        if (!empty($data['phone'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">📱 Teléfono:</span>
                        <span class="info-value">' . htmlspecialchars($data['phone']) . '</span>
                    </div>';
        }

        if (!empty($data['company'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">🏢 Empresa:</span>
                        <span class="info-value">' . htmlspecialchars($data['company']) . '</span>
                    </div>';
        }

        if (!empty($data['job_title'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">💼 Cargo:</span>
                        <span class="info-value">' . htmlspecialchars($data['job_title']) . '</span>
                    </div>';
        }

        if (!empty($data['country'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">🌍 País:</span>
                        <span class="info-value">' . htmlspecialchars($data['country']) . '</span>
                    </div>';
        }

        if (!empty($data['interest'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">💡 Interés:</span>
                        <span class="info-value">' . htmlspecialchars($data['interest']) . '</span>
                    </div>';
        }

        $html .= '
                    <div class="info-row">
                        <span class="info-label">🌐 IP:</span>
                        <span class="info-value">' . htmlspecialchars($data['ip_address']) . '</span>
                    </div>
                </div>
                
                <p style="text-align: center;">
                    <a href="' . $_SERVER['HTTP_HOST'] . '/admin/visitors.php" class="button">Ver en Panel de Admin</a>
                </p>
            </div>
        ';
        $html .= $this->getEmailFooter();
        return $html;
    }

    private function getAppointmentConfirmationTemplate($appointmentData, $visitorData)
    {
        $date = date('l, F j, Y', strtotime($appointmentData['appointment_date']));
        $time = date('g:i A', strtotime($appointmentData['appointment_time']));

        $html = $this->getEmailHeader();
        $html .= '
            <div class="email-header">
                <h1>✅ Cita Confirmada</h1>
            </div>
            <div class="email-body">
                <p>Hola <strong>' . htmlspecialchars($visitorData['full_name']) . '</strong>,</p>
                
                <p>Tu cita ha sido registrada exitosamente. Aquí están los detalles:</p>
                
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">📆 Fecha:</span>
                        <span class="info-value">' . $date . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">🕐 Hora:</span>
                        <span class="info-value">' . $time . ' (' . htmlspecialchars($appointmentData['timezone']) . ')</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">🎯 Tipo de reunión:</span>
                        <span class="info-value">' . ucfirst(str_replace('_', ' ', $appointmentData['meeting_type'])) . '</span>
                    </div>';

        if (!empty($appointmentData['description'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">💬 Descripción:</span>
                        <span class="info-value">' . nl2br(htmlspecialchars($appointmentData['description'])) . '</span>
                    </div>';
        }

        $html .= '
                </div>
                
                <p><strong>Estado actual:</strong> ⏳ Pendiente de confirmación</p>
                
                <p>Recibirás un email de confirmación una vez que revise tu solicitud. También te enviaré un recordatorio 24 horas antes de la reunión.</p>
                
                <p>Si necesitas reagendar o cancelar, por favor responde a este email o contáctame directamente.</p>
                
                <p>¡Espero nuestra conversación!</p>
                
                <p>Saludos,<br><strong>' . ADMIN_NAME . '</strong></p>
            </div>
        ';
        $html .= $this->getEmailFooter();
        return $html;
    }

    private function getNewAppointmentAdminTemplate($appointmentData, $visitorData)
    {
        $date = date('l, F j, Y', strtotime($appointmentData['appointment_date']));
        $time = date('g:i A', strtotime($appointmentData['appointment_time']));

        $html = $this->getEmailHeader();
        $html .= '
            <div class="email-header">
                <h1>📅 Nueva Cita Agendada</h1>
            </div>
            <div class="email-body">
                <p>Se ha agendado una nueva cita en tu calendario:</p>
                
                <div class="info-box">
                    <h3 style="margin-top: 0; color: #0070F3;">Información del Cliente</h3>
                    <div class="info-row">
                        <span class="info-label">👤 Nombre:</span>
                        <span class="info-value">' . htmlspecialchars($visitorData['full_name']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📧 Email:</span>
                        <span class="info-value"><a href="mailto:' . htmlspecialchars($visitorData['email']) . '">' . htmlspecialchars($visitorData['email']) . '</a></span>
                    </div>';

        if (!empty($visitorData['phone'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">📱 Teléfono:</span>
                        <span class="info-value"><a href="tel:' . htmlspecialchars($visitorData['phone']) . '">' . htmlspecialchars($visitorData['phone']) . '</a></span>
                    </div>';
        }

        if (!empty($visitorData['company'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">🏢 Empresa:</span>
                        <span class="info-value">' . htmlspecialchars($visitorData['company']) . '</span>
                    </div>';
        }

        $html .= '
                </div>
                
                <div class="info-box" style="border-left-color: #28a745;">
                    <h3 style="margin-top: 0; color: #28a745;">Detalles de la Cita</h3>
                    <div class="info-row">
                        <span class="info-label">📆 Fecha:</span>
                        <span class="info-value">' . $date . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">🕐 Hora:</span>
                        <span class="info-value">' . $time . ' (' . htmlspecialchars($appointmentData['timezone']) . ')</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">🎯 Tipo:</span>
                        <span class="info-value">' . ucfirst(str_replace('_', ' ', $appointmentData['meeting_type'])) . '</span>
                    </div>';

        if (!empty($appointmentData['description'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">💬 Descripción:</span>
                        <span class="info-value">' . nl2br(htmlspecialchars($appointmentData['description'])) . '</span>
                    </div>';
        }

        $html .= '
                </div>
                
                <p style="text-align: center;">
                    <a href="' . $_SERVER['HTTP_HOST'] . '/admin/appointments.php" class="button">Gestionar en Panel de Admin</a>
                </p>
                
                <p style="font-size: 12px; color: #6c757d; margin-top: 30px;">
                    💡 <strong>Recordatorio:</strong> Confirma la cita desde el panel de administración para que el cliente reciba la notificación de confirmación.
                </p>
            </div>
        ';
        $html .= $this->getEmailFooter();
        return $html;
    }

    private function getAppointmentStatusTemplate($appointmentData)
    {
        $date = date('l, F j, Y', strtotime($appointmentData['appointment_date']));
        $time = date('g:i A', strtotime($appointmentData['appointment_time']));

        $statusConfig = [
            'confirmed' => [
                'color' => '#28a745',
                'icon' => '✅',
                'title' => 'Cita Confirmada',
                'message' => '¡Excelente! Tu cita ha sido confirmada.'
            ],
            'cancelled' => [
                'color' => '#dc3545',
                'icon' => '❌',
                'title' => 'Cita Cancelada',
                'message' => 'Tu cita ha sido cancelada. Si deseas reagendar, por favor contáctame.'
            ],
            'completed' => [
                'color' => '#0070F3',
                'icon' => '✔️',
                'title' => 'Cita Completada',
                'message' => 'Gracias por tu tiempo. Espero que la reunión haya sido productiva.'
            ]
        ];

        $config = $statusConfig[$appointmentData['status']] ?? $statusConfig['confirmed'];

        $html = $this->getEmailHeader();
        $html .= '
            <div class="email-header" style="background: linear-gradient(135deg, ' . $config['color'] . ' 0%, ' . $config['color'] . 'dd 100%);">
                <h1>' . $config['icon'] . ' ' . $config['title'] . '</h1>
            </div>
            <div class="email-body">
                <p>Hola <strong>' . htmlspecialchars($appointmentData['full_name']) . '</strong>,</p>
                
                <p>' . $config['message'] . '</p>
                
                <div class="info-box" style="border-left-color: ' . $config['color'] . ';">
                    <div class="info-row">
                        <span class="info-label">📆 Fecha:</span>
                        <span class="info-value">' . $date . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">🕐 Hora:</span>
                        <span class="info-value">' . $time . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">🎯 Tipo:</span>
                        <span class="info-value">' . ucfirst(str_replace('_', ' ', $appointmentData['meeting_type'])) . '</span>
                    </div>
                </div>
                
                <p>Si tienes alguna pregunta, no dudes en contactarme.</p>
                
                <p>Saludos,<br><strong>' . ADMIN_NAME . '</strong></p>
            </div>
        ';
        $html .= $this->getEmailFooter();
        return $html;
    }

    private function getNewCommentTemplate($commentData, $visitorData)
    {
        $html = $this->getEmailHeader();
        $html .= '
            <div class="email-header">
                <h1>💬 Nuevo Comentario</h1>
            </div>
            <div class="email-body">
                <p>Has recibido un nuevo comentario en tu portfolio:</p>
                
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">👤 De:</span>
                        <span class="info-value">' . htmlspecialchars($visitorData['full_name']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📧 Email:</span>
                        <span class="info-value">' . htmlspecialchars($visitorData['email']) . '</span>
                    </div>';

        if (!empty($visitorData['company'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">🏢 Empresa:</span>
                        <span class="info-value">' . htmlspecialchars($visitorData['company']) . '</span>
                    </div>';
        }

        if (!empty($commentData['rating'])) {
            $stars = str_repeat('⭐', $commentData['rating']);
            $html .= '
                    <div class="info-row">
                        <span class="info-label">⭐ Rating:</span>
                        <span class="info-value rating-stars">' . $stars . ' (' . $commentData['rating'] . '/5)</span>
                    </div>';
        }

        if (!empty($commentData['project_id'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">📁 Proyecto:</span>
                        <span class="info-value">' . htmlspecialchars($commentData['project_id']) . '</span>
                    </div>';
        }

        $html .= '
                </div>
                
                <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
                    <h4 style="margin-top: 0;">💭 Comentario:</h4>
                    <p>' . nl2br(htmlspecialchars($commentData['comment_text'])) . '</p>
                </div>
                
                <p style="text-align: center;">
                    <a href="' . $_SERVER['HTTP_HOST'] . '/admin/comments.php" class="button">Aprobar Comentario</a>
                </p>
                
                <p style="font-size: 12px; color: #6c757d;">
                    Este comentario está pendiente de aprobación y no será visible públicamente hasta que lo apruebes.
                </p>
            </div>
        ';
        $html .= $this->getEmailFooter();
        return $html;
    }

    private function getContactFormTemplate($formData)
    {
        $html = $this->getEmailHeader();
        $html .= '
            <div class="email-header">
                <h1>📬 Nuevo Mensaje de Contacto</h1>
            </div>
            <div class="email-body">
                <p>Has recibido un nuevo mensaje desde el formulario de contacto:</p>
                
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">👤 Nombre:</span>
                        <span class="info-value">' . htmlspecialchars($formData['name']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📧 Email:</span>
                        <span class="info-value"><a href="mailto:' . htmlspecialchars($formData['email']) . '">' . htmlspecialchars($formData['email']) . '</a></span>
                    </div>';

        if (!empty($formData['phone'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">📱 Teléfono:</span>
                        <span class="info-value"><a href="tel:' . htmlspecialchars($formData['phone']) . '">' . htmlspecialchars($formData['phone']) . '</a></span>
                    </div>';
        }

        if (!empty($formData['subject'])) {
            $html .= '
                    <div class="info-row">
                        <span class="info-label">📋 Asunto:</span>
                        <span class="info-value">' . htmlspecialchars($formData['subject']) . '</span>
                    </div>';
        }

        $html .= '
                </div>
                
                <div class="info-box" style="background: #e7f3ff; border-left-color: #0070F3;">
                    <h4 style="margin-top: 0;">💬 Mensaje:</h4>
                    <p>' . nl2br(htmlspecialchars($formData['message'])) . '</p>
                </div>
                
                <p style="text-align: center;">
                    <a href="mailto:' . htmlspecialchars($formData['email']) . '?subject=Re: ' . urlencode($formData['subject'] ?? 'Contacto') . '" class="button">Responder</a>
                </p>
            </div>
        ';
        $html .= $this->getEmailFooter();
        return $html;
    }
}
