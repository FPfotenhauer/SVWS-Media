		</main>
	</div>
</div>

<script>
    // Dark Mode Toggle
    (function() {
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const htmlElement = document.documentElement;
        
        // Load saved preference
        const savedTheme = localStorage.getItem('svws-dark-mode');
        if (savedTheme === 'true') {
            htmlElement.classList.add('dark-mode');
            darkModeToggle.checked = true;
        }
        
        // Toggle on change
        if (darkModeToggle) {
            darkModeToggle.addEventListener('change', function() {
                if (this.checked) {
                    htmlElement.classList.add('dark-mode');
                    localStorage.setItem('svws-dark-mode', 'true');
                } else {
                    htmlElement.classList.remove('dark-mode');
                    localStorage.setItem('svws-dark-mode', 'false');
                }
            });
        }
    })();
</script>
</body>
</html>
