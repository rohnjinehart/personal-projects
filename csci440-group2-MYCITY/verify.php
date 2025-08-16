<?php
require 'session.php';
require 'authFunctions.php';

// Database configuration
$servername = "[redacted]";
$username = "[redacted]";
$password = "[redacted]";
$dbname = "[redacted]";

// Change content type to HTML since we'll be outputting a redirect
header('Content-Type: text/html');

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Verify token from URL
    if (isset($_GET['token'])) {
        $token = $_GET['token'];
        
        // Find user with this token
        $stmt = $conn->prepare("SELECT id, verification_token_created_at FROM users WHERE verification_token = ? AND is_verified = 0");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Invalid or expired verification token.');
        }
        
        $user = $result->fetch_assoc();
        
        // Check if token is expired (24 hours)
        $tokenCreated = new DateTime($user['verification_token_created_at']);
        $now = new DateTime();
        $diff = $now->diff($tokenCreated);
        
        if ($diff->h + ($diff->days * 24) > 24) {
            throw new Exception('Verification token has expired. Please request a new one.');
        }
        
        // Mark user as verified
        $updateStmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, verification_token_created_at = NULL WHERE id = ?");
        $updateStmt->bind_param("i", $user['id']);
        
        if ($updateStmt->execute()) {
            // Output HTML with redirect to login page
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Email Verified</title>
                <meta http-equiv="refresh" content="3;url=login.html">
            </head>
            <body>
                <h1>Email verified successfully!</h1>
                <p>You will be redirected to the login page in 3 seconds. If not, <a href="login.html">click here</a>.</p>
                <script>
                    setTimeout(function() {
                        window.location.href = "login.html";
                    }, 3000);
                </script>
            </body>
            </html>';
        } else {
            throw new Exception('Failed to verify email. Please try again.');
        }
        
        $updateStmt->close();
    } else {
        throw new Exception('No verification token provided.');
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    // Output error message with option to go to login
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Verification Error</title>
    </head>
    <body>
        <h1>Error: ' . htmlspecialchars($e->getMessage()) . '</h1>
        <p><a href="login.html">Go to login page</a></p>
    </body>
    </html>';
}
?>