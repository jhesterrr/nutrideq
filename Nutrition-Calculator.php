<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

// Get user data from session
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['user_role'];

// Generate initials for avatar
function getInitials($name)
{
    $names = explode(' ', $name);
    $initials = '';
    foreach ($names as $n) {
        $initials .= strtoupper($n[0]);
    }
    return substr($initials, 0, 2);
}

$user_initials = getInitials($user_name);
require_once 'navigation.php';
$nav_links_array = getNavigationLinks($user_role, 'Nutrition-Calculator.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>NutriDeq - Nutrition Calculator</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/calculator.css">
    <link rel="stylesheet" href="css/messages.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/logout-modal.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/mobile-style.css">
    <script src="scripts/dashboard.js" defer></script>
</head>

<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-container">
                <div class="header">
                <div class="page-title">
                    <h1>Nutrition Calculator</h1>
                    <p>Calculate your energy requirements and body composition metrics</p>
                </div>
            </div>

            <div class="calculator-container">
                <!-- Input Section -->
                <div class="calculator-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-edit"></i> Personal Information</h2>
                    </div>

                    <div class="input-grid">
                        <div class="form-group">
                            <label for="sex">Sex</label>
                            <select class="form-control" id="sex">
                                <option value="">Select Sex</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" class="form-control" id="dob">
                        </div>

                        <div class="form-group">
                            <label for="weight">Weight</label>
                            <div class="select-group">
                                <input type="number" class="form-control" id="weight" placeholder="0">
                                <select class="form-control">
                                    <option>kg</option>
                                    <option>lbs</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="height">Height</label>
                            <div class="select-group">
                                <input type="text" class="form-control" id="height" placeholder="0">
                                <select class="form-control">
                                    <option>cm</option>
                                    <option>ft</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="activity">Physical Activity Level</label>
                            <select class="form-control" id="activity">
                                <option value="">Select Activity Level</option>
                                <option value="sedentary">Sedentary</option>
                                <option value="light">Lightly Active</option>
                                <option value="moderate">Moderately Active</option>
                                <option value="very">Very Active</option>
                                <option value="extra">Extra Active</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="waist">Waist Circumference</label>
                            <div class="select-group">
                                <input type="number" class="form-control" id="waist" placeholder="0">
                                <select class="form-control">
                                    <option>cm</option>
                                    <option>in</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="hip">Hip Circumference</label>
                            <div class="select-group">
                                <input type="number" class="form-control" id="hip" placeholder="0">
                                <select class="form-control">
                                    <option>cm</option>
                                    <option>in</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="button-group">
                        <button class="calculate-btn" id="calculateBtn">
                            <i class="fas fa-calculator"></i> Calculate All Metrics
                        </button>
                        <button class="reset-btn" id="resetBtn">
                            <i class="fas fa-redo"></i> Reset All Inputs
                        </button>
                    </div>
                </div>

                <!-- Results Section -->
                <div class="calculator-card">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-bar"></i> Calculation Results</h2>
                    </div>

                    <div class="results-grid">
                        <div class="result-card">
                            <div class="result-value" id="energyResult">--</div>
                            <div class="result-label">
                                Energy Requirement
                                <button class="info-btn" title="Learn More"><i class="fas fa-info-circle"></i></button>
                            </div>
                            <div class="result-status">kcal/day</div>
                        </div>

                        <div class="result-card">
                            <div class="result-value" id="bmiResult">--</div>
                            <div class="result-label">
                                Body Mass Index
                                <button class="info-btn" title="Learn More"><i class="fas fa-info-circle"></i></button>
                            </div>
                            <div class="result-status" id="bmiStatus">--</div>
                        </div>

                        <div class="result-card">
                            <div class="result-value" id="whrResult">--</div>
                            <div class="result-label">
                                Waist-Hip Ratio
                                <button class="info-btn" title="Learn More"><i class="fas fa-info-circle"></i></button>
                            </div>
                            <div class="result-status" id="whrStatus">--</div>
                        </div>

                        <div class="result-card">
                            <div class="result-value" id="whtrResult">--</div>
                            <div class="result-label">
                                Waist-Height Ratio
                                <button class="info-btn" title="Learn More"><i class="fas fa-info-circle"></i></button>
                            </div>
                            <div class="result-status" id="whtrStatus">--</div>
                        </div>
                    </div>

                    <div class="method-selection">
                        <h3 style="margin-bottom: 15px;">Desirable Body Weight Method</h3>
                        <div class="method-options">
                            <div class="method-option active" data-method="tannhauser">
                                Tannhauser Formula
                            </div>
                            <div class="method-option" data-method="hamwi">
                                Hamwi Formula
                            </div>
                            <div class="method-option" data-method="bmi">
                                Body Mass Index
                            </div>
                            <div class="method-option" data-method="kilogram">
                                Kilogram
                            </div>
                        </div>
                    </div>

                    <div class="result-card" style="margin-top: 20px;">
                        <div class="result-value" id="weightResult">--</div>
                        <div class="result-label">Desirable Body Weight</div>
                        <div class="result-status" id="weightUnit">kg</div>
                    </div>
                </div>
            </div>

            <!-- Disclaimer Section -->
            <div class="disclaimer-box">
                <h4><i class="fas fa-exclamation-triangle"></i> Important Notice</h4>
                <p>For personalized diet and nutrition concerns, it is best to seek advice from a Registered
                    Nutritionist-Dietitian.</p>
                <?php if ($user_role === 'regular'): ?>
                    <button class="consult-btn" id="consultBtn">
                        <i class="fas fa-user-md"></i> CONSULT NOW!
                    </button>
                <?php endif; ?>
                <p style="margin-top: 15px; font-size: 0.8rem; color: var(--gray);">
                    Results are estimates and should not replace professional medical advice.
                </p>
            </div>
            </div> <!-- end page-container -->
        </main> <!-- end main-content -->
    </div> <!-- end main-layout -->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calculateBtn = document.getElementById('calculateBtn');
            const resetBtn = document.getElementById('resetBtn');
            const methodOptions = document.querySelectorAll('.method-option');
            let currentMethod = 'tannhauser';

            // Method selection logic
            methodOptions.forEach(option => {
                option.addEventListener('click', function() {
                    methodOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    currentMethod = this.getAttribute('data-method');
                    if (document.getElementById('energyResult').textContent !== '--') {
                        calculate(); // Re-calculate if results already show
                    }
                });
            });

            // Helper: Convert to Standard Units
            function getStandardizedValues() {
                const weightVal = parseFloat(document.getElementById('weight').value) || 0;
                const weightUnit = document.getElementById('weight').nextElementSibling.value;
                const heightVal = parseFloat(document.getElementById('height').value) || 0;
                const heightUnit = document.getElementById('height').nextElementSibling.value;
                const waistVal = parseFloat(document.getElementById('waist').value) || 0;
                const waistUnit = document.getElementById('waist').nextElementSibling.value;
                const hipVal = parseFloat(document.getElementById('hip').value) || 0;
                const hipUnit = document.getElementById('hip').nextElementSibling.value;

                let weightKg = weightUnit === 'lbs' ? weightVal * 0.453592 : weightVal;
                let heightCm = heightVal;
                
                if (heightUnit === 'ft') {
                    // Try to handle ft'in" if present, otherwise treat as decimal ft
                    if (heightVal % 1 !== 0 || heightVal > 10) { // Likely cm in ft box or decimal
                         heightCm = heightVal * 30.48;
                    } else {
                         heightCm = heightVal * 30.48; // Simple decimal conversion
                    }
                }

                let waistCm = waistUnit === 'in' ? waistVal * 2.54 : waistVal;
                let hipCm = hipUnit === 'in' ? hipVal * 2.54 : hipVal;

                return { weightKg, heightCm, waistCm, hipCm };
            }

            function calculateAge(dob) {
                if (!dob) return 25;
                const birthDate = new Date(dob);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                return age;
            }

            function calculate() {
                const { weightKg, heightCm, waistCm, hipCm } = getStandardizedValues();
                const sex = document.getElementById('sex').value;
                const age = calculateAge(document.getElementById('dob').value);
                const activityMultiplier = {
                    'sedentary': 1.2,
                    'light': 1.375,
                    'moderate': 1.55,
                    'very': 1.725,
                    'extra': 1.9
                }[document.getElementById('activity').value] || 1.2;

                if (!weightKg || !heightCm || !sex) {
                    alert('Please enter your Sex, Weight, and Height to calculate.');
                    return;
                }

                // 1. Energy Requirement (Mifflin-St Jeor)
                let bmr;
                if (sex === 'male') {
                    bmr = (10 * weightKg) + (6.25 * heightCm) - (5 * age) + 5;
                } else {
                    bmr = (10 * weightKg) + (6.25 * heightCm) - (5 * age) - 161;
                }
                const tdee = Math.round(bmr * activityMultiplier);
                document.getElementById('energyResult').textContent = tdee;

                // 2. BMI
                const heightM = heightCm / 100;
                const bmi = (weightKg / (heightM * heightM)).toFixed(1);
                document.getElementById('bmiResult').textContent = bmi;
                
                let bmiStat = '--';
                if (bmi < 18.5) bmiStat = 'Underweight';
                else if (bmi < 25) bmiStat = 'Normal';
                else if (bmi < 30) bmiStat = 'Overweight';
                else bmiStat = 'Obese';
                document.getElementById('bmiStatus').textContent = bmiStat;

                // 3. Ratios
                if (waistCm && hipCm) {
                    const whr = (waistCm / hipCm).toFixed(2);
                    document.getElementById('whrResult').textContent = whr;
                    let whrStat = 'Normal';
                    if (sex === 'male' && whr > 0.9) whrStat = 'High Risk';
                    if (sex === 'female' && whr > 0.85) whrStat = 'High Risk';
                    document.getElementById('whrStatus').textContent = whrStat;
                }

                if (waistCm && heightCm) {
                    const whtr = (waistCm / heightCm).toFixed(2);
                    document.getElementById('whtrResult').textContent = whtr;
                    document.getElementById('whtrStatus').textContent = whtr > 0.5 ? 'High Risk' : 'Healthy';
                }

                // 4. Desirable Body Weight
                let dbw = 0;
                const heightIn = heightCm / 2.54;
                const inchesOver5ft = Math.max(0, heightIn - 60);

                if (currentMethod === 'tannhauser') {
                    dbw = (heightCm - 100) - ((heightCm - 100) * 0.1);
                } else if (currentMethod === 'hamwi') {
                    if (sex === 'male') {
                        dbw = 48 + (2.7 * inchesOver5ft);
                    } else {
                        dbw = 45 + (2.2 * inchesOver5ft);
                    }
                } else if (currentMethod === 'bmi') {
                    dbw = 22 * (heightM * heightM);
                } else { // Kilogram method (Standard recommendation for Filipino pop is simplified heights)
                    dbw = (heightCm - 100);
                    if (age < 40) dbw *= 0.9; // Simple variant
                }

                document.getElementById('weightResult').textContent = Math.round(dbw);
                
                // Track results fade-in
                document.querySelectorAll('.result-card').forEach(card => {
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.5s ease';
                        card.style.opacity = '1';
                    }, 50);
                });
            }

            calculateBtn.addEventListener('click', calculate);

            resetBtn.addEventListener('click', function() {
                const inputs = ['weight', 'height', 'waist', 'hip', 'dob', 'sex', 'activity'];
                inputs.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                
                ['energyResult', 'bmiResult', 'bmiStatus', 'whrResult', 'whrStatus', 'whtrResult', 'whtrStatus', 'weightResult'].forEach(id => {
                    document.getElementById(id).textContent = '--';
                });
                
                document.getElementById('weightUnit').textContent = 'kg';
            });
        });
    </script>
</body>
</html>
