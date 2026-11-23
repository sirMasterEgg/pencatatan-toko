// Kotak centang bertanda data-check-all="<selektor>" mencentang atau mengosongkan semua kotak yang cocok.
document.addEventListener('change', (event) => {
    const source = event.target;

    if (!(source instanceof HTMLInputElement) || !source.matches('[data-check-all]')) {
        return;
    }

    document.querySelectorAll(source.dataset.checkAll).forEach((box) => {
        box.checked = source.checked;
    });
});
