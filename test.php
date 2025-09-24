<?php
// test-db.php - Database connection and table structure test
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database-config.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test database connection
    if ($db) {
        echo "✅ Database connection: <strong>SUCCESS</strong><br>";
        
        // Test if customers table exists
        $stmt = $db->prepare("SHOW TABLES LIKE 'customers'");
        $stmt->execute();
        $tableExists = $stmt->fetch();
        
        if ($tableExists) {
            echo "✅ Customers table: <strong>EXISTS</strong><br>";
            
            // Show table structure
            $stmt = $db->prepare("DESCRIBE customers");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            echo "<h3>Table Structure:</h3>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Test insert
            echo "<h3>Test Insert:</h3>";
            try {
                $testEmail = 'test_' . time() . '@example.com';
                $hashedPassword = password_hash('test123456', PASSWORD_DEFAULT);
                $trialEndsAt = date('Y-m-d H:i:s', strtotime('+14 days'));
                
                $stmt = $db->prepare("
                    INSERT INTO customers (
                        name, email, password, country, phone, 
                        subscription_status, trial_ends_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'trial', ?, NOW())
                ");
                
                $result = $stmt->execute(array(
                    'Test User',
                    $testEmail,
                    $hashedPassword,
                    'us',
                    '+1234567890',
                    $trialEndsAt
                ));
                
                if ($result) {
                    $customerId = $db->lastInsertId();
                    echo "✅ Test insert: <strong>SUCCESS</strong> (ID: $customerId)<br>";
                    
                    // Clean up test data
                    $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
                    $stmt->execute(array($customerId));
                    echo "✅ Test cleanup: <strong>SUCCESS</strong><br>";
                } else {
                    $errorInfo = $stmt->errorInfo();
                    echo "❌ Test insert: <strong>FAILED</strong><br>";
                    echo "Error: " . print_r($errorInfo, true) . "<br>";
                }
                
            } catch (Exception $e) {
                echo "❌ Test insert exception: " . $e->getMessage() . "<br>";
            }
            
        } else {
            echo "❌ Customers table: <strong>DOES NOT EXIST</strong><br>";
            echo "<p>Please run the database.sql file to create the tables.</p>";
        }
        
    } else {
        echo "❌ Database connection: <strong>FAILED</strong><br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h3>Configuration Info:</h3>";
echo "Site URL: " . SITE_URL . "<br>";
echo "Default Country: " . DEFAULT_COUNTRY . "<br>";
echo "Trial Period: " . TRIAL_PERIOD_DAYS . " days<br>";

echo "<h3>PHP Info:</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "PDO Available: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "<br>";
echo "PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "<br>";

if (extension_loaded('pdo')) {
    echo "Available PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "<br>";
}

echo "<hr>";
echo "<p><a href='customer/signup.php'>Test Signup Form</a></p>";
echo "<p><a href='index.php'>Back to Homepage</a></p>";
?>