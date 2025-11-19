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
        </div>
    <?php endif; ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">⚙️ General</a>
        <a href="#pricing" class="nav-tab" onclick="switchTab(event, 'pricing')">💰 Pricing Plans</a>
        <a href="#certificates" class="nav-tab" onclick="switchTab(event, 'certificates')">🏆 Certificates</a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('cip_settings_action', 'cip_settings_nonce'); ?>
        
        <!-- General Settings Tab -->
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
                        <td>
                            <input type="number" name="max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="100" class="small-text">
                            <p class="description">Maximum file size for uploads</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Allowed File Types</th>
                        <td>
                            <input type="text" name="allowed_file_types" value="<?php echo esc_attr($allowed_file_types); ?>" class="regular-text">
                            <p class="description">Comma-separated list (e.g., pdf,doc,docx)</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Pricing Plans Tab -->
        <div id="tab-pricing" class="tab-content" style="display: none;">
            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>💰 Pricing Plans Configuration</h2>
                <p>Configure your subscription pricing tiers. Plans will be created as WooCommerce products.</p>
                
                <div id="pricing-plans-container">
                    <?php
                    $pricing_plans = get_option('cip_pricing_plans', []);
                    if (empty($pricing_plans)) {
                        $pricing_plans = [
                            [
                                'name' => 'Basic', 
                                'price' => '499', 
                                'currency' => 'EUR', 
                                'features' => "ESG Assessment\nBasic Certificate\nEmail Support\n1 Year Validity", 
                                'popular' => false
                            ],
                            [
                                'name' => 'Professional', 
                                'price' => '999', 
                                'currency' => 'EUR', 
                                'features' => "ESG Assessment\nPremium Certificate\nPriority Support\n2 Years Validity\nBenchmarking Report\nDirectory Listing", 
                                'popular' => true
                            ],
                            [
                                'name' => 'Enterprise', 
                                'price' => '1999', 
                                'currency' => 'EUR', 
                                'features' => "ESG Assessment\nPremium Certificate\nDedicated Support\n3 Years Validity\nDetailed Analytics\nFeatured Directory Listing\nCustom Reporting\nAPI Access", 
                                'popular' => false
                            ]
                        ];
                    }
                    
                    foreach ($pricing_plans as $index => $plan):
                    ?>
                        <div class="pricing-plan-item" style="background: #f9f9f9; padding: 20px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid #4CAF50;">
                            <h3>Plan <?php echo $index + 1; ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th style="width: 200px;">Plan Name</th>
                                    <td><input type="text" name="pricing_plans[<?php echo $index; ?>][name]" value="<?php echo esc_attr($plan['name']); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>Price</th>
                                    <td>
                                        <input type="number" name="pricing_plans[<?php echo $index; ?>][price]" value="<?php echo esc_attr($plan['price']); ?>" class="small-text">
                                        <select name="pricing_plans[<?php echo $index; ?>][currency]">
                                            <option value="EUR" <?php selected($plan['currency'], 'EUR'); ?>>EUR (€)</option>
                                            <option value="USD" <?php selected($plan['currency'], 'USD'); ?>>USD ($)</option>
                                            <option value="GBP" <?php selected($plan['currency'], 'GBP'); ?>>GBP (£)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Features</th>
                                    <td>
                                        <textarea name="pricing_plans[<?php echo $index; ?>][features]" rows="6" class="large-text" placeholder="One feature per line"><?php echo esc_textarea($plan['features']); ?></textarea>
                                        <p class="description">Enter one feature per line</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Mark as Popular</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="pricing_plans[<?php echo $index; ?>][popular]" value="1" <?php checked(!empty($plan['popular'])); ?>>
                                            Highlight this plan as most popular
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" onclick="addPricingPlan()" class="button button-secondary">➕ Add Another Plan</button>
                
                <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                    <strong>ℹ️ Note:</strong> After saving, WooCommerce products will be automatically created/updated for each plan.
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
                                <strong>Manual</strong> - Admin selects grade for each certificate
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Grade Thresholds (Automatic Mode)</th>
                        <td>
                            <table style="max-width: 400px;">
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
                                <tr>
                                    <td><strong>ESG</strong></td>
                                    <td>< 75%</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <th>Certificate Validity</th>
                        <td>
                            <input type="number" name="cert_validity_years" value="<?php echo esc_attr(get_option('cip_cert_validity_years', '1')); ?>" class="small-text" min="1" max="5">
                            years
                        </td>
                    </tr>
                </table>
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
    
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Remove active class from all nav tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('nav-tab-active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).style.display = 'block';
    e.target.classList.add('nav-tab-active');
}

let planCounter = <?php echo count($pricing_plans); ?>;

function addPricingPlan() {
    const container = document.getElementById('pricing-plans-container');
    const newPlan = document.createElement('div');
    newPlan.className = 'pricing-plan-item';
    newPlan.style.cssText = 'background: #f9f9f9; padding: 20px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid #4CAF50;';
    newPlan.innerHTML = `
        <h3>Plan ${planCounter + 1}</h3>
        <table class="form-table">
            <tr>
                <th style="width: 200px;">Plan Name</th>
                <td><input type="text" name="pricing_plans[${planCounter}][name]" value="" class="regular-text"></td>
            </tr>
            <tr>
                <th>Price</th>
                <td>
                    <input type="number" name="pricing_plans[${planCounter}][price]" value="" class="small-text">
                    <select name="pricing_plans[${planCounter}][currency]">
                        <option value="EUR">EUR (€)</option>
                        <option value="USD">USD ($)</option>
                        <option value="GBP">GBP (£)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Features</th>
                <td>
                    <textarea name="pricing_plans[${planCounter}][features]" rows="6" class="large-text" placeholder="One feature per line"></textarea>
                    <p class="description">Enter one feature per line</p>
                </td>
            </tr>
            <tr>
                <th>Mark as Popular</th>
                <td>
                    <label>
                        <input type="checkbox" name="pricing_plans[${planCounter}][popular]" value="1">
                        Highlight this plan as most popular
                    </label>
                </td>
            </tr>
        </table>
    `;
    container.appendChild(newPlan);
    planCounter++;
}
</script>