<?php
/**
 * Plugin Name: CleanIndex Portal
 * Plugin URI: https://cleanindex.com
 * Description: Complete ESG certification portal with CSRD/ESRS compliant assessment, multi-role dashboards, and approval workflow.
 * Version: 1.0.1
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
define('CIP_VERSION', '1.0.1');
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
    'includes/helpers.php'
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
            CIP_UPLOAD_DIR . 'assessments/'
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
        'reset-password'
    ];
    
    // Add rewrite rule for each page
    foreach ($pages as $page) {
        add_rewrite_rule(
            '^cleanindex/' . $page . '/?$',
            'index.php?cip_page=' . $page,
            'top'
        );
    }
}

/**
 * ========================================
 * QUERY VARS
 * ========================================
 */
add_filter('query_vars', 'cip_query_vars');

function cip_query_vars($vars) {
    $vars[] = 'cip_page';
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
    
    // Define template map
    $template_map = [
        'register' => 'pages/register.php',
        'login' => 'pages/login.php',
        'dashboard' => 'pages/user-dashboard.php',
        'assessment' => 'pages/assessment.php',
        'manager' => 'pages/manager-dashboard.php',
        'admin-portal' => 'pages/admin-dashboard.php'
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

// Upload file
add_action('wp_ajax_cip_upload_file', 'cip_ajax_upload_file');

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
        
        // Save settings
        update_option('cip_enable_registration', isset($_POST['enable_registration']) ? '1' : '0');
        update_option('cip_enable_auto_approval', isset($_POST['enable_auto_approval']) ? '1' : '0');
        update_option('cip_admin_email', sanitize_email($_POST['admin_email']));
        update_option('cip_company_name', sanitize_text_field($_POST['company_name']));
        update_option('cip_max_file_size', intval($_POST['max_file_size']));
        update_option('cip_allowed_file_types', sanitize_text_field($_POST['allowed_file_types']));
        
        echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Settings saved successfully!', 'cleanindex-portal') . '</strong></p></div>';
    }
    
    // Get current settings
    $enable_registration = get_option('cip_enable_registration', '1');
    $enable_auto_approval = get_option('cip_enable_auto_approval', '0');
    $admin_email = get_option('cip_admin_email', get_option('admin_email'));
    $company_name = get_option('cip_company_name', 'CleanIndex');
    $max_file_size = get_option('cip_max_file_size', 10);
    $allowed_file_types = get_option('cip_allowed_file_types', 'pdf,doc,docx');
    
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
        'pages/register.php',
        'pages/login.php',
        'pages/user-dashboard.php',
        'pages/assessment.php',
        'pages/manager-dashboard.php',
        'pages/admin-dashboard.php',
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
            delete_option('cip_enable_auto_approval');
            delete_option('cip_admin_email');
            delete_option('cip_company_name');
            delete_option('cip_max_file_size');
            delete_option('cip_allowed_file_types');
            delete_option('cip_remove_data_on_uninstall');
        }
    }
}

// End of file