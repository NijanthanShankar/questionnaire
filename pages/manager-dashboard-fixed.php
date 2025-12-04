<?php
/**
 * CleanIndex Portal - Manager Dashboard (COMPLETE & FIXED)
 * Features: View registrations, download assessments, approve/reject, scoring
 */

if (!defined('ABSPATH')) exit;

// Check if user is manager
if (!current_user_can('manager') && !current_user_can('administrator')) {
    wp_redirect(home_url());
    exit;
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manager_action_nonce'])) {
    if (wp_verify_nonce($_POST['manager_action_nonce'], 'manager_action')) {
        global $wpdb;
        $table = $wpdb->prefix . 'company_registrations';
        $registration_id = intval($_POST['registration_id']);
        
        if (isset($_POST['approve'])) {
            // Recommend approval to admin
            $wpdb->update(
                $table,
                [
                    'status' => 'pending_admin_approval',
                    'manager_notes' => sanitize_textarea_field($_POST['manager_notes'])
                ],
                ['id' => $registration_id]
            );
            
            // Send email to admin
            $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $registration_id), ARRAY_A);
            if ($registration && function_exists('cip_send_admin_approval_notification')) {
                cip_send_admin_approval_notification($registration);
            }
            
            $success_message = "Registration recommended for approval. Admin will review.";
            
        } elseif (isset($_POST['reject'])) {
            $wpdb->update(
                $table,
                [
                    'status' => 'rejected',
                    'manager_notes' => sanitize_textarea_field($_POST['manager_notes'])
                ],
                ['id' => $registration_id]
            );
            
            // Send rejection email
            $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $registration_id), ARRAY_A);
            if ($registration && function_exists('cip_send_rejection_email')) {
                cip_send_rejection_email($registration, $_POST['manager_notes']);
            }
            
            $success_message = "Registration rejected and user notified.";
        }
    }
}

// Get statistics
global $wpdb;
$table = $wpdb->prefix . 'company_registrations';
$table_assessments = $wpdb->prefix . 'company_assessments';

$total_pending = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending_manager_review'");
$total_reviewed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('pending_admin_approval', 'approved', 'rejected')");
$total_approved = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'approved'");
$total_rejected = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'rejected'");

// Get filter
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending_manager_review';

// Build query
$where = "1=1";
if ($status_filter !== 'all') {
    $where .= $wpdb->prepare(" AND status = %s", $status_filter);
}

$registrations = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC", ARRAY_A);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - CleanIndex Portal</title>
    <?php wp_head(); ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border-left: 4px solid;
        }
        
        .stat-card.pending { border-color: #ff9800; }
        .stat-card.reviewed { border-color: #2196F3; }
        .stat-card.approved { border-color: #4CAF50; }
        .stat-card.rejected { border-color: #f44336; }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 20px;
            border-radius: 8px;
            background: #f5f5f5;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .filter-tab:hover {
            background: #e0e0e0;
        }
        
        .filter-tab.active {
            background: #4CAF50;
            color: white;
        }
        
        .registrations-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .registrations-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .registrations-table th {
            background: #f9fafb;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #374151;
        }
        
        .registrations-table td {
            padding: 16px;
            border-top: 1px solid #e5e7eb;
        }
        
        .registrations-table tr:hover {
            background: #f9fafb;
        }
        
        .btn-view {
            padding: 8px 16px;
            background: #4CAF50;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-view:hover {
            background: #45a049;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            overflow-y: auto;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 32px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 32px;
            cursor: pointer;
            color: #666;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .info-item {
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .info-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }
        
        .assessment-section {
            margin-top: 24px;
            padding: 20px;
            background: #f0f9ff;
            border-radius: 8px;
            border: 1px solid #bfdbfe;
        }
        
        .btn-download {
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-download:hover {
            background: #1976D2;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .btn-approve {
            flex: 1;
            padding: 14px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-reject {
            flex: 1;
            padding: 14px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">🌱 CleanIndex Manager</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/manager'); ?>" class="active">📋 Registrations</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url('/cleanindex/manager-login')); ?>">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>
            
            <h1 style="margin-bottom: 24px;">Registration Management</h1>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?php echo $total_pending; ?></div>
                    <div style="color: #666;">Pending Review</div>
                </div>
                <div class="stat-card reviewed">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?php echo $total_reviewed; ?></div>
                    <div style="color: #666;">Reviewed</div>
                </div>
                <div class="stat-card approved">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?php echo $total_approved; ?></div>
                    <div style="color: #666;">Approved</div>
                </div>
                <div class="stat-card rejected">
                    <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?php echo $total_rejected; ?></div>
                    <div style="color: #666;">Rejected</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-tabs">
                <a href="?status=pending_manager_review" class="filter-tab <?php echo $status_filter === 'pending_manager_review' ? 'active' : ''; ?>">
                    Pending Review
                </a>
                <a href="?status=pending_admin_approval" class="filter-tab <?php echo $status_filter === 'pending_admin_approval' ? 'active' : ''; ?>">
                    With Admin
                </a>
                <a href="?status=approved" class="filter-tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                    Approved
                </a>
                <a href="?status=rejected" class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                    Rejected
                </a>
                <a href="?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All
                </a>
            </div>
            
            <!-- Registrations Table -->
            <div class="registrations-table">
                <table>
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Contact Person</th>
                            <th>Industry</th>
                            <th>Country</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registrations)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                    No registrations found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registrations as $reg): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($reg['company_name']); ?></strong></td>
                                    <td><?php echo esc_html($reg['employee_name']); ?></td>
                                    <td><?php echo esc_html($reg['industry']); ?></td>
                                    <td><?php echo esc_html($reg['country']); ?></td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending_manager_review' => '<span style="background: #ff9800; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">Pending Review</span>',
                                            'pending_admin_approval' => '<span style="background: #2196F3; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">With Admin</span>',
                                            'approved' => '<span style="background: #4CAF50; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">Approved</span>',
                                            'rejected' => '<span style="background: #f44336; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">Rejected</span>'
                                        ];
                                        echo $status_badges[$reg['status']] ?? $reg['status'];
                                        ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($reg['created_at'])); ?></td>
                                    <td>
                                        <button onclick="viewRegistration(<?php echo $reg['id']; ?>)" class="btn-view">
                                            👁 View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- View Registration Modal -->
    <div id="registrationModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Registration Details</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div id="modalBody">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
    function viewRegistration(id) {
        document.getElementById('registrationModal').style.display = 'flex';
        document.getElementById('modalBody').innerHTML = '<p style="text-align: center; padding: 40px;">Loading...</p>';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'cip_manager_get_registration_details',
                nonce: '<?php echo wp_create_nonce('manager_action'); ?>',
                registration_id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRegistrationDetails(data.data);
            } else {
                document.getElementById('modalBody').innerHTML = '<p style="color: red;">Error loading details</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('modalBody').innerHTML = '<p style="color: red;">Error loading details</p>';
        });
    }
    
    function displayRegistrationDetails(data) {
        const reg = data.registration;
        const assessment = data.assessment;
        
        let html = `
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Company Name</div>
                    <div class="info-value">${reg.company_name}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Contact Person</div>
                    <div class="info-value">${reg.employee_name}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value">${reg.email}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Organization Type</div>
                    <div class="info-value">${reg.org_type}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Industry</div>
                    <div class="info-value">${reg.industry}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Country</div>
                    <div class="info-value">${reg.country}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Number of Employees</div>
                    <div class="info-value">${reg.num_employees || 'N/A'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Culture</div>
                    <div class="info-value">${reg.culture || 'N/A'}</div>
                </div>
            </div>
            
            ${reg.working_desc ? `
                <div style="margin-top: 20px;">
                    <div class="info-label">Working Description</div>
                    <div style="padding: 12px; background: #f9fafb; border-radius: 8px; margin-top: 8px;">
                        ${reg.working_desc}
                    </div>
                </div>
            ` : ''}
            
            ${assessment ? `
                <div class="assessment-section">
                    <h3 style="margin-top: 0;">📝 Assessment Status</h3>
                    <p><strong>Progress:</strong> ${assessment.progress}/5 steps completed</p>
                    ${assessment.submitted_at ? `<p><strong>Submitted:</strong> ${new Date(assessment.submitted_at).toLocaleDateString()}</p>` : '<p style="color: #ff9800;">Not yet submitted</p>'}
                    ${assessment.submitted_at ? `
                        <a href="<?php echo home_url('/cleanindex/download-assessment-pdf'); ?>?user_id=${reg.id}" class="btn-download" target="_blank">
                            📥 Download Assessment PDF
                        </a>
                    ` : ''}
                </div>
            ` : '<div class="assessment-section"><p style="color: #666;">Assessment not started yet</p></div>'}
            
            ${reg.manager_notes ? `
                <div style="margin-top: 20px;">
                    <div class="info-label">Manager Notes</div>
                    <div style="padding: 12px; background: #fff3cd; border-radius: 8px; margin-top: 8px;">
                        ${reg.manager_notes}
                    </div>
                </div>
            ` : ''}
        `;
        
        // Add action buttons if pending review
        if (reg.status === 'pending_manager_review') {
            html += `
                <form method="POST" style="margin-top: 24px;">
                    <input type="hidden" name="manager_action_nonce" value="<?php echo wp_create_nonce('manager_action'); ?>">
                    <input type="hidden" name="registration_id" value="${reg.id}">
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Manager Notes:</label>
                        <textarea name="manager_notes" rows="4" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;" placeholder="Add your review comments here..."></textarea>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" name="approve" class="btn-approve">✅ Recommend Approval</button>
                        <button type="submit" name="reject" class="btn-reject">❌ Reject</button>
                    </div>
                </form>
            `;
        }
        
        document.getElementById('modalBody').innerHTML = html;
    }
    
    function closeModal() {
        document.getElementById('registrationModal').style.display = 'none';
    }
    
    // Close on outside click
    document.getElementById('registrationModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>