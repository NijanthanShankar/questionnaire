<?php
/**
 * CleanIndex Portal - Manager Dashboard
 * Review and manage pending registrations
 */

if (!defined('ABSPATH')) exit;

// Check if user has manager permissions
if (!current_user_can('manage_cleanindex_submissions') && !current_user_can('review_submissions')) {
    wp_die('You do not have permission to access this page.');
}

// Handle AJAX actions
if (isset($_POST['action']) && isset($_POST['cip_manager_nonce'])) {
    if (wp_verify_nonce($_POST['cip_manager_nonce'], 'cip_manager_action')) {
        global $wpdb;
        $table = $wpdb->prefix . 'company_registrations';
        $submission_id = intval($_POST['submission_id']);
        
        if ($_POST['action'] === 'recommend_approval') {
            $notes = sanitize_textarea_field($_POST['manager_notes']);
            $wpdb->update(
                $table,
                [
                    'status' => 'pending_admin_approval',
                    'manager_notes' => $notes
                ],
                ['id' => $submission_id]
            );
            
            $success_message = 'Submission recommended for approval';
        } elseif ($_POST['action'] === 'request_info') {
            $message = sanitize_textarea_field($_POST['info_message']);
            $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $submission_id), ARRAY_A);
            
            if ($submission) {
                cip_send_info_request_email($submission['email'], $submission['company_name'], $message);
                $success_message = 'Information request sent';
            }
        }
    }
}

// Get all submissions
global $wpdb;
$table = $wpdb->prefix . 'company_registrations';

// Filters
$where = "1=1";
$filter_industry = isset($_GET['industry']) ? sanitize_text_field($_GET['industry']) : '';
$filter_country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending_manager_review';

if ($filter_industry) {
    $where .= $wpdb->prepare(" AND industry = %s", $filter_industry);
}
if ($filter_country) {
    $where .= $wpdb->prepare(" AND country = %s", $filter_country);
}
if ($filter_status) {
    $where .= $wpdb->prepare(" AND status = %s", $filter_status);
}

$submissions = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC", ARRAY_A);

// Get unique industries and countries for filters
$industries = $wpdb->get_col("SELECT DISTINCT industry FROM $table");
$countries = $wpdb->get_col("SELECT DISTINCT country FROM $table");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - CleanIndex Portal</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/manager'); ?>" class="active">Submissions</a></li>
                    <li><a href="<?php echo admin_url(); ?>">WP Admin</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url()); ?>">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <div class="dashboard-header">
                <h1>Review Submissions</h1>
                <div>
                    <span class="badge badge-pending"><?php echo count($submissions); ?> Submissions</span>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success mb-3">
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="glass-card mb-3">
                <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap;">
                    <select name="status" class="form-control" style="flex: 1; min-width: 200px;">
                        <option value="">All Statuses</option>
                        <option value="pending_manager_review" <?php selected($filter_status, 'pending_manager_review'); ?>>Pending Review</option>
                        <option value="pending_admin_approval" <?php selected($filter_status, 'pending_admin_approval'); ?>>Recommended</option>
                        <option value="approved" <?php selected($filter_status, 'approved'); ?>>Approved</option>
                        <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>Rejected</option>
                    </select>
                    
                    <select name="industry" class="form-control" style="flex: 1; min-width: 200px;">
                        <option value="">All Industries</option>
                        <?php foreach ($industries as $industry): ?>
                            <option value="<?php echo esc_attr($industry); ?>" <?php selected($filter_industry, $industry); ?>>
                                <?php echo esc_html($industry); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="country" class="form-control" style="flex: 1; min-width: 200px;">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo esc_attr($country); ?>" <?php selected($filter_country, $country); ?>>
                                <?php echo esc_html($country); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="<?php echo home_url('/cleanindex/manager'); ?>" class="btn btn-outline">Clear</a>
                </form>
            </div>
            
            <!-- Submissions Table -->
            <div class="data-table">
                <?php if (empty($submissions)): ?>
                    <div style="padding: 3rem; text-align: center;">
                        <p style="color: var(--gray-medium);">No submissions found</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Contact</th>
                                <th>Industry</th>
                                <th>Country</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Files</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $submission): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($submission['company_name']); ?></strong></td>
                                    <td><?php echo esc_html($submission['employee_name']); ?></td>
                                    <td><?php echo esc_html($submission['industry']); ?></td>
                                    <td><?php echo esc_html($submission['country']); ?></td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'pending_manager_review' => 'badge-pending',
                                            'pending_admin_approval' => 'badge-review',
                                            'approved' => 'badge-approved',
                                            'rejected' => 'badge-rejected'
                                        ];
                                        $status_label = [
                                            'pending_manager_review' => 'Pending Review',
                                            'pending_admin_approval' => 'Recommended',
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
                                        <?php
                                        $files = json_decode($submission['supporting_files'], true);
                                        if ($files && is_array($files)):
                                        ?>
                                            <?php foreach ($files as $file): ?>
                                                <a href="<?php echo esc_url($file['url']); ?>" target="_blank" title="<?php echo esc_attr($file['filename']); ?>">
                                                    📎
                                                </a>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: var(--gray-medium);">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="viewSubmission(<?php echo $submission['id']; ?>)" class="btn btn-accent" style="padding: 0.4rem 0.8rem; font-size: 0.875rem;">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
        
        document.getElementById('modalBody').innerHTML = `
            <div style="margin-bottom: 1rem;">
                <strong>Company:</strong> ${submission.company_name}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Contact Person:</strong> ${submission.employee_name}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Email:</strong> ${submission.email}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Organization Type:</strong> ${submission.org_type}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Industry:</strong> ${submission.industry}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Country:</strong> ${submission.country}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Employees:</strong> ${submission.num_employees}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Company Description:</strong>
                <p style="background: rgba(0,0,0,0.03); padding: 1rem; border-radius: 8px; margin-top: 0.5rem;">
                    ${submission.working_desc}
                </p>
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Supporting Files:</strong>
                <div style="margin-top: 0.5rem;">${filesHtml}</div>
            </div>
            ${submission.manager_notes ? `
                <div style="margin-bottom: 1rem;">
                    <strong>Manager Notes:</strong>
                    <p style="background: rgba(0,0,0,0.03); padding: 1rem; border-radius: 8px; margin-top: 0.5rem;">
                        ${submission.manager_notes}
                    </p>
                </div>
            ` : ''}
            
            ${submission.status === 'pending_manager_review' ? `
                <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--gray-light);">
                <form method="POST" onsubmit="return confirm('Recommend this submission for approval?');">
                    <input type="hidden" name="cip_manager_nonce" value="<?php echo wp_create_nonce('cip_manager_action'); ?>">
                    <input type="hidden" name="submission_id" value="${submission.id}">
                    <input type="hidden" name="action" value="recommend_approval">
                    
                    <div class="form-group">
                        <label class="form-label">Manager Notes</label>
                        <textarea name="manager_notes" class="form-control" rows="3" placeholder="Add your review notes..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">✅ Recommend Approval</button>
                        <button type="button" onclick="showInfoRequest(${submission.id})" class="btn btn-secondary">📝 Request More Info</button>
                    </div>
                </form>
            ` : ''}
        `;
        
        document.getElementById('submissionModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('submissionModal').style.display = 'none';
    }
    
    function showInfoRequest(id) {
        const message = prompt('What additional information do you need?');
        if (message) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="cip_manager_nonce" value="<?php echo wp_create_nonce('cip_manager_action'); ?>">
                <input type="hidden" name="submission_id" value="${id}">
                <input type="hidden" name="action" value="request_info">
                <input type="hidden" name="info_message" value="${message}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
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