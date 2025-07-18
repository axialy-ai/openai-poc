<?php
// /content_review_form.php

require_once 'includes/db_connection.php'; // Ensure this path is correct

// Function to sanitize input data
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Retrieve the token from the URL
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    die('Invalid or missing token.');
}

// Fetch the review request from the database
$stmt = $pdo->prepare("SELECT * FROM content_reviews WHERE token = :token AND completed = 0");
$stmt->execute([':token' => $token]);
$review = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$review) {
    die('Invalid or expired token.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback = isset($_POST['feedback']) ? sanitizeInput($_POST['feedback']) : '';

    if (empty($feedback)) {
        $error = "Feedback cannot be empty.";
    } else {
        // Update the review as completed and store the feedback
        $updateStmt = $pdo->prepare("UPDATE content_reviews SET feedback = :feedback, completed = 1, reviewed_at = NOW() WHERE id = :id");
        $updateSuccess = $updateStmt->execute([
            ':feedback' => $feedback,
            ':id' => $review['id']
        ]);

        if ($updateSuccess) {
            $success = "Thank you for your feedback.";
        } else {
            $error = "An error occurred while submitting your feedback. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Content Review</title>
    <link rel="stylesheet" href="assets/css/overlay.css">
    <link rel="stylesheet" href="assets/css/refine-tab.css"> <!-- Ensure this path is correct -->
    <style>
        /* Additional styling specific to the review form */
        .review-form-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .review-form-container h2 {
            margin-top: 0;
            color: #333333;
        }
        .review-form-container label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #555555;
        }
        .review-form-container textarea {
            width: 100%;
            height: 200px;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            resize: vertical;
            font-size: 14px;
            box-sizing: border-box;
        }
        .review-form-container .form-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .review-form-container .form-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .review-form-container .form-actions .submit-btn {
            background-color: #28a745;
            color: #ffffff;
            transition: background-color 0.3s;
        }
        .review-form-container .form-actions .submit-btn:hover {
            background-color: #218838;
        }
        .review-form-container .form-actions .cancel-btn {
            background-color: #dc3545;
            color: #ffffff;
            transition: background-color 0.3s;
        }
        .review-form-container .form-actions .cancel-btn:hover {
            background-color: #c82333;
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
        }
        .success-message {
            color: #28a745;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="overlay">
        <div class="review-form-container">
            <?php if (isset($error)): ?>
                <p class="error-message"><?php echo $error; ?></p>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <p class="success-message"><?php echo $success; ?></p>
            <?php endif; ?>
            <?php if (!isset($success)): ?>
                <h2>Content Review</h2>
                <p>Please provide your feedback below:</p>
                <form method="POST" action="">
                    <label for="feedback">Feedback:</label>
                    <textarea name="feedback" id="feedback" required><?php echo isset($_POST['feedback']) ? $_POST['feedback'] : ''; ?></textarea>
                    <div class="form-actions">
                        <button type="submit" class="submit-btn">Submit Feedback</button>
                        <button type="button" class="cancel-btn" onclick="window.close();">Cancel</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
