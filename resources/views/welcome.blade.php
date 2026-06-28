<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Planet Sinergi</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,sans-serif;background:#f5f6f7;color:#2c3e50;min-height:100vh;display:flex;align-items:center;justify-content:center}
    .container{text-align:center;padding:2rem}
    .logo{font-size:2rem;font-weight:700;letter-spacing:-0.5px;margin-bottom:0.25rem}
    .logo span{color:#e74c3c}
    .tagline{font-size:0.9rem;color:#94a3b8;margin-bottom:2.5rem}
    .status{display:inline-flex;align-items:center;gap:0.5rem;background:#ecfdf5;color:#065f46;padding:0.6rem 1.2rem;border-radius:999px;font-size:0.85rem;font-weight:500}
    .status::before{content:"";display:inline-block;width:8px;height:8px;background:#10b981;border-radius:50%;animation:pulse 2s infinite}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:0.4}}
    .footer{margin-top:3rem;font-size:0.8rem;color:#cbd5e1}
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">Planet <span>Sinergi</span></div>
    <p class="tagline">Backend API</p>
    <div class="status">Sistem berjalan normal</div>
    <p class="footer">&copy; {{ date('Y') }} Planet Sinergi</p>
  </div>
</body>
</html>