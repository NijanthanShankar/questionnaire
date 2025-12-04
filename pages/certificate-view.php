<?php
/**
 * CleanIndex Portal - Certificate View Page
 * Users land here after successful payment to view/download their certificate
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

$current_user = wp_get_current_user();
global $wpdb;

// Get user's registration
$table_reg = $wpdb->prefix . 'company_registrations';
$registration = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_reg WHERE email = %s OR wordpress_user_id = %d",
    $current_user->user_email,
    $current_user->ID
), ARRAY_A);

if (!$registration) {
    wp_redirect(home_url('/cleanindex/dashboard'));
    exit;
}

// Get certificate
$table_certs = $wpdb->prefix . 'cip_certificates';
$certificate = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_certs WHERE user_id = %d ORDER BY issued_date DESC LIMIT 1",
    $registration['id']
), ARRAY_A);

// If no certificate, check if payment was just made
if (!$certificate) {
    // Try to get from order
    if (isset($_GET['order'])) {
        $order_id = intval($_GET['order']);
        $cert_url = get_post_meta($order_id, '_cip_certificate_url', true);
        $cert_number = get_post_meta($order_id, '_cip_certificate_number', true);
        
        if ($cert_url && $cert_number) {
            // Refresh page to load from database
            echo '<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your ESG Certificate - CleanIndex Portal</title>
    <?php wp_head(); ?>
    <style>
        .certificate-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .congratulations {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .congratulations h1 {
            font-size: 36px;
            color: #4CAF50;
            margin-bottom: 16px;
        }
        
        .certificate-preview {
            border: 4px solid #4CAF50;
            border-radius: 12px;
            padding: 40px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            text-align: center;
            margin: 30px 0;
            position: relative;
        }
        
        .badge {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .cert-title {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .company-name {
            font-size: 28px;
            color: #4CAF50;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .grade-display {
            font-size: 48px;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .cert-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        
        .detail-item {
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            text-align: left;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }
        
        .detail-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 16px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 16px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
        }
        
        .btn-secondary {
            background: #2196F3;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #1976D2;
        }
        
        .loading {
            text-align: center;
            padding: 60px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .share-section {
            margin-top: 40px;
            padding: 20px;
            background: #f0f9ff;
            border-radius: 8px;
            text-align: center;
        }
        
        .share-link {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .share-link input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .copy-btn {
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
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
                    <li><a href="<?php echo home_url('/cleanindex/certificate'); ?>" class="active">🏆 Certificate</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url('/cleanindex/login')); ?>">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <?php if (!$certificate): ?>
                <!-- Loading or No Certificate State -->
                <div class="certificate-container">
                    <div class="loading">
                        <div class="spinner"></div>
                        <h2>Generating Your Certificate...</h2>
                        <p>Please wait while we prepare your ESG certificate.</p>
                        <p style="margin-top: 20px; color: #666;">This usually takes just a few seconds.</p>
                    </div>
                </div>
                <script>
                // Reload page after 3 seconds to check for certificate
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
                </script>
            <?php else: ?>
                <!-- Certificate Display -->
                <div class="certificate-container">
                    <div class="congratulations">
                        <h1>🎉 Congratulations!</h1>
                        <p style="font-size: 18px; color: #666;">
                            You have successfully earned your ESG Certificate
                        </p>
                    </div>
                    
                    <div class="certificate-preview">
                        <?php
                        $badge_emoji = [
                            'gold' => '🥇',
                            'silver' => '🥈',
                            'bronze' => '🥉',
                            'blue' => '🔵',
                            'green' => '🟢'
                        ];
                        $emoji = $badge_emoji[$certificate['badge_type']] ?? '🏆';
                        ?>
                        <div class="badge"><?php echo $emoji; ?></div>
                        <div class="cert-title">CERTIFICATE OF ACHIEVEMENT</div>
                        <p style="margin: 10px 0;">This is to certify that</p>
                        <div class="company-name"><?php echo esc_html(strtoupper($registration['company_name'])); ?></div>
                        <p style="margin: 10px 0;">has successfully completed the ESG Assessment</p>
                        <p style="margin: 10px 0;">and achieved the following grade:</p>
                        <div class="grade-display" style="color: <?php echo cip_get_grade_color_hex($certificate['grade']); ?>">
                            GRADE: <?php echo esc_html($certificate['grade']); ?> (<?php echo esc_html($certificate['score']); ?>/100)
                        </div>
                    </div>
                    
                    <div class="cert-details">
                        <div class="detail-item">
                            <div class="detail-label">Certificate Number</div>
                            <div class="detail-value"><?php echo esc_html($certificate['certificate_number']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Issue Date</div>
                            <div class="detail-value"><?php echo date('F j, Y', strtotime($certificate['issued_date'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Company</div>
                            <div class="detail-value"><?php echo esc_html($registration['company_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Grade Achieved</div>
                            <div class="detail-value">
                                <?php 
                                $grade_details = cip_get_grade_details($certificate['grade']);
                                echo esc_html($grade_details['name']);
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="<?php echo esc_url($certificate['pdf_url']); ?>" class="btn btn-primary" download>
                            📥 Download Certificate (PDF)
                        </a>
                        <a href="<?php echo esc_url($certificate['pdf_url']); ?>" class="btn btn-secondary" target="_blank">
                            👁 View Full Certificate
                        </a>
                    </div>
                    
                    <div class="share-section">
                        <h3 style="margin-top: 0;">Share Your Achievement</h3>
                        <p>Share your certificate link:</p>
                        <div class="share-link">
                            <input type="text" id="certLink" value="<?php echo esc_url($certificate['pdf_url']); ?>" readonly>
                            <button class="copy-btn" onclick="copyCertLink()">Copy Link</button>
                        </div>
                    </div>
                    
                    <?php
                    // Show recommendations based on grade
                    $recommendations = cip_get_score_recommendations($certificate['score'], $certificate['grade']);
                    if (!empty($recommendations)):
                    ?>
                        <div style="margin-top: 40px; padding: 24px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
                            <h3 style="margin-top: 0;">💡 Recommendations for Improvement</h3>
                            <ul style="margin: 0; padding-left: 20px;">
                                <?php foreach ($recommendations as $recommendation): ?>
                                    <li style="margin: 8px 0;"><?php echo esc_html($recommendation); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
    function copyCertLink() {
        var copyText = document.getElementById("certLink");
        copyText.select();
        copyText.setSelectionRange(0, 99999); // For mobile devices
        document.execCommand("copy");
        
        var btn = event.target;
        var originalText = btn.textContent;
        btn.textContent = "✓ Copied!";
        btn.style.background = "#4CAF50";
        
        setTimeout(function() {
            btn.textContent = originalText;
            btn.style.background = "#4CAF50";
        }, 2000);
    }
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>