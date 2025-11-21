<?php
/**
 * CleanIndex Portal - Helper Functions
 * Utility functions used throughout the plugin
 */

if (!defined('ABSPATH')) exit;

/**
 * Check if current page is a plugin page
 */
function cip_is_plugin_page() {
    $page = get_query_var('cip_page');
    return !empty($page);
}

/**
 * Get current user's organization
 */
function cip_get_current_organization() {
    if (!is_user_logged_in()) {
        return null;
    }
    
    $user = wp_get_current_user();
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE email = %s",
        $user->user_email
    ), ARRAY_A);
}

/**
 * Format date for display
 */
function cip_format_date($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Get status badge HTML
 */
function cip_get_status_badge($status) {
    $badges = [
        'pending_manager_review' => '<span class="badge badge-pending">Pending Review</span>',
        'pending_admin_approval' => '<span class="badge badge-review">Admin Approval</span>',
        'approved' => '<span class="badge badge-approved">Approved</span>',
        'rejected' => '<span class="badge badge-rejected">Rejected</span>'
    ];
    
    return isset($badges[$status]) ? $badges[$status] : '';
}

/**
 * Sanitize assessment data
 */
function cip_sanitize_assessment_data($data) {
    if (!is_array($data)) {
        $data = json_decode($data, true);
    }
    
    $sanitized = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = cip_sanitize_assessment_data($value);
        } else {
            $sanitized[sanitize_key($key)] = sanitize_textarea_field($value);
        }
    }
    
    return $sanitized;
}

/**
 * Get industries list
 */
function cip_get_industries() {
    return [
        'Agriculture' => 'Agriculture',
        'Construction' => 'Construction',
        'Education' => 'Education',
        'Energy' => 'Energy',
        'Finance' => 'Finance',
        'Healthcare' => 'Healthcare',
        'Manufacturing' => 'Manufacturing',
        'Retail' => 'Retail',
        'Technology' => 'Technology',
        'Transportation' => 'Transportation',
        'Other' => 'Other'
    ];
}

/**
 * Get countries list
 */
function cip_get_countries() {
    return [
        'Netherlands' => 'Netherlands',
        'Belgium' => 'Belgium',
        'Germany' => 'Germany',
        'France' => 'France',
        'United Kingdom' => 'United Kingdom',
        'Spain' => 'Spain',
        'Italy' => 'Italy',
        'Other EU' => 'Other EU Country',
        'Other' => 'Other'
    ];
}

/**
 * Get organization types
 */
function cip_get_organization_types() {
    return [
        'Company' => 'Company',
        'Municipality' => 'Municipality',
        'NGO' => 'NGO',
        'Other' => 'Other'
    ];
}

/**
 * Check if file is allowed
 */
function cip_is_allowed_file_type($filename) {
    $allowed_extensions = ['pdf', 'doc', 'docx'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowed_extensions);
}

/**
 * Generate verification code
 */
function cip_generate_verification_code() {
    return strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
}

/**
 * Get assessment step titles
 */
function cip_get_assessment_steps() {
    return [
        1 => 'General Requirements & Materiality Analysis',
        2 => 'Company Profile & Governance',
        3 => 'Strategy & Risk Management',
        4 => 'Environment (E1-E5)',
        5 => 'Social & Metrics (S1-S4)'
    ];
}

/**
 * Calculate assessment completion percentage
 */
function cip_calculate_assessment_progress($assessment_data) {
    if (empty($assessment_data)) {
        return 0;
    }
    
    $total_fields = 0;
    $completed_fields = 0;
    
    foreach ($assessment_data as $key => $value) {
        if (strpos($key, 'q') === 0) { // Question fields start with 'q'
            $total_fields++;
            if (!empty($value)) {
                $completed_fields++;
            }
        }
    }
    
    return $total_fields > 0 ? round(($completed_fields / $total_fields) * 100) : 0;
}

/**
 * Log activity
 */
function cip_log_activity($user_id, $action, $details = '') {
    global $wpdb;
    
    $log_table = $wpdb->prefix . 'cip_activity_log';
    
    // Create log table if it doesn't exist
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS $log_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $wpdb->insert(
        $log_table,
        [
            'user_id' => $user_id,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]
    );
}

/**
 * Send notification to admin
 */
function cip_notify_admin($subject, $message) {
    $admin_email = get_option('admin_email');
    return cip_send_email($admin_email, $subject, $message);
}

/**
 * Get submission statistics
 */
function cip_get_statistics() {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return [
        'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
        'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('pending_manager_review', 'pending_admin_approval')"),
        'approved' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'approved'"),
        'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'rejected'"),
        'this_month' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")
    ];
}

/**
 * Get recent submissions
 */
function cip_get_recent_submissions($limit = 10) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d", $limit),
        ARRAY_A
    );
}

/**
 * Get pending submissions count
 */
function cip_get_pending_count() {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return $wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE status IN ('pending_manager_review', 'pending_admin_approval')"
    );
}

/**
 * Validate email format
 */
function cip_validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 */
function cip_validate_phone($phone) {
    return preg_match('/^[0-9\s\-\+\(\)]+$/', $phone);
}

/**
 * Truncate text
 */
function cip_truncate_text($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Get time ago string
 */
function cip_time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

/**
 * Check if assessment is complete
 */
function cip_is_assessment_complete($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    return $assessment && $assessment['progress'] >= 5;
}

/**
 * Get assessment data
 */
function cip_get_assessment($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    if ($assessment && $assessment['assessment_json']) {
        $assessment['data'] = json_decode($assessment['assessment_json'], true);
    }
    
    return $assessment;
}

/**
 * Format currency
 */
function cip_format_currency($amount, $currency = 'EUR') {
    $symbols = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£'
    ];
    
    $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    return $symbol . number_format($amount, 2);
}

/**
 * Generate random password
 */
function cip_generate_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Clean input data
 */
function cip_clean_input($data) {
    if (is_array($data)) {
        return array_map('cip_clean_input', $data);
    }
    return trim(strip_tags($data));
}

/**
 * Check if user has completed profile
 */
function cip_user_profile_complete($user_id) {
    $org = cip_get_current_organization();
    if (!$org) {
        return false;
    }
    
    $required_fields = ['company_name', 'employee_name', 'industry', 'country', 'num_employees'];
    
    foreach ($required_fields as $field) {
        if (empty($org[$field])) {
            return false;
        }
    }
    
    return true;
}

/**
 * Get plugin version
 */
function cip_get_version() {
    return CIP_VERSION;
}

/**
 * Debug log (only if WP_DEBUG is enabled)
 */
function cip_debug_log($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[CleanIndex Portal] ' . $message);
        if ($data !== null) {
            error_log(print_r($data, true));
        }
    }
}


/**
 * Currency Helper Functions
 * ADD TO: includes/helpers.php (at the end)
 */

/**
 * Get all supported currencies with symbols
 */
function cip_get_currencies() {
    return [
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'position' => 'before'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'position' => 'before'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'position' => 'before'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'position' => 'before'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'position' => 'before'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'position' => 'before'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF', 'position' => 'before'],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'position' => 'before'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'position' => 'before'],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr', 'position' => 'after'],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'position' => 'after'],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr', 'position' => 'after'],
        'PLN' => ['name' => 'Polish Złoty', 'symbol' => 'zł', 'position' => 'after'],
        'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč', 'position' => 'after'],
        'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft', 'position' => 'after'],
        'RON' => ['name' => 'Romanian Leu', 'symbol' => 'lei', 'position' => 'after'],
        'BGN' => ['name' => 'Bulgarian Lev', 'symbol' => 'лв', 'position' => 'after'],
        'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺', 'position' => 'before'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$', 'position' => 'before'],
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => 'Mex$', 'position' => 'before'],
        'ARS' => ['name' => 'Argentine Peso', 'symbol' => 'ARS$', 'position' => 'before'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R', 'position' => 'before'],
        'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩', 'position' => 'before'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'position' => 'before'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'position' => 'before'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'position' => 'before'],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿', 'position' => 'before'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'position' => 'before'],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'position' => 'before'],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱', 'position' => 'before'],
        'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫', 'position' => 'after'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'position' => 'after'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼', 'position' => 'after'],
        'ILS' => ['name' => 'Israeli Shekel', 'symbol' => '₪', 'position' => 'before'],
        'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽', 'position' => 'after'],
        'UAH' => ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴', 'position' => 'before'],
        'EGP' => ['name' => 'Egyptian Pound', 'symbol' => '£', 'position' => 'before'],
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦', 'position' => 'before'],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'position' => 'before'],
    ];
}

/**
 * Format amount with currency symbol
 * 
 * @param float $amount The amount to format
 * @param string $currency_code Currency code (e.g., 'USD', 'EUR')
 * @param bool $include_decimals Whether to show decimals
 * @return string Formatted currency string
 */
function cip_format_price($amount, $currency_code = null, $include_decimals = true) {
    if (!$currency_code) {
        $currency_code = get_option('cip_default_currency', 'EUR');
    }
    
    $currencies = cip_get_currencies();
    
    if (!isset($currencies[$currency_code])) {
        $currency_code = 'EUR'; // Fallback
    }
    
    $currency = $currencies[$currency_code];
    $symbol = $currency['symbol'];
    $position = $currency['position'];
    
    // Format the number
    if ($include_decimals) {
        $formatted_amount = number_format((float)$amount, 2, '.', ',');
    } else {
        $formatted_amount = number_format((float)$amount, 0, '', ',');
    }
    
    // Position the symbol
    if ($position === 'before') {
        return $symbol . $formatted_amount;
    } else {
        return $formatted_amount . ' ' . $symbol;
    }
}

/**
 * Get currency symbol
 * 
 * @param string $currency_code Currency code
 * @return string Currency symbol
 */
function cip_get_currency_symbol($currency_code = null) {
    if (!$currency_code) {
        $currency_code = get_option('cip_default_currency', 'EUR');
    }
    
    $currencies = cip_get_currencies();
    
    if (isset($currencies[$currency_code])) {
        return $currencies[$currency_code]['symbol'];
    }
    
    return $currency_code; // Fallback to code
}

/**
 * Get currency name
 * 
 * @param string $currency_code Currency code
 * @return string Currency name
 */
function cip_get_currency_name($currency_code = null) {
    if (!$currency_code) {
        $currency_code = get_option('cip_default_currency', 'EUR');
    }
    
    $currencies = cip_get_currencies();
    
    if (isset($currencies[$currency_code])) {
        return $currencies[$currency_code]['name'];
    }
    
    return $currency_code;
}