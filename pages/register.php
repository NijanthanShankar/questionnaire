<?php
/**
 * CleanIndex Portal - Registration Page
 */

if (!defined('ABSPATH')) exit;

// Handle form submission
$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cip_register_nonce'])) {
    if (!wp_verify_nonce($_POST['cip_register_nonce'], 'cip_register')) {
        $errors[] = 'Security check failed. Please try again.';
    } else {
        global $wpdb;
        
        // Sanitize inputs
        $company_name = sanitize_text_field($_POST['company_name']);
        $employee_name = sanitize_text_field($_POST['employee_name']);
        $org_type = sanitize_text_field($_POST['org_type']);
        $industry = sanitize_text_field($_POST['industry']);
        $country = sanitize_text_field($_POST['country']);
        $working_desc = sanitize_textarea_field($_POST['working_desc']);
        $num_employees = intval($_POST['num_employees']);
        $culture = sanitize_text_field($_POST['culture']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        // Validation
        if (empty($company_name)) $errors[] = 'Company name is required';
        if (empty($employee_name)) $errors[] = 'Contact person name is required';
        if (empty($email) || !is_email($email)) $errors[] = 'Valid email is required';
        if (empty($password) || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
        
        // Check if email exists
        $table = $wpdb->prefix . 'company_registrations';
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));
        
        if ($existing) {
            $errors[] = 'This email is already registered';
        }
        
        // Handle file uploads
        $uploaded_files = [];
        if (isset($_FILES['supporting_files']) && !empty($_FILES['supporting_files']['name'][0])) {
            $files = $_FILES['supporting_files'];
            $file_count = count($files['name']);
            
            for ($i = 0; $i < min($file_count, 3); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_array = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    
                    $result = cip_handle_file_upload($file_array, 'registration');
                    
                    if ($result['success']) {
                        $uploaded_files[] = [
                            'filename' => $result['filename'],
                            'url' => $result['url']
                        ];
                    }
                }
            }
        }
        
        if (empty($errors)) {
            // Insert into database
            $inserted = $wpdb->insert(
                $table,
                [
                    'company_name' => $company_name,
                    'employee_name' => $employee_name,
                    'org_type' => $org_type,
                    'industry' => $industry,
                    'country' => $country,
                    'working_desc' => $working_desc,
                    'num_employees' => $num_employees,
                    'culture' => $culture,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'supporting_files' => json_encode($uploaded_files),
                    'status' => 'pending_manager_review'
                ]
            );
            
            if ($inserted) {
                $success = true;
            } else {
                $errors[] = 'Failed to submit registration. Please try again.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CleanIndex Portal</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="cip-container-narrow">
        <div class="glass-card">
            <div class="text-center mb-4">
                <h1>Join CleanIndex</h1>
                <p>Register your organization for ESG certification</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Success!</strong> Your registration has been submitted and is awaiting review.
                    You will receive an email notification once your account is approved.
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo home_url('/cleanindex/login'); ?>" class="btn btn-primary">
                        Go to Login
                    </a>
                </div>
            <?php else: ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <strong>Please correct the following errors:</strong>
                        <ul style="margin: 0.5rem 0 0 1.5rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="registrationForm">
                    <?php wp_nonce_field('cip_register', 'cip_register_nonce'); ?>
                    
                    <div class="form-group">
                        <label class="form-label">Company Name *</label>
                        <input type="text" name="company_name" class="form-control" required 
                               value="<?php echo isset($_POST['company_name']) ? esc_attr($_POST['company_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contact Person Name *</label>
                        <input type="text" name="employee_name" class="form-control" required
                               value="<?php echo isset($_POST['employee_name']) ? esc_attr($_POST['employee_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Organization Type *</label>
                        <select name="org_type" class="form-control" required>
                            <option value="">Select type...</option>
                            <option value="Company" <?php selected(isset($_POST['org_type']) && $_POST['org_type'] === 'Company'); ?>>Company</option>
                            <option value="Municipality" <?php selected(isset($_POST['org_type']) && $_POST['org_type'] === 'Municipality'); ?>>Municipality</option>
                            <option value="NGO" <?php selected(isset($_POST['org_type']) && $_POST['org_type'] === 'NGO'); ?>>NGO</option>
                            <option value="Other" <?php selected(isset($_POST['org_type']) && $_POST['org_type'] === 'Other'); ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Industry *</label>
                        <select name="industry" class="form-control" required>
                            <option value="">Select industry...</option>
                            <option value="Agriculture">Agriculture</option>
                            <option value="Construction">Construction</option>
                            <option value="Education">Education</option>
                            <option value="Energy">Energy</option>
                            <option value="Finance">Finance</option>
                            <option value="Healthcare">Healthcare</option>
                            <option value="Manufacturing">Manufacturing</option>
                            <option value="Retail">Retail</option>
                            <option value="Technology">Technology</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Country *</label>
                        <select name="country" class="form-control" required>
                            <option value="">Select country...</option>
                            <option value="Netherlands">Netherlands</option>
                            <option value="Belgium">Belgium</option>
                            <option value="Germany">Germany</option>
                            <option value="France">France</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="Spain">Spain</option>
                            <option value="Italy">Italy</option>
                            <option value="Other EU">Other EU Country</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">How is your company working? *</label>
                        <textarea name="working_desc" class="form-control" required 
                                  placeholder="Describe your company's activities, mission, and sustainability initiatives..."><?php echo isset($_POST['working_desc']) ? esc_textarea($_POST['working_desc']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Number of Employees *</label>
                        <input type="number" name="num_employees" class="form-control" required min="1"
                               value="<?php echo isset($_POST['num_employees']) ? esc_attr($_POST['num_employees']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Company Culture</label>
                        <input type="text" name="culture" class="form-control" 
                               placeholder="e.g., Innovative, Collaborative, Customer-focused"
                               value="<?php echo isset($_POST['culture']) ? esc_attr($_POST['culture']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="8"
                               placeholder="At least 8 characters">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Supporting Documents (Optional)</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <p>📎 Drag and drop files here or click to browse</p>
                            <p style="font-size: 0.875rem; color: var(--gray-medium);">
                                PDF, DOC, DOCX only • Max 10MB per file • Up to 3 files
                            </p>
                            <input type="file" name="supporting_files[]" id="fileInput" 
                                   accept=".pdf,.doc,.docx" multiple style="display: none;" 
                                   onchange="handleFileSelect(this.files)">
                        </div>
                        <div id="fileList" class="file-list"></div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Submit Registration
                        </button>
                    </div>
                    
                    <div class="text-center mt-3">
                        <p>Already have an account? 
                            <a href="<?php echo home_url('/cleanindex/login'); ?>" style="color: var(--primary);">
                                Login here
                            </a>
                        </p>
                    </div>
                </form>
                
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // File upload handling
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');
    let selectedFiles = [];
    
    fileUploadArea.addEventListener('click', () => fileInput.click());
    
    fileUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileUploadArea.classList.add('dragover');
    });
    
    fileUploadArea.addEventListener('dragleave', () => {
        fileUploadArea.classList.remove('dragover');
    });
    
    fileUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');
        handleFileSelect(e.dataTransfer.files);
    });
    
    function handleFileSelect(files) {
        const maxFiles = 3;
        const maxSize = 10485760; // 10MB
        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        for (let i = 0; i < files.length && selectedFiles.length < maxFiles; i++) {
            const file = files[i];
            
            if (file.size > maxSize) {
                alert(`${file.name} is too large. Maximum size is 10MB.`);
                continue;
            }
            
            if (!allowedTypes.includes(file.type)) {
                alert(`${file.name} is not a supported file type.`);
                continue;
            }
            
            selectedFiles.push(file);
        }
        
        updateFileList();
    }
    
    function updateFileList() {
        fileList.innerHTML = '';
        
        selectedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <div class="file-item-name">
                    <span>${getFileIcon(file.name)}</span>
                    <span>${file.name}</span>
                    <span style="color: var(--gray-medium); font-size: 0.875rem;">
                        (${formatFileSize(file.size)})
                    </span>
                </div>
                <span class="file-remove" onclick="removeFile(${index})">✕</span>
            `;
            fileList.appendChild(fileItem);
        });
    }
    
    function removeFile(index) {
        selectedFiles.splice(index, 1);
        updateFileList();
    }
    
    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        return ext === 'pdf' ? '📄' : '📝';
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>