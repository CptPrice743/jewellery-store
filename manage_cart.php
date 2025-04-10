<?php
session_start();

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$response = ['success' => false, 'message' => '', 'cart_count' => 0];

// Check if product_id is provided (for adding items)
if (isset($_POST['action']) && $_POST['action'] == 'add' && isset($_POST['product_id'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    if ($quantity < 1) $quantity = 1; // Ensure quantity is at least 1

    // --- Optional: Check product existence and stock in DB ---
    // $servername = "localhost"; $username = "root"; $password = "1234"; $dbname = "jewellery_store";
    // $conn = new mysqli($servername, $username, $password, $dbname);
    // $stmt = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
    // $stmt->bind_param("i", $productId); $stmt->execute(); $result = $stmt->get_result();
    // if ($result->num_rows > 0) { $product = $result->fetch_assoc(); } else { /* Handle error */ }
    // $conn->close();
    // if ($product['stock'] < $quantity) { /* Handle insufficient stock */ }
    // --- End Optional DB Check ---

    // Add or update product in cart session
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += $quantity;
    } else {
        // You might want to fetch product details (name, price) here if needed later on cart page
        $_SESSION['cart'][$productId] = ['quantity' => $quantity];
    }

    $response['success'] = true;
    $response['message'] = 'Item added to cart!';

} elseif (isset($_POST['action']) && $_POST['action'] == 'update' && isset($_POST['product_id']) && isset($_POST['quantity'])) {
     $productId = (int)$_POST['product_id'];
     $quantity = (int)$_POST['quantity'];

     if ($quantity > 0) {
         if (isset($_SESSION['cart'][$productId])) {
             $_SESSION['cart'][$productId]['quantity'] = $quantity;
             $response['success'] = true;
             $response['message'] = 'Cart updated.';
         } else {
              $response['message'] = 'Item not found in cart.';
         }
     } else {
         // Remove item if quantity is 0 or less
         unset($_SESSION['cart'][$productId]);
         $response['success'] = true;
         $response['message'] = 'Item removed from cart.';
     }

} elseif (isset($_POST['action']) && $_POST['action'] == 'remove' && isset($_POST['product_id'])) {
    $productId = (int)$_POST['product_id'];
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
        $response['success'] = true;
        $response['message'] = 'Item removed from cart.';
    } else {
         $response['message'] = 'Item not found in cart.';
    }
}

// Calculate total items in cart for header update
$totalItems = 0;
foreach ($_SESSION['cart'] as $item) {
    $totalItems += $item['quantity'];
}
$response['cart_count'] = $totalItems;

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>