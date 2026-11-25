document.addEventListener('DOMContentLoaded', () => {
  if (!document.querySelector('[data-stat]')) return;
  let busy = false;
  async function refresh() {
    if (busy || document.hidden) return;
    busy = true;
    try {
      const response = await fetch('/status', {headers: {'Accept': 'application/json'}, signal: AbortSignal.timeout(8000)});
      if (response.status === 401) { location.href = '/login'; return; }
      if (!response.ok) throw new Error('Status unavailable');
      const data = await response.json();
      document.querySelectorAll('[data-stat]').forEach(el => { el.textContent = data[el.dataset.stat]; });
      const percent = Math.round(data.available / data.capacity * 100);
      document.querySelector('[data-percent]').textContent = `${percent}% tersedia`;
      document.querySelector('.progress-bar').style.width = `${percent}%`;
      const progress = document.querySelector('[role=progressbar]');
      progress.setAttribute('aria-valuenow', data.available); progress.setAttribute('aria-valuemax', data.capacity);
      const badge = document.querySelector('[data-capacity-badge]');
      badge.textContent = data.available === 0 ? 'Penuh' : percent <= 20 ? 'Hampir penuh' : 'Tersedia';
      badge.className = `status-badge ${data.available === 0 ? 'full' : percent <= 20 ? 'almost' : ''}`;
      document.querySelector('[data-device-dot]').classList.toggle('offline', !data.online);
      document.querySelector('[data-device-label]').textContent = data.online ? 'Perangkat terhubung' : 'Perangkat belum terhubung';
      document.querySelector('[data-update]').textContent = `Diperbarui ${new Date().toLocaleTimeString('id-ID', {timeZone:'Asia/Jakarta',hour:'2-digit',minute:'2-digit',second:'2-digit'})} WIB`;
    } catch { document.querySelector('[data-update]').textContent = 'Koneksi terganggu. Data terakhir ditampilkan.'; }
    finally { busy = false; }
  }
  setInterval(refresh, 10000);
  setInterval(async () => {
    if (document.hidden || document.activeElement?.closest('form')) return;
    try {
      const response = await fetch('/', {headers: {'Accept':'text/html'}, signal: AbortSignal.timeout(8000)});
      if (!response.ok || response.redirected) return;
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      for (const id of ['notices-panel', 'vehicle-panel']) {
        const current = document.getElementById(id), updated = page.getElementById(id);
        // Preserve an admin's capacity form and any edits in progress.
        if (current && updated && !current.querySelector('input[name=capacity]')) current.replaceWith(updated);
      }
    } catch { /* Capacity refresh reports connection problems. */ }
  }, 30000);
});
