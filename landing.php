<?php
//require_once 'includes/auth.php';
// Redirect if already logged in
//if (isLoggedIn()) {
  //  redirectByRole();
//}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSys | Modern Tutoring Platform</title>
    <!-- Modern Typography: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Dedicated Minimal CSS -->
    <link rel="stylesheet" href="css/landing.css">
</head>
<body>
    <!-- Refined Minimal Navigation -->
    <nav>
        <a href="#" class="logo">
            <i class="fa-solid fa-graduation-cap"></i>
            <span>EduSys</span>
        </a>
        <div class="nav-buttons">
            <a href="login.php" class="btn btn-minimal">Login</a>
            <a href="register.php" class="btn btn-primary">Join for Free</a>
        </div>
    </nav>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <span class="badge">✨ Trusted by 10,000+ Students</span>
                <h1>Best Tutoring Platform <br>for Home & Online Tuitions</h1>
                <p>Unlock your potential with personal guidance. Whether you prefer home visits or digital sessions, find the perfect tutor tailored to your learning style.</p>
                <div class="hero-cta">
                    <a href="register.php" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1rem;">Find Your Tutor Now</a>
                </div>
            </div>

            <div class="hero-image">
                <div class="hero-scene">
                    <img src="assets/images/hero-3d.png" alt="3D Learning Experience">
                    
                    <div class="float-element float-1">
                        <i class="fa-solid fa-video"></i>
                        <div>
                            <span style="display:block; opacity:0.7; font-size:0.7rem;">Live Now</span>
                            <span>Calculus Masterclass</span>
                        </div>
                    </div>

                    <div class="float-element float-2">
                        <i class="fa-solid fa-star" style="color: #fbbf24;"></i>
                        <div>
                            <span style="display:block; opacity:0.7; font-size:0.7rem;">Top Rated</span>
                            <span>Mr. Anderson</span>
                        </div>
                    </div>

                    <div class="float-element float-3" style="background: var(--accent);">
                        <i class="fa-solid fa-bolt" style="color: var(--accent);"></i>
                        <div>
                            <span style="display:block; opacity:0.7; font-size:0.7rem;">Quick Search</span>
                            <span>Math Tutors</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section style="padding-top: 0;">
            <div class="stats-grid">
                <div class="stat-item">
                    <h2>500+</h2>
                    <p>Expert Tutors</p>
                </div>
                <div class="stat-item">
                    <h2>98%</h2>
                    <p>Success Rate</p>
                </div>
                <div class="stat-item">
                    <h2>24/7</h2>
                    <p>Support System</p>
                </div>
                <div class="stat-item">
                    <h2>10k+</h2>
                    <p>Active Students</p>
                </div>
            </div>
        </section>

        <!-- Features Bento Grid -->
        <section id="specialities" style="background: #fff; padding-top: 6rem;">
            <div class="section-header">
                <h2>Our Specialities</h2>
                <p>Designed to provide the most flexible and effective learning experience for students and professional growth for teachers.</p>
            </div>

            <div class="bento-grid">
                <div class="bento-item bento-1">
                    <i class="fa-solid fa-house-user"></i>
                    <h3>Offline Tuition</h3>
                    <p>Personalized face-to-face learning at your home. Real-world interaction leads to deeper understanding and faster progress.</p>
                </div>
                <div class="bento-item bento-2" style="background: var(--primary); color: #fff;">
                    <i class="fa-solid fa-location-dot" style="color: #fff; opacity: 0.2;"></i>
                    <h3>Nearby Tutors</h3>
                    <p style="color: rgba(255,255,255,0.8);">Find qualified educators in your immediate neighborhood. Save time and build community ties while you learn.</p>
                    <a href="register.php" class="btn btn-primary" style="background:#fff; color: var(--primary); margin-top: 1.5rem; width: fit-content;">Check Map</a>
                </div>
                <div class="bento-item bento-3">
                    <i class="fa-solid fa-globe"></i>
                    <h3>Online Session</h3>
                    <p>Access top tier education from anywhere in the world. High-quality video calls and digital whiteboards.</p>
                </div>
                <div class="bento-item bento-4">
                    <i class="fa-solid fa-users"></i>
                    <h3>Everyone Can Join</h3>
                    <p>Programs for primary, secondary, and professional learners. We believe education has no age limit.</p>
                </div>
            </div>
        </section>

        <!-- How it Works Section -->
        <section>
            <div class="section-header">
                <h2>How it Works</h2>
                <p>Starting your learning journey is easier than you think. Follow these three simple steps.</p>
            </div>

            <div class="steps-container">
                <div class="step-card">
                    <div class="step-num">1</div>
                    <h4>Create Account</h4>
                    <p>Sign up as a student or teacher. Fill in your details and preferences in minutes.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">2</div>
                    <h4>Find Matches</h4>
                    <p>Our smart algorithm suggests the best tutors or students nearby based on your subjects.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">3</div>
                    <h4>Start Learning</h4>
                    <p>Connect, schedule your first session, and embark on your educational journey with ease.</p>
                </div>
            </div>
        </section>

        <!-- Become a Teacher CTA -->
        <section style="background: rgba(79, 70, 229, 0.03); border-radius: 40px; margin: 0 8% 4rem; padding: 5rem 10%;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 4rem; flex-wrap: wrap;">
                <div style="flex: 1.5;">
                    <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">Are you an educator?</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">Join our platform to reach more students, manage your schedule efficiently, and grow your teaching career with our AI-powered tools.</p>
                    <a href="register.php?role=teacher" class="btn btn-primary">Apply as a Teacher</a>
                </div>
                <div style="flex: 1; text-align: center;">
                    <i class="fa-solid fa-chalkboard-user" style="font-size: 8rem; color: var(--primary); opacity: 0.1;"></i>
                </div>
            </div>
        </section>
    </main>

    <!-- Detailed Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-brand">
                <a href="#" class="logo" style="margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>EduSys</span>
                </a>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">Modernizing education through technology. We connect the best minds for a smarter tomorrow.</p>
            </div>
            <div class="footer-links">
                <div class="link-col">
                    <h5>Platform</h5>
                    <ul>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                        <li><a href="#">Browse Tutors</a></li>
                        <li><a href="#">Online Classes</a></li>
                    </ul>
                </div>
                <div class="link-col">
                    <h5>Company</h5>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 4rem; padding-top: 2rem; border-top: 1px solid #f1f1f1; font-size: 0.85rem; color: #aaa;">
            &copy; <?= date('Y') ?> EduSys Platform. Built with ❤️ for Educators & Students.
        </div>
    </footer>
</body>
</html>
