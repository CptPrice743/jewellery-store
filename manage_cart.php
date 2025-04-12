<?php
// START OF SCRIPT - Suppress direct error output, use logging instead for debugging
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// **IMPORTANT**: Ensure database credentials are correct and consistent across all files.
$servername = "localhost";
$username = "root";
$password = "vyom0403"; // Make sure this is your correct password
$dbname = "jewellery_store";

// --- Define Fees and Tax Rate ---
define('TAX_RATE', 0.18); // 18%
define('SHIPPING_FEE', 49.00);
define('PLATFORM_FEE', 3.99);

// --- Initialize Response ---
// Default values assume failure until proven otherwise
$response = [
    'success' => false,
    'message' => 'An unknown error occurred while processing the cart.',
    'cart_count' => 0,
    'cart_subtotal' => 0.00,
    'cart_discount' => 0.00,
    'cart_tax' => 0.00,
    'shipping_fee' => 0.00, // Initialize new fees
    'platform_fee' => 0.00, // Initialize new fees
    'cart_total' => 0.00,
    'item_quantity' => 0,
    'item_subtotal' => 0.00,
    'coupon_removed' => false,
    'applied_coupon_code' => null
];

// --- Helper Function: Send JSON Response and Exit ---
function sendJsonResponseAndExit($data)
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit();
}

// --- Setup Global Exception Handler ---
set_exception_handler(function ($exception) use ($response) {
    error_log("Uncaught Exception in manage_cart.php: " . $exception->getMessage());
    $response['message'] = "A server error occurred. Please try again.";
    sendJsonResponseAndExit($response);
});


// --- Input Validation ---
$productId = isset($_POST['product_id']) ? filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) : null;
$quantity = isset($_POST['quantity']) ? filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) : null;
$action = isset($_POST['action']) ? filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) : null;

if ($productId === false || $productId === null || $action === null || !in_array($action, ['add', 'update', 'remove'])) {
    $response['message'] = 'Invalid product ID or action specified.';
    sendJsonResponseAndExit($response);
}
if (($action === 'add' || $action === 'update') && ($quantity === null || $quantity === false || $quantity < 0)) {
    $response['message'] = 'Invalid quantity specified for add/update action.';
    sendJsonResponseAndExit($response);
}
if ($action === 'remove') $quantity = 0;

// --- Determine User Status ---
$loggedIn = isset($_SESSION['user_id']);
$userId = $loggedIn ? $_SESSION['user_id'] : null;
if (!$loggedIn && !isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// --- Database Connection ---
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("DB Connection Error in manage_cart: " . $conn->connect_error);
    $response['message'] = 'Database connection error. Cannot update cart.';
    sendJsonResponseAndExit($response);
}
$conn->set_charset("utf8mb4");

// --- Perform Cart Operation ---
$operationSuccess = false;
$dbError = null;

try {
    // Update DB and Session based on action
    if ($loggedIn) {
        if ($quantity > 0) {
            $stmt = $conn->prepare("INSERT INTO user_carts (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
            if (!$stmt) throw new Exception("DB prepare error (insert/update): " . $conn->error);
            $stmt->bind_param("iiii", $userId, $productId, $quantity, $quantity);
            $operationSuccess = $stmt->execute();
            if (!$operationSuccess) $dbError = $stmt->error;
            $stmt->close();
            if ($operationSuccess) {
                $_SESSION['cart'][$productId] = ['quantity' => $quantity];
            }
        } else {
            $stmt = $conn->prepare("DELETE FROM user_carts WHERE user_id = ? AND product_id = ?");
            if (!$stmt) throw new Exception("DB prepare error (delete): " . $conn->error);
            $stmt->bind_param("ii", $userId, $productId);
            $operationSuccess = $stmt->execute();
            if (!$operationSuccess) $dbError = $stmt->error;
            $stmt->close();
            if ($operationSuccess && isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
            }
        }
    } else { // Guest User
        if ($quantity > 0) {
            $_SESSION['cart'][$productId] = ['quantity' => $quantity];
            $operationSuccess = true;
        } else {
            if (isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
            }
            $operationSuccess = true;
        }
    }

    if (!$operationSuccess) {
        throw new Exception('Failed to update cart item.' . ($dbError ? ' DB Error: ' . $dbError : ''));
    }

    // --- Recalculate Entire Cart State After Update ---
    $cartSubtotal = 0;
    $cartCount = 0;
    $itemSubtotal = 0;
    $itemPrice = 0;
    $currentProductPrices = [];
    $allProductIdsInCart = isset($_SESSION['cart']) ? array_keys($_SESSION['cart']) : [];

    // Fetch current prices
    if (!empty($allProductIdsInCart)) {
        $sanitized_ids = array_map('intval', $allProductIdsInCart);
        if (!empty($sanitized_ids)) {
            $ids_string = implode(',', $sanitized_ids);
            $sql_prices = "SELECT product_id, price FROM products WHERE product_id IN ($ids_string)";
            $result_prices = $conn->query($sql_prices);
            if ($result_prices) {
                while ($row = $result_prices->fetch_assoc()) {
                    $currentProductPrices[$row['product_id']] = $row['price'];
                }
            } else {
                error_log("Failed to fetch current product prices: " . $conn->error);
            }
        }
    }

    // Recalculate subtotal based on SESSION cart
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $pid => $item) {
            $qty = $item['quantity'];
            if (isset($currentProductPrices[$pid])) {
                $price = $currentProductPrices[$pid];
                $current_item_subtotal = $price * $qty;
                $cartSubtotal += $current_item_subtotal;
                $cartCount += $qty;
                if ($pid == $productId) {
                    $itemSubtotal = $current_item_subtotal;
                    $itemPrice = $price;
                }
            } else {
                error_log("Price not found for product ID (Session Cart recalc): " . $pid . " - Removing from session.");
                unset($_SESSION['cart'][$pid]);
            }
        }
    }

    // --- Apply Coupon Discount ---
    $couponDiscount = 0.00;
    $couponWasApplied = isset($_SESSION['applied_coupon']);
    $appliedCouponCode = null;
    $couponRemovedFlag = false;

    if ($couponWasApplied && $cartSubtotal > 0) {
        // [Coupon validation logic remains the same as before]
        // ... (ensure $couponDiscount is calculated and $appliedCouponCode is set if valid) ...
        $couponData = $_SESSION['applied_coupon'];
        $codeToCheck = $couponData['code'];

        $stmt_coupon = $conn->prepare("SELECT discount_type, discount_value, min_spend FROM coupons WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
        if ($stmt_coupon) {
            $stmt_coupon->bind_param("s", $codeToCheck);
            $stmt_coupon->execute();
            $coupon_result = $stmt_coupon->get_result();
            if ($coupon_details = $coupon_result->fetch_assoc()) {
                if ($coupon_details['min_spend'] === null || $cartSubtotal >= $coupon_details['min_spend']) {
                    if ($coupon_details['discount_type'] == 'percentage') {
                        $couponDiscount = ($cartSubtotal * $coupon_details['discount_value'] / 100);
                    } elseif ($coupon_details['discount_type'] == 'fixed') {
                        $couponDiscount = $coupon_details['discount_value'];
                    }
                    $couponDiscount = min($couponDiscount, $cartSubtotal);
                    $_SESSION['applied_coupon']['discount'] = $couponDiscount;
                    $appliedCouponCode = $codeToCheck;
                } else {
                    unset($_SESSION['applied_coupon']);
                    $couponRemovedFlag = true;
                }
            } else {
                unset($_SESSION['applied_coupon']);
                $couponRemovedFlag = true;
            }
            $stmt_coupon->close();
        } else {
            error_log("Failed to prepare coupon re-check statement: " . $conn->error);
            unset($_SESSION['applied_coupon']);
            $couponRemovedFlag = true;
        }
    } else {
        if ($couponWasApplied && $cartSubtotal <= 0) { // Explicitly handle cart becoming empty
            unset($_SESSION['applied_coupon']);
            $couponRemovedFlag = true;
        }
    }


    // --- Final Calculations (Including Tax and Fees) ---
    $discountAmount = round($couponDiscount, 2);
    $subtotalAfterDiscount = $cartSubtotal - $discountAmount;

    // Calculate Tax only if there are items and subtotal is positive after discount
    $taxAmount = 0.00;
    if ($cartCount > 0 && $subtotalAfterDiscount > 0) {
        $taxAmount = round($subtotalAfterDiscount * TAX_RATE, 2);
    }

    // Determine Shipping and Platform fees (only apply if cart is not empty)
    $shippingFee = ($cartCount > 0) ? SHIPPING_FEE : 0.00;
    $platformFee = ($cartCount > 0) ? PLATFORM_FEE : 0.00;

    // Calculate Final Total
    $finalTotal = $subtotalAfterDiscount + $taxAmount + $shippingFee + $platformFee;
    $finalTotal = max(0, round($finalTotal, 2)); // Ensure non-negative and round

    // Update session totals (optional)
    $_SESSION['cart_final_total'] = $finalTotal;
    $_SESSION['cart_discount_amount'] = $discountAmount;
    $_SESSION['cart_subtotal'] = $cartSubtotal;
    $_SESSION['cart_tax_amount'] = $taxAmount;
    // Optional: Store other fees in session if needed elsewhere
    // $_SESSION['cart_shipping_fee'] = $shippingFee;
    // $_SESSION['cart_platform_fee'] = $platformFee;

    // --- Populate Response Object ---
    $response['success'] = true;
    $response['message'] = $action == 'remove' || $quantity == 0 ? 'Item removed successfully.' : 'Cart updated successfully.';
    if ($couponRemovedFlag) {
        $response['message'] .= ' Applied coupon was removed as it is no longer valid for the updated cart.';
    }
    $response['cart_count'] = $cartCount;
    $response['cart_subtotal'] = round($cartSubtotal, 2);
    $response['cart_discount'] = $discountAmount; // Already rounded
    $response['cart_tax'] = $taxAmount;           // Already rounded
    $response['shipping_fee'] = round($shippingFee, 2); // Add to response
    $response['platform_fee'] = round($platformFee, 2); // Add to response
    $response['cart_total'] = $finalTotal;          // Already rounded
    $response['item_quantity'] = $quantity;
    $response['item_subtotal'] = isset($itemPrice) ? round($itemPrice * $quantity, 2) : 0.00;
    $response['coupon_removed'] = $couponRemovedFlag;
    $response['applied_coupon_code'] = $appliedCouponCode;
} catch (Exception $e) {
    error_log("Error in manage_cart main try block: " . $e->getMessage());
    $response['message'] = "An internal error occurred while updating the cart.";
} finally {
    if ($conn) $conn->close();
}

// --- Final Response ---
sendJsonResponseAndExit($response);
