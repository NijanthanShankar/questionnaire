<?php
/**
 * Admin Settings Page Template
 * 
 * Variables available:
 * - $enable_registration
 * - $enable_auto_approval
 * - $admin_email
 * - $company_name
 * - $max_file_size
 * - $allowed_file_types
 * - $activated
 * - $stats
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
        <span style="font-size: 14px; color: #666; margin-left: 10px;">v<?php echo CIP_VERSION; ?></span>
    </h1>
    
    <?php if ($activated): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✅ Plugin Activated Successfully!</strong></p>
            <p><?php _e('Next steps:', 'cleanindex-portal'); ?></p>
            <ol>
                <li><?php _e('Go to', 'cleanindex-portal'); ?> <a href="<?php echo admin_url('options-permalink.php'); ?>"><?php _e('Settings > Permalinks', 'cleanindex-portal'); ?></a> <?php _e('and click "Save Changes"', 'cleanindex-portal'); ?></li>
                <li><?php _e('Configure your settings below', 'cleanindex-portal'); ?></li>
                <li><?php _e('Test the registration page:', 'cleanindex-portal'); ?> <a href="<?php echo home_url('/cleanindex/register'); ?>" target="_blank"><?php _e('View Registration Page', 'cleanindex-portal'); ?></a></li>
            </ol>
        </div>
    <?php endif; ?>
    
    <!-- Quick Links -->
    <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0;">🔗 <?php _e('Quick Links', 'cleanindex-portal'); ?></h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <a href="<?php echo home_url('/cleanindex/register'); ?>" class="button" target="_blank">📝 <?php _e('Registration Page', 'cleanindex-portal'); ?></a>
            <a href="<?php echo home_url('/cleanindex/login'); ?>" class="button" target="_blank">🔐 <?php _e('Login Page', 'cleanindex-portal'); ?></a>
            <a href="<?php echo home_url('/cleanindex/admin-portal'); ?>" class="button button-primary" target="_blank">⚡ <?php _e('Admin Portal', 'cleanindex-portal'); ?></a>
            <a href="<?php echo home_url('/cleanindex/manager'); ?>" class="button" target="_blank">👔 <?php _e('Manager Portal', 'cleanindex-portal'); ?></a>
        </div>
    </div>
    
    <!-- Statistics Dashboard -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
        <div style="background: #fff; padding: 20px; text-align: center; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 36px; font-weight: bold; color: #4CAF50;"><?php echo $stats['total']; ?></div>
            <div style="color: #666; margin-top: 5px;"><?php _e('Total Registrations', 'cleanindex-portal'); ?></div>
        </div>
        <div style="background: #fff; padding: 20px; text-align: center; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 36px; font-weight: bold; color: #EB5E28;"><?php echo $stats['pending']; ?></div>
            <div style="color: #666; margin-top: 5px;"><?php _e('Pending Review', 'cleanindex-portal'); ?></div>
        </div>
        <div style="background: #fff; padding: 20px; text-align: center; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 36px; font-weight: bold; color: #4CAF50;"><?php echo $stats['approved']; ?></div>
            <div style="color: #666; margin-top: 5px;"><?php _e('Approved', 'cleanindex-portal'); ?></div>
        </div>
        <div style="background: #fff; padding: 20px; text-align: center; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 36px; font-weight: bold; color: #999;"><?php echo $stats['rejected']; ?></div>
            <div style="color: #666; margin-top: 5px;"><?php _e('Rejected', 'cleanindex-portal'); ?></div>
        </div>
    </div>
    
    <!-- Settings Form -->
    <form method="post" action="">
        <?php wp_nonce_field('cip_settings_action', 'cip_settings_nonce'); ?>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2><?php _e('General Settings', 'cleanindex-portal'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Company Name', 'cleanindex-portal'); ?></th>
                    <td>
                        <input type="text" name="company_name" value="<?php echo esc_attr($company_name); ?>" class="regular-text">
                        <p class="description"><?php _e('This name appears in emails and throughout the portal', 'cleanindex-portal'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Admin Email', 'cleanindex-portal'); ?></th>
                    <td>
                        <input type="email" name="admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text">
                        <p class="description"><?php _e('Notifications will be sent to this email', 'cleanindex-portal'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Enable Registration', 'cleanindex-portal'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_registration" value="1" <?php checked($enable_registration, '1'); ?>>
                            <?php _e('Allow new organizations to register', 'cleanindex-portal'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Auto-Approval', 'cleanindex-portal'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_auto_approval" value="1" <?php checked($enable_auto_approval, '1'); ?>>
                            <?php _e('Automatically approve registrations (bypass manager review)', 'cleanindex-portal'); ?>
                        </label>
                        <p class="description">⚠️ <?php _e('Use with caution - not recommended for production', 'cleanindex-portal'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2><?php _e('File Upload Settings', 'cleanindex-portal'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Max File Size (MB)', 'cleanindex-portal'); ?></th>
                    <td>
                        <input type="number" name="max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="100" class="small-text">
                        <p class="description"><?php printf(__('Maximum file size for uploads (current server limit: %s)', 'cleanindex-portal'), ini_get('upload_max_filesize')); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Allowed File Types', 'cleanindex-portal'); ?></th>
                    <td>
                        <input type="text" name="allowed_file_types" value="<?php echo esc_attr($allowed_file_types); ?>" class="regular-text">
                        <p class="description"><?php _e('Comma-separated list (e.g., pdf,doc,docx)', 'cleanindex-portal'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <button type="submit" name="cip_settings_submit" class="button button-primary button-large">
                💾 <?php _e('Save Settings', 'cleanindex-portal'); ?>
            </button>
        </p>
    </form>
    
    <!-- Troubleshooting Section -->
    <div style="background: #fff3e0; padding: 20px; margin: 20px 0; border-left: 4px solid #ff9800;">
        <h2 style="margin-top: 0;">🔧 <?php _e('Troubleshooting', 'cleanindex-portal'); ?></h2>
        
        <h3><?php _e('Pages Showing 404 Error?', 'cleanindex-portal'); ?></h3>
        <ol>
            <li><?php _e('Go to', 'cleanindex-portal'); ?> <a href="<?php echo admin_url('options-permalink.php'); ?>"><?php _e('Settings > Permalinks', 'cleanindex-portal'); ?></a></li>
            <li><?php _e('Click "Save Changes" (don\'t modify anything)', 'cleanindex-portal'); ?></li>
            <li><?php _e('Test your pages again', 'cleanindex-portal'); ?></li>
        </ol>
        
        <h3><?php _e('Emails Not Sending?', 'cleanindex-portal'); ?></h3>
        <ol>
            <li><?php _e('Install', 'cleanindex-portal'); ?> <strong>WP Mail SMTP</strong> <?php _e('plugin', 'cleanindex-portal'); ?></li>
            <li><?php _e('Configure your email provider (Gmail, SendGrid, etc.)', 'cleanindex-portal'); ?></li>
            <li><?php _e('Test email delivery', 'cleanindex-portal'); ?></li>
        </ol>
        
        <h3><?php _e('File Uploads Failing?', 'cleanindex-portal'); ?></h3>
        <ol>
            <li><?php printf(__('Check server upload limits: %s', 'cleanindex-portal'), '<code>' . ini_get('upload_max_filesize') . '</code>'); ?></li>
            <li><?php printf(__('Verify folder permissions: %s', 'cleanindex-portal'), '<code>' . CIP_UPLOAD_DIR . '</code>'); ?></li>
            <li><?php _e('Ensure folder is writable (755 for directories)', 'cleanindex-portal'); ?></li>
        </ol>
    </div>
</div>