/* ============================================================
   SIMRS Rumah Sakit Dunia Indah - CSS bersama
   ============================================================ */
:root {
  --biru-utama: #1d4ed8;
  --biru-tua: #1e3a8a;
  --biru-hover: #1e40af;
  --biru-muda: #eff6ff;
  --biru-garis: #dbeafe;
  --latar: #eef3fa;
  --teks: #1e293b;
  --teks-redup: #64748b;
}

* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
body { background: var(--latar); color: var(--teks); }

/* ---------- Tombol ---------- */
.btn { padding: 10px 18px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
.btn-primary { background: var(--biru-utama); color: #fff; }
.btn-primary:hover { background: var(--biru-hover); }
.btn-outline { background: #fff; color: var(--biru-utama); border: 1px solid var(--biru-utama); }
.btn-outline:hover { background: var(--biru-muda); }
.btn-block { width: 100%; }

/* ---------- Pesan (flash / error) ---------- */
.alert { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; border: 1px solid transparent; }
.alert-error   { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.alert-success { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
.alert-info    { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }

/* ---------- Form ---------- */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
.form-group input,
.form-group select,
.form-group textarea {
  width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;
}
.form-group input:focus { outline: none; border-color: var(--biru-utama); box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15); }

/* ---------- Halaman login ---------- */
.login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.login-card {
  background: #fff; width: 100%; max-width: 420px; border-radius: 12px; padding: 32px 30px;
  border: 1px solid var(--biru-garis); box-shadow: 0 4px 16px rgba(30, 58, 138, 0.12);
}
.login-logo { width: 120px; height: 120px; margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; }
.login-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
.login-card h1 { text-align: center; font-size: 22px; color: var(--biru-utama); }
.login-sub { text-align: center; font-size: 14px; color: var(--teks-redup); margin-bottom: 22px; }

/* Kotak akun demo */
.demo-box { margin-top: 22px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 12px; font-size: 11.5px; color: #92400e; }
.demo-box strong { display: block; margin-bottom: 6px; }
.demo-box table { width: 100%; border-collapse: collapse; }
.demo-box td { padding: 2px 4px; font-family: 'Consolas', monospace; }
.demo-box td:first-child { font-family: 'Segoe UI', Arial, sans-serif; }

/* ---------- Dashboard sementara (diganti di Fase 5) ---------- */
.simple-page { max-width: 720px; margin: 40px auto; padding: 0 20px; }
.card { background: #fff; border-radius: 10px; padding: 22px; margin-bottom: 22px; border: 1px solid var(--biru-garis); box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08); }
.card h2 { font-size: 16px; color: var(--biru-utama); border-bottom: 2px solid var(--biru-garis); padding-bottom: 8px; margin-bottom: 12px; }
.perm-list { display: flex; flex-wrap: wrap; gap: 6px; }
.perm-list span { background: var(--biru-garis); color: var(--biru-utama); font-size: 12px; padding: 3px 10px; border-radius: 20px; font-family: 'Consolas', monospace; }

/* ============================================================
   Layout aplikasi: navbar, sidebar, konten (Fase 5)
   ============================================================ */
.navbar { background: var(--biru-utama); color: #fff; padding: 14px 32px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15); }
.navbar .brand { display: flex; align-items: center; gap: 12px; }
.navbar .brand-mini { width: 36px; height: 36px; background: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 4px; }
.navbar .brand-mini img { max-width: 100%; max-height: 100%; object-fit: contain; }
.navbar h1 { font-size: 18px; font-weight: 600; }
.navbar .user-info { display: flex; align-items: center; gap: 12px; font-size: 14px; }
.navbar .avatar { width: 32px; height: 32px; border-radius: 50%; background: #3b82f6; display: flex; align-items: center; justify-content: center; font-weight: bold; }
.navbar .logout-form { margin: 0; }
.navbar .logout-btn { background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.4); color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; }
.navbar .logout-btn:hover { background: rgba(255, 255, 255, 0.25); }

.layout { display: flex; min-height: calc(100vh - 64px); }
.sidebar { width: 250px; background: var(--biru-tua); color: #dbeafe; padding: 20px 0; flex-shrink: 0; }
.sidebar .menu-item { display: block; padding: 12px 24px; font-size: 14px; color: #dbeafe; text-decoration: none; border-left: 4px solid transparent; }
.sidebar a.menu-item:hover { background: #2547a0; }
.sidebar .menu-item.active { background: #2563eb; border-left-color: #93c5fd; color: #fff; font-weight: 600; }
.sidebar .menu-item.disabled { opacity: 0.45; cursor: not-allowed; }

.content { flex: 1; padding: 28px; min-width: 0; }
.breadcrumb { font-size: 13px; color: var(--teks-redup); margin-bottom: 16px; }
.breadcrumb span { color: var(--biru-utama); font-weight: 600; }
.app-footer { margin-top: 30px; padding-top: 14px; border-top: 1px solid var(--biru-garis); font-size: 12px; color: #94a3b8; text-align: center; }

/* ---------- Dashboard ---------- */
.welcome { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.role-badge { display: inline-block; background: var(--biru-garis); color: var(--biru-utama); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 6px; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 22px; }
.kpi-box { background: #fff; border: 1px solid var(--biru-garis); border-radius: 10px; padding: 16px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); }
.kpi-box span { display: block; font-size: 12px; color: var(--teks-redup); margin-bottom: 6px; }
.kpi-box strong { font-size: 26px; color: var(--biru-utama); }

.module-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
.module-card { display: block; background: #f0f6ff; border: 1px solid var(--biru-garis); border-radius: 10px; padding: 16px; text-decoration: none; color: var(--teks); }
a.module-card:hover { border-color: var(--biru-utama); background: #e6f0ff; }
.module-card .icon { font-size: 24px; display: block; margin-bottom: 6px; }
.module-card .name { font-weight: 600; font-size: 14px; }
.module-card .meta { font-size: 11px; color: var(--teks-redup); margin-top: 4px; }
.module-card.disabled { opacity: 0.55; cursor: default; }

@media (max-width: 800px) {
  .layout { flex-direction: column; }
  .sidebar { width: 100%; }
  .navbar { padding: 12px 16px; flex-wrap: wrap; gap: 10px; }
  .navbar h1 { font-size: 15px; }
}