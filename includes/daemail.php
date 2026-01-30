<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoload

/**
 * Send Disciplinary Action Email
 *
 * Signature kept compatible with existing calls:
 * sendDAEmail($toEmail, $fullName, $actionType, $DA_Reason, $resolvedReason, $actionDate, $operation = 'add', $recordLink = '/disciplinary.php')
 *
 * @param string      $toEmail        Student email
 * @param string      $fullName       Student full name
 * @param string      $actionType     Action type (Warning, Suspension, etc.)
 * @param string      $DA_Reason    Action DA_Reason / reason
 * @param string|null $resolvedReason Resolved reason if action is resolved (optional)
 * @param string      $actionDate     Action date (string)
 * @param string      $operation      Operation type: add, edit, delete, resolved
 * @param string|null $recordLink     Optional link to the record (defaults to '/disciplinary.php')
 * @return 
 */
function sendDAEmail(
    $toEmail,
    $fullName,
    $actionType,
    $DA_Reason,
    $resolvedReason = null,
    $actionDate = '',
    $operation = 'add',
    $recordLink = '/disciplinary.php'
) {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'scsvmvstaffportal@gmail.com';
        $mail->Password   = 'xjyo byvm sdwq yqmj'; // move this to env/config for production
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Sender & recipient
        $mail->setFrom('scsvmvstaffportal@gmail.com', 'SCSVMV Staff Portal');
        $mail->addAddress($toEmail, $fullName);

        // Embed university logo (if available)
        $logoPath = __DIR__ . '/../assets/img/logo.png';
        $logoHTML = '';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'unilogo');
            $logoHTML = '<img src="cid:unilogo" alt="University Logo" style="max-width:140px; height:auto; display:block; margin:0 auto 12px;">';
        }

        // Normalize operation for comparisons
        $op = strtolower((string)$operation);

        // Subject mapping
        $subject = match ($op) {
            'add'      => 'New Disciplinary Action Recorded',
            'edit'     => 'Disciplinary Action Updated',
            'delete'   => 'Disciplinary Action Removed',
            'resolved' => 'Disciplinary Action Resolved',
            default    => 'Disciplinary Action Notification'
        };
        $mail->Subject = $subject;

        // Operation text
        $operationText = match ($op) {
            'add'      => 'A new disciplinary action has been recorded on your student record.',
            'edit'     => 'A disciplinary action on your record has been updated. See details below.',
            'delete'   => 'A disciplinary action has been removed from your record.',
            'resolved' => 'A disciplinary action on your record has been marked as resolved.',
            default    => 'Please see the disciplinary action details below.'
        };

        // Safely prepare values
        $safeFullName   = htmlspecialchars((string)$fullName, ENT_QUOTES, 'UTF-8');
        $safeActionType = htmlspecialchars((string)$actionType, ENT_QUOTES, 'UTF-8');
        $safeDA_Reason = nl2br(htmlspecialchars((string)$DA_Reason, ENT_QUOTES, 'UTF-8'));
        $safeResolved   = nl2br(htmlspecialchars((string)$resolvedReason, ENT_QUOTES, 'UTF-8'));
        $safeActionDate = htmlspecialchars((string)$actionDate, ENT_QUOTES, 'UTF-8');
        $safeRecordLink = htmlspecialchars((string)($recordLink ?? '/disciplinary.php'), ENT_QUOTES, 'UTF-8');

        // Build HTML body: always show Reason; show Resolved Reason row when not empty
        $resolvedRow = '';
        if (!empty($resolvedReason)) {
            $resolvedRow = '
                <tr>
                    <td style="padding:10px; border:1px solid #ddd; background:#f8f9fb;"><strong>Resolved Reason</strong></td>
                    <td style="padding:10px; border:1px solid #ddd;">' . $safeResolved . '</td>
                </tr>';
        }

        $mail->isHTML(true);
        $mail->Body = '
        <div style="font-family:Arial, Helvetica, sans-serif; color:#333; line-height:1.4; max-width:700px; margin:0 auto;">
            <div style="text-align:center; padding:18px 0 6px 0;">
                ' . $logoHTML . '
                <h2 style="margin:6px 0 0 0; color:#004085; font-size:18px;">Sri Chandrasekharendra Saraswathi Viswa Mahavidyalaya</h2>
            </div>

            <div style="padding:16px; background:#fff; border:1px solid #e6e6e6; border-radius:6px;">
                <p>Dear <strong>' . $safeFullName . '</strong>,</p>
                <p>' . $operationText . '</p>

                <table style="width:100%; border-collapse:collapse; margin-top:12px;">
                    <tr>
                        <td style="padding:10px; border:1px solid #ddd; width:30%; background:#f8f9fb;"><strong>Action Type</strong></td>
                        <td style="padding:10px; border:1px solid #ddd;">' . $safeActionType . '</td>
                    </tr>
                    <tr>
                        <td style="padding:10px; border:1px solid #ddd; background:#f8f9fb;"><strong>Reason</strong></td>
                        <td style="padding:10px; border:1px solid #ddd;">' . $safeDA_Reason . '</td>
                    </tr>'
                    . $resolvedRow . '
                    <tr>
                        <td style="padding:10px; border:1px solid #ddd; background:#f8f9fb;"><strong>Action Date</strong></td>
                        <td style="padding:10px; border:1px solid #ddd;">' . $safeActionDate . '</td>
                    </tr>
                </table>


                <p style="margin-top:16px;">If you have questions, please contact your HOD or the administration.</p>

                <p>Regards,<br><strong>SCSVMV Administration</strong></p>
            </div>

            <p style="font-size:12px; color:#777; text-align:center; margin-top:12px;">
                This is an automated message from the University Student Management System. Do not reply to this email.
            </p>
        </div>';

        // Optional plain-text fallback
        $alt = $subject . "\n\n" .
               strip_tags($operationText) . "\n\n" .
               "Action Type: " . strip_tags($safeActionType) . "\n" .
               "Reason: " . strip_tags($DA_Reason) . "\n" .
               (!empty($resolvedReason) ? "Resolved Reason: " . strip_tags($resolvedReason) . "\n" : '') .
               "Action Date: " . strip_tags($safeActionDate) . "\n" .
               "View: " . $safeRecordLink . "\n";
        $mail->AltBody = $alt;

        // Send
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('sendDAEmail error: ' . $mail->ErrorInfo . ' | Exception: ' . $e->getMessage());
        return false;
    }
}