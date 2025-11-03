<?php
/**
 * CleanIndex Portal - Database Functions
 * Handles all database operations
 */

if (!defined('ABSPATH')) exit;

/**
 * Get registration by ID
 */
function cip_get_registration($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $id
    ), ARRAY_A);
}

/**
 * Get registration by email
 */
function cip_get_registration_by_email($email) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE email = %s",
        $email
    ), ARRAY_A);
}

/**
 * Create new registration
 */
function cip_create_registration($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $defaults = [
        'company_name' => '',
        'employee_name' => '',
        'org_type' => '',
        'industry' => '',
        'country' => '',
        'working_desc' => '',
        'num_employees' => 0,
        'culture' => '',
        'email' => '',
        'password' => '',
        'status' => 'pending_manager_review',
        'manager_notes' => '',
        'supporting_files' => ''
    ];
    
    $data = wp_parse_args($data, $defaults);
    
    // Hash password if not already hashed
    if (!empty($data['password']) && strlen($data['password']) < 60) {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    $result = $wpdb->insert($table, $data);
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return false;
}

/**
 * Update registration
 */
function cip_update_registration($id, $data) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    // Remove ID from data if present
    unset($data['id']);
    
    return $wpdb->update(
        $table,
        $data,
        ['id' => $id]
    );
}

/**
 * Delete registration
 */
function cip_delete_registration($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return $wpdb->delete($table, ['id' => $id]);
}

/**
 * Get all registrations with filters
 */
function cip_get_registrations($args = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $defaults = [
        'status' => '',
        'industry' => '',
        'country' => '',
        'order_by' => 'created_at',
        'order' => 'DESC',
        'limit' => -1,
        'offset' => 0
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $where = ['1=1'];
    $values = [];
    
    if (!empty($args['status'])) {
        $where[] = 'status = %s';
        $values[] = $args['status'];
    }
    
    if (!empty($args['industry'])) {
        $where[] = 'industry = %s';
        $values[] = $args['industry'];
    }
    
    if (!empty($args['country'])) {
        $where[] = 'country = %s';
        $values[] = $args['country'];
    }
    
    $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY {$args['order_by']} {$args['order']}";
    
    if ($args['limit'] > 0) {
        $sql .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";
    }
    
    if (!empty($values)) {
        $sql = $wpdb->prepare($sql, $values);
    }
    
    return $wpdb->get_results($sql, ARRAY_A);
}

/**
 * Count registrations
 */
function cip_count_registrations($status = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    if ($status) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = %s",
            $status
        ));
    }
    
    return $wpdb->get_var("SELECT COUNT(*) FROM $table");
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
 * Save assessment
 */
function cip_save_assessment($user_id, $data, $progress = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    // Convert data to JSON if it's an array
    if (is_array($data)) {
        $data = json_encode($data);
    }
    
    // Check if assessment exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d",
        $user_id
    ));
    
    if ($existing) {
        // Update existing
        return $wpdb->update(
            $table,
            [
                'assessment_json' => $data,
                'progress' => $progress
            ],
            ['user_id' => $user_id]
        );
    } else {
        // Insert new
        return $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'assessment_json' => $data,
                'progress' => $progress
            ]
        );
    }
}

/**
 * Delete assessment
 */
function cip_delete_assessment($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    
    return $wpdb->delete($table, ['user_id' => $user_id]);
}

/**
 * Search registrations
 */
function cip_search_registrations($search_term) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $search_term = '%' . $wpdb->esc_like($search_term) . '%';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table 
        WHERE company_name LIKE %s 
        OR employee_name LIKE %s 
        OR email LIKE %s 
        ORDER BY created_at DESC",
        $search_term,
        $search_term,
        $search_term
    ), ARRAY_A);
}

/**
 * Get registrations by date range
 */
function cip_get_registrations_by_date($start_date, $end_date) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table 
        WHERE created_at BETWEEN %s AND %s 
        ORDER BY created_at DESC",
        $start_date,
        $end_date
    ), ARRAY_A);
}

/**
 * Update registration status
 */
function cip_update_registration_status($id, $status, $notes = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $data = ['status' => $status];
    
    if (!empty($notes)) {
        $data['manager_notes'] = $notes;
    }
    
    return $wpdb->update($table, $data, ['id' => $id]);
}

/**
 * Get statistics
 */
function cip_get_dashboard_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return [
        'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
        'pending_manager' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending_manager_review'"),
        'pending_admin' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending_admin_approval'"),
        'approved' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'approved'"),
        'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'rejected'"),
        'today' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE DATE(created_at) = CURDATE()"),
        'this_week' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())"),
        'this_month' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")
    ];
}

/**
 * Bulk update registrations
 */
function cip_bulk_update_status($ids, $status) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    if (empty($ids) || !is_array($ids)) {
        return false;
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    
    return $wpdb->query($wpdb->prepare(
        "UPDATE $table SET status = %s WHERE id IN ($placeholders)",
        array_merge([$status], $ids)
    ));
}

/**
 * Check if email exists
 */
function cip_email_exists($email, $exclude_id = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    if ($exclude_id) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE email = %s AND id != %d",
            $email,
            $exclude_id
        )) > 0;
    }
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE email = %s",
        $email
    )) > 0;
}

/**
 * Get recent activity
 */
function cip_get_recent_activity($limit = 10) {
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT id, company_name, status, created_at, updated_at 
        FROM $table 
        ORDER BY updated_at DESC 
        LIMIT %d",
        $limit
    ), ARRAY_A);
}