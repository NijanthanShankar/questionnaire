<?php
/**
 * CleanIndex Portal - Manager Login Page
 * Separate login for manager accounts
 */

if (!defined('ABSPATH')) exit;

// Redirect if already logged in
if (is_user_logged_in()) {
    $user = wp_get_current_user();
    if (in_array('manager', $user->roles) || in_array('administrator', $user->roles)) {
        wp_redirect(home_url('/cleanindex/manager'));
        exit;
    }
}

// Handle login
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cip_manager_login_nonce'])) {
    if (wp_verify_nonce($_POST['cip_manager_login_nonce'], 'cip_manager_login')) {
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        // Check rate limit
        $attempts_key = 'cip_manager_login_attempts_' . md5($email);
        $attempts = get_transient($attempts_key);
        
        if ($attempts !== false && $attempts >= 5) {
            $error = 'Too many login attempts. Please try again in 15 minutes.';
        } else {
            // Attempt login
            $creds = [
                'user_login' => $email,
                'user_password' => $password,
                'remember' => $remember
            ];
            
            $user = wp_signon($creds, false);
            
            if (is_wp_error($user)) {
                $error = 'Invalid email or password';
                
                // Record failed attempt
                if ($attempts === false) {
                    $attempts = 0;
                }
                set_transient($attempts_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
            } else {
                // Check if user has manager or admin role
                if (in_array('manager', $user->roles) || in_array('administrator', $user->roles)) {
                    // Clear failed attempts
                    delete_transient($attempts_key);
                    
                    // Redirect to manager dashboard
                    wp_redirect(home_url('/cleanindex/manager'));
                    exit;
                } else {
                    // Not a manager, log them out
                    wp_logout();
                    $error = 'This login is for managers only. Please use the organization login.';
                }
            }
        }
    } else {
        $error = 'Security check failed. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Login - CleanIndex Portal</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="cip-container-narrow" style="padding-top: 5rem;">
        <div class="glass-card">
            <div class="text-center mb-4">
                <h1 style="color: var(--primary); margin-bottom: 0.5rem;">CleanIndex Manager</h1>
                <p style="color: var(--gray-medium);">Sign in to your manager account</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error mb-3">
                    <?php echo esc_html($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <?php wp_nonce_field('cip_manager_login', 'cip_manager_login_nonce'); ?>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required 
                           placeholder="manager@company.com"
                           value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required 
                           placeholder="Enter your password">
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
                    <label style="display: flex; align-items: center; margin: 0;">
                        <input type="checkbox" name="remember" value="1" style="margin-right: 0.5rem;">
                        <span>Remember me</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Sign In as Manager
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-3" style="padding-top: 1.5rem; border-top: 1px solid var(--gray-light);">
                <p style="color: var(--gray-medium);">
                    Don't have a manager account? 
                    <a href="<?php echo home_url('/cleanindex/manager-register'); ?>" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                        Register here
                    </a>
                </p>
                <p style="color: var(--gray-medium); margin-top: 0.5rem;">
                    Organization login? 
                    <a href="<?php echo home_url('/cleanindex/login'); ?>" style="color: var(--accent); font-weight: 600; text-decoration: none;">
                        Organization Login
                    </a>
                </p>
            </div>
        </div>
        
        <div class="glass-card" style="margin-top: 2rem; text-align: center;">
            <h3 style="margin-bottom: 1rem;">👥 Manager Access</h3>
            <p style="color: var(--gray-medium); font-size: 0.875rem;">
                This portal is for CleanIndex review managers to evaluate and approve organization registrations.
            </p>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>