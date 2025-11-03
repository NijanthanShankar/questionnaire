<?php
/**
 * CleanIndex Portal - Authentication Functions
 * Handles user login, logout, and session management
 */

if (!defined('ABSPATH')) exit;

/**
 * Authenticate user with email and password
 */
function cip_authenticate_user($email, $password) {
    $registration = cip_get_registration_by_email($email);
    
    if (!$registration) {
        return new WP_Error('invalid_credentials', 'Invalid email or password');
    }
    
    // Verify password
    if (!password_verify($password, $registration['password'])) {
        return new WP_Error('invalid_credentials', 'Invalid email or password');
    }
    
    // Check if approved
    if ($registration['status'] === 'rejected') {
        return new WP_Error('account_rejected', 'Your account registration was not approved. Please contact support.');
    }
    
    return $registration;
}

/**
 * Login user and create WordPress session
 */
function cip_login_user($email, $password, $remember = false) {
    $auth = cip_authenticate_user($email, $password);
    
    if (is_wp_error($auth)) {
        return $auth;
    }
    
    // Check if WordPress user exists
    $user = get_user_by('email', $email);
    
    if (!$user) {
        // Create WordPress user
        $user_id = wp_create_user(
            sanitize_user($email),
            $password,
            $email
        );
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Assign role
        $user = new WP_User($user_id);
        $user->set_role('organization_admin');
        
        // Update user meta
        update_user_meta($user_id, 'cip_registration_id', $auth['id']);
        update_user_meta($user_id, 'first_name', $auth['employee_name']);
        update_user_meta($user_id, 'company_name', $auth['company_name']);
    }
    
    // Log user in
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember);
    do_action('wp_login', $user->user_login, $user);
    
    return $user;
}

/**
 * Logout current user
 */
function cip_logout_user() {
    wp_logout();
}

/**
 * Check if user is logged in and has access
 */
function cip_check_user_access($required_role = '') {
    if (!is_user_logged_in()) {
        return false;
    }
    
    if (empty($required_role)) {
        return true;
    }
    
    $user = wp_get_current_user();
    return in_array($required_role, $user->roles);
}

/**
 * Require login (redirect if not logged in)
 */
function cip_require_login($redirect_to = '') {
    if (!is_user_logged_in()) {
        if (empty($redirect_to)) {
            $redirect_to = home_url('/cleanindex/login');
        }
        wp_redirect($redirect_to);
        exit;
    }
}

/**
 * Require specific role
 */
function cip_require_role($role) {
    if (!cip_check_user_access($role)) {
        wp_die('You do not have permission to access this page.');
    }
}

/**
 * Get current user's registration
 */
function cip_get_current_user_registration() {
    if (!is_user_logged_in()) {
        return null;
    }
    
    $user = wp_get_current_user();
    return cip_get_registration_by_email($user->user_email);
}

/**
 * Check if current user is organization admin
 */
function cip_is_organization_admin() {
    return cip_check_user_access('organization_admin');
}

/**
 * Check if current user is manager
 */
function cip_is_manager() {
    return cip_check_user_access('manager') || current_user_can('review_submissions');
}

/**
 * Check if current user is admin
 */
function cip_is_admin() {
    return current_user_can('manage_options');
}

/**
 * Generate password reset token
 */
function cip_generate_reset_token($email) {
    $registration = cip_get_registration_by_email($email);
    
    if (!$registration) {
        return false;
    }
    
    $token = bin2hex(random_bytes(32));
    $expiry = time() + 3600; // 1 hour
    
    update_option('cip_reset_' . $token, [
        'email' => $email,
        'expiry' => $expiry
    ]);
    
    return $token;
}

/**
 * Verify password reset token
 */
function cip_verify_reset_token($token) {
    $data = get_option('cip_reset_' . $token);
    
    if (!$data) {
        return false;
    }
    
    if ($data['expiry'] < time()) {
        delete_option('cip_reset_' . $token);
        return false;
    }
    
    return $data['email'];
}

/**
 * Reset password
 */
function cip_reset_password($token, $new_password) {
    $email = cip_verify_reset_token($token);
    
    if (!$email) {
        return new WP_Error('invalid_token', 'Invalid or expired reset token');
    }
    
    $registration = cip_get_registration_by_email($email);
    
    if (!$registration) {
        return new WP_Error('user_not_found', 'User not found');
    }
    
    // Update password in registrations table
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    
    $updated = $wpdb->update(
        $table,
        ['password' => password_hash($new_password, PASSWORD_DEFAULT)],
        ['email' => $email]
    );
    
    if (!$updated) {
        return new WP_Error('update_failed', 'Failed to update password');
    }
    
    // Update WordPress user if exists
    $user = get_user_by('email', $email);
    if ($user) {
        wp_set_password($new_password, $user->ID);
    }
    
    // Delete token
    delete_option('cip_reset_' . $token);
    
    return true;
}

/**
 * Send password reset email
 */
function cip_send_password_reset_email($email) {
    $token = cip_generate_reset_token($email);
    
    if (!$token) {
        return false;
    }
    
    $reset_link = home_url('/cleanindex/reset-password?token=' . $token);
    
    $subject = 'Password Reset Request - CleanIndex';
    $message = "
    <p>Hello,</p>
    <p>We received a request to reset your password for your CleanIndex account.</p>
    <p>Click the button below to reset your password:</p>
    <p><a href='{$reset_link}' style='display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 6px;'>Reset Password</a></p>
    <p>This link will expire in 1 hour.</p>
    <p>If you didn't request this, you can safely ignore this email.</p>
    <p>Best regards,<br>CleanIndex Team</p>
    ";
    
    return cip_send_email($email, $subject, $message);
}

/**
 * Update user session
 */
function cip_update_user_session($user_id) {
    $sessions = WP_Session_Tokens::get_instance($user_id);
    $sessions->destroy_all();
}

/**
 * Log user activity
 */
function cip_log_user_activity($action, $details = '') {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user = wp_get_current_user();
    $registration = cip_get_current_user_registration();
    
    if ($registration) {
        cip_log_activity($registration['id'], $action, $details);
    }
}

/**
 * Check rate limit for login attempts
 */
function cip_check_login_rate_limit($email) {
    $attempts_key = 'cip_login_attempts_' . md5($email);
    $attempts = get_transient($attempts_key);
    
    if ($attempts === false) {
        $attempts = 0;
    }
    
    if ($attempts >= 5) {
        return new WP_Error('too_many_attempts', 'Too many login attempts. Please try again in 15 minutes.');
    }
    
    return true;
}

/**
 * Record login attempt
 */
function cip_record_login_attempt($email, $success = false) {
    $attempts_key = 'cip_login_attempts_' . md5($email);
    
    if ($success) {
        delete_transient($attempts_key);
    } else {
        $attempts = get_transient($attempts_key);
        if ($attempts === false) {
            $attempts = 0;
        }
        set_transient($attempts_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
    }
}

/**
 * Get user display name
 */
function cip_get_user_display_name($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $registration = cip_get_current_user_registration();
    
    if ($registration) {
        return $registration['employee_name'];
    }
    
    $user = get_userdata($user_id);
    return $user ? $user->display_name : '';
}

/**
 * Check if user account is active
 */
function cip_is_account_active() {
    $registration = cip_get_current_user_registration();
    
    if (!$registration) {
        return false;
    }
    
    return in_array($registration['status'], ['approved', 'pending_admin_approval']);
}