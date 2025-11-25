<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlowStack Ledger - Your Money, Organized</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-blue: #0066FF;
            --primary-dark: #0052cc;
            --accent-green: #00CC88;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-light: #F8FAFC;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Navbar */
        .navbar {
            padding: 1.2rem 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .nav-link {
            font-weight: 500;
            color: var(--text-dark);
            margin: 0 10px;
            transition: color 0.2s;
        }
        .nav-link:hover { color: var(--primary-blue); }
        
        .btn-login {
            color: var(--primary-blue);
            font-weight: 600;
            text-decoration: none;
            margin-right: 1.5rem;
        }
        .btn-signup {
            background-color: var(--primary-blue);
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 102, 255, 0.2);
        }
        .btn-signup:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 102, 255, 0.3);
            color: white;
        }

        /* Hero Section */
        .hero {
            padding: 8rem 0 5rem;
            background: linear-gradient(180deg, #FFFFFF 0%, #F0F7FF 100%);
            position: relative;
            overflow: hidden;
        }
        .hero h1 {
            font-weight: 800;
            font-size: 4rem;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #0f172a 0%, #0066FF 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 1.25rem;
            color: var(--text-muted);
            margin-bottom: 2.5rem;
            max-width: 600px;
        }
        .hero-img {
            position: relative;
            z-index: 10;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
            background: white;
            overflow: hidden;
        }

        /* Features Section */
        .features { padding: 6rem 0; }
        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border-color: var(--primary-blue);
        }
        .icon-box {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
        }
        .bg-blue-light { background: #eff6ff; color: var(--primary-blue); }
        .bg-green-light { background: #ecfdf5; color: var(--accent-green); }
        .bg-orange-light { background: #fff7ed; color: #f97316; }
        .bg-purple-light { background: #f5f3ff; color: #8b5cf6; }

        /* Footer */
        .footer {
            background: #0f172a;
            color: white;
            padding: 4rem 0 2rem;
        }
        .footer a {
            color: #94a3b8;
            text-decoration: none;
            transition: 0.2s;
        }
        .footer a:hover { color: white; }
        .social-icon {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .social-icon:hover { background: var(--primary-blue); color: white; }

        @media (max-width: 991px) {
            .hero h1 { font-size: 2.5rem; }
            .hero { padding: 6rem 0 4rem; text-align: center; }
            .hero p { margin-left: auto; margin-right: auto; }
            .hero-btns { justify-content: center; }
            .hero-img { margin-top: 3rem; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <!-- Aligned Logo SVG -->
                <svg width="160" height="40" viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg">
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
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard/support.php">Support</a></li>
                </ul>
                <div class="d-flex align-items-center mt-3 mt-lg-0">
                    <a href="auth/login.php" class="btn-login">Log In</a>
                    <a href="auth/register.php" class="btn-signup">Start Tracking</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="animate-up">Your money, <br>organized.</h1>
                    <p class="animate-up delay-1">Stop guessing where your money goes. Track income, expenses, and generate professional statements effortlessly with FlowStack Ledger.</p>
                    <div class="d-flex gap-3 hero-btns animate-up delay-2">
                        <a href="auth/register.php" class="btn btn-primary btn-lg rounded-pill px-4 py-3 fw-bold shadow-sm" style="background: var(--primary-blue); border:none;">Get Started Free</a>
                        <a href="#features" class="btn btn-outline-dark btn-lg rounded-pill px-4 py-3 fw-bold border-2">Learn More</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <!-- Abstract Representation of Dashboard -->
                    <div class="hero-img animate-up delay-3">
                        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=1000&auto=format&fit=crop" alt="Dashboard Preview" class="img-fluid" style="opacity: 0.9;">
                        <!-- Overlaying UI Element Mockup -->
                        <div style="position: absolute; bottom: 20px; left: 20px; right: 20px; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); padding: 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-muted">TOTAL BALANCE</span>
                                <span class="badge bg-success-subtle text-success">+12%</span>
                            </div>
                            <h3 class="fw-bold m-0">KES 145,000.00</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section id="features" class="features">
        <div class="container">
            <div class="text-center mb-5">
                <h6 class="text-uppercase text-primary fw-bold letter-spacing-1">Why FlowStack?</h6>
                <h2 class="fw-bold display-5">Everything you need to grow.</h2>
            </div>
            
            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-blue-light"><i class="bi bi-wallet2"></i></div>
                        <h4>Smart Budgeting</h4>
                        <p class="text-muted">Set monthly limits for categories and get alerted before you overspend. Stay on track effortlessly.</p>
                    </div>
                </div>
                <!-- Feature 2 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-green-light"><i class="bi bi-graph-up-arrow"></i></div>
                        <h4>Visual Analytics</h4>
                        <p class="text-muted">See where your money goes with interactive charts and insights. Spot trends instantly.</p>
                    </div>
                </div>
                <!-- Feature 3 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-orange-light"><i class="bi bi-file-earmark-pdf"></i></div>
                        <h4>Professional Statements</h4>
                        <p class="text-muted">Generate bank-grade PDF statements with official stamps for your records or clients.</p>
                    </div>
                </div>
                <!-- Feature 4 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-purple-light"><i class="bi bi-shield-lock"></i></div>
                        <h4>Bank-Level Security</h4>
                        <p class="text-muted">Your data is encrypted and secure. We prioritize privacy and data protection above all.</p>
                    </div>
                </div>
                <!-- Feature 5 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-blue-light" style="color: #0dcaf0; background: #cffafe;"><i class="bi bi-calendar-check"></i></div>
                        <h4>Bill Reminders</h4>
                        <p class="text-muted">Never miss a payment. Schedule recurring reminders and get notified on time.</p>
                    </div>
                </div>
                <!-- Feature 6 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-blue-light" style="color: #6610f2; background: #e0cffc;"><i class="bi bi-cloud-arrow-down"></i></div>
                        <h4>Data Export</h4>
                        <p class="text-muted">Your data belongs to you. Export transactions to CSV or Excel whenever you need.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5" style="background: var(--bg-light);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="fw-bold mb-3">Ready to take control?</h2>
                    <p class="text-muted mb-4 fs-5">Join thousands of users who trust FlowStack to manage their finances.</p>
                    <a href="auth/register.php" class="btn btn-primary btn-lg rounded-pill px-5 py-3 shadow-lg fw-bold" style="background: var(--primary-blue); border:none;">Create Free Account</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="fw-bold text-white mb-3">FlowStack Ledger</h5>
                    <p class="text-secondary">Secure, simple, and smart financial management for everyone. Built for growth.</p>
                    <div class="d-flex gap-2 mt-4">
                        <a href="#" class="social-icon"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-linkedin"></i></a>
                        <a href="#" class="social-icon"><i class="bi bi-instagram"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <h6 class="fw-bold text-white mb-3">Product</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#features">Features</a></li>
                        <li class="mb-2"><a href="#">Pricing</a></li>
                        <li class="mb-2"><a href="#">Updates</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6">
                    <h6 class="fw-bold text-white mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="dashboard/support.php">Help Center</a></li>
                        <li class="mb-2"><a href="dashboard/terms.php">Terms of Service</a></li>
                        <li class="mb-2"><a href="dashboard/privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h6 class="fw-bold text-white mb-3">Stay Updated</h6>
                    <form>
                        <div class="input-group">
                            <input type="email" class="form-control border-0" placeholder="Enter email address">
                            <button class="btn btn-primary" type="button">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="border-top border-secondary mt-5 pt-4 text-center text-secondary small">
                &copy; <?php echo date('Y'); ?> FlowStack Ledger. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>