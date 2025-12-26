<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js" integrity="sha512-A7AYk1fGKX6S2SsHywmPkrnzTZHrgiVT7GcQkLGDe2ev0aWb8zejytzS8wjo7PGEXKqJOrjQ4oORtnimIRZBtw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script src="//unpkg.com/alpinejs" defer></script>
<script>
    AOS.init({
        once: true,
        disable: 'phone',
        duration: 500,
        easing: 'ease-out-cubic',
    });

    // Handle smooth scrolling with AOS refresh
    document.addEventListener('DOMContentLoaded', function() {
        // Handle anchor link clicks
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '#0') return;

                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();

                    // Calculate offset for fixed header if needed
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    // Smooth scroll
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    // Refresh AOS after scroll completes to trigger animations
                    // Use multiple timeouts to ensure AOS detects elements properly
                    setTimeout(() => {
                        AOS.refresh();
                    }, 600);

                    setTimeout(() => {
                        AOS.refresh();
                    }, 1000);
                }
            });
        });

        // Also handle direct hash navigation on page load
        if (window.location.hash) {
            setTimeout(() => {
                const target = document.querySelector(window.location.hash);
                if (target) {
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    setTimeout(() => {
                        AOS.refresh();
                    }, 600);

                    setTimeout(() => {
                        AOS.refresh();
                    }, 1000);
                }
            }, 100);
        }
    });

</script>

<script
    id="chatbot-embed-script"
    src="{{ config('widget.script_url_embed') }}"
    agent-id="{{ config('widget.chatvia_widget_id') }}"
    defer>
</script>
