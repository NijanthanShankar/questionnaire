<?php
/**
 * Enhanced Questions Manager - Add Steps & File Upload Toggle
 * REPLACE: admin/questions-manager-enhanced.php
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Get current questions
$questions = get_option('cip_assessment_questions', cip_get_default_questions());

// Handle ADD NEW STEP
if (isset($_POST['add_new_step'])) {
    check_admin_referer('cip_add_step', 'cip_add_step_nonce');
    
    $new_step_number = intval($_POST['new_step_number']);
    $new_step_title = sanitize_text_field($_POST['new_step_title']);
    
    if ($new_step_number > 0 && !empty($new_step_title)) {
        $questions[$new_step_number] = [
            'title' => $new_step_title,
            'questions' => []
        ];
        
        // Sort by step number
        ksort($questions);
        
        update_option('cip_assessment_questions', $questions);
        echo '<div class="notice notice-success"><p>✅ New step added successfully!</p></div>';
        $questions = get_option('cip_assessment_questions');
    }
}

// Handle SAVE questions
if (isset($_POST['save_questions'])) {
    check_admin_referer('cip_save_questions', 'cip_questions_nonce');
    
    $new_questions = [];
    
    if (isset($_POST['questions']) && is_array($_POST['questions'])) {
        foreach ($_POST['questions'] as $step => $step_data) {
            $step = intval($step);
            
            $new_questions[$step] = [
                'title' => isset($step_data['title']) ? sanitize_text_field($step_data['title']) : 'Step ' . $step,
                'questions' => []
            ];
            
            if (isset($step_data['questions']) && is_array($step_data['questions'])) {
                foreach ($step_data['questions'] as $q_id => $question_data) {
                    $q_id = sanitize_text_field($q_id);
                    
                    if (is_array($question_data)) {
                        $question_text = sanitize_textarea_field($question_data['text']);
                        $allow_file_upload = !empty($question_data['allow_file_upload']);
                    } else {
                        $question_text = sanitize_textarea_field($question_data);
                        $allow_file_upload = false;
                    }
                    
                    if (!empty($question_text)) {
                        $new_questions[$step]['questions'][$q_id] = [
                            'text' => $question_text,
                            'allow_file_upload' => $allow_file_upload
                        ];
                    }
                }
            }
        }
    }
    
    ksort($new_questions);
    update_option('cip_assessment_questions', $new_questions);
    $questions = $new_questions;
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Questions saved successfully!</strong></p></div>';
}

// Handle DELETE question
if (isset($_POST['delete_question'])) {
    check_admin_referer('cip_delete_question', 'cip_delete_nonce');
    
    $step = intval($_POST['step']);
    $q_id = sanitize_text_field($_POST['q_id']);
    
    if (isset($questions[$step]['questions'][$q_id])) {
        unset($questions[$step]['questions'][$q_id]);
        update_option('cip_assessment_questions', $questions);
        echo '<div class="notice notice-success"><p>✅ Question deleted!</p></div>';
    }
}

// Handle DELETE step
if (isset($_POST['delete_step'])) {
    check_admin_referer('cip_delete_step_action', 'cip_delete_step_nonce');
    
    $step = intval($_POST['step_to_delete']);
    
    if (isset($questions[$step])) {
        unset($questions[$step]);
        ksort($questions);
        update_option('cip_assessment_questions', $questions);
        echo '<div class="notice notice-success"><p>✅ Step deleted successfully!</p></div>';
    }
}

// Handle ADD new question
if (isset($_POST['add_question'])) {
    check_admin_referer('cip_add_question', 'cip_add_nonce');
    
    $step = intval($_POST['new_step']);
    $question_text = sanitize_textarea_field($_POST['new_question_text']);
    $allow_file_upload = !empty($_POST['new_question_file_upload']);
    
    if (!empty($question_text)) {
        $existing_ids = isset($questions[$step]['questions']) ? array_keys($questions[$step]['questions']) : [];
        $max_num = 0;
        
        foreach ($existing_ids as $id) {
            if (preg_match('/^q' . $step . '_(\d+)$/', $id, $matches)) {
                $max_num = max($max_num, intval($matches[1]));
            }
        }
        
        $new_q_id = 'q' . $step . '_' . ($max_num + 1);
        
        if (!isset($questions[$step])) {
            $questions[$step] = [
                'title' => 'Step ' . $step,
                'questions' => []
            ];
        }
        
        $questions[$step]['questions'][$new_q_id] = [
            'text' => $question_text,
            'allow_file_upload' => $allow_file_upload
        ];
        
        update_option('cip_assessment_questions', $questions);
        echo '<div class="notice notice-success"><p>✅ Question added!</p></div>';
    }
}

// Get total question count
$total_questions = 0;
foreach ($questions as $step_data) {
    if (isset($step_data['questions'])) {
        $total_questions += count($step_data['questions']);
    }
}

?>

<div class="wrap">
    <h1 class="wp-heading-inline">📝 Assessment Questions Manager</h1>
    
    <!-- Stats Cards -->
    <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <div style="font-size: 32px; font-weight: 700; color: #4CAF50;"><?php echo count($questions); ?></div>
                <div style="color: #666; font-size: 14px;">Total Steps</div>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <div style="font-size: 32px; font-weight: 700; color: #03A9F4;"><?php echo $total_questions; ?></div>
                <div style="color: #666; font-size: 14px;">Total Questions</div>
            </div>
        </div>
    </div>
    
    <!-- ADD NEW STEP SECTION -->
    <div style="background: #e3f2fd; border: 2px solid #03A9F4; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <h2 style="margin-top: 0; color: #03A9F4;">➕ Add New Step</h2>
        
        <form method="POST" style="max-width: 600px;">
            <?php wp_nonce_field('cip_add_step', 'cip_add_step_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="new_step_number">Step Number</label>
                    </th>
                    <td>
                        <input type="number" name="new_step_number" id="new_step_number" 
                               value="<?php echo count($questions) + 1; ?>" 
                               min="1" style="width: 100px;" required>
                        <p class="description">Recommended: <?php echo count($questions) + 1; ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="new_step_title">Step Title</label>
                    </th>
                    <td>
                        <input type="text" name="new_step_title" id="new_step_title" 
                               class="regular-text" placeholder="e.g., Compliance & Regulations" required>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="add_new_step" class="button button-primary">
                    ➕ Create New Step
                </button>
            </p>
        </form>
    </div>
    
    <!-- ADD NEW QUESTION -->
    <div style="background: #f0f9ff; border: 2px solid #03A9F4; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <h2 style="margin-top: 0; color: #03A9F4;">➕ Add New Question</h2>
        
        <form method="POST" style="max-width: 800px;">
            <?php wp_nonce_field('cip_add_question', 'cip_add_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="new_step">Step Number</label>
                    </th>
                    <td>
                        <select name="new_step" id="new_step" style="width: 200px;" required>
                            <?php foreach ($questions as $step => $step_data): ?>
                                <option value="<?php echo $step; ?>">
                                    Step <?php echo $step; ?>: <?php echo esc_html($step_data['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="new_question_text">Question Text</label>
                    </th>
                    <td>
                        <textarea name="new_question_text" id="new_question_text" rows="3" 
                                  class="large-text" placeholder="Enter your question here..." required></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="new_question_file_upload">Allow File Upload</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="new_question_file_upload" id="new_question_file_upload" value="1">
                            Enable file upload for this question
                        </label>
                        <p class="description">Users can attach evidence documents (PDF, DOC, DOCX)</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="add_question" class="button button-primary">
                    ➕ Add Question
                </button>
            </p>
        </form>
    </div>
    
    <!-- EDIT EXISTING QUESTIONS -->
    <form method="POST" id="questionsForm">
        <?php wp_nonce_field('cip_save_questions', 'cip_questions_nonce'); ?>
        
        <?php if (empty($questions)): ?>
            <div style="background: white; padding: 40px; margin: 20px 0; text-align: center; border-radius: 8px;">
                <div style="font-size: 64px; margin-bottom: 20px;">📝</div>
                <h3>No Steps Found</h3>
                <p style="color: #666;">Use the form above to add your first step.</p>
            </div>
        <?php else: ?>
            
            <?php foreach ($questions as $step => $step_data): ?>
                <div style="background: white; padding: 25px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <!-- Step Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #4CAF50;">
                        <div style="flex: 1;">
                            <input type="text" 
                                   name="questions[<?php echo $step; ?>][title]" 
                                   value="<?php echo esc_attr($step_data['title']); ?>" 
                                   style="font-size: 20px; font-weight: 600; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; width: 100%; max-width: 500px;"
                                   placeholder="Step Title">
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="badge" style="background: #4CAF50; color: white; padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                                <?php echo count($step_data['questions']); ?> Questions
                            </span>
                            <button type="button" onclick="deleteStep(<?php echo $step; ?>)" 
                                    class="button" style="background: #f44336; color: white; border: none;">
                                🗑️ Delete Step
                            </button>
                        </div>
                    </div>
                    
                    <!-- Questions List -->
                    <?php if (empty($step_data['questions'])): ?>
                        <p style="color: #999; font-style: italic; padding: 20px; background: #f9f9f9; border-radius: 6px; text-align: center;">
                            No questions in this step yet. Use "Add New Question" above.
                        </p>
                    <?php else: ?>
                        <div class="questions-list">
                            <?php foreach ($step_data['questions'] as $q_id => $question): ?>
                                <?php
                                // Handle both old format (string) and new format (array)
                                if (is_array($question)) {
                                    $question_text = $question['text'];
                                    $allow_file_upload = !empty($question['allow_file_upload']);
                                } else {
                                    $question_text = $question;
                                    $allow_file_upload = false;
                                }
                                ?>
                                <div style="margin-bottom: 15px; padding: 20px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid #03A9F4;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 15px;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                                <code style="background: #03A9F4; color: white; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 12px;">
                                                    <?php echo esc_html($q_id); ?>
                                                </code>
                                            </div>
                                            
                                            <textarea 
                                                name="questions[<?php echo $step; ?>][questions][<?php echo esc_attr($q_id); ?>][text]" 
                                                rows="3" 
                                                class="large-text"
                                                style="width: 100%; padding: 12px; font-size: 14px; border: 2px solid #e0e0e0; border-radius: 6px; resize: vertical;"
                                                placeholder="Question text..."><?php echo esc_textarea($question_text); ?></textarea>
                                            
                                            <!-- FILE UPLOAD TOGGLE -->
                                            <div style="margin-top: 10px; padding: 10px; background: rgba(3, 169, 244, 0.05); border-radius: 6px;">
                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                    <input type="checkbox" 
                                                           name="questions[<?php echo $step; ?>][questions][<?php echo esc_attr($q_id); ?>][allow_file_upload]" 
                                                           value="1" 
                                                           <?php checked($allow_file_upload); ?>
                                                           style="width: 18px; height: 18px;">
                                                    <span style="font-weight: 500; color: #03A9F4;">
                                                        📎 Allow file upload for this question
                                                    </span>
                                                </label>
                                                <p style="margin: 5px 0 0 26px; font-size: 12px; color: #666;">
                                                    Users can attach evidence documents (PDF, DOC, DOCX)
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <button type="button" 
                                                    onclick="deleteQuestion('<?php echo $step; ?>', '<?php echo esc_js($q_id); ?>')"
                                                    class="button button-small"
                                                    style="background: #f44336; color: white; border: none; padding: 8px 12px; cursor: pointer; border-radius: 4px;"
                                                    title="Delete this question">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Save Button -->
            <div style="position: sticky; bottom: 20px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); margin-top: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
                    <div>
                        <strong>💾 Remember to save your changes</strong>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                            All modifications will be applied to the assessment form.
                        </p>
                    </div>
                    <button type="submit" name="save_questions" class="button button-primary button-hero">
                        💾 Save All Questions
                    </button>
                </div>
            </div>
            
        <?php endif; ?>
    </form>
    
    <!-- Hidden Delete Forms -->
    <form method="POST" id="deleteQuestionForm" style="display: none;">
        <?php wp_nonce_field('cip_delete_question', 'cip_delete_nonce'); ?>
        <input type="hidden" name="step" id="delete_step">
        <input type="hidden" name="q_id" id="delete_q_id">
        <input type="hidden" name="delete_question" value="1">
    </form>
    
    <form method="POST" id="deleteStepForm" style="display: none;">
        <?php wp_nonce_field('cip_delete_step_action', 'cip_delete_step_nonce'); ?>
        <input type="hidden" name="step_to_delete" id="step_to_delete">
        <input type="hidden" name="delete_step" value="1">
    </form>
</div>

<script>
function deleteQuestion(step, qId) {
    if (!confirm('Are you sure you want to delete this question?\n\nQuestion ID: ' + qId + '\n\nThis action cannot be undone!')) {
        return;
    }
    
    document.getElementById('delete_step').value = step;
    document.getElementById('delete_q_id').value = qId;
    document.getElementById('deleteQuestionForm').submit();
}

function deleteStep(step) {
    if (!confirm('⚠️ Delete entire Step ' + step + '?\n\nThis will delete ALL questions in this step.\n\nThis action cannot be undone!')) {
        return;
    }
    
    document.getElementById('step_to_delete').value = step;
    document.getElementById('deleteStepForm').submit();
}

// Warn before leaving if form has changes
let formChanged = false;
document.getElementById('questionsForm').addEventListener('input', function() {
    formChanged = true;
});

document.getElementById('questionsForm').addEventListener('submit', function() {
    formChanged = false;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        return e.returnValue;
    }
});
</script>

<style>
.badge {
    display: inline-block;
    font-size: 12px;
    font-weight: 600;
}

.questions-list textarea:focus {
    border-color: #03A9F4;
    box-shadow: 0 0 0 1px #03A9F4;
    outline: none;
}

.button-hero {
    font-size: 16px !important;
    padding: 12px 24px !important;
    height: auto !important;
}
</style>