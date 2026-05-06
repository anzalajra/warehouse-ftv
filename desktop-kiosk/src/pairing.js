// Pairing UI — pakai contextBridge IPC ke main untuk submit code.
const { ipcRenderer } = require('electron');

const codeInput = document.getElementById('code');
const submitBtn = document.getElementById('submit');
const errBox = document.getElementById('err');
const okBox = document.getElementById('ok');
const serverEl = document.getElementById('server');

ipcRenderer.invoke('pairing:server-base').then((url) => {
  serverEl.textContent = url;
});

codeInput.addEventListener('input', () => {
  codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
});

codeInput.focus();

async function submit() {
  errBox.style.display = 'none';
  okBox.style.display = 'none';
  const code = codeInput.value.trim();
  if (code.length !== 6) {
    errBox.textContent = 'Kode harus 6 digit angka.';
    errBox.style.display = 'block';
    return;
  }
  submitBtn.disabled = true;
  submitBtn.textContent = 'Memproses…';
  try {
    const result = await ipcRenderer.invoke('pairing:submit', code);
    if (result.ok) {
      okBox.textContent = 'Berhasil! Aplikasi akan restart…';
      okBox.style.display = 'block';
      setTimeout(() => ipcRenderer.invoke('pairing:relaunch'), 1500);
    } else {
      errBox.textContent = result.error || 'Gagal pairing.';
      errBox.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Pair';
    }
  } catch (err) {
    errBox.textContent = 'Tidak bisa terhubung ke server. Cek koneksi.';
    errBox.style.display = 'block';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Pair';
  }
}

submitBtn.addEventListener('click', submit);
codeInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') submit();
});
