import './bootstrap';

// Each page sends its own Content-Security-Policy (SecurityHeaders): Electrik/Slate pages need
// 'unsafe-eval' and style attributes, the others refuse both. A Livewire SPA visit keeps the
// policy of the page it started on, so the new page's scripts and styles get blocked. Load
// every forward navigation (wire:navigate links, redirects with navigate: true) in full.
// Back/forward is left to Livewire: without SPA visits its entries belong to this same page.
document.addEventListener('livewire:navigate', (event) => {
    if (event.detail.history) {
        return;
    }
    event.preventDefault();
    window.location.href = event.detail.url.href;
});
