<?php
/**
 * COMPLETE SETTINGS PAGE - ALL SECTIONS WORKING
 * REPLACE: admin/settings-page.php
 * 
 * This file includes ALL settings sections with proper save functionality
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Handle form submission directly
if (isset($_POST['cip_save_settings']) && check_admin_referer('cip_settings_save', 'cip_settings_nonce')) {
    
    // General Settings
    update_option('cip_company_name', sanitize_text_field($_POST['cip_company_name']));
    update_option('cip_admin_email', sanitize_email($_POST['cip_admin_email']));
    update_option('cip_enable_registration', isset($_POST['cip_enable_registration']) ? '1' : '0');
    update_option('cip_max_file_size', intval($_POST['cip_max_file_size']));
    update_option('cip_allowed_file_types', sanitize_text_field($_POST['cip_allowed_file_types']));
    update_option('cip_manager_access_code', sanitize_text_field($_POST['cip_manager_access_code']));
    
    // Branding
    update_option('cip_brand_logo', esc_url_raw($_POST['cip_brand_logo']));
    update_option('cip_brand_tagline', sanitize_text_field($_POST['cip_brand_tagline']));
    update_option('cip_brand_primary_color', sanitize_hex_color($_POST['cip_brand_primary_color']));
    update_option('cip_brand_secondary_color', sanitize_hex_color($_POST['cip_brand_secondary_color']));
    
    // GDPR
    update_option('cip_gdpr_enabled', isset($_POST['cip_gdpr_enabled']) ? '1' : '0');
    update_option('cip_gdpr_privacy_url', esc_url_raw($_POST['cip_gdpr_privacy_url']));
    update_option('cip_gdpr_terms_url', esc_url_raw($_POST['cip_gdpr_terms_url']));
    
    // Certificates
    update_option('cip_cert_grading_mode', sanitize_text_field($_POST['cip_cert_grading_mode']));
    update_option('cip_cert_grade_esg3', intval($_POST['cip_cert_grade_esg3']));
    update_option('cip_cert_grade_esg2', intval($_POST['cip_cert_grade_esg2']));
    update_option('cip_cert_grade_esg1', intval($_POST['cip_cert_grade_esg1']));
    update_option('cip_cert_validity_years', intval($_POST['cip_cert_validity_years']));
    
    // Email
    update_option('cip_email_from_name', sanitize_text_field($_POST['cip_email_from_name']));
    update_option('cip_email_from_address', sanitize_email($_POST['cip_email_from_address']));
    update_option('cip_email_approval_subject', sanitize_text_field($_POST['cip_email_approval_subject']));
    update_option('cip_email_approval_body', wp_kses_post($_POST['cip_email_approval_body']));
    update_option('cip_email_rejection_subject', sanitize_text_field($_POST['cip_email_rejection_subject']));
    update_option('cip_email_rejection_body', wp_kses_post($_POST['cip_email_rejection_body']));
    
    // Pricing Plans
    if (isset($_POST['cip_pricing_plans']) && is_array($_POST['cip_pricing_plans'])) {
        $sanitized_plans = [];
        foreach ($_POST['cip_pricing_plans'] as $plan) {
            $sanitized_plans[] = [
                'name' => sanitize_text_field($plan['name']),
                'price' => sanitize_text_field($plan['price']),
                'currency' => sanitize_text_field($plan['currency']),
                'features' => sanitize_textarea_field($plan['features']),
                'popular' => !empty($plan['popular'])
            ];
        }
        update_option('cip_pricing_plans', $sanitized_plans);
        
        // Debug: Log what was saved
        error_log('CIP Settings: Saved pricing plans - ' . print_r($sanitized_plans, true));
    }
    
    // Redirect with success message
    wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
    exit;
}

// Get current settings with proper defaults
$options = [
    'company_name' => get_option('cip_company_name', 'CleanIndex'),
    'admin_email' => get_option('cip_admin_email', get_option('admin_email')),
    'enable_registration' => get_option('cip_enable_registration', '1'),
    'max_file_size' => get_option('cip_max_file_size', 10),
    'allowed_file_types' => get_option('cip_allowed_file_types', 'pdf,doc,docx'),
    'manager_access_code' => get_option('cip_manager_access_code', 'CLEANINDEX2025'),
    
    // Branding
    'brand_logo' => get_option('cip_brand_logo', ''),
    'brand_tagline' => get_option('cip_brand_tagline', 'ESG Certification Platform'),
    'brand_primary' => get_option('cip_brand_primary_color', '#4CAF50'),
    'brand_secondary' => get_option('cip_brand_secondary_color', '#EB5E28'),
    
    // GDPR
    'gdpr_enabled' => get_option('cip_gdpr_enabled', '0'),
    'gdpr_privacy_url' => get_option('cip_gdpr_privacy_url', ''),
    'gdpr_terms_url' => get_option('cip_gdpr_terms_url', ''),
    
    // Certificates
    'cert_grading_mode' => get_option('cip_cert_grading_mode', 'automatic'),
    'cert_grade_esg3' => get_option('cip_cert_grade_esg3', 95),
    'cert_grade_esg2' => get_option('cip_cert_grade_esg2', 85),
    'cert_grade_esg1' => get_option('cip_cert_grade_esg1', 75),
    'cert_validity_years' => get_option('cip_cert_validity_years', 1),
    
    // Email
    'email_from_name' => get_option('cip_email_from_name', 'CleanIndex'),
    'email_from_address' => get_option('cip_email_from_address', get_option('admin_email')),
    'email_approval_subject' => get_option('cip_email_approval_subject', 'Your Registration is Approved!'),
    'email_approval_body' => get_option('cip_email_approval_body', 'Congratulations! Your organization has been approved.'),
    'email_rejection_subject' => get_option('cip_email_rejection_subject', 'Additional Information Required'),
    'email_rejection_body' => get_option('cip_email_rejection_body', 'We need some additional information.'),
];

// Get pricing plans - DO NOT set defaults if empty, let admin configure
$pricing_plans = get_option('cip_pricing_plans', false);

// Only set default on first time installation (when option doesn't exist at all)
if ($pricing_plans === false) {
    $pricing_plans = [
        [
            'name' => 'Basic',
            'price' => '499',
            'currency' => 'INR',
            'features' => "ESG Assessment\nBasic Certificate\nEmail Support",
            'popular' => false
        ]
    ];
    // Save the default so it can be edited
    update_option('cip_pricing_plans', $pricing_plans);
}

// Ensure pricing_plans is always an array
if (!is_array($pricing_plans)) {
    $pricing_plans = [];
}

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✅ Settings saved successfully!</strong></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
        <div class="notice notice-info">
            <h3>🐛 Debug Information</h3>
            <p><strong>Current Pricing Plans in Database:</strong></p>
            <pre style="background: #f5f5f5; padding: 15px; overflow: auto;"><?php 
                print_r(get_option('cip_pricing_plans')); 
            ?></pre>
        </div>
    <?php endif; ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">⚙️ General</a>
        <a href="#pricing" class="nav-tab" onclick="switchTab(event, 'pricing')">💰 Pricing</a>
        <a href="#branding" class="nav-tab" onclick="switchTab(event, 'branding')">🎨 Branding</a>
        <a href="#certificates" class="nav-tab" onclick="switchTab(event, 'certificates')">🏆 Certificates</a>
        <a href="#email" class="nav-tab" onclick="switchTab(event, 'email')">📧 Email</a>
        <a href="#gdpr" class="nav-tab" onclick="switchTab(event, 'gdpr')">🔒 GDPR</a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('cip_settings_save', 'cip_settings_nonce'); ?>
        <input type="hidden" name="cip_save_settings" value="1">
        
        <!-- GENERAL TAB -->
        <div id="tab-general" class="tab-content" style="display: block; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>General Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="company_name">Company Name</label></th>
                    <td>
                        <input type="text" id="company_name" name="cip_company_name" class="regular-text" 
                               value="<?php echo esc_attr($options['company_name']); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="admin_email">Admin Email</label></th>
                    <td>
                        <input type="email" id="admin_email" name="cip_admin_email" class="regular-text" 
                               value="<?php echo esc_attr($options['admin_email']); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="enable_registration">Enable Registration</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="enable_registration" name="cip_enable_registration" 
                                   value="1" <?php checked($options['enable_registration'], '1'); ?>>
                            Allow new organizations to register
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="max_file_size">Max File Size (MB)</label></th>
                    <td>
                        <input type="number" id="max_file_size" name="cip_max_file_size" class="small-text" 
                               value="<?php echo esc_attr($options['max_file_size']); ?>" min="1" max="100">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="allowed_file_types">Allowed File Types</label></th>
                    <td>
                        <input type="text" id="allowed_file_types" name="cip_allowed_file_types" class="regular-text" 
                               value="<?php echo esc_attr($options['allowed_file_types']); ?>">
                        <p class="description">Comma-separated (e.g., pdf,doc,docx)</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="manager_access_code">Manager Access Code</label></th>
                    <td>
                        <input type="text" id="manager_access_code" name="cip_manager_access_code" class="regular-text" 
                               value="<?php echo esc_attr($options['manager_access_code']); ?>">
                        <p class="description">Required code for manager registration</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- PRICING TAB -->
        <div id="tab-pricing" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>Pricing Plans</h2>
            <p>Configure your subscription plans. Each plan can have its own currency.</p>
            
            <div id="pricing-plans-container">
                <?php foreach ($pricing_plans as $index => $plan): ?>
                    <div class="pricing-plan-item" style="background: #f9f9f9; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #4CAF50;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                            <h3>Plan <?php echo $index + 1; ?></h3>
                            <?php if ($index > 0): ?>
                                <button type="button" onclick="removePlan(this)" class="button" style="background: #f44336; color: white;">
                                    🗑️ Remove
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th style="width: 150px;"><label>Plan Name</label></th>
                                <td>
                                    <input type="text" name="cip_pricing_plans[<?php echo $index; ?>][name]" 
                                           class="regular-text" value="<?php echo esc_attr($plan['name']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Price</label></th>
                                <td>
                                    <input type="number" name="cip_pricing_plans[<?php echo $index; ?>][price]" 
                                           class="small-text" value="<?php echo esc_attr($plan['price']); ?>" 
                                           step="0.01" min="0" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Currency</label></th>
                                <td>
                                    <select name="cip_pricing_plans[<?php echo $index; ?>][currency]" style="width: 250px;">
                                        <option value="EUR" <?php selected($plan['currency'], 'EUR'); ?>>€ - Euro (EUR)</option>
                                        <option value="USD" <?php selected($plan['currency'], 'USD'); ?>>$ - US Dollar (USD)</option>
                                        <option value="GBP" <?php selected($plan['currency'], 'GBP'); ?>>£ - British Pound (GBP)</option>
                                        <option value="INR" <?php selected($plan['currency'], 'INR'); ?>>₹ - Indian Rupee (INR)</option>
                                        <option value="AUD" <?php selected($plan['currency'], 'AUD'); ?>>A$ - Australian Dollar (AUD)</option>
                                        <option value="CAD" <?php selected($plan['currency'], 'CAD'); ?>>C$ - Canadian Dollar (CAD)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Features</label></th>
                                <td>
                                    <textarea name="cip_pricing_plans[<?php echo $index; ?>][features]" 
                                              rows="5" class="large-text"><?php echo esc_textarea($plan['features']); ?></textarea>
                                    <p class="description">One feature per line</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Popular</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cip_pricing_plans[<?php echo $index; ?>][popular]" 
                                               value="1" <?php checked(!empty($plan['popular'])); ?>>
                                        Mark as "Most Popular"
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" onclick="addPlan()" class="button button-secondary">➕ Add Another Plan</button>
        </div>
        
        <!-- BRANDING TAB -->
        <div id="tab-branding" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>Branding Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="brand_logo">Logo URL</label></th>
                    <td>
                        <input type="url" id="brand_logo" name="cip_brand_logo" class="regular-text" 
                               value="<?php echo esc_attr($options['brand_logo']); ?>"
                               placeholder="https://yoursite.com/logo.png">
                        <p class="description">Enter the full URL of your logo image</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="brand_tagline">Tagline</label></th>
                    <td>
                        <input type="text" id="brand_tagline" name="cip_brand_tagline" class="regular-text" 
                               value="<?php echo esc_attr($options['brand_tagline']); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="brand_primary">Primary Color</label></th>
                    <td>
                        <input type="color" id="brand_primary" name="cip_brand_primary_color" 
                               value="<?php echo esc_attr($options['brand_primary']); ?>">
                        <span style="margin-left: 10px;"><?php echo esc_html($options['brand_primary']); ?></span>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="brand_secondary">Secondary Color</label></th>
                    <td>
                        <input type="color" id="brand_secondary" name="cip_brand_secondary_color" 
                               value="<?php echo esc_attr($options['brand_secondary']); ?>">
                        <span style="margin-left: 10px;"><?php echo esc_html($options['brand_secondary']); ?></span>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- CERTIFICATES TAB -->
        <div id="tab-certificates" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>Certificate Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="cert_grading_mode">Grading Mode</label></th>
                    <td>
                        <select id="cert_grading_mode" name="cip_cert_grading_mode">
                            <option value="automatic" <?php selected($options['cert_grading_mode'], 'automatic'); ?>>
                                Automatic (Based on Score)
                            </option>
                            <option value="manual" <?php selected($options['cert_grading_mode'], 'manual'); ?>>
                                Manual (Admin Selects Grade)
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="cert_grade_esg3">ESG+++ Threshold (%)</label></th>
                    <td>
                        <input type="number" id="cert_grade_esg3" name="cip_cert_grade_esg3" 
                               value="<?php echo esc_attr($options['cert_grade_esg3']); ?>" 
                               min="0" max="100" class="small-text">
                        <p class="description">Score required for ESG+++ grade</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="cert_grade_esg2">ESG++ Threshold (%)</label></th>
                    <td>
                        <input type="number" id="cert_grade_esg2" name="cip_cert_grade_esg2" 
                               value="<?php echo esc_attr($options['cert_grade_esg2']); ?>" 
                               min="0" max="100" class="small-text">
                        <p class="description">Score required for ESG++ grade</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="cert_grade_esg1">ESG+ Threshold (%)</label></th>
                    <td>
                        <input type="number" id="cert_grade_esg1" name="cip_cert_grade_esg1" 
                               value="<?php echo esc_attr($options['cert_grade_esg1']); ?>" 
                               min="0" max="100" class="small-text">
                        <p class="description">Score required for ESG+ grade</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="cert_validity_years">Certificate Validity (Years)</label></th>
                    <td>
                        <input type="number" id="cert_validity_years" name="cip_cert_validity_years" 
                               value="<?php echo esc_attr($options['cert_validity_years']); ?>" 
                               min="1" max="10" class="small-text">
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- EMAIL TAB -->
        <div id="tab-email" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>Email Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="email_from_name">From Name</label></th>
                    <td>
                        <input type="text" id="email_from_name" name="cip_email_from_name" class="regular-text" 
                               value="<?php echo esc_attr($options['email_from_name']); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="email_from_address">From Email</label></th>
                    <td>
                        <input type="email" id="email_from_address" name="cip_email_from_address" class="regular-text" 
                               value="<?php echo esc_attr($options['email_from_address']); ?>">
                    </td>
                </tr>
            </table>
            
            <hr style="margin: 30px 0;">
            
            <h3>Approval Email</h3>
            <table class="form-table">
                <tr>
                    <th><label for="email_approval_subject">Subject</label></th>
                    <td>
                        <input type="text" id="email_approval_subject" name="cip_email_approval_subject" 
                               class="large-text" value="<?php echo esc_attr($options['email_approval_subject']); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="email_approval_body">Body</label></th>
                    <td>
                        <textarea id="email_approval_body" name="cip_email_approval_body" 
                                  rows="5" class="large-text"><?php echo esc_textarea($options['email_approval_body']); ?></textarea>
                        <p class="description">Available variables: {{company_name}}, {{dashboard_link}}</p>
                    </td>
                </tr>
            </table>
            
            <hr style="margin: 30px 0;">
            
            <h3>Rejection Email</h3>
            <table class="form-table">
                <tr>
                    <th><label for="email_rejection_subject">Subject</label></th>
                    <td>
                        <input type="text" id="email_rejection_subject" name="cip_email_rejection_subject" 
                               class="large-text" value="<?php echo esc_attr($options['email_rejection_subject']); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="email_rejection_body">Body</label></th>
                    <td>
                        <textarea id="email_rejection_body" name="cip_email_rejection_body" 
                                  rows="5" class="large-text"><?php echo esc_textarea($options['email_rejection_body']); ?></textarea>
                        <p class="description">Available variables: {{company_name}}, {{reason}}</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- GDPR TAB -->
        <div id="tab-gdpr" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>GDPR & Privacy Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="gdpr_enabled">Enable GDPR Compliance</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="gdpr_enabled" name="cip_gdpr_enabled" 
                                   value="1" <?php checked($options['gdpr_enabled'], '1'); ?>>
                            Show privacy policy and terms acceptance on registration
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="gdpr_privacy_url">Privacy Policy URL</label></th>
                    <td>
                        <input type="url" id="gdpr_privacy_url" name="cip_gdpr_privacy_url" class="regular-text" 
                               value="<?php echo esc_attr($options['gdpr_privacy_url']); ?>"
                               placeholder="https://yoursite.com/privacy">
                    </td>
                </tr>
                
                <tr>
                    <th><label for="gdpr_terms_url">Terms of Service URL</label></th>
                    <td>
                        <input type="url" id="gdpr_terms_url" name="cip_gdpr_terms_url" class="regular-text" 
                               value="<?php echo esc_attr($options['gdpr_terms_url']); ?>"
                               placeholder="https://yoursite.com/terms">
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('💾 Save All Settings', 'primary', 'submit', true, ['style' => 'font-size: 16px; padding: 12px 24px;']); ?>
    </form>
</div>

<script>
function switchTab(e, tabName) {
    e.preventDefault();
    
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => tab.style.display = 'none');
    
    // Remove active class from all nav tabs
    document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('nav-tab-active'));
    
    // Show selected tab
    document.getElementById('tab-' + tabName).style.display = 'block';
    
    // Add active class to clicked nav tab
    e.target.classList.add('nav-tab-active');
}

let planIndex = <?php echo count($pricing_plans); ?>;

function addPlan() {
    const container = document.getElementById('pricing-plans-container');
    
    const newPlan = document.createElement('div');
    newPlan.className = 'pricing-plan-item';
    newPlan.style.cssText = 'background: #f9f9f9; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #03A9F4;';
    newPlan.innerHTML = `
        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
            <h3>Plan ${planIndex + 1}</h3>
            <button type="button" onclick="removePlan(this)" class="button" style="background: #f44336; color: white;">
                🗑️ Remove
            </button>
        </div>
        <table class="form-table">
            <tr>
                <th style="width: 150px;"><label>Plan Name</label></th>
                <td><input type="text" name="cip_pricing_plans[${planIndex}][name]" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label>Price</label></th>
                <td><input type="number" name="cip_pricing_plans[${planIndex}][price]" class="small-text" step="0.01" min="0" required></td>
            </tr>
            <tr>
                <th><label>Currency</label></th>
                <td>
                    <select name="cip_pricing_plans[${planIndex}][currency]" style="width: 250px;">
                        <option value="EUR">€ - Euro (EUR)</option>
                        <option value="USD">$ - US Dollar (USD)</option>
                        <option value="GBP">£ - British Pound (GBP)</option>
                        <option value="INR">₹ - Indian Rupee (INR)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Features</label></th>
                <td><textarea name="cip_pricing_plans[${planIndex}][features]" rows="5" class="large-text"></textarea></td>
            </tr>
            <tr>
                <th><label>Popular</label></th>
                <td><label><input type="checkbox" name="cip_pricing_plans[${planIndex}][popular]" value="1"> Mark as "Most Popular"</label></td>
            </tr>
        </table>
    `;
    container.appendChild(newPlan);
    planIndex++;
}

function removePlan(button) {
    if (confirm('Remove this plan?')) {
        button.closest('.pricing-plan-item').remove();
    }
}
</script>

<style>
.tab-content {
    display: none;
}
.nav-tab-wrapper {
    margin-bottom: 0;
}
</style>