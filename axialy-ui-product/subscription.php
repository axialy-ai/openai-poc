<?php
// subscription.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Load new config
require_once '/home/i17z4s936h3j/private_axiaba/includes/Config.php';
use AxiaBA\Config\Config;
$config = Config::getInstance();

require_once __DIR__ . '/vendor/autoload.php';
require_once 'includes/db_connection.php';
require_once 'includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['PHP_SELF']));
    exit;
}

// Attempt to retrieve user email from database
try {
    $stmt = $pdo->prepare('SELECT user_email FROM ui_users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $userEmail = $stmt->fetchColumn();
    if (!$userEmail) {
        throw new Exception('User email not found');
    }
} catch (Exception $e) {
    error_log('Error fetching user email: ' . $e->getMessage());
    die('An error occurred. Please try again later.');
}

// Optional: configure SSL certificates if needed
putenv("CURL_CA_BUNDLE=/home/i17z4s936h3j/public_html/certs/cacert.pem");
ini_set('curl.cainfo', '/home/i17z4s936h3j/public_html/certs/cacert.pem');
ini_set('openssl.cafile', '/home/i17z4s936h3j/public_html/certs/cacert.pem');

// Use Stripe API key from config
\Stripe\Stripe::setApiKey($config['stripe_api_key']);

/**
 * Handle POST requests: 
 * (Stripe subscription creation code here) ...
 */

// If GET, create a SetupIntent...
try {
    $setupIntent = \Stripe\SetupIntent::create([
        'payment_method_types' => ['card'],
        'usage' => 'off_session',
    ]);
    $clientSecret = $setupIntent->client_secret;
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Setup Intent error: ' . $e->getMessage());
    die('An error occurred while initializing the payment process. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- 1) Make the page responsive -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to Axiaba</title>
    <script src="https://js.stripe.com/v3/"></script>
    
    <!-- 2) Basic mobile-friendly adjustments -->
    <style>
      body {
        margin: 0; 
        font-family: Arial, sans-serif; 
        background: #f8f8f8; 
      }
      .container {
        /* Let it fill the screen and have a little padding */
        max-width: 100%;
        margin: 0 auto;
        padding: 16px;
        box-sizing: border-box;
      }
      h1 {
        margin-top: 0;
        text-align: center;
      }

      /* The plan cards container: use flex but allow wrap for small screens */
      .plans-container {
        display: flex;
        flex-wrap: wrap; /* let them wrap in narrow view */
        gap: 20px;
        justify-content: center; /* center them horizontally */
        margin-bottom: 40px;
      }
      /* Each plan card: fluid width so it shrinks on small screens */
      .plan-card {
        flex: 1 1 280px; /* minimum ~280px wide, then expand */
        max-width: 350px;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        background: white;
        position: relative;
        transition: transform 0.2s;
        cursor: pointer;
      }
      .plan-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      }
      .plan-card.selected {
        border: 2px solid #007bff;
      }
      .plan-name {
        font-size: 1.2rem;
        font-weight: bold;
        margin-bottom: 10px;
      }
      .plan-price {
        font-size: 1.8rem;
        color: #007bff;
        margin-bottom: 10px;
      }
      .plan-details {
        margin-bottom: 20px;
      }
      .plan-features {
        text-align: left;
        margin-bottom: 20px;
        padding-left: 20px;
      }
      .plan-features li {
        margin-bottom: 8px;
        list-style-type: none;
        position: relative;
      }
      .plan-features li:before {
        content: "✓";
        color: #28a745;
        position: absolute;
        left: -20px;
      }
      
      /* The payment form: let it flow in mobile */
      #payment-form {
        width: 100%;
        max-width: 500px; 
        margin: 0 auto 30px auto;
        background: #fff;
        border: 1px solid #ddd;
        padding: 16px;
        border-radius: 8px;
      }
      #card-element {
        margin: 20px 0;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        background: white;
      }
      #submit-button {
        width: 100%;
        padding: 12px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
      }
      #submit-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
      #error-message {
        color: #dc3545;
        margin-top: 10px;
        padding: 10px;
      }
      .savings-badge {
        position: absolute;
        top: -10px;
        right: -10px;
        background: #28a745;
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 14px;
      }
      
      /* The "I have a promo code" section: */
      .promo-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #007bff;
        cursor: pointer;
        text-decoration: underline;
      }
      #promo-code-container {
        display: none;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 16px;
        margin-top: 16px;
      }
      #promo-code-container h3 {
        margin-top: 0;
      }
      #promo-code-container input[type="text"] {
        width: 100%;
        padding: 8px;
        font-size: 16px;
        margin-bottom: 12px;
        box-sizing: border-box;
      }
      #promo-submit-btn {
        width: 100%;
        padding: 12px;
        background: #28a745;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
      }
      #promo-submit-btn:hover {
        background: #218838;
      }
      #promo-error {
        color: #dc3545;
        margin-top: 10px;
        display: none;
      }
      #promo-success {
        color: #28a745;
        margin-top: 10px;
        display: none;
      }
      
      /* Statement text and checkbox container */
      #promo-statement-text {
        display: none;
        margin: 15px 0;
      }
      #promo-statement-checkbox-container {
        display: none;
        margin-bottom: 15px;
      }
      #promo-statement-label {
        margin-left: 5px;
      }
      
      /* A little spacing at the bottom */
      body {
        margin-bottom: 60px;
      }
      
      /* Media query for narrower screens (optional) */
      @media (max-width: 600px) {
        .plan-card {
          flex: 1 1 100%;
          max-width: 100%;
        }
      }
    </style>
</head>
<body>
<!-- BRANDING CONTAINER -->
<div style="text-align: center; margin: 20px 0;">
  <a href="https://axialy.ai" target="_blank" rel="noopener noreferrer">
    <img src="/assets/img/product_logo.png" alt="AxiaBA Logo" style="max-height:50px;">
  </a>
</div>
<!-- END BRANDING -->

<div class="container">
    <h1>Choose Your AxiaBA Plan</h1>
    
    <div class="plans-container">
        <div class="plan-card" data-plan="monthly">
            <div class="plan-name">Monthly Subscription</div>
<!--
            <div class="plan-price">$65/month</div>
-->
            <div class="plan-price">$125/month</div>
            <div class="plan-details">
                <ul class="plan-features">
                    <li>Full access to all features</li>
                    <li>24/7 service & support</li>
                    <li>Unlimited stakeholder feedback</li>
                    <li>Cancel anytime</li>
                </ul>
            </div>
        </div>
        
        <div class="plan-card" data-plan="yearly">
            <div class="savings-badge">Save 15%</div>
            <div class="plan-name">Yearly Subscription</div>
<!--
            <div class="plan-price">$663/year</div>
-->
            <div class="plan-price"><code>available soon</code></div>
            <div class="plan-details">
                <ul class="plan-features">
                    <li>All monthly features</li>
                    <li>24/7 Service and Support</li>
                    <li>Best value option</li>
                    <li>Cancel anytime</li>
                </ul>
            </div>
        </div>
        
        <div class="plan-card" data-plan="day">
            <div class="plan-name">24-Hour Day Pass</div>
            <div class="plan-price">$10</div>
            <div class="plan-details">
                <ul class="plan-features">
                    <li>One-time purchase</li>
                    <li>24-hour full access</li>
                    <li>Data persists between passes</li>
                    <li>Stakeholder feedback remains active</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Stripe Payment Form -->
    <form id="payment-form">
        <div id="card-element"></div>
        <button id="submit-button" type="submit" disabled>Select a plan to continue</button>
        <div id="error-message"></div>
    </form>

    <!-- Promo Code UI -->
    <div>
        <span class="promo-link" id="show-promo-code-link">I have a promo code!</span>
    </div>
    <div id="promo-code-container">
        <h3>Redeem Promo Code</h3>
        
        <input type="text" id="promo-code-input" placeholder="Enter your promo code">
        
        <!-- Paragraph for statement text (hidden by default) -->
        <p id="promo-statement-text"></p>
        
        <!-- Checkbox and label for user confirmation (hidden by default) -->
        <div id="promo-statement-checkbox-container">
            <input type="checkbox" id="promo-statement-cb">
            <label for="promo-statement-cb" id="promo-statement-label">
                By checking this box, I confirm that I have read, fully understand, and agree to the statement above.
            </label>
        </div>
        
        <button id="promo-submit-btn">Apply Promo</button>
        
        <div id="promo-error"></div>
        <div id="promo-success"></div>
    </div>
</div>

<script>
// ================== Stripe Setup ======================
const stripe = Stripe('<?php echo $config['stripe_publishable_key']; ?>');
const clientSecret = '<?php echo $clientSecret; ?>';

const elements = stripe.elements();
const cardElement = elements.create('card');
cardElement.mount('#card-element');

const form = document.getElementById('payment-form');
const errorMessage = document.getElementById('error-message');
const submitButton = document.getElementById('submit-button');

let selectedPlan = null;

// Plan selection toggling
document.querySelectorAll('.plan-card').forEach(card => {
  card.addEventListener('click', () => {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedPlan = card.dataset.plan;
    submitButton.disabled = false;
    submitButton.textContent = getButtonText(selectedPlan);
  });
});

function getButtonText(plan) {
  switch(plan) {
    case 'monthly': return 'Subscribe Now - $125/month';
//    case 'yearly':  return 'Subscribe Now - $663/year';
//    case 'monthly': return 'Contact support for subscription pricing';
    case 'yearly':  return 'Contact support for yearly pricing';
    case 'day':     return 'Purchase Day Pass - $10';
    default:        return 'Select a plan to continue';
  }
}

// On form submit: Example of how you'd finalize the SetupIntent
// and then call your own backend to actually create a Subscription
form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!selectedPlan) {
    errorMessage.textContent = 'Please select a plan first';
    return;
  }
  
  submitButton.disabled = true;
  errorMessage.textContent = '';

  try {
    // 1) Confirm the card setup so we have a PaymentMethod
    const result = await stripe.confirmCardSetup(clientSecret, {
      payment_method: { card: cardElement }
    });
    if (result.error) {
      throw new Error(result.error.message);
    }

    // 2) Send the PaymentMethod ID + plan info to your server to create a subscription
    //    (Below is just a placeholder example calling a hypothetical endpoint)
    const createSubResponse = await fetch('/create_subscription.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        planType: selectedPlan,
        paymentMethodId: result.setupIntent.payment_method
      })
    });

    const data = await createSubResponse.json();
    if (!data.success) {
      throw new Error(data.message || 'Subscription creation failed.');
    }

    // 3) If subscription creation is successful, go to main app
    window.location.href = '/index.php';
  } catch (error) {
    console.error('Error:', error);
    errorMessage.textContent = error.message;
    submitButton.disabled = false;
    // Optionally clear card fields if needed
    // cardElement.clear();
  }
});

// ============= Promo Code Redemption ===============
const showPromoCodeLink = document.getElementById('show-promo-code-link');
const promoCodeContainer = document.getElementById('promo-code-container');
const promoSubmitBtn = document.getElementById('promo-submit-btn');
const promoError = document.getElementById('promo-error');
const promoSuccess = document.getElementById('promo-success');
const promoCodeInput = document.getElementById('promo-code-input');
const promoStatementText = document.getElementById('promo-statement-text');
const promoStatementCbContainer = document.getElementById('promo-statement-checkbox-container');
const promoStatementCb = document.getElementById('promo-statement-cb');
const promoStatementLabel = document.getElementById('promo-statement-label');

showPromoCodeLink.addEventListener('click', () => {
  promoCodeContainer.style.display = 'block';
});

// Redeem Promo Code when user clicks "Apply Promo"
promoSubmitBtn.addEventListener('click', async () => {
  const codeValue = promoCodeInput.value.trim();
  if (!codeValue) {
    promoError.textContent = 'Please enter a promo code.';
    promoError.style.display = 'block';
    promoSuccess.style.display = 'none';
    return;
  }
  
  // Clear old messages
  promoError.style.display = 'none';
  promoSuccess.style.display = 'none';

  try {
    const resp = await fetch('/redeem_promo_code.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        promo_code: codeValue,
        acceptStatement: promoStatementCb.checked
      })
    });

    const result = await resp.json();

    if (!result.success) {
      // Show error
      promoError.textContent = result.message || 'Promo code redemption failed.';
      promoError.style.display = 'block';

      // If statement is required, show statement text + checkbox
      if (result.statementRequired) {
        promoStatementText.style.display = 'block';
        promoStatementCbContainer.style.display = 'block';
        // Insert the statement's HTML (use innerHTML so any formatting is preserved)
        promoStatementText.innerHTML = result.statementLabel;
        // Force the user to re-check
        promoStatementCb.checked = false;
      } else {
        // Otherwise hide them
        promoStatementText.style.display = 'none';
        promoStatementCbContainer.style.display = 'none';
      }
    } else {
      // Success
      promoSuccess.textContent = 'Promo code redeemed successfully! Redirecting...';
      promoSuccess.style.display = 'block';
      setTimeout(() => {
        window.location.href = '/index.php';
      }, 2000);
    }
  } catch (err) {
    console.error(err);
    promoError.textContent = 'An error occurred. Please try again.';
    promoError.style.display = 'block';
  }
});
</script>
</body>
</html>
