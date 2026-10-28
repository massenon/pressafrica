/* Sets the theme before first paint (external file because the CSP forbids inline scripts). */
(function () {
  try {
    var t = JSON.parse(localStorage.getItem('agp.admin.theme'));
    document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');   // light by default, same as the public site
  } catch (e) { document.documentElement.setAttribute('data-theme', 'light'); }
})();
