<?php
/**
 * CleanIndex Portal - Manager Dashboard (FIXED)
 * Shows all submissions for review with proper debugging
 */

if (!defined('ABSPATH')) exit;

// Check if user has manager permissions
$user = wp_get_current_user();
$is_manager = in_array('manager', $user->roles);
$is_admin = in_array('administrator', $user->roles);

if (!$is_manager && !$is_admin && !current_user_can('manage_cleanindex_submissions') && !current_user_can('review_submissions')) {
    wp_die('You do not have permission to access this page. <br><br><a href="' . home_url() . '">Return Home</a>');
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

// Debug: Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

// Filters
$where = "1=1";
$filter_industry = isset($_GET['industry']) ? sanitize_text_field($_GET['industry']) : '';
$filter_country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

if ($filter_industry) {
    $where .= $wpdb->prepare(" AND industry = %s", $filter_industry);
}
if ($filter_country) {
    $where .= $wpdb->prepare(" AND country = %s", $filter_country);
}
if ($filter_status) {
    $where .= $wpdb->prepare(" AND status = %s", $filter_status);
}

// Get submissions
$submissions = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC", ARRAY_A);

// Debug: Get total count
$total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table");

// Get unique industries and countries for filters
$industries = $wpdb->get_col("SELECT DISTINCT industry FROM $table WHERE industry IS NOT NULL AND industry != ''");
$countries = $wpdb->get_col("SELECT DISTINCT country FROM $table WHERE country IS NOT NULL AND country != ''");

// Get status counts
$status_counts = [
    'pending_manager_review' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending_manager_review'"),
    'pending_admin_approval' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending_admin_approval'"),
    'approved' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'approved'"),
    'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'rejected'")
];
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
            <div class="dashboard-logo">🌱 CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/manager'); ?>" class="active">📊 Submissions</a></li>
                    <?php if ($is_admin): ?>
                        <li><a href="<?php echo home_url('/cleanindex/admin-portal'); ?>">⚙️ Admin Portal</a></li>
                        <li><a href="<?php echo admin_url(); ?>">🔧 WP Admin</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo wp_logout_url(home_url()); ?>">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <div class="dashboard-header">
                <h1>Review Submissions</h1>
                <div>
                    <span style="color: var(--gray-medium);">Welcome, <?php echo esc_html($user->display_name); ?></span>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success mb-3">
                    ✅ <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Debug Info (only show if no submissions found) -->
            <?php if (empty($submissions) && $is_admin): ?>
                <div class="alert alert-info mb-3">
                    <strong>🔍 Debug Information (Admin Only)</strong><br>
                    Table exists: <?php echo $table_exists ? '✓ Yes' : '✗ No'; ?><br>
                    Table name: <code><?php echo esc_html($table); ?></code><br>
                    Total records: <?php echo intval($total_submissions); ?><br>
                    Query: <code><?php echo esc_html("SELECT * FROM $table WHERE $where ORDER BY created_at DESC"); ?></code>
                </div>
            <?php endif; ?>
            
            <!-- Status Cards -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo intval($total_submissions); ?></div>
                    <div class="stat-label">Total Submissions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--secondary);"><?php echo intval($status_counts['pending_manager_review']); ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--accent);"><?php echo intval($status_counts['pending_admin_approval']); ?></div>
                    <div class="stat-label">Recommended</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--primary);"><?php echo intval($status_counts['approved']); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="glass-card mb-3">
                <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap;">
                    <select name="status" class="form-control" style="flex: 1; min-width: 200px;" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending_manager_review" <?php selected($filter_status, 'pending_manager_review'); ?>>
                            Pending Review (<?php echo $status_counts['pending_manager_review']; ?>)
                        </option>
                        <option value="pending_admin_approval" <?php selected($filter_status, 'pending_admin_approval'); ?>>
                            Recommended (<?php echo $status_counts['pending_admin_approval']; ?>)
                        </option>
                        <option value="approved" <?php selected($filter_status, 'approved'); ?>>
                            Approved (<?php echo $status_counts['approved']; ?>)
                        </option>
                        <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>
                            Rejected (<?php echo $status_counts['rejected']; ?>)
                        </option>
                    </select>
                    
                    <?php if (!empty($industries)): ?>
                    <select name="industry" class="form-control" style="flex: 1; min-width: 200px;" onchange="this.form.submit()">
                        <option value="">All Industries</option>
                        <?php foreach ($industries as $industry): ?>
                            <option value="<?php echo esc_attr($industry); ?>" <?php selected($filter_industry, $industry); ?>>
                                <?php echo esc_html($industry); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <?php if (!empty($countries)): ?>
                    <select name="country" class="form-control" style="flex: 1; min-width: 200px;" onchange="this.form.submit()">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo esc_attr($country); ?>" <?php selected($filter_country, $country); ?>>
                                <?php echo esc_html($country); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <a href="<?php echo home_url('/cleanindex/manager'); ?>" class="btn btn-outline">Clear Filters</a>
                </form>
            </div>
            
            <!-- Submissions Table -->
            <div class="glass-card">
                <h2 class="mb-3">Submissions (<?php echo count($submissions); ?>)</h2>
                
                <div class="data-table">
                    <?php if (empty($submissions)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
                            <h3>No Submissions Found</h3>
                            <?php if ($filter_status || $filter_industry || $filter_country): ?>
                                <p style="color: var(--gray-medium);">Try clearing your filters to see more results</p>
                                <a href="<?php echo home_url('/cleanindex/manager'); ?>" class="btn btn-primary" style="margin-top: 1rem;">
                                    Clear All Filters
                                </a>
                            <?php else: ?>
                                <p style="color: var(--gray-medium);">Submissions will appear here when organizations register</p>
                                <?php if ($is_admin && $total_submissions == 0): ?>
                                    <a href="<?php echo home_url('/cleanindex/register'); ?>" class="btn btn-primary" style="margin-top: 1rem;">
                                        Create Test Registration
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Industry</th>
                                    <th>Country</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th style="width: 100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td><?php echo intval($submission['id']); ?></td>
                                        <td><strong><?php echo esc_html($submission['company_name']); ?></strong></td>
                                        <td>
                                            <?php echo esc_html($submission['employee_name']); ?><br>
                                            <small style="color: var(--gray-medium);"><?php echo esc_html($submission['email']); ?></small>
                                        </td>
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
                                            <span class="badge <?php echo $status_class[$submission['status']] ?? 'badge-pending'; ?>">
                                                <?php echo $status_label[$submission['status']] ?? $submission['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($submission['created_at'])); ?></td>
                                        <td>
                                            <button onclick="viewSubmission(<?php echo $submission['id']; ?>)" 
                                                    class="btn btn-accent" 
                                                    style="padding: 0.5rem 1rem; font-size: 0.875rem;">
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
            'pending_manager_review': '<span class="badge badge-pending">Pending Review</span>',
            'pending_admin_approval': '<span class="badge badge-review">Recommended</span>',
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
                    <div>${statusBadges[submission.status]}</div>
                </div>
            </div>
            
            <div style="background: rgba(0,0,0,0.03); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div>
                        <strong>Contact Person</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.employee_name}</p>
                    </div>
                    <div>
                        <strong>Email</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.email}</p>
                    </div>
                    <div>
                        <strong>Organization Type</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.org_type}</p>
                    </div>
                    <div>
                        <strong>Industry</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.industry}</p>
                    </div>
                    <div>
                        <strong>Country</strong>
                        <p style="margin: 0.25rem 0 0 0;">${submission.country}</p>
                    </div>
                    <div>
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
                    <strong>Previous Manager Notes</strong>
                    <p style="background: rgba(3, 169, 244, 0.1); padding: 1rem; border-radius: 8px; margin-top: 0.5rem; border-left: 4px solid var(--accent);">
                        ${submission.manager_notes}
                    </p>
                </div>
            ` : ''}
            
            <div style="margin-bottom: 0.5rem;">
                <small style="color: var(--gray-medium);">Submitted: ${new Date(submission.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</small>
            </div>
            
            ${submission.status === 'pending_manager_review' ? `
                <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--gray-light);">
                <form method="POST" onsubmit="return confirm('Recommend this submission for approval?');">
                    <input type="hidden" name="cip_manager_nonce" value="<?php echo wp_create_nonce('cip_manager_action'); ?>">
                    <input type="hidden" name="submission_id" value="${submission.id}">
                    <input type="hidden" name="action" value="recommend_approval">
                    
                    <div class="form-group">
                        <label class="form-label">Manager Notes (Optional)</label>
                        <textarea name="manager_notes" class="form-control" rows="3" placeholder="Add your review notes..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">✅ Recommend Approval</button>
                        <button type="button" onclick="showInfoRequest(${submission.id})" class="btn btn-secondary" style="flex: 1;">📝 Request More Info</button>
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
        const message = prompt('What additional information do you need from this organization?');
        if (message && message.trim()) {
            if (confirm('Send information request to this organization?')) {
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