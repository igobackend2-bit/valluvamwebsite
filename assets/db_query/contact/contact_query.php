<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
// require_once 'C:/xampp/htdocs/valluvam/assets/db_query/config.php'; // PDO connection
require_once __DIR__ . '/../config.php'; // your PDO connection file


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = $_POST['name'] ?? '';
    $email   = $_POST['email'] ?? '';
    $phone   = $_POST['phone'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';

    if (!empty($name) && !empty($email) && !empty($message)) {
        try {
            // Insert into database
            $sql = "INSERT INTO contacts (name, email, phone, subject, message) 
                    VALUES (:name, :email, :phone, :subject, :message)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name'    => $name,
                ':email'   => $email,
                ':phone'   => $phone,
                ':subject' => $subject,
                ':message' => $message
            ]);

            // Get last inserted ID
            $contactId = $pdo->lastInsertId();

            // Create folder if not exists (relative to project root)
            $folder = __DIR__ . "/../../assets/contact";
            if (!is_dir($folder)) {
                mkdir($folder, 0755, true);
            }

            // Save to file
            $filename = $folder . "/contact_" . $contactId . ".txt";
            $data  = "ID: " . $contactId . "\n";
            $data .= "Name: " . $name . "\n";
            $data .= "Email: " . $email . "\n";
            $data .= "Phone: " . $phone . "\n";
            $data .= "Subject: " . $subject . "\n";
            $data .= "Message:\n" . $message . "\n";
            $data .= "Created At: " . date("Y-m-d H:i:s") . "\n\n";

            file_put_contents($filename, $data);

            echo json_encode(['status' => 'success', 'message' => 'Message stored successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Please fill required fields']);
    }
}
