<?php
session_start(); // ADD THIS LINE

// Database connection details (needed regardless of login status for fetching count later if needed)
$servername = "localhost"; $username = "root"; $password = "vyom0403"; $dbname = "jewellery_store";

$response = ['success' => false, 'message' => '', 'cart_count' => 0];
$loggedIn = isset($_SESSION['user_id']);
$userId = $loggedIn ? $_SESSION['user_id'] : null;

// Determine target cart (session or database)
// Note: Session cart is primarily for non-logged-in users now
if (!isset($_SESSION['cart'])) {
     $_SESSION['cart'] = []; // Initialize session cart if not set
}
$cart = &$_SESSION['cart']; // Reference session cart for non-logged-in users

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : null; // Can be 0 for removal
$action = isset($_POST['action']) ? $_POST['action'] : null; // 'add', 'update', 'remove'

// --- Basic Input Validation ---
if ($productId === null || $action === null) {
    $response['message'] = 'Missing product ID or action.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
if (($action == 'update' || $action == 'add') && $quantity === null) {
     $response['message'] = 'Missing quantity for add/update action.';
     header('Content-Type: application/json');
     echo json_encode($response);
     exit();
}
if (($action == 'update' || $action == 'add') && (!is_numeric($quantity) || $quantity < 0)) {
    $response['message'] = 'Invalid quantity provided.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
// --- End Basic Input Validation ---


// --- Database Interaction Functions ---
function updateDatabaseCart($conn, $userId, $productId, $newQuantity) {
    if ($newQuantity > 0) {
        $stmt = $conn->prepare("
            INSERT INTO user_carts (user_id, product_id, quantity) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = ?
        ");
         if (!$stmt) return false; // Prepare failed
        $stmt->bind_param("iiii", $userId, $productId, $newQuantity, $newQuantity);
    } else { // Quantity <= 0 means remove
        $stmt = $conn->prepare("DELETE FROM user_carts WHERE user_id = ? AND product_id = ?");
         if (!$stmt) return false; // Prepare failed
        $stmt->bind_param("ii", $userId, $productId);
    }
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function getDatabaseCartCount($conn, $userId) {
    $totalItems = 0;
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM user_carts WHERE user_id = ?");
    if ($stmt) { // Check prepare success
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $totalItems = $result['total'] ?? 0;
        $stmt->close();
    }
    return $totalItems;
}

function getProductQuantityFromDb($conn, $userId, $productId) {
     $currentQuantity = 0;
     $stmt = $conn->prepare("SELECT quantity FROM user_carts WHERE user_id = ? AND product_id = ?");
     if ($stmt) {
        $stmt->bind_param("ii", $userId, $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
             $currentQuantity = $result->fetch_assoc()['quantity'];
        }
        $stmt->close();
     }
     return $currentQuantity;
}
// --- End Database Interaction Functions ---


// --- Main Logic ---
if ($loggedIn) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        $response['message'] = 'Database connection error.';
    } else {
        $newQuantity = 0; // Initialize
        $dbSuccess = false; // Flag for DB operation result

        if ($action == 'add') {
            // Quantity for 'add' action usually means adding 1 item
             $currentQuantity = getProductQuantityFromDb($conn, $userId, $productId);
             $newQuantity = $currentQuantity + 1;
             $dbSuccess = updateDatabaseCart($conn, $userId, $productId, $newQuantity);
             if ($dbSuccess) $response['message'] = 'Item added to cart!';

        } elseif ($action == 'update') {
             // Quantity is explicitly provided by the request
             $newQuantity = $quantity;
             $dbSuccess = updateDatabaseCart($conn, $userId, $productId, $newQuantity);
              if ($dbSuccess) $response['message'] = $newQuantity > 0 ? 'Cart updated.' : 'Item removed from cart.';

        } elseif ($action == 'remove') {
             // Explicitly remove the item (set quantity to 0 in DB or DELETE)
             $newQuantity = 0;
             $dbSuccess = updateDatabaseCart($conn, $userId, $productId, $newQuantity);
             if ($dbSuccess) $response['message'] = 'Item removed from cart.';
        }

        // Set response based on DB operation result
        if ($dbSuccess) {
            $response['success'] = true;
            $response['cart_count'] = getDatabaseCartCount($conn, $userId); // Update count
        } else {
            $response['message'] = $response['message'] ?: 'Failed to update database cart.'; // Keep specific message if set, otherwise generic fail
        }

        $conn->close();
    }
} else {
    // --- Session Cart Logic (for non-logged-in users) ---
    // Note: 'add' action here might receive quantity=1 from cart.js
    if ($action == 'add') {
         $currentQuantity = isset($cart[$productId]['quantity']) ? $cart[$productId]['quantity'] : 0;
         $cart[$productId] = ['quantity' => $currentQuantity + 1]; // Always add 1 for 'add'
         $response['success'] = true;
         $response['message'] = 'Item added to cart!';
    } elseif ($action == 'update') {
         if ($quantity > 0) {
              // Update quantity or add if not present (though typically updated from cart page)
              $cart[$productId] = ['quantity' => $quantity];
              $response['success'] = true;
              $response['message'] = 'Cart updated.';
         } else {
              // Remove if quantity is 0 or less
              unset($cart[$productId]);
              $response['success'] = true;
              $response['message'] = 'Item removed from cart.';
         }
    } elseif ($action == 'remove') {
        unset($cart[$productId]);
        $response['success'] = true;
        $response['message'] = 'Item removed from cart.';
    } else {
        $response['message'] = 'Invalid action for session cart.';
    }

    // Calculate total items in session cart
    $totalItems = 0;
    foreach ($cart as $item) { $totalItems += $item['quantity']; }
    $response['cart_count'] = $totalItems;
}

// --- Final Response ---
header('Content-Type: application/json');
echo json_encode($response);
?>