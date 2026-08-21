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

const galleryCaptions = {
  'Awards.jpg': 'Academic Excellence Award Recipients',
  'Awards2.jpg': 'Academic Excellence Award Ceremony',
  'Awards3.jpg': 'Honouring Outstanding Students',
  'Campus1.jpg': 'A Warm Welcome to the AYISEC Campus',
  'Culture1.jpg': 'Cultural Dance Performance',
  'Culture2.jpg': 'Celebrating Ghanaian Culture',
  'Culture3.jpg': 'Cultural Dance in Motion',
  'Dinner night.jpg': 'AYISEC Dinner Night',
  'Dinner night2.jpg': 'An Evening of Fellowship',
  'fashion1.jpg': 'Student Fashion and Creativity',
  'General Pictures.jpg': 'Highlights from School Life',
  'Head2.jpg': 'Leadership and Partnership',
  'Headmaster and students.jpg': 'Headmaster with Award-Winning Students',
  'Headmater.jpg': 'Headmaster Recognising Excellence',
  'Minster.jpg': 'Guest of Honour at AYISEC',
  'NGO.jpg': 'Partners Supporting AYISEC',
  'NGO2.jpg': 'Community Partnership Event',
  'Random.jpg': 'Student Address at a School Event',
  'Sports.jpg': 'Inter-House Sports Gathering',
  'Sports2.jpg': 'Annual Inter-House Athletics Competition',
  'Sports3.jpg': 'Student House Formation',
  'Student.jpg': 'Proud AYISEC Students',
  'Teacher2.jpg': 'Teaching and Support Staff',
  'Teachers1.jpg': 'Staff at a School Event',
  'Teachers3.jpg': 'Classroom Learning Session'
};
document.querySelectorAll('.gallery-item').forEach(item => {
  const fileName = decodeURIComponent(item.dataset.image || '').split('/').pop();
  const caption = galleryCaptions[fileName];
  if (!caption) return;
  item.dataset.label = caption;
  item.title = caption;
  item.querySelector('img').alt = `Ayirebi Senior High School — ${caption}`;
  item.querySelector('span').textContent = caption;
});
