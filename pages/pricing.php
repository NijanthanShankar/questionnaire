<?php
/**
 * CleanIndex Portal - Pricing Page (REAL PAYMENT VERSION)
 * With WooCommerce Integration
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

$user = wp_get_current_user();
$registration = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_registrations WHERE email = %s",
    $user->user_email
), ARRAY_A);

if (!$registration) {
    wp_redirect(home_url('/cleanindex/dashboard'));
    exit;
}

// Get pricing plans
$pricing_plans = get_option('cip_pricing_plans', []);

// Check if WooCommerce is active
$woo_active = class_exists('WooCommerce');

// Helper function to get currency symbol
if (!function_exists('cip_get_currency_symbol')) {
    function cip_get_currency_symbol($currency) {
        $symbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹', 
            'AUD' => 'A$', 'CAD' => 'C$', 'CHF' => 'CHF', 
            'CNY' => '¥', 'JPY' => '¥'
        ];
        return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe - CleanIndex Portal</title>
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
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 32px;
            cursor: pointer;
            color: #666;
            line-height: 1;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            color: #f44336;
        }
        
        .payment-method {
            padding: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .payment-method:hover {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.05);
        }
        
        .payment-method.selected {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
        }
        
        .payment-method input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #4CAF50;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f44336;
            color: #721c24;
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
                    Select a plan and complete secure payment to get your ESG certificate
                </p>
            </div>
            
            <?php if (!$woo_active): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Payment System Not Configured</strong><br>
                    WooCommerce is required for payment processing. Please contact the administrator.
                </div>
            <?php else: ?>
                <?php
                // Check if any payment gateways are available
                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                if (empty($available_gateways)):
                ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Payment Methods Being Configured</strong><br>
                        Payment methods are currently being set up. Please check back shortly or contact support.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="pricing-grid">
                <?php foreach ($pricing_plans as $index => $plan): ?>
                    <?php 
                    $currency_code = isset($plan['currency']) ? $plan['currency'] : 'EUR';
                    $currency_symbol = cip_get_currency_symbol($currency_code);
                    ?>
                    <div class="pricing-card <?php echo !empty($plan['popular']) ? 'popular' : ''; ?>">
                        <?php if (!empty($plan['popular'])): ?>
                            <div class="popular-badge">⭐ Most Popular</div>
                        <?php endif; ?>
                        
                        <h3 style="font-size: 22px; margin-bottom: 8px; margin-top: 0;">
                            <?php echo esc_html($plan['name']); ?>
                        </h3>
                        
                        <div style="font-size: 48px; font-weight: 700; color: #4CAF50; margin: 24px 0;">
                            <span style="font-size: 24px; vertical-align: super;"><?php echo esc_html($currency_symbol); ?></span>
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
                            onclick="selectPlan(<?php echo $index; ?>, '<?php echo esc_js($plan['name']); ?>', <?php echo esc_js($plan['price']); ?>, '<?php echo esc_js($currency_code); ?>')"
                            class="btn <?php echo !empty($plan['popular']) ? 'btn-primary' : 'btn-outline'; ?>"
                            style="width: 100%; padding: 14px; font-weight: 600;"
                            <?php echo !$woo_active || empty($available_gateways) ? 'disabled' : ''; ?>>
                            <?php echo !empty($plan['popular']) ? '🚀 Select Plan' : 'Select Plan'; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin: 0; font-size: 24px;">Complete Your Payment</h2>
                <button class="modal-close" onclick="closeModal()" aria-label="Close">×</button>
            </div>
            <div id="modalBody">
                <!-- Payment options will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 48px; border-radius: 12px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #4CAF50; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto;"></div>
            <p style="margin-top: 16px; font-weight: 500; color: #333;">Processing your payment...</p>
            <p style="margin-top: 8px; font-size: 13px; color: #666;">Please do not close this window</p>
        </div>
    </div>
    
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    
    <script>
    let selectedPlanIndex = null;
    let selectedPlanName = '';
    let selectedPlanPrice = 0;
    let selectedPlanCurrency = 'EUR';
    let selectedPaymentMethod = null;
    
    function selectPlan(planIndex, planName, planPrice, planCurrency) {
        selectedPlanIndex = planIndex;
        selectedPlanName = planName;
        selectedPlanPrice = planPrice;
        selectedPlanCurrency = planCurrency || 'EUR';
        
        console.log('Plan selected:', {planIndex, planName, planPrice, planCurrency});
        loadPaymentMethods();
    }
    
    function loadPaymentMethods() {
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'cip_get_payment_methods',
                nonce: '<?php echo wp_create_nonce('cip_payment_direct'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingOverlay').style.display = 'none';
            console.log('Payment methods response:', data);
            
            if (data.success && data.data.methods && data.data.methods.length > 0) {
                showPaymentModal(data.data.methods);
            } else {
                alert('Payment methods are not configured yet. Please contact support or try again later.');
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').style.display = 'none';
            console.error('Error loading payment methods:', error);
            alert('Error loading payment options. Please try again or contact support.');
        });
    }
    
    function showPaymentModal(methods) {
        const modalBody = document.getElementById('modalBody');
        const currencySymbols = {
            'USD': '$', 'EUR': '€', 'GBP': '£', 'INR': '₹', 'AUD': 'A$', 
            'CAD': 'C$', 'CHF': 'CHF', 'CNY': '¥', 'JPY': '¥'
        };
        const currencySymbol = currencySymbols[selectedPlanCurrency] || selectedPlanCurrency;
        
        let methodsHtml = '';
        methods.forEach((method, index) => {
            methodsHtml += `
                <label class="payment-method" for="payment_${method.id}" data-method-id="${method.id}">
                    <input type="radio" name="payment_method" id="payment_${method.id}" 
                           value="${method.id}" ${index === 0 ? 'checked' : ''}
                           onchange="selectPaymentMethodRadio('${method.id}')">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; margin-bottom: 4px;">${method.title}</div>
                        ${method.description ? `<div style="font-size: 13px; color: #6b7280;">${method.description}</div>` : ''}
                    </div>
                </label>
            `;
        });
        
        modalBody.innerHTML = `
            <div style="text-align: center; margin-bottom: 24px; padding: 20px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 8px;">
                <h3 style="margin: 0 0 8px 0; color: #0369a1;">${selectedPlanName} Plan</h3>
                <div style="font-size: 32px; font-weight: 700; color: #4CAF50;">
                    ${currencySymbol}${selectedPlanPrice}
                </div>
                <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                    ${selectedPlanCurrency} per year
                </div>
            </div>
            
            <div style="margin-bottom: 24px;">
                <h4 style="margin-bottom: 12px; font-size: 16px;">Select Payment Method:</h4>
                ${methodsHtml}
            </div>
            
            <div style="padding: 12px; background: #f9fafb; border-radius: 6px; margin-bottom: 16px; font-size: 13px; color: #6b7280;">
                🔒 Your payment is secure and encrypted. You will be redirected to complete the transaction.
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button onclick="processPayment()" class="btn btn-primary" style="flex: 1; padding: 14px; font-weight: 600;">
                    🔒 Proceed to Payment
                </button>
                <button onclick="closeModal()" class="btn btn-outline" style="flex: 0.5; padding: 14px;">
                    Cancel
                </button>
            </div>
        `;
        
        document.getElementById('paymentModal').style.display = 'flex';
        
        // Set first method as default
        if (methods.length > 0) {
            selectedPaymentMethod = methods[0].id;
            console.log('Default payment method:', selectedPaymentMethod);
            setTimeout(() => {
                const firstMethod = document.querySelector('.payment-method');
                if (firstMethod) firstMethod.classList.add('selected');
            }, 100);
        }
    }
    
    function selectPaymentMethodRadio(methodId) {
        console.log('Payment method selected:', methodId);
        selectedPaymentMethod = methodId;
        document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
        const selectedLabel = document.querySelector(`label[data-method-id="${methodId}"]`);
        if (selectedLabel) selectedLabel.classList.add('selected');
    }
    
    function processPayment() {
        console.log('Processing payment with method:', selectedPaymentMethod);
        
        if (!selectedPaymentMethod) {
            alert('Please select a payment method');
            return;
        }
        
        closeModal();
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'cip_create_payment_order',
                nonce: '<?php echo wp_create_nonce('cip_payment_direct'); ?>',
                plan_index: selectedPlanIndex,
                payment_method: selectedPaymentMethod
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Payment response:', data);
            
            if (data.success) {
                // Redirect to payment gateway
                window.location.href = data.data.redirect;
            } else {
                document.getElementById('loadingOverlay').style.display = 'none';
                alert('Error: ' + (data.data.message || 'Payment processing failed. Please try again.'));
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').style.display = 'none';
            console.error('Payment error:', error);
            alert('Error processing payment. Please try again or contact support.');
        });
    }
    
    function closeModal() {
        document.getElementById('paymentModal').style.display = 'none';
        selectedPaymentMethod = null;
    }
    
    // Close modal on outside click
    document.getElementById('paymentModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('paymentModal').style.display === 'flex') {
            closeModal();
        }
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>