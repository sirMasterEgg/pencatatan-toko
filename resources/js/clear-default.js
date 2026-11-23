// Kolom bertanda data-clear-default="<nilai bawaan>", misalnya Jumlah (1) dan Diskon (0) di form transaksi.
// - Saat difokus PERTAMA kali dan isinya masih nilai bawaan, kolom dikosongkan agar bisa langsung diketik.
// - Saat ditinggal dalam keadaan kosong, nilai bawaan dikembalikan.
const touched = new WeakSet();

const isTarget = (element) => element instanceof HTMLInputElement && element.matches('[data-clear-default]');

document.addEventListener('focusin', (event) => {
    const input = event.target;

    if (!isTarget(input) || touched.has(input)) {
        return;
    }

    touched.add(input);

    if (input.value === input.dataset.clearDefault) {
        input.value = '';
    }
});

// Fase capture pada "blur", agar nilai bawaan sudah kembali sebelum Livewire membacanya.
document.addEventListener('blur', (event) => {
    const input = event.target;

    if (isTarget(input) && input.value.trim() === '') {
        input.value = input.dataset.clearDefault;
    }
}, true);
