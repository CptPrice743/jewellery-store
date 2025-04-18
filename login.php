<?php
session_start();

// If user is already logged in, redirect them away from login page
if (isset($_SESSION['user_id'])) {
    header("Location: store.php"); // Or index.php or wherever you prefer
    exit();
}


// Database connection details
$servername = "localhost";
$username = "root";
$password = "vyom0403";
$dbname = "jewellery_store";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- FUNCTION TO LOAD CART (Define or include from another file) ---
function loadCartFromDatabase($conn, $userId)
{
    $_SESSION['cart'] = []; // Clear any temporary session cart
    $stmt = $conn->prepare("SELECT product_id, quantity FROM user_carts WHERE user_id = ?");
    if ($stmt) { // Check if prepare was successful
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($item = $result->fetch_assoc()) {
            $_SESSION['cart'][$item['product_id']] = ['quantity' => $item['quantity']];
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement in loadCartFromDatabase: " . $conn->error);
    }
}
// --- END FUNCTION ---


$emailErr = $passwordErr = $loginErr = ""; // Initialize loginErr
$email = $password = "";
$loginSuccess = false;

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Email validation
    if (empty($_POST["Email"])) {
        $emailErr = "Email is required";
    } else {
        $email = inputData($_POST["Email"]);
        // Check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = "Invalid email format";
        }
    }

    // Password validation
    if (empty($_POST["Password"])) {
        $passwordErr = "Password is required";
    } else {
        $password = inputData($_POST["Password"]);
        // Validate password format (Consider using password_hash and password_verify for security)
        if (!preg_match("/^[a-zA-Z0-9_@#]+$/", $password)) {
            $passwordErr = "Invalid password format.";
        }
    }

    // If there are no errors, check if the email and password exist in the database
    if (empty($emailErr) && empty($passwordErr)) {
        // *** IMPORTANT: Fetch user_id and name along with checking credentials ***
        // *** Assuming 'users' table has 'user_id', 'name', 'email', 'password' columns ***
        // *** SECURITY WARNING: Store hashed passwords, not plain text! Use password_verify() ***
        $stmt = $conn->prepare("SELECT user_id, name, password FROM users WHERE email = ?");
        if ($stmt) { // Check prepare success
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                // *** SECURITY: Verify hashed password instead of plain text comparison ***
                // if (password_verify($password, $user['password'])) { // Use this if passwords are hashed
                if ($password === $user['password']) { // TEMPORARY: If using plain text (NOT RECOMMENDED)
                    $loginSuccess = true;
                    $_SESSION['user_id'] = $user['user_id']; // Store user ID
                    $_SESSION['user_name'] = $user['name'];   // Store user name

                    // Load persistent cart
                    loadCartFromDatabase($conn, $_SESSION['user_id']);


                    header("Location: store.php");
                    exit();
                } else {
                    $loginErr = "Invalid email or password"; // Password mismatch
                }
            } else {
                $loginErr = "Invalid email or password"; // Email not found
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement in login: " . $conn->error);
            $loginErr = "An error occurred during login.";
        }
    }
}

function inputData($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data);
}
$conn->close(); // Close connection at the end
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./resources/css/reset.css">
    <link rel="stylesheet" href="./resources/css/universal.css">
    <link rel="stylesheet" href="./resources/css/login.css">
    <link rel="stylesheet" href="./resources/css/responsive.css">
</head>

<body>
    <header>
        <div class="content">
            <a href="index.php" class="desktop logo">Prism Jewellery</a>
            <nav class="desktop">
                <ul>
                    <li><a href="./index.php">Home</a></li>
                    <li><a href="./about-us.php">About us</a></li>
                </ul>
            </nav>
            <nav class="mobile">
                <ul>
                    <li><a href="./index.php">Prism Jewellery</a></li>
                    <li><a href="./about-us.php">About Us</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <div class="login-container">
        <div class="centere">
            <h1>Login</h1>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <?php if (!empty($loginErr)) : // Display login error if exists 
                ?>
                    <p style="color:red; text-align: center; margin-bottom: 15px;"><?php echo $loginErr; ?></p>
                <?php endif; ?>
                <div class="txt_field">
                    <input name="Email" type="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <span style="color:red" class="error">
                        <?php echo $emailErr; ?>
                    </span>
                </div>

                <div class="txt_field">
                    <input name="Password" type="password" placeholder="Password" required>
                    <span style="color:red" class="error">
                        <?php echo $passwordErr; ?>
                    </span>
                </div>

                <div style="margin-top: 10px" class="pass">Forgot Password?</div>
                <input type="submit" value="Sign In">
                <div class="singup_link">
                    Not a member? <a href="signup.php">Signup</a>
                </div>
            </form>

        </div>
    </div>
    <footer>
        <div class="content">
            <span class="copyright">© 2024 Prism Jewellery, All Rights Reserved</span>
            <span class="location">Designed by Vyom Uchat (22BCP450)</span>
        </div>
    </footer>
</body>

</html>