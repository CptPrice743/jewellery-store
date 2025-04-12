<?php
session_start();

// Redirect if cart is empty or user not logged in
// Use session cart check primarily, as DB cart might exist but shouldn't proceed
if (empty($_SESSION['cart']) || !isset($_SESSION['user_id'])) {
    header("Location: store.php"); // Redirect to store or cart page if appropriate
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "vyom0403"; // <-- CORRECTED PASSWORD
$dbname = "jewellery_store";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // Log the error and redirect with a generic message
    error_log("Checkout DB Connection failed: " . $conn->connect_error);
    // Redirect to cart page with an error - consider a more user-friendly way
    header("Location: cart_page.php?error=db_connection_failed");
    exit();
}
$conn->set_charset("utf8mb4"); // Good practice

$userId = $_SESSION['user_id'];
$cartItemsData = [];
$cartSubtotal = 0; // Use 'subtotal' as it's before taxes/fees

// --- Re-fetch product data to ensure prices are current ---
$productIds = array_keys($_SESSION['cart']);

// Ensure product IDs are valid integers before using in query
$sanitizedProductIds = array_map('intval', $productIds);
$validProductIds = array_filter($sanitizedProductIds, function ($id) {
    return $id > 0;
});

if (empty($validProductIds)) {
    // Cart was technically not empty in session, but contained invalid IDs
    unset($_SESSION['cart']); // Clear the invalid session cart
    $conn->close();
    header("Location: cart_page.php?error=invalid_cart_items");
    exit();
}

$ids_string = implode(',', $validProductIds);
$sql = "SELECT product_id, price FROM products WHERE product_id IN ($ids_string)";
$result = $conn->query($sql);

if (!$result) {
    error_log("Failed to fetch product prices in checkout: " . $conn->error);
    $conn->close();
    header("Location: cart_page.php?error=price_fetch_failed");
    exit();
}

$productsData = [];
while ($row = $result->fetch_assoc()) {
    $productsData[$row['product_id']] = $row;
}

// Calculate total and prepare order items, ensuring product exists
foreach ($_SESSION['cart'] as $productId => $item) {
    $productIdInt = (int)$productId; // Ensure integer key
    if (isset($productsData[$productIdInt])) {
        $price = $productsData[$productIdInt]['price'];
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;

        if ($quantity <= 0) { // Ignore items with zero or negative quantity
            unset($_SESSION['cart'][$productId]); // Remove invalid item from session
            continue; // Skip this item
        }

        $cartItemsData[] = [
            'product_id' => $productIdInt,
            'quantity' => $quantity,
            'price_at_purchase' => $price
        ];
        $cartSubtotal += $price * $quantity;
    } else {
        // Handle error: product in cart not found in DB or removed
        unset($_SESSION['cart'][$productId]); // Remove invalid item from session
        // Consider adding a message to the user, but redirecting is simpler for now
        error_log("Product ID $productId found in session cart but not in DB during checkout for user $userId.");
        // We don't redirect immediately, let the process continue with valid items
        // If $cartItemsData becomes empty after this loop, we handle it below.
    }
}

// If after validation, there are no valid items left to order
if (empty($cartItemsData)) {
    $conn->close();
    header("Location: cart_page.php?error=no_valid_items");
    exit();
}

// --- Apply Coupon / Calculate Final Total (fetch from session if stored) ---
// It's generally safer to recalculate totals here based on fetched prices
// and validated cart items, rather than relying solely on session totals.
// This example uses the subtotal calculated above. You might need to add
// logic here to re-apply coupons, calculate tax, shipping based on $cartSubtotal
// if you want the *exact* final amount stored in the order.
// For simplicity, this example uses the $cartSubtotal.
$orderTotalAmount = $cartSubtotal; // Or recalculate full total including tax/fees/discount

// --- Insert Order into Database ---
$conn->begin_transaction(); // Start transaction

try {
    // 1. Insert into 'orders' table
    // Add more fields like shipping_address, discount_amount, tax_amount etc. if collected/calculated
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed (orders): " . $conn->error);
    }
    $status = 'Pending'; // Initial status
    // Use the calculated $orderTotalAmount
    $stmt->bind_param("ids", $userId, $orderTotalAmount, $status);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed (orders): " . $stmt->error);
    }
    $orderId = $conn->insert_id; // Get the ID of the inserted order
    $stmt->close();

    if (!$orderId) {
        throw new Exception("Failed to create order record (no insert ID).");
    }

    // 2. Insert into 'order_items' table
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed (order_items): " . $conn->error);
    }
    foreach ($cartItemsData as $item) {
        $stmt->bind_param("iiid", $orderId, $item['product_id'], $item['quantity'], $item['price_at_purchase']);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed (order_items) for product ID {$item['product_id']}: " . $stmt->error);
        }
        // Optional: Reduce stock in 'products' table here (requires another query)
    }
    $stmt->close();

    // --- ADDED SECTION ---
    // 3. Clear the user's persistent cart from the database
    $stmt_clear_db_cart = $conn->prepare("DELETE FROM user_carts WHERE user_id = ?");
    if (!$stmt_clear_db_cart) {
        throw new Exception("Failed to prepare statement for clearing database cart: " . $conn->error);
    }
    $stmt_clear_db_cart->bind_param("i", $userId);
    if (!$stmt_clear_db_cart->execute()) {
        throw new Exception("Failed to execute statement for clearing database cart: " . $stmt_clear_db_cart->error);
    }
    $stmt_clear_db_cart->close();
    // --- END OF ADDED SECTION ---

    // 4. Clear the cart session (This should be done AFTER DB clear)
    unset($_SESSION['cart']);
    // Optional: Unset related total variables if they exist
    unset($_SESSION['cart_subtotal'], $_SESSION['cart_discount_amount'], $_SESSION['cart_tax_amount'], $_SESSION['cart_final_total'], $_SESSION['applied_coupon']);


    // 5. Commit transaction
    $conn->commit();

    // 6. Redirect to Order Confirmation page
    header("Location: order_confirmation.php?order_id=" . $orderId);
    exit();
} catch (Exception $e) {
    $conn->rollback(); // Rollback changes on error
    // Log the error
    error_log("Order placement failed for user $userId: " . $e->getMessage());
    // Redirect back to cart with a generic error message
    header("Location: cart_page.php?error=order_failed");
    exit();
} finally {
    // Ensure connection is always closed
    if ($conn) {
        $conn->close();
    }
}
