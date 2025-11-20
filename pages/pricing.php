<?php
/**
 * Updated Pricing Page - Direct Subscription (No WooCommerce)
 * REPLACE: pages/pricing.php with this version
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

// Check if assessment is complete
global $wpdb;
$table_registrations = $wpdb->prefix . 'company_registrations';
$table_assessments = $wpdb->prefix . 'company_assessments';

$registration = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_registrations WHERE email = %s",
    wp_get_current_user()->user_email
), ARRAY_A);

if (!$registration) {
    wp_redirect(home_url('/cleanindex/dashboard'));
    exit;
}

$assessment = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_assessments WHERE user_id = %d AND progress >= 5",
    $registration['id']
), ARRAY_A);

if (!$assessment) {
    wp_redirect(home_url('/cleanindex/assessment'));
    exit;
}

// Check existing subscription
$existing_subscription = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}cip_subscriptions WHERE user_id = %d AND status = 'active'",
    wp_get_current_user()->ID
), ARRAY_A);

// Get pricing plans
$pricing_plans = get_option('cip_pricing_plans', []);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe Now - CleanIndex Portal</title>
    <?php wp_head(); ?>
    <style>
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
            margin: 40px 0;
        }
        
        .pricing-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: #4CAF50;
        }
        
        .pricing-card.popular {
            border-color: #4CAF50;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.2);
        }
        
        .popular-badge {
            position: absolute;
            top: -16px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .price {
            font-size: 48px;
            font-weight: 700;
            color: #4CAF50;
            line-height: 1;
            margin: 24px 0;
        }
        
        .price-currency {
            font-size: 24px;
            vertical-align: super;
        }
        
        .price-period {
            font-size: 14px;
            color: #6b7280;
            font-weight: 400;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 24px 0;
        }
        
        .feature-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #374151;
        }
        
        .feature-list li::before {
            content: '✓';
            color: #4CAF50;
            font-weight: bold;
            font-size: 18px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">🌱 CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/dashboard'); ?>">📊 Dashboard</a></li>
                    <li><a href="<?php echo home_url('/cleanindex/assessment'); ?>">📝 Assessment</a></li>
                    <li><a href="<?php echo home_url('/cleanindex/pricing'); ?>" class="active">💳 Subscribe</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url('/cleanindex/login')); ?>">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <div style="text-align: center; margin-bottom: 48px;">
                <h1 style="font-size: 36px; margin-bottom: 12px;">Choose Your Subscription</h1>
                <p style="font-size: 16px; color: #6b7280;">
                    Select a plan to get your ESG certificate and unlock premium features
                </p>
            </div>
            
            <?php if ($existing_subscription): ?>
                <div class="alert alert-success" style="margin-bottom: 32px;">
                    ✅ <strong>Active Subscription:</strong> You already have an active 
                    <strong><?php echo esc_html($existing_subscription['plan_name']); ?></strong> 
                    subscription until <?php echo date('F j, Y', strtotime($existing_subscription['end_date'])); ?>.
                    <a href="<?php echo home_url('/cleanindex/dashboard'); ?>" style="margin-left: 1rem;">
                        Back to Dashboard →
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="pricing-grid">
                <?php foreach ($pricing_plans as $index => $plan): ?>
                    <div class="pricing-card <?php echo $plan['popular'] ? 'popular' : ''; ?>">
                        <?php if ($plan['popular']): ?>
                            <div class="popular-badge">⭐ Most Popular</div>
                        <?php endif; ?>
                        
                        <h3 style="font-size: 22px; margin-bottom: 8px; margin-top: 0; color: #111827;">
                            <?php echo esc_html($plan['name']); ?>
                        </h3>
                        
                        <div class="price">
                            <span class="price-currency">€</span><?php echo esc_html($plan['price']); ?>
                            <span class="price-period">/year</span>
                        </div>
                        
                        <ul class="feature-list">
                            <?php 
                            $features = is_array($plan['features']) ? $plan['features'] : 
                                       array_filter(array_map('trim', explode("\n", $plan['features'])));
                            foreach ($features as $feature): 
                                if (!empty($feature)): 
                            ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php 
                                endif; 
                            endforeach; 
                            ?>
                        </ul>
                        
                        <button 
                            onclick="subscribePlan(<?php echo $index; ?>, '<?php echo esc_js($plan['name']); ?>')"
                            class="btn <?php echo $plan['popular'] ? 'btn-primary' : 'btn-outline'; ?>"
                            style="width: 100%; padding: 14px; font-size: 15px; font-weight: 600;">
                            <?php echo $plan['popular'] ? '🚀 Subscribe Now' : 'Select Plan'; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- FAQ Section -->
            <div class="glass-card" style="margin-top: 48px;">
                <h2 style="text-align: center; margin-bottom: 32px;">❓ Frequently Asked Questions</h2>
                
                <div style="display: grid; gap: 24px;">
                    <div>
                        <h4 style="margin-bottom: 8px; color: #4CAF50;">💳 What payment methods do you accept?</h4>
                        <p style="color: #6b7280; margin: 0;">
                            We accept all major credit/debit cards and bank transfers. 
                            Invoices will be sent to your registered email.
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="margin-bottom: 8px; color: #4CAF50;">🔄 Can I change my plan later?</h4>
                        <p style="color: #6b7280; margin: 0;">
                            Yes, you can upgrade or downgrade your plan at any time. 
                            Changes take effect on your next billing date.
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="margin-bottom: 8px; color: #4CAF50;">📜 What's included in the certificate?</h4>
                        <p style="color: #6b7280; margin: 0;">
                            Your certificate includes your ESG grade, verification number, and validity period. 
                            You can share it publicly or use it internally.
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="margin-bottom: 8px; color: #4CAF50;">❌ Can I cancel anytime?</h4>
                        <p style="color: #6b7280; margin: 0;">
                            Yes, you can cancel your subscription anytime. Your certificate remains valid 
                            until the end of your subscription period.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="glass-card" style="margin-top: 32px; text-align: center;">
                <h3 style="margin-bottom: 16px;">💬 Need Help?</h3>
                <p style="color: #6b7280; margin-bottom: 16px;">
                    Our team is here to help you choose the right plan for your organization.
                </p>
                <a href="mailto:support@cleanindex.com" class="btn btn-accent">
                    Contact Support Team
                </a>
            </div>
        </main>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 48px 32px; border-radius: 12px; text-align: center;">
            <div class="spinner"></div>
            <p style="margin-top: 16px; color: #374151; font-weight: 500;">
                Processing your subscription...
            </p>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal-overlay" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Confirm Subscription</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div id="modalBody">
                <!-- Content will be inserted here -->
            </div>
        </div>
    </div>
    
    <script>
    function subscribePlan(planIndex, planName) {
        const plans = <?php echo json_encode($pricing_plans); ?>;
        const plan = plans[planIndex];
        
        if (!plan) {
            alert('Invalid plan selected');
            return;
        }
        
        // Show confirmation modal
        const modalBody = document.getElementById('modalBody');
        modalBody.innerHTML = `
            <div style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 16px;">💳</div>
                <h3 style="margin: 0 0 16px 0;">${plan.name} Plan</h3>
                <div style="font-size: 36px; font-weight: 700; color: #4CAF50; margin-bottom: 8px;">
                    €${plan.price}<span style="font-size: 16px; color: #6b7280;">/year</span>
                </div>
                <p style="color: #6b7280; margin-bottom: 24px;">
                    Your subscription will be active for 1 year. You'll receive a confirmation email with your certificate.
                </p>
                
                <div style="background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 24px; text-align: left;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Plan:</span>
                        <strong>${plan.name}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Price:</span>
                        <strong>€${plan.price}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 8px;">
                        <span style="font-weight: 600;">Total:</span>
                        <strong style="font-size: 18px;">€${plan.price}</strong>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button onclick="processSubscription(${planIndex})" class="btn btn-primary" style="flex: 1; padding: 12px;">
                        ✓ Confirm & Subscribe
                    </button>
                    <button onclick="closeModal()" class="btn btn-outline" style="flex: 1; padding: 12px;">
                        Cancel
                    </button>
                </div>
            </div>
        `;
        
        document.getElementById('confirmationModal').style.display = 'flex';
    }
    
    function processSubscription(planIndex) {
        closeModal();
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'cip_subscribe_plan',
                nonce: '<?php echo wp_create_nonce('cip_subscribe'); ?>',
                plan_index: planIndex
            })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingOverlay').style.display = 'none';
            
            if (data.success) {
                // Show success message
                alert('🎉 Subscription activated successfully!\n\nYour ESG certificate is being generated.');
                window.location.href = data.data.redirect;
            } else {
                alert('Error: ' + (data.data.message || 'Failed to process subscription'));
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').style.display = 'none';
            alert('Error: ' + error.message);
        });
    }
    
    function closeModal() {
        document.getElementById('confirmationModal').style.display = 'none';
    }
    
    // Close modal on outside click
    document.getElementById('confirmationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>