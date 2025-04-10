<?php
session_start(); // ADD THIS LINE
?>
<!DOCTYPE html>
<html>
<head>
  <link href="https://fonts.googleapis.com/css?family=Damion" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Rubik" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,600,700" rel="stylesheet">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
  <link
    href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="./resources/css/reset.css">
  <link rel="stylesheet" href="./resources/css/style.css">
  <link rel="stylesheet" href="./resources/css/about-us.css">

  <meta charset="UTF-8">
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
           <li><a href="cart_page.php">Cart (<span id="cart-count"><?php echo array_sum(array_column($_SESSION['cart'] ?? [], 'quantity')); ?></span>)</a></li>
          <li><a href="https://www.instagram.com/">Follow us</a></li>
          <?php if (isset($_SESSION['user_id'])): ?>
              <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>)</a></li>
          <?php else: ?>
              <li><a href="login.php">Login</a></li>
          <?php endif; ?>
        </ul>
      </nav>
      <nav class="mobile">
         <ul>
          <li><a href="./index.php">Prism Jewellery</a></li> <li><a href="./about-us.php">About Us</a></li>  <li><a href="https://www.instagram.com/">Follow Us</a></li> <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="logout.php" class="button">Logout</a></li>
           <?php else: ?>
                <li><a href="./login.php" class="button">Login</a></li>
           <?php endif; ?>
         </ul>
      </nav>
    </div>
  </header>

  <div class="about-section">
    <div class="content-container">
        <h1>About us</h1>
        <p class="intro-text">People love Prism Jewelry because it's where minimalist dreams are brought to life.</p>
        <h2>Discovering the Prism difference</h2>
        <p class="main-text">Prism Jewellery is more than just a store; it's a celebration of minimalism and elegance. We believe that true beauty lies in simplicity, and our collection reflects this philosophy. Each piece is a testament to our dedication to crafting jewelry that resonates with the modern woman.</p>
        <p class="main-text">Our founder, Vyom, is a visionary with a keen eye for design. Driven by a passion for creating timeless pieces, Vyom has poured his heart and soul into building Prism Jewellery. His belief in the power of adornment to elevate one's spirit is the cornerstone of our brand.</p>
        <p class="main-text">At Prism, we are committed to using only the finest materials and employing skilled artisans to bring our designs to life. Our jewelry is not just an accessory; it's an expression of individuality.</p>
    </div>
</div>
 <footer>
      <div class="content">
        <span class="copyright">© 2024  Prism Jewellery, All Rights Reserved</span>
        <span class="location">Designed by Vyom Uchat (22BCP450)</span>
      </div>
    </footer>
</body>
</html>