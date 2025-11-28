<?php
/**
 * CleanIndex Portal - Login Page
 */

if (!defined('ABSPATH')) exit;

// Redirect if already logged in
if (is_user_logged_in()) {
    $registration = cip_get_current_user_registration();
    if ($registration && $registration['status'] === 'approved') {
        wp_redirect(home_url('/cleanindex/dashboard'));
        exit;
    }
}

// Handle login form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cip_login_nonce'])) {
    
    // FIX 3: More lenient nonce checking
    $nonce_valid = wp_verify_nonce($_POST['cip_login_nonce'], 'cip_login');
    
    if (!$nonce_valid) {
        // Log the error for debugging
        error_log('CleanIndex Login: Nonce verification failed');
        error_log('POST nonce: ' . $_POST['cip_login_nonce']);
        error_log('Expected action: cip_login');
        
        $error = 'Session expired. Please try again.';
    } else {
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        // Check rate limit
        $rate_check = cip_check_login_rate_limit($email);
        
        if (is_wp_error($rate_check)) {
            $error = $rate_check->get_error_message();
        } else {
            // Attempt login
            $result = cip_login_user($email, $password, $remember);
            
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
                cip_record_login_attempt($email, false);
            } else {
                cip_record_login_attempt($email, true);
                cip_log_user_activity('user_login', 'User logged in successfully');
                
                // Redirect based on user role
                $user = wp_get_current_user();
                
                if (in_array('administrator', $user->roles)) {
                    wp_redirect(home_url('/cleanindex/admin-portal'));
                } elseif (in_array('manager', $user->roles)) {
                    wp_redirect(home_url('/cleanindex/manager'));
                } else {
                    wp_redirect(home_url('/cleanindex/dashboard'));
                }
                exit;
            }
        }
    }
}

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cip_reset_nonce'])) {
    if (wp_verify_nonce($_POST['cip_reset_nonce'], 'cip_reset')) {
        $email = sanitize_email($_POST['reset_email']);
        
        if (cip_email_exists($email)) {
            if (cip_send_password_reset_email($email)) {
                $success = 'Password reset instructions have been sent to your email.';
            } else {
                $error = 'Failed to send password reset email. Please try again.';
            }
        } else {
            $error = 'Email address not found in our records.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CleanIndex Portal</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="cip-container-narrow" style="padding-top: 5rem;">
        <div class="glass-card">
            <div class="text-center mb-4">
                <h1 style="color: var(--primary); margin-bottom: 0.5rem;">CleanIndex</h1>
                <p style="color: var(--gray-medium);">Sign in to your account</p>
            </div>
            <input type="hidden" name="cip_login_nonce" value="<?php echo esc_attr($login_nonce); ?>">

            <?php if (!empty($error)): ?>
                <div class="alert alert-error mb-3">
                    <?php echo esc_html($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success mb-3">
                    <?php echo esc_html($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" id="loginForm">
                <?php wp_nonce_field('cip_login', 'cip_login_nonce'); ?>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required 
                           placeholder="your.email@company.com"
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
                    <a href="#" onclick="showForgotPassword(); return false;" style="color: var(--accent); text-decoration: none;">
                        Forgot password?
                    </a>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Sign In
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-3" style="padding-top: 1.5rem; border-top: 1px solid var(--gray-light);">
                <p style="color: var(--gray-medium);">
                    Don't have an account? 
                    <a href="<?php echo home_url('/cleanindex/register'); ?>" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                        Register here
                    </a>
                </p>
            </div>
        </div>
        
        <!-- Forgot Password Modal -->
        <div id="forgotPasswordModal" class="modal-overlay" style="display: none;">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h2>Reset Password</h2>
                    <button class="modal-close" onclick="closeForgotPassword()">×</button>
                </div>
                <div>
                    <p style="margin-bottom: 1.5rem; color: var(--gray-medium);">
                        Enter your email address and we'll send you instructions to reset your password.
                    </p>
                    
                    <form method="POST">
                        <?php wp_nonce_field('cip_reset', 'cip_reset_nonce'); ?>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="reset_email" class="form-control" required 
                                   placeholder="your.email@company.com">
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                Send Reset Link
                            </button>
                            <button type="button" class="btn btn-outline" onclick="closeForgotPassword()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Info Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 3rem;">
            <div class="glass-card" style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🌱</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">ESG Certification</h3>
                <p style="color: var(--gray-medium); font-size: 0.875rem;">
                    Get certified based on CSRD/ESRS standards
                </p>
            </div>
            
            <div class="glass-card" style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">📊</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Benchmarking</h3>
                <p style="color: var(--gray-medium); font-size: 0.875rem;">
                    Compare your performance with industry peers
                </p>
            </div>
            
            <div class="glass-card" style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🌍</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Directory</h3>
                <p style="color: var(--gray-medium); font-size: 0.875rem;">
                    Join our community of sustainable organizations
                </p>
            </div>
        </div>
    </div>
    
    <script>
    function showForgotPassword() {
        document.getElementById('forgotPasswordModal').style.display = 'flex';
    }
    
    function closeForgotPassword() {
        document.getElementById('forgotPasswordModal').style.display = 'none';
    }
    
    // Close modal on outside click
    document.getElementById('forgotPasswordModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeForgotPassword();
        }
    });
    
    // Form validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = this.querySelector('[name="email"]').value;
        const password = this.querySelector('[name="password"]').value;
        
        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all required fields');
            return false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return false;
        }
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>