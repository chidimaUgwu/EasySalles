<?php
// index.php - Main entry point
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to dashboard
// if (isset($_SESSION['user_id'])) {
//     header('Location: ' . ($_SESSION['role'] == 1 ? 'admin/index.php' : 'staff-dashboard.php'));
//     exit();
// }

    
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasySalles | Welcome</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #1E293B; overflow-x: hidden; min-height: 100vh;">

    <!-- Modern Navigation -->
    <nav style="position: fixed; top: 0; width: 100%; z-index: 1000; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05); border-bottom: 1px solid #E2E8F0;">
        <div style="max-width: 1400px; margin: 0 auto; padding: 1.2rem 2rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #7C3AED, #EC4899); border-radius: 14px; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 20px rgba(124, 58, 237, 0.3);">
                    <span style="color: white; font-weight: 800; font-size: 1.4rem;">ES</span>
                </div>
                <span style="font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.8rem; background: linear-gradient(135deg, #7C3AED, #EC4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">EasySalles</span>
            </div>
            <div style="display: flex; gap: 2rem; align-items: center;">
                <a href="#features" style="text-decoration: none; color: #1E293B; font-weight: 500; font-size: 1rem; transition: all 0.3s ease; padding: 0.5rem 1rem; border-radius: 10px;" 
                   onmouseover="this.style.color='#7C3AED'; this.style.backgroundColor='rgba(124, 58, 237, 0.1)';"
                   onmouseout="this.style.color='#1E293B'; this.style.backgroundColor='transparent';">Features</a>
                <a href="#pricing" style="text-decoration: none; color: #1E293B; font-weight: 500; font-size: 1rem; transition: all 0.3s ease; padding: 0.5rem 1rem; border-radius: 10px;"
                   onmouseover="this.style.color='#EC4899'; this.style.backgroundColor='rgba(236, 72, 153, 0.1)';"
                   onmouseout="this.style.color='#1E293B'; this.style.backgroundColor='transparent';">Pricing</a>
                <a href="#contact" style="text-decoration: none; color: #1E293B; font-weight: 500; font-size: 1rem; transition: all 0.3s ease; padding: 0.5rem 1rem; border-radius: 10px;"
                   onmouseover="this.style.color='#06B6D4'; this.style.backgroundColor='rgba(6, 182, 212, 0.1)';"
                   onmouseout="this.style.color='#1E293B'; this.style.backgroundColor='transparent';">Contact</a>
                <a href="login.php" style="text-decoration: none; background: linear-gradient(135deg, #7C3AED, #EC4899); color: white; font-weight: 600; font-size: 1rem; padding: 0.8rem 2rem; border-radius: 14px; box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3); transition: all 0.3s ease;"
                   onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 12px 30px rgba(124, 58, 237, 0.4)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 25px rgba(124, 58, 237, 0.3)';">Sign In</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section style="padding: 10rem 2rem 6rem; background: linear-gradient(135deg, #F8FAFC 0%, rgba(124, 58, 237, 0.05) 100%); position: relative; overflow: hidden;">
        <!-- Background Decorative Elements -->
        <div style="position: absolute; top: 10%; left: 5%; width: 300px; height: 300px; border-radius: 50%; background: radial-gradient(circle, rgba(124, 58, 237, 0.1) 0%, transparent 70%);"></div>
        <div style="position: absolute; bottom: 10%; right: 10%; width: 200px; height: 200px; border-radius: 50%; background: radial-gradient(circle, rgba(236, 72, 153, 0.1) 0%, transparent 70%);"></div>
        <div style="position: absolute; top: 30%; right: 15%; width: 150px; height: 150px; border-radius: 50%; background: radial-gradient(circle, rgba(6, 182, 212, 0.1) 0%, transparent 70%);"></div>
        
        <div style="max-width: 1200px; margin: 0 auto; text-align: center; position: relative; z-index: 2;">
            <div style="display: inline-block; background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.1)); padding: 0.8rem 2rem; border-radius: 50px; margin-bottom: 2rem; backdrop-filter: blur(10px);">
                <span style="color: #7C3AED; font-weight: 600; font-size: 0.95rem; letter-spacing: 1px;"><i class="fas fa-rocket" style="margin-right: 0.5rem;"></i> TRANSFORM YOUR SALES PROCESS</span>
            </div>
            
            <h1 style="font-family: 'Poppins', sans-serif; font-size: 4.5rem; font-weight: 800; line-height: 1.1; margin: 0 0 1.5rem 0; background: linear-gradient(135deg, #1E293B, #7C3AED, #EC4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                Sales Management<br><span style="color: #06B6D4;">Made Effortless</span>
            </h1>
            
            <p style="font-size: 1.4rem; line-height: 1.6; color: #64748b; max-width: 700px; margin: 0 auto 3rem; font-weight: 400;">
                Streamline your sales, track performance, and boost revenue with our intuitive platform designed for businesses of all sizes.
            </p>
            
            <div style="display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap; margin-bottom: 5rem;">
                <a href="login.php" style="text-decoration: none; background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; font-weight: 600; font-size: 1.2rem; padding: 1.3rem 3rem; border-radius: 18px; box-shadow: 0 12px 30px rgba(124, 58, 237, 0.3); transition: all 0.4s ease; display: flex; align-items: center; gap: 0.8rem;"
                   onmouseover="this.style.transform='translateY(-5px) scale(1.03)'; this.style.boxShadow='0 18px 40px rgba(124, 58, 237, 0.4)';"
                   onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 12px 30px rgba(124, 58, 237, 0.3)';">
                    <i class="fas fa-play-circle"></i> Get Started Free
                </a>
                <a href="#demo" style="text-decoration: none; background: rgba(255, 255, 255, 0.9); color: #1E293B; font-weight: 600; font-size: 1.2rem; padding: 1.3rem 3rem; border-radius: 18px; border: 2px solid #E2E8F0; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05); transition: all 0.4s ease; display: flex; align-items: center; gap: 0.8rem;"
                   onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='#7C3AED'; this.style.boxShadow='0 15px 35px rgba(124, 58, 237, 0.15)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='#E2E8F0'; this.style.boxShadow='0 8px 25px rgba(0, 0, 0, 0.05)';">
                    <i class="fas fa-play"></i> Watch Demo
                </a>
            </div>
            
            <!-- Stats Preview -->
            <div style="display: flex; justify-content: center; gap: 4rem; flex-wrap: wrap; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(15px); padding: 2.5rem; border-radius: 24px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08); max-width: 900px; margin: 0 auto;">
                <div style="text-align: center;">
                    <div style="font-size: 2.8rem; font-weight: 800; color: #7C3AED; margin-bottom: 0.5rem;">+45%</div>
                    <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Average Revenue Growth</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2.8rem; font-weight: 800; color: #EC4899; margin-bottom: 0.5rem;">+60%</div>
                    <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Team Productivity</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2.8rem; font-weight: 800; color: #06B6D4; margin-bottom: 0.5rem;">24/7</div>
                    <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Support Available</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" style="padding: 7rem 2rem; background-color: #FFFFFF; position: relative;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 5rem;">
                <h2 style="font-family: 'Poppins', sans-serif; font-size: 3.2rem; font-weight: 700; color: #1E293B; margin-bottom: 1.5rem;">
                    Powerful Features for <span style="color: #7C3AED;">Modern</span> Sales Teams
                </h2>
                <p style="font-size: 1.2rem; color: #64748b; max-width: 700px; margin: 0 auto; line-height: 1.7;">
                    Everything you need to manage your sales pipeline, track performance, and close more deals.
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2.5rem;">
                <!-- Feature 1 -->
                <div style="background: linear-gradient(145deg, #FFFFFF, #F8FAFC); padding: 2.5rem; border-radius: 24px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.05); transition: all 0.4s ease; border: 1px solid rgba(226, 232, 240, 0.5);"
                     onmouseover="this.style.transform='translateY(-12px)'; this.style.boxShadow='0 25px 50px rgba(124, 58, 237, 0.1)';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 40px rgba(0, 0, 0, 0.05)';">
                    <div style="width: 70px; height: 70px; background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.1)); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin-bottom: 2rem;">
                        <i class="fas fa-chart-line" style="font-size: 2rem; color: #7C3AED;"></i>
                    </div>
                    <h3 style="font-size: 1.6rem; font-weight: 700; color: #1E293B; margin-bottom: 1rem;">Real-time Analytics</h3>
                    <p style="color: #64748b; line-height: 1.7; margin-bottom: 1.5rem;">Get instant insights into your sales performance with beautiful, interactive dashboards and reports.</p>
                    <a href="#" style="text-decoration: none; color: #7C3AED; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <!-- Feature 2 -->
                <div style="background: linear-gradient(145deg, #FFFFFF, #F8FAFC); padding: 2.5rem; border-radius: 24px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.05); transition: all 0.4s ease; border: 1px solid rgba(226, 232, 240, 0.5);"
                     onmouseover="this.style.transform='translateY(-12px)'; this.style.boxShadow='0 25px 50px rgba(236, 72, 153, 0.1)';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 40px rgba(0, 0, 0, 0.05)';">
                    <div style="width: 70px; height: 70px; background: linear-gradient(135deg, rgba(236, 72, 153, 0.1), rgba(6, 182, 212, 0.1)); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin-bottom: 2rem;">
                        <i class="fas fa-mobile-alt" style="font-size: 2rem; color: #EC4899;"></i>
                    </div>
                    <h3 style="font-size: 1.6rem; font-weight: 700; color: #1E293B; margin-bottom: 1rem;">Mobile-First Design</h3>
                    <p style="color: #64748b; line-height: 1.7; margin-bottom: 1.5rem;">Manage your sales on the go with our fully responsive mobile interface that works beautifully on any device.</p>
                    <a href="#" style="text-decoration: none; color: #EC4899; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <!-- Feature 3 -->
                <div style="background: linear-gradient(145deg, #FFFFFF, #F8FAFC); padding: 2.5rem; border-radius: 24px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.05); transition: all 0.4s ease; border: 1px solid rgba(226, 232, 240, 0.5);"
                     onmouseover="this.style.transform='translateY(-12px)'; this.style.boxShadow='0 25px 50px rgba(6, 182, 212, 0.1)';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 15px 40px rgba(0, 0, 0, 0.05)';">
                    <div style="width: 70px; height: 70px; background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(124, 58, 237, 0.1)); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin-bottom: 2rem;">
                        <i class="fas fa-shield-alt" style="font-size: 2rem; color: #06B6D4;"></i>
                    </div>
                    <h3 style="font-size: 1.6rem; font-weight: 700; color: #1E293B; margin-bottom: 1rem;">Enterprise Security</h3>
                    <p style="color: #64748b; line-height: 1.7; margin-bottom: 1.5rem;">Your data is protected with bank-level security, regular backups, and role-based access controls.</p>
                    <a href="#" style="text-decoration: none; color: #06B6D4; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section style="padding: 8rem 2rem; background: linear-gradient(135deg, #7C3AED 0%, #EC4899 100%); position: relative; overflow: hidden;">
        <div style="position: absolute; top: -100px; left: -100px; width: 400px; height: 400px; border-radius: 50%; background: rgba(255, 255, 255, 0.05);"></div>
        <div style="position: absolute; bottom: -150px; right: -100px; width: 500px; height: 500px; border-radius: 50%; background: rgba(255, 255, 255, 0.03);"></div>
        
        <div style="max-width: 900px; margin: 0 auto; text-align: center; position: relative; z-index: 2;">
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 3.5rem; font-weight: 800; color: white; margin-bottom: 1.5rem; line-height: 1.2;">
                Ready to Transform Your Sales Process?
            </h2>
            <p style="font-size: 1.3rem; color: rgba(255, 255, 255, 0.9); margin-bottom: 3rem; line-height: 1.7;">
                Join thousands of businesses that have already streamlined their sales with EasySalles. Start your free trial today.
            </p>
            
            <div style="display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap;">
                <a href="login.php" style="text-decoration: none; background: white; color: #7C3AED; font-weight: 700; font-size: 1.3rem; padding: 1.5rem 4rem; border-radius: 20px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2); transition: all 0.4s ease;"
                   onmouseover="this.style.transform='translateY(-5px) scale(1.05)'; this.style.boxShadow='0 20px 50px rgba(0, 0, 0, 0.3)';"
                   onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 15px 40px rgba(0, 0, 0, 0.2)';">
                    <i class="fas fa-play-circle" style="margin-right: 0.8rem;"></i> Start Free Trial
                </a>
                <a href="#contact" style="text-decoration: none; background: rgba(255, 255, 255, 0.15); color: white; font-weight: 600; font-size: 1.3rem; padding: 1.5rem 4rem; border-radius: 20px; border: 2px solid rgba(255, 255, 255, 0.3); backdrop-filter: blur(10px); transition: all 0.4s ease;"
                   onmouseover="this.style.transform='translateY(-5px)'; this.style.backgroundColor='rgba(255, 255, 255, 0.25)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.backgroundColor='rgba(255, 255, 255, 0.15)';">
                    <i class="fas fa-comment-dots" style="margin-right: 0.8rem;"></i> Schedule a Demo
                </a>
            </div>
            
            <div style="margin-top: 4rem; display: flex; justify-content: center; gap: 3rem; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 0.8rem;">
                    <i class="fas fa-check-circle" style="color: white; font-size: 1.5rem;"></i>
                    <span style="color: white; font-weight: 500;">No credit card required</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.8rem;">
                    <i class="fas fa-check-circle" style="color: white; font-size: 1.5rem;"></i>
                    <span style="color: white; font-weight: 500;">Free 14-day trial</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.8rem;">
                    <i class="fas fa-check-circle" style="color: white; font-size: 1.5rem;"></i>
                    <span style="color: white; font-weight: 500;">Cancel anytime</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background-color: #1E293B; color: #CBD5E1; padding: 5rem 2rem 3rem;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 3rem; margin-bottom: 4rem;">
                <div style="flex: 1; min-width: 300px;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #7C3AED, #EC4899); border-radius: 14px; display: flex; align-items: center; justify-content: center;">
                            <span style="color: white; font-weight: 800; font-size: 1.4rem;">ES</span>
                        </div>
                        <span style="font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.8rem; color: white;">EasySalles</span>
                    </div>
                    <p style="line-height: 1.7; margin-bottom: 2rem; max-width: 400px;">
                        Transform your sales process with our powerful, intuitive platform designed for businesses of all sizes.
                    </p>
                    <div style="display: flex; gap: 1.2rem;">
                        <a href="#" style="width: 45px; height: 45px; background: rgba(255, 255, 255, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.3s ease;"
                           onmouseover="this.style.backgroundColor='#7C3AED'; this.style.transform='translateY(-3px)';"
                           onmouseout="this.style.backgroundColor='rgba(255, 255, 255, 0.1)'; this.style.transform='translateY(0)';"><i class="fab fa-twitter"></i></a>
                        <a href="#" style="width: 45px; height: 45px; background: rgba(255, 255, 255, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.3s ease;"
                           onmouseover="this.style.backgroundColor='#EC4899'; this.style.transform='translateY(-3px)';"
                           onmouseout="this.style.backgroundColor='rgba(255, 255, 255, 0.1)'; this.style.transform='translateY(0)';"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" style="width: 45px; height: 45px; background: rgba(255, 255, 255, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.3s ease;"
                           onmouseover="this.style.backgroundColor='#06B6D4'; this.style.transform='translateY(-3px)';"
                           onmouseout="this.style.backgroundColor='rgba(255, 255, 255, 0.1)'; this.style.transform='translateY(0)';"><i class="fab fa-facebook-f"></i></a>
                    </div>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <h4 style="color: white; font-size: 1.3rem; margin-bottom: 1.5rem; font-weight: 600;">Product</h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="margin-bottom: 0.8rem;"><a href="#features" style="color: #CBD5E1; text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#7C3AED';" onmouseout="this.style.color='#CBD5E1';">Features</a></li>
                        <li style="margin-bottom: 0.8rem;"><a href="#pricing" style="color: #CBD5E1; text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#EC4899';" onmouseout="this.style.color='#CBD5E1';">Pricing</a></li>
                        <li style="margin-bottom: 0.8rem;"><a href="#demo" style="color: #CBD5E1; text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#06B6D4';" onmouseout="this.style.color='#CBD5E1';">Demo</a></li>
                        <li style="margin-bottom: 0.8rem;"><a href="#" style="color: #CBD5E1; text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#7C3AED';" onmouseout="this.style.color='#CBD5E1';">Updates</a></li>
                    </ul>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <h4 style="color: white; font-size: 1.3rem; margin-bottom: 1.5rem; font-weight: 600;">Company</h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="margin-bottom: 0.8rem;"><a href="#" style="color: #CBD5E1; text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#7C3AED';" onmouseout="this.style.color='#CBD5E1';">About</a></li>
                        <li style="margin-bottom: 0.8rem;"><a href="#" style="color: #CBD5E1; text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#EC4899';" onmouseout="this.style.color='#CBD5E1';">Careers</a></li>
                        <li style="margin-bottom: 0.8rem;"><a href="#" style="color: #CBD5E1; text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#06B6D4';" onmouseout="this.style.color='#CBD5E1';">Blog</a></li>
                        <li style="margin-bottom: 0.8rem;"><a href="#contact" style="color: #CBD5E1; text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#7C3AED';" onmouseout="this.style.color='#CBD5E1';">Contact</a></li>
                    </ul>
                </div>
                
                <div style="flex: 1; min-width: 300px;">
                    <h4 style="color: white; font-size: 1.3rem; margin-bottom: 1.5rem; font-weight: 600;">Stay Updated</h4>
                    <p style="line-height: 1.7; margin-bottom: 1.5rem;">
                        Subscribe to our newsletter for the latest updates and sales tips.
                    </p>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="email" placeholder="Enter your email" style="flex: 1; padding: 1rem 1.5rem; border-radius: 12px; border: none; background: rgba(255, 255, 255, 0.1); color: white; font-size: 1rem;">
                        <button style="background: linear-gradient(135deg, #7C3AED, #EC4899); color: white; border: none; border-radius: 12px; padding: 0 2rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(124, 58, 237, 0.4)';"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">Subscribe</button>
                    </div>
                </div>
            </div>
            
            <div style="border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 2.5rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; align-items: center;">
                <div style="color: #94A3B8; font-size: 0.95rem;">
                    © 2023 EasySalles. All rights reserved.
                </div>
                <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <a href="#" style="color: #94A3B8; text-decoration: none; font-size: 0.95rem; transition: color 0.3s;" onmouseover="this.style.color='#7C3AED';">Privacy Policy</a>
                    <a href="#" style="color: #94A3B8; text-decoration: none; font-size: 0.95rem; transition: color 0.3s;" onmouseover="this.style.color='#EC4899';">Terms of Service</a>
                    <a href="#" style="color: #94A3B8; text-decoration: none; font-size: 0.95rem; transition: color 0.3s;" onmouseover="this.style.color='#06B6D4';">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating Action Button for Login -->
    <a href="login.php" style="position: fixed; bottom: 2rem; right: 2rem; width: 70px; height: 70px; background: linear-gradient(135deg, #7C3AED, #EC4899); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.8rem; box-shadow: 0 10px 30px rgba(124, 58, 237, 0.4); text-decoration: none; z-index: 999; transition: all 0.3s ease;"
       onmouseover="this.style.transform='scale(1.1) rotate(5deg)'; this.style.boxShadow='0 15px 40px rgba(124, 58, 237, 0.6)';"
       onmouseout="this.style.transform='scale(1) rotate(0)'; this.style.boxShadow='0 10px 30px rgba(124, 58, 237, 0.4)';">
        <i class="fas fa-sign-in-alt"></i>
    </a>

    <script>
        // Simple animation for page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add scroll animation for features
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Observe feature cards
            document.querySelectorAll('[onmouseover*="transform"]').forEach(el => {
                el.style.transition = 'all 0.4s ease';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
