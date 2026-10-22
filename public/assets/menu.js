// Cabinet menu button: [data-menu-toggle] opens the panel named by aria-controls.
// A file, not an onclick attribute, so the Content-Security-Policy needs no inline handlers.
document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-menu-toggle]');
    if (!button) return;
    var panel = document.getElementById(button.getAttribute('aria-controls'));
    if (!panel) return;
    var open = panel.classList.toggle('open');
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
});
