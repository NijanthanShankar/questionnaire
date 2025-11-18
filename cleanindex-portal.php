<?php
/**
 * Plugin Name: CleanIndex Portal
 * Plugin URI: https://cleanindex.com
 * Description: Complete ESG certification portal with CSRD/ESRS compliant assessment, multi-role dashboards, payment processing, and certificate generation.
 * Version: 1.1.0
 * Author: CleanIndex / Brnd Guru
 * Author URI: https://brndguru.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: cleanindex-portal
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ========================================
 * PLUGIN CONSTANTS
 * ========================================
 */
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
    'includes/pdf-generator.php'
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
        
        // Backup and drop existing tables if they exist
        $table_registrations = $wpdb->prefix . 'company_registrations';
        $table_assessments = $wpdb->prefix . 'company_assessments';
        
        // Backup existing data from registrations table
        $backup_registrations = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_registrations'") === $table_registrations) {
            $backup_registrations = $wpdb->get_results("SELECT * FROM $table_registrations", ARRAY_A);
            error_log("CleanIndex Portal: Backed up " . count($backup_registrations) . " registration records");
        }
        
        // Backup existing data from assessments table
        $backup_assessments = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_assessments'") === $table_assessments) {
            $backup_assessments = $wpdb->get_results("SELECT * FROM $table_assessments", ARRAY_A);
            error_log("CleanIndex Portal: Backed up " . count($backup_assessments) . " assessment records");
        }
        
        // Drop existing tables
        $wpdb->query("DROP TABLE IF EXISTS $table_registrations");
        $wpdb->query("DROP TABLE IF EXISTS $table_assessments");
        error_log("CleanIndex Portal: Dropped existing tables");
        
        // Create company registrations table with correct schema
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
        error_log("CleanIndex Portal: Created registrations table with correct schema");
        
        // Create assessments table with correct schema
        $sql_assessments = "CREATE TABLE $table_assessments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            assessment_json LONGTEXT,
            progress INT DEFAULT 0,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_progress (progress)
        ) $charset_collate;";
        
        dbDelta($sql_assessments);
        error_log("CleanIndex Portal: Created assessments table");
        
        // Restore backed up data to registrations table
        if (!empty($backup_registrations)) {
            $restored = 0;
            foreach ($backup_registrations as $row) {
                // Map old data to new schema (handle missing columns)
                $insert_data = [
                    'company_name' => isset($row['company_name']) ? $row['company_name'] : '',
                    'employee_name' => isset($row['employee_name']) ? $row['employee_name'] : (isset($row['contact_name']) ? $row['contact_name'] : 'Unknown'),
                    'org_type' => isset($row['org_type']) ? $row['org_type'] : 'Company',
                    'industry' => isset($row['industry']) ? $row['industry'] : 'Other',
                    'country' => isset($row['country']) ? $row['country'] : 'Other',
                    'working_desc' => isset($row['working_desc']) ? $row['working_desc'] : (isset($row['description']) ? $row['description'] : ''),
                    'num_employees' => isset($row['num_employees']) ? intval($row['num_employees']) : 0,
                    'culture' => isset($row['culture']) ? $row['culture'] : '',
                    'email' => isset($row['email']) ? $row['email'] : '',
                    'password' => isset($row['password']) ? $row['password'] : '',
                    'status' => isset($row['status']) ? $row['status'] : 'pending_manager_review',
                    'manager_notes' => isset($row['manager_notes']) ? $row['manager_notes'] : '',
                    'supporting_files' => isset($row['supporting_files']) ? $row['supporting_files'] : '[]'
                ];
                
                // Only insert if email is valid
                if (!empty($insert_data['email'])) {
                    $result = $wpdb->insert($table_registrations, $insert_data);
                    if ($result) $restored++;
                }
            }
            error_log("CleanIndex Portal: Restored $restored registration records");
        }
        
        // Restore backed up data to assessments table
        if (!empty($backup_assessments)) {
            $restored = 0;
            foreach ($backup_assessments as $row) {
                $insert_data = [
                    'user_id' => isset($row['user_id']) ? intval($row['user_id']) : 0,
                    'assessment_json' => isset($row['assessment_json']) ? $row['assessment_json'] : '',
                    'progress' => isset($row['progress']) ? intval($row['progress']) : 0
                ];
                
                if ($insert_data['user_id'] > 0) {
                    $result = $wpdb->insert($table_assessments, $insert_data);
                    if ($result) $restored++;
                }
            }
            error_log("CleanIndex Portal: Restored $restored assessment records");
        }
        
        // Create upload directories
        $upload_dirs = [
            CIP_UPLOAD_DIR,
            CIP_UPLOAD_DIR . 'registration/',
            CIP_UPLOAD_DIR . 'assessments/',
            CIP_UPLOAD_DIR . 'certificates/'
        ];
        
        foreach ($upload_dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                // Add .htaccess for security
                $htaccess_content = "Order Deny,Allow\nDeny from all\n<Files ~ \"\\.(pdf|doc|docx)$\">\nAllow from all\n</Files>";
                @file_put_contents($dir . '.htaccess', $htaccess_content);
                // Add index.php to prevent directory listing
                @file_put_contents($dir . 'index.php', '<?php // Silence is golden');
            }
        }
        
        // Add custom roles
        if (function_exists('cip_add_custom_roles')) {
            cip_add_custom_roles();
        }
        
        // Set default pricing plans if not exist
        if (!get_option('cip_pricing_plans')) {
            update_option('cip_pricing_plans', [
                [
                    'name' => 'Basic',
                    'price' => '499',
                    'currency' => 'EUR',
                    'features' => "ESG Assessment\nBasic Certificate\nEmail Support\n1 Year Validity",
                    'popular' => false
                ],
                [
                    'name' => 'Professional',
                    'price' => '999',
                    'currency' => 'EUR',
                    'features' => "ESG Assessment\nPremium Certificate\nPriority Support\n2 Years Validity\nBenchmarking Report\nDirectory Listing",
                    'popular' => true
                ],
                [
                    'name' => 'Enterprise',
                    'price' => '1999',
                    'currency' => 'EUR',
                    'features' => "ESG Assessment\nPremium Certificate\nDedicated Support\n3 Years Validity\nDetailed Analytics\nFeatured Directory Listing\nCustom Reporting\nAPI Access",
                    'popular' => false
                ]
            ]);
        }
        
        // Set default certificate settings
        if (!get_option('cip_cert_grading_mode')) {
            update_option('cip_cert_grading_mode', 'automatic');
            update_option('cip_cert_grade_esg3', 95);
            update_option('cip_cert_grade_esg2', 85);
            update_option('cip_cert_grade_esg1', 75);
            update_option('cip_cert_validity_years', 1);
        }
        
        // Add rewrite rules and flush
        cip_add_rewrite_rules();
        flush_rewrite_rules();
        
        // Set activation flag for redirect
        set_transient('cip_activation_redirect', true, 30);
        
        // Set flag for permalink notice
        set_transient('cip_flush_rewrite_rules_flag', true, 60);
        
        // Log successful activation
        error_log('CleanIndex Portal: Plugin activated successfully (v' . CIP_VERSION . ')');
        
    } catch (Exception $e) {
        error_log('CleanIndex Portal Activation Error: ' . $e->getMessage());
        wp_die(
            '<h1>Plugin Activation Failed</h1>' .
            '<p><strong>Error:</strong> ' . esc_html($e->getMessage()) . '</p>' .
            '<p>Please check your error logs for details.</p>' .
            '<p><a href="' . admin_url('plugins.php') . '">Back to Plugins</a></p>',
            'Activation Error'
        );
    }
}

/**
 * ========================================
 * PLUGIN DEACTIVATION
 * ========================================
 */
register_deactivation_hook(__FILE__, 'cip_deactivate_plugin');

function cip_deactivate_plugin() {
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clean up transients
    delete_transient('cip_activation_redirect');
    delete_transient('cip_flush_rewrite_rules_flag');
    
    error_log('CleanIndex Portal: Plugin deactivated');
}

/**
 * ========================================
 * REDIRECT TO SETTINGS AFTER ACTIVATION
 * ========================================
 */
add_action('admin_init', 'cip_activation_redirect');

function cip_activation_redirect() {
    // Check if we should redirect
    if (get_transient('cip_activation_redirect')) {
        delete_transient('cip_activation_redirect');
        
        // Don't redirect if activating multiple plugins
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('admin.php?page=cleanindex-portal&activated=true'));
            exit;
        }
    }
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
        'manager' => 'pages/manager-dashboard.php',
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
        // Template file not found - show helpful error
        wp_die(
            '<h1>Template File Not Found</h1>' .
            '<p><strong>Page:</strong> ' . esc_html($page) . '</p>' .
            '<p><strong>Expected file:</strong> <code>' . esc_html($template_file) . '</code></p>' .
            '<p>Please ensure the file exists in your plugin directory.</p>' .
            '<hr>' .
            '<p><strong>Troubleshooting:</strong></p>' .
            '<ul>' .
            '<li>Verify the file exists at the path above</li>' .
            '<li>Check file permissions (should be 644)</li>' .
            '<li>Ensure the plugin folder is complete</li>' .
            '</ul>' .
            '<p><a href="' . admin_url('plugins.php') . '" class="button">Back to Plugins</a> ' .
            '<a href="' . admin_url('admin.php?page=cleanindex-system') . '" class="button">System Info</a></p>',
            'Template Error',
            ['response' => 404]
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
        $result = CIP_PDF_Generator::generate_assessment_pdf($registration['id']);
        
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
    
    // Search for certificate in user meta
    global $wpdb;
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'cip_certificate_number' AND meta_value = %s",
        $cert_number
    ));
    
    if ($user_id) {
        $grade = get_user_meta($user_id, 'cip_certificate_grade', true);
        $table = $wpdb->prefix . 'company_registrations';
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'cip_certificate_number' AND meta_value = %s)",
            $cert_number
        ), ARRAY_A);
        
        if ($registration) {
            // Display verification page
            echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification - CleanIndex</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 20px; }
        .container { background: white; border-radius: 20px; padding: 3rem; max-width: 600px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; }
        .verified { color: #4CAF50; font-size: 4rem; margin-bottom: 1rem; }
        h1 { color: #333; margin-bottom: 0.5rem; }
        .cert-number { font-family: "Courier New", monospace; font-size: 1.25rem; color: #666; padding: 1rem; background: #f5f5f5; border-radius: 8px; margin: 1.5rem 0; }
        .grade { display: inline-block; background: #4CAF50; color: white; padding: 1rem 2rem; border-radius: 50px; font-size: 2rem; font-weight: bold; margin: 1rem 0; }
        .company { font-size: 1.5rem; font-weight: 600; color: #333; margin: 1.5rem 0; }
        .info { color: #666; margin: 0.5rem 0; }
        .footer { margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #eee; color: #999; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="verified">✓</div>
        <h1>Certificate Verified</h1>
        <p>This certificate is valid and has been issued by CleanIndex.</p>
        
        <div class="cert-number">' . esc_html($cert_number) . '</div>
        
        <div class="grade">' . esc_html($grade) . '</div>
        
        <div class="company">' . esc_html($registration['company_name']) . '</div>
        
        <div class="info">Industry: ' . esc_html($registration['industry']) . '</div>
        <div class="info">Location: ' . esc_html($registration['country']) . '</div>
        
        <div class="footer">
            <p>This certificate confirms successful completion of CSRD/ESRS compliant ESG assessment.</p>
            <p>© ' . date('Y') . ' CleanIndex. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
            exit;
        }
    }
    
    // Certificate not found
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Not Found - CleanIndex</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 20px; }
        .container { background: white; border-radius: 20px; padding: 3rem; max-width: 600px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; }
        .error { color: #f44336; font-size: 4rem; margin-bottom: 1rem; }
        h1 { color: #333; margin-bottom: 0.5rem; }
        p { color: #666; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error">✗</div>
        <h1>Certificate Not Found</h1>
        <p>The certificate number <strong>' . esc_html($cert_number) . '</strong> could not be verified.</p>
        <p>Please check the number and try again, or contact support for assistance.</p>
    </div>
</body>
</html>';
    exit;
}

/**
 * ========================================
 * AJAX HANDLERS
 * ========================================
 */

// Save assessment
add_action('wp_ajax_cip_save_assessment', 'cip_ajax_save_assessment');

function cip_ajax_save_assessment() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Not authenticated');
        return;
    }
    
    $user_id = get_current_user_id();
    $assessment_data = isset($_POST['assessment_data']) ? wp_unslash($_POST['assessment_data']) : '';
    $progress = isset($_POST['progress']) ? intval($_POST['progress']) : 0;
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    // Check if assessment exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d",
        $user_id
    ));
    
    if ($existing) {
        // Update existing
        $result = $wpdb->update(
            $table,
            [
                'assessment_json' => $assessment_data,
                'progress' => $progress
            ],
            ['user_id' => $user_id]
        );
    } else {
        // Insert new
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'assessment_json' => $assessment_data,
                'progress' => $progress
            ]
        );
    }
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Assessment saved successfully']);
    } else {
        wp_send_json_error('Failed to save assessment');
    }
}

// Process payment
add_action('wp_ajax_cip_process_payment', 'cip_process_payment');

function cip_process_payment() {
    check_ajax_referer('cip_payment', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not authenticated']);
        return;
    }
    
    $payment_gateway = get_option('cip_payment_gateway', 'stripe');
    $plan_index = intval($_POST['plan_index']);
    $pricing_plans = get_option('cip_pricing_plans', []);
    
    if (!isset($pricing_plans[$plan_index])) {
        wp_send_json_error(['message' => 'Invalid plan']);
        return;
    }
    
    $plan = $pricing_plans[$plan_index];
    
    if ($payment_gateway === 'stripe' && class_exists('CIP_Payment_Handler')) {
        CIP_Payment_Handler::process_stripe_payment($plan, $_POST['payment_method_id']);
    } else {
        wp_send_json_error(['message' => 'Payment gateway not configured']);
    }
}

// Generate certificate (manual)
add_action('wp_ajax_cip_generate_certificate_manual', 'cip_generate_certificate_manual');

function cip_generate_certificate_manual() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    $grade = sanitize_text_field($_POST['grade']);
    
    if ($grade === 'auto' && class_exists('CIP_PDF_Generator')) {
        // Get assessment data for automatic grading
        global $wpdb;
        $table_assessments = $wpdb->prefix . 'company_assessments';
        $assessment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_assessments WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        if ($assessment) {
            $assessment_data = json_decode($assessment['assessment_json'], true);
            $grade = CIP_PDF_Generator::calculate_grade($assessment_data);
        } else {
            $grade = 'ESG';
        }
    }
    
    if (class_exists('CIP_PDF_Generator')) {
        $result = CIP_PDF_Generator::generate_certificate_pdf($user_id, $grade);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Certificate generated successfully',
                'url' => $result['url']
            ]);
        } else {
            wp_send_json_error('Failed to generate certificate');
        }
    } else {
        wp_send_json_error('PDF Generator not available');
    }
}

// Manager action
add_action('wp_ajax_cip_manager_action', 'cip_ajax_manager_action');

function cip_ajax_manager_action() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!current_user_can('review_submissions')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    // Handle manager actions
    wp_send_json_success();
}

// Admin action
add_action('wp_ajax_cip_admin_action', 'cip_ajax_admin_action');

function cip_ajax_admin_action() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    // Handle admin actions
    wp_send_json_success();
}

/**
 * ========================================
 * SAVE SETTINGS
 * ========================================
 */
add_action('admin_init', 'cip_save_settings_enhanced');

function cip_save_settings_enhanced() {
    if (!isset($_POST['cip_settings_submit'])) return;
    
    check_admin_referer('cip_settings_action', 'cip_settings_nonce');
    
    // Save general settings
    update_option('cip_enable_registration', isset($_POST['enable_registration']) ? '1' : '0');
    update_option('cip_admin_email', sanitize_email($_POST['admin_email']));
    update_option('cip_company_name', sanitize_text_field($_POST['company_name']));
    
    // Save pricing plans
    if (isset($_POST['pricing_plans'])) {
        $pricing_plans = [];
        foreach ($_POST['pricing_plans'] as $plan) {
            $pricing_plans[] = [
                'name' => sanitize_text_field($plan['name']),
                'price' => sanitize_text_field($plan['price']),
                'currency' => sanitize_text_field($plan['currency']),
                'features' => sanitize_textarea_field($plan['features']),
                'popular' => !empty($plan['popular'])
            ];
        }
        update_option('cip_pricing_plans', $pricing_plans);
    }
    
    // Save certificate settings
    if (isset($_POST['cert_grading_mode'])) {
        update_option('cip_cert_grading_mode', sanitize_text_field($_POST['cert_grading_mode']));
        update_option('cip_cert_grade_esg3', intval($_POST['cert_grade_esg3']));
        update_option('cip_cert_grade_esg2', intval($_POST['cert_grade_esg2']));
        update_option('cip_cert_grade_esg1', intval($_POST['cert_grade_esg1']));
        update_option('cip_cert_validity_years', intval($_POST['cert_validity_years']));
    }
    
    // Save payment gateway settings
    if (isset($_POST['payment_gateway'])) {
        update_option('cip_payment_gateway', sanitize_text_field($_POST['payment_gateway']));
        update_option('cip_stripe_publishable_key', sanitize_text_field($_POST['stripe_publishable_key']));
        update_option('cip_stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key']));
        update_option('cip_paypal_client_id', sanitize_text_field($_POST['paypal_client_id']));
        update_option('cip_paypal_secret', sanitize_text_field($_POST['paypal_secret']));
        update_option('cip_paypal_sandbox', !empty($_POST['paypal_sandbox']));
        update_option('cip_payment_currency', sanitize_text_field($_POST['payment_currency']));
    }
}

/**
 * ========================================
 * ADMIN MENU
 * ========================================
 */
add_action('admin_menu', 'cip_admin_menu');

function cip_admin_menu() {
    // Main menu page
    add_menu_page(
        __('CleanIndex Portal', 'cleanindex-portal'),
        __('CleanIndex', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-portal',
        'cip_admin_settings_page',
        'dashicons-shield-alt',
        30
    );
    
    // Settings submenu (same as parent - this hides "CleanIndex" duplicate)
    add_submenu_page(
        'cleanindex-portal',
        __('Settings', 'cleanindex-portal'),
        __('Settings', 'cleanindex-portal'),
        'manage_options',
        'cleanindex-portal',
        'cip_admin_settings_page'
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
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'cleanindex-portal'));
    }
    
    // Handle form submission
    if (isset($_POST['cip_settings_submit'])) {
        check_admin_referer('cip_settings_action', 'cip_settings_nonce');
        echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Settings saved successfully!', 'cleanindex-portal') . '</strong></p></div>';
    }
    
    // Get current settings
    $enable_registration = get_option('cip_enable_registration', '1');
    $admin_email = get_option('cip_admin_email', get_option('admin_email'));
    $company_name = get_option('cip_company_name', 'CleanIndex');
    
    // Check if recently activated
    $activated = isset($_GET['activated']) ? true : false;
    
    // Get statistics
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    $stats = [
        'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
        'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('pending_manager_review', 'pending_admin_approval')"),
        'approved' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'approved'"),
        'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'rejected'")
    ];
    
    // Include the settings page template
    include CIP_PLUGIN_DIR . 'admin/settings-page.php';
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
    include CIP_PLUGIN_DIR . 'admin/submissions-page.php';
}

/**
 * System Info Page
 */
function cip_admin_system_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'cleanindex-portal'));
    }
    
    global $wpdb;
    
    // Gather system information
    $system_info = [
        'plugin_version' => CIP_VERSION,
        'wp_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'upload_dir' => CIP_UPLOAD_DIR,
        'upload_dir_writable' => is_writable(CIP_UPLOAD_DIR),
        'max_upload_size' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit')
    ];
    
    // Check database tables
    $table1 = $wpdb->prefix . 'company_registrations';
    $table2 = $wpdb->prefix . 'company_assessments';
    $system_info['table_registrations_exists'] = $wpdb->get_var("SHOW TABLES LIKE '$table1'") === $table1;
    $system_info['table_assessments_exists'] = $wpdb->get_var("SHOW TABLES LIKE '$table2'") === $table2;
    
    // Check required files
    $required_files = [
        'includes/db.php',
        'includes/roles.php',
        'includes/auth.php',
        'includes/email.php',
        'includes/upload-handler.php',
        'includes/helpers.php',
        'includes/pdf-generator.php',
        'pages/register.php',
        'pages/login.php',
        'pages/user-dashboard.php',
        'pages/assessment.php',
        'pages/manager-dashboard.php',
        'pages/admin-dashboard.php',
        'pages/pricing.php',
        'pages/checkout.php',
        'pages/certificate-view.php',
        'css/style.css',
        'js/script.js'
    ];
    
    $system_info['files'] = [];
    foreach ($required_files as $file) {
        $system_info['files'][$file] = file_exists(CIP_PLUGIN_DIR . $file);
    }
    
    // Get database stats
    if ($system_info['table_registrations_exists']) {
        $system_info['total_registrations'] = $wpdb->get_var("SELECT COUNT(*) FROM $table1");
        $system_info['latest_registration'] = $wpdb->get_row("SELECT * FROM $table1 ORDER BY created_at DESC LIMIT 1", ARRAY_A);
    }
    
    // Include the system info page template
    include CIP_PLUGIN_DIR . 'admin/system-info-page.php';
}

/**
 * ========================================
 * PLUGIN LOADED
 * ========================================
 */
add_action('plugins_loaded', 'cip_plugin_loaded');

function cip_plugin_loaded() {
    // Load text domain for translations
    load_plugin_textdomain(
        'cleanindex-portal',
        false,
        dirname(CIP_PLUGIN_BASENAME) . '/languages'
    );
    
    // Check if dependencies are met
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>CleanIndex Portal:</strong> This plugin requires PHP 7.4 or higher. ';
            echo 'You are running PHP ' . PHP_VERSION . '. Please upgrade PHP.';
            echo '</p></div>';
        });
    }
}

/**
 * ========================================
 * UNINSTALL CLEANUP
 * ========================================
 */
if (!function_exists('cip_uninstall')) {
    register_uninstall_hook(__FILE__, 'cip_uninstall');
    
    function cip_uninstall() {
        // Only remove data if user wants to
        if (get_option('cip_remove_data_on_uninstall', false)) {
            global $wpdb;
            
            // Drop tables
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}company_registrations");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}company_assessments");
            
            // Delete options
            delete_option('cip_enable_registration');
            delete_option('cip_admin_email');
            delete_option('cip_company_name');
            delete_option('cip_pricing_plans');
            delete_option('cip_cert_grading_mode');
            delete_option('cip_cert_grade_esg3');
            delete_option('cip_cert_grade_esg2');
            delete_option('cip_cert_grade_esg1');
            delete_option('cip_cert_validity_years');
            delete_option('cip_payment_gateway');
            delete_option('cip_stripe_publishable_key');
            delete_option('cip_stripe_secret_key');
            delete_option('cip_paypal_client_id');
            delete_option('cip_paypal_secret');
            delete_option('cip_paypal_sandbox');
            delete_option('cip_payment_currency');
            delete_option('cip_remove_data_on_uninstall');
        }
    }
}

// End of file