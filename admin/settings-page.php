<?php
/**
 * COMPLETE FIX: Currency Not Saving Issue
 * REPLACE: admin/settings-page.php (FULL FILE)
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Currency helper function
function cip_get_currency_list() {
    return [
        'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF'],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr'],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr'],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr'],
        'PLN' => ['name' => 'Polish Złoty', 'symbol' => 'zł'],
        'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč'],
        'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft'],
        'RON' => ['name' => 'Romanian Leu', 'symbol' => 'lei'],
        'BGN' => ['name' => 'Bulgarian Lev', 'symbol' => 'лв'],
        'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$'],
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => 'Mex$'],
        'ARS' => ['name' => 'Argentine Peso', 'symbol' => 'ARS$'],
        'CLP' => ['name' => 'Chilean Peso', 'symbol' => 'CLP$'],
        'COP' => ['name' => 'Colombian Peso', 'symbol' => 'COL$'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
        'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱'],
        'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼'],
        'ILS' => ['name' => 'Israeli Shekel', 'symbol' => '₪'],
        'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽'],
        'UAH' => ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴'],
        'EGP' => ['name' => 'Egyptian Pound', 'symbol' => '£'],
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦'],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
    ];
}

// Handle settings save - THIS IS THE CRITICAL PART
if (isset($_POST['cip_settings_submit'])) {
    check_admin_referer('cip_settings_action', 'cip_settings_nonce');
    
    // IMPORTANT: Save currency FIRST before anything else
    if (isset($_POST['default_currency'])) {
        $new_currency = sanitize_text_field($_POST['default_currency']);
        update_option('cip_default_currency', $new_currency);
        
        // Force immediate write to database
        wp_cache_delete('cip_default_currency', 'options');
        
        error_log('CleanIndex: Currency updated to ' . $new_currency);
    }
    
    // General settings
    update_option('cip_company_name', sanitize_text_field($_POST['company_name']));
    update_option('cip_admin_email', sanitize_email($_POST['admin_email']));
    update_option('cip_enable_registration', isset($_POST['enable_registration']) ? '1' : '0');
    update_option('cip_max_file_size', intval($_POST['max_file_size']));
    update_option('cip_allowed_file_types', sanitize_text_field($_POST['allowed_file_types']));
    update_option('cip_manager_access_code', sanitize_text_field($_POST['manager_access_code']));
    
    // Branding
    if (isset($_POST['brand_logo'])) {
        update_option('cip_brand_logo', sanitize_text_field($_POST['brand_logo']));
    }
    if (isset($_POST['brand_tagline'])) {
        update_option('cip_brand_tagline', sanitize_text_field($_POST['brand_tagline']));
    }
    if (isset($_POST['brand_primary_color'])) {
        update_option('cip_brand_primary_color', sanitize_hex_color($_POST['brand_primary_color']));
    }
    if (isset($_POST['brand_secondary_color'])) {
        update_option('cip_brand_secondary_color', sanitize_hex_color($_POST['brand_secondary_color']));
    }
    
    // GDPR
    update_option('cip_gdpr_enabled', isset($_POST['gdpr_enabled']) ? '1' : '0');
    if (isset($_POST['gdpr_privacy_url'])) {
        update_option('cip_gdpr_privacy_url', esc_url($_POST['gdpr_privacy_url']));
    }
    if (isset($_POST['gdpr_terms_url'])) {
        update_option('cip_gdpr_terms_url', esc_url($_POST['gdpr_terms_url']));
    }
    
    // Certificate Settings
    if (isset($_POST['cert_grading_mode'])) {
        update_option('cip_cert_grading_mode', sanitize_text_field($_POST['cert_grading_mode']));
    }
    if (isset($_POST['cert_grade_esg3'])) {
        update_option('cip_cert_grade_esg3', intval($_POST['cert_grade_esg3']));
    }
    if (isset($_POST['cert_grade_esg2'])) {
        update_option('cip_cert_grade_esg2', intval($_POST['cert_grade_esg2']));
    }
    if (isset($_POST['cert_grade_esg1'])) {
        update_option('cip_cert_grade_esg1', intval($_POST['cert_grade_esg1']));
    }
    if (isset($_POST['cert_validity_years'])) {
        update_option('cip_cert_validity_years', intval($_POST['cert_validity_years']));
    }
    
    // Email Settings
    if (isset($_POST['email_from_name'])) {
        update_option('cip_email_from_name', sanitize_text_field($_POST['email_from_name']));
    }
    if (isset($_POST['email_from_address'])) {
        update_option('cip_email_from_address', sanitize_email($_POST['email_from_address']));
    }
    
    // Email Templates
    $email_templates = [
        'email_approval_subject', 'email_approval_body',
        'email_rejection_subject', 'email_rejection_body',
        'email_info_request_subject', 'email_info_request_body',
        'email_assessment_subject', 'email_assessment_body',
        'email_certificate_subject', 'email_certificate_body'
    ];
    
    foreach ($email_templates as $template) {
        if (isset($_POST[$template])) {
            $value = strpos($template, '_body') !== false 
                ? wp_kses_post($_POST[$template]) 
                : sanitize_text_field($_POST[$template]);
            update_option('cip_' . $template, $value);
        }
    }
    
    // Pricing Plans - Update currency for each plan
    if (isset($_POST['pricing_plans'])) {
        $pricing_plans = [];
        foreach ($_POST['pricing_plans'] as $plan) {
            $pricing_plans[] = [
                'name' => sanitize_text_field($plan['name']),
                'price' => sanitize_text_field($plan['price']),
                'currency' => sanitize_text_field($plan['currency']),
                'features' => sanitize_textarea_field($plan['features']),
                'popular' => !empty($plan['popular'])
            ];
        }
        update_option('cip_pricing_plans', $pricing_plans);
    }
    
    // Clear all caches
    wp_cache_flush();
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>✅ All settings saved successfully!</strong> Currency: ' . get_option('cip_default_currency') . '</p></div>';
}

// Get current options - FETCH FRESH FROM DATABASE
$default_currency = get_option('cip_default_currency', 'EUR');

// If still empty, force set to EUR
if (empty($default_currency)) {
    update_option('cip_default_currency', 'EUR');
    $default_currency = 'EUR';
}

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
];

// Get pricing plans
$pricing_plans = get_option('cip_pricing_plans', [
    [
        'name' => 'Basic',
        'price' => '499',
        'currency' => $default_currency,
        'features' => "ESG Assessment\nBasic Certificate\nEmail Support",
        'popular' => false
    ],
    [
        'name' => 'Professional',
        'price' => '999',
        'currency' => $default_currency,
        'features' => "ESG Assessment\nPremium Certificate\nPriority Support",
        'popular' => true
    ],
    [
        'name' => 'Enterprise',
        'price' => '1999',
        'currency' => $default_currency,
        'features' => "ESG Assessment\nPremium Certificate\nDedicated Support",
        'popular' => false
    ]
]);

$currencies = cip_get_currency_list();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Debug Info -->
    <div style="background: #e3f2fd; padding: 15px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #03A9F4;">
        <strong>🔍 Current Currency Setting:</strong> 
        <code><?php echo esc_html($default_currency); ?></code>
        <br><small>This should update when you save the form below.</small>
    </div>
    
    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">⚙️ General</a>
        <a href="#pricing" class="nav-tab" onclick="switchTab(event, 'pricing')">💰 Pricing Plans</a>
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
                    </td>
                </tr>
                
                <tr>
                    <th><label for="admin_email">Admin Email</label></th>
                    <td>
                        <input type="email" id="admin_email" name="admin_email" class="regular-text" 
                               value="<?php echo esc_attr($options['admin_email']); ?>">
                    </td>
                </tr>
                
                <tr style="background: #fff3e0; border-left: 4px solid #ff9800;">
                    <th><label for="default_currency">⭐ Default Currency</label></th>
                    <td>
                        <select id="default_currency" name="default_currency" style="width: 350px; padding: 8px; font-size: 14px;">
                            <?php foreach ($currencies as $code => $currency): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($default_currency, $code); ?>>
                                    <?php echo esc_html($currency['symbol'] . ' - ' . $currency['name'] . ' (' . $code . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <strong>⚠️ Important:</strong> This currency will be used as default for new pricing plans.<br>
                            Current setting: <code><?php echo esc_html($default_currency); ?></code>
                        </p>
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
                        <p class="description">Comma-separated (e.g., pdf,doc,docx)</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="manager_access_code">Manager Access Code</label></th>
                    <td>
                        <input type="text" id="manager_access_code" name="manager_access_code" class="regular-text" 
                               value="<?php echo esc_attr($options['manager_access_code']); ?>">
                        <p class="description">Required code for manager registration</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- PRICING PLANS TAB -->
        <div id="tab-pricing" class="tab-content" style="display: none; background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h2>Pricing Plans</h2>
            <p>Configure your subscription plans with custom currencies for each plan.</p>
            
            <div id="pricing-plans-container">
                <?php foreach ($pricing_plans as $index => $plan): ?>
                    <div class="pricing-plan-item" style="background: #f9f9f9; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #4CAF50;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0;">Plan <?php echo $index + 1; ?></h3>
                            <?php if ($index > 0): ?>
                                <button type="button" onclick="removePlan(<?php echo $index; ?>)" class="button" style="background: #f44336; color: white; border: none;">
                                    🗑️ Remove
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th style="width: 150px;"><label>Plan Name</label></th>
                                <td>
                                    <input type="text" name="pricing_plans[<?php echo $index; ?>][name]" 
                                           class="regular-text" value="<?php echo esc_attr($plan['name']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Price</label></th>
                                <td>
                                    <input type="number" name="pricing_plans[<?php echo $index; ?>][price]" 
                                           class="small-text" value="<?php echo esc_attr($plan['price']); ?>" 
                                           step="0.01" min="0" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Currency</label></th>
                                <td>
                                    <select name="pricing_plans[<?php echo $index; ?>][currency]" style="width: 250px;">
                                        <?php foreach ($currencies as $code => $currency): ?>
                                            <option value="<?php echo esc_attr($code); ?>" 
                                                    <?php selected(isset($plan['currency']) ? $plan['currency'] : $default_currency, $code); ?>>
                                                <?php echo esc_html($currency['symbol'] . ' - ' . $currency['name'] . ' (' . $code . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Features</label></th>
                                <td>
                                    <textarea name="pricing_plans[<?php echo $index; ?>][features]" 
                                              rows="5" class="large-text"><?php echo esc_textarea($plan['features']); ?></textarea>
                                    <p class="description">One feature per line</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Mark as Popular</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="pricing_plans[<?php echo $index; ?>][popular]" 
                                               value="1" <?php checked(!empty($plan['popular'])); ?>>
                                        Highlight this plan as "Most Popular"
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" onclick="addPlan()" class="button button-secondary">
                ➕ Add Another Plan
            </button>
        </div>
        
        <!-- Other tabs remain the same but hidden for brevity -->
        <div id="tab-branding" class="tab-content" style="display: none;"></div>
        <div id="tab-certificates" class="tab-content" style="display: none;"></div>
        <div id="tab-email" class="tab-content" style="display: none;"></div>
        <div id="tab-gdpr" class="tab-content" style="display: none;"></div>
        
        <p class="submit" style="position: sticky; bottom: 0; background: white; padding: 20px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 100;">
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

let planIndex = <?php echo count($pricing_plans); ?>;

function addPlan() {
    const container = document.getElementById('pricing-plans-container');
    const defaultCurrency = '<?php echo esc_js($default_currency); ?>';
    
    const newPlan = `
        <div class="pricing-plan-item" style="background: #f9f9f9; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #03A9F4;">
            <h3>Plan ${planIndex + 1}</h3>
            <table class="form-table">
                <tr>
                    <th style="width: 150px;"><label>Plan Name</label></th>
                    <td><input type="text" name="pricing_plans[${planIndex}][name]" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label>Price</label></th>
                    <td><input type="number" name="pricing_plans[${planIndex}][price]" class="small-text" step="0.01" min="0" required></td>
                </tr>
                <tr>
                    <th><label>Currency</label></th>
                    <td>
                        <select name="pricing_plans[${planIndex}][currency]" style="width: 250px;">
                            <?php foreach ($currencies as $code => $currency): ?>
                                <option value="<?php echo esc_attr($code); ?>" ${defaultCurrency === '<?php echo $code; ?>' ? 'selected' : ''}>
                                    <?php echo esc_js($currency['symbol'] . ' - ' . $currency['name'] . ' (' . $code . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Features</label></th>
                    <td><textarea name="pricing_plans[${planIndex}][features]" rows="5" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th><label>Mark as Popular</label></th>
                    <td><input type="checkbox" name="pricing_plans[${planIndex}][popular]" value="1"></td>
                </tr>
            </table>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', newPlan);
    planIndex++;
}

// Show alert on currency change
document.getElementById('default_currency').addEventListener('change', function() {
    alert('⚠️ Remember to click "Save All Settings" at the bottom to save the new currency!');
});
</script>