<?php
/**
 * CleanIndex Portal - Enhanced AJAX Handlers
 * Handles: Manager actions, Assessment scoring, Certificate generation
 */

if (!defined('ABSPATH')) exit;

// AJAX: Manager get registration details
add_action('wp_ajax_cip_manager_get_registration_details', 'cip_manager_get_registration_details');
function cip_manager_get_registration_details() {
    check_ajax_referer('manager_action', 'nonce');
    
    if (!current_user_can('manager') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $registration_id = intval($_POST['registration_id']);
    
    global $wpdb;
    $table_reg = $wpdb->prefix . 'company_registrations';
    $table_assess = $wpdb->prefix . 'company_assessments';
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_reg WHERE id = %d",
        $registration_id
    ), ARRAY_A);
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_assess WHERE user_id = %d",
        $registration_id
    ), ARRAY_A);
    
    wp_send_json_success([
        'registration' => $registration,
        'assessment' => $assessment
    ]);
}

// AJAX: Admin get registration details for approval
add_action('wp_ajax_cip_admin_get_registration_details', 'cip_admin_get_registration_details');
function cip_admin_get_registration_details() {
    check_ajax_referer('admin_action', 'nonce');
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $registration_id = intval($_POST['registration_id']);
    
    global $wpdb;
    $table_reg = $wpdb->prefix . 'company_registrations';
    $table_assess = $wpdb->prefix . 'company_assessments';
    $table_scores = $wpdb->prefix . 'cip_assessment_scores';
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_reg WHERE id = %d",
        $registration_id
    ), ARRAY_A);
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_assess WHERE user_id = %d",
        $registration_id
    ), ARRAY_A);
    
    $score = null;
    if ($assessment) {
        $score = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_scores WHERE assessment_id = %d ORDER BY scored_at DESC LIMIT 1",
            $assessment['id']
        ), ARRAY_A);
    }
    
    wp_send_json_success([
        'registration' => $registration,
        'assessment' => $assessment,
        'score' => $score
    ]);
}

// AJAX: Admin approve registration
add_action('wp_ajax_cip_admin_approve_registration', 'cip_admin_approve_registration');
function cip_admin_approve_registration() {
    check_ajax_referer('admin_action', 'nonce');
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $registration_id = intval($_POST['registration_id']);
    $admin_notes = sanitize_textarea_field($_POST['admin_notes']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $result = $wpdb->update(
        $table,
        [
            'status' => 'approved',
            'admin_notes' => $admin_notes,
            'approved_at' => current_time('mysql')
        ],
        ['id' => $registration_id]
    );
    
    if ($result === false) {
        wp_send_json_error(['message' => 'Failed to approve registration']);
    }
    
    // Get registration details
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $registration_id
    ), ARRAY_A);
    
    // Create WordPress user
    if ($registration && !username_exists($registration['email'])) {
        $user_id = wp_create_user(
            $registration['email'],
            wp_generate_password(),
            $registration['email']
        );
        
        if (!is_wp_error($user_id)) {
            $user = new WP_User($user_id);
            $user->set_role('organization_admin');
            
            // Update registration with user_id
            $wpdb->update(
                $table,
                ['wordpress_user_id' => $user_id],
                ['id' => $registration_id]
            );
            
            // Send approval email
            if (function_exists('cip_send_approval_email')) {
                cip_send_approval_email($registration);
            }
        }
    }
    
    wp_send_json_success(['message' => 'Registration approved successfully']);
}

// AJAX: Admin reject registration
add_action('wp_ajax_cip_admin_reject_registration', 'cip_admin_reject_registration');
function cip_admin_reject_registration() {
    check_ajax_referer('admin_action', 'nonce');
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $registration_id = intval($_POST['registration_id']);
    $admin_notes = sanitize_textarea_field($_POST['admin_notes']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $result = $wpdb->update(
        $table,
        [
            'status' => 'rejected',
            'admin_notes' => $admin_notes
        ],
        ['id' => $registration_id]
    );
    
    if ($result === false) {
        wp_send_json_error(['message' => 'Failed to reject registration']);
    }
    
    // Get registration details and send rejection email
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $registration_id
    ), ARRAY_A);
    
    if ($registration && function_exists('cip_send_rejection_email')) {
        cip_send_rejection_email($registration, $admin_notes);
    }
    
    wp_send_json_success(['message' => 'Registration rejected']);
}

// AJAX: Get assessment details for review
add_action('wp_ajax_cip_get_assessment_details', 'cip_get_assessment_details');
function cip_get_assessment_details() {
    check_ajax_referer('admin_action', 'nonce');
    
    if (!current_user_can('administrator') && !current_user_can('manager')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    $table_reg = $wpdb->prefix . 'company_registrations';
    $table_assess = $wpdb->prefix . 'company_assessments';
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_reg WHERE id = %d",
        $user_id
    ), ARRAY_A);
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_assess WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    if (!$assessment) {
        wp_send_json_error(['message' => 'Assessment not found']);
    }
    
    // Parse answers
    $answers = json_decode($assessment['answers'], true);
    
    wp_send_json_success([
        'registration' => $registration,
        'assessment' => $assessment,
        'answers' => $answers
    ]);
}

// AJAX: Save assessment score
add_action('wp_ajax_cip_save_assessment_score', 'cip_save_assessment_score');
function cip_save_assessment_score() {
    check_ajax_referer('admin_action', 'nonce');
    
    if (!current_user_can('administrator') && !current_user_can('manager')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $assessment_id = intval($_POST['assessment_id']);
    $user_id = intval($_POST['user_id']);
    $score = intval($_POST['score']);
    $grade = sanitize_text_field($_POST['grade']);
    $comments = sanitize_textarea_field($_POST['comments']);
    
    global $wpdb;
    $table_scores = $wpdb->prefix . 'cip_assessment_scores';
    
    // Insert or update score
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_scores WHERE assessment_id = %d",
        $assessment_id
    ));
    
    if ($existing) {
        $result = $wpdb->update(
            $table_scores,
            [
                'score' => $score,
                'grade' => $grade,
                'comments' => $comments,
                'scored_by' => get_current_user_id(),
                'scored_at' => current_time('mysql')
            ],
            ['assessment_id' => $assessment_id]
        );
    } else {
        $result = $wpdb->insert(
            $table_scores,
            [
                'assessment_id' => $assessment_id,
                'user_id' => $user_id,
                'score' => $score,
                'grade' => $grade,
                'comments' => $comments,
                'scored_by' => get_current_user_id(),
                'scored_at' => current_time('mysql')
            ]
        );
    }
    
    if ($result === false) {
        wp_send_json_error(['message' => 'Failed to save score']);
    }
    
    // Send notification email to user
    $table_reg = $wpdb->prefix . 'company_registrations';
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_reg WHERE id = %d",
        $user_id
    ), ARRAY_A);
    
    if ($registration) {
        $to = $registration['email'];
        $subject = 'Your ESG Assessment Has Been Scored';
        $message = sprintf(
            "Dear %s,\n\nYour ESG assessment has been reviewed and scored.\n\nScore: %d/100\nGrade: %s\n\nComments: %s\n\nYou can now proceed to subscribe and receive your certificate.\n\nBest regards,\nCleanIndex Team",
            $registration['employee_name'],
            $score,
            $grade,
            $comments
        );
        wp_mail($to, $subject, $message);
    }
    
    wp_send_json_success([
        'message' => 'Score saved successfully',
        'score' => $score,
        'grade' => $grade
    ]);
}

// AJAX: Generate certificate after payment
add_action('wp_ajax_cip_generate_certificate', 'cip_generate_certificate_ajax');
add_action('wp_ajax_nopriv_cip_generate_certificate', 'cip_generate_certificate_ajax');
function cip_generate_certificate_ajax() {
    check_ajax_referer('cip_payment_direct', 'nonce');
    
    $user_id = intval($_POST['user_id']);
    
    if (!$user_id) {
        wp_send_json_error(['message' => 'Invalid user ID']);
    }
    
    // Check if certificate generator exists
    if (!function_exists('cip_generate_certificate_pdf')) {
        wp_send_json_error(['message' => 'Certificate generator not available']);
    }
    
    // Generate certificate
    $result = cip_generate_certificate_pdf($user_id);
    
    if ($result['success']) {
        wp_send_json_success([
            'message' => 'Certificate generated successfully',
            'certificate_url' => $result['url'],
            'certificate_number' => $result['certificate_number']
        ]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
}

// AJAX: Download assessment PDF
add_action('wp_ajax_cip_download_assessment_pdf', 'cip_download_assessment_pdf');
function cip_download_assessment_pdf() {
    check_ajax_referer('admin_action', 'nonce');
    
    if (!current_user_can('administrator') && !current_user_can('manager')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    $table_reg = $wpdb->prefix . 'company_registrations';
    $table_assess = $wpdb->prefix . 'company_assessments';
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_reg WHERE id = %d",
        $user_id
    ), ARRAY_A);
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_assess WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    if (!$assessment || !$registration) {
        wp_send_json_error(['message' => 'Assessment not found']);
    }
    
    // Check if PDF generator function exists
    if (!function_exists('cip_generate_assessment_pdf')) {
        wp_send_json_error(['message' => 'PDF generator not available']);
    }
    
    // Generate PDF
    $pdf_url = cip_generate_assessment_pdf($registration, $assessment);
    
    if ($pdf_url) {
        wp_send_json_success(['pdf_url' => $pdf_url]);
    } else {
        wp_send_json_error(['message' => 'Failed to generate PDF']);
    }
}

// Helper: Send admin approval notification
function cip_send_admin_approval_notification($registration) {
    $admin_email = get_option('admin_email');
    $subject = 'New Registration Pending Your Approval';
    $message = sprintf(
        "A new registration has been recommended for approval by the manager.\n\nCompany: %s\nContact: %s\nEmail: %s\n\nPlease review in the admin dashboard.",
        $registration['company_name'],
        $registration['employee_name'],
        $registration['email']
    );
    wp_mail($admin_email, $subject, $message);
}

// Helper: Send approval email
function cip_send_approval_email($registration) {
    $to = $registration['email'];
    $subject = 'Your Registration Has Been Approved!';
    $login_url = home_url('/cleanindex/login');
    $message = sprintf(
        "Dear %s,\n\nCongratulations! Your registration for %s has been approved.\n\nYou can now login and complete your ESG assessment:\n%s\n\nBest regards,\nCleanIndex Team",
        $registration['employee_name'],
        $registration['company_name'],
        $login_url
    );
    wp_mail($to, $subject, $message);
}

// Helper: Send rejection email
function cip_send_rejection_email($registration, $reason) {
    $to = $registration['email'];
    $subject = 'Registration Update';
    $message = sprintf(
        "Dear %s,\n\nThank you for your interest in CleanIndex.\n\nUnfortunately, we are unable to approve your registration at this time.\n\nReason: %s\n\nIf you have any questions, please contact our support team.\n\nBest regards,\nCleanIndex Team",
        $registration['employee_name'],
        $reason
    );
    wp_mail($to, $subject, $message);
}