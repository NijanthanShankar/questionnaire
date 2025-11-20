# CleanIndex Portal - Complete Implementation Guide

## Quick Installation Steps

### 1. **Backup Your Site**
```bash
# Create backup of your WordPress site
# Export database and download all plugin files
```

### 2. **Replace Core Files**

Replace these files in your plugin directory:

- `admin/settings-page.php` → Use **Enhanced Admin Settings Page**
- `css/style.css` → Use **Modern Minimal Design CSS**
- `includes/email.php` → Add **Enhanced Email Notifications** functions
- `includes/payment-handler.php` → Add **Fixed WooCommerce Checkout** functions

### 3. **Add New Files**

Create these new files:

- `pages/profile.php` → Use **Profile Management Page**
- `pages/notifications.php` → Create notifications page (code below)
- `pages/billing.php` → Create billing management (code below)

### 4. **Update Existing Files**

#### Update `cleanindex-portal.php` (main plugin file)

Add these rewrite rules in the `cip_add_rewrite_rules()` function:

```php
'profile',
'notifications',
'billing',
```

Add profile/notifications links to all dashboard navigation menus.

### 5. **Update Database**

Run this SQL to create notifications table:

```sql
CREATE TABLE IF NOT EXISTS wp_cip_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type VARCHAR(50) DEFAULT 'info',
    link VARCHAR(500),
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Requirement #3: View Submission with Answers & PDF

### Update Admin Dashboard

Add this function to `pages/admin-dashboard.php` after the `viewSubmission()` function:

```javascript
function viewSubmissionAnswers(userId) {
    // Fetch assessment data
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'cip_get_assessment_answers',
            nonce: '<?php echo wp_create_nonce('cip_admin_action'); ?>',
            user_id: userId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const answers = data.data.answers;
            let html = '<h3>Assessment Answers</h3>';
            
            // Display answers by section
            for (let step = 1; step <= 5; step++) {
                html += '<div style="margin-bottom: 24px;">';
                html += '<h4 style="color: var(--primary);">Step ' + step + '</h4>';
                
                // Filter answers for this step
                for (let key in answers) {
                    if (key.startsWith('q' + step + '_')) {
                        html += '<div style="margin-bottom: 16px; padding: 12px; background: var(--gray-50); border-radius: 8px;">';
                        html += '<strong>' + key + ':</strong> ' + (answers[key] || '<em>No answer</em>');
                        html += '</div>';
                    }
                }
                html += '</div>';
            }
            
            // Add download button
            html += '<a href="<?php echo home_url('/cleanindex/download-assessment-pdf'); ?>?user_id=' + userId + '" class="btn btn-primary">Download PDF</a>';
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('submissionModal').style.display = 'flex';
        }
    });
}
```

### Add AJAX Handler

Add to `cleanindex-portal.php`:

```php
add_action('wp_ajax_cip_get_assessment_answers', 'cip_ajax_get_assessment_answers');

function cip_ajax_get_assessment_answers() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'company_assessments';
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    if ($assessment) {
        $answers = json_decode($assessment['assessment_json'], true);
        wp_send_json_success(['answers' => $answers]);
    } else {
        wp_send_json_error('No assessment found');
    }
}
```

---

## Requirement #4: Send Email After Both Approvals ✅

**Already implemented** in the Enhanced Email System. Update your admin approval handler:

```php
// In admin-dashboard.php, after admin approves:
if ($_POST['action'] === 'approve') {
    $wpdb->update($table, ['status' => 'approved'], ['id' => $submission_id]);
    
    // Send assessment start email (NEW)
    cip_check_and_send_assessment_email($submission_id);
    
    $success_message = 'Organization approved and email sent';
}
```

---

## Requirement #7: Advanced Question Management

### Create Admin Panel

Create `admin/questions-manager.php`:

```php
<?php
/**
 * Questions Manager - Add/Edit Assessment Questions
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Get or initialize questions
$questions = get_option('cip_assessment_questions', cip_get_default_questions());

// Handle save
if (isset($_POST['save_questions'])) {
    check_admin_referer('cip_save_questions');
    
    $new_questions = [];
    foreach ($_POST['questions'] as $step => $step_questions) {
        $new_questions[$step] = [];
        foreach ($step_questions as $q_id => $question) {
            $new_questions[$step][$q_id] = [
                'text' => sanitize_textarea_field($question['text']),
                'weight' => floatval($question['weight']),
                'type' => sanitize_text_field($question['type']),
                'required' => !empty($question['required'])
            ];
        }
    }
    
    update_option('cip_assessment_questions', $new_questions);
    echo '<div class="notice notice-success"><p>Questions saved successfully!</p></div>';
}

?>
<div class="wrap">
    <h1>Assessment Questions Manager</h1>
    
    <form method="post">
        <?php wp_nonce_field('cip_save_questions'); ?>
        
        <?php foreach ($questions as $step => $step_questions): ?>
            <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
                <h2>Step <?php echo $step; ?></h2>
                
                <?php foreach ($step_questions as $q_id => $question): ?>
                    <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 6px;">
                        <label><strong>Question ID:</strong> <?php echo $q_id; ?></label>
                        
                        <textarea name="questions[<?php echo $step; ?>][<?php echo $q_id; ?>][text]" 
                                  class="large-text" rows="2"><?php echo esc_textarea($question['text']); ?></textarea>
                        
                        <div style="margin-top: 10px;">
                            <label>Weight: 
                                <input type="number" name="questions[<?php echo $step; ?>][<?php echo $q_id; ?>][weight]" 
                                       value="<?php echo esc_attr($question['weight']); ?>" 
                                       step="0.1" min="0" max="10" style="width: 80px;">
                            </label>
                            
                            <label style="margin-left: 20px;">Type:
                                <select name="questions[<?php echo $step; ?>][<?php echo $q_id; ?>][type]">
                                    <option value="text" <?php selected($question['type'], 'text'); ?>>Text</option>
                                    <option value="textarea" <?php selected($question['type'], 'textarea'); ?>>Textarea</option>
                                    <option value="number" <?php selected($question['type'], 'number'); ?>>Number</option>
                                    <option value="select" <?php selected($question['type'], 'select'); ?>>Dropdown</option>
                                </select>
                            </label>
                            
                            <label style="margin-left: 20px;">
                                <input type="checkbox" name="questions[<?php echo $step; ?>][<?php echo $q_id; ?>][required]" 
                                       value="1" <?php checked(!empty($question['required'])); ?>>
                                Required
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        
        <p class="submit">
            <button type="submit" name="save_questions" class="button button-primary">Save All Questions</button>
        </p>
    </form>
</div>
```

Add menu item in `cleanindex-portal.php`:

```php
add_submenu_page(
    'cleanindex-portal',
    'Questions Manager',
    'Questions',
    'manage_options',
    'cleanindex-questions',
    'cip_admin_questions_page'
);

function cip_admin_questions_page() {
    include CIP_PLUGIN_DIR . 'admin/questions-manager.php';
}
```

---

## Requirement #8: Certificate Management Panel

### Create Certificate Manager

Create `admin/certificates-manager.php`:

```php
<?php
/**
 * Certificates Manager
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Get all certificates
global $wpdb;
$certificates = $wpdb->get_results("
    SELECT u.ID, u.user_email, u.display_name,
           um1.meta_value as cert_grade,
           um2.meta_value as cert_url,
           um3.meta_value as cert_number,
           um4.meta_value as cert_generated
    FROM {$wpdb->users} u
    LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'cip_certificate_grade'
    LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'cip_certificate_url'
    LEFT JOIN {$wpdb->usermeta} um3 ON u.ID = um3.user_id AND um3.meta_key = 'cip_certificate_number'
    LEFT JOIN {$wpdb->usermeta} um4 ON u.ID = um4.user_id AND um4.meta_key = 'cip_certificate_generated'
    WHERE um1.meta_value IS NOT NULL
    ORDER BY u.ID DESC
", ARRAY_A);

// Handle revoke
if (isset($_POST['revoke_certificate'])) {
    check_admin_referer('cip_cert_action');
    
    $user_id = intval($_POST['user_id']);
    delete_user_meta($user_id, 'cip_certificate_generated');
    delete_user_meta($user_id, 'cip_certificate_url');
    delete_user_meta($user_id, 'cip_certificate_grade');
    delete_user_meta($user_id, 'cip_certificate_number');
    
    echo '<div class="notice notice-success"><p>Certificate revoked</p></div>';
}

?>
<div class="wrap">
    <h1>Certificates Management</h1>
    
    <div style="background: white; padding: 20px; margin: 20px 0;">
        <h2>Badge Templates Configuration</h2>
        <table class="form-table">
            <tr>
                <th>ESG+++ Badge</th>
                <td><input type="color" value="#2E7D32"> <span>Color for top tier</span></td>
            </tr>
            <tr>
                <th>ESG++ Badge</th>
                <td><input type="color" value="#4CAF50"></td>
            </tr>
            <tr>
                <th>ESG+ Badge</th>
                <td><input type="color" value="#66BB6A"></td>
            </tr>
            <tr>
                <th>ESG Badge</th>
                <td><input type="color" value="#81C784"></td>
            </tr>
        </table>
    </div>
    
    <div style="background: white; padding: 20px;">
        <h2>Issued Certificates</h2>
        
        <?php if (empty($certificates)): ?>
            <p>No certificates issued yet.</p>
        <?php else: ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Grade</th>
                        <th>Certificate Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificates as $cert): ?>
                        <tr>
                            <td><?php echo esc_html($cert['display_name']); ?></td>
                            <td><?php echo esc_html($cert['user_email']); ?></td>
                            <td><strong style="color: #4CAF50;"><?php echo esc_html($cert['cert_grade']); ?></strong></td>
                            <td><code><?php echo esc_html($cert['cert_number']); ?></code></td>
                            <td>
                                <a href="<?php echo esc_url($cert['cert_url']); ?>" target="_blank" class="button button-small">View</a>
                                
                                <form method="post" style="display: inline;" onsubmit="return confirm('Revoke this certificate?');">
                                    <?php wp_nonce_field('cip_cert_action'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $cert['ID']; ?>">
                                    <button type="submit" name="revoke_certificate" class="button button-small">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
```

Add menu:

```php
add_submenu_page(
    'cleanindex-portal',
    'Certificates',
    'Certificates',
    'manage_options',
    'cleanindex-certificates',
    function() { include CIP_PLUGIN_DIR . 'admin/certificates-manager.php'; }
);
```

---

## Requirement #10: Notifications Component

Create `pages/notifications.php`:

```php
<?php
/**
 * Notifications Page
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

$user_id = get_current_user_id();

// Mark as read if requested
if (isset($_GET['mark_read'])) {
    cip_mark_notification_read(intval($_GET['mark_read']));
    wp_redirect(home_url('/cleanindex/notifications'));
    exit;
}

// Get notifications
$notifications = cip_get_user_notifications($user_id, 50);
$unread_count = cip_get_unread_count($user_id);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Notifications - CleanIndex</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">🌱 CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/dashboard'); ?>">📊 Dashboard</a></li>
                    <li><a href="<?php echo home_url('/cleanindex/notifications'); ?>" class="active">
                        🔔 Notifications
                        <?php if ($unread_count > 0): ?>
                            <span style="background: #EB5E28; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 4px;">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="<?php echo wp_logout_url(home_url()); ?>">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <div class="dashboard-header">
                <h1>Notifications</h1>
                <?php if ($unread_count > 0): ?>
                    <span class="badge badge-pending"><?php echo $unread_count; ?> Unread</span>
                <?php endif; ?>
            </div>
            
            <div class="glass-card">
                <?php if (empty($notifications)): ?>
                    <div style="text-align: center; padding: 3rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🔔</div>
                        <p style="color: var(--gray-500);">No notifications yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div style="padding: 16px; border-bottom: 1px solid var(--gray-200); <?php echo $notif['is_read'] ? 'opacity: 0.6;' : 'background: rgba(76, 175, 80, 0.02);'; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <?php
                                        $icons = ['success' => '✅', 'info' => 'ℹ️', 'warning' => '⚠️', 'error' => '❌'];
                                        echo $icons[$notif['type']] ?? 'ℹ️';
                                        ?>
                                        <strong><?php echo esc_html($notif['title']); ?></strong>
                                        <?php if (!$notif['is_read']): ?>
                                            <span class="badge badge-pending" style="font-size: 10px;">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <p style="margin: 0; color: var(--gray-600); font-size: 13px;">
                                        <?php echo esc_html($notif['message']); ?>
                                    </p>
                                    <small style="color: var(--gray-400); font-size: 11px;">
                                        <?php echo cip_time_ago($notif['created_at']); ?>
                                    </small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <?php if ($notif['link']): ?>
                                        <a href="<?php echo esc_url($notif['link']); ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px;">View</a>
                                    <?php endif; ?>
                                    <?php if (!$notif['is_read']): ?>
                                        <a href="?mark_read=<?php echo $notif['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">Mark Read</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
```

---

## Requirement #11 & #12: Billing Management

Create `pages/billing.php`:

```php
<?php
/**
 * Billing & Membership Management
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

$user_id = get_current_user_id();
$subscription_status = get_user_meta($user_id, 'cip_subscription_status', true);
$subscription_plan = get_user_meta($user_id, 'cip_subscription_plan', true);
$subscription_start = get_user_meta($user_id, 'cip_subscription_start', true);
$order_id = get_user_meta($user_id, 'cip_woo_order_id', true);

// Get WooCommerce orders if available
$orders = [];
if (class_exists('WooCommerce') && $order_id) {
    $customer_orders = wc_get_orders([
        'customer_id' => $user_id,
        'limit' => 10
    ]);
    
    foreach ($customer_orders as $order) {
        $orders[] = [
            'id' => $order->get_id(),
            'date' => $order->get_date_created()->format('M d, Y'),
            'total' => $order->get_formatted_order_total(),
            'status' => $order->get_status()
        ];
    }
}

// Get certificates
$certificates = [];
$cert_url = get_user_meta($user_id, 'cip_certificate_url', true);
$cert_grade = get_user_meta($user_id, 'cip_certificate_grade', true);
$cert_number = get_user_meta($user_id, 'cip_certificate_number', true);

if ($cert_url) {
    $certificates[] = [
        'grade' => $cert_grade,
        'number' => $cert_number,
        'url' => $cert_url,
        'issued' => $subscription_start
    ];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Billing & Membership - CleanIndex</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">🌱 CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/dashboard'); ?>">📊 Dashboard</a></li>
                    <li><a href="<?php echo home_url('/cleanindex/billing'); ?>" class="active">💳 Billing</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url()); ?>">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <h1>Billing & Membership</h1>
            
            <!-- Subscription Status -->
            <div class="glass-card mb-3">
                <h2 class="mb-3">Current Subscription</h2>
                
                <?php if ($subscription_status === 'active'): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: rgba(76, 175, 80, 0.05); border-radius: 12px; border-left: 4px solid var(--primary);">
                        <div>
                            <div style="font-size: 24px; font-weight: 600; color: var(--primary); margin-bottom: 4px;">
                                <?php echo esc_html($subscription_plan); ?> Plan
                            </div>
                            <div style="color: var(--gray-600); font-size: 13px;">
                                Active since <?php echo date('M d, Y', strtotime($subscription_start)); ?>
                            </div>
                        </div>
                        <span class="badge badge-approved" style="font-size: 14px;">Active</span>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <a href="<?php echo home_url('/cleanindex/pricing'); ?>" class="btn btn-accent">
                            Upgrade Plan
                        </a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem;">
                        <p style="color: var(--gray-600); margin-bottom: 16px;">No active subscription</p>
                        <a href="<?php echo home_url('/cleanindex/pricing'); ?>" class="btn btn-primary">
                            View Plans
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Certificates List (REQUIREMENT #11) -->
            <?php if (!empty($certificates)): ?>
                <div class="glass-card mb-3">
                    <h2 class="mb-3">Your Certificates</h2>
                    
                    <?php foreach ($certificates as $cert): ?>
                        <div style="padding: 16px; background: var(--gray-50); border-radius: 12px; margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 20px; font-weight: 700; color: var(--primary); margin-bottom: 4px;">
                                        <?php echo esc_html($cert['grade']); ?> Certification
                                    </div>
                                    <div style="font-size: 12px; color: var(--gray-600);">
                                        Certificate #<?php echo esc_html($cert['number']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--gray-500);">
                                        Issued: <?php echo date('M d, Y', strtotime($cert['issued'])); ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="<?php echo esc_url($cert['url']); ?>" download class="btn btn-primary" style="padding: 10px 20px;">
                                        📥 Download
                                    </a>
                                    <button onclick="shareCertificate('<?php echo esc_js($cert['number']); ?>')" class="btn btn-accent" style="padding: 10px 20px;">
                                        🔗 Share
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Invoices (REQUIREMENT #12) -->
            <?php if (!empty($orders)): ?>
                <div class="glass-card">
                    <h2 class="mb-3">Invoices & Orders</h2>
                    
                    <div class="data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo $order['date']; ?></td>
                                        <td><?php echo $order['total']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status'] === 'completed' ? 'approved' : 'pending'; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo wc_get_endpoint_url('view-order', $order['id'], wc_get_page_permalink('myaccount')); ?>" 
                                               class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
    function shareCertificate(certNumber) {
        const url = '<?php echo home_url('/cleanindex/verify/'); ?>' + certNumber;
        const text = 'Check out my ESG certification from CleanIndex!';
        
        if (navigator.share) {
            navigator.share({title: 'ESG Certificate', text: text, url: url});
        } else {
            navigator.clipboard.writeText(url).then(() => {
                alert('Certificate link copied to clipboard!');
            });
        }
    }
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
```

---

## Final Deployment Checklist

### ✅ Phase 1: Core Updates
- [ ] Replace CSS file with modern design
- [ ] Update admin settings page
- [ ] Add enhanced email functions
- [ ] Fix WooCommerce integration

### ✅ Phase 2: New Features
- [ ] Add profile management page
- [ ] Add notifications system
- [ ] Add billing page
- [ ] Create admin panels for questions/certificates

### ✅ Phase 3: Testing
- [ ] Test manager registration with access code
- [ ] Test pricing plan → checkout flow
- [ ] Verify email notifications on approval
- [ ] Test profile updates and logo upload
- [ ] Check notifications display
- [ ] Verify certificate management

### ✅ Phase 4: Go Live
- [ ] Backup database
- [ ] Deploy all files
- [ ] Run database migrations
- [ ] Test all user flows
- [ ] Monitor error logs

---

## Support & Troubleshooting

### Common Issues

**Issue: 404 on new pages**
```bash
Go to Settings → Permalinks → Click "Save Changes"
```

**Issue: WooCommerce not redirecting**
```bash
1. Verify WooCommerce is active
2. Run: cip_sync_woocommerce_products()
3. Check cart settings in WooCommerce
```

**Issue: Emails not sending**
```bash
1. Install WP Mail SMTP plugin
2. Configure email provider
3. Test email delivery
```

**Issue: Notifications not showing**
```bash
1. Run database migration SQL
2. Check error logs
3. Verify user permissions
```

---

## Need Help?

Contact: support@cleanindex.com  
Documentation: docs.cleanindex.com  
Developer: shivanshu@brndguru.com