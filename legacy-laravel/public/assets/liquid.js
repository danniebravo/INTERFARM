document.addEventListener('DOMContentLoaded', () => {
  const items = document.querySelectorAll('[data-reveal]');
  items.forEach((el, i) => {
    el.classList.add('reveal');
    el.style.setProperty('--d', `${i * 70}ms`);
  });
  requestAnimationFrame(() => items.forEach(el => el.classList.add('is-in')));
});