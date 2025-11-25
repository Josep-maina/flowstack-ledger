<?php
session_start();

// If user is already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/index.php");
    exit();
}

// Retrieve error messages or old form data
$error = '';
$form_data = [];

if (isset($_SESSION['register_error'])) {
    $error = $_SESSION['register_error'];
    unset($_SESSION['register_error']);
}

if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlowStack Ledger - Create Account</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-blue: #0066FF;
            --primary-dark: #0052cc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        .login-container {
            height: 100vh;
        }

        /* --- Left Side: Hero Image (Different from Login) --- */
        .hero-section {
            background: url('https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2000&auto=format&fit=crop') no-repeat center center;
            background-size: cover;
            position: relative;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 102, 255, 0.9), rgba(0, 204, 136, 0.7));
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            color: white;
        }

        .hero-content h1 {
            font-weight: 700;
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .hero-content p {
            font-size: 1.25rem;
            opacity: 0.9;
            font-weight: 300;
            max-width: 80%;
        }

        /* --- Right Side: Form --- */
        .form-section {
            background-color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            overflow-y: auto;
        }

        .form-wrapper {
            width: 100%;
            max-width: 420px;
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        /* Logo */
        .logo-brand {
            display: block;
            margin-bottom: 2rem;
            text-align: center;
        }

        /* Form Controls */
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: var(--primary-blue);
            opacity: 1;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(0, 102, 255, 0.1);
        }

        .btn-primary {
            background-color: var(--primary-blue);
            border: none;
            padding: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        /* Mobile */
        @media (max-width: 768px) {
            body { overflow: auto; }
            .hero-section { display: none; } 
            .login-container { height: auto; min-height: 100vh; }
        }
    </style>
</head>
<body>

    <div class="container-fluid p-0">
        <div class="row g-0 login-container">
            
            <!-- LEFT COLUMN -->
            <div class="col-md-6 col-lg-7 hero-section">
                <div class="hero-overlay">
                    <div class="hero-content">
                        <h1>Start Your Journey</h1>
                        <p class="mb-4">Join thousands of users managing their wealth.</p>
                        <div style="width: 60px; height: 4px; background: white; margin-bottom: 2rem;"></div>
                        <ul class="list-unstyled fs-5" style="opacity: 0.9;">
                            <li class="mb-3"><i class="bi bi-check-circle-fill me-2"></i> Real-time expense tracking</li>
                            <li class="mb-3"><i class="bi bi-check-circle-fill me-2"></i> Secure data encryption</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Insightful financial reports</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="col-md-6 col-lg-5 form-section">
                <div class="form-wrapper">
                    
                    <div class="logo-brand">
                        <svg width="180" height="50" viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g transform="translate(0, 5)">
                                <rect x="0" y="24" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.4"/>
                                <rect x="0" y="14" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.7"/>
                                <rect x="0" y="4" width="28" height="7" rx="2" fill="#0066FF"/>
                                <path d="M34 28C34 28 38 28 40 20C42 12 46 6 46 6" stroke="#00CC88" stroke-width="3" stroke-linecap="round"/>
                            </g>
                            <g transform="translate(56, 0)">
                                <text x="0" y="22" font-family="'Inter', sans-serif" font-weight="700" font-size="22" fill="#1e293b" letter-spacing="-0.5">FlowStack</text>
                                <text x="0" y="42" font-family="'Inter', sans-serif" font-weight="400" font-size="14" fill="#0066FF" letter-spacing="0.5">Ledger</text>
                            </g>
                        </svg>
                    </div>

                    <h2 class="fw-bold mb-1">Create Account</h2>
                    <p class="text-muted mb-4">Start managing your finances today.</p>

                    <!-- Alert Message -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center small" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="register-process.php" method="POST">
                        
                        <!-- Username -->
                        <div class="form-floating mb-3">
                            <input 
                                type="text" 
                                class="form-control" 
                                id="username" 
                                name="username" 
                                placeholder="Username"
                                value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
                                required
                            >
                            <label for="username">Username</label>
                        </div>

                        <!-- Email -->
                        <div class="form-floating mb-3">
                            <input 
                                type="email" 
                                class="form-control" 
                                id="email" 
                                name="email" 
                                placeholder="name@example.com"
                                value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                required
                            >
                            <label for="email">Email address</label>
                        </div>

                        <!-- Password -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="password" 
                                        name="password" 
                                        placeholder="Password"
                                        required
                                    >
                                    <label for="password">Password</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="confirm_password" 
                                        name="confirm_password" 
                                        placeholder="Confirm"
                                        required
                                    >
                                    <label for="confirm_password">Confirm</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-text text-muted mb-3 small">
                            Use at least 6 characters.
                        </div>

                        <!-- Terms -->
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="terms" id="terms" required>
                            <label class="form-check-label text-muted small" for="terms">
                                I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and <a href="#" class="text-decoration-none">Privacy Policy</a>.
                            </label>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn btn-primary w-100 btn-lg shadow-sm">
                            Create Account
                        </button>

                        <div class="text-center mt-4 text-muted">
                            Already have an account? 
                            <a href="../auth/login.php" class="text-decoration-none fw-semibold" style="color: var(--primary-blue);">Log in</a>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>