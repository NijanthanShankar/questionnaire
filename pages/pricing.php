<?php
/**
 * Pricing Page with Direct WooCommerce Payment
 * REPLACE: pages/pricing.php
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

// Get pricing plans
$pricing_plans = get_option('cip_pricing_plans', []);

// Check if WooCommerce is active
$woo_active = class_exists('WooCommerce');

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
        }
        
        .payment-method {
            padding: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .payment-method:hover {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.05);
        }
        
        .payment-method.selected {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
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
                    Select a plan and complete payment to get your ESG certificate
                </p>
            </div>
            
            <?php if (!$woo_active): ?>
                <div class="alert alert-error">
                    <strong>⚠️ WooCommerce Required</strong><br>
                    Please install and activate WooCommerce to enable payments.
                </div>
            <?php endif; ?>
            
            <div class="pricing-grid">
                <?php foreach ($pricing_plans as $index => $plan): ?>
                    <div class="pricing-card <?php echo $plan['popular'] ? 'popular' : ''; ?>">
                        <?php if ($plan['popular']): ?>
                            <div class="popular-badge">⭐ Most Popular</div>
                        <?php endif; ?>
                        
                        <h3 style="font-size: 22px; margin-bottom: 8px; margin-top: 0;">
                            <?php echo esc_html($plan['name']); ?>
                        </h3>
                        
                        <div style="font-size: 48px; font-weight: 700; color: #4CAF50; margin: 24px 0;">
                            <span style="font-size: 24px; vertical-align: super;">€</span>
                            <?php echo esc_html($plan['price']); ?>
                            <span style="font-size: 14px; color: #6b7280; font-weight: 400;">/year</span>
                        </div>
                        
                        <ul style="list-style: none; padding: 0; margin: 24px 0;">
                            <?php 
                            $features = is_array($plan['features']) ? $plan['features'] : 
                                       array_filter(array_map('trim', explode("\n", $plan['features'])));
                            foreach ($features as $feature): 
                                if (!empty($feature)): 
                            ?>
                                <li style="padding: 12px 0; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 10px;">
                                    <span style="color: #4CAF50; font-weight: bold;">✓</span>
                                    <?php echo esc_html($feature); ?>
                                </li>
                            <?php 
                                endif; 
                            endforeach; 
                            ?>
                        </ul>
                        
                        <button 
                            onclick="selectPlan(<?php echo $index; ?>, '<?php echo esc_js($plan['name']); ?>', <?php echo esc_js($plan['price']); ?>)"
                            class="btn <?php echo $plan['popular'] ? 'btn-primary' : 'btn-outline'; ?>"
                            style="width: 100%; padding: 14px; font-weight: 600;"
                            <?php echo !$woo_active ? 'disabled' : ''; ?>>
                            <?php echo $plan['popular'] ? '🚀 Select Plan' : 'Select Plan'; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="modal-overlay" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Complete Payment</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div id="modalBody">
                <!-- Content will be inserted here -->
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 48px; border-radius: 12px; text-align: center;">
            <div class="spinner"></div>
            <p style="margin-top: 16px; font-weight: 500;">Processing payment...</p>
        </div>
    </div>
    
    <script>
    let selectedPlanIndex = null;
    let selectedPlanName = '';
    let selectedPlanPrice = 0;
    
    function selectPlan(planIndex, planName, planPrice) {
        selectedPlanIndex = planIndex;
        selectedPlanName = planName;
        selectedPlanPrice = planPrice;
        
        // Load payment methods
        loadPaymentMethods();
    }
    
    function loadPaymentMethods() {
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'cip_get_payment_methods',
                nonce: '<?php echo wp_create_nonce('cip_payment_direct'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingOverlay').style.display = 'none';
            
            if (data.success) {
                showPaymentModal(data.data.methods);
            } else {
                alert('Error: ' + (data.data.message || 'Failed to load payment methods'));
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').style.display = 'none';
            alert('Error: ' + error.message);
        });
    }
    
    function showPaymentModal(methods) {
        const modalBody = document.getElementById('modalBody');
        
        let methodsHtml = '';
        methods.forEach(method => {
            methodsHtml += `
                <div class="payment-method" onclick="selectPaymentMethod('${method.id}', this)">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="radio" name="payment_method" value="${method.id}" style="width: 20px; height: 20px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; margin-bottom: 4px;">${method.title}</div>
                            ${method.description ? `<div style="font-size: 13px; color: #6b7280;">${method.description}</div>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        modalBody.innerHTML = `
            <div style="text-align: center; margin-bottom: 24px;">
                <h3 style="margin: 0 0 8px 0;">${selectedPlanName} Plan</h3>
                <div style="font-size: 32px; font-weight: 700; color: #4CAF50;">
                    €${selectedPlanPrice}
                </div>
            </div>
            
            <div style="margin-bottom: 24px;">
                <h4 style="margin-bottom: 12px;">Select Payment Method:</h4>
                ${methodsHtml}
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button onclick="processPayment()" class="btn btn-primary" style="flex: 1;">
                    🔒 Pay Now
                </button>
                <button onclick="closeModal()" class="btn btn-outline" style="flex: 1;">
                    Cancel
                </button>
            </div>
        `;
        
        document.getElementById('paymentModal').style.display = 'flex';
    }
    
    let selectedPaymentMethod = null;
    
    function selectPaymentMethod(methodId, element) {
        // Remove previous selection
        document.querySelectorAll('.payment-method').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Add selection
        element.classList.add('selected');
        element.querySelector('input[type="radio"]').checked = true;
        selectedPaymentMethod = methodId;
    }
    
    function processPayment() {
        if (!selectedPaymentMethod) {
            alert('Please select a payment method');
            return;
        }
        
        closeModal();
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'cip_create_payment_order',
                nonce: '<?php echo wp_create_nonce('cip_payment_direct'); ?>',
                plan_index: selectedPlanIndex,
                payment_method: selectedPaymentMethod
            })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingOverlay').style.display = 'none';
            
            if (data.success) {
                // Redirect to payment page
                window.location.href = data.data.redirect;
            } else {
                alert('Error: ' + (data.data.message || 'Payment failed'));
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').style.display = 'none';
            alert('Error: ' + error.message);
        });
    }
    
    function closeModal() {
        document.getElementById('paymentModal').style.display = 'none';
        selectedPaymentMethod = null;
    }
    
    // Close modal on outside click
    document.getElementById('paymentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>