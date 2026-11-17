// Kolom uang bertanda data-rupiah: 15000 menjadi 15.000 saat diketik.
export function formatRupiahInput(input) {
    const caret = input.selectionStart ?? input.value.length;
    const digitsAfterCaret = input.value.slice(caret).replace(/\D/g, '').length;
    const digits = input.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    const formatted = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    if (formatted === input.value) {
        return;
    }

    input.value = formatted;

    let position = formatted.length;
    let seen = 0;
    while (position > 0 && seen < digitsAfterCaret) {
        position--;
        if (/\d/.test(formatted[position])) {
            seen++;
        }
    }
    input.setSelectionRange(position, position);
}

// Fase capture, agar nilai sudah berformat sebelum Livewire membacanya.
document.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches('[data-rupiah]')) {
        formatRupiahInput(event.target);
    }
}, true);
