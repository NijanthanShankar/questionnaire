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
/**
 * ============================================
 * FILE 4: settings-page.php (ENHANCED WITH PRICING)
 * ============================================
 */

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
        <a href="#payment" class="nav-tab" onclick="switchTab(event, 'payment')">💳 Payment Gateway</a>
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
                </table>
            </div>
        </div>
        
        <!-- Pricing Plans Tab -->
        <div id="tab-pricing" class="tab-content" style="display: none;">
            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>💰 Pricing Plans Configuration</h2>
                <p>Configure your subscription pricing tiers. Plans will appear on the pricing page.</p>
                
                <div id="pricing-plans-container">
                    <?php
                    $pricing_plans = get_option('cip_pricing_plans', []);
                    if (empty($pricing_plans)) {
                        $pricing_plans = [
                            ['name' => 'Basic', 'price' => '499', 'currency' => 'EUR', 'features' => 'ESG Assessment
Basic Certificate
Email Support
1 Year Validity', 'popular' => false],
                            ['name' => 'Professional', 'price' => '999', 'currency' => 'EUR', 'features' => 'ESG Assessment
Premium Certificate
Priority Support
2 Years Validity
Benchmarking Report
Directory Listing', 'popular' => true],
                            ['name' => 'Enterprise', 'price' => '1999', 'currency' => 'EUR', 'features' => 'ESG Assessment
Premium Certificate
Dedicated Support
3 Years Validity
Detailed Analytics
Featured Directory Listing
Custom Reporting
API Access', 'popular' => false]
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
        
        <!-- Payment Gateway Tab -->
        <div id="tab-payment" class="tab-content" style="display: none;">
            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>💳 Payment Gateway Configuration</h2>
                
                <table class="form-table">
                    <tr>
                        <th>Payment Gateway</th>
                        <td>
                            <select name="payment_gateway" id="payment_gateway" onchange="toggleGatewaySettings()">
                                <option value="stripe" <?php selected(get_option('cip_payment_gateway', 'stripe'), 'stripe'); ?>>Stripe</option>
                                <option value="paypal" <?php selected(get_option('cip_payment_gateway'), 'paypal'); ?>>PayPal</option>
                                <option value="mollie" <?php selected(get_option('cip_payment_gateway'), 'mollie'); ?>>Mollie</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="stripe-settings" style="<?php echo get_option('cip_payment_gateway', 'stripe') !== 'stripe' ? 'display:none;' : ''; ?>">
                        <th>Stripe Configuration</th>
                        <td>
                            <p><strong>Publishable Key</strong></p>
                            <input type="text" name="stripe_publishable_key" value="<?php echo esc_attr(get_option('cip_stripe_publishable_key')); ?>" class="regular-text" placeholder="pk_live_...">
                            
                            <p style="margin-top: 15px;"><strong>Secret Key</strong></p>
                            <input type="password" name="stripe_secret_key" value="<?php echo esc_attr(get_option('cip_stripe_secret_key')); ?>" class="regular-text" placeholder="sk_live_...">
                            
                            <p class="description">Get your keys from <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a></p>
                        </td>
                    </tr>
                    
                    <tr id="paypal-settings" style="<?php echo get_option('cip_payment_gateway') !== 'paypal' ? 'display:none;' : ''; ?>">
                        <th>PayPal Configuration</th>
                        <td>
                            <p><strong>Client ID</strong></p>
                            <input type="text" name="paypal_client_id" value="<?php echo esc_attr(get_option('cip_paypal_client_id')); ?>" class="regular-text">
                            
                            <p style="margin-top: 15px;"><strong>Secret</strong></p>
                            <input type="password" name="paypal_secret" value="<?php echo esc_attr(get_option('cip_paypal_secret')); ?>" class="regular-text">
                            
                            <p style="margin-top: 15px;">
                                <label>
                                    <input type="checkbox" name="paypal_sandbox" value="1" <?php checked(get_option('cip_paypal_sandbox'), '1'); ?>>
                                    Use Sandbox Mode (for testing)
                                </label>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th>Currency</th>
                        <td>
                            <select name="payment_currency">
                                <option value="EUR" <?php selected(get_option('cip_payment_currency', 'EUR'), 'EUR'); ?>>EUR (€)</option>
                                <option value="USD" <?php selected(get_option('cip_payment_currency'), 'USD'); ?>>USD ($)</option>
                                <option value="GBP" <?php selected(get_option('cip_payment_currency'), 'GBP'); ?>>GBP (£)</option>
                            </select>
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

function toggleGatewaySettings() {
    const gateway = document.getElementById('payment_gateway').value;
    document.getElementById('stripe-settings').style.display = gateway === 'stripe' ? 'table-row' : 'none';
    document.getElementById('paypal-settings').style.display = gateway === 'paypal' ? 'table-row' : 'none';
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
