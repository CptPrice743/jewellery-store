<?php
session_start();

// Redirect if cart is empty or user not logged in
if (empty($_SESSION['cart']) || !isset($_SESSION['user_id'])) {
     header("Location: store.php");
     exit();
}

// Database connection
$servername = "localhost"; $username = "root"; $password = "1234"; $dbname = "jewellery_store";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$userId = $_SESSION['user_id'];
$cartItemsData = [];
$cartTotal = 0;

// --- Re-fetch product data to ensure prices are current ---
$productIds = array_keys($_SESSION['cart']);
$ids_string = implode(',', $productIds);
$sql = "SELECT product_id, price FROM products WHERE product_id IN ($ids_string)";
$result = $conn->query($sql);
$productsData = [];
while($row = $result->fetch_assoc()) { $productsData[$row['product_id']] = $row; }

// Calculate total and prepare order items
foreach ($_SESSION['cart'] as $productId => $item) {
    if (isset($productsData[$productId])) {
        $price = $productsData[$productId]['price'];
        $quantity = $item['quantity'];
        $cartItemsData[] = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'price_at_purchase' => $price
        ];
        $cartTotal += $price * $quantity;
    } else {
        // Handle error: product in cart not found in DB
        unset($_SESSION['cart'][$productId]); // Remove invalid item
        // Potentially redirect back to cart with an error message
        header("Location: cart_page.php?error=invalid_item");
        exit();
    }
}

// --- Insert Order into Database ---
$conn->begin_transaction(); // Start transaction

try {
    // 1. Insert into 'orders' table
    // Add more fields like shipping_address if collected
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, ?)");
    $status = 'Pending'; // Initial status
    $stmt->bind_param("ids", $userId, $cartTotal, $status);
    $stmt->execute();
    $orderId = $conn->insert_id; // Get the ID of the inserted order
    $stmt->close();

     if (!$orderId) {
        throw new Exception("Failed to create order record.");
    }


    // 2. Insert into 'order_items' table
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
    foreach ($cartItemsData as $item) {
        $stmt->bind_param("iiid", $orderId, $item['product_id'], $item['quantity'], $item['price_at_purchase']);
        $stmt->execute();
         if ($stmt->affected_rows <= 0) {
             throw new Exception("Failed to insert order item for product ID: " . $item['product_id']);
        }
        // Optional: Reduce stock in 'products' table here
    }
    $stmt->close();

    // 3. Commit transaction
    $conn->commit();

    // 4. Clear the cart session
    unset($_SESSION['cart']);

    // 5. Redirect to Order Confirmation page
    header("Location: order_confirmation.php?order_id=" . $orderId);
    exit();

} catch (Exception $e) {
    $conn->rollback(); // Rollback changes on error
    // Log the error: error_log("Order placement failed: " . $e->getMessage());
     // Redirect back to cart with an error message
     header("Location: cart_page.php?error=order_failed");
     exit();
} finally {
    $conn->close();
}
?>