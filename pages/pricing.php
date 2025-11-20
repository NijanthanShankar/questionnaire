<?php
/**
 * Fixed Pricing Page with AJAX Checkout (REQUIREMENT #6)
 * REPLACE pages/pricing.php with this
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

// Get pricing plans
$pricing_plans = get_option('cip_pricing_plans', [
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
]);

// Convert features string to array if needed
foreach ($pricing_plans as &$plan) {
    if (!is_array($plan['features'])) {
        $plan['features'] = array_filter(array_map('trim', explode("\n", $plan['features'])));
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Plans - CleanIndex Portal</title>
    <?php wp_head(); ?>
    <style>
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-lg);
            margin: var(--spacing-xl) 0;
        }
        
        .pricing-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            border: 2px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .pricing-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .pricing-card.popular {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .popular-badge {
            position: absolute;
            top: -12px;
            right: 20px;
            background: var(--primary);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .price {
            font-size: 48px;
            font-weight: 700;
            color: var(--primary);
            font-family: 'Raleway', sans-serif;
            line-height: 1;
            margin: var(--spacing-lg) 0;
        }
        
        .price-currency {
            font-size: 24px;
            vertical-align: super;
        }
        
        .price-period {
            font-size: 14px;
            color: var(--gray-500);
            font-weight: 400;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: var(--spacing-lg) 0;
        }
        
        .feature-list li {
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--gray-700);
        }
        
        .feature-list li:last-child {
            border-bottom: none;
        }
        
        .feature-list li::before {
            content: '✓';
            color: var(--primary);
            font-weight: bold;
            font-size: 16px;
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
                    <li><a href="<?php echo home_url('/cleanindex/pricing'); ?>" class="active">💳 Subscription</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url('/cleanindex/login')); ?>">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <div class="text-center" style="margin-bottom: var(--spacing-xl);">
                <h1 style="font-size: 36px; margin-bottom: 12px;">Choose Your Plan</h1>
                <p style="font-size: 16px; color: var(--gray-600);">
                    Select the perfect plan for your organization's ESG journey
                </p>
            </div>
            
            <div class="pricing-grid">
                <?php foreach ($pricing_plans as $index => $plan): ?>
                    <div class="pricing-card <?php echo $plan['popular'] ? 'popular' : ''; ?>">
                        <?php if ($plan['popular']): ?>
                            <div class="popular-badge">⭐ Most Popular</div>
                        <?php endif; ?>
                        
                        <h3 style="font-size: 22px; margin-bottom: 8px; color: var(--black);">
                            <?php echo esc_html($plan['name']); ?>
                        </h3>
                        
                        <div class="price">
                            <span class="price-currency">€</span><?php echo esc_html($plan['price']); ?>
                            <span class="price-period">/year</span>
                        </div>
                        
                        <ul class="feature-list">
                            <?php foreach ($plan['features'] as $feature): ?>
                                <?php if (!empty(trim($feature))): ?>
                                    <li><?php echo esc_html($feature); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                        
                        <button onclick="selectPlan(<?php echo $index; ?>)" 
                                class="btn <?php echo $plan['popular'] ? 'btn-primary' : 'btn-outline'; ?>" 
                                style="width: 100%; padding: 14px; font-size: 15px; margin-top: auto;">
                            <?php echo $plan['popular'] ? '🚀 Get Started' : 'Select Plan'; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="glass-card" style="margin-top: var(--spacing-xl); text-align: center;">
                <h3 style="margin-bottom: 12px;">💡 Need Help Choosing?</h3>
                <p style="color: var(--gray-600); margin-bottom: var(--spacing-md);">
                    Our team is here to help you select the best plan for your needs.
                </p>
                <a href="mailto:support@cleanindex.com" class="btn btn-accent">
                    Contact Support
                </a>
            </div>
        </main>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 32px; border-radius: 12px; text-align: center;">
            <div class="spinner"></div>
            <p style="margin-top: 16px; color: var(--gray-700);">Processing your selection...</p>
        </div>
    </div>
    
    <script>
    function selectPlan(planIndex) {
        if (!confirm('Add this plan to cart and proceed to checkout?')) {
            return;
        }
        
        // Show loading
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        // AJAX request to add to cart
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'cip_add_to_cart',
                nonce: '<?php echo wp_create_nonce('cip_checkout'); ?>',
                plan_index: planIndex
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirect to checkout
                window.location.href = data.data.checkout_url;
            } else {
                // Hide loading and show error
                document.getElementById('loadingOverlay').style.display = 'none';
                alert('Error: ' + (data.data.message || 'Failed to add to cart. Please try again.'));
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').style.display = 'none';
            alert('Error: ' + error.message);
        });
    }
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>