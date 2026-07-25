<?php
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'result_management';

echo "<h1>Result Management System - Database Setup</h1>";

$sql_file = __DIR__ . '/../Database/result_management.sql';

$cmd = sprintf('mysql --ssl-mode=DISABLED -h %s -u %s %s %s < %s 2>&1',
    escapeshellarg($host),
    escapeshellarg($user),
    $pass ? '-p' . escapeshellarg($pass) : '',
    escapeshellarg($dbname),
    escapeshellarg($sql_file)
);

exec($cmd, $output, $exit_code);

if ($exit_code === 0) {
    echo "<p>Database setup completed successfully!</p>";
    echo "<p>Default admin credentials:</p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    echo "<p><a href='../login.php'>Go to Login Page</a></p>";
} else {
    echo "<p>Error during database setup:</p>";
    echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    
    // Fallback: try via mysqli without triggers
    echo "<p>Attempting fallback setup via mysqli...</p>";
    
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        echo "<p>Fallback also failed: " . $conn->connect_error . "</p>";
        exit();
    }
    $conn->query("DROP DATABASE IF EXISTS $dbname");
    $conn->query("CREATE DATABASE $dbname");
    $conn->select_db($dbname);
    
    $content = file_get_contents($sql_file);
    $content = preg_replace('/DELIMITER \$\$.*?\$\$\s*DELIMITER ;/s', '', $content);
    $content = preg_replace('/START TRANSACTION;|COMMIT;/', '', $content);
    $content = preg_replace('/^SET .*;$/m', '', $content);
    $content = preg_replace('/\/\*!\d+\s.*?;\s*\*\//s', '', $content);
    $content = preg_replace('/^--.*$/m', '', $content);
    $content = trim($content);
    
    $conn->multi_query($content);
    // Drain results
    while ($conn->next_result()) {;}
    
    echo "<p>Fallback setup completed (triggers may be missing, check above).</p>";
    echo "<p><a href='../login.php'>Go to Login Page</a></p>";
    $conn->close();
}
