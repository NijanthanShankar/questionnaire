/**
 * ============================================
 * FILE 7: certificate-view.php (NEW)
 * ============================================
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

$certificate_url = get_user_meta(get_current_user_id(), 'cip_certificate_url', true);
$certificate_grade = get_user_meta(get_current_user_id(), 'cip_certificate_grade', true);
$certificate_number = get_user_meta(get_current_user_id(), 'cip_certificate_number', true);
$subscription_plan = get_user_meta(get_current_user_id(), 'cip_subscription_plan', true);

if (!$certificate_url) {
    wp_redirect(home_url('/cleanindex/dashboard'));
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Certificate - CleanIndex Portal</title>
    <?php wp_head(); ?>
    <style>
        .certificate-preview {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            max-width: 800px;
            margin: 2rem auto;
        }
        .grade-badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Raleway', sans-serif;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
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
            <div class="text-center" style="margin-bottom: 3rem;">
                <h1 style="font-size: 3rem;">🏆 Your ESG Certificate</h1>
                <p style="font-size: 1.25rem; color: var(--gray-medium);">
                    Congratulations on achieving ESG certification!
                </p>
            </div>
            
            <div class="certificate-preview">
                <div class="text-center">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🌱</div>
                    <h2 style="font-size: 2rem; margin-bottom: 2rem;">ESG CERTIFICATE</h2>
                    
                    <div class="grade-badge">
                        <?php echo esc_html($certificate_grade); ?>
                    </div>
                    
                    <h3 style="font-size: 1.75rem; margin: 2rem 0 1rem 0; text-transform: uppercase;">
                        <?php echo esc_html(wp_get_current_user()->display_name); ?>
                    </h3>
                    
                    <p style="color: var(--gray-medium); max-width: 600px; margin: 0 auto 2rem auto; line-height: 1.8;">
                        This certificate confirms that the above organization has successfully completed 
                        the CSRD/ESRS compliant ESG assessment and meets the standards for 
                        <strong><?php echo esc_html($certificate_grade); ?></strong> certification.
                    </p>
                    
                    <div style="padding: 1.5rem; background: rgba(0,0,0,0.03); border-radius: 12px; display: inline-block; margin-bottom: 2rem;">
                        <div style="font-size: 0.875rem; color: var(--gray-medium); margin-bottom: 0.5rem;">
                            Certificate Number
                        </div>
                        <div style="font-family: 'Courier New', monospace; font-size: 1.25rem; font-weight: 700;">
                            <?php echo esc_html($certificate_number); ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 2rem; justify-content: center; margin-bottom: 2rem; flex-wrap: wrap;">
                        <div>
                            <div style="font-size: 0.875rem; color: var(--gray-medium);">Issue Date</div>
                            <div style="font-weight: 600;"><?php echo date('F j, Y'); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: var(--gray-medium);">Valid Until</div>
                            <div style="font-weight: 600;"><?php echo date('F j, Y', strtotime('+1 year')); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: var(--gray-medium);">Plan</div>
                            <div style="font-weight: 600;"><?php echo esc_html($subscription_plan); ?></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <a href="<?php echo esc_url($certificate_url); ?>" download class="btn btn-primary">
                            📥 Download PDF
                        </a>
                        <button onclick="shareCertificate()" class="btn btn-accent">
                            🔗 Share Certificate
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Certificate Badge HTML -->
            <div class="glass-card" style="margin-top: 3rem;">
                <h3>🏅 Display Your Badge</h3>
                <p style="color: var(--gray-medium); margin-bottom: 1rem;">
                    Add this badge to your website to showcase your ESG certification:
                </p>
                <textarea readonly onclick="this.select()" class="form-control" rows="5" style="font-family: 'Courier New', monospace; font-size: 0.875rem;">
<!-- CleanIndex ESG Badge -->
<a href="<?php echo home_url('/cleanindex/verify/' . $certificate_number); ?>" target="_blank" style="display: inline-block;">
    <img src="<?php echo CIP_PLUGIN_URL; ?>assets/badges/<?php echo strtolower(str_replace('+', 'plus', $certificate_grade)); ?>.png" 
         alt="CleanIndex <?php echo esc_attr($certificate_grade); ?> Certified" 
         style="width: 150px; height: auto;">
</a></textarea>
            </div>
        </main>
    </div>
    
    <script>
    function shareCertificate() {
        const shareUrl = '<?php echo home_url('/cleanindex/verify/' . $certificate_number); ?>';
        const shareText = 'We\'re proud to be CleanIndex <?php echo esc_js($certificate_grade); ?> certified!';
        
        if (navigator.share) {
            navigator.share({
                title: 'ESG Certificate',
                text: shareText,
                url: shareUrl
            });
        } else {
            // Fallback - copy to clipboard
            navigator.clipboard.writeText(shareUrl).then(() => {
                alert('Certificate link copied to clipboard!');
            });
        }
    }
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>