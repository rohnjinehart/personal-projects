<?php
// Include session management file and ensure user is logged in
require_once 'session.php';
requireLogin();

// Database connection
$servername = "[redacted]";
$username_db = "[redacted]";
$password = "[redacted]";
$dbname = "[redacted]";

// Create connection
$conn = new mysqli($servername, $username_db, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current user data
$currentUserId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] === 'admin');
$isEmployee = ($_SESSION['role'] === 'employee');
$isSupervisor = ($_SESSION['role'] === 'supervisor');
$editingUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $currentUserId;

// Get user data for the profile being viewed
$sql = "SELECT username, role, full_name, phone_number, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $editingUserId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData) {
    die("User not found");
}

// Get user's current skills
$userSkills = [];
$sql = "SELECT s.id, s.name FROM skills s 
        JOIN user_skills us ON s.id = us.skill_id 
        WHERE us.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $editingUserId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $userSkills[] = $row;
}
$stmt->close();

// Get all available skills
$allSkills = [];
$sql = "SELECT id, name FROM skills ORDER BY name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $allSkills[] = $row;
}

// Handle skill updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_skills'])) {
    // Only allow users to edit their own skills unless admin
    if ($editingUserId != $currentUserId && !$isAdmin) {
        die("You can only edit your own skills");
    }
    
    $selectedSkills = isset($_POST['skills']) ? $_POST['skills'] : [];
    
    // Clear current skills
    $stmt = $conn->prepare("DELETE FROM user_skills WHERE user_id = ?");
    $stmt->bind_param("i", $editingUserId);
    $stmt->execute();
    $stmt->close();
    
    // Add new skills
    if (!empty($selectedSkills)) {
        $stmt = $conn->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?, ?)");
        foreach ($selectedSkills as $skillId) {
            $skillId = intval($skillId);
            $stmt->bind_param("ii", $editingUserId, $skillId);
            $stmt->execute();
        }
        $stmt->close();
    }
    
    // Refresh the page to show updated skills
    header("Location: profile.php?user_id=" . $editingUserId);
    exit;
}

// Handle role update if submitted by admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_role']) && $isAdmin) {
    $newRole = in_array($_POST['role'], ['employee', 'supervisor', 'admin']) ? $_POST['role'] : 'employee';
    
    // Prevent admins from demoting themselves
    if ($editingUserId == $currentUserId && $newRole != 'admin') {
        die("You cannot remove your own admin privileges");
    }
    
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $newRole, $editingUserId);
    $stmt->execute();
    $stmt->close();
    
    // Refresh the page to show updated role
    header("Location: profile.php?user_id=" . $editingUserId);
    exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // Only allow users to change their own password
    if ($editingUserId != $currentUserId) {
        die("You can only change your own password");
    }
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        die("All password fields are required");
    }
    
    if ($newPassword !== $confirmPassword) {
        die("New passwords do not match");
    }
    
    // Get current password hash
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die("User not found");
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        die("Current password is incorrect");
    }
    
    // Validate password strength
    if (strlen($newPassword) < 8) {
        die("Password must be at least 8 characters long");
    }
    if (!preg_match('/[A-Z]/', $newPassword)) {
        die("Password must contain at least one uppercase letter");
    }
    if (!preg_match('/[0-9]/', $newPassword)) {
        die("Password must contain at least one number");
    }
    if (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
        die("Password must contain at least one special character");
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $currentUserId);
    $stmt->execute();
    $stmt->close();
    
    // Redirect with success message
    header("Location: profile.php?password_changed=1");
    exit;
}

// Get assigned reports for the current user
$assignedReports = [];
$sql = "SELECT id, title FROM reports WHERE assigned_to = ? AND status = 'accepted'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $editingUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $assignedReports[] = $row;
    }
}
$stmt->close();

// Get list of all users for dropdown (available to admins, supervisors, and employees)
$userList = [];
if ($isAdmin || $isSupervisor || $isEmployee) {
    // Admins can see all users, supervisors/employees can only see non-admin users
    $sql = "SELECT id, username, role FROM users ";
    if (!$isAdmin) {
        $sql .= "WHERE role != 'admin' ";
    }
    $sql .= "ORDER BY username";
    
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $userList[] = $row;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($userData['username']) ?>'s Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="messaging-widget.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .profile-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-icon {
            font-size: 5rem;
            color: #0d6efd;
            margin-bottom: 20px;
        }
        .profile-info dt {
            font-weight: 600;
            color: #495057;
        }
        .profile-info dd {
            margin-bottom: 15px;
            padding: 8px 12px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .user-menu {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .assigned-reports {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .report-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .role-badge {
            font-size: 0.8rem;
        }
        .user-select-dropdown .dropdown-menu {
            max-height: 300px;
            overflow-y: auto;
        }
        .user-search-container {
            position: relative;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .user-search-input {
            width: 100%;
            padding: 5px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .no-users-found {
            padding: 10px;
            color: #6c757d;
            font-style: italic;
            text-align: center;
        }
        .report-actions {
            display: flex;
            gap: 5px;
        }
        .dropdown-item .badge {
            cursor: pointer;
        }
        .skills-container {
            margin-top: 20px;
        }
        .skills-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 10px;
        }
        .skill-tag {
            background-color: #e9ecef;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .no-skills {
            color: #6c757d;
            font-style: italic;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .viewing-notice {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: -15px;
            margin-bottom: 15px;
        }
        
        .unread-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .messaging-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .new-conversation-btn {
            padding: 10px 15px;
            border-top: 1px solid #eee;
            text-align: center;
            background: #f8f9fa;
        }
        
        .new-conversation-btn button {
            width: 100%;
            padding: 8px;
            border-radius: 20px;
            background: #0d6efd;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .new-conversation-btn button:hover {
            background: #0b5ed7;
        }
    </style>
</head>
<body data-user-id="<?php echo $currentUserId ?>">

    <div class="container">
        <!-- User menu dropdown -->
        <div class="user-menu">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']) ?>
                </button>
                <!-- In the user menu dropdown section, update the dropdown menu with this: -->
<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
    <?php if ($isAdmin): ?>
        <li><a class="dropdown-item" href="profile.php?user_id=<?php echo $currentUserId ?>"><i class="bi bi-people me-2"></i>Manage Users</a></li>
    <?php endif; ?>
    <li>
    <a class="dropdown-item" href="#" onclick="window.messagingWidget.toggleWidget(); return false;">
        <i class="bi bi-chat-dots me-2"></i>Messages
        <span class="unread-badge">0</span>
        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
    </a>
</li>
            </div>
        </div>

        <div class="profile-container">
            <!-- Success message for password change -->
            <?php if (isset($_GET['password_changed'])): ?>
                <div class="alert-success">
                    Password changed successfully!
                </div>
            <?php endif; ?>

            <!-- User selection dropdown for admins, supervisors, and employees with search -->
            <?php if (($isAdmin || $isSupervisor || $isEmployee) && !empty($userList)): ?>
                <div class="user-select-dropdown mb-4">
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle w-100" type="button" id="userListDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-people-fill"></i> Viewing: <?php echo htmlspecialchars($userData['username']) ?>
                            <span class="badge bg-<?php 
                                echo $userData['role'] === 'admin' ? 'danger' : 
                                     ($userData['role'] === 'supervisor' ? 'warning' : 'primary'); 
                            ?> ms-2">
                                <?php echo ucfirst($userData['role']) ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu w-100" aria-labelledby="userListDropdown">
                            <li class="user-search-container">
                                <input type="text" class="user-search-input" placeholder="Search users..." id="userSearchInput" onkeyup="filterUserDropdown()">
                            </li>
                            <div id="userDropdownList">
                                <?php foreach ($userList as $user): ?>
                                    <?php 
                                        // Only show admin users to other admins
                                        if ($user['role'] === 'admin' && !$isAdmin) continue;
                                    ?>
                                    <li class="user-dropdown-item">
                                        <a class="dropdown-item d-flex justify-content-between align-items-center" 
                                           href="profile.php?user_id=<?php echo $user['id'] ?>">
                                            <?php echo htmlspecialchars($user['username']) ?>
                                            <span class="badge bg-<?php 
                                                echo $user['role'] === 'admin' ? 'danger' : 
                                                     ($user['role'] === 'supervisor' ? 'warning' : 'primary'); 
                                            ?> role-badge">
                                                <?php echo ucfirst($user['role']) ?>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </div>
                            <li id="noUsersFound" class="no-users-found" style="display: none;">
                                No users found matching your search
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Profile header -->
            <div class="profile-header">
                <div class="profile-icon">
                    <i class="bi bi-person-circle"></i>
                </div>
                <h2><?php echo htmlspecialchars($userData['username']) ?>'s Profile</h2>
                <?php if ($editingUserId != $currentUserId): ?>
                    <p class="viewing-notice">Viewing another user's profile</p>
                <?php endif; ?>
            </div>

            <!-- Profile information -->
            <dl class="profile-info">
                <dt>Username</dt>
                <dd><?php echo htmlspecialchars($userData['username']) ?></dd>

                <dt>Role</dt>
                <dd>
                    <?php if ($isAdmin && $editingUserId != $currentUserId): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="user_id" value="<?php echo $editingUserId ?>">
                            <select name="role" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                <option value="employee" <?php echo $userData['role'] === 'employee' ? 'selected' : '' ?>>Employee</option>
                                <option value="supervisor" <?php echo $userData['role'] === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
                                <option value="admin" <?php echo $userData['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <input type="hidden" name="update_role" value="1">
                        </form>
                    <?php else: ?>
                        <span class="badge bg-<?php 
                            echo $userData['role'] === 'admin' ? 'danger' : 
                                 ($userData['role'] === 'supervisor' ? 'warning' : 'primary'); 
                        ?>">
                            <?php echo ucfirst($userData['role']) ?>
                        </span>
                    <?php endif; ?>
                </dd>

                <dt>Full Name</dt>
                <dd><?php echo htmlspecialchars($userData['full_name'] ?? 'Not specified') ?></dd>

                <dt>Phone Number</dt>
                <dd><?php echo htmlspecialchars($userData['phone_number'] ?? 'Not specified') ?></dd>

                <dt>Member Since</dt>
                <dd><?php echo date('F j, Y', strtotime($userData['created_at'])) ?></dd>

                <!-- Password Change Section -->
                <?php if ($editingUserId == $currentUserId): ?>
                    <dt>Password</dt>
                    <dd>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#passwordChangeModal">
                            <i class="bi bi-key"></i> Change Password
                        </button>
                    </dd>
                <?php endif; ?>

                <!-- Technical Skills Section -->
                <dt>Technical Skills</dt>
                <dd>
                    <?php if ($editingUserId == $currentUserId || $isAdmin): ?>
                        <form method="post">
                            <select name="skills[]" class="form-select skill-select" multiple size="5">
                                <?php foreach ($allSkills as $skill): ?>
                                    <option value="<?php echo $skill['id'] ?>" 
                                        <?php echo in_array($skill, $userSkills) ? 'selected' : '' ?>>
                                        <?php echo htmlspecialchars($skill['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_skills" value="1">
                            <button type="submit" class="btn btn-sm btn-primary mt-2">Update Skills</button>
                        </form>
                    <?php else: ?>
                        <div class="skills-tags">
                            <?php if (!empty($userSkills)): ?>
                                <?php foreach ($userSkills as $skill): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill['name']) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="no-skills">No skills specified</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </dd>
            </dl>

            <!-- Assigned Reports Section -->
            <?php if ($editingUserId == $currentUserId || $isSupervisor || $isAdmin): ?>
                <div class="assigned-reports">
                    <h5><i class="bi bi-list-check"></i> Assigned Reports</h5>
                    <?php if (!empty($assignedReports)): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle w-100" type="button" id="assignedReportsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                View Assigned Reports (<?php echo count($assignedReports) ?>)
                            </button>
                            <ul class="dropdown-menu w-100" aria-labelledby="assignedReportsDropdown">
                                <?php foreach ($assignedReports as $report): ?>
                                    <li>
                                        <a class="dropdown-item d-flex justify-content-between align-items-center" 
                                           href="#" 
                                           onclick="viewReportOnMap(<?php echo $report['id'] ?>)">
                                            <?php echo htmlspecialchars($report['title']) ?>
                                            <div class="report-actions">
                                                <span class="badge bg-primary rounded-pill">
                                                    <i class="bi bi-map"></i> View
                                                </span>
                                                <?php if ($editingUserId == $currentUserId && $isEmployee): ?>
                                                    <span class="badge bg-success rounded-pill" 
                                                          onclick="event.stopPropagation(); completeReport(<?php echo $report['id'] ?>)">
                                                        <i class="bi bi-check-circle"></i> Complete
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No reports currently assigned.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Back button -->
            <div class="d-grid gap-2 mt-4">
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Password Change Modal -->
    <?php if ($editingUserId == $currentUserId): ?>
        <div class="modal fade" id="passwordChangeModal" tabindex="-1" aria-labelledby="passwordChangeModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="passwordChangeModalLabel">Change Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="passwordChangeForm" method="post">
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="newPassword" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="newPassword" name="new_password" required>
                                <div class="form-text">Password must be at least 8 characters long and contain uppercase, number, and special character.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                            </div>
                            <div id="passwordChangeMessage" class="alert" style="display: none;"></div>
                            <button type="submit" class="btn btn-primary" name="change_password">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap JavaScript bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function viewReportOnMap(reportId) {
            localStorage.setItem('reportToView', JSON.stringify({
                reportId: reportId,
                timestamp: new Date().getTime()
            }));
            window.location.href = 'index.php#map-container';
        }

        function filterUserDropdown() {
            const input = document.getElementById('userSearchInput');
            const filter = input.value.toUpperCase();
            const items = document.querySelectorAll('.user-dropdown-item');
            const noUsersFound = document.getElementById('noUsersFound');
            let hasVisibleItems = false;

            items.forEach(item => {
                const text = item.textContent || item.innerText;
                if (text.toUpperCase().indexOf(filter) > -1) {
                    item.style.display = '';
                    hasVisibleItems = true;
                } else {
                    item.style.display = 'none';
                }
            });

            noUsersFound.style.display = hasVisibleItems ? 'none' : 'block';
        }

        function completeReport(reportId) {
            if (!confirm('Are you sure you want to mark this report as pending completion?')) return;
            
            fetch('ReportActions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'complete',
                    reportId: reportId
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.message || 'Action failed'); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Action failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'An error occurred');
            });
        }

        // Initialize the skills select with a better UI
        document.addEventListener('DOMContentLoaded', function() {
            const skillSelect = document.querySelector('.skill-select');
            if (skillSelect) {
                // This enhances the multi-select experience
                skillSelect.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    
                    const option = e.target;
                    if (option.tagName === 'OPTION') {
                        option.selected = !option.selected;
                    }
                });
            }

            // Handle password change form submission
            const passwordChangeForm = document.getElementById('passwordChangeForm');
            if (passwordChangeForm) {
                passwordChangeForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(passwordChangeForm);
                    const messageEl = document.getElementById('passwordChangeMessage');
                    
                    fetch('profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.redirected) {
                            window.location.href = response.url;
                        } else {
                            return response.text();
                        }
                    })
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data.error) {
                                messageEl.style.display = 'block';
                                messageEl.className = 'alert-danger';
                                messageEl.textContent = data.error;
                            }
                        } catch (e) {
                            // Handle non-JSON response (shouldn't happen with our current implementation)
                            console.error('Error parsing response:', e);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        messageEl.style.display = 'block';
                        messageEl.className = 'alert-danger';
                        messageEl.textContent = 'An error occurred. Please try again.';
                    });
                });
            }
        });
    </script>
    <script src="messagingWidget.js"></script>
</body>
</html>