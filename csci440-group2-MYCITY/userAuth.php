<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database configuration
$servername = "[redacted]";
$username = "[redacted]";
$password = "[redacted]";
$dbname = "[redacted]";

// reCAPTCHA configuration
$secretKey = "6LfZsAUrAAAAAGdo5M0OaHpY-bmr9CZs7Ev8KRhx";

// Argon2 configuration
define('ARGON2_MEMORY_COST', 65536);  // 64MB
define('ARGON2_TIME_COST', 4);        // 4 iterations
define('ARGON2_THREADS', 1);          // 1 thread

// Include mailer functionality
require 'mailer.php';

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Handle registration
    if (isset($_POST['register'])) {
        // Verify reCAPTCHA
        if (isset($_POST['g-recaptcha-response'])) {
            $recaptchaResponse = $_POST['g-recaptcha-response'];
            $recaptchaUrl = "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$recaptchaResponse}";
            $recaptcha = file_get_contents($recaptchaUrl);
            $recaptcha = json_decode($recaptcha);
            
            if (!$recaptcha->success || $recaptcha->score < 0.5) {
                throw new Exception('reCAPTCHA verification failed. Please try again.');
            }
        } else {
            throw new Exception('reCAPTCHA token is missing.');
        }

        // Get form data
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $role = $_POST['role'] ?? 'employee';

        // Validate inputs
        $errors = [];
        
        if (empty($username)) $errors['username'] = 'Username is required';
        if (empty($email)) $errors['email'] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email format';
        if (empty($password)) $errors['password'] = 'Password is required';
        if (empty($full_name)) $errors['full_name'] = 'Full name is required';

        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['username'] = 'Username already taken';
        }
        $stmt->close();

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['email'] = 'Email already registered';
        }
        $stmt->close();

        // Validate password strength
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one number';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one special character';
        }

        // If there are errors, return them
        if (!empty($errors)) {
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
            exit;
        }

        // Hash the password with Argon2
        $hashed_password = password_hash(
            $password,
            PASSWORD_ARGON2ID,
            [
                'memory_cost' => ARGON2_MEMORY_COST,
                'time_cost' => ARGON2_TIME_COST,
                'threads' => ARGON2_THREADS
            ]
        );

        if ($hashed_password === false) {
            throw new Exception('Failed to hash password');
        }

        // Generate verification token and expiry
        $verification_token = bin2hex(random_bytes(32));
        $verification_token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Insert new user into database with verification data
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, phone_number, role, verification_token, verification_token_created_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssss", $username, $email, $hashed_password, $full_name, $phone_number, $role, $verification_token, $verification_token_expiry);

        if ($stmt->execute()) {
            // Prepare and send verification email
            $verificationLink = "http://localhost/CSCI440/verify.php?token=" . urlencode($verification_token);
            
            $emailSubject = "Verify Your Gridfix Account";
            $emailBody = "
                <h1>Welcome, {$full_name}!</h1>
                <p>Thank you for registering with Gridfix. Please verify your email address to activate your account.</p>
                <p><strong>Account Details:</strong></p>
                <ul>
                    <li>Username: {$username}</li>
                    <li>Registered Email: {$email}</li>
                    <li>Account Type: " . ucfirst($role) . "</li>
                </ul>
                <p>Click the link below to verify your email address:</p>
                <p><a href='{$verificationLink}'>Verify My Account</a></p>
                <p>This link will expire in 24 hours.</p>
                <p>If you didn't request this account, please ignore this email.</p>
            ";
            
            $emailSent = sendEmail($email, $emailSubject, $emailBody);
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful! Please check your email to verify your account.',
                'email_sent' => $emailSent
            ]);
        } else {
            throw new Exception('Registration failed: ' . $stmt->error);
        }

        $stmt->close();
        $conn->close();
        exit;
    }

    // Handle login
    if (isset($_POST['login'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            throw new Exception('Username and password are required');
        }

        // Find user by username
        $stmt = $conn->prepare("SELECT id, username, password_hash, role, is_verified FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Invalid username or password');
        }

        $user = $result->fetch_assoc();

        // Check if account is verified
        if (!$user['is_verified']) {
            throw new Exception('Your account is not verified. Please check your email for the verification link. <a href="resend_verification.php">Resend verification email</a>');
        }

        // Verify password with Argon2
        if (password_verify($password, $user['password_hash'])) {
            // Password is correct, now check if it needs rehashing
            if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID, [
                'memory_cost' => ARGON2_MEMORY_COST,
                'time_cost' => ARGON2_TIME_COST,
                'threads' => ARGON2_THREADS
            ])) {
                // Rehash the password if necessary
                $newHash = password_hash($password, PASSWORD_ARGON2ID, [
                    'memory_cost' => ARGON2_MEMORY_COST,
                    'time_cost' => ARGON2_TIME_COST,
                    'threads' => ARGON2_THREADS
                ]);
                
                $updateStmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newHash, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }

            // Start session and set user data
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            echo json_encode([
                'success' => true,
                'message' => 'Login successful!',
                'redirect' => 'dashboard.php'
            ]);
        } else {
            throw new Exception('Invalid username or password');
        }

        $stmt->close();
        $conn->close();
        exit;
    }

    // Handle verification resend request
    if (isset($_POST['resend_verification'])) {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            throw new Exception('Email address is required');
        }

        // Find user by email
        $stmt = $conn->prepare("SELECT id, username, full_name, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('No account found with that email address');
        }

        $user = $result->fetch_assoc();

        if ($user['is_verified']) {
            throw new Exception('This account is already verified');
        }

        // Generate new verification token
        $verification_token = bin2hex(random_bytes(32));
        $verification_token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Update user record with new token
        $updateStmt = $conn->prepare("UPDATE users SET verification_token = ?, verification_token_created_at = ? WHERE id = ?");
        $updateStmt->bind_param("ssi", $verification_token, $verification_token_expiry, $user['id']);
        $updateStmt->execute();

        // Send verification email
        $verificationLink = "http://localhost/CSCI440/verify.php?token=" . urlencode($verification_token);
        
        $emailSubject = "Verify Your Gridfix Account";
        $emailBody = "
            <h1>Hello, {$user['full_name']}!</h1>
            <p>We received a request to verify your Gridfix account.</p>
            <p>Click the link below to verify your email address:</p>
            <p><a href='{$verificationLink}'>Verify My Account</a></p>
            <p>This link will expire in 24 hours.</p>
            <p>If you didn't request this verification, please ignore this email.</p>
        ";
        
        $emailSent = sendEmail($email, $emailSubject, $emailBody);
        
        echo json_encode([
            'success' => true,
            'message' => 'Verification email sent! Please check your inbox.',
            'email_sent' => $emailSent
        ]);

        $updateStmt->close();
        $stmt->close();
        $conn->close();
        exit;
    }

    // Handle password reset request
    if (isset($_POST['request_password_reset'])) {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            throw new Exception('Email address is required');
        }
        
        // Check if email exists (but don't reveal if it doesn't)
        $stmt = $conn->prepare("SELECT id, username, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate reset token (expires in 1 hour)
            $reset_token = bin2hex(random_bytes(32));
            $reset_token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $updateStmt->bind_param("ssi", $reset_token, $reset_token_expiry, $user['id']);
            $updateStmt->execute();
            
            // Send reset email
            $resetLink = "http://localhost/CSCI440/reset_password.php?token=" . urlencode($reset_token);
            
            $emailSubject = "Password Reset Request";
            $emailBody = "
                <h1>Password Reset</h1>
                <p>Hello {$user['full_name']},</p>
                <p>We received a request to reset your password for your Gridfix account ({$user['username']}).</p>
                <p>Click the link below to reset your password:</p>
                <p><a href='{$resetLink}'>Reset Password</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request a password reset, please ignore this email.</p>
            ";
            
            $emailSent = sendEmail($email, $emailSubject, $emailBody);
        }
        
        // Always return success to prevent email enumeration
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists with this email, a password reset link has been sent.',
            'email_sent' => $emailSent ?? false
        ]);
        
        if (isset($updateStmt)) $updateStmt->close();
        $stmt->close();
        $conn->close();
        exit;
    }

    // Handle actual password reset (when token is used)
    if (isset($_POST['reset_password'])) {
        $token = $_POST['token'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $current_password = $_POST['current_password'] ?? null;
        
        // Check if this is a password change from profile (requires current password)
        if ($current_password !== null) {
            session_start();
            
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('You must be logged in to change your password');
            }
            
            // Get current password hash
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('User not found');
            }
            
            $user = $result->fetch_assoc();
            
            // Verify current password
            if (!password_verify($current_password, $user['password_hash'])) {
                throw new Exception('Current password is incorrect');
            }
            
            $user_id = $_SESSION['user_id'];
        } 
        // Otherwise it's a token-based reset
        else {
            if (empty($token)) {
                throw new Exception('Reset token is required');
            }
            
            // Check if token is valid and not expired
            $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Invalid or expired reset token');
            }
            
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
        }
        
        // Validate new password
        if (empty($new_password) || empty($confirm_password)) {
            throw new Exception('New password and confirmation are required');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }
        
        // Validate password strength
        if (strlen($new_password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }
        if (!preg_match('/[A-Z]/', $new_password)) {
            throw new Exception('Password must contain at least one uppercase letter');
        }
        if (!preg_match('/[0-9]/', $new_password)) {
            throw new Exception('Password must contain at least one number');
        }
        if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            throw new Exception('Password must contain at least one special character');
        }
        
        // Hash the new password
        $hashed_password = password_hash(
            $new_password,
            PASSWORD_ARGON2ID,
            [
                'memory_cost' => ARGON2_MEMORY_COST,
                'time_cost' => ARGON2_TIME_COST,
                'threads' => ARGON2_THREADS
            ]
        );
        
        // Update password and clear reset token if this was a token-based reset
        if ($current_password === null) {
            $updateStmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        } else {
            $updateStmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        }
        $updateStmt->bind_param("si", $hashed_password, $user_id);
        $updateStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully!'
        ]);
        
        $updateStmt->close();
        $stmt->close();
        $conn->close();
        exit;
    }

    // Default response if no action specified
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>