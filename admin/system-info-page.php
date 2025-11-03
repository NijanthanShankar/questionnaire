<?php
/**
 * Admin System Info Page Template
 * 
 * Variables available:
 * - $system_info (array)
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php _e('System Information', 'cleanindex-portal'); ?></h1>
    
    <!-- Plugin Status -->
    <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Plugin Status', 'cleanindex-portal'); ?></h2>
        <table class="form-table">
            <tr>
                <th style="width: 250px;"><?php _e('Plugin Version', 'cleanindex-portal'); ?></th>
                <td><code><?php echo esc_html($system_info['plugin_version']); ?></code></td>
            </tr>
            <tr>
                <th><?php _e('WordPress Version', 'cleanindex-portal'); ?></th>
                <td><code><?php echo esc_html($system_info['wp_version']); ?></code></td>
            </tr>
            <tr>
                <th><?php _e('PHP Version', 'cleanindex-portal'); ?></th>
                <td>
                    <code><?php echo esc_html($system_info['php_version']); ?></code>
                    <?php if (version_compare($system_info['php_version'], '7.4', '<')): ?>
                        <span style="color: #d63638; margin-left: 10px;">⚠️ <?php _e('Upgrade recommended', 'cleanindex-portal'); ?></span>
                    <?php else: ?>
                        <span style="color: #4CAF50; margin-left: 10px;">✓ <?php _e('Compatible', 'cleanindex-portal'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Database Tables', 'cleanindex-portal'); ?></th>
                <td>
                    <?php echo $system_info['table_registrations_exists'] ? '<span style="color: #4CAF50;">✓</span>' : '<span style="color: #d63638;">✗</span>'; ?>
                    <code>wp_company_registrations</code>
                    <?php echo $system_info['table_registrations_exists'] ? '' : '<span style="color: #d63638;"> - ' . __('Missing', 'cleanindex-portal') . '</span>'; ?>
                    <br>
                    <?php echo $system_info['table_assessments_exists'] ? '<span style="color: #4CAF50;">✓</span>' : '<span style="color: #d63638;">✗</span>'; ?>
                    <code>wp_company_assessments</code>
                    <?php echo $system_info['table_assessments_exists'] ? '' : '<span style="color: #d63638;"> - ' . __('Missing', 'cleanindex-portal') . '</span>'; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Upload Directory', 'cleanindex-portal'); ?></th>
                <td>
                    <?php echo $system_info['upload_dir_writable'] ? '<span style="color: #4CAF50;">✓</span>' : '<span style="color: #d63638;">✗</span>'; ?>
                    <code><?php echo esc_html($system_info['upload_dir']); ?></code>
                    <?php if ($system_info['upload_dir_writable']): ?>
                        <span style="color: #4CAF50;"> - <?php _e('Writable', 'cleanindex-portal'); ?></span>
                    <?php else: ?>
                        <span style="color: #d63638;"> - <?php _e('Not Writable', 'cleanindex-portal'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Max Upload Size', 'cleanindex-portal'); ?></th>
                <td><code><?php echo esc_html($system_info['max_upload_size']); ?></code></td>
            </tr>
            <tr>
                <th><?php _e('Post Max Size', 'cleanindex-portal'); ?></th>
                <td><code><?php echo esc_html($system_info['post_max_size']); ?></code></td>
            </tr>
            <tr>
                <th><?php _e('Max Execution Time', 'cleanindex-portal'); ?></th>
                <td><code><?php echo esc_html($system_info['max_execution_time']); ?> <?php _e('seconds', 'cleanindex-portal'); ?></code></td>
            </tr>
            <tr>
                <th><?php _e('Memory Limit', 'cleanindex-portal'); ?></th>
                <td><code><?php echo esc_html($system_info['memory_limit']); ?></code></td>
            </tr>
        </table>
    </div>
    
    <!-- Required Files -->
    <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Required Files', 'cleanindex-portal'); ?></h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px;">
            <?php foreach ($system_info['files'] as $file => $exists): ?>
                <div style="padding: 10px; background: <?php echo $exists ? '#f0f9ff' : '#fff5f5'; ?>; border-left: 3px solid <?php echo $exists ? '#4CAF50' : '#d63638'; ?>; border-radius: 4px;">
                    <?php echo $exists ? '<span style="color: #4CAF50;">✓</span>' : '<span style="color: #d63638;">✗</span>'; ?>
                    <code style="font-size: 12px;"><?php echo esc_html($file); ?></code>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php
        $missing_count = count(array_filter($system_info['files'], function($exists) { return !$exists; }));
        if ($missing_count > 0):
        ?>
            <div style="margin-top: 20px; padding: 15px; background: #fff5f5; border-left: 4px solid #d63638;">
                <strong style="color: #d63638;">⚠️ <?php printf(_n('%d file is missing', '%d files are missing', $missing_count, 'cleanindex-portal'), $missing_count); ?></strong>
                <p><?php _e('Please re-upload the plugin files from a fresh download.', 'cleanindex-portal'); ?></p>
            </div>
        <?php else: ?>
            <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #4CAF50;">
                <strong style="color: #4CAF50;">✓ <?php _e('All required files are present', 'cleanindex-portal'); ?></strong>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Database Statistics -->
    <?php if (isset($system_info['total_registrations'])): ?>
        <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2><?php _e('Database Statistics', 'cleanindex-portal'); ?></h2>
            <table class="form-table">
                <tr>
                    <th style="width: 250px;"><?php _e('Total Registrations', 'cleanindex-portal'); ?></th>
                    <td><strong><?php echo intval($system_info['total_registrations']); ?></strong></td>
                </tr>
                <?php if (!empty($system_info['latest_registration'])): ?>
                    <tr>
                        <th><?php _e('Latest Registration', 'cleanindex-portal'); ?></th>
                        <td>
                            <strong><?php echo esc_html($system_info['latest_registration']['company_name']); ?></strong>
                            (<?php echo date_i18n(get_option('date_format'), strtotime($system_info['latest_registration']['created_at'])); ?>)
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- System Health Check -->
    <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('System Health Check', 'cleanindex-portal'); ?></h2>
        <?php
        $issues = [];
        $warnings = [];
        
        // Check for issues
        if (!$system_info['table_registrations_exists'] || !$system_info['table_assessments_exists']) {
            $issues[] = __('Database tables are missing. Try deactivating and reactivating the plugin.', 'cleanindex-portal');
        }
        
        if (!$system_info['upload_dir_writable']) {
            $issues[] = __('Upload directory is not writable. File uploads will fail.', 'cleanindex-portal');
        }
        
        if ($missing_count > 0) {
            $issues[] = __('Required plugin files are missing. Please re-upload the plugin.', 'cleanindex-portal');
        }
        
        // Check for warnings
        if (version_compare($system_info['php_version'], '7.4', '<')) {
            $warnings[] = __('PHP version is below recommended 7.4. Please upgrade PHP.', 'cleanindex-portal');
        }
        
        $permalink_structure = get_option('permalink_structure');
        if (empty($permalink_structure)) {
            $warnings[] = __('Pretty permalinks are not enabled. Plugin pages may not work correctly.', 'cleanindex-portal');
        }
        
        // Display results
        if (empty($issues) && empty($warnings)):
        ?>
            <div style="padding: 20px; background: #f0f9ff; border-left: 4px solid #4CAF50; border-radius: 4px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 48px;">✓</div>
                    <div>
                        <h3 style="margin: 0; color: #4CAF50;"><?php _e('All Systems Operational', 'cleanindex-portal'); ?></h3>
                        <p style="margin: 5px 0 0 0; color: #666;"><?php _e('No critical issues detected. Your plugin is ready to use.', 'cleanindex-portal'); ?></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php if (!empty($issues)): ?>
                <div style="padding: 20px; background: #fff5f5; border-left: 4px solid #d63638; border-radius: 4px; margin-bottom: 15px;">
                    <h3 style="margin-top: 0; color: #d63638;">❌ <?php _e('Critical Issues', 'cleanindex-portal'); ?></h3>
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($issues as $issue): ?>
                            <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($warnings)): ?>
                <div style="padding: 20px; background: #fff9e6; border-left: 4px solid #ff9800; border-radius: 4px;">
                    <h3 style="margin-top: 0; color: #ff9800;">⚠️ <?php _e('Warnings', 'cleanindex-portal'); ?></h3>
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($warnings as $warning): ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Quick Actions', 'cleanindex-portal'); ?></h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('options-permalink.php'); ?>" class="button">
                <?php _e('Flush Permalinks', 'cleanindex-portal'); ?>
            </a>
            <a href="<?php echo admin_url('plugins.php'); ?>" class="button">
                <?php _e('Manage Plugins', 'cleanindex-portal'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=cleanindex-portal'); ?>" class="button button-primary">
                <?php _e('Plugin Settings', 'cleanindex-portal'); ?>
            </a>
        </div>
    </div>
</div>