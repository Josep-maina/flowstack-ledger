<?php
session_start();

// If user is already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/index.php");
    exit();
}

// Initialize error message
$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlowStack Ledger - Login</title>
    
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
            overflow: hidden; /* Prevent scroll on desktop */
        }

        .login-container {
            height: 100vh;
        }

        /* --- Left Side: Hero Image --- */
        .hero-section {
            background: url('https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?q=80&w=2000&auto=format&fit=crop') no-repeat center center;
            background-size: cover;
            position: relative;
        }

        /* Blue overlay gradient */
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 102, 255, 0.9), rgba(0, 30, 80, 0.85));
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
            overflow-y: auto; /* Allow scroll on small screens */
        }

        .form-wrapper {
            width: 100%;
            max-width: 420px;
        }

        /* Logo Construction */
        .logo-brand {
            display: block;
            margin-bottom: 2rem;
            text-align: center; /* Center logo on login form */
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

        .btn-login {
            background-color: var(--primary-blue);
            border: none;
            padding: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: var(--text-muted);
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        .divider span {
            padding: 0 10px;
            font-size: 0.9rem;
        }

        /* Mobile Adjustments */
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
            
            <!-- LEFT COLUMN: Brand & Visual -->
            <div class="col-md-6 col-lg-7 hero-section">
                <div class="hero-overlay">
                    <div class="hero-content">
                        <h1>FlowStack Ledger</h1>
                        <p class="mb-4">Secure. Transparent. Personalized.</p>
                        <div style="width: 60px; height: 4px; background: #00CC88; margin-bottom: 2rem;"></div>
                        <p class="fs-5">Manage your finances with precision. Track every transaction and visualize your growth in real-time.</p>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Login Form -->
            <div class="col-md-6 col-lg-5 form-section">
                <div class="form-wrapper">
                    
                    <!-- Logo Area -->
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

                    <h2 class="fw-bold mb-1">Welcome back</h2>
                    <p class="text-muted mb-4">Please enter your details to sign in.</p>

                    <!-- Alert Message -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="login-process.php" method="POST">
                        
                        <!-- Email Input -->
                        <div class="form-floating mb-3">
                            <input 
                                type="text" 
                                class="form-control" 
                                id="email" 
                                name="email" 
                                placeholder="name@example.com"
                                required
                                autocomplete="username"
                            >
                            <label for="email">Email address or Username</label>
                        </div>

                        <!-- Password Input -->
                        <div class="form-floating mb-3">
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                placeholder="Password"
                                required
                                autocomplete="current-password"
                            >
                            <label for="password">Password</label>
                        </div>

                        <!-- Remember & Forgot -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe">
                                <label class="form-check-label text-muted" for="rememberMe">
                                    Remember me
                                </label>
                            </div>
                            <a href="../auth/forgot-password.php" class="text-decoration-none" style="color: var(--primary-blue); font-weight: 500;">Forgot password?</a>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary btn-login w-100 btn-lg shadow-sm">
                            Sign In
                        </button>

                        <div class="divider">
                            <span>or</span>
                        </div>

                        <!-- Register Link -->
                        <div class="text-center text-muted">
                            Don't have an account? 
                            <a href="../auth/register.php" class="text-decoration-none fw-semibold" style="color: var(--primary-blue);">Create free account</a>
                        </div>
                    </form>

                    <div class="mt-5 text-center text-muted small">
                        &copy; <?php echo date('Y'); ?> FlowStack Ledger. All rights reserved.
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>