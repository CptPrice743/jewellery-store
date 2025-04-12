<?php
session_start();

// Database connection
$servername = "localhost"; $username = "root"; $password = "vyom0403"; $dbname = "jewellery_store";

// --- Initialize Response ---
// Add new fields for detailed cart state
$response = [
    'success' => false,
    'message' => '',
    'cart_count' => 0,
    'cart_subtotal' => 0.00,
    'cart_discount' => 0.00, // Total discount (including coupon)
    'cart_tax' => 0.00,      // Example tax
    'cart_total' => 0.00,
    'item_quantity' => 0,    // Quantity of the updated item
    'item_subtotal' => 0.00, // Subtotal of the updated item
    'coupon_removed' => false // Flag if coupon was invalidated
];

$loggedIn = isset($_SESSION['user_id']);
$userId = $loggedIn ? $_SESSION['user_id'] : null;

// Session cart for non-logged in (initialize if needed)
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }
$cart = &$_SESSION['cart']; // Reference session cart

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : null; // Can be 0 for removal
$action = isset($_POST['action']) ? $_POST['action'] : null; // 'add', 'update', 'remove'

// Basic Input Validation
if ($productId === null || $action === null || ($action != 'remove' && $quantity === null) || ($action != 'remove' && (!is_numeric($quantity) || $quantity < 0))) {
    $response['message'] = 'Missing or invalid parameters.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
// Ensure quantity is 0 if action is 'remove'
if ($action == 'remove') {
    $quantity = 0;
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $response['message'] = 'Database connection error.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// --- Check if Coupon Was Applied Before Update ---
$couponWasApplied = isset($_SESSION['applied_coupon']);
$appliedCouponCode = $couponWasApplied ? $_SESSION['applied_coupon']['code'] : null;

// --- Perform Cart Operation (DB or Session) ---
$operationSuccess = false;
if ($loggedIn) {
    // Database operations
    if ($quantity > 0) {
        $stmt = $conn->prepare("INSERT INTO user_carts (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
        if ($stmt) {
            $stmt->bind_param("iiii", $userId, $productId, $quantity, $quantity);
            $operationSuccess = $stmt->execute();
            $stmt->close();
        }
    } else { // Remove item
        $stmt = $conn->prepare("DELETE FROM user_carts WHERE user_id = ? AND product_id = ?");
         if ($stmt) {
            $stmt->bind_param("ii", $userId, $productId);
            $operationSuccess = $stmt->execute();
            $stmt->close();
         }
    }
} else {
    // Session operations
    if ($quantity > 0) {
        $cart[$productId] = ['quantity' => $quantity];
        $operationSuccess = true;
    } else { // Remove item
        unset($cart[$productId]);
        $operationSuccess = true;
    }
}

// --- If operation failed, return error ---
if (!$operationSuccess) {
    $response['message'] = 'Failed to update cart item.';
    $conn->close();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


// --- Recalculate Entire Cart State After Update ---
$cartSubtotal = 0;
$cartCount = 0;
$itemSubtotal = 0; // For the specific item updated
$itemPrice = 0;    // Price of the specific item updated

if ($loggedIn) {
    $sql = "SELECT uc.product_id, uc.quantity, p.price
            FROM user_carts uc
            JOIN products p ON uc.product_id = p.product_id
            WHERE uc.user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $current_item_subtotal = $row['price'] * $row['quantity'];
            $cartSubtotal += $current_item_subtotal;
            $cartCount += $row['quantity'];
            if ($row['product_id'] == $productId) { // Check if this is the item we just updated
                $itemSubtotal = $current_item_subtotal;
                $itemPrice = $row['price']; // Store price for later calculation if needed
            }
        }
        $stmt->close();
    }
} else {
     // Session recalculation
     $productIds = array_keys($cart);
     if (!empty($productIds)) {
        $sanitized_ids = array_map('intval', $productIds);
        $ids_string = implode(',', $sanitized_ids);
        $sql = "SELECT product_id, price FROM products WHERE product_id IN ($ids_string)";
        $result = $conn->query($sql);
        $productsData = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) { $productsData[$row['product_id']] = $row; }
        }
        foreach ($cart as $pid => $item) {
             if (isset($productsData[$pid])) {
                $current_item_subtotal = $productsData[$pid]['price'] * $item['quantity'];
                $cartSubtotal += $current_item_subtotal;
                $cartCount += $item['quantity'];
                 if ($pid == $productId) {
                     $itemSubtotal = $current_item_subtotal;
                     $itemPrice = $productsData[$pid]['price'];
                 }
            }
        }
     }
}

// --- Recalculate Discount (Check if coupon still valid) ---
$couponDiscount = 0.00;
$finalTotal = 0.00;
$taxAmount = 0.00; // Keep tax logic if needed

if ($couponWasApplied && $cartSubtotal > 0) {
    // Re-validate coupon against new subtotal
    $stmt_coupon = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) AND (min_spend IS NULL OR ? >= min_spend)");
     if ($stmt_coupon) {
        $stmt_coupon->bind_param("sd", $appliedCouponCode, $cartSubtotal);
        $stmt_coupon->execute();
        $coupon_result = $stmt_coupon->get_result();
        if ($coupon = $coupon_result->fetch_assoc()) {
             // Coupon still valid, recalculate discount
             if ($coupon['discount_type'] == 'percentage') {
                 $couponDiscount = ($cartSubtotal * $coupon['discount_value'] / 100);
             } elseif ($coupon['discount_type'] == 'fixed') {
                 $couponDiscount = $coupon['discount_value'];
             }
             $couponDiscount = min($couponDiscount, $cartSubtotal); // Cap discount
             // Update session
             $_SESSION['applied_coupon']['discount'] = $couponDiscount;
        } else {
            // Coupon is NO LONGER valid (e.g., subtotal dropped below min_spend)
            unset($_SESSION['applied_coupon']);
            $couponDiscount = 0;
            $response['coupon_removed'] = true; // Signal to JS
        }
        $stmt_coupon->close();
     } else {
         // Error checking coupon
         unset($_SESSION['applied_coupon']);
         $couponDiscount = 0;
         $response['coupon_removed'] = true; // Signal to JS
     }

} else {
    // Coupon wasn't applied or cart is now empty
    if ($couponWasApplied && $cartSubtotal <= 0) {
        // If cart became empty, explicitly remove coupon
        unset($_SESSION['applied_coupon']);
        $response['coupon_removed'] = true;
    }
    $couponDiscount = 0;
}

// Final Calculations
$discountAmount = $couponDiscount; // Total discount is just coupon for now
$finalTotal = $cartSubtotal - $discountAmount + $taxAmount;
$finalTotal = max(0, $finalTotal); // Ensure non-negative total

// Store final total in session for checkout
$_SESSION['cart_final_total'] = $finalTotal;
$_SESSION['cart_discount_amount'] = $discountAmount;

// --- Populate Response Object ---
$response['success'] = true;
$response['message'] = $action == 'remove' || $quantity == 0 ? 'Item removed from cart.' : 'Cart updated.';
if ($response['coupon_removed']) {
     $response['message'] .= ' Coupon invalidated due to cart changes.';
}
$response['cart_count'] = $cartCount;
$response['cart_subtotal'] = $cartSubtotal;
$response['cart_discount'] = $discountAmount;
$response['cart_tax'] = $taxAmount;
$response['cart_total'] = $finalTotal;
$response['item_quantity'] = $quantity; // The *new* quantity of the item (0 if removed)
// Recalculate item subtotal based on new quantity and known price
$response['item_subtotal'] = $itemPrice * $quantity;

$conn->close();

// --- Final Response ---
header('Content-Type: application/json');
echo json_encode($response);
?>