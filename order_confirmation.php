<?php
session_start();
if (!isset($_GET['order_id']) || !isset($_SESSION['user_id'])) {
    header("Location: store.php");
    exit();
}

$orderId = (int)$_GET['order_id'];
$userId = $_SESSION['user_id'];

// Database connection
$servername = "localhost"; $username = "root"; $password = "1234"; $dbname = "jewellery_store";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Fetch order details (ensure order belongs to the logged-in user)
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    // Order not found or doesn't belong to user
    header("Location: store.php"); // Or show an error page
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
$stmt->bind_param("i", $orderId);
$stmt->execute();
$itemsResult = $stmt->get_result();
$orderItems = [];
while ($item = $itemsResult->fetch_assoc()) {
    $orderItems[] = $item;
}
$stmt->close();
$conn->close();

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
         .confirmation-container { padding: 2rem 5%; margin-top: 60px; text-align: center; }
         .order-summary { max-width: 600px; margin: 2rem auto; text-align: left; border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; }
         .order-summary h2 { margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; }
         .order-summary p { margin-bottom: 0.5rem; }
         .order-items-table { width: 100%; margin-top: 1rem; border-collapse: collapse; }
         .order-items-table th, .order-items-table td { border: 1px solid #eee; padding: 0.5rem; text-align: left; }
         .order-items-table img { max-width: 40px; vertical-align: middle; margin-right: 5px;}
     </style>
</head>
<body>
    <header>
         <div class="content">
            <a href="index.html" class="desktop logo">Prism Jewellery</a>
            <nav class="desktop">
                 <ul>
                    <li><a href="./index.html">Home</a></li>
                    <li><a href="./about-us.html">About us</a></li>
                    <li><a href="./store.php">Store</a></li>
                     <li><a href="cart_page.php">Cart (0)</a></li> <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="confirmation-container">
        <h1>Thank You For Your Order!</h1>
        <p>Your order has been placed successfully.</p>

        <div class="order-summary">
            <h2>Order Summary (ID: <?php echo $order['order_id']; ?>)</h2>
            <p><strong>Order Date:</strong> <?php echo date("F j, Y, g:i a", strtotime($order['order_date'])); ?></p>
            <p><strong>Order Status:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
            <p><strong>Order Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
            <h3>Items Ordered:</h3>
            <table class="order-items-table">
                 <thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>
                 <tbody>
                 <?php foreach ($orderItems as $item): ?>
                     <tr>
                         <td>
                              <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="">
                              <?php echo htmlspecialchars($item['name']); ?>
                         </td>
                         <td><?php echo $item['quantity']; ?></td>
                         <td>$<?php echo number_format($item['price_at_purchase'], 2); ?></td>
                          <td>$<?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2); ?></td>
                     </tr>
                 <?php endforeach; ?>
                 </tbody>
            </table>
        </div>

        <a href="store.php" class="button" style="padding: 0.8rem 1.5rem; display: inline-block; margin-top: 1rem;">Continue Shopping</a>
    </div>

    <footer>
       <div class="content">
           <span class="copyright">© 2024  Prism Jewellery, All Rights Reserved</span>
           <span class="location">Designed by Vyom Uchat (22BCP450)</span>
       </div>
    </footer>
</body>
</html>