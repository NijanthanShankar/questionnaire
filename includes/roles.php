<?php
/**
 * CleanIndex Portal - Roles and Capabilities
 * Handles custom user roles for the platform
 */

if (!defined('ABSPATH')) exit;

function cip_add_custom_roles() {
    // Manager Role
    add_role('manager', 'Manager', [
        'read' => true,
        'edit_posts' => false,
        'delete_posts' => false,
        'publish_posts' => false,
        'upload_files' => true,
        'manage_cleanindex_submissions' => true,
        'review_submissions' => true
    ]);
    
    // Organization Admin Role
    add_role('organization_admin', 'Organization Admin', [
        'read' => true,
        'edit_posts' => false,
        'delete_posts' => false,
        'publish_posts' => false,
        'upload_files' => true,
        'manage_own_organization' => true,
        'complete_assessments' => true
    ]);
    
    // Add capabilities to administrator
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_cleanindex_submissions');
        $admin->add_cap('review_submissions');
        $admin->add_cap('approve_organizations');
    }
}

function cip_remove_custom_roles() {
    remove_role('manager');
    remove_role('organization_admin');
}

function cip_user_can_manage_submissions() {
    return current_user_can('manage_cleanindex_submissions') || current_user_can('review_submissions');
}

function cip_user_can_approve() {
    return current_user_can('administrator') || current_user_can('approve_organizations');
}

function cip_get_user_registration($user_id = null) {
    global $wpdb;
    
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $table = $wpdb->prefix . 'company_registrations';
    $user = wp_get_current_user();
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE email = %s",
        $user->user_email
    ), ARRAY_A);
}

function cip_check_user_status() {
    $registration = cip_get_user_registration();
    
    if (!$registration) {
        return false;
    }
    
    return $registration['status'];
}