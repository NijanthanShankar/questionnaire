<?php
/**
 * Enhanced Admin Settings Page
 * Add this to admin/settings-page.php (REPLACE existing)
 */

if (!defined('ABSPATH')) exit;

// Handle settings save
if (isset($_POST['cip_settings_submit'])) {
    check_admin_referer('cip_settings_action', 'cip_settings_nonce');
    
    // Save all settings
    update_option('cip_company_name', sanitize_text_field($_POST['company_name']));
    update_option('cip_admin_email', sanitize_email($_POST['admin_email']));
    update_option('cip_enable_registration', isset($_POST['enable_registration']) ? '1' : '0');
    update_option('cip_max_file_size', intval($_POST['max_file_size']));
    update_option('cip_allowed_file_types', sanitize_text_field($_POST['allowed_file_types']));
    
    // Manager Access Code
    update_option('cip_manager_access_code', sanitize_text_field($_POST['manager_access_code']));
    
    // Branding
    update_option('cip_brand_logo', sanitize_text_field($_POST['brand_logo']));
    update_option('cip_brand_tagline', sanitize_text_field($_POST['brand_tagline']));
    update_option('cip_brand_primary_color', sanitize_hex_color($_POST['brand_primary_color']));
    update_option('cip_brand_secondary_color', sanitize_hex_color($_POST['brand_secondary_color']));
    
    // GDPR Settings
    update_option('cip_gdpr_enabled', isset($_POST['gdpr_enabled']) ? '1' : '0');
    update_option('cip_gdpr_privacy_url', esc_url($_POST['gdpr_privacy_url']));
    update_option('cip_gdpr_terms_url', esc_url($_POST['gdpr_terms_url']));
    
    // Certificate Settings
    update_option('cip_cert_grading_mode', sanitize_text_field($_POST['cert_grading_mode']));
    update_option('cip_cert_grade_esg3', intval($_POST['cert_grade_esg3']));
    update_option('cip_cert_grade_esg2', intval($_POST['cert_grade_esg2']));
    update_option('cip_cert_grade_esg1', intval($_POST['cert_grade_esg1']));
    update_option('cip_cert_validity_years', intval($_POST['cert_validity_years']));
    
    echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
}

// Get current settings
$company_name = get_option('cip_company_name', 'CleanIndex');
$admin_email = get_option('cip_admin_email', get_option('admin_email'));
$enable_registration = get_option('cip_enable_registration', '1');
$max_file_size = get_option('cip_max_file_size', 10);
$allowed_file_types = get_option('cip_allowed_file_types', 'pdf,doc,docx');
$manager_access_code = get_option('cip_manager_access_code', 'CLEANINDEX2025');
$brand_logo = get_option('cip_brand_logo', '');
$brand_tagline = get_option('cip_brand_tagline', 'ESG Certification Platform');
$brand_primary = get_option('cip_brand_primary_color', '#4CAF50');
$brand_secondary = get_option('cip_brand_secondary_color', '#EB5E28');
$gdpr_enabled = get_option('cip_gdpr_enabled', '0');
$gdpr_privacy_url = get_option('cip_gdpr_privacy_url', '');
$gdpr_terms_url = get_option('cip_gdpr_terms_url', '');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">⚙️ General</a>
        <a href="#branding" class="nav-tab" onclick="switchTab(event, 'branding')">🎨 Branding</a>
        <a href="#managers" class="nav-tab" onclick="switchTab(event, 'managers')">👥 Managers</a>
        <a href="#certificates" class="nav-tab" onclick="switchTab(event, 'certificates')">🏆 Certificates</a>
        <a href="#gdpr" class="nav-tab" onclick="switchTab(event, 'gdpr')">🔒 GDPR</a>
        <a href="#email" class="nav-tab" onclick="switchTab(event, 'email')">📧 Email Templates</a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('cip_settings_action', 'cip_settings_nonce'); ?>
        
        <!-- General Tab -->
        <div id="tab-general" class="tab-content" style="display: block;">
            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>📊 General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Company Name</th>
                        <td><input type="text" name="company_name" value="<?php echo esc_attr($company_name); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Admin Email</th>
                        <td><input type="email" name="admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Enable Registration</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_registration" value="1" <?php checked($enable_registration, '1'); ?>>
                                Allow new organizations to register
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Max File Size (MB)</th>
                        <td><input type="number" name="max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="100" class="small-text"></td>
                    </tr>
                    <tr>
                        <th>Allowed File Types</th>
                        <td><input type="text" name="allowed_file_types" value="<?php echo esc_attr($allowed_file_types); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Branding Tab -->
        <div id="tab-branding" class="tab-content" style="display: none;">
            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>🎨 Branding Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Logo URL</th>
                        <td>
                            <input type="text" name="brand_logo" value="<?php echo esc_attr($brand_logo); ?>" class="regular-text">
                            <p class="description">Enter full URL to your logo image</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Tagline</th>
                        <td><input type="text" name="brand_tagline" value="<?php echo esc_attr($brand_tagline); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Primary Color</th>
                        <td><input type="color" name="brand_primary_color" value="<?php echo esc_attr($brand_primary); ?>"></td>
                    </tr>
                    <tr>
                        <th>Secondary Color</th>
                        <td><input type="color" name="brand_secondary_color" value="<?php echo esc_attr($brand_secondary); ?>"></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Manager Access Tab -->
        <div id="tab-managers" class="tab-content" style="display: none;">
            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>👥 Manager Access</h2>
                <table class="form-table">
                    <tr>
                        <th>Manager Access Code</th>
                        <td>
                            <input type="text" name="manager_access_code" value="<?php echo esc_attr($manager_access_code); ?>" class="regular-text">
                            <p class="description">This code is required to register as a manager. Share it only with trusted team members.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Manager Registration URL</th>
                        <td>
                            <input type="text" value="<?php echo home_url('/cleanindex/manager-register'); ?>" class="regular-text" readonly onclick="this.select()">
                            <button type="button" onclick="copyToClipboard('<?php echo home_url('/cleanindex/manager-register'); ?>')" class="button">Copy URL</button>
                        </td>
                    </tr>
                </table>
                
                <div style="margin-top: 30px;">
                    <h3>Current Managers</h3>
                    <?php
                    $managers = get_users(['role' => 'manager']);
                    if (!empty($managers)):
                    ?>
                        <table class="wp-list-table widefat">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($managers as $manager): ?>
                                    <tr>
                                        <td><?php echo esc_html($manager->display_name); ?></td>
                                        <td><?php echo esc_html($manager->user_email); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($manager->user_registered)); ?></td>
                                        <td>
                                            <a href="<?php echo get_edit_user_link($manager->ID); ?>" class="button button-small">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No managers registered yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Certificates Tab -->
        <div id="tab-certificates" class="tab-content" style="display: none;">
            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>🏆 Certificate Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Grading System</th>
                        <td>
                            <label>
                                <input type="radio" name="cert_grading_mode" value="automatic" <?php checked(get_option('cip_cert_grading_mode', 'automatic'), 'automatic'); ?>>
                                <strong>Automatic</strong> - Grade based on assessment score
                            </label><br>
                            <label style="margin-top: 10px; display: block;">
                                <input type="radio" name="cert_grading_mode" value="manual" <?php checked(get_option('cip_cert_grading_mode'), 'manual'); ?>>
                                <strong>Manual</strong> - Admin selects grade
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Grade Thresholds</th>
                        <td>
                            <table>
                                <tr>
                                    <td><strong>ESG+++</strong></td>
                                    <td>≥ <input type="number" name="cert_grade_esg3" value="<?php echo esc_attr(get_option('cip_cert_grade_esg3', '95')); ?>" class="small-text" min="0" max="100">%</td>
                                </tr>
                                <tr>
                                    <td><strong>ESG++</strong></td>
                                    <td>≥ <input type="number" name="cert_grade_esg2" value="<?php echo esc_attr(get_option('cip_cert_grade_esg2', '85')); ?>" class="small-text" min="0" max="100">%</td>
                                </tr>
                                <tr>
                                    <td><strong>ESG+</strong></td>
                                    <td>≥ <input type="number" name="cert_grade_esg1" value="<?php echo esc_attr(get_option('cip_cert_grade_esg1', '75')); ?>" class="small-text" min="0" max="100">%</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <th>Certificate Validity</th>
                        <td>
                            <input type="number" name="cert_validity_years" value="<?php echo esc_attr(get_option('cip_cert_validity_years', '1')); ?>" class="small-text" min="1" max="5"> years
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- GDPR Tab -->
        <div id="tab-gdpr" class="tab-content" style="display: none;">
            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>🔒 GDPR Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable GDPR Compliance</th>
                        <td>
                            <label>
                                <input type="checkbox" name="gdpr_enabled" value="1" <?php checked($gdpr_enabled, '1'); ?>>
                                Show GDPR consent checkboxes on registration
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Privacy Policy URL</th>
                        <td><input type="url" name="gdpr_privacy_url" value="<?php echo esc_attr($gdpr_privacy_url); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Terms & Conditions URL</th>
                        <td><input type="url" name="gdpr_terms_url" value="<?php echo esc_attr($gdpr_terms_url); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Email Templates Tab -->
        <div id="tab-email" class="tab-content" style="display: none;">
            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>📧 Email Templates</h2>
                <p>Email templates are located in: <code>/mailer/email-templates/</code></p>
                <ul>
                    <li><strong>approval.html</strong> - Sent when organization is approved</li>
                    <li><strong>rejection.html</strong> - Sent when more information is needed</li>
                    <li><strong>info-request.html</strong> - Sent when manager requests more info</li>
                </ul>
                <p>You can customize these HTML files directly in your plugin folder.</p>
            </div>
        </div>
        
        <p class="submit">
            <button type="submit" name="cip_settings_submit" class="button button-primary button-large">
                💾 Save All Settings
            </button>
        </p>
    </form>
</div>

<script>
function switchTab(e, tabName) {
    e.preventDefault();
    document.querySelectorAll('.tab-content').forEach(tab => tab.style.display = 'none');
    document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('nav-tab-active'));
    document.getElementById('tab-' + tabName).style.display = 'block';
    e.target.classList.add('nav-tab-active');
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('URL copied to clipboard!');
    });
}
</script>