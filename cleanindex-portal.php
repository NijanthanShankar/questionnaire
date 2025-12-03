<?php
/**
 * Plugin Name: CleanIndex Portal
 * Plugin URI: https://cleanindex.com
 * Description: Complete ESG certification portal with CSRD/ESRS compliant assessment, multi-role dashboards, payment processing, and certificate generation.
 * Version: 1.1.0
 * Author: CleanIndex / Brnd Guru
 * Author URI: https://brndguru.com
 * License: GPL-2.0+
 * Text Domain: cleanindex-portal
 * 
 * COMPLETE & FIXED VERSION WITH ALL FIXES APPLIED
 */

if (!defined('ABSPATH')) {
    exit;
}

// PLUGIN CONSTANTS
define('CIP_VERSION', '1.1.0');
define('CIP_PLUGIN_FILE', __FILE__);
define('CIP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CIP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CIP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CIP_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/cleanindex/');
define('CIP_UPLOAD_URL', wp_upload_dir()['baseurl'] . '/cleanindex/');

/**
 * INCLUDE REQUIRED FILES
 * FIX #1: Added subscription-handler.php
 */
$required_files = [
    'includes/db.php',
    'includes/roles.php',
    'includes/auth.php',
    'includes/email.php',
    'includes/upload-handler.php',
    'includes/helpers.php',
    'includes/pdf-generator.php',
    'includes/payment-handler.php',
    'includes/subscription-handler.php'  // ← FIX #1
];

$missing_files = [];

foreach ($required_files as $file) {
    $filepath = CIP_PLUGIN_DIR . $file;
    
    if (file_exists($filepath)) {
        require_once $filepath;
    } else {
        $missing_files[] = $file;
        error_log("CleanIndex Portal: Missing required file - {$file}");
    }
}

// Show admin notice if files are missing
if (!empty($missing_files)) {
    add_action('admin_notices', function() use ($missing_files) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>CleanIndex Portal Error:</strong> Missing required files:<br>';
        echo '<ul style="margin-left: 20px;">';
        foreach ($missing_files as $file) {
            echo '<li><code>' . esc_html($file) . '</code></li>';
        }
        echo '</ul>';
        echo 'Please ensure all plugin files are properly uploaded.';
        echo '</p></div>';
    });
}

/**
 * ========================================
 * PLUGIN ACTIVATION - COMPLETE & FIXED
 * FIX #1, #2, #5: All activation fixes applied
 * ========================================
 */
register_activation_hook(__FILE__, 'cip_activate_plugin');

function cip_activate_plugin() {
    global $wpdb;

    // FIX #5: Wrap entire activation in try-catch
    try {
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_registrations = $wpdb->prefix . 'company_registrations';
        $table_assessments = $wpdb->prefix . 'company_assessments';
        
        // Backup existing data if tables exist
        $backup_registrations = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_registrations'") === $table_registrations) {
            $backup_registrations = $wpdb->get_results("SELECT * FROM $table_registrations", ARRAY_A);
        }
        
        $backup_assessments = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_assessments'") === $table_assessments) {
            $backup_assessments = $wpdb->get_results("SELECT * FROM $table_assessments", ARRAY_A);
        }
        
        // Drop existing tables to recreate with correct structure
        $wpdb->query("DROP TABLE IF EXISTS $table_registrations");
        $wpdb->query("DROP TABLE IF EXISTS $table_assessments");
        
        // Create registrations table with COMPLETE structure
        $sql_registrations = "CREATE TABLE $table_registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(255) NOT NULL,
            employee_name VARCHAR(255) NOT NULL,
            org_type VARCHAR(100) NOT NULL,
            industry VARCHAR(255) NOT NULL,
            country VARCHAR(100) NOT NULL,
            working_desc TEXT,
            num_employees INT,
            culture VARCHAR(255),
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            status ENUM('pending_manager_review','pending_admin_approval','approved','rejected') DEFAULT 'pending_manager_review',
            manager_notes TEXT,
            supporting_files LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_registrations);
        
        // Create assessments table with COMPLETE structure
        $sql_assessments = "CREATE TABLE $table_assessments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            assessment_json LONGTEXT,
            progress INT DEFAULT 0,
            submitted_at DATETIME,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_progress (progress)
        ) $charset_collate;";
        
        dbDelta($sql_assessments);
        
        // Restore backup data if any
        if (!empty($backup_registrations)) {
            foreach ($backup_registrations as $row) {
                $insert_data = [
                    'company_name' => isset($row['company_name']) ? $row['company_name'] : '',
                    'employee_name' => isset($row['employee_name']) ? $row['employee_name'] : 'Unknown',
                    'org_type' => isset($row['org_type']) ? $row['org_type'] : 'Company',
                    'industry' => isset($row['industry']) ? $row['industry'] : 'Other',
                    'country' => isset($row['country']) ? $row['country'] : 'Other',
                    'working_desc' => isset($row['working_desc']) ? $row['working_desc'] : '',
                    'num_employees' => isset($row['num_employees']) ? intval($row['num_employees']) : 0,
                    'culture' => isset($row['culture']) ? $row['culture'] : '',
                    'email' => isset($row['email']) ? $row['email'] : '',
                    'password' => isset($row['password']) ? $row['password'] : '',
                    'status' => isset($row['status']) ? $row['status'] : 'pending_manager_review',
                    'manager_notes' => isset($row['manager_notes']) ? $row['manager_notes'] : '',
                    'supporting_files' => isset($row['supporting_files']) ? $row['supporting_files'] : '[]'
                ];
                
                if (!empty($insert_data['email'])) {
                    $wpdb->insert($table_registrations, $insert_data);
                }
            }
        }
        
        if (!empty($backup_assessments)) {
            foreach ($backup_assessments as $row) {
                if (isset($row['user_id']) && $row['user_id'] > 0) {
                    $wpdb->insert($table_assessments, [
                        'user_id' => intval($row['user_id']),
                        'assessment_json' => isset($row['assessment_json']) ? $row['assessment_json'] : '{}',
                        'progress' => isset($row['progress']) ? intval($row['progress']) : 0,
                        'submitted_at' => isset($row['submitted_at']) ? $row['submitted_at'] : null
                    ]);
                }
            }
        }
        
        // FIX #1: Create subscriptions table
        if (function_exists('cip_create_subscriptions_table')) {
            cip_create_subscriptions_table();
        } else {
            error_log('CIP Warning: cip_create_subscriptions_table function not found');
        }
        
        // FIX #2: Set default currency
        $default_currency = get_option('cip_default_currency');
        if (empty($default_currency)) {
            update_option('cip_default_currency', 'EUR');
            error_log('CIP: Default currency set to EUR');
        }
        
        // Create custom roles
        if (function_exists('cip_create_custom_roles')) {
            cip_create_custom_roles();
        }
        
        // Create upload directory
        if (!file_exists(CIP_UPLOAD_DIR)) {
            wp_mkdir_p(CIP_UPLOAD_DIR);
            wp_mkdir_p(CIP_UPLOAD_DIR . 'registration/');
            wp_mkdir_p(CIP_UPLOAD_DIR . 'assessments/');
            wp_mkdir_p(CIP_UPLOAD_DIR . 'certificates/');
        }
        
        // Initialize default questions
        if (!get_option('cip_assessment_questions')) {
            if (function_exists('cip_get_default_questions')) {
                update_option('cip_assessment_questions', cip_get_default_questions());
            }
        }
        
        // Add rewrite rules
        cip_add_rewrite_rules();
        flush_rewrite_rules();
        
        // Schedule cron jobs
        cip_schedule_cron_jobs();
        
        // Set activation flag
        set_transient('cip_activation_redirect', true, 30);
        set_transient('cip_flush_rewrite_rules_flag', true, 60);
        
        // Clear cache
        wp_cache_flush();
        
        error_log('CleanIndex Portal: Plugin activated successfully (v' . CIP_VERSION . ')');
        
    } catch (Exception $e) {
        // FIX #5: Error handling instead of crash
        error_log('CleanIndex Portal Activation Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        // Show error notice instead of dying
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>CleanIndex Portal Warning:</strong> ' . esc_html($e->getMessage());
            echo '<br>Check error logs for details.';
            echo '</p></div>';
        });
    }
}

/**
 * ========================================
 * PLUGIN DEACTIVATION
 * ========================================
 */
register_deactivation_hook(__FILE__, 'cip_deactivate_plugin');

function cip_deactivate_plugin() {
    flush_rewrite_rules();
    delete_transient('cip_activation_redirect');
    delete_transient('cip_flush_rewrite_rules_flag');
    cip_unschedule_cron_jobs();
    error_log('CleanIndex Portal: Plugin deactivated');
}

/**
 * ========================================
 * URL REWRITE RULES
 * ========================================
 */
add_action('init', 'cip_add_rewrite_rules');

function cip_add_rewrite_rules() {
    // Define all plugin pages
    $pages = [
        'register',
        'login',
        'dashboard',
        'assessment',
        'manager',
        'manager-register',
        'manager-login',
        'admin-portal',
        'reset-password',
        'pricing',
        'checkout',
        'certificate',
        'payment-success',
        'download-assessment-pdf',
        'download-certificate'
    ];
    
    // Add rewrite rule for each page
    foreach ($pages as $page) {
        add_rewrite_rule(
            '^cleanindex/' . $page . '/?$',
            'index.php?cip_page=' . $page,
            'top'
        );
    }
    
    // Certificate verification
    add_rewrite_rule(
        '^cleanindex/verify/([^/]+)/?$',
        'index.php?cip_page=verify&cert_number=$matches[1]',
        'top'
    );
}

/**
 * ========================================
 * QUERY VARS
 * ========================================
 */
add_filter('query_vars', 'cip_query_vars');

function cip_query_vars($vars) {
    $vars[] = 'cip_page';
    $vars[] = 'cert_number';
    return $vars;
}

/**
 * ========================================
 * TEMPLATE REDIRECT
 * ========================================
 */
add_action('template_redirect', 'cip_template_redirect');

function cip_template_redirect() {
    $page = get_query_var('cip_page');
    
    if (!$page) {
        return;
    }
    
    // Handle PDF downloads
    if ($page === 'download-assessment-pdf') {
        cip_download_assessment_pdf();
        exit;
    }
    
    if ($page === 'download-certificate') {
        cip_download_certificate();
        exit;
    }
    
    if ($page === 'verify') {
        cip_verify_certificate();
        exit;
    }
    
    // Define template map
    $template_map = [
        'register' => 'pages/register.php',
        'login' => 'pages/login.php',
        'dashboard' => 'pages/user-dashboard.php',
        'assessment' => 'pages/assessment.php',
        'manager' => 'pages/manager-dashboard-fixed.php',
        'manager-register' => 'pages/manager-register.php',
        'manager-login' => 'pages/manager-login.php',
        'admin-portal' => 'pages/admin-dashboard.php',
        'pricing' => 'pages/pricing.php',
        'checkout' => 'pages/checkout.php',
        'certificate' => 'pages/certificate-view.php',
        'payment-success' => 'pages/payment-success.php'
    ];
    
    // Check if page exists in map
    if (!isset($template_map[$page])) {
        return;
    }
    
    $template_file = CIP_PLUGIN_DIR . $template_map[$page];
    
    // Check if template file exists
    if (file_exists($template_file)) {
        // Load the template
        include $template_file;
        exit;
    } else {
        // Template file not found
        wp_die(
            '<h1>Template File Not Found</h1>' .
            '<p><strong>Page:</strong> ' . esc_html($page) . '</p>' .
            '<p><strong>Expected file:</strong> <code>' . esc_html($template_file) . '</code></p>' .
            '<p>Please ensure the file exists in your plugin directory.</p>' .
            '<p><a href="' . home_url() . '">← Back to Home</a></p>',
            'Template Not Found'
        );
    }
}

/**
 * ========================================
 * PDF DOWNLOAD HANDLERS
 * ========================================
 */
function cip_download_assessment_pdf() {
    if (!is_user_logged_in()) {
        wp_die('Not authorized');
    }
    
    $user = wp_get_current_user();
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE email = %s",
        $user->user_email
    ), ARRAY_A);
    
    if (!$registration) {
        wp_die('Registration not found');
    }
    
    if (class_exists('CIP_PDF_Generator')) {
        $pdf_gen = new CIP_PDF_Generator();
        $result = $pdf_gen->generate_assessment_pdf($registration['id']);
        
        if ($result['success']) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="ESG_Assessment.pdf"');
            readfile($result['path']);
            exit;
        }
    }
    
    wp_die('Error generating PDF');
}

function cip_download_certificate() {
    if (!is_user_logged_in()) {
        wp_die('Not authorized');
    }
    
    $cert_url = get_user_meta(get_current_user_id(), 'cip_certificate_url', true);
    
    if (!$cert_url) {
        wp_die('Certificate not found');
    }
    
    $cert_path = str_replace(CIP_UPLOAD_URL, CIP_UPLOAD_DIR, $cert_url);
    
    if (file_exists($cert_path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="ESG_Certificate.pdf"');
        readfile($cert_path);
        exit;
    } else {
        wp_die('Certificate file not found');
    }
}

function cip_verify_certificate() {
    $cert_number = get_query_var('cert_number');
    
    if (!$cert_number) {
        wp_die('Invalid certificate number');
    }
    
    global $wpdb;
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'cip_certificate_number' AND meta_value = %s",
        $cert_number
    ));
    
    if ($user_id) {
        $grade = get_user_meta($user_id, 'cip_certificate_grade', true);
        $table = $wpdb->prefix . 'company_registrations';
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = (SELECT user_email FROM {$wpdb->users} WHERE ID = %d)",
            $user_id
        ), ARRAY_A);
        
        echo '<html><head><title>Certificate Verification</title><style>
            body { font-family: Arial; padding: 40px; text-align: center; }
            .verified { color: #4CAF50; font-size: 24px; margin: 20px 0; }
            .cert-info { background: #f5f5f5; padding: 20px; border-radius: 8px; display: inline-block; }
        </style></head><body>';
        echo '<h1>✓ Certificate Verified</h1>';
        echo '<div class="verified">This is a valid CleanIndex ESG Certificate</div>';
        echo '<div class="cert-info">';
        echo '<strong>Certificate Number:</strong> ' . esc_html($cert_number) . '<br>';
        echo '<strong>Company:</strong> ' . esc_html($registration['company_name']) . '<br>';
        echo '<strong>Grade:</strong> ' . esc_html($grade) . '<br>';
        echo '</div>';
        echo '</body></html>';
        exit;
    } else {
        wp_die('Certificate not found');
    }
}

/**
 * ========================================
 * ENQUEUE ASSETS
 * ========================================
 */
add_action('wp_enqueue_scripts', 'cip_enqueue_assets');

function cip_enqueue_assets() {
    // Only load on plugin pages
    if (!cip_is_plugin_page()) {
        return;
    }
    
    // Stylesheet
    wp_enqueue_style(
        'cip-style',
        CIP_PLUGIN_URL . 'css/style.css',
        [],
        CIP_VERSION
    );
    
    // Google Fonts
    wp_enqueue_style(
        'cip-fonts',
        'https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700&family=Open+Sans:wght@400;600&family=Inter:wght@400;500;600&display=swap',
        [],
        null
    );
    
    // JavaScript
    wp_enqueue_script(
        'cip-script',
        CIP_PLUGIN_URL . 'js/script.js',
        ['jquery'],
        CIP_VERSION,
        true
    );
    
    // Localize script for AJAX
    wp_localize_script('cip-script', 'cipAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cip_nonce'),
        'styleUrl' => CIP_PLUGIN_URL . 'css/style.css'
    ]);
}

/**
 * ========================================
 * HELPER FUNCTION: Check if current page is a plugin page
 * ========================================
 */
function cip_is_plugin_page() {
    $page = get_query_var('cip_page');
    return !empty($page);
}

/**
 * ========================================
 * ADMIN MENU
 * ========================================
 */
add_action('admin_menu', 'cip_admin_menu');

function cip_admin_menu() {
    // Main menu
    add_menu_page(
        __('CleanIndex Portal', 'cleanindex-portal'),
        __('CleanIndex', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-portal',
        'cip_admin_settings_page',
        'dashicons-admin-site-alt3',
        30
    );
    
    // Settings submenu
    add_submenu_page(
        'cleanindex-portal',
        __('Settings', 'cleanindex-portal'),
        __('Settings', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-portal',
        'cip_admin_settings_page'
    );
    
    // Questions Manager submenu
    add_submenu_page(
        'cleanindex-portal',
        __('Manage Questions', 'cleanindex-portal'),
        __('Manage Questions', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-questions',
        function() {
            if (file_exists(CIP_PLUGIN_DIR . 'admin/questions-manager-enhanced.php')) {
                include CIP_PLUGIN_DIR . 'admin/questions-manager-enhanced.php';
            }
        }
    );
    
    // Submissions submenu
    add_submenu_page(
        'cleanindex-portal',
        __('Submissions', 'cleanindex-portal'),
        __('Submissions', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-submissions',
        'cip_admin_submissions_page'
    );
    
    // System Info submenu
    add_submenu_page(
        'cleanindex-portal',
        __('System Info', 'cleanindex-portal'),
        __('System Info', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-system',
        'cip_admin_system_page'
    );
}

/**
 * ========================================
 * ADMIN PAGES
 * ========================================
 */

/**
 * Main Settings Page
 */
function cip_admin_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'cleanindex-portal'));
    }
    
    // Include the settings page template
    if (file_exists(CIP_PLUGIN_DIR . 'admin/settings-page.php')) {
        include CIP_PLUGIN_DIR . 'admin/settings-page.php';
    }
}

/**
 * Submissions Page
 */
function cip_admin_submissions_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'cleanindex-portal'));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    // Get filter
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
    
    // Build query
    $where = "1=1";
    if ($status_filter !== 'all') {
        $where .= $wpdb->prepare(" AND status = %s", $status_filter);
    }
    
    $submissions = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT 50", ARRAY_A);
    
    // Include the submissions page template
    if (file_exists(CIP_PLUGIN_DIR . 'admin/submissions-page.php')) {
        include CIP_PLUGIN_DIR . 'admin/submissions-page.php';
    }
}

/**
 * System Info Page
 */
function cip_admin_system_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'cleanindex-portal'));
    }
    
    // Include the system info page template
    if (file_exists(CIP_PLUGIN_DIR . 'admin/system-info-page.php')) {
        include CIP_PLUGIN_DIR . 'admin/system-info-page.php';
    }
}

/**
 * ========================================
 * AJAX HANDLERS FOR ADMIN
 * ========================================
 */

// Approve Registration
add_action('wp_ajax_cip_approve_registration', 'cip_ajax_approve_registration');

function cip_ajax_approve_registration() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'cip_registration_id' LIMIT 1)",
        $user_id
    ), ARRAY_A);
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
        return;
    }
    
    // Update status
    $wpdb->update(
        $table,
        ['status' => 'approved'],
        ['id' => $registration['id']]
    );
    
    // Update user meta
    update_user_meta($user_id, 'cip_status', 'approved');
    
    // Send approval email
    if (function_exists('cip_send_approval_email')) {
        cip_send_approval_email($registration);
    }
    
    wp_send_json_success(['message' => 'Registration approved successfully']);
}

// Reject Registration
add_action('wp_ajax_cip_reject_registration', 'cip_ajax_reject_registration');

function cip_ajax_reject_registration() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    $reason = sanitize_textarea_field($_POST['reason']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'cip_registration_id' LIMIT 1)",
        $user_id
    ), ARRAY_A);
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
        return;
    }
    
    // Update status
    $wpdb->update(
        $table,
        [
            'status' => 'rejected',
            'manager_notes' => $reason
        ],
        ['id' => $registration['id']]
    );
    
    // Update user meta
    update_user_meta($user_id, 'cip_status', 'rejected');
    
    // Send rejection email
    if (function_exists('cip_send_rejection_email')) {
        cip_send_rejection_email($registration, $reason);
    }
    
    wp_send_json_success(['message' => 'Registration rejected']);
}

// Get Assessment Data
add_action('wp_ajax_cip_get_assessment_data', 'cip_ajax_get_assessment_data');

function cip_ajax_get_assessment_data() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    if (!$assessment) {
        wp_send_json_error(['message' => 'Assessment not found']);
        return;
    }
    
    $data = json_decode($assessment['assessment_json'], true);
    
    wp_send_json_success([
        'assessment' => $data,
        'progress' => $assessment['progress'],
        'submitted_at' => $assessment['submitted_at']
    ]);
}

/**
 * ========================================
 * AJAX HANDLERS FOR USER ASSESSMENT
 * ========================================
 */

// Save Assessment Progress
add_action('wp_ajax_cip_save_assessment', 'cip_ajax_save_assessment');

function cip_ajax_save_assessment() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
        return;
    }
    
    $user_id = get_current_user_id();
    $step = intval($_POST['step']);
    $data = $_POST['data'];
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    // Get existing assessment
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    if ($existing) {
        // Update existing
        $current_data = json_decode($existing['assessment_json'], true);
        $current_data = array_merge($current_data, $data);
        
        $wpdb->update(
            $table,
            [
                'assessment_json' => json_encode($current_data),
                'progress' => max($step, $existing['progress'])
            ],
            ['user_id' => $user_id]
        );
    } else {
        // Create new
        $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'assessment_json' => json_encode($data),
                'progress' => $step
            ]
        );
    }
    
    wp_send_json_success(['message' => 'Progress saved']);
}

// Submit Assessment
add_action('wp_ajax_cip_submit_assessment', 'cip_ajax_submit_assessment');

function cip_ajax_submit_assessment() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
        return;
    }
    
    $user_id = get_current_user_id();
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    $wpdb->update(
        $table,
        [
            'progress' => 5,
            'submitted_at' => current_time('mysql')
        ],
        ['user_id' => $user_id]
    );
    
    // Update user status
    update_user_meta($user_id, 'cip_assessment_submitted', true);
    
    wp_send_json_success(['message' => 'Assessment submitted successfully']);
}

// Load Assessment Data
add_action('wp_ajax_cip_load_assessment', 'cip_ajax_load_assessment');

function cip_ajax_load_assessment() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
        return;
    }
    
    $user_id = get_current_user_id();
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    if ($assessment) {
        $data = json_decode($assessment['assessment_json'], true);
        wp_send_json_success([
            'data' => $data,
            'progress' => $assessment['progress']
        ]);
    } else {
        wp_send_json_success([
            'data' => [],
            'progress' => 0
        ]);
    }
}

/**
 * ========================================
 * HELPER FUNCTIONS
 * ========================================
 */

/**
 * Get registration by user ID
 */
function cip_get_registration_by_user($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $user = get_userdata($user_id);
    if (!$user) {
        return null;
    }
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE email = %s",
        $user->user_email
    ), ARRAY_A);
}

/**
 * Get assessment by user ID
 */
function cip_get_assessment_by_user($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
}

/**
 * Calculate assessment score
 */
function cip_calculate_assessment_score($assessment_data) {
    if (!is_array($assessment_data)) {
        return 0;
    }
    
    $total_questions = 0;
    $answered_questions = 0;
    
    foreach ($assessment_data as $key => $value) {
        if (strpos($key, 'q') === 0) {
            $total_questions++;
            if (!empty($value) && $value !== 'not_applicable') {
                $answered_questions++;
            }
        }
    }
    
    return $total_questions > 0 ? round(($answered_questions / $total_questions) * 100) : 0;
}

/**
 * Get certificate grade based on score
 */
function cip_get_certificate_grade($score) {
    $esg3_threshold = get_option('cip_cert_grade_esg3', 90);
    $esg2_threshold = get_option('cip_cert_grade_esg2', 70);
    $esg1_threshold = get_option('cip_cert_grade_esg1', 50);
    
    if ($score >= $esg3_threshold) {
        return 'ESG 3';
    } elseif ($score >= $esg2_threshold) {
        return 'ESG 2';
    } elseif ($score >= $esg1_threshold) {
        return 'ESG 1';
    } else {
        return 'Below Standard';
    }
}

/**
 * Format file size
 */
function cip_format_filesize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Get user's subscription status
 */
function cip_get_subscription_status($user_id) {
    $status = get_user_meta($user_id, 'cip_subscription_status', true);
    $end_date = get_user_meta($user_id, 'cip_subscription_end', true);
    
    if (empty($status)) {
        return 'none';
    }
    
    if ($status === 'active' && $end_date) {
        if (strtotime($end_date) < time()) {
            update_user_meta($user_id, 'cip_subscription_status', 'expired');
            return 'expired';
        }
    }
    
    return $status;
}

/**
 * Check if user has active subscription
 */
function cip_has_active_subscription($user_id) {
    $status = cip_get_subscription_status($user_id);
    return $status === 'active';
}

/**
 * Get user's subscription plan
 */
function cip_get_subscription_plan($user_id) {
    return get_user_meta($user_id, 'cip_subscription_plan', true);
}

/**
 * Create notification for user
 */
function cip_create_notification($user_id, $title, $message, $type = 'info', $link = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_notifications';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return false;
    }
    
    return $wpdb->insert(
        $table,
        [
            'user_id' => $user_id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'is_read' => 0
        ]
    );
}

/**
 * Get unread notifications count
 */
function cip_get_unread_notifications_count($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_notifications';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return 0;
    }
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0",
        $user_id
    ));
}

/**
 * Get plugin status summary
 */
function cip_get_plugin_status() {
    global $wpdb;
    
    $status = [];
    
    // Check tables
    $table1 = $wpdb->prefix . 'company_registrations';
    $table2 = $wpdb->prefix . 'company_assessments';
    $table3 = $wpdb->prefix . 'cip_subscriptions';
    
    $status['tables'] = [
        'registrations' => $wpdb->get_var("SHOW TABLES LIKE '$table1'") === $table1,
        'assessments' => $wpdb->get_var("SHOW TABLES LIKE '$table2'") === $table2,
        'subscriptions' => $wpdb->get_var("SHOW TABLES LIKE '$table3'") === $table3
    ];
    
    // Check upload directory
    $status['upload_dir'] = [
        'exists' => file_exists(CIP_UPLOAD_DIR),
        'writable' => is_writable(CIP_UPLOAD_DIR)
    ];
    
    // Get counts
    if ($status['tables']['registrations']) {
        $status['counts'] = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table1"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $table1 WHERE status = 'pending_manager_review'"),
            'approved' => $wpdb->get_var("SELECT COUNT(*) FROM $table1 WHERE status = 'approved'")
        ];
    }
    
    return $status;
}

/**
 * Log plugin activity
 */
function cip_log($message, $type = 'info') {
    if (get_option('cip_enable_logging', false)) {
        error_log("CleanIndex Portal [{$type}]: {$message}");
    }
}

/**
 * Sanitize assessment data
 */
function cip_sanitize_assessment_data($data) {
    $sanitized = [];
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = cip_sanitize_assessment_data($value);
        } else {
            $sanitized[sanitize_text_field($key)] = sanitize_textarea_field($value);
        }
    }
    
    return $sanitized;
}

/**
 * Get allowed file types
 */
function cip_get_allowed_file_types() {
    $types = get_option('cip_allowed_file_types', 'pdf,doc,docx');
    return explode(',', $types);
}

/**
 * Get max file size
 */
function cip_get_max_file_size() {
    return get_option('cip_max_file_size', 10) * 1024 * 1024; // Convert MB to bytes
}

/**
 * Validate uploaded file
 */
function cip_validate_uploaded_file($file) {
    $allowed_types = cip_get_allowed_file_types();
    $max_size = cip_get_max_file_size();
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    return ['success' => true];
}

/**
 * ========================================
 * CRON JOBS
 * ========================================
 */

// Schedule cron jobs
function cip_schedule_cron_jobs() {
    if (!wp_next_scheduled('cip_check_expired_subscriptions')) {
        wp_schedule_event(time(), 'daily', 'cip_check_expired_subscriptions');
    }
}

// Remove cron jobs
function cip_unschedule_cron_jobs() {
    wp_clear_scheduled_hook('cip_check_expired_subscriptions');
}

// Check for expired subscriptions
add_action('cip_check_expired_subscriptions', 'cip_check_expired_subscriptions_callback');

function cip_check_expired_subscriptions_callback() {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return;
    }
    
    // Find expired subscriptions
    $expired = $wpdb->get_results(
        "SELECT * FROM $table WHERE status = 'active' AND end_date < NOW()"
    );
    
    foreach ($expired as $subscription) {
        // Update subscription status
        $wpdb->update(
            $table,
            ['status' => 'expired'],
            ['id' => $subscription->id]
        );
        
        // Update user meta
        update_user_meta($subscription->user_id, 'cip_subscription_status', 'expired');
        
        // Send notification
        cip_create_notification(
            $subscription->user_id,
            'Subscription Expired',
            'Your subscription has expired. Please renew to continue accessing your certificates.',
            'warning',
            home_url('/cleanindex/pricing')
        );
    }
}

/**
 * ========================================
 * SHORTCODES (Optional)
 * ========================================
 */

// [cleanindex_login]
add_shortcode('cleanindex_login', 'cip_shortcode_login');

function cip_shortcode_login() {
    ob_start();
    echo '<div style="padding: 20px; background: white; border-radius: 8px;">';
    echo '<p>Please visit <a href="' . home_url('/cleanindex/login') . '">CleanIndex Login</a></p>';
    echo '</div>';
    return ob_get_clean();
}

// [cleanindex_register]
add_shortcode('cleanindex_register', 'cip_shortcode_register');

function cip_shortcode_register() {
    ob_start();
    echo '<div style="padding: 20px; background: white; border-radius: 8px;">';
    echo '<p>Please visit <a href="' . home_url('/cleanindex/register') . '">CleanIndex Registration</a></p>';
    echo '</div>';
    return ob_get_clean();
}

/**
 * ========================================
 * ADMIN NOTICES
 * ========================================
 */
add_action('admin_notices', 'cip_admin_notices');

function cip_admin_notices() {
    // Permalink refresh notice
    if (get_transient('cip_flush_rewrite_rules_flag')) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>CleanIndex Portal:</strong> 
                To ensure all plugin pages work correctly, please 
                <a href="<?php echo admin_url('options-permalink.php'); ?>">go to Settings > Permalinks</a> 
                and click "Save Changes".
            </p>
        </div>
        <?php
    }
    
    // Check if permalink structure is set
    $permalink_structure = get_option('permalink_structure');
    if (empty($permalink_structure)) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>CleanIndex Portal:</strong> 
                Pretty permalinks are required for this plugin to work. 
                <a href="<?php echo admin_url('options-permalink.php'); ?>">Please configure your permalink settings</a>.
            </p>
        </div>
        <?php
    }
}

/**
 * ========================================
 * ACTIVATION REDIRECT
 * ========================================
 */
add_action('admin_init', 'cip_activation_redirect');

function cip_activation_redirect() {
    if (get_transient('cip_activation_redirect')) {
        delete_transient('cip_activation_redirect');
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('admin.php?page=cleanindex-portal&activated=true'));
            exit;
        }
    }
}

/**
 * ========================================
 * CLEANUP ON UNINSTALL
 * ========================================
 */
register_uninstall_hook(__FILE__, 'cip_plugin_uninstall');

function cip_plugin_uninstall() {
    // Note: This only runs when plugin is DELETED, not just deactivated
    
    global $wpdb;
    
    // Optionally delete tables (commented out for safety)
    // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}company_registrations");
    // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}company_assessments");
    // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cip_subscriptions");
    
    // Optionally delete options
    // delete_option('cip_default_currency');
    // delete_option('cip_pricing_plans');
    
    // Remove cron jobs
    wp_clear_scheduled_hook('cip_check_expired_subscriptions');
}