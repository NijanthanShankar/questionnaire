<?php
/**
 * Admin Submissions Page Template
 * 
 * Variables available:
 * - $submissions (array)
 * - $status_filter (string)
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php _e('Submissions Overview', 'cleanindex-portal'); ?></h1>
    
    <!-- Filter -->
    <div style="background: #fff; padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="page" value="cleanindex-submissions">
            <label for="status"><?php _e('Filter by Status:', 'cleanindex-portal'); ?></label>
            <select name="status" id="status" onchange="this.form.submit()">
                <option value="all" <?php selected($status_filter, 'all'); ?>><?php _e('All', 'cleanindex-portal'); ?></option>
                <option value="pending_manager_review" <?php selected($status_filter, 'pending_manager_review'); ?>><?php _e('Pending Manager Review', 'cleanindex-portal'); ?></option>
                <option value="pending_admin_approval" <?php selected($status_filter, 'pending_admin_approval'); ?>><?php _e('Pending Admin Approval', 'cleanindex-portal'); ?></option>
                <option value="approved" <?php selected($status_filter, 'approved'); ?>><?php _e('Approved', 'cleanindex-portal'); ?></option>
                <option value="rejected" <?php selected($status_filter, 'rejected'); ?>><?php _e('Rejected', 'cleanindex-portal'); ?></option>
            </select>
            <noscript>
                <button type="submit" class="button"><?php _e('Filter', 'cleanindex-portal'); ?></button>
            </noscript>
        </form>
    </div>
    
    <!-- Submissions Table -->
    <div style="background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow-x: auto;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 60px;"><?php _e('ID', 'cleanindex-portal'); ?></th>
                    <th><?php _e('Company', 'cleanindex-portal'); ?></th>
                    <th><?php _e('Contact', 'cleanindex-portal'); ?></th>
                    <th><?php _e('Email', 'cleanindex-portal'); ?></th>
                    <th><?php _e('Industry', 'cleanindex-portal'); ?></th>
                    <th><?php _e('Status', 'cleanindex-portal'); ?></th>
                    <th><?php _e('Registered', 'cleanindex-portal'); ?></th>
                    <th><?php _e('Actions', 'cleanindex-portal'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                            <div style="font-size: 48px; margin-bottom: 10px;">📋</div>
                            <strong><?php _e('No submissions found', 'cleanindex-portal'); ?></strong>
                            <p><?php _e('Submissions will appear here when organizations register.', 'cleanindex-portal'); ?></p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td><?php echo intval($sub['id']); ?></td>
                            <td><strong><?php echo esc_html($sub['company_name']); ?></strong></td>
                            <td><?php echo esc_html($sub['employee_name']); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($sub['email']); ?>"><?php echo esc_html($sub['email']); ?></a></td>
                            <td><?php echo esc_html($sub['industry']); ?></td>
                            <td>
                                <?php
                                $status_colors = [
                                    'pending_manager_review' => '#EB5E28',
                                    'pending_admin_approval' => '#03A9F4',
                                    'approved' => '#4CAF50',
                                    'rejected' => '#999'
                                ];
                                $status_labels = [
                                    'pending_manager_review' => __('Pending Manager Review', 'cleanindex-portal'),
                                    'pending_admin_approval' => __('Pending Admin Approval', 'cleanindex-portal'),
                                    'approved' => __('Approved', 'cleanindex-portal'),
                                    'rejected' => __('Rejected', 'cleanindex-portal')
                                ];
                                $color = $status_colors[$sub['status']] ?? '#666';
                                $label = $status_labels[$sub['status']] ?? $sub['status'];
                                ?>
                                <span style="background: <?php echo esc_attr($color); ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block;">
                                    <?php echo esc_html($label); ?>
                                </span>
                            </td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($sub['created_at'])); ?></td>
                            <td>
                                <a href="<?php echo home_url('/cleanindex/admin-portal'); ?>" class="button button-small" target="_blank" title="<?php _e('View Details', 'cleanindex-portal'); ?>">
                                    <?php _e('View', 'cleanindex-portal'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (!empty($submissions)): ?>
        <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #4CAF50;">
            <p style="margin: 0;">
                <strong><?php _e('Tip:', 'cleanindex-portal'); ?></strong>
                <?php _e('To review and approve submissions, visit the', 'cleanindex-portal'); ?>
                <a href="<?php echo home_url('/cleanindex/admin-portal'); ?>" target="_blank"><?php _e('Admin Portal', 'cleanindex-portal'); ?></a>.
            </p>
        </div>
    <?php endif; ?>
</div>