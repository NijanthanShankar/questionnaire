/**
 * ============================================
 * SOLUTION 3: ENHANCED SETTINGS PAGE WITH
 * EMAIL TEMPLATES & ALL OPTIONS
 * ============================================
 * 
 * REPLACE: admin/settings-page.php with this enhanced version
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Handle settings save
if (isset($_POST['cip_settings_submit'])) {
    check_admin_referer('cip_settings_action', 'cip_settings_nonce');
    
    // General settings
    update_option('cip_company_name', sanitize_text_field($_POST['company_name']));
    update_option('cip_admin_email', sanitize_email($_POST['admin_email']));
    update_option('cip_enable_registration', isset($_POST['enable_registration']) ? '1' : '0');
    update_option('cip_max_file_size', intval($_POST['max_file_size']));
    update_option('cip_allowed_file_types', sanitize_text_field($_POST['allowed_file_types']));
    update_option('cip_manager_access_code', sanitize_text_field($_POST['manager_access_code']));
    
    // Branding
    update_option('cip_brand_logo', sanitize_text_field($_POST['brand_logo']));
    update_option('cip_brand_tagline', sanitize_text_field($_POST['brand_tagline']));
    update_option('cip_brand_primary_color', sanitize_hex_color($_POST['brand_primary_color']));
    update_option('cip_brand_secondary_color', sanitize_hex_color($_POST['brand_secondary_color']));
    
    // GDPR
    update_option('cip_gdpr_enabled', isset($_POST['gdpr_enabled']) ? '1' : '0');
    update_option('cip_gdpr_privacy_url', esc_url($_POST['gdpr_privacy_url']));
    update_option('cip_gdpr_terms_url', esc_url($_POST['gdpr_terms_url']));
    
    // Certificate Settings
    update_option('cip_cert_grading_mode', sanitize_text_field($_POST['cert_grading_mode']));
    update_option('cip_cert_grade_esg3', intval($_POST['cert_grade_esg3']));
    update_option('cip_cert_grade_esg2', intval($_POST['cert_grade_esg2']));
    update_option('cip_cert_grade_esg1', intval($_POST['cert_grade_esg1']));
    update_option('cip_cert_validity_years', intval($_POST['cert_validity_years']));
    
    // Email Settings
    update_option('cip_email_from_name', sanitize_text_field($_POST['email_from_name']));
    update_option('cip_email_from_address', sanitize_email($_POST['email_from_address']));
    
    // Email Templates
    update_option('cip_email_approval_subject', sanitize_text_field($_POST['email_approval_subject']));
    update_option('cip_email_approval_body', wp_kses_post($_POST['email_approval_body']));
    
    update_option('cip_email_rejection_subject', sanitize_text_field($_POST['email_rejection_subject']));
    update_option('cip_email_rejection_body', wp_kses_post($_POST['email_rejection_body']));
    
    update_option('cip_email_info_request_subject', sanitize_text_field($_POST['email_info_request_subject']));
    update_option('cip_email_info_request_body', wp_kses_post($_POST['email_info_request_body']));
    
    update_option('cip_email_assessment_subject', sanitize_text_field($_POST['email_assessment_subject']));
    update_option('cip_email_assessment_body', wp_kses_post($_POST['email_assessment_body']));
    
    update_option('cip_email_certificate_subject', sanitize_text_field($_POST['email_certificate_subject']));
    update_option('cip_email_certificate_body', wp_kses_post($_POST['email_certificate_body']));
    
    echo '<div class="notice notice-success"><p>✅ All settings saved successfully!</p></div>';
}

// Get current options
$options = [
    'company_name' => get_option('cip_company_name', 'CleanIndex'),
    'admin_email' => get_option('cip_admin_email', get_option('admin_email')),
    'enable_registration' => get_option('cip_enable_registration', '1'),
    'max_file_size' => get_option('cip_max_file_size', 10),
    'allowed_file_types' => get_option('cip_allowed_file_types', 'pdf,doc,docx'),
    'manager_access_code' => get_option('cip_manager_access_code', 'CLEANINDEX2025'),
    'brand_logo' => get_option('cip_brand_logo', ''),
    'brand_tagline' => get_option('cip_brand_tagline', ''),
    'brand_primary' => get_option('cip_brand_primary_color', '#4CAF50'),
    'brand_secondary' => get_option('cip_brand_secondary_color', '#EB5E28'),
    'gdpr_enabled' => get_option('cip_gdpr_enabled', '0'),
    'gdpr_privacy_url' => get_option('cip_gdpr_privacy_url', ''),
    'gdpr_terms_url' => get_option('cip_gdpr_terms_url', ''),
    'cert_grading_mode' => get_option('cip_cert_grading_mode', 'automatic'),
    'cert_grade_esg3' => get_option('cip_cert_grade_esg3', 95),
    'cert_grade_esg2' => get_option('cip_cert_grade_esg2', 85),
    'cert_grade_esg1' => get_option('cip_cert_grade_esg1', 75),
    'cert_validity_years' => get_option('cip_cert_validity_years', 1),
    'email_from_name' => get_option('cip_email_from_name', 'CleanIndex'),
    'email_from_address' => get_option('cip_email_from_address', get_option('admin_email')),
    'email_approval_subject' => get_option('cip_email_approval_subject', 'Your Registration is Approved - CleanIndex'),
    'email_approval_body' => get_option('cip_email_approval_body', 'Your registration has been approved!'),
    'email_rejection_subject' => get_option('cip_email_rejection_subject', 'Additional Information Needed - CleanIndex'),
    'email_rejection_body' => get_option('cip_email_rejection_body', 'We need more information...'),
    'email_info_request_subject' => get_option('cip_email_info_request_subject', 'Information Request - CleanIndex'),
    'email_info_request_body' => get_option('cip_email_info_request_body', 'Please provide...'),
    'email_assessment_subject' => get_option('cip_email_assessment_subject', 'Start Your ESG Assessment - CleanIndex'),
    'email_assessment_body' => get_option('cip_email_assessment_body', 'You can now start your assessment...'),
    'email_certificate_subject' => get_option('cip_email_certificate_subject', 'Your ESG Certificate - CleanIndex'),
    'email_certificate_body' => get_option('cip_email_certificate_body', 'Your certificate is ready!'),
];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">⚙️ General</a>
        <a href="#branding" class="nav-tab" onclick="switchTab(event, 'branding')">🎨 Branding</a>
        <a href="#certificates" class="nav-tab" onclick="switchTab(event, 'certificates')">🏆 Certificates</a>
        <a href="#email" class="nav-tab" onclick="switchTab(event, 'email')">📧 Email Settings</a>
        <a href="#gdpr" class="nav-tab" onclick="switchTab(event, 'gdpr')">🔒 GDPR</a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('cip_settings_action', 'cip_settings_nonce'); ?>
        
        <!-- GENERAL TAB -->
        <div id="tab-general" class="tab-content" style="display: block; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>General Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="company_name">Company Name</label></th>
                    <td>
                        <input type="text" id="company_name" name="company_name" class="regular-text" 
                               value="<?php echo esc_attr($options['company_name']); ?>">
                        <p class="description">The name of your organization</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="admin_email">Admin Email</label></th>
                    <td>
                        <input type="email" id="admin_email" name="admin_email" class="regular-text" 
                               value="<?php echo esc_attr($options['admin_email']); ?>">
                        <p class="description">Email for system notifications</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="enable_registration">Enable Registration</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="enable_registration" name="enable_registration" 
                                   value="1" <?php checked($options['enable_registration'], '1'); ?>>
                            Allow new organizations to register
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="max_file_size">Max File Size (MB)</label></th>
                    <td>
                        <input type="number" id="max_file_size" name="max_file_size" class="small-text" 
                               value="<?php echo esc_attr($options['max_file_size']); ?>" min="1" max="100">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="allowed_file_types">Allowed File Types</label></th>
                    <td>
                        <input type="text" id="allowed_file_types" name="allowed_file_types" class="regular-text" 
                               value="<?php echo esc_attr($options['allowed_file_types']); ?>">
                        <p class="description">Comma-separated values (e.g., pdf,doc,docx)</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="manager_access_code">Manager Access Code</label></th>
                    <td>
                        <input type="text" id="manager_access_code" name="manager_access_code" class="regular-text" 
                               value="<?php echo esc_attr($options['manager_access_code']); ?>">
                        <p class="description">Code required for manager registration. Share with team members only.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- BRANDING TAB -->
        <div id="tab-branding" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>Branding Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="brand_logo">Logo URL</label></th>
                    <td>
                        <input type="text" id="brand_logo" name="brand_logo" class="regular-text" 
                               value="<?php echo esc_attr($options['brand_logo']); ?>">
                        <p class="description">Full URL to your logo image</p>
                        <?php if ($options['brand_logo']): ?>
                            <img src="<?php echo esc_url($options['brand_logo']); ?>" 
                                 style="max-width: 200px; max-height: 100px; margin-top: 10px; border-radius: 4px;">
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="brand_tagline">Tagline</label></th>
                    <td>
                        <input type="text" id="brand_tagline" name="brand_tagline" class="regular-text" 
                               value="<?php echo esc_attr($options['brand_tagline']); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="brand_primary_color">Primary Color</label></th>
                    <td>
                        <input type="color" id="brand_primary_color" name="brand_primary_color" 
                               value="<?php echo esc_attr($options['brand_primary']); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="brand_secondary_color">Secondary Color</label></th>
                    <td>
                        <input type="color" id="brand_secondary_color" name="brand_secondary_color" 
                               value="<?php echo esc_attr($options['brand_secondary']); ?>">
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- CERTIFICATES TAB -->
        <div id="tab-certificates" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>Certificate Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="cert_grading_mode">Grading System</label></th>
                    <td>
                        <label>
                            <input type="radio" id="cert_grading_auto" name="cert_grading_mode" value="automatic" 
                                   <?php checked($options['cert_grading_mode'], 'automatic'); ?>>
                            <strong>Automatic</strong> - Grade based on assessment score
                        </label><br>
                        <label style="margin-top: 10px; display: block;">
                            <input type="radio" name="cert_grading_mode" value="manual" 
                                   <?php checked($options['cert_grading_mode'], 'manual'); ?>>
                            <strong>Manual</strong> - Admin selects grade for each organization
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th colspan="2"><h3>Grade Thresholds</h3></th>
                </tr>
                
                <tr>
                    <th><label for="cert_grade_esg3">ESG+++ Threshold (%)</label></th>
                    <td>
                        <input type="number" id="cert_grade_esg3" name="cert_grade_esg3" class="small-text" 
                               value="<?php echo esc_attr($options['cert_grade_esg3']); ?>" min="0" max="100"> %
                    </td>
                </tr>
                
                <tr>
                    <th><label for="cert_grade_esg2">ESG++ Threshold (%)</label></th>
                    <td>
                        <input type="number" id="cert_grade_esg2" name="cert_grade_esg2" class="small-text" 
                               value="<?php echo esc_attr($options['cert_grade_esg2']); ?>" min="0" max="100"> %
                    </td>
                </tr>
                
                <tr>
                    <th><label for="cert_grade_esg1">ESG+ Threshold (%)</label></th>
                    <td>
                        <input type="number" id="cert_grade_esg1" name="cert_grade_esg1" class="small-text" 
                               value="<?php echo esc_attr($options['cert_grade_esg1']); ?>" min="0" max="100"> %
                    </td>
                </tr>
                
                <tr>
                    <th><label for="cert_validity_years">Certificate Validity (Years)</label></th>
                    <td>
                        <input type="number" id="cert_validity_years" name="cert_validity_years" class="small-text" 
                               value="<?php echo esc_attr($options['cert_validity_years']); ?>" min="1" max="5">
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- EMAIL SETTINGS TAB -->
        <div id="tab-email" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>Email Configuration</h2>
            
            <table class="form-table">
                <tr>
                    <th colspan="2"><h3 style="margin: 0;">Email Sender Information</h3></th>
                </tr>
                
                <tr>
                    <th><label for="email_from_name">From Name</label></th>
                    <td>
                        <input type="text" id="email_from_name" name="email_from_name" class="regular-text" 
                               value="<?php echo esc_attr($options['email_from_name']); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="email_from_address">From Email Address</label></th>
                    <td>
                        <input type="email" id="email_from_address" name="email_from_address" class="regular-text" 
                               value="<?php echo esc_attr($options['email_from_address']); ?>">
                    </td>
                </tr>
            </table>
            
            <!-- Email Templates -->
            <div style="margin-top: 30px;">
                <h3>Email Templates</h3>
                
                <!-- Approval Email -->
                <div style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 8px;">
                    <h4>✅ Approval Email</h4>
                    <p>
                        <label>Subject:</label>
                        <input type="text" name="email_approval_subject" class="large-text" 
                               value="<?php echo esc_attr($options['email_approval_subject']); ?>">
                    </p>
                    <p>
                        <label>Body:</label>
                        <?php wp_editor($options['email_approval_body'], 'email_approval_body', [
                            'media_buttons' => false,
                            'textarea_rows' => 8,
                            'teeny' => true
                        ]); ?>
                        <small style="color: #666;">Variables: {{company_name}}, {{assessment_link}}, {{dashboard_link}}</small>
                    </p>
                </div>
                
                <!-- Rejection Email -->
                <div style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 8px;">
                    <h4>❌ Information Request Email</h4>
                    <p>
                        <label>Subject:</label>
                        <input type="text" name="email_rejection_subject" class="large-text" 
                               value="<?php echo esc_attr($options['email_rejection_subject']); ?>">
                    </p>
                    <p>
                        <label>Body:</label>
                        <?php wp_editor($options['email_rejection_body'], 'email_rejection_body', [
                            'media_buttons' => false,
                            'textarea_rows' => 8,
                            'teeny' => true
                        ]); ?>
                        <small style="color: #666;">Variables: {{company_name}}, {{reason}}, {{register_link}}</small>
                    </p>
                </div>
                
                <!-- Info Request Email -->
                <div style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 8px;">
                    <h4>📋 Info Request Email</h4>
                    <p>
                        <label>Subject:</label>
                        <input type="text" name="email_info_request_subject" class="large-text" 
                               value="<?php echo esc_attr($options['email_info_request_subject']); ?>">
                    </p>
                    <p>
                        <label>Body:</label>
                        <?php wp_editor($options['email_info_request_body'], 'email_info_request_body', [
                            'media_buttons' => false,
                            'textarea_rows' => 8,
                            'teeny' => true
                        ]); ?>
                        <small style="color: #666;">Variables: {{company_name}}, {{message}}</small>
                    </p>
                </div>
                
                <!-- Assessment Email -->
                <div style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 8px;">
                    <h4>🚀 Assessment Start Email</h4>
                    <p>
                        <label>Subject:</label>
                        <input type="text" name="email_assessment_subject" class="large-text" 
                               value="<?php echo esc_attr($options['email_assessment_subject']); ?>">
                    </p>
                    <p>
                        <label>Body:</label>
                        <?php wp_editor($options['email_assessment_body'], 'email_assessment_body', [
                            'media_buttons' => false,
                            'textarea_rows' => 8,
                            'teeny' => true
                        ]); ?>
                        <small style="color: #666;">Variables: {{company_name}}, {{assessment_link}}, {{dashboard_link}}</small>
                    </p>
                </div>
                
                <!-- Certificate Email -->
                <div style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 8px;">
                    <h4>🏆 Certificate Email</h4>
                    <p>
                        <label>Subject:</label>
                        <input type="text" name="email_certificate_subject" class="large-text" 
                               value="<?php echo esc_attr($options['email_certificate_subject']); ?>">
                    </p>
                    <p>
                        <label>Body:</label>
                        <?php wp_editor($options['email_certificate_body'], 'email_certificate_body', [
                            'media_buttons' => false,
                            'textarea_rows' => 8,
                            'teeny' => true
                        ]); ?>
                        <small style="color: #666;">Variables: {{company_name}}, {{grade}}, {{certificate_url}}</small>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- GDPR TAB -->
        <div id="tab-gdpr" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>GDPR Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="gdpr_enabled">Enable GDPR Compliance</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="gdpr_enabled" name="gdpr_enabled" value="1" 
                                   <?php checked($options['gdpr_enabled'], '1'); ?>>
                            Show GDPR consent checkboxes on registration
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="gdpr_privacy_url">Privacy Policy URL</label></th>
                    <td>
                        <input type="url" id="gdpr_privacy_url" name="gdpr_privacy_url" class="large-text" 
                               value="<?php echo esc_url($options['gdpr_privacy_url']); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="gdpr_terms_url">Terms & Conditions URL</label></th>
                    <td>
                        <input type="url" id="gdpr_terms_url" name="gdpr_terms_url" class="large-text" 
                               value="<?php echo esc_url($options['gdpr_terms_url']); ?>">
                    </td>
                </tr>
            </table>
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
</script>