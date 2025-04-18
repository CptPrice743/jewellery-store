<?php
session_start();
?>
<!DOCTYPE html>
<html>

<head>
  <link href="https://fonts.googleapis.com/css?family=Damion" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Rubik" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,600,700" rel="stylesheet">
  <link rel="stylesheet" href="./resources/css/reset.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
  <link
    href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css"
    rel="stylesheet" />
  <link rel="stylesheet" href="./resources/css/universal.css">
  <link rel="stylesheet" href="./resources/css/landing.css">
  <link rel="stylesheet" href="./resources/css/responsive.css">

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
          <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="./store.php">Store</a></li>
            <li><a href="cart_page.php">Cart (<span id="cart-count-header"><?php echo array_sum(array_column($_SESSION['cart'] ?? [], 'quantity')); ?></span>)</a></li>
            <li><a href="logout.php">Logout</a></li>
          <?php else: ?>
            <li><a href="./store.php" class="button buy-now-button">Buy now</a></li>
          <?php endif; ?>
        </ul>
      </nav>
      <nav class="mobile">
        <ul>
          <li><a href="./index.php">Prism Jewellery</a></li>
          <li><a href="./about-us.php">About Us</a></li>
          <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="./store.php">Store</a></li>
            <li><a href="cart_page.php">Cart (<span id="cart-count-mobile-header"><?php echo array_sum(array_column($_SESSION['cart'] ?? [], 'quantity')); ?></span>)</a></li>
            <li><a href="logout.php" class="button">Logout</a></li>
          <?php else: ?>
            <li><a href="./store.php" class="button buy-now-button">Buy now</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </header>

  <div class="main-content">

    <div id="sign-up-section" class="banner">
      <div id="sign-up-cta">
        <div class="content center">
          <div class="header">
            <h2 class="cursive">Discover Timeless Beauty</h2>
            <h1 class="striking">PRISM JEWELLERY</h1>
          </div>
          <div class="email">
            <span>
              Email us to request a demo and be in our waiting list for the <strong>Doorstep Tryout</strong> facility!
            </span>
            <div class="button email-button">Email us now!</div>
          </div>
        </div>
      </div>
    </div>

    <div id="features-section">
      <div class="feature">
        <div class="center">
          <div class="image-container">
            <img src="./resources/images/feature-1.jpg" />
          </div>
          <div class="content">
            <h2>Less is More</h2>
            <h3>Discover understated elegance in every piece. Our minimalist jewelry is designed to complement your unique style.</h3>
          </div>
        </div>
      </div>
      <div class="feature">
        <div class="center">
          <div class="image-container">
            <img src="./resources/images/feature-2.jpg" />
          </div>
          <div class="content">
            <h2>Simplicity, Refined</h2>
            <h3>Experience the beauty of simplicity with our collection of refined jewelry. Each piece is a testament to timeless design.</h3>
          </div>
        </div>
      </div>
    </div>

    <div id="filters-section">
      <div class="content center">
        <h2>Over 600+ carefully crafted designs to choose from.</h2>
        <h3>Each piece is a testament to our dedication to artistry and quality. Discover the perfect accessory to elevate your style.</h3>
      </div>
      <div class="images-container">
        <div class="image-container">
          <img src="./resources/images/cropped-filter-1.jpg" />
        </div>
        <div class="image-container">
          <img src="./resources/images/cropped-filter-2.jpg" />
        </div>
        <div class="image-container">
          <img src="./resources/images/cropped-filter-3.jpg">
        </div>
        <div class="image-container extra">
          <img src="./resources/images/cropped-filter-4.jpg" />
        </div>
      </div>
    </div>

    <div id="quotes-section">
      <div class="content center">
        <span class="quote">“ Each piece is a masterpiece of craftsmanship and design, showcasing an unparalleled attention to detail.”</span>
        <img class="quote-citation" src="./resources/images/Natural_Diamonds_logo_cropped.png" />
      </div>
    </div>

    <footer>
      <div class="content">
        <span class="copyright">© 2024 Prism Jewellery, All Rights Reserved</span>
        <span class="location">Designed by Vyom Uchat (22BCP450)</span>
      </div>
    </footer>

  </div>

  <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@latest/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://cdn.jsdelivr.net/npm/ionicons@latest/dist/ionicons/ionicons.js"></script>
  <script src="./resources/js/index.js"></script>
</body>

</html>