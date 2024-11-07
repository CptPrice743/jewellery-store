<?php
// Database connection details
$servername = "localhost";
$username = "root";
$password = "1234";
$dbname = "jewellery_store";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$nameErr = $emailErr = $passwordErr = $confirmPasswordErr = "";
$name = $email = $password = $confirmPassword = "";
$signupSuccess = false;

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Name validation
    if (empty($_POST["Name"])) {
        $nameErr = "Name is required";
    } else {
        $name = inputData($_POST["Name"]);
        // Check if name contains only letters and underscores
        if (!preg_match("/^[a-zA-Z_]+$/", $name)) {
            $nameErr = "Only alphabets and underscores are allowed.";
        }
    }

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

    // Confirm Password validation
    if (empty($_POST["ConfirmPassword"])) {
        $confirmPasswordErr = "Confirm Password is required";
    } else {
        $confirmPassword = inputData($_POST["ConfirmPassword"]);
        if ($password !== $confirmPassword) {
            $confirmPasswordErr = "Passwords do not match";
        }
    }

    // If there are no errors, insert the data into the database
    if (empty($nameErr) && empty($emailErr) && empty($passwordErr) && empty($confirmPasswordErr)) {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $password);
        if ($stmt->execute()) {
            $signupSuccess = true;
        } else {
            echo "Error: " . $stmt->error;
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
    <title>Signup Page</title>
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
            <a href="index.html" class="desktop logo" href="./index.html">Prism Jewellery</a>
            <nav class="desktop">
                <ul>
                    <li><a href="./about-us.html">About us</a></li>
                    <li><a href="https://www.instagram.com/">Follow us</a></li>
                </ul>
            </nav>
            <nav class="mobile">
                <ul>
                    <li><a href="./index.html">Prism Jewellery</a></li>
                    <li><a href="./about-us.html">About Us</a></li>
                    <li><a href="https://www.instagram.com/">Follow Us</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <div class="login-container">
        <div class="centere">
            <h1>Signup</h1>
            <?php if ($signupSuccess) : ?>
                <p style="color: green;">Account created successfully. <a href="login.php">Click here to login</a>.</p>
            <?php else : ?>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="txt_field">
                        <input name="Name" type="text" placeholder="Username" required>
                    </div>
                    <span style="color:red" class="error">
                        <?php echo $nameErr; ?>
                    </span>
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
                    <div class="txt_field">
                        <input name="ConfirmPassword" type="password" placeholder="Confirm Password" required>
                    </div>
                    <span style="color:red" class="error">
                        <?php echo $confirmPasswordErr; ?>
                    </span>
                    <input type="submit" value="Sign Up">
                    <div class="singup_link">
                        Already have an account? <a href="login.php">Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>