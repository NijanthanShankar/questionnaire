<?php
/**
 * CleanIndex Portal - Organization Dashboard
 * Dashboard for approved organizations
 *
 * ============================================
 * FILE 1: user-dashboard.php (ENHANCED)
 * ============================================
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

// Handle additional document upload
if (isset($_POST['upload_additional_docs']) && isset($_FILES['additional_files'])) {
    check_admin_referer('cip_upload_docs', 'cip_upload_nonce');
    
    $uploaded_files = json_decode($registration['supporting_files'], true) ?: [];
    
    foreach ($_FILES['additional_files']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['additional_files']['error'][$key] === UPLOAD_ERR_OK) {
            $file_array = [
                'name' => $_FILES['additional_files']['name'][$key],
                'type' => $_FILES['additional_files']['type'][$key],
                'tmp_name' => $tmp_name,
                'error' => $_FILES['additional_files']['error'][$key],
                'size' => $_FILES['additional_files']['size'][$key]
            ];
            
            $result = cip_handle_file_upload($file_array, 'registration');
            
            if ($result['success']) {
                $uploaded_files[] = [
                    'filename' => $result['filename'],
                    'url' => $result['url'],
                    'uploaded_at' => current_time('mysql')
                ];
            }
        }
    }
    
    // Update database
    $wpdb->update(
        $table_registrations,
        ['supporting_files' => json_encode($uploaded_files)],
        ['id' => $registration['id']]
    );
    
    $success_message = 'Documents uploaded successfully!';
    $registration['supporting_files'] = json_encode($uploaded_files);
}

// Get assessment progress
$assessment = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_assessments WHERE user_id = %d",
    $registration['id']
), ARRAY_A);

$assessment_progress = $assessment ? $assessment['progress'] : 0;
$assessment_completed = $assessment && $assessment_progress >= 5;

// Check subscription status
$subscription_status = get_user_meta(get_current_user_id(), 'cip_subscription_status', true);
$has_certificate = get_user_meta(get_current_user_id(), 'cip_certificate_generated', true);

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
        'message' => 'We need more information to process your registration. Please upload the requested documents below.'
    ]
];

$current_status = $status_info[$registration['status']];
$uploaded_files = json_decode($registration['supporting_files'], true) ?: [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CleanIndex Portal</title>
    <?php wp_head(); ?>
    <style>
        .upload-zone {
            border: 2px dashed var(--accent);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: rgba(3, 169, 244, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-zone:hover {
            border-color: var(--primary);
            background: rgba(76, 175, 80, 0.05);
        }
        .doc-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">🌱 CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/dashboard'); ?>" class="active">📊 Dashboard</a></li>
                    <?php if ($registration['status'] === 'approved'): ?>
                        <li><a href="<?php echo home_url('/cleanindex/assessment'); ?>">📝 Assessment</a></li>
                        <?php if ($assessment_completed && !$subscription_status): ?>
                            <li><a href="<?php echo home_url('/cleanindex/pricing'); ?>">💳 Subscription</a></li>
                        <?php endif; ?>
                        <?php if ($has_certificate): ?>
                            <li><a href="<?php echo home_url('/cleanindex/certificate'); ?>">🏆 Certificate</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li><a href="<?php echo wp_logout_url(home_url('/cleanindex/login')); ?>">🚪 Logout</a></li>
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
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Status Alert -->
            <div class="alert <?php echo $registration['status'] === 'approved' ? 'alert-success' : ($registration['status'] === 'rejected' ? 'alert-error' : 'alert-info'); ?>">
                <strong><?php echo $current_status['title']; ?></strong><br>
                <?php echo $current_status['message']; ?>
                
                <?php if ($registration['manager_notes']): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.1);">
                        <strong>📝 Notes from our team:</strong>
                        <p style="margin: 0.5rem 0 0 0;"><?php echo nl2br(esc_html($registration['manager_notes'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Upload Additional Documents (if rejected) -->
            <?php if ($registration['status'] === 'rejected'): ?>
                <div class="glass-card mb-3">
                    <h2 class="mb-3">📎 Upload Additional Documents</h2>
                    <p style="color: var(--gray-medium); margin-bottom: 1.5rem;">
                        Please upload the requested documents to continue with your registration.
                    </p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <?php wp_nonce_field('cip_upload_docs', 'cip_upload_nonce'); ?>
                        
                        <div class="upload-zone" onclick="document.getElementById('additionalFiles').click()">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📁</div>
                            <p style="font-weight: 600; margin-bottom: 0.5rem;">Click to browse or drag files here</p>
                            <p style="font-size: 0.875rem; color: var(--gray-medium);">
                                PDF, DOC, DOCX only • Max 10MB per file
                            </p>
                            <input type="file" id="additionalFiles" name="additional_files[]" multiple 
                                   accept=".pdf,.doc,.docx" style="display: none;" onchange="updateFileList(this.files)">
                        </div>
                        
                        <div id="fileList" style="margin-top: 1rem;"></div>
                        
                        <button type="submit" name="upload_additional_docs" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                            ⬆️ Upload Documents
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $registration['num_employees']; ?></div>
                    <div class="stat-label">Employees</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($uploaded_files); ?></div>
                    <div class="stat-label">Documents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--accent);"><?php echo ($assessment_progress * 20); ?>%</div>
                    <div class="stat-label">Assessment Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: <?php echo $subscription_status ? 'var(--primary)' : 'var(--secondary)'; ?>">
                        <?php echo $subscription_status ? '✓' : '○'; ?>
                    </div>
                    <div class="stat-label">Subscription</div>
                </div>
            </div>
            
            <!-- Supporting Documents -->
            <?php if (!empty($uploaded_files)): ?>
                <div class="glass-card mb-3">
                    <h2 class="mb-3">📄 Supporting Documents</h2>
                    <div>
                        <?php foreach ($uploaded_files as $file): ?>
                            <div class="doc-item">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <span style="font-size: 1.5rem;">
                                        <?php echo cip_get_file_icon($file['filename']); ?>
                                    </span>
                                    <div>
                                        <div style="font-weight: 600;"><?php echo esc_html($file['filename']); ?></div>
                                        <?php if (isset($file['uploaded_at'])): ?>
                                            <div style="font-size: 0.75rem; color: var(--gray-medium);">
                                                Uploaded: <?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="<?php echo esc_url($file['url']); ?>" target="_blank" class="btn btn-accent" style="padding: 0.5rem 1rem;">
                                    Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Assessment Section -->
            <?php if ($registration['status'] === 'approved'): ?>
                <div class="glass-card mb-3">
                    <div class="d-flex justify-between align-center mb-3">
                        <h2 style="margin: 0;">📋 ESG Assessment</h2>
                        <?php if ($assessment_completed): ?>
                            <span class="badge badge-approved">✓ Completed</span>
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
                    
                    <div class="text-center">
                        <a href="<?php echo home_url('/cleanindex/assessment'); ?>" class="btn btn-primary" style="padding: 1rem 2rem;">
                            <?php echo $assessment_completed ? '📄 View Assessment' : ($assessment_progress > 0 ? '▶️ Continue Assessment' : '🚀 Start Assessment'); ?>
                        </a>
                        
                        <?php if ($assessment_completed): ?>
                            <a href="<?php echo home_url('/cleanindex/download-assessment-pdf'); ?>" class="btn btn-accent" style="padding: 1rem 2rem; margin-left: 1rem;">
                                📥 Download PDF
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($assessment_completed && !$subscription_status): ?>
                        <div class="alert alert-info mt-3" style="text-align: center;">
                            <strong>🎉 Assessment Complete!</strong><br>
                            Please select a subscription plan to receive your ESG certificate.
                            <a href="<?php echo home_url('/cleanindex/pricing'); ?>" class="btn btn-primary" style="margin-top: 1rem;">
                                View Pricing Plans →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Certificate Section -->
            <?php if ($has_certificate): ?>
                <div class="glass-card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(3, 169, 244, 0.1));">
                    <div class="text-center">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🏆</div>
                        <h2>Your ESG Certificate is Ready!</h2>
                        <p style="color: var(--gray-medium); margin-bottom: 2rem;">
                            Congratulations on completing your ESG certification journey.
                        </p>
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <a href="<?php echo home_url('/cleanindex/certificate'); ?>" class="btn btn-primary">
                                View Certificate
                            </a>
                            <a href="<?php echo home_url('/cleanindex/download-certificate'); ?>" class="btn btn-accent">
                                Download PDF
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
    function updateFileList(files) {
        const container = document.getElementById('fileList');
        container.innerHTML = '';
        
        Array.from(files).forEach((file, index) => {
            const div = document.createElement('div');
            div.className = 'doc-item';
            div.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.5rem;">📄</span>
                    <div>
                        <div style="font-weight: 600;">${file.name}</div>
                        <div style="font-size: 0.75rem; color: var(--gray-medium);">
                            ${(file.size / 1024 / 1024).toFixed(2)} MB
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(div);
        });
    }
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>