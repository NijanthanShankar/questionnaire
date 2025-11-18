<?php
/**
 * CleanIndex Portal - Admin Dashboard (Enhanced)
 * Final approval/rejection of organization registrations with certificate management
 */

if (!defined('ABSPATH')) exit;

// Check admin permissions
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this page.');
}

// Handle approval/rejection
if (isset($_POST['action']) && isset($_POST['cip_admin_nonce'])) {
    if (wp_verify_nonce($_POST['cip_admin_nonce'], 'cip_admin_action')) {
        global $wpdb;
        $table = $wpdb->prefix . 'company_registrations';
        $submission_id = intval($_POST['submission_id']);
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $submission_id), ARRAY_A);
        
        if ($submission) {
            if ($_POST['action'] === 'approve') {
                // Update status
                $wpdb->update(
                    $table,
                    ['status' => 'approved'],
                    ['id' => $submission_id]
                );
                
                // Send approval email
                cip_send_approval_email($submission['email'], $submission['company_name']);
                $success_message = 'Organization approved and notification sent';
                
            } elseif ($_POST['action'] === 'reject') {
                $reason = sanitize_textarea_field($_POST['rejection_reason']);
                
                // Update status
                $wpdb->update(
                    $table,
                    [
                        'status' => 'rejected',
                        'manager_notes' => $reason
                    ],
                    ['id' => $submission_id]
                );
                
                // Send rejection email
                cip_send_rejection_email($submission['email'], $submission['company_name'], $reason);
                $success_message = 'Organization rejected and notification sent';
            }
        }
    }
}

// Get statistics
global $wpdb;
$table = $wpdb->prefix . 'company_registrations';
$table_assessments = $wpdb->prefix . 'company_assessments';

$stats = [
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
    'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('pending_manager_review', 'pending_admin_approval')"),
    'approved' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'approved'"),
    'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'rejected'")
];

// Get all submissions
$submissions = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CleanIndex Portal</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">🌱 CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/admin-portal'); ?>" class="active">📊 Dashboard</a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=cleanindex-portal'); ?>">⚙️ Settings</a></li>
                    <li><a href="<?php echo admin_url(); ?>">🔧 WP Admin</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url()); ?>">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <div class="dashboard-header">
                <h1>Admin Dashboard</h1>
                <div>
                    <span style="color: var(--gray-medium);">Welcome, <?php echo wp_get_current_user()->display_name; ?></span>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success mb-3">
                    ✅ <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--secondary);"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--primary);"><?php echo $stats['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--gray-medium);"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
            
            <!-- Submissions Table -->
            <div class="glass-card">
                <h2 class="mb-3">All Submissions</h2>
                
                <div class="data-table">
                    <?php if (empty($submissions)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <p style="color: var(--gray-medium);">No submissions yet</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Industry</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Manager Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td><?php echo $submission['id']; ?></td>
                                        <td><strong><?php echo esc_html($submission['company_name']); ?></strong></td>
                                        <td>
                                            <?php echo esc_html($submission['employee_name']); ?><br>
                                            <small style="color: var(--gray-medium);"><?php echo esc_html($submission['email']); ?></small>
                                        </td>
                                        <td><?php echo esc_html($submission['industry']); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'pending_manager_review' => 'badge-pending',
                                                'pending_admin_approval' => 'badge-review',
                                                'approved' => 'badge-approved',
                                                'rejected' => 'badge-rejected'
                                            ];
                                            $status_label = [
                                                'pending_manager_review' => 'Manager Review',
                                                'pending_admin_approval' => 'Admin Approval',
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected'
                                            ];
                                            ?>
                                            <span class="badge <?php echo $status_class[$submission['status']]; ?>">
                                                <?php echo $status_label[$submission['status']]; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($submission['created_at'])); ?></td>
                                        <td>
                                            <?php if ($submission['manager_notes']): ?>
                                                <span title="<?php echo esc_attr($submission['manager_notes']); ?>" style="cursor: help;">
                                                    📝 Notes
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--gray-medium);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button onclick="viewSubmission(<?php echo $submission['id']; ?>)" 
                                                    class="btn btn-accent" 
                                                    style="padding: 0.4rem 0.8rem; font-size: 0.875rem;">
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal for Submission Details -->
    <div id="submissionModal" class="modal-overlay" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2>Submission Details</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div id="modalBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
    
    <script>
    function viewSubmission(id) {
        const submissions = <?php echo json_encode($submissions); ?>;
        const submission = submissions.find(s => s.id == id);
        
        if (!submission) return;
        
        const files = JSON.parse(submission.supporting_files || '[]');
        const filesHtml = files.length > 0 
            ? files.map(f => `<a href="${f.url}" target="_blank" class="btn btn-outline" style="display: inline-block; margin: 0.25rem;">${f.filename}</a>`).join('')
            : '<p style="color: var(--gray-medium);">No files uploaded</p>';
        
        const statusBadges = {
            'pending_manager_review': '<span class="badge badge-pending">Manager Review</span>',
            'pending_admin_approval': '<span class="badge badge-review">Admin Approval</span>',
            'approved': '<span class="badge badge-approved">Approved</span>',
            'rejected': '<span class="badge badge-rejected">Rejected</span>'
        };
        
        // Get assessment and subscription status via AJAX
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'cip_get_user_details',
                nonce: '<?php echo wp_create_nonce('cip_admin_action'); ?>',
                user_id: submission.id
            })
        })
        .then(r => r.json())
        .then(data => {
            const assessmentCompleted = data.data?.assessment_completed || false;
            const subscriptionStatus = data.data?.subscription_status || false;
            const hasCertificate = data.data?.has_certificate || false;
            const certificateGrade = data.data?.certificate_grade || '';
            const certificateUrl = data.data?.certificate_url || '';
            
            let modalContent = `
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0;">${submission.company_name}</h3>
                        <p style="color: var(--gray-medium); margin: 0.25rem 0 0 0;">ID: ${submission.id}</p>
                    </div>
                    <div>
                        ${statusBadges[submission.status]}
                    </div>
                </div>
            </div>
            
            <div style="background: rgba(0,0,0,0.03); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <div class="d-flex gap-2" style="flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <strong>Contact Person</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.employee_name}</p>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <strong>Email</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.email}</p>
                    </div>
                </div>
                
                <hr style="margin: 1rem 0; border: none; border-top: 1px solid rgba(0,0,0,0.1);">
                
                <div class="d-flex gap-2" style="flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 150px;">
                        <strong>Organization Type</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.org_type}</p>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <strong>Industry</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.industry}</p>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <strong>Country</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.country}</p>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <strong>Employees</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.num_employees}</p>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <strong>Company Description</strong>
                <p style="background: rgba(0,0,0,0.03); padding: 1rem; border-radius: 8px; margin-top: 0.5rem;">
                    ${submission.working_desc}
                </p>
            </div>
            
            ${submission.culture ? `
                <div style="margin-bottom: 1rem;">
                    <strong>Company Culture</strong>
                    <p style="margin-top: 0.25rem;">${submission.culture}</p>
                </div>
            ` : ''}
            
            <div style="margin-bottom: 1rem;">
                <strong>Supporting Files</strong>
                <div style="margin-top: 0.5rem;">${filesHtml}</div>
            </div>
            
            ${submission.manager_notes ? `
                <div style="margin-bottom: 1rem;">
                    <strong>Manager Notes</strong>
                    <p style="background: rgba(3, 169, 244, 0.1); padding: 1rem; border-radius: 8px; margin-top: 0.5rem; border-left: 4px solid var(--accent);">
                        ${submission.manager_notes}
                    </p>
                </div>
            ` : ''}
            
            <div style="margin-bottom: 1rem;">
                <strong>Progress Status</strong>
                <div style="display: flex; gap: 1rem; margin-top: 0.5rem; flex-wrap: wrap;">
                    <div style="padding: 0.75rem 1rem; background: ${assessmentCompleted ? 'rgba(76, 175, 80, 0.1)' : 'rgba(0,0,0,0.05)'}; border-radius: 8px; border-left: 4px solid ${assessmentCompleted ? 'var(--primary)' : 'var(--gray-medium)'};">
                        <strong>Assessment:</strong> ${assessmentCompleted ? '✓ Completed' : '○ Not Completed'}
                    </div>
                    <div style="padding: 0.75rem 1rem; background: ${subscriptionStatus ? 'rgba(76, 175, 80, 0.1)' : 'rgba(0,0,0,0.05)'}; border-radius: 8px; border-left: 4px solid ${subscriptionStatus ? 'var(--primary)' : 'var(--gray-medium)'};">
                        <strong>Subscription:</strong> ${subscriptionStatus ? '✓ Active' : '○ Inactive'}
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 0.5rem;">
                <small style="color: var(--gray-medium);">Submitted: ${new Date(submission.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</small>
            </div>`;
            
            // Add certificate management section
            if (submission.status === 'approved' && assessmentCompleted && subscriptionStatus) {
                modalContent += `
                <hr style="margin: 1.5rem 0; border: none; border-top: 2px solid var(--gray-light);">
                <h3 style="margin-bottom: 1rem;">🏆 Certificate Management</h3>
                
                ${hasCertificate ? `
                    <div style="background: rgba(76, 175, 80, 0.1); padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem; border-left: 4px solid var(--primary);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 1.1rem;">✓ Certificate Generated</strong><br>
                                <span style="color: var(--gray-medium);">Grade: <strong style="color: var(--primary); font-size: 1.25rem;">${certificateGrade}</strong></span>
                            </div>
                            <a href="${certificateUrl}" target="_blank" class="btn btn-accent" style="padding: 0.75rem 1.5rem;">
                                📄 View Certificate
                            </a>
                        </div>
                    </div>
                ` : ''}
                
                ${!hasCertificate ? `
                    <div style="background: rgba(3, 169, 244, 0.1); padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem; border-left: 4px solid var(--accent);">
                        <p style="margin: 0 0 1rem 0;">No certificate generated yet. Generate a certificate for this organization.</p>
                    </div>
                ` : ''}
                
                ${<?php echo json_encode(get_option('cip_cert_grading_mode') === 'manual'); ?> ? `
                    <form id="cert-generation-form-${submission.id}" style="margin-bottom: 1rem;">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Select Grade</label>
                            <select id="cert-grade-${submission.id}" style="padding: 0.75rem; border: 2px solid var(--gray-light); border-radius: 8px; width: 100%; max-width: 200px; font-size: 1rem;">
                                <option value="ESG">ESG</option>
                                <option value="ESG+">ESG+</option>
                                <option value="ESG++">ESG++</option>
                                <option value="ESG+++">ESG+++</option>
                            </select>
                        </div>
                        <button type="button" onclick="generateCertificate(${submission.id})" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                            ${hasCertificate ? '🔄 Regenerate Certificate' : '🏆 Generate Certificate'}
                        </button>
                    </form>
                ` : `
                    <button type="button" onclick="generateCertificateAuto(${submission.id})" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                        ${hasCertificate ? '🔄 Regenerate Certificate' : '🏆 Generate Certificate (Auto Grade)'}
                    </button>
                `}
            </div>`;
            }
            
            // Add approval/rejection buttons
            if (submission.status === 'pending_admin_approval' || submission.status === 'pending_manager_review') {
                modalContent += `
                <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--gray-light);">
                
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Approve this organization?');">
                        <input type="hidden" name="cip_admin_nonce" value="<?php echo wp_create_nonce('cip_admin_action'); ?>">
                        <input type="hidden" name="submission_id" value="${submission.id}">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem 1.5rem;">
                            ✅ Approve Organization
                        </button>
                    </form>
                    
                    <button onclick="showRejectForm(${submission.id})" class="btn btn-secondary" style="flex: 1; padding: 0.75rem 1.5rem;">
                        ❌ Reject
                    </button>
                </div>`;
            }
            
            document.getElementById('modalBody').innerHTML = modalContent;
        })
        .catch(err => {
            console.error('Error loading user details:', err);
            // Show basic info without status
            document.getElementById('modalBody').innerHTML = `
            <div style="margin-bottom: 1.5rem;">
                <h3 style="margin: 0;">${submission.company_name}</h3>
                <p style="color: var(--gray-medium); margin: 0.25rem 0 0 0;">ID: ${submission.id}</p>
            </div>
            <p style="color: var(--secondary);">Error loading complete details. Please refresh and try again.</p>
            `;
        });
        
        document.getElementById('submissionModal').style.display = 'flex';
    }
    
    function generateCertificate(userId) {
        const grade = document.getElementById('cert-grade-' + userId).value;
        
        if (!confirm('Generate certificate with grade ' + grade + '?')) return;
        
        const button = event.target;
        button.disabled = true;
        button.textContent = '⏳ Generating...';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'cip_generate_certificate_manual',
                nonce: '<?php echo wp_create_nonce('cip_admin_action'); ?>',
                user_id: userId,
                grade: grade
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✓ Certificate generated successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.data || 'Failed to generate certificate'));
                button.disabled = false;
                button.textContent = '🏆 Generate Certificate';
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
            button.disabled = false;
            button.textContent = '🏆 Generate Certificate';
        });
    }
    
    function generateCertificateAuto(userId) {
        if (!confirm('Generate certificate with automatic grading based on assessment score?')) return;
        
        const button = event.target;
        button.disabled = true;
        button.textContent = '⏳ Generating...';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'cip_generate_certificate_manual',
                nonce: '<?php echo wp_create_nonce('cip_admin_action'); ?>',
                user_id: userId,
                grade: 'auto'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✓ Certificate generated successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.data || 'Failed to generate certificate'));
                button.disabled = false;
                button.textContent = '🏆 Generate Certificate (Auto Grade)';
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
            button.disabled = false;
            button.textContent = '🏆 Generate Certificate (Auto Grade)';
        });
    }
    
    function showRejectForm(id) {
        const reason = prompt('Please provide a reason for rejection:');
        if (reason && reason.trim()) {
            if (confirm('Reject this organization and send notification?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="cip_admin_nonce" value="<?php echo wp_create_nonce('cip_admin_action'); ?>">
                    <input type="hidden" name="submission_id" value="${id}">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="rejection_reason" value="${reason}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    }
    
    function closeModal() {
        document.getElementById('submissionModal').style.display = 'none';
    }
    
    // Close modal on outside click
    document.getElementById('submissionModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>

<?php
// Add AJAX handler for getting user details
add_action('wp_ajax_cip_get_user_details', 'cip_ajax_get_user_details');

function cip_ajax_get_user_details() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    $table_assessments = $wpdb->prefix . 'company_assessments';
    
    // Get assessment status
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_assessments WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    $assessment_completed = $assessment && $assessment['progress'] >= 5;
    
    // Get subscription status (looking for WordPress user by matching registration email)
    $table_registrations = $wpdb->prefix . 'company_registrations';
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_registrations WHERE id = %d",
        $user_id
    ), ARRAY_A);
    
    $wp_user = null;
    if ($registration) {
        $wp_user = get_user_by('email', $registration['email']);
    }
    
    $subscription_status = false;
    $has_certificate = false;
    $certificate_grade = '';
    $certificate_url = '';
    
    if ($wp_user) {
        $subscription_status = get_user_meta($wp_user->ID, 'cip_subscription_status', true) === 'active';
        $has_certificate = get_user_meta($wp_user->ID, 'cip_certificate_generated', true);
        $certificate_grade = get_user_meta($wp_user->ID, 'cip_certificate_grade', true);
        $certificate_url = get_user_meta($wp_user->ID, 'cip_certificate_url', true);
    }
    
    wp_send_json_success([
        'assessment_completed' => $assessment_completed,
        'subscription_status' => $subscription_status,
        'has_certificate' => $has_certificate,
        'certificate_grade' => $certificate_grade,
        'certificate_url' => $certificate_url
    ]);
}