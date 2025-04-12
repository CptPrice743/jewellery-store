<?php
session_start();

// Make sure user is logged in to view their order
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$userId = $_SESSION['user_id']; // Get logged-in user's ID

// Check if order_id is provided and numeric
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: store.php"); // Redirect if order ID is missing or invalid
    exit();
}

$orderId = (int)$_GET['order_id'];


// Database connection
$servername = "localhost";
$username = "root";
$password = "vyom0403";
$dbname = "jewellery_store"; // Ensure password is correct
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch order details AND verify it belongs to the logged-in user
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
} // Check prepare success
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Order not found or doesn't belong to this user
    $stmt->close();
    $conn->close();
    header("Location: store.php"); // Or show an "Order not found" page
    exit();
}
$order = $result->fetch_assoc();
$stmt->close();

// Fetch order items
$stmt = $conn->prepare("
    SELECT oi.quantity, oi.price_at_purchase, p.name, p.image_url
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
} // Check prepare success
$stmt->bind_param("i", $orderId);
$stmt->execute();
$itemsResult = $stmt->get_result();
$orderItems = [];
while ($item = $itemsResult->fetch_assoc()) {
    $orderItems[] = $item;
}
$stmt->close();


// --- Get Cart Count for Header ---
$cartCount = 0;
// Since checkout clears the cart, the count should be 0, but we query anyway for consistency
$count_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM user_carts WHERE user_id = ?");
if ($count_stmt) {
    $count_stmt->bind_param("i", $userId);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $cartCount = $count_result['total'] ?? 0;
    $count_stmt->close();
}
// --- End Cart Count ---

$conn->close(); // Close connection after fetching all data

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Prism Jewellery</title>
    <link rel="stylesheet" href="./resources/css/reset.css">
    <link rel="stylesheet" href="./resources/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .confirmation-container {
            padding: 2rem 5%;
            padding-top: 7rem;
            /* Account for fixed header */
            text-align: center;
            flex-grow: 1;
            /* Allow content to take up space */
        }

        .confirmation-container h1 {
            font-size: 2.2rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: "Playfair Display", serif;
        }

        .order-summary {
            max-width: 700px;
            /* Wider summary */
            margin: 2rem auto;
            text-align: left;
            border: 1px solid #eee;
            /* Lighter border */
            padding: 2rem;
            /* More padding */
            border-radius: 8px;
            background-color: #fdfdfd;
            /* Light background */
        }

        .order-summary h2 {
            margin-bottom: 1.5rem;
            /* More space below heading */
            border-bottom: 1px solid #ddd;
            padding-bottom: 0.8rem;
            font-size: 1.4rem;
            color: #333;
        }

        .order-summary p {
            margin-bottom: 0.8rem;
            line-height: 1.5;
            color: #555;
        }

        .order-summary p strong {
            color: #333;
        }

        .order-summary h3 {
            /* Style for "Items Ordered" */
            font-size: 1.2rem;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .order-items-table {
            width: 100%;
            margin-top: 1rem;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .order-items-table th,
        .order-items-table td {
            border: 1px solid #eee;
            padding: 0.8rem 1rem;
            text-align: left;
            vertical-align: middle;
        }

        .order-items-table th {
            background-color: #f8f8f8;
            font-weight: 600;
        }

        .order-items-table img {
            max-width: 50px;
            vertical-align: middle;
            margin-right: 10px;
            border-radius: 4px;
        }

        .order-items-table td:last-child {
            font-weight: 500;
        }

        /* Subtotal column */

        .continue-shopping-btn {
            padding: 0.8rem 1.8rem;
            display: inline-block;
            margin-top: 2rem;
            /* More space above button */
            text-decoration: none;
            /* Reuse button styles from style.css */
            background-color: var(--text-dark);
            color: var(--primary-color);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .continue-shopping-btn:hover {
            background-color: #555;
        }
    </style>
</head>

<body>
    <header>
        <div class="content">
            <a href="index.php" class="desktop logo">Prism Jewellery</a>
            <nav class="desktop">
                <ul>
                    <li><a href="./index.php">Home</a></li>
                    <li><a href="./about-us.php">About us</a></li>
                    <li><a href="./store.php">Store</a></li>
                    <li><a href="cart_page.php">Cart (<span id="cart-count"><?php echo $cartCount; ?></span>)</a></li>
                    <?php // Logout link is always shown here since user must be logged in 
                    ?>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
            <nav class="mobile">
                <ul>
                    <li><a href="./index.php">Prism Jewellery</a></li>
                    <li><a href="./about-us.php">About Us</a></li>
                    <li><a href="./store.php">Store</a></li>
                    <li><a href="cart_page.php">Cart (<span id="cart-count-mobile"><?php echo $cartCount; ?></span>)</a></li>
                    <li><a href="logout.php" class="button">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="confirmation-container">
        <h1>Thank You For Your Order!</h1>
        <p>Your order has been placed successfully and will be processed shortly.</p>

        <div class="order-summary">
            <h2>Order Summary (ID: <?php echo $order['order_id']; ?>)</h2>
            <p><strong>Order Date:</strong> <?php echo date("F j, Y, g:i a", strtotime($order['order_date'])); ?></p>
            <p><strong>Order Status:</strong> <?php echo htmlspecialchars(ucfirst($order['status'])); // Capitalize status 
                                                ?></p>
            <p><strong>Order Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>

            <?php if (!empty($orderItems)): ?>
                <h3>Items Ordered:</h3>
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars($item['image_url'] ?: './resources/images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>$<?php echo number_format($item['price_at_purchase'], 2); ?></td>
                                <td>$<?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <a href="store.php" class="continue-shopping-btn">Continue Shopping</a>
    </div>

    <footer>
        <div class="content">
            <span class="copyright">© 2024 Prism Jewellery, All Rights Reserved</span>
            <span class="location">Designed by Vyom Uchat (22BCP450)</span>
        </div>
    </footer>
</body>

</html>