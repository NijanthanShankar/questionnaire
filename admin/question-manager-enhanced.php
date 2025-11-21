<?php
/**
 * CleanIndex Portal - Enhanced Questions Manager
 * REPLACE: admin/questions-manager-enhanced.php
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Get current questions
$questions = get_option('cip_assessment_questions', cip_get_default_questions());

// Handle form submission
if (isset($_POST['save_questions'])) {
    check_admin_referer('cip_save_questions', 'cip_questions_nonce');
    
    $new_questions = [];
    
    if (isset($_POST['questions']) && is_array($_POST['questions'])) {
        foreach ($_POST['questions'] as $step => $step_data) {
            $step = intval($step);
            
            if (!isset($new_questions[$step])) {
                $new_questions[$step] = [
                    'title' => isset($step_data['title']) ? sanitize_text_field($step_data['title']) : 'Step ' . $step,
                    'questions' => []
                ];
            }
            
            if (isset($step_data['questions']) && is_array($step_data['questions'])) {
                foreach ($step_data['questions'] as $q_id => $question_text) {
                    $q_id = sanitize_text_field($q_id);
                    if (!empty($question_text)) {
                        $new_questions[$step]['questions'][$q_id] = sanitize_textarea_field($question_text);
                    }
                }
            }
        }
    }
    
    // Sort by step number
    ksort($new_questions);
    
    update_option('cip_assessment_questions', $new_questions);
    
    // Refresh questions
    $questions = $new_questions;
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Questions saved successfully!</strong></p></div>';
}

// Handle delete question
if (isset($_POST['delete_question'])) {
    check_admin_referer('cip_delete_question', 'cip_delete_nonce');
    
    $step = intval($_POST['step']);
    $q_id = sanitize_text_field($_POST['q_id']);
    
    if (isset($questions[$step]['questions'][$q_id])) {
        unset($questions[$step]['questions'][$q_id]);
        update_option('cip_assessment_questions', $questions);
        
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Question deleted successfully!</strong></p></div>';
    }
}

// Handle add new question
if (isset($_POST['add_question'])) {
    check_admin_referer('cip_add_question', 'cip_add_nonce');
    
    $step = intval($_POST['new_step']);
    $question_text = sanitize_textarea_field($_POST['new_question_text']);
    
    if (!empty($question_text)) {
        // Generate new question ID
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
        
        $questions[$step]['questions'][$new_q_id] = $question_text;
        update_option('cip_assessment_questions', $questions);
        
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Question added successfully!</strong></p></div>';
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
    
    <!-- Add New Question Form -->
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
                        <select name="new_step" id="new_step" style="width: 200px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>">Step <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="new_question_text">Question Text</label>
                    </th>
                    <td>
                        <textarea name="new_question_text" id="new_question_text" rows="3" class="large-text" 
                                  placeholder="Enter your question here..." required></textarea>
                        <p class="description">This will be displayed to users during the assessment.</p>
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
    
    <!-- Edit Existing Questions Form -->
    <form method="POST" id="questionsForm">
        <?php wp_nonce_field('cip_save_questions', 'cip_questions_nonce'); ?>
        
        <?php if (empty($questions)): ?>
            <div style="background: white; padding: 40px; margin: 20px 0; text-align: center; border-radius: 8px;">
                <div style="font-size: 64px; margin-bottom: 20px;">📝</div>
                <h3>No Questions Found</h3>
                <p style="color: #666;">Use the form above to add your first question.</p>
            </div>
        <?php else: ?>
            
            <?php foreach ($questions as $step => $step_data): ?>
                <div style="background: white; padding: 25px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <!-- Step Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #4CAF50;">
                        <div>
                            <input type="text" 
                                   name="questions[<?php echo $step; ?>][title]" 
                                   value="<?php echo esc_attr($step_data['title']); ?>" 
                                   style="font-size: 20px; font-weight: 600; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; width: 100%; max-width: 500px;"
                                   placeholder="Step Title">
                        </div>
                        <div>
                            <span class="badge" style="background: #4CAF50; color: white; padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                                <?php echo count($step_data['questions']); ?> Questions
                            </span>
                        </div>
                    </div>
                    
                    <!-- Questions List -->
                    <?php if (empty($step_data['questions'])): ?>
                        <p style="color: #999; font-style: italic; padding: 20px; background: #f9f9f9; border-radius: 6px; text-align: center;">
                            No questions in this step yet. Use the "Add New Question" form above to add questions.
                        </p>
                    <?php else: ?>
                        <div class="questions-list">
                            <?php foreach ($step_data['questions'] as $q_id => $question_text): ?>
                                <div style="margin-bottom: 15px; padding: 20px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid #03A9F4;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 15px;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                                <code style="background: #03A9F4; color: white; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 12px;">
                                                    <?php echo esc_html($q_id); ?>
                                                </code>
                                                <span style="color: #999; font-size: 12px;">
                                                    Question ID (used in database)
                                                </span>
                                            </div>
                                            
                                            <textarea 
                                                name="questions[<?php echo $step; ?>][questions][<?php echo esc_attr($q_id); ?>]" 
                                                rows="3" 
                                                class="large-text"
                                                style="width: 100%; padding: 12px; font-size: 14px; border: 2px solid #e0e0e0; border-radius: 6px; resize: vertical;"
                                                placeholder="Question text..."><?php echo esc_textarea($question_text); ?></textarea>
                                        </div>
                                        
                                        <div>
                                            <button type="button" 
                                                    onclick="deleteQuestion('<?php echo $step; ?>', '<?php echo esc_js($q_id); ?>')"
                                                    class="button button-small"
                                                    style="background: #f44336; color: white; border: none; padding: 8px 12px; cursor: pointer; border-radius: 4px;"
                                                    title="Delete this question">
                                                🗑️ Delete
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
    
    <!-- Hidden Delete Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <?php wp_nonce_field('cip_delete_question', 'cip_delete_nonce'); ?>
        <input type="hidden" name="step" id="delete_step">
        <input type="hidden" name="q_id" id="delete_q_id">
        <input type="hidden" name="delete_question" value="1">
    </form>
    
    <!-- Help Section -->
    <div style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <h3 style="margin-top: 0; color: #ff9800;">💡 Tips for Managing Questions</h3>
        <ul style="margin: 10px 0;">
            <li><strong>Question IDs:</strong> Each question has a unique ID (e.g., q1_1, q2_3). Don't change these as they're used to store answers in the database.</li>
            <li><strong>Step Titles:</strong> You can customize the title for each step to better describe that section of the assessment.</li>
            <li><strong>Question Order:</strong> Questions are displayed in the order they appear here. Drag and drop functionality coming soon!</li>
            <li><strong>Adding Questions:</strong> Use the "Add New Question" form above to add new questions to any step.</li>
            <li><strong>Deleting Questions:</strong> Be careful when deleting questions - existing answers for that question will still be in the database.</li>
        </ul>
    </div>
    
    <!-- Preview Section -->
    <div style="background: white; padding: 25px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h2>👁️ Questions Overview</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th style="width: 80px;">Step</th>
                    <th>Title</th>
                    <th style="width: 120px;">Questions</th>
                    <th style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $step => $step_data): ?>
                    <tr>
                        <td><strong>Step <?php echo $step; ?></strong></td>
                        <td><?php echo esc_html($step_data['title']); ?></td>
                        <td style="text-align: center;">
                            <span style="background: #03A9F4; color: white; padding: 4px 12px; border-radius: 12px; font-weight: 600;">
                                <?php echo count($step_data['questions']); ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" onclick="scrollToStep(<?php echo $step; ?>)" class="button button-small">
                                Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function deleteQuestion(step, qId) {
    if (!confirm('Are you sure you want to delete this question?\n\nQuestion ID: ' + qId + '\n\nThis action cannot be undone!')) {
        return;
    }
    
    document.getElementById('delete_step').value = step;
    document.getElementById('delete_q_id').value = qId;
    document.getElementById('deleteForm').submit();
}

function scrollToStep(step) {
    // Find the step container and scroll to it
    const stepElements = document.querySelectorAll('[name^="questions[' + step + '][title]"]');
    if (stepElements.length > 0) {
        stepElements[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        stepElements[0].focus();
        
        // Highlight the step temporarily
        const container = stepElements[0].closest('div[style*="background: white"]');
        if (container) {
            container.style.boxShadow = '0 0 0 3px #03A9F4';
            setTimeout(() => {
                container.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
            }, 2000);
        }
    }
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

// Auto-save notification
let saveTimeout;
document.getElementById('questionsForm').addEventListener('input', function() {
    clearTimeout(saveTimeout);
    
    // Show reminder after 30 seconds of no typing
    saveTimeout = setTimeout(function() {
        if (confirm('You have unsaved changes. Would you like to save now?')) {
            document.getElementById('questionsForm').submit();
        }
    }, 30000);
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

@media (max-width: 768px) {
    .wrap > div {
        padding: 15px !important;
    }
    
    table.form-table th,
    table.form-table td {
        display: block;
        width: 100% !important;
    }
}
</style>