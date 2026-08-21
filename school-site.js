const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('.nav');
toggle?.addEventListener('click', () => {
  const open = nav.classList.toggle('open');
  toggle.setAttribute('aria-expanded', open);
});
nav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
  nav.classList.remove('open');
  toggle.setAttribute('aria-expanded', 'false');
}));

const lightbox = document.createElement('div');
lightbox.className = 'gallery-lightbox';
lightbox.innerHTML = '<figure><button class="gallery-close" aria-label="Close image">×</button><img alt=""><figcaption></figcaption></figure>';
document.body.appendChild(lightbox);
const lightboxImage = lightbox.querySelector('img');
const lightboxCaption = lightbox.querySelector('figcaption');
const closeLightbox = () => lightbox.classList.remove('open');
document.querySelectorAll('.gallery-item').forEach(item => item.addEventListener('click', () => {
  lightboxImage.src = item.dataset.image;
  lightboxImage.alt = item.dataset.label;
  lightboxCaption.textContent = item.dataset.label;
  lightbox.classList.add('open');
}));
lightbox.addEventListener('click', event => { if (event.target === lightbox || event.target.closest('.gallery-close')) closeLightbox(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeLightbox(); });

const revealItems = document.querySelectorAll('.welcome .split-grid, .leadership-grid, .leadership-moments, .values-grid, .academics .shell, .life-panel, .campus-gallery .shell, .teachers .shell, .admissions-inner, .news .shell, .full-gallery .shell, .social-connect-inner');
if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const revealObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  revealItems.forEach(item => { item.classList.add('motion-reveal'); revealObserver.observe(item); });
}
