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
    $teacher_id = getPostValue('teacher_id');
    $user_id = getPostValue('user_id');
    $full_name = getPostValue('full_name');
    $email = getPostValue('email');
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

    // Get password data
    $password = getPostValue('password');
    $confirm_password = getPostValue('confirm_password');
    
    // Validate password if provided
    if (!empty($password)) {
        // Check if passwords match
        if ($password !== $confirm_password) {
            throw new Exception('New password and confirm password do not match');
        }
        
        // Check password length
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters long');
        }
    }

        'teacher_id' => $teacher_id,
        'user_id' => $user_id,
        'full_name' => $full_name,
        'email' => $email,
        'employee_id' => $employee_id,
        'status' => $status
    ]));

    // Validate required fields
    if (empty($teacher_id) || empty($user_id) || empty($full_name) || empty($email) || empty($employee_id)) {
        throw new Exception('Please fill all required fields');
    }

    // Begin transaction
    $conn->begin_transaction();

    // Check if email already exists for another user
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Email already exists for another user');
    }
    $stmt->close();

    // Check if employee ID already exists for another teacher
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE employee_id = ? AND teacher_id != ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $employee_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Employee ID already exists for another teacher');
    }
    $stmt->close();

    // Update user information
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, status = ?, phone = ? WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ssssi", $full_name, $email, $status, $phone, $user_id);
    if (!$stmt->execute()) {
        throw new Exception('Error updating user: ' . $stmt->error);
    }
    $stmt->close();

    // Update password if provided
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $pwd_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        if (!$pwd_stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $pwd_stmt->bind_param("si", $hashed_password, $user_id);
        if (!$pwd_stmt->execute()) {
            throw new Exception('Error updating password: ' . $pwd_stmt->error);
        }
        $pwd_stmt->close();
        
        // Update activity description to include password change
        $description .= " and updated password";
    }
    
    // Check if gender column exists in teachers table
    $column_check = $conn->query("SHOW COLUMNS FROM teachers LIKE 'gender'");
    $gender_exists = $column_check && $column_check->num_rows > 0;
    
    // Check if date_of_birth column exists in teachers table
    $column_check = $conn->query("SHOW COLUMNS FROM teachers LIKE 'date_of_birth'");
    $dob_exists = $column_check && $column_check->num_rows > 0;
    
    // Prepare SQL based on existing columns
    if ($gender_exists && $dob_exists) {
        $query = "UPDATE teachers SET employee_id = ?, qualification = ?, joining_date = ?, experience = ?, address = ?, gender = ?, date_of_birth = ? WHERE teacher_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("sssisssi", $employee_id, $qualification, $joining_date, $experience, $address, $gender, $date_of_birth, $teacher_id);
    } elseif ($gender_exists) {
        $query = "UPDATE teachers SET employee_id = ?, qualification = ?, joining_date = ?, experience = ?, address = ?, gender = ? WHERE teacher_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ssisssi", $employee_id, $qualification, $joining_date, $experience, $address, $gender, $teacher_id);
    } elseif ($dob_exists) {
        $query = "UPDATE teachers SET employee_id = ?, qualification = ?, joining_date = ?, experience = ?, address = ?, date_of_birth = ? WHERE teacher_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ssisssi", $employee_id, $qualification, $joining_date, $experience, $address, $date_of_birth, $teacher_id);
    } else {
        $query = "UPDATE teachers SET employee_id = ?, qualification = ?, joining_date = ?, experience = ?, address = ? WHERE teacher_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("ssisi", $employee_id, $qualification, $joining_date, $experience, $address, $teacher_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error updating teacher: ' . $stmt->error);
    }
    $stmt->close();
    
    // Log the activity
    $activity_type = 'teacher_update';
    $description = "Updated teacher: $full_name";
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
    echo json_encode(['success' => true, 'message' => 'Teacher updated successfully', 'teacher_id' => $teacher_id]);
} catch (Exception $e) {
    // Rollback transaction on error if connection exists
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating teacher: ' . $e->getMessage()]);
}

// Close connection if it exists
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
