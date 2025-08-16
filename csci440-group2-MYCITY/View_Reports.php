<?php
require_once 'session.php';
requireLogin(); 
require_once 'authFunctions.php';

$isEmployee = isEmployee();
$isSupervisor = isSupervisor();
$isAdmin = isAdmin();

// Database connection configuration
$servername = "[redacted]";
$username = "[redacted]";
$password = "[redacted]";
$dbname = "[redacted]";

// Create MySQLi connection object
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection and terminate on failure
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the active tab from request, default to 'accepted' for employees, 'active' for others
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : ($isEmployee ? 'accepted' : 'active');

// Get search term if exists
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base SQL query - updated to include photos
$sql = "SELECT r.id, r.title, r.description, r.latitude, r.longitude, r.status, 
               r.created_at, 
               u1.username as assigned_to_name, 
               u2.username as completed_by_name,
               u3.username as pending_completion_by_name,
               GROUP_CONCAT(p.file_path SEPARATOR '|||') as photos
        FROM reports r
        LEFT JOIN users u1 ON r.assigned_to = u1.id
        LEFT JOIN users u2 ON r.completed_by = u2.id
        LEFT JOIN users u3 ON r.pending_completion_by = u3.id
        LEFT JOIN report_photos p ON r.id = p.report_id";

// Add conditions based on user role
if ($isEmployee) {
    // For employees, only filter by assigned reports when viewing 'accepted' tab (their tasks)
    if ($activeTab === 'accepted') {
        $sql .= " WHERE r.assigned_to = " . $_SESSION['user_id'];
    } else {
        // For other tabs (like pending), show all reports of that status
        $sql .= " WHERE r.status = '$activeTab'";
    }
    
    if (!empty($searchTerm)) {
        $searchTerm = $conn->real_escape_string($searchTerm);
        $sql .= " AND (r.title LIKE '%$searchTerm%' OR 
                      r.description LIKE '%$searchTerm%' OR 
                      r.status LIKE '%$searchTerm%')";
    }
} else {
    if (!empty($searchTerm)) {
        $searchTerm = $conn->real_escape_string($searchTerm);
        $sql .= " WHERE (r.title LIKE '%$searchTerm%' OR 
                        r.description LIKE '%$searchTerm%' OR 
                        r.status LIKE '%$searchTerm%' OR 
                        u1.username LIKE '%$searchTerm%' OR 
                        u2.username LIKE '%$searchTerm%' OR
                        u3.username LIKE '%$searchTerm%')";
    }
}

// Filter by tab if not showing all
if ($activeTab !== 'active' && !$isEmployee) {
    if (strpos($sql, 'WHERE') !== false) {
        $sql .= " AND r.status = '$activeTab'";
    } else {
        $sql .= " WHERE r.status = '$activeTab'";
    }
} elseif ($isEmployee && $activeTab === 'accepted') {
    if (strpos($sql, 'WHERE') !== false) {
        $sql .= " AND r.status = 'accepted'";
    } else {
        $sql .= " WHERE r.status = 'accepted'";
    }
}

$sql .= " GROUP BY r.id";
$sql .= " ORDER BY r.created_at DESC";
$result = $conn->query($sql);

// Initialize empty reports arrays
$allReports = [];
$pendingReports = [];
$acceptedReports = [];
$pendingCompletionReports = [];
$completedReports = [];

// Populate reports arrays if results exist
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Process photos if they exist
        if (!empty($row['photos'])) {
            $row['photos'] = array_map(function($path) {
                return ['file_path' => $path];
            }, explode('|||', $row['photos']));
        } else {
            $row['photos'] = [];
        }
        
        $allReports[] = $row;
        switch ($row['status']) {
            case 'pending':
                $pendingReports[] = $row;
                break;
            case 'accepted':
                $acceptedReports[] = $row;
                break;
            case 'pending_completion':
                $pendingCompletionReports[] = $row;
                break;
            case 'completed':
                $completedReports[] = $row;
                break;
        }
    }
}

// Determine which reports to display based on active tab
$displayReports = [];
switch ($activeTab) {
    case 'pending':
        $displayReports = $pendingReports;
        break;
    case 'accepted':
        $displayReports = $acceptedReports;
        break;
    case 'pending_completion':
        $displayReports = $pendingCompletionReports;
        break;
    case 'completed':
        $displayReports = $completedReports;
        break;
    case 'active':
    default:
        // Show pending and accepted reports combined (active reports)
        $displayReports = array_merge($pendingReports, $acceptedReports);
        $activeTab = 'active';
}

// Fetch all users for the assign dropdown
$users = [];
if ($isSupervisor || $isAdmin) {
    $userQuery = "SELECT id, username FROM users WHERE role = 'employee'";
    $userResult = $conn->query($userQuery);
    if ($userResult->num_rows > 0) {
        while ($userRow = $userResult->fetch_assoc()) {
            $users[] = $userRow;
        }
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic meta tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports</title>
    
    <!-- External CSS dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS styles -->
    <style>
    /* Report card styling */
    .report-card {
        margin-bottom: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border: none;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    .report-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .location-badge {
        cursor: pointer;
        color: #0d6efd;
    }
    .location-badge:hover {
        text-decoration: underline;
    }
    .nav-tabs .nav-link.active {
        font-weight: bold;
    }
    .status-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
        margin-left: 8px;
    }
    .status-pending {
        background-color: #fff3cd;
        color: #664d03;
    }
    .status-accepted {
        background-color: #cfe2ff;
        color: #084298;
    }
    .status-pending_completion {
        background-color: #ffeeba;
        color: #856404;
    }
    .status-completed {
        background-color: #d1e7dd;
        color: #0f5132;
    }
    .assigned-info {
        font-size: 0.9rem;
        color: #6c757d;
        margin-top: 5px;
    }
    
    .report-actions .dropdown {
        position: relative;
        display: inline-block;
        z-index: 1000;
    }

    .report-actions .dropdown-menu {
        position: absolute;
        left: 0;
        top: 100%;
        margin-top: 0.125rem;
        z-index: 1001;
    }

    /* User menu positioning */
    .user-menu {
        position: absolute;
        top: 20px;
        right: 20px;
        z-index: 1000;
    }
    
    /* User menu button styling */
    .user-menu-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #6c757d;
        padding: 5px 10px;
        border-radius: 50%;
        transition: all 0.2s ease;
    }
    
    /* User menu button hover */
    .user-menu-btn:hover {
        background-color: rgba(108, 117, 125, 0.1);
    }
    
    /* Assign modal select styling */
    #assigneeSelect {
        width: 100%;
        padding: 0.375rem 0.75rem;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }

    #assigneeSelect option {
        padding: 0.375rem 0.75rem;
    }

    #assigneeSelect option[value=""] {
        color: #6c757d;
        font-style: italic;
    }
    
    /* Archive confirmation modal styling */
    .archive-modal-body {
        padding: 20px;
    }
    .archive-warning {
        color: #dc3545;
        font-weight: bold;
    }
    
    /* Search bar styling */
    #searchForm {
        margin-bottom: 20px;
    }
    #searchInput {
        border-right: none;
    }
    #searchForm .btn-outline-secondary {
        border-left: none;
        border-color: #ced4da;
    }
    #searchForm .btn-outline-secondary:hover {
        background-color: #f8f9fa;
    }
    
    /* Photo modal styles */
    .photo-modal .modal-dialog {
        max-width: 90%;
        max-height: 90vh;
    }
    
    .photo-modal .modal-content {
        height: 90vh;
    }
    
    .photo-modal .modal-body {
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .photo-modal img {
        max-width: 100%;
        max-height: 80vh;
        margin-bottom: 15px;
        object-fit: contain;
    }
    
    .photo-thumbnail {
        width: 100px;
        height: 100px;
        object-fit: cover;
        margin-right: 10px;
        margin-bottom: 10px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: border-color 0.2s;
    }
    
    .photo-thumbnail:hover {
        border-color: #0d6efd;
    }
    
    .photo-thumbnail.active {
        border-color: #0d6efd;
    }
    
    .photo-count-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: rgba(0,0,0,0.7);
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }
    .report-description {
    white-space: pre-line;
    word-wrap: break-word;
    margin-bottom: 10px;
}

.btn-read-more {
    color: #0d6efd;
    padding: 0;
    vertical-align: baseline;
    font-size: inherit;
}

.btn-read-more:hover {
    text-decoration: underline;
}
    </style>
</head>
<body>
    <!-- User dropdown menu -->
    <div class="user-menu">
        <div class="dropdown">
            <button class="user-menu-btn dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-gear-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>

    <!-- Main container with padding -->
    <div class="container py-4">
        <!-- Page header section -->
        <div class="page-header">
            <h1 class="display-4">GridFix</h1>
            <p class="lead">View all submitted reports</p>
        </div>

        <!-- Navigation back to map -->
        <a href="index.php" class="btn btn-outline-primary btn-return mb-3">
            <i class="bi bi-arrow-left"></i> Return to Map
        </a>

        <!-- Tabs navigation -->
        <ul class="nav nav-tabs mb-4">
            <?php if (!$isEmployee): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'active' ? 'active' : ''; ?>" 
                       href="View_Reports.php?tab=active<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                        Active
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'pending' ? 'active' : ''; ?>" 
                       href="View_Reports.php?tab=pending<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                        Pending
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'pending' ? 'active' : ''; ?>" 
                       href="View_Reports.php?tab=pending<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                        Pending
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'accepted' ? 'active' : ''; ?>" 
                   href="View_Reports.php?tab=accepted<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                    <?php echo $isEmployee ? 'My Tasks' : 'Accepted'; ?>
                </a>
            </li>
            
            <?php if (!$isEmployee): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'pending_completion' ? 'active' : ''; ?>" 
                       href="View_Reports.php?tab=pending_completion<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                        Pending Completion
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'completed' ? 'active' : ''; ?>" 
                   href="View_Reports.php?tab=completed<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                    Completed
                </a>
            </li>
        </ul>

        <!-- Search bar -->
        <div class="row mb-4">
            <div class="col-md-6">
                <form id="searchForm" class="d-flex">
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search reports..." 
                               value="<?php echo !empty($searchTerm) ? htmlspecialchars($searchTerm) : ''; ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if (!empty($searchTerm)): ?>
                            <a href="View_Reports.php?tab=<?php echo $activeTab; ?>" class="btn btn-outline-danger">
                                <i class="bi bi-x"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($displayReports)): ?>
            <!-- Empty state when no reports exist -->
            <div class="no-reports alert alert-info">
                <h5><i class="bi bi-exclamation-circle"></i> No Reports Found</h5>
                <p class="mt-2">
                    <?php if (!empty($searchTerm)): ?>
                        No <?php echo str_replace('_', ' ', $activeTab) ?> reports match your search for "<?php echo htmlspecialchars($searchTerm); ?>".
                    <?php else: ?>
                        There are currently no <?php echo str_replace('_', ' ', $activeTab) ?> reports to display.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <!-- Reports grid -->
            <div class="row">
                <?php foreach ($displayReports as $report): ?>
                    <div class="col-md-6">
                        <!-- Individual report card - clickable -->
                        <div class="card report-card" data-lat="<?php echo $report['latitude']; ?>" 
                             data-lng="<?php echo $report['longitude']; ?>" 
                             data-report-id="<?php echo $report['id']; ?>">
                            <div class="card-body">
                                <h5 class="report-title">
                                    <?php echo htmlspecialchars($report['title']); ?>
                                    <span class="status-badge status-<?php echo $report['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                    </span>
                                    <span class="location-badge" onclick="navigateToReportFromCard(this)">
                                        <i class="bi bi-geo-alt"></i> View on Map
                                    </span>
                                </h5>
                                
                                <?php 
$description = htmlspecialchars($report['description']);
if (strlen($description) > 500): ?>
    <p class="report-description">
        <?php echo nl2br(substr($description, 0, 500)) . '...'; ?>
        <button class="btn btn-link p-0 text-decoration-none" 
                onclick="showFullDescription('<?php echo addslashes($report['title']); ?>', '<?php echo addslashes($description); ?>')">
            Read more
        </button>
    </p>
<?php else: ?>
    <p class="report-description"><?php echo nl2br($description); ?></p>
<?php endif; ?>                                
                                <div class="report-meta">
                                    <i class="bi bi-clock"></i> 
                                    <?php 
                                        $date = new DateTime($report['created_at']);
                                        echo $date->format('M j, Y g:i a');
                                    ?>
                                </div>
                                
                                <?php if ($report['assigned_to_name']): ?>
                                    <div class="assigned-info">
                                        <i class="bi bi-person-check"></i> Assigned to: <?php echo htmlspecialchars($report['assigned_to_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($report['pending_completion_by_name']): ?>
                                    <div class="assigned-info">
                                        <i class="bi bi-hourglass-split"></i> Marked complete by: <?php echo htmlspecialchars($report['pending_completion_by_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($report['completed_by_name']): ?>
                                    <div class="assigned-info">
                                        <i class="bi bi-check-circle"></i> Completed by: <?php echo htmlspecialchars($report['completed_by_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Add photo viewing button if photos exist -->
                                <?php if (!empty($report['photos'])): ?>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#photoModal"
                                                data-report-id="<?php echo $report['id']; ?>"
                                                data-photos='<?php echo json_encode($report['photos']); ?>'>
                                            <i class="bi bi-image"></i> View Photos (<?php echo count($report['photos']); ?>)
                                        </button>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Add action buttons based on role -->
                                <div class="report-actions mt-2">
                                    <?php if ($isEmployee && $report['status'] === 'pending' && ($report['assigned_to_name'] === null || $report['assigned_to_name'] === $_SESSION['username'])): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="acceptReport(<?php echo $report['id']; ?>)">
                                            <i class="bi bi-check-circle"></i> Accept
                                        </button>
                                    <?php elseif ($isEmployee && $report['status'] === 'accepted' && $report['assigned_to_name'] === $_SESSION['username']): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="completeReport(<?php echo $report['id']; ?>)">
                                            <i class="bi bi-check-circle"></i> Mark as Complete
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="resetToPending(<?php echo $report['id']; ?>)">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Pending
                                        </button>
                                    <?php elseif ($isEmployee && $report['status'] === 'pending_completion' && $report['assigned_to_name'] === $_SESSION['username']): ?>
                                        <button class="btn btn-sm btn-outline-warning" onclick="resetToPending(<?php echo $report['id']; ?>)">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Pending
                                        </button>
                                    <?php elseif (($isSupervisor || $isAdmin) && $report['status'] === 'pending_completion'): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="confirmCompletion(<?php echo $report['id']; ?>)">
                                            <i class="bi bi-check-circle-fill"></i> Confirm Completion
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($isSupervisor || $isAdmin): ?>
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#statusModal"
                                                data-report-id="<?php echo $report['id']; ?>"
                                                data-current-status="<?php echo $report['status']; ?>">
                                            <i class="bi bi-arrow-repeat"></i> Change Status
                                        </button>

                                        <?php if ($report['status'] !== 'completed'): ?>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#assignModal" 
                                                    data-report-id="<?php echo $report['id']; ?>">
                                                <i class="bi bi-person-plus"></i> Assign
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($isAdmin): ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#archiveModal"
                                                    data-report-id="<?php echo $report['id']; ?>"
                                                    data-report-title="<?php echo htmlspecialchars($report['title']); ?>">
                                                <i class="bi bi-archive"></i> Archive
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Assign Report Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignModalLabel">Assign Report to Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="assignForm">
                        <input type="hidden" id="assignReportId" name="reportId">
                        <div class="mb-3">
                            <label for="assigneeSelect" class="form-label">Select Employee</label>
                            <input type="text" class="form-control mb-2" id="employeeSearch" placeholder="Search employees..." autocomplete="off">
                            <select class="form-select" id="assigneeSelect" size="5" required style="height: auto;">
                                <option value="">-- Select an employee --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="assignReport()">Assign</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Change Report Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="statusForm">
                        <input type="hidden" id="statusReportId" name="reportId">
                        <div class="mb-3">
                            <label for="statusSelect" class="form-label">Select New Status</label>
                            <select class="form-select" id="statusSelect" required>
                                <option value="">-- Select a status --</option>
                                <option value="pending">Pending</option>
                                <option value="accepted">Accepted</option>
                                <option value="pending_completion">Pending Completion</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div id="statusWarning" class="alert alert-warning mt-3" style="display: none;">
                            <i class="bi bi-exclamation-triangle-fill"></i> 
                            <span id="warningText"></span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmStatusChange()">Change Status</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Report Confirmation Modal -->
    <div class="modal fade" id="archiveModal" tabindex="-1" aria-labelledby="archiveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="archiveModalLabel">Archive Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body archive-modal-body">
                    <p>You are about to archive the following report:</p>
                    <p class="fw-bold" id="archiveReportTitle"></p>
                    <p class="archive-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        This action will permanently remove the report from the system and store it in encrypted archive storage.
                    </p>
                    <p>Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmArchive">Archive Report</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div class="modal fade photo-modal" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalLabel">Report Photos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="photoThumbnails" class="d-flex flex-wrap mb-3"></div>
                    <div id="photoDisplay" class="text-center"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Description Modal -->
<div class="modal fade" id="descriptionModal" tabindex="-1" aria-labelledby="descriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="descriptionModalLabel">Report Description</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="fullDescriptionContent"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

    <!-- Bootstrap JavaScript bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script> 
    // Initialize modal event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Assign modal initialization
        var assignModal = document.getElementById('assignModal');
        if (assignModal) {
            assignModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var reportId = button.getAttribute('data-report-id');
                document.getElementById('assignReportId').value = reportId;
            });
        }
        
        
        // Status modal initialization
        var statusModal = document.getElementById('statusModal');
        if (statusModal) {
            statusModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var reportId = button.getAttribute('data-report-id');
                var currentStatus = button.getAttribute('data-current-status');
                
                document.getElementById('statusReportId').value = reportId;
                document.getElementById('statusSelect').value = '';
                document.getElementById('statusWarning').style.display = 'none';
                
                // Set up the status select options
                const statusSelect = document.getElementById('statusSelect');
                statusSelect.querySelectorAll('option').forEach(option => {
                    option.disabled = option.value === currentStatus;
                    if (option.value === currentStatus) {
                        option.textContent += ' (current)';
                    }
                });
            });
            
            // Listen for status selection changes
            const statusSelect = document.getElementById('statusSelect');
            statusSelect.addEventListener('change', function() {
                const warningDiv = document.getElementById('statusWarning');
                const warningText = document.getElementById('warningText');
                warningDiv.style.display = 'none';
                
                if (this.value === 'pending') {
                    warningDiv.style.display = 'block';
                    warningText.textContent = 'Resetting to pending will clear assignment and completion data.';
                } else if (this.value === 'pending_completion') {
                    warningDiv.style.display = 'block';
                    warningText.textContent = 'Marking as pending completion will require supervisor approval.';
                }
            });
        }
        
        // Archive modal initialization
        var archiveModal = document.getElementById('archiveModal');
        if (archiveModal) {
            archiveModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var reportId = button.getAttribute('data-report-id');
                var reportTitle = button.getAttribute('data-report-title');
                
                document.getElementById('archiveReportTitle').textContent = reportTitle;
                
                // Set up the confirm button
                var confirmBtn = document.getElementById('confirmArchive');
                confirmBtn.onclick = function() {
                    performSilentArchive(reportId);
                    var modalInstance = bootstrap.Modal.getInstance(archiveModal);
                    modalInstance.hide();
                };
            });
        }
        
        // Photo modal initialization
        var photoModal = document.getElementById('photoModal');
        if (photoModal) {
            photoModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var reportId = button.getAttribute('data-report-id');
                var photos = JSON.parse(button.getAttribute('data-photos'));
                
                const modalTitle = photoModal.querySelector('.modal-title');
                modalTitle.textContent = `Photos for Report #${reportId}`;
                
                const thumbnailsContainer = document.getElementById('photoThumbnails');
                const photoDisplay = document.getElementById('photoDisplay');
                
                // Clear previous content
                thumbnailsContainer.innerHTML = '';
                photoDisplay.innerHTML = '';
                
                // Add thumbnails
                photos.forEach((photo, index) => {
                    const thumbnail = document.createElement('img');
                    thumbnail.src = photo.file_path;
                    thumbnail.className = 'photo-thumbnail' + (index === 0 ? ' active' : '');
                    thumbnail.alt = `Photo ${index + 1}`;
                    thumbnail.onclick = function() {
                        // Update main display
                        photoDisplay.innerHTML = `<img src="${photo.file_path}" class="img-fluid" alt="Report Photo">`;
                        
                        // Update active thumbnail
                        document.querySelectorAll('.photo-thumbnail').forEach(t => {
                            t.classList.remove('active');
                        });
                        this.classList.add('active');
                    };
                    
                    thumbnailsContainer.appendChild(thumbnail);
                });
                
                // Show first photo by default
                if (photos.length > 0) {
                    photoDisplay.innerHTML = `<img src="${photos[0].file_path}" class="img-fluid" alt="Report Photo">`;
                }
            });
        }
        
        // Employee search functionality
        const employeeSearch = document.getElementById('employeeSearch');
        const assigneeSelect = document.getElementById('assigneeSelect');
        
        if (employeeSearch && assigneeSelect) {
            employeeSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const options = assigneeSelect.options;
                
                for (let i = 0; i < options.length; i++) {
                    const option = options[i];
                    const text = option.text.toLowerCase();
                    if (text.includes(searchTerm)) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                }
            });
            
            // Clear search when modal is hidden
            if (assignModal) {
                assignModal.addEventListener('hidden.bs.modal', function() {
                    employeeSearch.value = '';
                    const options = assigneeSelect.options;
                    for (let i = 0; i < options.length; i++) {
                        options[i].style.display = '';
                    }
                });
            }
        }
        
        // Search form handling
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const searchTerm = document.getElementById('searchInput').value.trim();
            const activeTab = '<?php echo $activeTab; ?>';
            
            if (searchTerm) {
                window.location.href = `View_Reports.php?tab=${activeTab}&search=${encodeURIComponent(searchTerm)}`;
            } else {
                window.location.href = `View_Reports.php?tab=${activeTab}`;
            }
        });
    });

    function resetToPending(reportId) {
        if (!confirm('Are you sure you want to reset this report to pending? This will unassign it from you.')) return;
        performReportAction('reset_to_pending', reportId);
    }

    function confirmStatusChange() {
        const reportId = document.getElementById('statusReportId').value;
        const newStatus = document.getElementById('statusSelect').value;
        
        if (!newStatus) {
            alert('Please select a status');
            return;
        }
        
        let action;
        switch(newStatus) {
            case 'accepted': action = 'accept'; break;
            case 'completed': action = 'confirm_complete'; break;
            case 'pending_completion': action = 'complete'; break;
            case 'pending': action = 'reset'; break;
            default: 
                alert('Invalid status');
                return;
        }
        
        // For pending status (reset), show additional confirmation
        if (newStatus === 'pending') {
            if (!confirm('Are you sure you want to reset this report to pending? This will clear assignment and completion data.')) {
                return;
            }
        }
        
        performReportAction(action, reportId);
        
        // Close the modal
        var modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
        modal.hide();
    }

    function confirmCompletion(reportId) {
        if (!confirm('Are you sure you want to confirm this report as completed?')) return;
        performReportAction('confirm_complete', reportId);
    }

    function performSilentArchive(reportId) {
        fetch('ReportActions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                reportId: reportId
            })
        })
        .then(response => {
            if (!response.ok) {
                console.error('Archive failed');
                return;
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                window.location.reload();
            } else {
                console.error('Archive failed:', data ? data.message : 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    function navigateToReport(lat, lng, id) {
        localStorage.setItem('reportLocation', JSON.stringify({
            latitude: lat,
            longitude: lng,
            reportId: id,
            shouldFocus: true,
            timestamp: Date.now()
        }));
        window.location.href = 'index.php#map-container';
    }

    function navigateToReportFromCard(element) {
        const card = element.closest('.report-card');
        const lat = card.dataset.lat;
        const lng = card.dataset.lng;
        const id = card.dataset.reportId;
        navigateToReport(lat, lng, id);
    }

    function acceptReport(reportId) {
        if (!confirm('Are you sure you want to accept this report?')) return;
        performReportAction('accept', reportId);
    }

    function completeReport(reportId) {
        if (!confirm('Mark this report as pending completion?')) return;
        performReportAction('complete', reportId);
    }

    function assignReport() {
        const reportId = document.getElementById('assignReportId').value;
        const employeeId = document.getElementById('assigneeSelect').value;
        
        if (!employeeId) {
            alert('Please select an employee');
            return;
        }
        
        performReportAction('assign', reportId, {employeeId: employeeId});
    }

    function performReportAction(action, reportId, additionalData = {}) {
        fetch('ReportActions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                reportId: reportId,
                ...additionalData
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
    function showFullDescription(title, description) {
    const modal = new bootstrap.Modal(document.getElementById('descriptionModal'));
    document.getElementById('descriptionModalLabel').textContent = title;
    document.getElementById('fullDescriptionContent').innerHTML = description.replace(/\n/g, '<br>');
    modal.show();
}
    </script>
</body>
</html>