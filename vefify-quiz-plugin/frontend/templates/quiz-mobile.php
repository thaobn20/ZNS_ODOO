<?php
/**
 * Frontend Quiz Template (Mobile-First)
 * File: frontend/templates/quiz-mobile.php
 * Based on user's existing HTML structure
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Get campaign data
$campaign_id = $atts['campaign_id'];
$campaign_manager = new VefifyQuiz\CampaignManager();
$campaign_response = $campaign_manager->get_campaign(['id' => $campaign_id]);

if (is_wp_error($campaign_response)) {
    echo '<p>Campaign not found.</p>';
    return;
}

$campaign_data = $campaign_response->data;
$campaign = $campaign_data['campaign'];
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($campaign['name']); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    
    <!-- Enhanced styles for WordPress integration -->
    <style>
        /* Your existing CSS from the HTML file - enhanced for WordPress */
        .vefify-quiz-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 1rem;
            color: #333;
            position: relative;
        }
        
        .vefify-quiz-wrapper * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* Copy all your existing styles here */
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        /* Progress bar styles */
        .progress-bar {
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: white;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        /* Continue with all your existing CSS styles... */
        .content { padding: 2rem 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; font-size: 0.9rem; }
        .form-input, .form-select { width: 100%; padding: 0.875rem; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; transition: all 0.2s ease; background: white; }
        .form-input:focus, .form-select:focus { outline: none; border-color: #4facfe; box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1); }
        .form-input.error { border-color: #e74c3c; animation: shake 0.3s ease-in-out; }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .error-message { color: #e74c3c; font-size: 0.8rem; margin-top: 0.5rem; display: none; }
        .question-container { display: none; }
        .question-header { text-align: center; margin-bottom: 2rem; }
        .question-counter { background: #f8f9fa; color: #666; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem; display: inline-block; margin-bottom: 1rem; }
        .question-title { font-size: 1.2rem; font-weight: 600; line-height: 1.4; color: #333; }
        .answers-container { margin: 2rem 0; }
        .answer-option { background: #f8f9fa; border: 2px solid #e1e5e9; border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; cursor: pointer; transition: all 0.2s ease; position: relative; user-select: none; -webkit-tap-highlight-color: transparent; }
        .answer-option:hover { background: #e9ecef; }
        .answer-option.selected { background: #e3f2fd; border-color: #4facfe; color: #1976d2; }
        .answer-option input[type="checkbox"] { position: absolute; opacity: 0; pointer-events: none; }
        .answer-text { font-weight: 500; }
        .button-group { display: flex; gap: 1rem; margin-top: 2rem; }
        .btn { flex: 1; padding: 0.875rem 1.5rem; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 0.5px; position: relative; overflow: hidden; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4); }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover:not(:disabled) { background: #5a6268; transform: translateY(-2px); }
        .loading { display: none; text-align: center; padding: 3rem 1rem; }
        .spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #4facfe; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1rem; }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .result-container { display: none; text-align: center; padding: 2rem 1rem; }
        .result-icon { font-size: 4rem; margin-bottom: 1rem; }
        .result-score { font-size: 2rem; font-weight: 700; color: #4facfe; margin-bottom: 0.5rem; }
        .result-message { font-size: 1.1rem; margin-bottom: 1.5rem; line-height: 1.4; }
        .reward-card { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 12px; padding: 1.5rem; margin: 1.5rem 0; }
        .reward-title { font-weight: 700; color: #8b4513; margin-bottom: 0.5rem; }
        .reward-code { font-family: 'Courier New', monospace; font-size: 1.2rem; font-weight: 700; background: white; padding: 0.5rem; border-radius: 6px; color: #333; }
        .popup { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: none; justify-content: center; align-items: center; z-index: 1000; padding: 1rem; }
        .popup-content { background: white; border-radius: 16px; padding: 2rem; max-width: 300px; text-align: center; transform: scale(0.7); transition: transform 0.3s ease; }
        .popup.show .popup-content { transform: scale(1); }
        .popup-icon { font-size: 3rem; margin-bottom: 1rem; }
        .popup-message { margin-bottom: 1.5rem; line-height: 1.4; }
        
        @media (max-width: 480px) {
            .vefify-quiz-wrapper { padding: 0.5rem; }
            .content { padding: 1.5rem 1rem; }
            .header { padding: 1.5rem 1rem; }
            .header h1 { font-size: 1.3rem; }
            .button-group { flex-direction: column; }
            .btn { margin-bottom: 0.5rem; }
        }
        
        @media (max-height: 600px) and (orientation: landscape) {
            .header { padding: 1rem 1.5rem; }
            .content { padding: 1rem 1.5rem; }
            .form-group { margin-bottom: 1rem; }
        }
        
        .fade-in { animation: fadeIn 0.3s ease-in; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body <?php body_class('vefify-quiz-page'); ?>>

<div class="vefify-quiz-wrapper">
    <div class="container">
        <div class="header">
            <h1>🎯 <?php echo esc_html($campaign['name']); ?></h1>
            <p><?php echo esc_html($campaign['description']); ?></p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <!-- Registration Form -->
        <div class="content" id="registrationForm">
            <form id="userForm">
                <div class="form-group">
                    <label class="form-label" for="fullName">Full Name *</label>
                    <input type="text" id="fullName" class="form-input" placeholder="Enter your full name" required>
                    <div class="error-message" id="nameError">Please enter your full name</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phoneNumber">Phone Number *</label>
                    <input type="tel" id="phoneNumber" class="form-input" placeholder="0901234567" required>
                    <div class="error-message" id="phoneError">Please enter a valid phone number</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="province">Province/City *</label>
                    <select id="province" class="form-select" required>
                        <option value="">Select your province/city</option>
                        <?php
                        // Load Vietnamese provinces from config
                        global $vietnamese_provinces;
                        foreach ($vietnamese_provinces as $key => $name): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="error-message" id="provinceError">Please select your province/city</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pharmacyCode">Pharmacy Code</label>
                    <input type="text" id="pharmacyCode" class="form-input" placeholder="Optional">
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Continue →</button>
                </div>
            </form>
        </div>

        <!-- Loading State -->
        <div class="loading" id="loadingState">
            <div class="spinner"></div>
            <p>Loading quiz questions...</p>
        </div>

        <!-- Quiz Container -->
        <div class="question-container" id="quizContainer">
            <div class="content">
                <div class="question-header">
                    <div class="question-counter" id="questionCounter">Question 1 of 5</div>
                    <div class="question-title" id="questionTitle">Loading question...</div>
                </div>

                <div class="answers-container" id="answersContainer">
                    <!-- Answers will be loaded here -->
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary" id="prevBtn" disabled>← Previous</button>
                    <button type="button" class="btn btn-primary" id="nextBtn">Next →</button>
                </div>
            </div>
        </div>

        <!-- Result Container -->
        <div class="result-container" id="resultContainer">
            <div class="result-icon">🎉</div>
            <div class="result-score" id="resultScore">5/5</div>
            <div class="result-message" id="resultMessage">Congratulations! You've completed the quiz.</div>
            
            <div class="reward-card" id="rewardCard" style="display: none;">
                <div class="reward-title">🎁 You've Won!</div>
                <div class="reward-code" id="rewardCode">GIFT50K</div>
            </div>

            <div class="button-group">
                <button type="button" class="btn btn-primary" onclick="restartQuiz()">Take Another Quiz</button>
            </div>
        </div>
    </div>

    <!-- Popup for already participated -->
    <div class="popup" id="alreadyParticipatedPopup">
        <div class="popup-content">
            <div class="popup-icon">⚠️</div>
            <div class="popup-message">You have already participated in this campaign.</div>
            <button class="btn btn-primary" onclick="closePopup()">OK</button>
        </div>
    </div>
</div>

<!-- WordPress Footer -->
<?php wp_footer(); ?>

<!-- Campaign Data -->
<script>
    // Pass campaign data to JavaScript
    window.vefifyCampaignData = <?php echo json_encode([
        'campaign_id' => $campaign['id'],
        'campaign_name' => $campaign['name'],
        'questions_per_quiz' => $campaign['questions_per_quiz'],
        'time_limit' => $campaign['time_limit'],
        'nonce' => wp_create_nonce('vefify_quiz_nonce')
    ]); ?>;
</script>

</body>
</html>