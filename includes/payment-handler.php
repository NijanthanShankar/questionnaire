<?php
/**
 * Fixed WooCommerce Integration (REQUIREMENT #6)
 * ADD to includes/payment-handler.php or REPLACE existing functions
 */

if (!defined('ABSPATH')) exit;

/**
 * Create/Update WooCommerce Products for Pricing Plans
 */
function cip_sync_woocommerce_products() {
    if (!class_exists('WooCommerce')) {
        return new WP_Error('woocommerce_missing', 'WooCommerce is not installed');
    }
    
    $pricing_plans = get_option('cip_pricing_plans', []);
    
    foreach ($pricing_plans as $index => $plan) {
        $product_id = get_option('cip_woo_product_' . $index);
        
        if ($product_id && get_post_status($product_id) === 'publish') {
            // Update existing product
            $product = wc_get_product($product_id);
        } else {
            // Create new product
            $product = new WC_Product_Simple();
        }
        
        $product->set_name('CleanIndex ' . $plan['name'] . ' Plan');
        $product->set_regular_price($plan['price']);
        $product->set_description('CleanIndex ESG Certification - ' . $plan['name'] . ' Plan');
        
        // Parse features
        $features = is_array($plan['features']) ? $plan['features'] : explode("\n", $plan['features']);
        $features_html = '<ul>';
        foreach ($features as $feature) {
            if (trim($feature)) {
                $features_html .= '<li>' . esc_html(trim($feature)) . '</li>';
            }
        }
        $features_html .= '</ul>';
        
        $product->set_short_description($features_html);
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_sold_individually(true);
        $product->set_manage_stock(false);
        
        // Save meta data
        $product->update_meta_data('_cip_plan_index', $index);
        $product->update_meta_data('_cip_plan_name', $plan['name']);
        $product->update_meta_data('_cip_plan_currency', $plan['currency']);
        
        $new_product_id = $product->save();
        
        if (!$product_id) {
            update_option('cip_woo_product_' . $index, $new_product_id);
        }
    }
    
    return true;
}

/**
 * AJAX Handler: Add to Cart and Get Checkout URL (REQUIREMENT #6 FIX)
 */
add_action('wp_ajax_cip_add_to_cart', 'cip_ajax_add_to_cart');

function cip_ajax_add_to_cart() {
    check_ajax_referer('cip_checkout', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please login first']);
        return;
    }
    
    if (!class_exists('WooCommerce')) {
        wp_send_json_error(['message' => 'WooCommerce is not installed']);
        return;
    }
    
    $plan_index = intval($_POST['plan_index']);
    $product_id = get_option('cip_woo_product_' . $plan_index);
    
    if (!$product_id || get_post_status($product_id) !== 'publish') {
        // Try to sync products
        cip_sync_woocommerce_products();
        $product_id = get_option('cip_woo_product_' . $plan_index);
        
        if (!$product_id) {
            wp_send_json_error(['message' => 'Product not found. Please contact support.']);
            return;
        }
    }
    
    // Clear cart
    WC()->cart->empty_cart();
    
    // Add to cart
    $cart_item_key = WC()->cart->add_to_cart($product_id, 1);
    
    if ($cart_item_key) {
        // Store plan selection in user meta
        update_user_meta(get_current_user_id(), 'cip_selected_plan_index', $plan_index);
        
        wp_send_json_success([
            'message' => 'Plan added to cart',
            'checkout_url' => wc_get_checkout_url()
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to add to cart']);
    }
}

/**
 * Enhanced Pricing Page with AJAX (UPDATE pages/pricing.php)
 */
function cip_render_pricing_page() {
    ?>
    <script>
    function selectPlan(planIndex) {
        if (!confirm('Add this plan to cart and proceed to checkout?')) {
            return;
        }
        
        const button = event.target;
        button.disabled = true;
        button.textContent = '⏳ Processing...';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'cip_add_to_cart',
                nonce: '<?php echo wp_create_nonce('cip_checkout'); ?>',
                plan_index: planIndex
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.data.checkout_url;
            } else {
                alert('Error: ' + (data.data.message || 'Failed to add to cart'));
                button.disabled = false;
                button.textContent = 'Select Plan';
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
            button.disabled = false;
            button.textContent = 'Select Plan';
        });
    }
    </script>
    <?php
}

/**
 * Handle WooCommerce Order Completion
 */
add_action('woocommerce_order_status_completed', 'cip_handle_order_completion');

function cip_handle_order_completion($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $user_id = $order->get_user_id();
    if (!$user_id) return;
    
    // Check if CleanIndex order
    $is_cip_order = false;
    $plan_name = '';
    
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product) {
            $plan_index = $product->get_meta('_cip_plan_index');
            if ($plan_index !== '') {
                $is_cip_order = true;
                $plan_name = $product->get_meta('_cip_plan_name');
                break;
            }
        }
    }
    
    if (!$is_cip_order) return;
    
    // Activate subscription
    update_user_meta($user_id, 'cip_subscription_status', 'active');
    update_user_meta($user_id, 'cip_subscription_plan', $plan_name);
    update_user_meta($user_id, 'cip_subscription_start', current_time('mysql'));
    update_user_meta($user_id, 'cip_woo_order_id', $order_id);
    
    // Create notification
    cip_create_notification(
        $user_id,
        'Subscription Activated',
        'Your ' . $plan_name . ' subscription is now active. You can now generate your certificate.',
        'success',
        home_url('/cleanindex/dashboard')
    );
    
    // Send notification email
    $user = get_userdata($user_id);
    cip_send_notification_email(
        $user->user_email,
        'Subscription Activated - CleanIndex',
        'Your ' . $plan_name . ' subscription is now active. You can now generate your ESG certificate from your dashboard.',
        'success'
    );
    
    // Auto-generate certificate if assessment is complete
    global $wpdb;
    $table_registrations = $wpdb->prefix . 'company_registrations';
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_registrations WHERE email = %s",
        $user->user_email
    ), ARRAY_A);
    
    if ($registration) {
        $table_assessments = $wpdb->prefix . 'company_assessments';
        $assessment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_assessments WHERE user_id = %d AND progress >= 5",
            $registration['id']
        ), ARRAY_A);
        
        if ($assessment && class_exists('CIP_PDF_Generator')) {
            $cert_mode = get_option('cip_cert_grading_mode', 'automatic');
            
            if ($cert_mode === 'automatic') {
                $assessment_data = json_decode($assessment['assessment_json'], true);
                $grade = CIP_PDF_Generator::calculate_grade($assessment_data);
                $result = CIP_PDF_Generator::generate_certificate_pdf($registration['id'], $grade);
                
                if ($result['success']) {
                    // Send certificate email
                    cip_send_certificate_email($user->user_email, $registration['company_name'], $grade, $result['url']);
                    
                    // Create notification
                    cip_create_notification(
                        $user_id,
                        'Certificate Generated',
                        'Your ESG certificate has been generated with grade: ' . $grade,
                        'success',
                        home_url('/cleanindex/certificate')
                    );
                }
            }
        }
    }
}

/**
 * Send certificate email
 */
function cip_send_certificate_email($email, $company_name, $grade, $certificate_url) {
    $subject = 'Your ESG Certificate is Ready! - CleanIndex';
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Inter, sans-serif; background: #FAFAFA; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 32px; border-radius: 12px; }
            h1 { color: #4CAF50; font-family: Raleway, sans-serif; font-size: 24px; margin-bottom: 16px; }
            .grade { background: #4CAF50; color: white; padding: 16px 32px; border-radius: 50px; font-size: 32px; font-weight: bold; display: inline-block; margin: 20px 0; }
            .button { display: inline-block; padding: 14px 28px; background: #4CAF50; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 14px; margin: 16px 8px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🏆 Your ESG Certificate is Ready!</h1>
            <p>Dear ' . esc_html($company_name) . ',</p>
            <p>Congratulations! Your ESG certification has been completed.</p>
            <div style="text-align: center;">
                <div class="grade">' . esc_html($grade) . '</div>
            </div>
            <p style="text-align: center;">
                <a href="' . esc_url($certificate_url) . '" class="button">Download Certificate</a>
            </p>
        </div>
    </body>
    </html>
    ';
    
    return wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
}

// Run sync on activation
register_activation_hook(CIP_PLUGIN_FILE, 'cip_sync_woocommerce_products');