<?php
session_start();
require_once 'config.php';

// Connect sa MySQL database mo
$conn = getDBConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate required fields
    if (empty($_POST['firstName']) || empty($_POST['fullName']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['contact']) || empty($_POST['address'])) {
        die("Error: First name, display name, email, password, contact, and address are required!");
    }

    // Kunin ang data galing sa form
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName'] ?? '');
    $displayName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    
    // Validate display name
    if (empty($displayName)) {
        die("Error: Display name is required!");
    }
    if (mb_strlen($displayName) > 50) {
        die("Error: Display name must be 50 characters or fewer.");
    }

    // Validate firstName and lastName
    if (mb_strlen($firstName) > 50) {
        die("Error: First name must be 50 characters or fewer.");
    }
    if (mb_strlen($firstName) == 0) {
        die("Error: First name cannot be empty.");
    }
    if (mb_strlen($lastName) > 50) {
        die("Error: Last name must be 50 characters or fewer.");
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Error: Invalid email format!");
    }

    // ✅ PASSWORD VALIDATION (Server-side)
    if (strlen($password) < 8) {
        die("Error: Password must be at least 8 characters long!");
    }
    if (!preg_match('/[A-Z]/', $password)) {
        die("Error: Password must contain at least one uppercase letter!");
    }
    if (!preg_match('/[a-z]/', $password)) {
        die("Error: Password must contain at least one lowercase letter!");
    }
    if (!preg_match('/[0-9]/', $password)) {
        die("Error: Password must contain at least one number!");
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        die("Error: Password must contain at least one special character (!@#$%^&*(),.?\":{}|<>)!");
    }
    
    // Check if passwords match
    if ($password !== $confirmPassword) {
        die("Error: Passwords do not match!");
    }

    // Validate contact number format (11 digits)
    if (!preg_match('/^[0-9]{11}$/', $contact)) {
        die("Error: Contact number must be exactly 11 digits!");
    }

    // Length validations to match DB schema
    if (mb_strlen($email) > 50) {
        die("Error: Email must be 50 characters or fewer.");
    }
    if (mb_strlen($address) > 50) {
        die("Error: Address must be 50 characters or fewer.");
    }

	// Check if email already exists
	$check = $conn->prepare("SELECT acc_id FROM USER_ACCOUNT WHERE email = ? LIMIT 1");
	if (!$check) {
		die("Prepare failed: " . $conn->error);
	}
	$check->bind_param("s", $email);
	$check->execute();
	$checkResult = $check->get_result();
	if ($checkResult && $checkResult->num_rows > 0) {
		$check->close();
		die("Error: Email already registered. Please use a different email or log in.");
	}
	$check->close();

	// Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if firstName and lastName columns exist
    $checkCols = $conn->query("SHOW COLUMNS FROM USER_ACCOUNT LIKE 'firstName'");
    $hasFirstName = $checkCols && $checkCols->num_rows > 0;
    
    if (!$hasFirstName) {
        die("
        <html>
        <head><title>Database Setup Required</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
            .error-box { background: white; padding: 30px; border-radius: 10px; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #d32f2f; margin-top: 0; }
            code { background: #f5f5f5; padding: 15px; display: block; border-left: 4px solid #d32f2f; margin: 20px 0; font-size: 14px; }
        </style>
        </head>
        <body>
        <div class='error-box'>
            <h1>⚠️ Database Setup Required</h1>
            <p>Please run the database migration first.</p>
        </div>
        </body>
        </html>
        ");
    }
    
    // Check if fullName column exists
    $checkFullName = $conn->query("SHOW COLUMNS FROM USER_ACCOUNT LIKE 'fullName'");
    $hasFullName = $checkFullName && $checkFullName->num_rows > 0;
    
    if ($hasFullName) {
        $stmt = $conn->prepare("
            INSERT INTO USER_ACCOUNT (firstName, lastName, fullName, email, user_password, contNo, address, time_created, user_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'online')
        ");
        
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssss", $firstName, $lastName, $displayName, $email, $hashed_password, $contact, $address);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO USER_ACCOUNT (firstName, lastName, email, user_password, contNo, address, time_created, user_status)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'online')
        ");
        
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssss", $firstName, $lastName, $email, $hashed_password, $contact, $address);
    }

	try {
		$executed = $stmt->execute();
	} catch (mysqli_sql_exception $e) {
		if ((int)$e->getCode() === 1062) {
			$stmt->close();
			die("Error: Email already registered. Please use a different email or log in.");
		}
		throw $e;
	}

	if ($executed) {
		$user_id = $stmt->insert_id;
		$stmt->close();

		// Create email verification table
		$conn->query("CREATE TABLE IF NOT EXISTS email_verifications (
			id INT AUTO_INCREMENT PRIMARY KEY,
			acc_id INT NOT NULL,
			token VARCHAR(255) NOT NULL,
			expires_at DATETIME NOT NULL,
			used_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX (acc_id),
			UNIQUE KEY token_unique (token)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		// Create verification token
		$raw = bin2hex(random_bytes(32));
		$token = hash('sha256', $raw . uniqid('', true));
		$expires = date('Y-m-d H:i:s', time() + 24 * 60 * 60);

		$ins = $conn->prepare("INSERT INTO email_verifications (acc_id, token, expires_at) VALUES (?, ?, ?)");
		if ($ins) {
			$ins->bind_param("iss", $user_id, $token, $expires);
			$ins->execute();
			$ins->close();
		}

		// Send verification email
		require_once __DIR__ . '/mailer.php';
		$appUrl = getenv('APP_URL');
		if ($appUrl) {
			$link = rtrim($appUrl, "/") . "/verify-email.php?token=" . urlencode($token);
		} else {
			$link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/\\') . "/verify-email.php?token=" . urlencode($token);
		}
		try {
			$fromEmail = getenv('SMTP_FROM_EMAIL');
			$fromName = getenv('SMTP_FROM_NAME') ?: 'Ticketix';
			if ($fromEmail) {
				$mail->setFrom($fromEmail, $fromName);
			} elseif (!empty($mail->Username)) {
				$mail->setFrom($mail->Username, $fromName);
			}
			$displayNameForEmail = !empty($displayName) ? $displayName : ($firstName . ' ' . $lastName);
			$mail->addAddress($email, $displayNameForEmail);
			$mail->Subject = 'Verify your Ticketix email address';
			$mail->Body = '<p>Hi ' . htmlspecialchars($displayNameForEmail, ENT_QUOTES, 'UTF-8') . ',</p>' .
				'<p>Thanks for signing up. Please verify your email by clicking the link below:</p>' .
				'<p><a href="' . $link . '">Verify Email</a></p>' .
				'<p>This link will expire in 24 hours.</p>';
			$mail->AltBody = "Hi $displayNameForEmail,\n\nPlease verify your email: $link\n\nThis link expires in 24 hours.";
			$mail->send();
		} catch (Exception $e) {
			echo "Registration successful, but we couldn't send the verification email: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
			exit();
		}

		echo "Registration successful! Please check your email to verify your account.";
		exit();
    } else {
		echo "Error inserting record: " . $stmt->error;
    }

	$stmt->close();
} else {
    header("Location: signup.html");
    exit();
}

$conn->close();
?>