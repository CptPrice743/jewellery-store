<?php
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

$emailErr = $passwordErr = "";
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
        // Validate password format
        if (!preg_match("/^[a-zA-Z0-9_@#]+$/", $password)) {
            $passwordErr = "Invalid password format. Only Uppercase, Lowercase, Numbers, Symbols('_', '@', '#') are allowed";
        }
    }

    // If there are no errors, check if the email and password exist in the database
    if (empty($emailErr) && empty($passwordErr)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $loginSuccess = true;
            header("Location: store.php");
            exit();
        } else {
            $loginErr = "Invalid email or password";
        }
        $stmt->close();
    }
}

function inputData($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <!-- Imports -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./resources/css/style.css">
    <link rel="stylesheet" href="./resources/css/reset.css">
</head>
<body>
    <header>
        <div class="content">
            <a href="index.php" class="desktop logo" href="./index.php">Prism Jewellery</a>
            <nav class="desktop">
                <ul>
                    <li><a href="./about-us.php">About us</a></li>
                    <li><a href="https://www.instagram.com/">Follow us</a></li>
                </ul>
            </nav>
            <nav class="mobile">
                <ul>
                    <li><a href="./index.php">Prism Jewellery</a></li>
                    <li><a href="./about-us.php">About Us</a></li>
                    <li><a href="https://www.instagram.com/">Follow Us</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <div class="login-container">
        <div class="centere">
            <h1>Login</h1>
            <?php if ($loginSuccess) : ?>
                <!-- Redirect to store.html -->
            <?php else : ?>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="txt_field">
                        <input name="Email" type="email" placeholder="Email" required>
                    </div>
                    <span style="color:red" class="error">
                        <?php echo $emailErr; ?>
                    </span>
                    <div class="txt_field">
                        <input name="Password" type="password" placeholder="Password" required>
                    </div>
                    <span style="color:red" class="error">
                        <?php echo $passwordErr; ?>
                    </span>
                    <div style="margin-top: 10px" class="pass">Forgot Password?</div>
                    <input type="submit" value="Sign In">
                    <div class="singup_link">
                        Not a member? <a href="signup.php">Signup</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>