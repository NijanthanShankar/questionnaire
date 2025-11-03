<?php
/**
 * CleanIndex Portal - Diagnostic Script
 * 
 * INSTRUCTIONS:
 * 1. Save this file as "cleanindex-diagnostic.php" in your WordPress root directory
 * 2. Access it via: your-site.com/cleanindex-diagnostic.php
 * 3. Review the report and fix any issues
 * 4. DELETE THIS FILE when done (security risk if left)
 */

// Security check - change this to a random string
define('DIAGNOSTIC_KEY', 'change-this-secret-key-12345');

if (!isset($_GET['key']) || $_GET['key'] !== DIAGNOSTIC_KEY) {
    die('Access Denied. Add ?key=' . DIAGNOSTIC_KEY . ' to the URL.');
}

// Load WordPress
require_once('wp-load.php');

// Start output
?>
<!DOCTYPE html>
<html>
<head>
    <title>CleanIndex Portal - Diagnostic Report</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #4CAF50; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: bold; margin-right: 10px; }
        .status.ok { background: #4CAF50; color: white; }
        .status.warning { background: #ff9800; color: white; }
        .status.error { background: #f44336; color: white; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f9f9f9; font-weight: 600; }
        .code { background: #f5f5f5; padding: 15px; border-left: 4px solid #4CAF50; margin: 15px 0; overflow-x: auto; }
        .warning-box { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 15px 0; }
        .error-box { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 CleanIndex Portal - Diagnostic Report</h1>
        <p><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <?php
        $issues = [];
        $warnings = [];
        
        // Check if plugin is active
        if (!defined('CIP_VERSION')) {
            $issues[] = 'Plugin constants not defined - plugin may not be activated properly';
        }
        
        // 1. Check Plugin Files
        echo '<h2>📁 Plugin Files</h2>';
        echo '<table>';
        echo '<tr><th>File</th><th>Status</th><th>Path</th></tr>';
        
        $required_files = [
            'Main Plugin' => 'wp-content/plugins/cleanindex-portal/cleanindex-portal.php',
            'Database Functions' => 'wp-content/plugins/cleanindex-portal/includes/db.php',
            'Roles' => 'wp-content/plugins/cleanindex-portal/includes/roles.php',
            'Authentication' => 'wp-content/plugins/cleanindex-portal/includes/auth.php',
            'Email Handler' => 'wp-content/plugins/cleanindex-portal/includes/email.php',
            'Upload Handler' => 'wp-content/plugins/cleanindex-portal/includes/upload-handler.php',
            'Helpers' => 'wp-content/plugins/cleanindex-portal/includes/helpers.php',
            'Register Page' => 'wp-content/plugins/cleanindex-portal/pages/register.php',
            'Login Page' => 'wp-content/plugins/cleanindex-portal/pages/login.php',
            'Dashboard' => 'wp-content/plugins/cleanindex-portal/pages/user-dashboard.php',
            'Assessment' => 'wp-content/plugins/cleanindex-portal/pages/assessment.php',
            'Stylesheet' => 'wp-content/plugins/cleanindex-portal/css/style.css',
            'JavaScript' => 'wp-content/plugins/cleanindex-portal/js/script.js'
        ];
        
        foreach ($required_files as $name => $file) {
            $full_path = ABSPATH . $file;
            $exists = file_exists($full_path);
            
            echo '<tr>';
            echo '<td>' . $name . '</td>';
            echo '<td>';
            if ($exists) {
                echo '<span class="status ok">✓ EXISTS</span>';
            } else {
                echo '<span class="status error">✗ MISSING</span>';
                $issues[] = "Missing file: $file";
            }
            echo '</td>';
            echo '<td><code>' . $file . '</code></td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // 2. Check Database Tables
        global $wpdb;
        echo '<h2>🗄️ Database Tables</h2>';
        echo '<table>';
        echo '<tr><th>Table</th><th>Status</th><th>Row Count</th></tr>';
        
        $tables = [
            'Registrations' => $wpdb->prefix . 'company_registrations',
            'Assessments' => $wpdb->prefix . 'company_assessments'
        ];
        
        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            echo '<tr>';
            echo '<td>' . $name . '</td>';
            echo '<td>';
            if ($exists) {
                echo '<span class="status ok">✓ EXISTS</span>';
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                echo '</td><td>' . $count . ' rows</td>';
            } else {
                echo '<span class="status error">✗ MISSING</span>';
                echo '</td><td>-</td>';
                $issues[] = "Missing database table: $table";
            }
            echo '</tr>';
        }
        echo '</table>';
        
        // 3. Check Rewrite Rules
        echo '<h2>🔗 URL Rewrite Rules</h2>';
        $rewrite_rules = get_option('rewrite_rules');
        $cip_rules = array_filter($rewrite_rules, function($key) {
            return strpos($key, 'cleanindex') !== false;
        }, ARRAY_FILTER_USE_KEY);
        
        if (empty($cip_rules)) {
            echo '<div class="error-box">';
            echo '<strong>❌ NO REWRITE RULES FOUND</strong><br>';
            echo 'This is why your pages show 404 errors!<br><br>';
            echo '<strong>FIX:</strong> Go to <a href="' . admin_url('options-permalink.php') . '">Settings > Permalinks</a> and click "Save Changes"';
            echo '</div>';
            $issues[] = 'Rewrite rules not registered';
        } else {
            echo '<div class="status ok">✓ RULES REGISTERED</div>';
            echo '<div class="code">';
            foreach ($cip_rules as $pattern => $rewrite) {
                echo htmlspecialchars($pattern) . ' → ' . htmlspecialchars($rewrite) . '<br>';
            }
            echo '</div>';
        }
        
        // 4. Test URLs
        echo '<h2>🌐 Test URLs</h2>';
        echo '<table>';
        echo '<tr><th>Page</th><th>URL</th><th>Action</th></tr>';
        
        $test_urls = [
            'Registration' => home_url('/cleanindex/register'),
            'Login' => home_url('/cleanindex/login'),
            'Dashboard' => home_url('/cleanindex/dashboard'),
            'Assessment' => home_url('/cleanindex/assessment'),
            'Manager Portal' => home_url('/cleanindex/manager'),
            'Admin Portal' => home_url('/cleanindex/admin-portal')
        ];
        
        foreach ($test_urls as $name => $url) {
            echo '<tr>';
            echo '<td>' . $name . '</td>';
            echo '<td><code>' . $url . '</code></td>';
            echo '<td><a href="' . $url . '" target="_blank" class="status ok">Test →</a></td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // 5. Check Upload Directory
        echo '<h2>📤 Upload Directory</h2>';
        $upload_dir = defined('CIP_UPLOAD_DIR') ? CIP_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/cleanindex/';
        
        echo '<table>';
        echo '<tr><th>Check</th><th>Status</th><th>Details</th></tr>';
        
        // Directory exists
        $dir_exists = file_exists($upload_dir);
        echo '<tr>';
        echo '<td>Directory Exists</td>';
        echo '<td>' . ($dir_exists ? '<span class="status ok">✓ YES</span>' : '<span class="status error">✗ NO</span>') . '</td>';
        echo '<td><code>' . $upload_dir . '</code></td>';
        echo '</tr>';
        
        if (!$dir_exists) {
            $issues[] = 'Upload directory does not exist';
        }
        
        // Directory writable
        $is_writable = is_writable($upload_dir);
        echo '<tr>';
        echo '<td>Directory Writable</td>';
        echo '<td>' . ($is_writable ? '<span class="status ok">✓ YES</span>' : '<span class="status error">✗ NO</span>') . '</td>';
        echo '<td>Permissions: ' . (file_exists($upload_dir) ? substr(sprintf('%o', fileperms($upload_dir)), -4) : 'N/A') . '</td>';
        echo '</tr>';
        
        if (!$is_writable && $dir_exists) {
            $issues[] = 'Upload directory is not writable - file uploads will fail';
        }
        
        echo '</table>';
        
        // 6. Check PHP Settings
        echo '<h2>⚙️ PHP Configuration</h2>';
        echo '<table>';
        echo '<tr><th>Setting</th><th>Value</th><th>Status</th></tr>';
        
        $php_checks = [
            'PHP Version' => [PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>=')],
            'upload_max_filesize' => [ini_get('upload_max_filesize'), true],
            'post_max_size' => [ini_get('post_max_size'), true],
            'max_execution_time' => [ini_get('max_execution_time') . ' seconds', true],
            'memory_limit' => [ini_get('memory_limit'), true]
        ];
        
        foreach ($php_checks as $setting => $data) {
            list($value, $ok) = $data;
            echo '<tr>';
            echo '<td>' . $setting . '</td>';
            echo '<td><code>' . $value . '</code></td>';
            echo '<td>' . ($ok ? '<span class="status ok">✓ OK</span>' : '<span class="status warning">⚠ CHECK</span>') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // 7. Check User Roles
        echo '<h2>👥 User Roles</h2>';
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        
        $custom_roles = ['manager', 'organization_admin'];
        echo '<table>';
        echo '<tr><th>Role</th><th>Status</th></tr>';
        
        foreach ($custom_roles as $role) {
            $exists = isset($all_roles[$role]);
            echo '<tr>';
            echo '<td>' . ucwords(str_replace('_', ' ', $role)) . '</td>';
            echo '<td>' . ($exists ? '<span class="status ok">✓ EXISTS</span>' : '<span class="status error">✗ MISSING</span>') . '</td>';
            echo '</tr>';
            
            if (!$exists) {
                $warnings[] = "Custom role '$role' is not registered";
            }
        }
        echo '</table>';
        
        // SUMMARY
        echo '<h2>📋 Summary</h2>';
        
        if (empty($issues)) {
            echo '<div class="status ok" style="padding: 20px; font-size: 18px;">✓ NO CRITICAL ISSUES FOUND</div>';
            
            if (empty($cip_rules)) {
                echo '<div class="warning-box">';
                echo '<h3>⚠️ Action Required: Flush Permalinks</h3>';
                echo '<p>Your pages are probably showing 404 errors. To fix:</p>';
                echo '<ol>';
                echo '<li>Go to <a href="' . admin_url('options-permalink.php') . '"><strong>Settings > Permalinks</strong></a></li>';
                echo '<li>Click <strong>"Save Changes"</strong> (don\'t change anything)</li>';
                echo '<li>Test your pages again</li>';
                echo '</ol>';
                echo '</div>';
            } else {
                echo '<p style="color: #4CAF50; font-size: 16px;">✅ Everything looks good! Your plugin should work correctly.</p>';
            }
        } else {
            echo '<div class="error-box">';
            echo '<h3>❌ Critical Issues Found (' . count($issues) . ')</h3>';
            echo '<ul>';
            foreach ($issues as $issue) {
                echo '<li>' . $issue . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        if (!empty($warnings)) {
            echo '<div class="warning-box">';
            echo '<h3>⚠️ Warnings (' . count($warnings) . ')</h3>';
            echo '<ul>';
            foreach ($warnings as $warning) {
                echo '<li>' . $warning . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        ?>
        
        <h2>🔧 Quick Fixes</h2>
        <div class="code">
            <h3>1. Fix 404 Errors (Permalinks)</h3>
            <p>Go to: <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings > Permalinks</a> → Click "Save Changes"</p>
            
            <h3>2. Recreate Database Tables</h3>
            <p>Go to: <strong>Plugins > Deactivate "CleanIndex Portal"</strong> → Then <strong>Activate</strong> again</p>
            
            <h3>3. Create Upload Directory</h3>
            <p>Run this in your wp-config.php temporarily:</p>
            <pre>
wp_mkdir_p('<?php echo $upload_dir; ?>');
chmod('<?php echo $upload_dir; ?>', 0755);
            </pre>
            
            <h3>4. Test Everything</h3>
            <p>After fixes, test these URLs:</p>
            <ul>
                <li><a href="<?php echo home_url('/cleanindex/register'); ?>" target="_blank">Registration Page</a></li>
                <li><a href="<?php echo home_url('/cleanindex/login'); ?>" target="_blank">Login Page</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=cleanindex-portal'); ?>">Admin Settings</a></li>
            </ul>
        </div>
        
        <div class="warning-box">
            <strong>⚠️ SECURITY WARNING</strong><br>
            Delete this diagnostic file (<code>cleanindex-diagnostic.php</code>) when you're done! It exposes sensitive information.
        </div>
    </div>
</body>
</html>