<?php
/**
 * CleanIndex Portal - Admin Dashboard
 * Final approval/rejection of organization registrations
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
            <div class="dashboard-logo">CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/admin-portal'); ?>" class="active">Dashboard</a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=cleanindex-portal'); ?>">Settings</a></li>
                    <li><a href="<?php echo admin_url(); ?>">WP Admin</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url()); ?>">Logout</a></li>
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
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
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
        <div class="modal-content">
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
        
        document.getElementById('modalBody').innerHTML = `
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
            
            <div style="margin-bottom: 0.5rem;">
                <small style="color: var(--gray-medium);">Submitted: ${new Date(submission.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</small>
            </div>
            
            ${submission.status === 'pending_admin_approval' || submission.status === 'pending_manager_review' ? `
                <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--gray-light);">
                
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Approve this organization?');">
                        <input type="hidden" name="cip_admin_nonce" value="<?php echo wp_create_nonce('cip_admin_action'); ?>">
                        <input type="hidden" name="submission_id" value="${submission.id}">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            ✅ Approve Organization
                        </button>
                    </form>
                    
                    <button onclick="showRejectForm(${submission.id})" class="btn btn-secondary" style="flex: 1;">
                        ❌ Reject
                    </button>
                </div>
            ` : ''}
        `;
        
        document.getElementById('submissionModal').style.display = 'flex';
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