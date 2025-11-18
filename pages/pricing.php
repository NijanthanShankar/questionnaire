<?php
/**
 * ============================================
 * FILE 2: pricing.php (NEW)
 * ============================================
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

// Get pricing plans from settings
$pricing_plans = get_option('cip_pricing_plans', [
    [
        'name' => 'Basic',
        'price' => '499',
        'currency' => 'EUR',
        'features' => [
            'ESG Assessment',
            'Basic Certificate',
            'Email Support',
            '1 Year Validity'
        ],
        'popular' => false
    ],
    [
        'name' => 'Professional',
        'price' => '999',
        'currency' => 'EUR',
        'features' => [
            'ESG Assessment',
            'Premium Certificate',
            'Priority Support',
            '2 Years Validity',
            'Benchmarking Report',
            'Directory Listing'
        ],
        'popular' => true
    ],
    [
        'name' => 'Enterprise',
        'price' => '1999',
        'currency' => 'EUR',
        'features' => [
            'ESG Assessment',
            'Premium Certificate',
            'Dedicated Support',
            '3 Years Validity',
            'Detailed Analytics',
            'Featured Directory Listing',
            'Custom Reporting',
            'API Access'
        ],
        'popular' => false
    ]
]);

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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }
        .pricing-card {
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 2.5rem;
            border: 2px solid rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
            position: relative;
        }
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .pricing-card.popular {
            border-color: var(--primary);
            background: rgba(76, 175, 80, 0.1);
        }
        .popular-badge {
            position: absolute;
            top: -15px;
            right: 20px;
            background: var(--primary);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .price {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--primary);
            font-family: 'Raleway', sans-serif;
            margin: 1.5rem 0;
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
        }
        .feature-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .feature-list li::before {
            content: '✓';
            color: var(--primary);
            font-weight: bold;
            font-size: 1.25rem;
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
            <div class="text-center" style="margin-bottom: 3rem;">
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">Choose Your Plan</h1>
                <p style="font-size: 1.25rem; color: var(--gray-medium);">
                    Select the perfect plan for your organization
                </p>
            </div>
            
            <div class="pricing-grid">
                <?php foreach ($pricing_plans as $index => $plan): ?>
                    <div class="pricing-card <?php echo $plan['popular'] ? 'popular' : ''; ?>">
                        <?php if ($plan['popular']): ?>
                            <div class="popular-badge">⭐ Most Popular</div>
                        <?php endif; ?>
                        
                        <h3 style="font-size: 1.75rem; margin-bottom: 0.5rem;"><?php echo esc_html($plan['name']); ?></h3>
                        
                        <div class="price">
                            <span style="font-size: 1.5rem;">€</span><?php echo esc_html($plan['price']); ?>
                            <span style="font-size: 1rem; color: var(--gray-medium); font-weight: 400;">/year</span>
                        </div>
                        
                        <ul class="feature-list">
                            <?php foreach ($plan['features'] as $feature): ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <form method="POST" action="<?php echo home_url('/cleanindex/checkout'); ?>">
                            <?php wp_nonce_field('cip_checkout', 'cip_checkout_nonce'); ?>
                            <input type="hidden" name="plan_index" value="<?php echo $index; ?>">
                            <button type="submit" class="btn <?php echo $plan['popular'] ? 'btn-primary' : 'btn-outline'; ?>" 
                                    style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                                <?php echo $plan['popular'] ? '🚀 Get Started' : 'Select Plan'; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="glass-card" style="margin-top: 3rem; text-align: center;">
                <h3>💡 Need Help Choosing?</h3>
                <p style="color: var(--gray-medium); margin-bottom: 1.5rem;">
                    Our team is here to help you select the best plan for your needs.
                </p>
                <a href="mailto:support@cleanindex.com" class="btn btn-accent">
                    Contact Support
                </a>
            </div>
        </main>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>