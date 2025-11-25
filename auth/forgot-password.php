<?php
session_start();

// Initialize messages
$error = '';
$success = '';

if (isset($_SESSION['forgot_error'])) {
    $error = $_SESSION['forgot_error'];
    unset($_SESSION['forgot_error']);
}

if (isset($_SESSION['forgot_success'])) {
    $success = $_SESSION['forgot_success'];
    unset($_SESSION['forgot_success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlowStack Ledger - Recover Account</title>
    
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

        /* --- Left Side: Security/Lock Image --- */
        .hero-section {
            /* Lock/Security image */
            background: url('https://images.unsplash.com/photo-1563986768609-322da13575f3?q=80&w=2000&auto=format&fit=crop') no-repeat center center;
            background-size: cover;
            position: relative;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Darker blue gradient for "Security" vibe */
            background: linear-gradient(135deg, rgba(0, 30, 80, 0.9), rgba(0, 102, 255, 0.6));
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
        }

        /* Logo */
        .logo-brand {
            display: block;
            margin-bottom: 2rem;
            text-align: center;
        }

        /* Floating Labels & Inputs */
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
                        <h1>Account Recovery</h1>
                        <p class="mb-4">Securely restore access to your ledger.</p>
                        <div style="width: 60px; height: 4px; background: #FFD700; margin-bottom: 2rem;"></div> <!-- Gold accent for security -->
                        <p class="fs-5">Don't worry, it happens. We'll send you a secure link to reset your password and get you back on track.</p>
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

                    <h2 class="fw-bold mb-1">Forgot Password?</h2>
                    <p class="text-muted mb-4">Enter your email to receive a reset link.</p>

                    <!-- Alert Messages -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <div><?php echo htmlspecialchars($success); ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="forgot-process.php" method="POST">
                        
                        <!-- Email Input -->
                        <div class="form-floating mb-4">
                            <input 
                                type="email" 
                                class="form-control" 
                                id="email" 
                                name="email" 
                                placeholder="name@example.com"
                                required
                            >
                            <label for="email">Email address</label>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary w-100 btn-lg shadow-sm mb-4">
                            Send Reset Link
                        </button>

                        <!-- Back to Login -->
                        <div class="text-center">
                            <a href="../auth/login.php" class="text-decoration-none fw-medium text-muted">
                                <i class="bi bi-arrow-left me-1"></i> Back to Login
                            </a>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>