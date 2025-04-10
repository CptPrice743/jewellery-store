<?php
// Always start the session first
session_start();

// Function to save cart items to the database
// You could place this in a separate 'functions.php' file and include it
function saveCartToDatabase($conn, $userId, $cart) {
    // Validate input
    if (!$conn || $userId <= 0 || !is_array($cart)) {
        return false;
    }

    // Begin transaction for atomicity
    $conn->begin_transaction();

    try {
        // Clear existing cart for the user first to avoid duplicates if items were removed
        $stmt_delete = $conn->prepare("DELETE FROM user_carts WHERE user_id = ?");
        if (!$stmt_delete) throw new Exception("Prepare delete failed: " . $conn->error);
        $stmt_delete->bind_param("i", $userId);
        if (!$stmt_delete->execute()) throw new Exception("Execute delete failed: " . $stmt_delete->error);
        $stmt_delete->close();

        // Check if the cart is not empty before trying to insert
        if (!empty($cart)) {
            // Prepare the insert statement once
            $stmt_insert = $conn->prepare("INSERT INTO user_carts (user_id, product_id, quantity) VALUES (?, ?, ?)");
             if (!$stmt_insert) throw new Exception("Prepare insert failed: " . $conn->error);

            // Insert current session cart items
            foreach ($cart as $productId => $item) {
                // Ensure quantity is valid
                $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
                $productId = (int)$productId; // Ensure product ID is an integer

                if ($productId > 0 && $quantity > 0) {
                    $stmt_insert->bind_param("iii", $userId, $productId, $quantity);
                     if (!$stmt_insert->execute()) throw new Exception("Execute insert failed for product ID $productId: " . $stmt_insert->error);
                }
            }
            $stmt_insert->close();
        }

        // If all operations were successful, commit the transaction
        $conn->commit();
        return true;

    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $conn->rollback();
        // Optional: Log the error $e->getMessage();
        return false;
    }
}

// --- Main Logout Logic ---

// Check if user is logged in and cart exists in session
if (isset($_SESSION['user_id']) && isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    // --- Database Connection ---
    // Replace with your actual credentials
    $servername = "localhost";
    $username = "root";
    $password = "vyom0403"; // Use your actual password
    $dbname = "jewellery_store";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        // Optional: Log error, but proceed with logout anyway
        // error_log("Logout DB Connection failed: " . $conn->connect_error);
    } else {
        // Save the cart to the database
        saveCartToDatabase($conn, $_SESSION['user_id'], $_SESSION['cart']);
        // Close the connection
        $conn->close();
    }
}

// --- Session Destruction ---

// Unset all of the session variables.
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// --- Redirect ---
// Redirect to the homepage (index.php) after logout
header("Location: index.php");
exit(); // Ensure no further code is executed after redirection

?>