<?php
// index.php (Landing Page)
session_start();
require_once 'db.php';

// Handle login form submission
if (isset($_POST['login'])) {
	$email = $_POST['email'] ?? '';
	$password = $_POST['password'] ?? '';

	// Fetch user from database
	$stmt = $conn->prepare("SELECT role, password FROM users WHERE email = ?");
	$stmt->bind_param('s', $email);
	$stmt->execute();
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
		$stmt->bind_result($role, $hashedPassword);
		$stmt->fetch();
		// For demo, assuming password is stored in plain text. Use password_verify for hashed passwords.
		if ($password === $hashedPassword) {
			$_SESSION['email'] = $email;
			$_SESSION['role'] = $role;
			// Redirect based on role
			if ($role === 'student') {
				header('Location: user/login.php');
				exit();
			} elseif ($role === 'instructor') {
				header('Location: dashboard/instructor/Instructor-dashboard.php');
				exit();
			} elseif ($role === 'Dean') {
				header('Location: dashboard/admin/Admin-dashboard.php');
				exit();
			}
		} else {
			echo '<script>alert("Invalid password.");</script>';
		}
	} else {
		echo '<script>alert("No account found for this email.");</script>';
	}
	$stmt->close();
}

// Handle Google login button click
if (isset($_POST['googleLogin'])) {
	// TODO: Add logic to handle Google login
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>EduVault|Landing-page</title>
		<link rel="stylesheet" href="css/Landing-page.css">
		<link href="https://fonts.googleapis.com/css2?family=Asap:wght@100;400;500;600;700&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
		<script src="https://accounts.google.com/gsi/client" async defer></script>
		<!-- Font Awesome CDN -->
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>
<body>
<header>
		<div class="logo-container">
				<img src="img/EduVault Official Logo (2).png" alt="Second Logo" class="second-logo">
				<img src="img/partido-state-university-logo.png" alt="Main Logo" class="main-logo">
				<h1>PARTIDO<span class="sub-text">STATE UNIVERSITY</span>
						<hr class="logo-line">
				</h1>
		</div>
</div>

<!-- Header Nav -->
<nav>
	<ul>
		<li><a href="#about">About Us</a></li>
		<li><a href="#department">Department</a></li>
		<li><a href="#features">Features</a></li>
		<li><a href="#contact">Contact</a></li>
        <li><a href="user/Registration-page.php">Sign Up</a></li>
	</ul>
</nav>

	<div class="menu">
		<span></span>
		<span></span>
		<span></span>
	</div>
</header>

		<!-- HERO 1 -->
<div class="hero hero-1">
	<div class="hero-inner">
		<div class="hero-inner__content">
			<h1 class="hero1-title">
				<span class="word">
					<span>W</span><span>e</span><span>l</span><span>c</span><span>o</span><span>m</span><span>e</span>
				</span>
				<span class="word">
					<span> </span><span>t</span><span>o</span>
				</span>
				<br>
				<span class="word">
					<span>P</span><span>a</span><span>r</span><span>t</span><span>i</span><span>d</span><span>o</span>
				</span>
				<span class="word">
					<span> </span><span>S</span><span>t</span><span>a</span><span>t</span><span>e</span>
				</span>
				<br>
				<span class="word">
					<span>U</span><span>n</span><span>i</span><span>v</span><span>e</span><span>r</span><span>s</span><span>i</span><span>t</span><span>y</span>
				</span>
			</h1>
			 <p>
				<span class="wave-text">
					<span>E</span><span>d</span><span>u</span><span>V</span><span>a</span><span>u</span><span>l</span><span>t</span>
				</span>
				: Centralized Repository <br>Knowledge Management Portal
			</p>
				<a href="https://accounts.google.com/o/oauth2/v2/auth?client_id=18457313587-e2iq0jtc0qr7kousep09aj60kugk2lfe.apps.googleusercontent.com&redirect_uri=http://localhost:3000/auth/google/callback&response_type=code&scope=email%20profile&access_type=online" class="google-btn">
				<img src="img/google.png" alt="Google Logo" style="vertical-align:middle;margin-right:8px;">Login with Google
				</a>
		</div>
	</div>
</div>

<!-- HERO 2 -->
<section class="hero-2" id="about">
	<div class="slider-container">
		<div class="slide active">
			<div class="slide-text">
				<h2><i class="fa-solid fa-trophy slide-icon"></i> Our Mission</h2>
				<p>To provide students and teachers with an accessible and organized platform for research, 
					lessons, and academic resources, fostering a culture of collaboration and learning.</p>
			</div>
		</div>
		<div class="slide">
			<div class="slide-text">
				<h2><i class="fa-solid fa-trophy slide-icon"></i> Our Vision</h2>
				<p>To be a trusted and innovative academic resource hub that empowers learners and educators 
					to achieve excellence through shared knowledge.</p>
			</div>
		</div>
		<div class="slide">
			<div class="slide-text">
				<h2><i class="fa-solid fa-trophy slide-icon"></i> Our Goals</h2>
				<p>To centralize academic materials, support student projects, and promote knowledge sharing
					 between students and teachers.</p>
			</div>
		</div>
	</div>

	<div class="dots-container">
		<div class="dot active"></div>
		<div class="dot"></div>
		<div class="dot"></div>
	</div>
</section>

<!-- HERO 3 -->
<div class="hero hero-3" id="department">
		<div class="hero-inner">
				<div class="hero-inner__content dark-text">
						<h1>Department</h1>

						<div class="grid-container">
								<div class="grid-item">
								<img src="img/DEP1.jpg"alt="Product 1">
										<div class="grid-item-text">
												<h3>College of Engineering and Computational Sciences</h3>
												<p>Engineering Building</p>
										</div>
								</div>
								<div class="grid-item">
								<img src="img/DEP2.jpg" alt="Product 2">
										<div class="grid-item-text">
												<h3>College Of Science</h3>
												<p>COS Building</p>
										</div>
								</div>
								<div class="grid-item">
								<img src="img/DEP3.jpg" alt="Product 3">
										<div class="grid-item-text">
												<h3>College of Businesss and Management</h3>
												<p>Entrepremeurship Building</p>
										</div>
								</div>
								<div class="grid-item">
								<img src="img/DEP4.jpg" alt="Product 4">
										<div class="grid-item-text">
												<h3>College of Business And Management</h3>
												<p>CBM Department Office and Staff</p>
										</div>
								</div>
								<div class="grid-item">
								<img src="img/DEP5.jpg" alt="Product 5">
										<div class="grid-item-text">
												<h3>College of Education</h3>
												<p>COED Building</p>
										</div>
								</div>
								<div class="grid-item">
								<img src="img/DEP6.jpg" alt="Product 6">
										<div class="grid-item-text">
												<h3>College of Engineering and Computational Sciences</h3>
												<p>IIT Building</p>
										</div>
								</div>
						</div>
				</div>
		</div>
</div>


<!-- HERO 4 -->
<div class="hero hero-4" id="features">
	<div class="hero-inner">
		<div class="hero-inner__content dark-text">
			<h1>Features</h1>
			<p>Check out what we offer</p>
			<div class="features-container">
        
				<!-- Card 1 -->
				<div class="flip-card">
					<div class="flip-card-inner">
						<div class="flip-card-front flex-center">
							<i class="fa-solid fa-chart-column fa-5x"></i>
						</div>
						<div class="flip-card-back">
							<h3>Statistics Dashboard</h3>
							<p>View charts and data showing counts of uploaded materials, research works, and other key metrics in one place.</p>
						</div>
					</div>
				</div>

				<!-- Card 2 -->
				<div class="flip-card">
					<div class="flip-card-inner">
						<div class="flip-card-front flex-center">
							<i class="fa-solid fa-book-open-reader fa-5x"></i>
						</div>
						<div class="flip-card-back">
							<h3>Research Repository</h3>
							<p>Browse published research from students and teachers as references for your own projects.</p>
						</div>
					</div>
				</div>

				<!-- Card 3 -->
				<div class="flip-card">
					<div class="flip-card-inner">
						<div class="flip-card-front flex-center">
							<i class="fa-solid fa-graduation-cap fa-5x"></i>
						</div>
						<div class="flip-card-back">
							<h3>Lesson Materials Archive</h3>
							<p>Access complete lesson files for all programs, from 1st to 4th year, organized by semester.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- HERO 5 -->
<div class="hero hero-5" id="contact">
	<div class="hero-5-top">
		<!-- Logos overlay -->
		<div class="hero-5-logos">
			<img src="img/EduVault Official Logo (2).png" alt="Logo 1">
			<img src="img/partido-state-university-logo.png" alt="Logo 2">
			<img src="img/DCSLogo.png" alt="Logo 3">
		</div>
	</div>
  
	<div class="hero-5-bottom">
		<div class="footer-content">
			<img src="img/partido-state-university-logo.png" alt="Logo" class="footer-logo">
			<p>&copy; 2025 Partido State University. All rights reserved.</p>
			<div class="creators">
				<p>Created by: John Lender Andaya, Aries Iraula, Edwin Conde II</p>
			</div>
			<p>Contact Us:</p>
			<div class="social-icons">
				<a href="#" target="_blank"><img src="img/facebook.png" alt="Facebook"></a>
				<a href="#"><img src="img/GMAIL1.png" alt="Gmail"></a>
				<a href="#" target="_blank"><img src="img/instagram.png" alt="Instagram"></a>
			</div>
		</div>
	</div>
</div>

<!-- Scroll To Top Button -->
<button id="scrollTopBtn">&#8679;</button>
<script src="js/Landing-page.js"></script>
</body>
</html>
