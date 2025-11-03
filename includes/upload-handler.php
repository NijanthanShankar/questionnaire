<?php
/**
 * CleanIndex Portal - File Upload Handler
 * Handles secure file uploads for registrations and assessments
 */

if (!defined('ABSPATH')) exit;

function cip_handle_file_upload($file, $type = 'registration', $user_id = null) {
    // Validate file
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred'];
    }
    
    // Validate file size (10MB max)
    if ($file['size'] > 10485760) {
        return ['success' => false, 'message' => 'File size exceeds 10MB limit'];
    }
    
    // Validate file type
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $file_type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    
    if (!in_array($file_type['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Only PDF and Word documents are allowed'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = sanitize_file_name(wp_unique_filename(
        CIP_UPLOAD_DIR . $type . '/',
        time() . '_' . $file['name']
    ));
    
    // Set upload directory based on type
    if ($type === 'assessment' && $user_id) {
        $upload_dir = CIP_UPLOAD_DIR . 'assessments/' . $user_id . '/';
        $upload_url = CIP_UPLOAD_URL . 'assessments/' . $user_id . '/';
    } else {
        $upload_dir = CIP_UPLOAD_DIR . 'registration/';
        $upload_url = CIP_UPLOAD_URL . 'registration/';
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
        file_put_contents($upload_dir . '.htaccess', 'deny from all');
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        return [
            'success' => true,
            'filename' => $filename,
            'url' => $upload_url . $filename,
            'path' => $upload_dir . $filename
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

function cip_ajax_upload_file() {
    check_ajax_referer('cip_nonce', 'nonce');
    
    if (!isset($_FILES['file'])) {
        wp_send_json_error('No file uploaded');
    }
    
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'registration';
    $user_id = get_current_user_id();
    
    $result = cip_handle_file_upload($_FILES['file'], $type, $user_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}

function cip_get_file_icon($filename) {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    
    $icons = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝'
    ];
    
    return isset($icons[$extension]) ? $icons[$extension] : '📎';
}

function cip_format_file_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

function cip_delete_file($file_path) {
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}