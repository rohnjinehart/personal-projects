<?php
require_once 'session.php';
requireLogin();
require_once 'authFunctions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Database connection configuration
$servername = "[redacted]";
$username = "[redacted]";
$password = "[redacted]";
$dbname = "[redacted]";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get JSON input from request body
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid JSON input']));
}

// Validate required fields
$action = $input['action'] ?? '';
$reportId = $input['reportId'] ?? null;
$employeeId = $input['employeeId'] ?? null;

if (empty($action)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Action is required']));
}

if (empty($reportId)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Report ID is required']));
}

try {
    // Check if report exists
    $checkStmt = $conn->prepare("SELECT id, assigned_to, status FROM reports WHERE id = ?");
    $checkStmt->bind_param("i", $reportId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Report not found']));
    }

    $report = $checkResult->fetch_assoc();
    $isAssignedToCurrentUser = ($report['assigned_to'] == $_SESSION['user_id']);
    $currentStatus = $report['status'];

    // Process different actions
    switch ($action) {
        case 'accept':
            if (!isEmployee()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Unauthorized']));
            }
            
            // Only allow accepting unassigned reports or reports assigned to current user
            if ($report['assigned_to'] !== null && !$isAssignedToCurrentUser) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'You can only accept unassigned reports or reports assigned to you']));
            }
            
            if ($currentStatus !== 'pending') {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Only pending reports can be accepted']));
            }
            
            $stmt = $conn->prepare("UPDATE reports SET status = 'accepted', assigned_to = ? WHERE id = ?");
            $stmt->bind_param("ii", $_SESSION['user_id'], $reportId);
            break;

        case 'complete':
            // Only allow employees to mark as pending completion
            if (!isEmployee()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Unauthorized']));
            }
            
            // Only allow completing reports assigned to current user
            if (!$isAssignedToCurrentUser) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'You can only complete reports assigned to you']));
            }
            
            // Only allow completing accepted reports
            if ($currentStatus !== 'accepted') {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Only accepted reports can be marked as pending completion']));
            }
            
            $stmt = $conn->prepare("UPDATE reports SET status = 'pending_completion', pending_completion_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $_SESSION['user_id'], $reportId);
            break;

        case 'reset_to_pending':
            if (!isEmployee()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Unauthorized']));
            }
            
            // Only allow resetting reports assigned to current user
            if (!$isAssignedToCurrentUser) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'You can only reset reports assigned to you']));
            }
            
            // Only allow resetting accepted or pending_completion reports
            if ($currentStatus !== 'accepted' && $currentStatus !== 'pending_completion') {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Only accepted or pending completion reports can be reset']));
            }
            
            $stmt = $conn->prepare("UPDATE reports SET status = 'pending', 
                                  assigned_to = NULL, 
                                  pending_completion_by = NULL
                                  WHERE id = ?");
            $stmt->bind_param("i", $reportId);
            break;

        case 'confirm_complete':
            // Only allow supervisors/admins to confirm completion
            if (!isSupervisor() && !isAdmin()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Unauthorized']));
            }
            
            // Only allow confirming completion of pending_completion reports
            if ($currentStatus !== 'pending_completion') {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Only pending completion reports can be confirmed']));
            }
            
            $stmt = $conn->prepare("UPDATE reports SET status = 'completed', completed_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $_SESSION['user_id'], $reportId);
            break;

        case 'assign':
            if (!isSupervisor() && !isAdmin()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Unauthorized']));
            }
            if (empty($employeeId)) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Employee ID is required']));
            }
            
            // Verify the employee exists and is actually an employee
            $employeeCheck = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'employee'");
            $employeeCheck->bind_param("i", $employeeId);
            $employeeCheck->execute();
            $employeeResult = $employeeCheck->get_result();
            
            if ($employeeResult->num_rows === 0) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Invalid employee ID']));
            }
            
            $stmt = $conn->prepare("UPDATE reports SET status = 'accepted', assigned_to = ? WHERE id = ?");
            $stmt->bind_param("ii", $employeeId, $reportId);
            break;

        case 'reset':
            if (!isSupervisor() && !isAdmin()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Unauthorized']));
            }
            
            $stmt = $conn->prepare("UPDATE reports SET status = 'pending', 
                                  assigned_to = NULL, 
                                  completed_by = NULL,
                                  pending_completion_by = NULL,
                                  rejected_by = NULL 
                                  WHERE id = ?");
            $stmt->bind_param("i", $reportId);
            break;

            case 'delete':
                if (!isAdmin()) {
                    http_response_code(403);
                    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
                }
                
                try {
                    // Begin transaction
                    $conn->begin_transaction();
                    
                    // 1. Get the report data
                    $selectStmt = $conn->prepare("SELECT * FROM reports WHERE id = ?");
                    if (!$selectStmt) {
                        throw new Exception('Prepare failed: ' . $conn->error);
                    }
                    $selectStmt->bind_param("i", $reportId);
                    if (!$selectStmt->execute()) {
                        throw new Exception('Execute failed: ' . $selectStmt->error);
                    }
                    $report = $selectStmt->get_result()->fetch_assoc();
                    $selectStmt->close();
                    
                    if (!$report) {
                        throw new Exception('Report not found');
                    }
                    
                    // 2. Get all photos for the report
                    $photoStmt = $conn->prepare("SELECT id, file_path FROM report_photos WHERE report_id = ?");
                    if (!$photoStmt) {
                        throw new Exception('Prepare failed: ' . $conn->error);
                    }
                    $photoStmt->bind_param("i", $reportId);
                    if (!$photoStmt->execute()) {
                        throw new Exception('Execute failed: ' . $photoStmt->error);
                    }
                    $photos = $photoStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $photoStmt->close();
                    
                    // 3. Insert into archived_reports
                    $insertStmt = $conn->prepare("
                        INSERT INTO archived_reports (
                            original_id, title, description, latitude, longitude, 
                            status, created_at, assigned_to, completed_by,
                            encrypted_data, archived_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if (!$insertStmt) {
                        throw new Exception('Prepare failed: ' . $conn->error);
                    }
                    
                    $encryptedData = json_encode(array_merge($report, ['photos' => $photos]));
                    $archivedBy = $_SESSION['user_id'];
                    
                    $insertStmt->bind_param(
                        "isssdssiisi", 
                        $report['id'],
                        $report['title'],
                        $report['description'],
                        $report['latitude'],
                        $report['longitude'],
                        $report['status'],
                        $report['created_at'],
                        $report['assigned_to'],
                        $report['completed_by'],
                        $encryptedData,
                        $archivedBy
                    );
                    
                    if (!$insertStmt->execute()) {
                        throw new Exception('Execute failed: ' . $insertStmt->error);
                    }
                    $archivedId = $conn->insert_id;
                    $insertStmt->close();
                    
                    // 4. Delete photos from report_photos table
                    $deletePhotosStmt = $conn->prepare("DELETE FROM report_photos WHERE report_id = ?");
                    if (!$deletePhotosStmt) {
                        throw new Exception('Prepare failed: ' . $conn->error);
                    }
                    $deletePhotosStmt->bind_param("i", $reportId);
                    if (!$deletePhotosStmt->execute()) {
                        throw new Exception('Execute failed: ' . $deletePhotosStmt->error);
                    }
                    $deletePhotosStmt->close();
                    
                    // 5. Delete from reports
                    $deleteStmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
                    if (!$deleteStmt) {
                        throw new Exception('Prepare failed: ' . $conn->error);
                    }
                    $deleteStmt->bind_param("i", $reportId);
                    if (!$deleteStmt->execute()) {
                        throw new Exception('Execute failed: ' . $deleteStmt->error);
                    }
                    if ($deleteStmt->affected_rows === 0) {
                        throw new Exception('Failed to delete report - no rows affected');
                    }
                    $deleteStmt->close();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    echo json_encode(['success' => true]);
                    
                } catch (Exception $e) {
                    // Rollback on error
                    if (isset($conn)) {
                        $conn->rollback();
                    }
                    error_log('Archive error: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Archive failed: ' . $e->getMessage()]);
                }
                break;

        default:
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid action']));
    }

    // Execute the prepared statement (for all cases except 'delete' which handles its own response)
    if ($action !== 'delete') {
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database operation failed']);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    // Close connections
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($stmt)) $stmt->close();
    if (isset($statusCheck)) $statusCheck->close();
    if (isset($employeeCheck)) $employeeCheck->close();
    $conn->close();
}
?>