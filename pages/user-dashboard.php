<?php
/**
 * CleanIndex Portal - Organization Dashboard
 * Dashboard for approved organizations
 */

if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

// Get user registration data
$user = wp_get_current_user();
global $wpdb;
$table_registrations = $wpdb->prefix . 'company_registrations';
$table_assessments = $wpdb->prefix . 'company_assessments';

$registration = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_registrations WHERE email = %s",
    $user->user_email
), ARRAY_A);

if (!$registration) {
    wp_die('Registration not found. Please contact support.');
}

// Get assessment progress
$assessment = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_assessments WHERE user_id = %d",
    $registration['id']
), ARRAY_A);

$assessment_progress = $assessment ? $assessment['progress'] : 0;
$assessment_completed = $assessment && $assessment_progress >= 5;

// Status information
$status_info = [
    'pending_manager_review' => [
        'badge' => 'badge-pending',
        'title' => 'Under Review',
        'message' => 'Your registration is being reviewed by our team. You will receive an email notification once approved.'
    ],
    'pending_admin_approval' => [
        'badge' => 'badge-review',
        'title' => 'Final Approval',
        'message' => 'Your registration has been recommended and is awaiting final approval from our administrators.'
    ],
    'approved' => [
        'badge' => 'badge-approved',
        'title' => 'Approved',
        'message' => 'Congratulations! Your organization has been approved. You can now complete your ESG assessment.'
    ],
    'rejected' => [
        'badge' => 'badge-rejected',
        'title' => 'Additional Information Required',
        'message' => 'We need more information to process your registration. Please review the notes below and update your details.'
    ]
];

$current_status = $status_info[$registration['status']];

// Parse uploaded files
$uploaded_files = json_decode($registration['supporting_files'], true) ?: [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CleanIndex Portal</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/dashboard'); ?>" class="active">Dashboard</a></li>
                    <?php if ($registration['status'] === 'approved'): ?>
                        <li><a href="<?php echo home_url('/cleanindex/assessment'); ?>">Assessment</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo wp_logout_url(home_url('/cleanindex/login')); ?>">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <div class="dashboard-header">
                <div>
                    <h1>Welcome, <?php echo esc_html($registration['company_name']); ?></h1>
                    <p style="color: var(--gray-medium); margin: 0;">
                        Contact: <?php echo esc_html($registration['employee_name']); ?>
                    </p>
                </div>
                <div>
                    <span class="badge <?php echo $current_status['badge']; ?>">
                        <?php echo $current_status['title']; ?>
                    </span>
                </div>
            </div>
            
            <!-- Status Alert -->
            <div class="alert <?php echo $registration['status'] === 'approved' ? 'alert-success' : ($registration['status'] === 'rejected' ? 'alert-error' : 'alert-info'); ?>">
                <strong><?php echo $current_status['title']; ?></strong><br>
                <?php echo $current_status['message']; ?>
                
                <?php if ($registration['manager_notes']): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.1);">
                        <strong>Notes from our team:</strong>
                        <p style="margin: 0.5rem 0 0 0;"><?php echo nl2br(esc_html($registration['manager_notes'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Stats -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $registration['num_employees']; ?></div>
                    <div class="stat-label">Employees</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($uploaded_files); ?></div>
                    <div class="stat-label">Documents Uploaded</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--accent);"><?php echo ($assessment_progress * 20); ?>%</div>
                    <div class="stat-label">Assessment Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--secondary);">
                        <?php echo date('M d, Y', strtotime($registration['created_at'])); ?>
                    </div>
                    <div class="stat-label">Registered On</div>
                </div>
            </div>
            
            <!-- Organization Details -->
            <div class="glass-card mb-3">
                <h2 class="mb-3">Organization Details</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div>
                        <strong>Organization Type</strong>
                        <p style="margin: 0.25rem 0 0 0;"><?php echo esc_html($registration['org_type']); ?></p>
                    </div>
                    <div>
                        <strong>Industry</strong>
                        <p style="margin: 0.25rem 0 0 0;"><?php echo esc_html($registration['industry']); ?></p>
                    </div>
                    <div>
                        <strong>Country</strong>
                        <p style="margin: 0.25rem 0 0 0;"><?php echo esc_html($registration['country']); ?></p>
                    </div>
                    <?php if ($registration['culture']): ?>
                        <div>
                            <strong>Company Culture</strong>
                            <p style="margin: 0.25rem 0 0 0;"><?php echo esc_html($registration['culture']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($registration['working_desc']): ?>
                    <div style="margin-top: 1.5rem;">
                        <strong>Company Description</strong>
                        <p style="background: rgba(0,0,0,0.03); padding: 1rem; border-radius: 8px; margin-top: 0.5rem;">
                            <?php echo nl2br(esc_html($registration['working_desc'])); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Uploaded Documents -->
            <?php if (!empty($uploaded_files)): ?>
                <div class="glass-card mb-3">
                    <h2 class="mb-3">Supporting Documents</h2>
                    <div class="file-list">
                        <?php foreach ($uploaded_files as $file): ?>
                            <div class="file-item">
                                <div class="file-item-name">
                                    <span><?php echo cip_get_file_icon($file['filename']); ?></span>
                                    <span><?php echo esc_html($file['filename']); ?></span>
                                </div>
                                <a href="<?php echo esc_url($file['url']); ?>" target="_blank" class="btn btn-accent" style="padding: 0.4rem 0.8rem; font-size: 0.875rem;">
                                    Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Assessment Section -->
            <?php if ($registration['status'] === 'approved'): ?>
                <div class="glass-card">
                    <div class="d-flex justify-between align-center mb-3">
                        <h2 style="margin: 0;">ESG Assessment</h2>
                        <?php if ($assessment_completed): ?>
                            <span class="badge badge-approved">Completed</span>
                        <?php else: ?>
                            <span class="badge badge-review">In Progress</span>
                        <?php endif; ?>
                    </div>
                    
                    <p style="color: var(--gray-medium); margin-bottom: 1.5rem;">
                        Complete the CSRD/ESRS compliant questionnaire to receive your ESG certification.
                    </p>
                    
                    <!-- Progress Bar -->
                    <div style="margin-bottom: 1.5rem;">
                        <div class="d-flex justify-between align-center mb-1">
                            <span style="font-weight: 600;">Progress</span>
                            <span style="color: var(--accent);"><?php echo ($assessment_progress * 20); ?>% Complete</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($assessment_progress * 20); ?>%;"></div>
                        </div>
                    </div>
                    
                    <!-- Assessment Steps -->
                    <div style="background: rgba(0,0,0,0.03); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                        <h4 style="margin-bottom: 1rem;">Assessment Sections:</h4>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                <?php echo $assessment_progress >= 1 ? '✅' : '⭕'; ?> 
                                <strong>Step 1:</strong> General Requirements & Materiality Analysis
                            </li>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                <?php echo $assessment_progress >= 2 ? '✅' : '⭕'; ?> 
                                <strong>Step 2:</strong> Company Profile & Governance
                            </li>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                <?php echo $assessment_progress >= 3 ? '✅' : '⭕'; ?> 
                                <strong>Step 3:</strong> Strategy & Risk Management
                            </li>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                <?php echo $assessment_progress >= 4 ? '✅' : '⭕'; ?> 
                                <strong>Step 4:</strong> Environment (E1-E5)
                            </li>
                            <li style="padding: 0.5rem 0;">
                                <?php echo $assessment_progress >= 5 ? '✅' : '⭕'; ?> 
                                <strong>Step 5:</strong> Social & Metrics (S1-S4)
                            </li>
                        </ul>
                    </div>
                    
                    <div class="text-center">
                        <a href="<?php echo home_url('/cleanindex/assessment'); ?>" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                            <?php echo $assessment_completed ? 'Review Assessment' : ($assessment_progress > 0 ? 'Continue Assessment' : 'Start Assessment'); ?>
                        </a>
                    </div>
                    
                    <?php if ($assessment_completed): ?>
                        <div class="alert alert-success mt-3" style="text-align: center;">
                            <strong>🎉 Assessment Complete!</strong><br>
                            Our team is reviewing your submission. You will receive your ESG certificate once approved.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Help Section -->
            <div class="glass-card mt-3" style="background: rgba(3, 169, 244, 0.05);">
                <h3>Need Help?</h3>
                <p style="margin-bottom: 1rem;">
                    If you have any questions or need assistance with your registration or assessment, 
                    our support team is here to help.
                </p>
                <div class="d-flex gap-2">
                    <a href="mailto:support@cleanindex.com" class="btn btn-accent">
                        📧 Contact Support
                    </a>
                    <a href="https://docs.cleanindex.com" target="_blank" class="btn btn-outline">
                        📚 Documentation
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>