{{-- Scroll-spy ng sidebar TOC — hino-highlight ang seksyong kasalukuyang
     binabasa. Puro progressive enhancement ito: kung hindi tumakbo ang JS,
     gumagana pa rin ang mga anchor link nang normal. --}}
<script>
    (function () {
        const links = Array.from(document.querySelectorAll('.toc-link'));
        const sections = links
            .map(link => document.querySelector(link.getAttribute('href')))
            .filter(Boolean);

        if (sections.length === 0) return;

        function activate(id) {
            links.forEach(link => link.classList.toggle('active', link.getAttribute('href') === '#' + id));
        }

        const observer = new IntersectionObserver(entries => {
            // Ang pinakamataas na seksyon na nakikita pa ang itinuturing
            // na "kasalukuyan" — mas matatag ito kaysa sa huling nag-fire
            // na entry kapag mabilis ang scroll.
            const visible = entries
                .filter(entry => entry.isIntersecting)
                .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

            if (visible.length) activate(visible[0].target.id);
        }, { rootMargin: '-100px 0px -65% 0px', threshold: 0 });

        sections.forEach(section => observer.observe(section));
        activate(sections[0].id);
    })();
</script>
