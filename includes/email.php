<?php
/**
 * CleanIndex Portal - Email Handler
 * Manages email notifications using WordPress mail functions
 */

if (!defined('ABSPATH')) exit;

function cip_send_email($to, $subject, $message, $headers = []) {
    $default_headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: CleanIndex <noreply@cleanindex.com>'
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    return wp_mail($to, $subject, $message, $headers);
}

function cip_get_email_template($type, $data = []) {
    $templates_dir = CIP_PLUGIN_DIR . 'mailer/email-templates/';
    $template_file = $templates_dir . $type . '.html';
    
    if (!file_exists($template_file)) {
        return false;
    }
    
    $template = file_get_contents($template_file);
    
    // Replace placeholders
    foreach ($data as $key => $value) {
        $template = str_replace('{{' . $key . '}}', $value, $template);
    }
    
    return $template;
}

function cip_send_approval_email($email, $company_name) {
    $data = [
        'company_name' => $company_name,
        'assessment_link' => home_url('/cleanindex/assessment'),
        'dashboard_link' => home_url('/cleanindex/dashboard'),
        'year' => date('Y')
    ];
    
    $message = cip_get_email_template('approval', $data);
    
    if (!$message) {
        $message = cip_get_default_approval_email($data);
    }
    
    $subject = 'Your CleanIndex Registration Has Been Approved!';
    
    return cip_send_email($email, $subject, $message);
}

function cip_send_rejection_email($email, $company_name, $reason = '') {
    $data = [
        'company_name' => $company_name,
        'reason' => $reason,
        'register_link' => home_url('/cleanindex/register'),
        'year' => date('Y')
    ];
    
    $message = cip_get_email_template('rejection', $data);
    
    if (!$message) {
        $message = cip_get_default_rejection_email($data);
    }
    
    $subject = 'Additional Information Required - CleanIndex Registration';
    
    return cip_send_email($email, $subject, $message);
}

function cip_send_info_request_email($email, $company_name, $message_text) {
    $data = [
        'company_name' => $company_name,
        'message' => $message_text,
        'dashboard_link' => home_url('/cleanindex/dashboard'),
        'year' => date('Y')
    ];
    
    $message = cip_get_email_template('info-request', $data);
    
    if (!$message) {
        $message = cip_get_default_info_request_email($data);
    }
    
    $subject = 'Action Required - CleanIndex Registration';
    
    return cip_send_email($email, $subject, $message);
}

// Default email templates (fallback)
function cip_get_default_approval_email($data) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Inter, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; }
            h1 { color: #4CAF50; font-family: Raleway, sans-serif; }
            .button { background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 20px 0; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎉 Congratulations!</h1>
            <p>Dear ' . esc_html($data['company_name']) . ',</p>
            <p>We are pleased to inform you that your registration with CleanIndex has been <strong>approved</strong>!</p>
            <p>You can now access your dashboard and begin your ESG assessment journey.</p>
            <a href="' . esc_url($data['assessment_link']) . '" class="button">Start Your Assessment</a>
            <p>If you have any questions, please don't hesitate to reach out to our support team.</p>
            <div class="footer">
                <p>© ' . $data['year'] . ' CleanIndex. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

function cip_get_default_rejection_email($data) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Inter, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; }
            h1 { color: #EB5E28; font-family: Raleway, sans-serif; }
            .button { background: #03A9F4; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 20px 0; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Additional Information Required</h1>
            <p>Dear ' . esc_html($data['company_name']) . ',</p>
            <p>Thank you for your interest in CleanIndex. We need some additional information to process your registration.</p>
            ' . ($data['reason'] ? '<p><strong>Reason:</strong> ' . esc_html($data['reason']) . '</p>' : '') . '
            <p>Please update your registration details and provide the necessary documentation.</p>
            <a href="' . esc_url($data['register_link']) . '" class="button">Update Registration</a>
            <div class="footer">
                <p>© ' . $data['year'] . ' CleanIndex. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

function cip_get_default_info_request_email($data) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Inter, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; }
            h1 { color: #03A9F4; font-family: Raleway, sans-serif; }
            .message-box { background: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .button { background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 20px 0; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Action Required</h1>
            <p>Dear ' . esc_html($data['company_name']) . ',</p>
            <p>Our review team has requested additional information regarding your registration:</p>
            <div class="message-box">
                ' . nl2br(esc_html($data['message'])) . '
            </div>
            <a href="' . esc_url($data['dashboard_link']) . '" class="button">Go to Dashboard</a>
            <div class="footer">
                <p>© ' . $data['year'] . ' CleanIndex. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}