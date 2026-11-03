/* Adds semantics to the native wrapper; never changes rows, forms or permissions. */
document.addEventListener('DOMContentLoaded', () => {
 if (!document.body.classList.contains('maxmobile-page')) return;
 const tr = document.documentElement.lang.toLowerCase().startsWith('tr');
 const label = tr ? 'Liste tablosu' : 'List table';
 const help = tr ? 'Tabloya odaklanıp sol/sağ ok tuşlarıyla yatay kaydırabilirsiniz.' : 'Focus the table region and use left/right arrow keys to scroll horizontally.';
 document.querySelectorAll('.div-table-responsive').forEach((region, index) => {
  if (!region.querySelector('table.liste')) return;
  region.classList.add('maxmobile-table-scroll');
  region.tabIndex = 0;
  region.setAttribute('role', 'region');
  region.setAttribute('aria-label', label);
  const hint = document.createElement('p');
  hint.id = 'maxmobile-scroll-help-' + index;
  hint.className = 'maxmobile-scroll-help';
  hint.textContent = help;
  region.before(hint);
  region.setAttribute('aria-describedby', hint.id);
  region.addEventListener('keydown', event => {
   if (event.target !== region || event.altKey || event.ctrlKey || event.metaKey) return;
   if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
    event.preventDefault();
    region.scrollLeft += event.key === 'ArrowRight' ? 80 : -80;
   } else if (event.key === 'Home' || event.key === 'End') {
    event.preventDefault();
    region.scrollLeft = event.key === 'Home' ? 0 : region.scrollWidth;
   }
  });
 });
});
