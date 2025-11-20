<?php
/**
 * Enhanced Email System
 * Add to includes/email.php (REPLACE existing or add these functions)
 */

if (!defined('ABSPATH')) exit;

/**
 * Send assessment start email (REQUIREMENT #4)
 */
function cip_send_assessment_start_email($email, $company_name) {
    $assessment_link = home_url('/cleanindex/assessment');
    $dashboard_link = home_url('/cleanindex/dashboard');
    
    $subject = 'Start Your ESG Assessment - CleanIndex';
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Inter, sans-serif; background: #FAFAFA; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 32px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            h1 { color: #4CAF50; font-family: Raleway, sans-serif; font-size: 24px; margin-bottom: 16px; }
            p { color: #374151; line-height: 1.6; margin-bottom: 12px; font-size: 14px; }
            .button { display: inline-block; padding: 14px 28px; background: #4CAF50; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 14px; margin: 16px 0; }
            .button:hover { background: #43A047; }
            .info-box { background: rgba(76, 175, 80, 0.05); border-left: 3px solid #4CAF50; padding: 16px; margin: 20px 0; border-radius: 6px; }
            .footer { margin-top: 32px; padding-top: 20px; border-top: 1px solid #E5E7EB; color: #6B7280; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎉 Your Registration is Approved!</h1>
            
            <p>Dear ' . esc_html($company_name) . ',</p>
            
            <p>Great news! Both our review team and administration have approved your registration with CleanIndex.</p>
            
            <p>You can now begin your ESG assessment journey. Our comprehensive CSRD/ESRS compliant questionnaire will help you evaluate and certify your sustainability practices.</p>
            
            <div style="text-align: center;">
                <a href="' . esc_url($assessment_link) . '" class="button">🚀 Start Assessment Now</a>
            </div>
            
            <div class="info-box">
                <h3 style="margin: 0 0 12px 0; color: #4CAF50; font-size: 16px;">What to Expect:</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 8px;">5 comprehensive steps covering all ESG aspects</li>
                    <li style="margin-bottom: 8px;">Upload evidence documents to support your answers</li>
                    <li style="margin-bottom: 8px;">Save progress and continue anytime</li>
                    <li style="margin-bottom: 8px;">Receive your ESG certificate upon completion</li>
                </ul>
            </div>
            
            <p><strong>Need help?</strong> Our support team is available to assist you throughout the assessment process.</p>
            
            <p>You can also access your dashboard anytime:</p>
            <p><a href="' . esc_url($dashboard_link) . '" style="color: #03A9F4; text-decoration: none; font-weight: 500;">View Dashboard →</a></p>
            
            <div class="footer">
                <p><strong>CleanIndex</strong> | ESG Certification Platform</p>
                <p>© ' . date('Y') . ' CleanIndex. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: CleanIndex <noreply@cleanindex.com>'
    ];
    
    return wp_mail($email, $subject, $message, $headers);
}

/**
 * Trigger assessment email when both manager AND admin approve (REQUIREMENT #4)
 * Add this function call to admin approval handler
 */
function cip_check_and_send_assessment_email($registration_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND status = 'approved'",
        $registration_id
    ), ARRAY_A);
    
    if ($registration) {
        // Check if assessment email already sent
        $email_sent = get_option('cip_assessment_email_sent_' . $registration_id);
        
        if (!$email_sent) {
            cip_send_assessment_start_email($registration['email'], $registration['company_name']);
            update_option('cip_assessment_email_sent_' . $registration_id, true);
            return true;
        }
    }
    
    return false;
}

/**
 * Send notification email
 */
function cip_send_notification_email($to, $subject, $message, $type = 'info') {
    $icons = [
        'success' => '✅',
        'info' => 'ℹ️',
        'warning' => '⚠️',
        'error' => '❌'
    ];
    
    $colors = [
        'success' => '#4CAF50',
        'info' => '#03A9F4',
        'warning' => '#EB5E28',
        'error' => '#F44336'
    ];
    
    $icon = $icons[$type] ?? $icons['info'];
    $color = $colors[$type] ?? $colors['info'];
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Inter, sans-serif; background: #FAFAFA; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 32px; border-radius: 12px; }
            h1 { color: ' . $color . '; font-size: 24px; margin-bottom: 16px; }
            p { color: #374151; line-height: 1.6; font-size: 14px; }
            .message-box { background: rgba(76, 175, 80, 0.05); padding: 16px; border-radius: 8px; margin: 16px 0; }
            .footer { margin-top: 32px; padding-top: 20px; border-top: 1px solid #E5E7EB; color: #6B7280; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>' . $icon . ' ' . esc_html($subject) . '</h1>
            <div class="message-box">
                ' . wpautop($message) . '
            </div>
            <div class="footer">
                <p>© ' . date('Y') . ' CleanIndex. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
}

/**
 * Create notification for user (REQUIREMENT #10)
 */
function cip_create_notification($user_id, $title, $message, $type = 'info', $link = '') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'cip_notifications';
    
    // Create table if doesn't exist
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            type VARCHAR(50) DEFAULT 'info',
            link VARCHAR(500),
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    return $wpdb->insert($table, [
        'user_id' => $user_id,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'link' => $link
    ]);
}

/**
 * Get user notifications
 */
function cip_get_user_notifications($user_id, $limit = 10, $unread_only = false) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_notifications';
    
    $where = "user_id = %d";
    if ($unread_only) {
        $where .= " AND is_read = 0";
    }
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d",
        $user_id,
        $limit
    ), ARRAY_A);
}

/**
 * Mark notification as read
 */
function cip_mark_notification_read($notification_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_notifications';
    
    return $wpdb->update($table, ['is_read' => 1], ['id' => $notification_id]);
}

/**
 * Get unread notification count
 */
function cip_get_unread_count($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_notifications';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0",
        $user_id
    ));
}