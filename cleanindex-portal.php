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
 * FIXED VERSION - Duplicate functions removed
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
 * ========================================
 * INCLUDE REQUIRED FILES
 * ========================================
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
    'includes/subscription-handler.php'
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
 * PLUGIN ACTIVATION
 * ========================================
 */
register_activation_hook(__FILE__, 'cip_activate_plugin');

function cip_activate_plugin() {
    global $wpdb;

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
        
        // Create registrations table
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
        
        // Create assessments table
        $sql_assessments = "CREATE TABLE $table_assessments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            assessment_json LONGTEXT,
            progress INT DEFAULT 0,
            submitted_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_user (user_id),
            INDEX idx_submitted (submitted_at)
        ) $charset_collate;";
        
        dbDelta($sql_assessments);
        
        // Restore backed up data
        if (!empty($backup_registrations)) {
            foreach ($backup_registrations as $row) {
                $wpdb->insert($table_registrations, [
                    'company_name' => $row['company_name'],
                    'employee_name' => $row['employee_name'],
                    'org_type' => $row['org_type'],
                    'industry' => $row['industry'],
                    'country' => $row['country'],
                    'working_desc' => isset($row['working_desc']) ? $row['working_desc'] : '',
                    'num_employees' => isset($row['num_employees']) ? $row['num_employees'] : 0,
                    'culture' => isset($row['culture']) ? $row['culture'] : '',
                    'email' => $row['email'],
                    'password' => $row['password'],
                    'status' => $row['status'],
                    'manager_notes' => isset($row['manager_notes']) ? $row['manager_notes'] : '',
                    'supporting_files' => isset($row['supporting_files']) ? $row['supporting_files'] : null,
                    'created_at' => $row['created_at']
                ]);
            }
        }
        
        if (!empty($backup_assessments)) {
            foreach ($backup_assessments as $row) {
                if (isset($row['user_id'])) {
                    $wpdb->insert($table_assessments, [
                        'user_id' => $row['user_id'],
                        'assessment_json' => isset($row['assessment_json']) ? $row['assessment_json'] : '{}',
                        'progress' => isset($row['progress']) ? intval($row['progress']) : 0,
                        'submitted_at' => isset($row['submitted_at']) ? $row['submitted_at'] : null
                    ]);
                }
            }
        }
        
        // Create subscriptions table
        if (function_exists('cip_create_subscriptions_table')) {
            cip_create_subscriptions_table();
        }
        
        // Set default currency
        $default_currency = get_option('cip_default_currency');
        if (empty($default_currency)) {
            update_option('cip_default_currency', 'EUR');
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
        if (function_exists('cip_schedule_cron_jobs')) {
            cip_schedule_cron_jobs();
        }
        
        // Set activation flags
        set_transient('cip_activation_redirect', true, 30);
        set_transient('cip_flush_rewrite_rules_flag', true, 60);
        
        // Clear cache
        wp_cache_flush();
        
        error_log('CleanIndex Portal: Plugin activated successfully (v' . CIP_VERSION . ')');
        
    } catch (Exception $e) {
        error_log('CleanIndex Portal Activation Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
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
    
    if (function_exists('cip_unschedule_cron_jobs')) {
        cip_unschedule_cron_jobs();
    }
    
    error_log('CleanIndex Portal: Plugin deactivated');
}

/**
 * ========================================
 * URL REWRITE RULES
 * ========================================
 */
add_action('init', 'cip_add_rewrite_rules');

function cip_add_rewrite_rules() {
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
        'payment-success' => 'pages/payment-success.php',
        'reset-password' => 'pages/reset-password.php'
    ];
    
    if (!isset($template_map[$page])) {
        return;
    }
    
    $template_file = CIP_PLUGIN_DIR . $template_map[$page];
    
    if (file_exists($template_file)) {
        include $template_file;
        exit;
    } else {
        wp_die(
            '<h1>Template File Not Found</h1>' .
            '<p><strong>Page:</strong> ' . esc_html($page) . '</p>' .
            '<p><strong>Expected file:</strong> <code>' . esc_html($template_file) . '</code></p>' .
            '<p>Please ensure the file exists in your plugin directory.</p>',
            'Template Not Found'
        );
    }
}

/**
 * ========================================
 * ENQUEUE SCRIPTS & STYLES
 * ========================================
 */
add_action('wp_enqueue_scripts', 'cip_enqueue_scripts');

function cip_enqueue_scripts() {
    if (!cip_is_plugin_page()) {
        return;
    }
    
    wp_enqueue_style(
        'cip-style',
        CIP_PLUGIN_URL . 'css/style.css',
        [],
        CIP_VERSION
    );
    
    wp_enqueue_script(
        'cip-script',
        CIP_PLUGIN_URL . 'js/script.js',
        ['jquery'],
        CIP_VERSION,
        true
    );
    
    wp_localize_script('cip-script', 'cipAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cip_nonce'),
        'styleUrl' => CIP_PLUGIN_URL . 'css/style.css'
    ]);
}

/**
 * ========================================
 * ADMIN MENU
 * ========================================
 */
add_action('admin_menu', 'cip_admin_menu');

function cip_admin_menu() {
    add_menu_page(
        __('CleanIndex Portal', 'cleanindex-portal'),
        __('CleanIndex', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-portal',
        'cip_admin_settings_page',
        'dashicons-admin-site-alt3',
        30
    );
    
    add_submenu_page(
        'cleanindex-portal',
        __('Settings', 'cleanindex-portal'),
        __('Settings', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-portal',
        'cip_admin_settings_page'
    );
    
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
    
    add_submenu_page(
        'cleanindex-portal',
        __('Submissions', 'cleanindex-portal'),
        __('Submissions', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-submissions',
        'cip_admin_submissions_page'
    );
    
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
function cip_admin_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    if (file_exists(CIP_PLUGIN_DIR . 'admin/settings-page.php')) {
        include CIP_PLUGIN_DIR . 'admin/settings-page.php';
    }
}

function cip_admin_submissions_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
    
    $where = "1=1";
    if ($status_filter !== 'all') {
        $where .= $wpdb->prepare(" AND status = %s", $status_filter);
    }
    
    $submissions = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT 50", ARRAY_A);
    
    if (file_exists(CIP_PLUGIN_DIR . 'admin/submissions-page.php')) {
        include CIP_PLUGIN_DIR . 'admin/submissions-page.php';
    }
}

function cip_admin_system_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    if (file_exists(CIP_PLUGIN_DIR . 'admin/system-info-page.php')) {
        include CIP_PLUGIN_DIR . 'admin/system-info-page.php';
    }
}

/**
 * ========================================
 * AJAX HANDLERS - ADMIN
 * ========================================
 */
add_action('wp_ajax_cip_approve_registration', 'cip_ajax_approve_registration');

function cip_ajax_approve_registration() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $result = $wpdb->update(
        $table,
        ['status' => 'approved'],
        ['id' => $user_id]
    );
    
    if ($result !== false) {
        $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $user_id), ARRAY_A);
        
        if ($registration && function_exists('cip_send_approval_email')) {
            cip_send_approval_email($registration);
        }
        
        wp_send_json_success(['message' => 'Registration approved']);
    } else {
        wp_send_json_error(['message' => 'Failed to approve']);
    }
}

add_action('wp_ajax_cip_reject_registration', 'cip_ajax_reject_registration');

function cip_ajax_reject_registration() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $user_id = intval($_POST['user_id']);
    $reason = sanitize_textarea_field($_POST['reason']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $result = $wpdb->update(
        $table,
        [
            'status' => 'rejected',
            'manager_notes' => $reason
        ],
        ['id' => $user_id]
    );
    
    if ($result !== false) {
        $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $user_id), ARRAY_A);
        
        if ($registration && function_exists('cip_send_rejection_email')) {
            cip_send_rejection_email($registration, $reason);
        }
        
        wp_send_json_success(['message' => 'Registration rejected']);
    } else {
        wp_send_json_error(['message' => 'Failed to reject']);
    }
}

add_action('wp_ajax_cip_get_submission_details', 'cip_ajax_get_submission_details');

function cip_ajax_get_submission_details() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    $table_assessments = $wpdb->prefix . 'company_assessments';
    
    $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $user_id), ARRAY_A);
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    $wp_user = get_user_by('email', $registration['email']);
    $assessment = null;
    
    if ($wp_user) {
        $assessment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_assessments WHERE user_id = %d",
            $wp_user->ID
        ), ARRAY_A);
    }
    
    wp_send_json_success([
        'registration' => $registration,
        'assessment' => $assessment
    ]);
}

/**
 * ========================================
 * AJAX HANDLERS - USER ASSESSMENT
 * ========================================
 */
add_action('wp_ajax_cip_save_assessment', 'cip_ajax_save_assessment');

function cip_ajax_save_assessment() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    $user_id = get_current_user_id();
    $step = intval($_POST['step']);
    $data = $_POST['data'];
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    if ($existing) {
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

add_action('wp_ajax_cip_submit_assessment', 'cip_ajax_submit_assessment');

function cip_ajax_submit_assessment() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
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
    
    update_user_meta($user_id, 'cip_assessment_submitted', true);
    
    wp_send_json_success(['message' => 'Assessment submitted successfully']);
}

add_action('wp_ajax_cip_load_assessment', 'cip_ajax_load_assessment');

function cip_ajax_load_assessment() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
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
    }
    
    wp_die('Certificate file not found');
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
        
        $user = get_userdata($user_id);
        $registration = null;
        
        if ($user) {
            $registration = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE email = %s",
                $user->user_email
            ), ARRAY_A);
        }
        
        if ($registration) {
            echo '<html><head><title>Certificate Verification</title></head><body>';
            echo '<h1>✓ Certificate Verified</h1>';
            echo '<p><strong>Company:</strong> ' . esc_html($registration['company_name']) . '</p>';
            echo '<p><strong>Certificate Number:</strong> ' . esc_html($cert_number) . '</p>';
            echo '<p><strong>Grade:</strong> ' . esc_html($grade) . '</p>';
            echo '</body></html>';
            exit;
        }
    }
    
    wp_die('Certificate not found or invalid');
}

/**
 * ========================================
 * ADMIN NOTICES
 * ========================================
 */
add_action('admin_notices', 'cip_admin_notices');

function cip_admin_notices() {
    if (!defined('CIP_VERSION')) {
        return;
    }
    
    if (isset($_GET['activated']) && $_GET['activated'] === 'true') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>CleanIndex Portal</strong> has been activated successfully! 
                <a href="<?php echo admin_url('admin.php?page=cleanindex-portal'); ?>">Configure settings</a>
            </p>
        </div>
        <?php
    }
    
    $permalink_structure = get_option('permalink_structure');
    if (empty($permalink_structure)) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>CleanIndex Portal:</strong> 
                Pretty permalinks are required. 
                <a href="<?php echo admin_url('options-permalink.php'); ?>">Configure permalink settings</a>
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
    global $wpdb;
    
    // Optionally delete tables (commented out for safety)
    // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}company_registrations");
    // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}company_assessments");
    // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cip_subscriptions");
    
    // Remove cron jobs
    wp_clear_scheduled_hook('cip_check_expired_subscriptions');
}