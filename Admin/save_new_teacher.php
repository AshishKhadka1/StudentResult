<?php
// Enable error logging to a file
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to prevent any unwanted output
ob_start();

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Function to safely get POST values
function getPostValue($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// Check for duplicate submission
$submission_token = getPostValue('submission_token');
if (!empty($submission_token)) {
    // Store submission tokens in session to prevent duplicates
    if (!isset($_SESSION['submission_tokens'])) {
        $_SESSION['submission_tokens'] = [];
    }
    
    // Check if this token has been used before
    if (in_array($submission_token, $_SESSION['submission_tokens'])) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'This form has already been submitted. Please refresh the page to submit again.']);
        exit();
    }
    
    // Add token to session
    $_SESSION['submission_tokens'][] = $submission_token;
    
    // Limit the number of stored tokens to prevent session bloat
    if (count($_SESSION['submission_tokens']) > 10) {
        array_shift($_SESSION['submission_tokens']);
    }
}

try {
    // Database connection
    $conn = new mysqli(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', getenv('DB_NAME') ?: 'result_management');
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Get form data
    $full_name = getPostValue('full_name');
    $email = getPostValue('email');
    $password = getPostValue('password');
    $confirm_password = getPostValue('confirm_password');
    $phone = getPostValue('phone');
    $employee_id = getPostValue('employee_id');
    // Department removed
    $qualification = getPostValue('qualification');
    $joining_date = !empty(getPostValue('joining_date')) ? getPostValue('joining_date') : null;
    $experience = !empty(getPostValue('experience')) ? intval(getPostValue('experience')) : null;
    $status = getPostValue('status', 'active');
    $gender = getPostValue('gender');
    $date_of_birth = !empty(getPostValue('date_of_birth')) ? getPostValue('date_of_birth') : null;
    $address = getPostValue('address');

    // Validate required fields
    if (empty($full_name) || empty($email) || empty($password) || empty($employee_id)) {
        throw new Exception('Please fill all required fields');
    }

    // Validate password match
    if ($password !== $confirm_password) {
        throw new Exception('Passwords do not match');
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Email already exists');
    }
    $stmt->close();

    // Check if employee ID already exists
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE employee_id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Employee ID already exists');
    }
    $stmt->close();

    // Begin transaction
    $conn->begin_transaction();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if username column exists in users table
    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'username'");
    $username_exists = $column_check && $column_check->num_rows > 0;
    
    // Generate a username from email if needed
    $username = '';
    if ($username_exists) {
        // Generate username from email (part before @)
        $username = strtolower(explode('@', $email)[0]);
        
        // Check if username already exists and append numbers if needed
        $base_username = $username;
        $counter = 1;
        
        while (true) {
            $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                // Username is available
                $check_stmt->close();
                break;
            }
            
            // Username exists, try with a number appended
            $username = $base_username . $counter;
            $counter++;
            $check_stmt->close();
        }
        
    }
    
    // Insert user
    if ($username_exists) {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, username, password, role, status, phone, created_at) VALUES (?, ?, ?, ?, 'teacher', ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ssssss", $full_name, $email, $username, $hashed_password, $status, $phone);
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, status, phone, created_at) VALUES (?, ?, ?, 'teacher', ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $status, $phone);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error inserting user: ' . $stmt->error);
    }
    $user_id = $conn->insert_id;
    $stmt->close();
    
    // Check if gender column exists in teachers table
    $column_check = $conn->query("SHOW COLUMNS FROM teachers LIKE 'gender'");
    $gender_exists = $column_check && $column_check->num_rows > 0;
    
    // Check if date_of_birth column exists in teachers table
    $column_check = $conn->query("SHOW COLUMNS FROM teachers LIKE 'date_of_birth'");
    $dob_exists = $column_check && $column_check->num_rows > 0;
    
    if ($gender_exists && $dob_exists) {
        $query = "INSERT INTO teachers (user_id, employee_id, qualification, joining_date, experience, address, gender, date_of_birth, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("isssssss", $user_id, $employee_id, $qualification, $joining_date, $experience, $address, $gender, $date_of_birth);
    } elseif ($gender_exists) {
        $query = "INSERT INTO teachers (user_id, employee_id, qualification, joining_date, experience, address, gender, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("issssss", $user_id, $employee_id, $qualification, $joining_date, $experience, $address, $gender);
    } elseif ($dob_exists) {
        $query = "INSERT INTO teachers (user_id, employee_id, qualification, joining_date, experience, address, date_of_birth, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("issssss", $user_id, $employee_id, $qualification, $joining_date, $experience, $address, $date_of_birth);
    } else {
        $query = "INSERT INTO teachers (user_id, employee_id, qualification, joining_date, experience, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("isssss", $user_id, $employee_id, $qualification, $joining_date, $experience, $address);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error inserting teacher: ' . $stmt->error);
    }
    $teacher_id = $conn->insert_id;
    $stmt->close();
    
    // Log the activity
    $activity_type = 'teacher_create';
    $description = "Added new teacher: $full_name";
    $admin_id = $_SESSION['user_id'];
    $current_time = date('Y-m-d H:i:s');
    
    // Check if activities table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'activities'");
    if ($table_check && $table_check->num_rows > 0) {
        $log_stmt = $conn->prepare("INSERT INTO activities (user_id, activity_type, description, created_by, created_at) VALUES (?, ?, ?, ?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("issss", $user_id, $activity_type, $description, $admin_id, $current_time);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            // Non-critical, continue without throwing exception
        }
    } else {
    }
    
    // Commit transaction
    $conn->commit();
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Teacher added successfully', 'teacher_id' => $teacher_id]);
} catch (Exception $e) {
    // Rollback transaction on error if connection exists
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error adding teacher: ' . $e->getMessage()]);
}

// Close connection if it exists
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
