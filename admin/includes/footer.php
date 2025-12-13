<?php
// admin/includes/footer.php
?>
        </div> <!-- Close content-area -->
    </div> <!-- Close main-content -->

    <script>
        // Auto-hide sidebar on mobile when clicking a link
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    document.querySelector('.sidebar').classList.remove('active');
                }
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        });

        // Show mobile menu toggle on small screens
        function checkScreenSize() {
            const menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 1024) {
                menuToggle.style.display = 'flex';
            } else {
                menuToggle.style.display = 'none';
                document.querySelector('.sidebar').classList.remove('active');
            }
        }

        window.addEventListener('resize', checkScreenSize);
        checkScreenSize(); // Initial check

        // Initialize tooltips
        document.querySelectorAll('[title]').forEach(el => {
            el.addEventListener('mouseenter', (e) => {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = e.target.title;
                document.body.appendChild(tooltip);
                
                const rect = e.target.getBoundingClientRect();
                tooltip.style.position = 'fixed';
                tooltip.style.left = rect.left + 'px';
                tooltip.style.top = rect.top - 40 + 'px';
                tooltip.style.background = 'var(--text)';
                tooltip.style.color = 'white';
                tooltip.style.padding = '5px 10px';
                tooltip.style.borderRadius = '4px';
                tooltip.style.fontSize = '12px';
                tooltip.style.zIndex = '10000';
                
                e.target.dataset.tooltip = tooltip;
            });
            
            el.addEventListener('mouseleave', (e) => {
                if (e.target.dataset.tooltip) {
                    document.body.removeChild(e.target.dataset.tooltip);
                    delete e.target.dataset.tooltip;
                }
            });
        });
    </script>
</body>
</html>
