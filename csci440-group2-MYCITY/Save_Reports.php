<?php
// Set response content type to JSON
header('Content-Type: application/json');

try {
    // 1. GET FORM DATA
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $address = $_POST['address'] ?? '';
    
    // Check required fields
    if (empty($title) || empty($description) || empty($address)) {
        throw new Exception("Please fill all required fields.");
    }

    // 2. GEOCODING FUNCTION - Convert address to coordinates
    function fetchGeocode($address) {
        // Prepare API request URL
        $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address);

        // Set up cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15 second timeout
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "User-Agent: GridFix/1.0 (gridfix@example.com)" // Required by Nominatim
        ]);

        // Execute request
        $response = curl_exec($ch);
        
        // Handle errors
        if ($response === false) {
            throw new Exception("Map service error: " . curl_error($ch));
        }

        // Check HTTP status
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Map service unavailable. Please try later.");
        }

        // Process response
        $data = json_decode($response, true);

        if (empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
            throw new Exception("Address not found. Please try a different address.");
        }

        return $data[0]; // Return first match
    }

    // Get coordinates from address
    $geocodeData = fetchGeocode($address);
    $latitude = $geocodeData['lat'];
    $longitude = $geocodeData['lon'];

    // 3. DATABASE CONNECTION
    $conn = new mysqli('[redacted]', '[redacted]', '[redacted]', '[redacted]');
    
    if ($conn->connect_error) {
        throw new Exception("Database connection error.");
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // 4. SAVE REPORT TO DATABASE
        $stmt = $conn->prepare("INSERT INTO reports (title, description, latitude, longitude) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdd", $title, $description, $latitude, $longitude);

        if (!$stmt->execute()) {
            throw new Exception("Failed to save report.");
        }

        // Get the new report ID
        $reportId = $stmt->insert_id;
        $stmt->close();

        // 5. HANDLE FILE UPLOADS
        $uploadedFiles = [];
        if (!empty($_FILES['photos']['name'][0])) {
            // Create directory if it doesn't exist
            $uploadDir = 'uploads/reports/' . $reportId . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Process each file
            for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
                // Skip empty files
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                // Validate file type
                $fileType = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($fileType, $allowedTypes)) {
                    continue;
                }

                // Validate file size (5MB max)
                if ($_FILES['photos']['size'][$i] > 5 * 1024 * 1024) {
                    continue;
                }

                // Generate unique filename
                $fileName = uniqid() . '.' . $fileType;
                $targetPath = $uploadDir . $fileName;

                // Move uploaded file
                if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $targetPath)) {
                    // Save to database
                    $stmt = $conn->prepare("INSERT INTO report_photos (report_id, file_path) VALUES (?, ?)");
                    $relativePath = 'uploads/reports/' . $reportId . '/' . $fileName;
                    $stmt->bind_param("is", $reportId, $relativePath);
                    $stmt->execute();
                    $stmt->close();

                    $uploadedFiles[] = $relativePath;
                }
            }
        }

        // Commit transaction
        $conn->commit();

        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Report saved successfully!',
            'reportId' => $reportId,
            'uploadedFiles' => $uploadedFiles
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Return error if anything fails
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}