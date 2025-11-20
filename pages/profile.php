<?php
/**
 * Profile Management Page (REQUIREMENT #5)
 * Create: pages/profile.php
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

$user = wp_get_current_user();
$success = '';
$errors = [];

// Get user registration if exists
global $wpdb;
$table = $wpdb->prefix . 'company_registrations';
$registration = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table WHERE email = %s",
    $user->user_email
), ARRAY_A);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    check_admin_referer('cip_profile_update', 'profile_nonce');
    
    $full_name = sanitize_text_field($_POST['full_name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    
    // Update WordPress user
    wp_update_user([
        'ID' => $user->ID,
        'display_name' => $full_name,
        'user_email' => $email
    ]);
    
    // Update phone in user meta
    update_user_meta($user->ID, 'phone', $phone);
    
    // For organization users, update registration data
    if ($registration) {
        $company_name = sanitize_text_field($_POST['company_name']);
        $industry = sanitize_text_field($_POST['industry']);
        $country = sanitize_text_field($_POST['country']);
        $num_employees = intval($_POST['num_employees']);
        
        $wpdb->update(
            $table,
            [
                'employee_name' => $full_name,
                'company_name' => $company_name,
                'industry' => $industry,
                'country' => $country,
                'num_employees' => $num_employees
            ],
            ['id' => $registration['id']]
        );
    }
    
    // Handle logo upload (REQUIREMENT #5)
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['company_logo'];
        
        // Validate image
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowed_types) && $file['size'] < 2097152) { // 2MB
            
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $upload = wp_handle_upload($file, ['test_form' => false]);
            
            if (!isset($upload['error'])) {
                update_user_meta($user->ID, 'company_logo', $upload['url']);
                $success = 'Profile updated successfully with new logo!';
            } else {
                $errors[] = 'Logo upload failed: ' . $upload['error'];
            }
        } else {
            $errors[] = 'Invalid logo file. Only JPG, PNG, GIF under 2MB allowed.';
        }
    } else {
        $success = 'Profile updated successfully!';
    }
    
    // Password change
    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            wp_set_password($_POST['new_password'], $user->ID);
            $success .= ' Password changed.';
        } else {
            $errors[] = 'Passwords do not match.';
        }
    }
}

$company_logo = get_user_meta($user->ID, 'company_logo', true);
$phone = get_user_meta($user->ID, 'phone', true);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - CleanIndex</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">🌱 CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/dashboard'); ?>">📊 Dashboard</a></li>
                    <?php if ($registration && $registration['status'] === 'approved'): ?>
                        <li><a href="<?php echo home_url('/cleanindex/assessment'); ?>">📝 Assessment</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo home_url('/cleanindex/profile'); ?>" class="active">👤 Profile</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url('/cleanindex/login')); ?>">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <div class="dashboard-header">
                <h1>Profile Settings</h1>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo esc_html($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="glass-card">
                <form method="POST" enctype="multipart/form-data">
                    <?php wp_nonce_field('cip_profile_update', 'profile_nonce'); ?>
                    
                    <h2 class="mb-3">Personal Information</h2>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required
                               value="<?php echo esc_attr($user->display_name); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo esc_attr($user->user_email); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?php echo esc_attr($phone); ?>">
                    </div>
                    
                    <?php if ($registration): ?>
                        <hr style="margin: 32px 0; border: none; border-top: 1px solid var(--gray-200);">
                        
                        <h2 class="mb-3">Company Information</h2>
                        
                        <div class="form-group">
                            <label class="form-label">Company Logo</label>
                            <?php if ($company_logo): ?>
                                <div style="margin-bottom: 16px;">
                                    <img src="<?php echo esc_url($company_logo); ?>" alt="Company Logo" 
                                         style="max-width: 200px; max-height: 100px; border-radius: 8px; border: 1px solid var(--gray-200);">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="company_logo" accept="image/*" class="form-control">
                            <p style="color: var(--gray-500); font-size: 12px; margin-top: 4px;">
                                JPG, PNG or GIF. Max 2MB.
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control"
                                   value="<?php echo esc_attr($registration['company_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Industry</label>
                            <select name="industry" class="form-control">
                                <option value="Agriculture" <?php selected($registration['industry'], 'Agriculture'); ?>>Agriculture</option>
                                <option value="Construction" <?php selected($registration['industry'], 'Construction'); ?>>Construction</option>
                                <option value="Education" <?php selected($registration['industry'], 'Education'); ?>>Education</option>
                                <option value="Energy" <?php selected($registration['industry'], 'Energy'); ?>>Energy</option>
                                <option value="Finance" <?php selected($registration['industry'], 'Finance'); ?>>Finance</option>
                                <option value="Healthcare" <?php selected($registration['industry'], 'Healthcare'); ?>>Healthcare</option>
                                <option value="Manufacturing" <?php selected($registration['industry'], 'Manufacturing'); ?>>Manufacturing</option>
                                <option value="Technology" <?php selected($registration['industry'], 'Technology'); ?>>Technology</option>
                                <option value="Other" <?php selected($registration['industry'], 'Other'); ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <select name="country" class="form-control">
                                <option value="Netherlands" <?php selected($registration['country'], 'Netherlands'); ?>>Netherlands</option>
                                <option value="Belgium" <?php selected($registration['country'], 'Belgium'); ?>>Belgium</option>
                                <option value="Germany" <?php selected($registration['country'], 'Germany'); ?>>Germany</option>
                                <option value="France" <?php selected($registration['country'], 'France'); ?>>France</option>
                                <option value="Other" <?php selected($registration['country'], 'Other'); ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Number of Employees</label>
                            <input type="number" name="num_employees" class="form-control"
                                   value="<?php echo esc_attr($registration['num_employees']); ?>">
                        </div>
                    <?php endif; ?>
                    
                    <hr style="margin: 32px 0; border: none; border-top: 1px solid var(--gray-200);">
                    
                    <h2 class="mb-3">Change Password</h2>
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8"
                               placeholder="Leave blank to keep current password">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            💾 Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>