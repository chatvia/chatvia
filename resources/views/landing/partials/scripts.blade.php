<script src="//unpkg.com/alpinejs" defer></script>
<script>
    // Smooth-scroll in-page anchor links, accounting for the fixed header height.
    document.addEventListener('DOMContentLoaded', function() {
        const headerOffset = 80;

        const scrollToTarget = (target) => {
            const offsetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerOffset;
            window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
        };

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '#0') return;

                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    scrollToTarget(target);
                }
            });
        });

        // Honour a hash present on initial load (e.g. /#pricing).
        if (window.location.hash) {
            setTimeout(() => {
                const target = document.querySelector(window.location.hash);
                if (target) {
                    scrollToTarget(target);
                }
            }, 100);
        }
    });
</script>

<script
    id="chatbot-embed-script"
    src="{{ config('chatvia.widget.script_url_embed') }}"
    data-agent-id="{{ config('chatvia.widget.chatvia_widget_id') }}"
    defer>
</script>
