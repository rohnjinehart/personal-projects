<?php
// Database configuration
$servername = "[redacted]";
$username = "[redacted]";
$password = "[redacted]";
$dbname = "[redacted]";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

header('Content-Type: text/html');
$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($token)) {
        $error = 'Reset token is missing';
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Use the same password validation as in userAuth.php
        if (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $error = 'Password must contain at least one uppercase letter';
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $error = 'Password must contain at least one number';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $error = 'Password must contain at least one special character';
        } else {
            // Check if token is valid and not expired
            $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = 'Invalid or expired reset token';
            } else {
                $user = $result->fetch_assoc();
                
                // Use Argon2 hashing to match userAuth.php
                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_ARGON2ID,
                    [
                        'memory_cost' => 65536,  // 64MB
                        'time_cost' => 4,       // 4 iterations
                        'threads' => 1           // 1 thread
                    ]
                );
                
                if ($hashed_password === false) {
                    $error = 'Failed to hash password';
                } else {
                    // Update password and clear reset token
                    $updateStmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
                    $updateStmt->bind_param("si", $hashed_password, $user['id']);
                    
                    if ($updateStmt->execute()) {
                        $success = true;
                    } else {
                        $error = 'Failed to update password';
                    }
                }
            }
        }
    }
} elseif (!empty($token)) {
    // Verify token is valid when page is first loaded
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = 'Invalid or expired reset token';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        /* Use the same styling as login.html for consistency */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #e0e0e0;
        }
        .container {
            width: 90%;
            max-width: 400px;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        form {
            display: flex;
            flex-direction: column;
        }
        input, button {
            margin: 10px 0;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            background-color: #007bff;
            color: #fff;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            display: block;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        
        <?php if ($success): ?>
            <div class="message success">
                Password reset successfully! You can now <a href="login.html">login</a> with your new password.
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <p><a href="login.html">Return to login</a></p>
        <?php elseif (!empty($token)): ?>
            <form method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="password" name="new_password" placeholder="New Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                <button type="submit">Reset Password</button>
            </form>
        <?php else: ?>
            <div class="message error">
                No reset token provided. Please use the link from your email.
            </div>
            <p><a href="login.html">Return to login</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>