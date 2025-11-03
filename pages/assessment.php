<?php
/**
 * CleanIndex Portal - Assessment Page
 * Multi-step CSRD/ESRS questionnaire
 */

if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

// Check user status
$status = cip_check_user_status();
if ($status !== 'approved') {
    wp_redirect(home_url('/cleanindex/dashboard'));
    exit;
}

$user_id = get_current_user_id();

// Load existing assessment data
global $wpdb;
$table_assessments = $wpdb->prefix . 'company_assessments';
$existing_assessment = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_assessments WHERE user_id = %d",
    $user_id
), ARRAY_A);

$assessment_data = $existing_assessment ? json_decode($existing_assessment['assessment_json'], true) : [];
$progress = $existing_assessment ? $existing_assessment['progress'] : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESG Assessment - CleanIndex Portal</title>
    <?php wp_head(); ?>
</head>
<body class="cleanindex-page">
    <div class="dashboard">
        <aside class="dashboard-sidebar">
            <div class="dashboard-logo">CleanIndex</div>
            <nav>
                <ul class="dashboard-nav">
                    <li><a href="<?php echo home_url('/cleanindex/dashboard'); ?>">Dashboard</a></li>
                    <li><a href="<?php echo home_url('/cleanindex/assessment'); ?>" class="active">Assessment</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url('/cleanindex/login')); ?>">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="dashboard-content">
            <div class="dashboard-header">
                <h1>CSRD/ESRS Assessment</h1>
                <div>
                    <span class="badge badge-review">In Progress</span>
                </div>
            </div>
            
            <!-- Progress Steps -->
            <div class="assessment-steps" id="assessmentSteps">
                <div class="step" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">General & Materiality</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Profile & Governance</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Strategy & Risk</div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label">Environment</div>
                </div>
                <div class="step" data-step="5">
                    <div class="step-number">5</div>
                    <div class="step-label">Social & Metrics</div>
                </div>
            </div>
            
            <div class="glass-card">
                <form id="assessmentForm">
                    <?php wp_nonce_field('cip_assessment', 'cip_assessment_nonce'); ?>
                    
                    <!-- Step 1: General Requirements & Materiality Analysis -->
                    <div class="assessment-step" id="step1">
                        <h2 class="mb-3">1. General Requirements and Materiality Analysis (ESRS 1 & 2)</h2>
                        
                        <div class="form-group">
                            <label class="form-label">What sustainability impacts, risks, and opportunities (IROs) does your company have on people and the environment (impact materiality), and which affect your finances (financial materiality)?</label>
                            <textarea name="q1_1" class="form-control" rows="4"><?php echo isset($assessment_data['q1_1']) ? esc_textarea($assessment_data['q1_1']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q1_1" accept=".pdf,.doc,.docx" class="form-control">
                                <small style="color: var(--gray-medium);">Optional: Attach evidence document</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">How have you engaged stakeholders (e.g., employees, investors, suppliers) in identifying material topics?</label>
                            <textarea name="q1_2" class="form-control" rows="4"><?php echo isset($assessment_data['q1_2']) ? esc_textarea($assessment_data['q1_2']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q1_2" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">What are the boundaries of your reporting (e.g., only own operations or including the entire value chain)?</label>
                            <select name="q1_3" class="form-control">
                                <option value="">Select...</option>
                                <option value="own_operations" <?php selected(isset($assessment_data['q1_3']) && $assessment_data['q1_3'] === 'own_operations'); ?>>Own Operations Only</option>
                                <option value="partial_value_chain" <?php selected(isset($assessment_data['q1_3']) && $assessment_data['q1_3'] === 'partial_value_chain'); ?>>Partial Value Chain</option>
                                <option value="full_value_chain" <?php selected(isset($assessment_data['q1_3']) && $assessment_data['q1_3'] === 'full_value_chain'); ?>>Full Value Chain</option>
                            </select>
                            <div class="mt-2">
                                <input type="file" name="evidence_q1_3" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Have you conducted a step-by-step materiality analysis, including an assessment of the severity, scale, and likelihood of IROs?</label>
                            <div style="margin-top: 0.5rem;">
                                <label style="display: inline-flex; align-items: center; margin-right: 2rem;">
                                    <input type="radio" name="q1_4" value="yes" <?php checked(isset($assessment_data['q1_4']) && $assessment_data['q1_4'] === 'yes'); ?>>
                                    <span style="margin-left: 0.5rem;">Yes</span>
                                </label>
                                <label style="display: inline-flex; align-items: center;">
                                    <input type="radio" name="q1_4" value="no" <?php checked(isset($assessment_data['q1_4']) && $assessment_data['q1_4'] === 'no'); ?>>
                                    <span style="margin-left: 0.5rem;">No</span>
                                </label>
                            </div>
                            <div class="mt-2">
                                <input type="file" name="evidence_q1_4" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Which ESRS standards are material for you, and which can you exclude (with justification)?</label>
                            <textarea name="q1_5" class="form-control" rows="4" placeholder="List material standards and excluded ones with justification..."><?php echo isset($assessment_data['q1_5']) ? esc_textarea($assessment_data['q1_5']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q1_5" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Company Profile & Governance -->
                    <div class="assessment-step hidden" id="step2">
                        <h2 class="mb-3">2. Company Profile and Value Chain (ESRS 2)</h2>
                        
                        <div class="form-group">
                            <label class="form-label">What is your business model, including inputs (e.g., materials, labor), activities, and outputs (e.g., products, waste)?</label>
                            <textarea name="q2_1" class="form-control" rows="4"><?php echo isset($assessment_data['q2_1']) ? esc_textarea($assessment_data['q2_1']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q2_1" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">How do sustainability factors integrate into your business model (e.g., circular economy or fair wages)?</label>
                            <textarea name="q2_2" class="form-control" rows="4"><?php echo isset($assessment_data['q2_2']) ? esc_textarea($assessment_data['q2_2']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q2_2" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4 mb-3">Governance and Due Diligence (ESRS 2 & G1)</h3>
                        
                        <div class="form-group">
                            <label class="form-label">How is your board of directors involved in managing sustainability IROs (e.g., through committees or training)?</label>
                            <textarea name="q2_3" class="form-control" rows="4"><?php echo isset($assessment_data['q2_3']) ? esc_textarea($assessment_data['q2_3']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q2_3" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">What due diligence processes do you have for sustainability risks in the value chain (e.g., supplier screening)?</label>
                            <textarea name="q2_4" class="form-control" rows="4"><?php echo isset($assessment_data['q2_4']) ? esc_textarea($assessment_data['q2_4']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q2_4" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Which compensation structures are linked to sustainability performance?</label>
                            <textarea name="q2_5" class="form-control" rows="3"><?php echo isset($assessment_data['q2_5']) ? esc_textarea($assessment_data['q2_5']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q2_5" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Strategy & Risk Management -->
                    <div class="assessment-step hidden" id="step3">
                        <h2 class="mb-3">3. Strategy and Outlook (ESRS 2)</h2>
                        
                        <div class="form-group">
                            <label class="form-label">How do material IROs influence your short-, medium-, and long-term strategy (e.g., climate risks impacting revenue)?</label>
                            <textarea name="q3_1" class="form-control" rows="4"><?php echo isset($assessment_data['q3_1']) ? esc_textarea($assessment_data['q3_1']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q3_1" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">What are your strategic adjustments to sustainability challenges (e.g., transition plans for net-zero)?</label>
                            <textarea name="q3_2" class="form-control" rows="4"><?php echo isset($assessment_data['q3_2']) ? esc_textarea($assessment_data['q3_2']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q3_2" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4 mb-3">Management of Impacts, Risks, and Opportunities</h3>
                        
                        <div class="form-group">
                            <label class="form-label">How do you identify, assess, and prioritize material IROs (e.g., through tools or audits)?</label>
                            <textarea name="q3_3" class="form-control" rows="4"><?php echo isset($assessment_data['q3_3']) ? esc_textarea($assessment_data['q3_3']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q3_3" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">What are your policies, actions, and mitigation strategies for these IROs?</label>
                            <textarea name="q3_4" class="form-control" rows="4"><?php echo isset($assessment_data['q3_4']) ? esc_textarea($assessment_data['q3_4']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q3_4" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 4: Environment (E1-E5) -->
                    <div class="assessment-step hidden" id="step4">
                        <h2 class="mb-3">4. Environment (ESRS E1 to E5)</h2>
                        
                        <h3>Climate Change (E1)</h3>
                        <div class="form-group">
                            <label class="form-label">What are your Scope 1, 2, and 3 greenhouse gas emissions?</label>
                            <input type="text" name="q4_1_scope1" class="form-control mb-2" placeholder="Scope 1 emissions (tCO2e)" value="<?php echo isset($assessment_data['q4_1_scope1']) ? esc_attr($assessment_data['q4_1_scope1']) : ''; ?>">
                            <input type="text" name="q4_1_scope2" class="form-control mb-2" placeholder="Scope 2 emissions (tCO2e)" value="<?php echo isset($assessment_data['q4_1_scope2']) ? esc_attr($assessment_data['q4_1_scope2']) : ''; ?>">
                            <input type="text" name="q4_1_scope3" class="form-control" placeholder="Scope 3 emissions (tCO2e)" value="<?php echo isset($assessment_data['q4_1_scope3']) ? esc_attr($assessment_data['q4_1_scope3']) : ''; ?>">
                            <div class="mt-2">
                                <input type="file" name="evidence_q4_1" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">How are you reducing emissions (including a transition plan to net-zero)?</label>
                            <textarea name="q4_2" class="form-control" rows="4"><?php echo isset($assessment_data['q4_2']) ? esc_textarea($assessment_data['q4_2']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q4_2" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4">Pollution (E2)</h3>
                        <div class="form-group">
                            <label class="form-label">What emissions (e.g., air, soil) do you cause, and what do you do to minimize them?</label>
                            <textarea name="q4_3" class="form-control" rows="4"><?php echo isset($assessment_data['q4_3']) ? esc_textarea($assessment_data['q4_3']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q4_3" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4">Water and Marine Resources (E3)</h3>
                        <div class="form-group">
                            <label class="form-label">How much water do you use, and how do you manage risks in water-scarce areas?</label>
                            <input type="text" name="q4_4_usage" class="form-control mb-2" placeholder="Water usage (cubic meters)" value="<?php echo isset($assessment_data['q4_4_usage']) ? esc_attr($assessment_data['q4_4_usage']) : ''; ?>">
                            <textarea name="q4_4_management" class="form-control" rows="3" placeholder="Water management strategies..."><?php echo isset($assessment_data['q4_4_management']) ? esc_textarea($assessment_data['q4_4_management']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q4_4" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4">Biodiversity (E4)</h3>
                        <div class="form-group">
                            <label class="form-label">What impact do you have on ecosystems (e.g., deforestation), and how do you protect biodiversity?</label>
                            <textarea name="q4_5" class="form-control" rows="4"><?php echo isset($assessment_data['q4_5']) ? esc_textarea($assessment_data['q4_5']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q4_5" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4">Circular Economy (E5)</h3>
                        <div class="form-group">
                            <label class="form-label">How much waste do you produce, and what is your percentage of recycled materials?</label>
                            <input type="text" name="q4_6_waste" class="form-control mb-2" placeholder="Total waste (tons)" value="<?php echo isset($assessment_data['q4_6_waste']) ? esc_attr($assessment_data['q4_6_waste']) : ''; ?>">
                            <input type="text" name="q4_6_recycled" class="form-control" placeholder="Recycled materials (%)" value="<?php echo isset($assessment_data['q4_6_recycled']) ? esc_attr($assessment_data['q4_6_recycled']) : ''; ?>">
                            <div class="mt-2">
                                <input type="file" name="evidence_q4_6" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 5: Social & Metrics -->
                    <div class="assessment-step hidden" id="step5">
                        <h2 class="mb-3">5. Social (ESRS S1 to S4) & Metrics</h2>
                        
                        <h3>Own Workforce (S1)</h3>
                        <div class="form-group">
                            <label class="form-label">How many employees do you have, what are their diversity and wage levels, and how do you prevent discrimination?</label>
                            <input type="number" name="q5_1_employees" class="form-control mb-2" placeholder="Total employees" value="<?php echo isset($assessment_data['q5_1_employees']) ? esc_attr($assessment_data['q5_1_employees']) : ''; ?>">
                            <textarea name="q5_1_diversity" class="form-control mb-2" rows="2" placeholder="Diversity statistics..."><?php echo isset($assessment_data['q5_1_diversity']) ? esc_textarea($assessment_data['q5_1_diversity']) : ''; ?></textarea>
                            <textarea name="q5_1_prevention" class="form-control" rows="3" placeholder="Anti-discrimination measures..."><?php echo isset($assessment_data['q5_1_prevention']) ? esc_textarea($assessment_data['q5_1_prevention']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q5_1" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4">Workers in the Value Chain (S2)</h3>
                        <div class="form-group">
                            <label class="form-label">What are the labor conditions at your suppliers, and how do you screen for child labor?</label>
                            <textarea name="q5_2" class="form-control" rows="4"><?php echo isset($assessment_data['q5_2']) ? esc_textarea($assessment_data['q5_2']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q5_2" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4">Affected Communities (S3)</h3>
                        <div class="form-group">
                            <label class="form-label">What impact do you have on local communities (e.g., displacement), and how do you consult them?</label>
                            <textarea name="q5_3" class="form-control" rows="4"><?php echo isset($assessment_data['q5_3']) ? esc_textarea($assessment_data['q5_3']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q5_3" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4">Consumers and End-Users (S4)</h3>
                        <div class="form-group">
                            <label class="form-label">How do you protect the health and privacy of customers in your products/services?</label>
                            <textarea name="q5_4" class="form-control" rows="4"><?php echo isset($assessment_data['q5_4']) ? esc_textarea($assessment_data['q5_4']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q5_4" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <h3 class="mt-4">Metrics and Targets</h3>
                        <div class="form-group">
                            <label class="form-label">What KPIs and targets do you have for material IROs (e.g., 50% emission reduction by 2030)?</label>
                            <textarea name="q5_5" class="form-control" rows="4" placeholder="List your key performance indicators and targets..."><?php echo isset($assessment_data['q5_5']) ? esc_textarea($assessment_data['q5_5']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q5_5" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">How do you audit your sustainability report (including limited or reasonable assurance)?</label>
                            <textarea name="q5_6" class="form-control" rows="3"><?php echo isset($assessment_data['q5_6']) ? esc_textarea($assessment_data['q5_6']) : ''; ?></textarea>
                            <div class="mt-2">
                                <input type="file" name="evidence_q5_6" accept=".pdf,.doc,.docx" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-between mt-4">
                        <button type="button" class="btn btn-outline" id="prevBtn" onclick="changeStep(-1)" style="display: none;">
                            ← Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">
                            Next →
                        </button>
                        <button type="button" class="btn btn-primary" id="submitBtn" onclick="submitAssessment()" style="display: none;">
                            Submit Assessment
                        </button>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <button type="button" class="btn btn-accent" onclick="saveProgress()">
                            💾 Save Progress
                        </button>
                    </div>
                </form>
                
                <div id="loadingIndicator" class="hidden text-center">
                    <div class="spinner"></div>
                    <p>Saving...</p>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    let currentStep = 1;
    const totalSteps = 5;
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        showStep(currentStep);
    });
    
    function showStep(step) {
        // Hide all steps
        document.querySelectorAll('.assessment-step').forEach(s => s.classList.add('hidden'));
        
        // Show current step
        document.getElementById('step' + step).classList.remove('hidden');
        
        // Update step indicators
        document.querySelectorAll('.step').forEach((s, index) => {
            s.classList.remove('active', 'completed');
            if (index + 1 < step) {
                s.classList.add('completed');
            } else if (index + 1 === step) {
                s.classList.add('active');
            }
        });
        
        // Update buttons
        document.getElementById('prevBtn').style.display = step === 1 ? 'none' : 'inline-block';
        document.getElementById('nextBtn').style.display = step === totalSteps ? 'none' : 'inline-block';
        document.getElementById('submitBtn').style.display = step === totalSteps ? 'inline-block' : 'none';
        
        // Scroll to top
        window.scrollTo(0, 0);
    }
    
    function changeStep(direction) {
        currentStep += direction;
        if (currentStep < 1) currentStep = 1;
        if (currentStep > totalSteps) currentStep = totalSteps;
        showStep(currentStep);
        saveProgress();
    }
    
    function saveProgress() {
        const formData = new FormData(document.getElementById('assessmentForm'));
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (!key.startsWith('evidence_')) {
                data[key] = value;
            }
        }
        
        document.getElementById('loadingIndicator').classList.remove('hidden');
        
        fetch(cipAjax.ajaxurl, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'cip_save_assessment',
                nonce: cipAjax.nonce,
                assessment_data: JSON.stringify(data),
                progress: currentStep
            })
        })
        .then(response => response.json())
        .then(result => {
            document.getElementById('loadingIndicator').classList.add('hidden');
            if (result.success) {
                showAlert('Progress saved successfully', 'success');
            }
        })
        .catch(error => {
            document.getElementById('loadingIndicator').classList.add('hidden');
            showAlert('Error saving progress', 'error');
        });
    }
    
    function submitAssessment() {
        if (confirm('Are you ready to submit your assessment? You can still edit it later.')) {
            saveProgress();
            alert('Assessment submitted successfully! Our team will review it shortly.');
            window.location.href = '<?php echo home_url('/cleanindex/dashboard'); ?>';
        }
    }
    
    function showAlert(message, type) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        alert.style.position = 'fixed';
        alert.style.top = '20px';
        alert.style.right = '20px';
        alert.style.zIndex = '9999';
        document.body.appendChild(alert);
        
        setTimeout(() => alert.remove(), 3000);
    }
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>