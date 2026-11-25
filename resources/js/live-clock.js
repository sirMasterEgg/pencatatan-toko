// Kolom datetime-local bertanda data-live-clock ikut berjalan mengikuti jam perangkat.
// Atribut itu hanya dipasang server (TransactionForm) selama waktu masih otomatis.
// Kolom yang sedang difokus tidak diubah, agar tidak mengganggu pengguna yang sedang mengetik.
const pad = (number) => String(number).padStart(2, '0');

const currentMinute = () => {
    const now = new Date();

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
};

setInterval(() => {
    document.querySelectorAll('input[data-live-clock]').forEach((input) => {
        const value = currentMinute();

        if (document.activeElement !== input && input.value !== value) {
            input.value = value;
        }
    });
}, 1000);
