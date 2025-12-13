<?php
// includes/footer.php
?>
        </div> <!-- Close main-wrapper -->
        
        <!-- Footer -->
        <footer class="main-footer">
            <div class="footer-wave">
                <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
                    <path fill="url(#footerGradient)" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path>
                    <defs>
                        <linearGradient id="footerGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#7C3AED;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#EC4899;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            
            <div class="footer-content">
                <div class="footer-grid">
                    <!-- Company Info -->
                    <div class="footer-section">
                        <div class="footer-logo">
                            <div class="footer-icon">
                                <span>ES</span>
                            </div>
                            <div class="footer-brand">EasySalles</div>
                        </div>
                        <p class="footer-description">
                            Streamline your sales, track performance, and boost revenue with our intuitive platform designed for businesses of all sizes.
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link" title="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link" title="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-link" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link" title="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="#" class="social-link" title="GitHub">
                                <i class="fab fa-github"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="footer-section">
                        <h4 class="footer-heading">Quick Links</h4>
                        <ul class="footer-links">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="#features">Features</a></li>
                            <li><a href="#pricing">Pricing</a></li>
                            <li><a href="#contact">Contact</a></li>
                            <li><a href="login.php">Login</a></li>
                        </ul>
                    </div>
                    
                    <!-- Product Links -->
                    <div class="footer-section">
                        <h4 class="footer-heading">Product</h4>
                        <ul class="footer-links">
                            <li><a href="#">Dashboard</a></li>
                            <li><a href="#">Sales Tracking</a></li>
                            <li><a href="#">Inventory Management</a></li>
                            <li><a href="#">Reporting</a></li>
                            <li><a href="#">Mobile App</a></li>
                        </ul>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="footer-section">
                        <h4 class="footer-heading">Contact Us</h4>
                        <div class="contact-info">
                            <div class="contact-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>123 Business Street, Sales City</span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <span>+1 (555) 123-4567</span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span>support@easysalles.com</span>
                            </div>
                        </div>
                        <div class="newsletter">
                            <h5>Stay Updated</h5>
                            <div class="newsletter-form">
                                <input type="email" placeholder="Your email" class="newsletter-input">
                                <button class="newsletter-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Copyright -->
                <div class="footer-bottom">
                    <div class="copyright">
                        &copy; <?php echo date('Y'); ?> EasySalles. All rights reserved.
                    </div>
                    <div class="footer-links-bottom">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">Cookie Policy</a>
                        <a href="#">Sitemap</a>
                    </div>
                </div>
            </div>
            
            <!-- Back to Top Button -->
            <button class="back-to-top" id="backToTop">
                <i class="fas fa-arrow-up"></i>
            </button>
        </footer>
        
        <style>
            /* Footer Styles */
            .main-footer {
                background: linear-gradient(135deg, var(--text) 0%, #1a202c 100%);
                color: #CBD5E1;
                position: relative;
                margin-top: auto;
            }
            
            .footer-wave {
                position: absolute;
                top: -1px;
                left: 0;
                width: 100%;
                height: 120px;
                overflow: hidden;
                line-height: 0;
                transform: rotate(180deg);
            }
            
            .footer-wave svg {
                position: relative;
                display: block;
                width: calc(100% + 1.3px);
                height: 120px;
            }
            
            .footer-content {
                max-width: 1400px;
                margin: 0 auto;
                padding: 4rem 2rem 2rem;
            }
            
            .footer-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 3rem;
                margin-bottom: 3rem;
            }
            
            .footer-section {
                animation: fadeInUp 0.6s ease-out;
            }
            
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .footer-logo {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .footer-icon {
                width: 50px;
                height: 50px;
                background: linear-gradient(135deg, var(--primary), var(--accent));
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 6px 20px rgba(124, 58, 237, 0.3);
            }
            
            .footer-icon span {
                color: white;
                font-weight: 800;
                font-size: 1.3rem;
                font-family: 'Poppins', sans-serif;
            }
            
            .footer-brand {
                font-family: 'Poppins', sans-serif;
                font-weight: 700;
                font-size: 1.8rem;
                background: linear-gradient(135deg, var(--primary), var(--accent));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .footer-description {
                line-height: 1.7;
                margin-bottom: 1.5rem;
                color: #94A3B8;
            }
            
            .social-links {
                display: flex;
                gap: 1rem;
            }
            
            .social-link {
                width: 42px;
                height: 42px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #CBD5E1;
                text-decoration: none;
                transition: all 0.3s ease;
            }
            
            .social-link:hover {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                transform: translateY(-3px) rotate(5deg);
                box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
            }
            
            .footer-heading {
                font-family: 'Poppins', sans-serif;
                font-size: 1.3rem;
                font-weight: 600;
                color: white;
                margin-bottom: 1.5rem;
                position: relative;
                padding-bottom: 0.5rem;
            }
            
            .footer-heading::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 40px;
                height: 3px;
                background: linear-gradient(135deg, var(--primary), var(--accent));
                border-radius: 2px;
            }
            
            .footer-links {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            
            .footer-links li {
                margin-bottom: 0.8rem;
            }
            
            .footer-links a {
                color: #94A3B8;
                text-decoration: none;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .footer-links a:hover {
                color: var(--accent);
                transform: translateX(5px);
            }
            
            .footer-links a::before {
                content: '→';
                opacity: 0;
                transition: all 0.3s ease;
            }
            
            .footer-links a:hover::before {
                opacity: 1;
            }
            
            .contact-info {
                margin-bottom: 2rem;
            }
            
            .contact-item {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1rem;
                color: #94A3B8;
            }
            
            .contact-item i {
                color: var(--accent);
                font-size: 1.1rem;
                width: 20px;
            }
            
            .newsletter h5 {
                color: white;
                font-size: 1rem;
                margin-bottom: 1rem;
                font-weight: 500;
            }
            
            .newsletter-form {
                display: flex;
                gap: 0.5rem;
            }
            
            .newsletter-input {
                flex: 1;
                padding: 0.8rem 1rem;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 10px;
                color: white;
                font-family: 'Inter', sans-serif;
                transition: all 0.3s ease;
            }
            
            .newsletter-input:focus {
                outline: none;
                background: rgba(255, 255, 255, 0.15);
                border-color: var(--accent);
            }
            
            .newsletter-input::placeholder {
                color: #94A3B8;
            }
            
            .newsletter-btn {
                background: linear-gradient(135deg, var(--accent), #0891b2);
                color: white;
                border: none;
                width: 46px;
                height: 46px;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .newsletter-btn:hover {
                transform: translateY(-2px) rotate(10deg);
                box-shadow: 0 6px 15px rgba(6, 182, 212, 0.3);
            }
            
            /* Footer Bottom */
            .footer-bottom {
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                padding-top: 2rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .copyright {
                color: #94A3B8;
                font-size: 0.9rem;
            }
            
            .footer-links-bottom {
                display: flex;
                gap: 2rem;
                flex-wrap: wrap;
            }
            
            .footer-links-bottom a {
                color: #94A3B8;
                text-decoration: none;
                font-size: 0.9rem;
                transition: color 0.3s;
            }
            
            .footer-links-bottom a:hover {
                color: var(--accent);
            }
            
            /* Back to Top Button */
            .back-to-top {
                position: fixed;
                bottom: 2rem;
                right: 2rem;
                width: 56px;
                height: 56px;
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                border: none;
                border-radius: 16px;
                font-size: 1.3rem;
                cursor: pointer;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 999;
                box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3);
            }
            
            .back-to-top.visible {
                opacity: 1;
                visibility: visible;
            }
            
            .back-to-top:hover {
                transform: translateY(-5px) scale(1.1);
                box-shadow: 0 12px 30px rgba(124, 58, 237, 0.4);
            }
            
            /* Responsive Footer */
            @media (max-width: 1024px) {
                .footer-content {
                    padding: 3rem 1.5rem 1.5rem;
                }
                
                .footer-grid {
                    gap: 2rem;
                }
            }
            
            @media (max-width: 768px) {
                .footer-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                
                .footer-bottom {
                    flex-direction: column;
                    text-align: center;
                }
                
                .footer-links-bottom {
                    justify-content: center;
                }
            }
            
            @media (max-width: 480px) {
                .footer-grid {
                    grid-template-columns: 1fr;
                }
                
                .footer-content {
                    padding: 2rem 1rem 1rem;
                }
                
                .footer-wave svg {
                    height: 80px;
                }
                
                .back-to-top {
                    width: 48px;
                    height: 48px;
                    bottom: 1rem;
                    right: 1rem;
                }
            }
        </style>
        
        <script>
            // Back to Top Button
            const backToTop = document.getElementById('backToTop');
            
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backToTop.classList.add('visible');
                } else {
                    backToTop.classList.remove('visible');
                }
            });
            
            backToTop.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Newsletter Form Submission
            document.querySelector('.newsletter-btn').addEventListener('click', function() {
                const emailInput = document.querySelector('.newsletter-input');
                const email = emailInput.value.trim();
                
                if (email && email.includes('@')) {
                    // Show success message
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i>';
                    this.style.background = 'linear-gradient(135deg, #10B981, #059669)';
                    
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.style.background = 'linear-gradient(135deg, var(--accent), #0891b2)';
                        emailInput.value = '';
                        alert('Thank you for subscribing to our newsletter!');
                    }, 1500);
                } else {
                    emailInput.focus();
                    emailInput.style.borderColor = '#EF4444';
                    setTimeout(() => {
                        emailInput.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                    }, 2000);
                }
            });
            
            // Animate footer sections on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = '0s';
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, observerOptions);
            
            // Observe footer sections
            document.querySelectorAll('.footer-section').forEach(section => {
                section.style.animationPlayState = 'paused';
                observer.observe(section);
            });
        </script>
    </body>
</html>
