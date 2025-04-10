<?php
session_start();
// Database connection
$servername = "localhost";
$username = "root";
$password = "vyom0403"; // Use your actual password
$dbname = "jewellery_store";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // Use json_encode for AJAX errors too for consistency
    header('Content-Type: application/json');
    echo json_encode(['error' => "Connection failed: " . $conn->connect_error]);
    exit(); // Stop script execution
}

$searchTerm = isset($_GET['term']) ? $conn->real_escape_string($_GET['term']) : '';
$products = [];

if (!empty($searchTerm)) {
    // --- MODIFIED SQL QUERY ---
    // Removed the 'OR description LIKE ?' part
    $sql = "SELECT product_id, name, description, price, image_url FROM products WHERE name LIKE ?";
    $likeTerm = "%" . $searchTerm . "%";
    $stmt = $conn->prepare($sql);
    // --- Only bind one parameter now ---
    $stmt->bind_param("s", $likeTerm); // Changed "ss" to "s"
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row; // Add each product to the array
        }
    }
    $stmt->close();
} else {
    // Optional: Return all products if search term is empty, or none
     $sql = "SELECT product_id, name, description, price, image_url FROM products";
     $result = $conn->query($sql);
     if ($result && $result->num_rows > 0) { // Add check for $result being valid
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
}

$conn->close();

// Return results as JSON
header('Content-Type: application/json');
echo json_encode($products);
?>