<?php
/**
 * CleanIndex Portal - Manager Registration Page
 * Separate registration for manager accounts
 */

if (!defined('ABSPATH')) exit;

// Redirect if already logged in
if (is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/manager'));
    exit;
}

// Handle registration
$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cip_manager_register_nonce'])) {
    if (!wp_verify_nonce($_POST['cip_manager_register_nonce'], 'cip_manager_register')) {
        $errors[] = 'Security check failed. Please try again.';
    } else {
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $access_code = sanitize_text_field($_POST['access_code']);
        
        // Validation
        if (empty($full_name)) $errors[] = 'Full name is required';
        if (empty($email) || !is_email($email)) $errors[] = 'Valid email is required';
        if (empty($password) || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
        if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
        
        // Check access code (you can set this in WordPress settings)
        $valid_access_code = get_option('cip_manager_access_code', 'CLEANINDEX2025');
        if ($access_code !== $valid_access_code) {
            $errors[] = 'Invalid access code. Please contact administration.';
        }
        
        // Check if email exists
        if (email_exists($email)) {
            $errors[] = 'This email is already registered';
        }
        
        if (empty($errors)) {
            // Create WordPress user
            $user_id = wp_create_user($email, $password, $email);
            
            if (is_wp_error($user_id)) {
                $errors[] = $user_id->get_error_message();
            } else {
                // Set user role to manager
                $user = new WP_User($user_id);
                $user->set_role('manager');
                
                // Update user meta
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $full_name,
                    'first_name' => explode(' ', $full_name)[0],
                    'last_name' => isset(explode(' ', $full_name)[1]) ? explode(' ', $full_name)[1] : ''
                ]);
                
                // Log them in automatically
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                
                // Send notification to admin
                $admin_email = get_option('admin_email');
                wp_mail(
                    $admin_email,
                    'New Manager Registration - CleanIndex',
                    "A new manager has registered:\n\nName: {$full_name}\nEmail: {$email}\n\nThey now have access to the manager dashboard."
                );
                
                // Redirect to manager dashboard
                wp_redirect(home_url('/cleanindex/manager'));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Registration - CleanIndex Portal</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="cip-container-narrow" style="padding-top: 5rem;">
        <div class="glass-card">
            <div class="text-center mb-4">
                <h1 style="color: var(--primary); margin-bottom: 0.5rem;">CleanIndex Manager</h1>
                <p style="color: var(--gray-medium);">Register as a Review Manager</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error mb-3">
                    <strong>Please correct the following errors:</strong>
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <?php wp_nonce_field('cip_manager_register', 'cip_manager_register_nonce'); ?>
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required 
                           placeholder="John Smith"
                           value="<?php echo isset($_POST['full_name']) ? esc_attr($_POST['full_name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" required 
                           placeholder="manager@company.com"
                           value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="8"
                           placeholder="At least 8 characters">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required 
                           placeholder="Re-enter password">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Manager Access Code *</label>
                    <input type="text" name="access_code" class="form-control" required 
                           placeholder="Enter the access code provided by administration">
                    <small style="color: var(--gray-medium); font-size: 0.875rem;">
                        Contact administration if you don't have an access code
                    </small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Create Manager Account
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-3" style="padding-top: 1.5rem; border-top: 1px solid var(--gray-light);">
                <p style="color: var(--gray-medium);">
                    Already have an account? 
                    <a href="<?php echo home_url('/cleanindex/manager-login'); ?>" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                        Login here
                    </a>
                </p>
                <p style="color: var(--gray-medium); margin-top: 0.5rem;">
                    Organization registration? 
                    <a href="<?php echo home_url('/cleanindex/register'); ?>" style="color: var(--accent); font-weight: 600; text-decoration: none;">
                        Register as Organization
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>