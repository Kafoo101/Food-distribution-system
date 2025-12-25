<?php
include 'navbar.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Home</title>
  <style>
    html,body{height:100%;margin:0;font-family:Arial,Helvetica,sans-serif}
    .center-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;}
    .center-content{display:flex;flex-direction:column;align-items:center;}
    .logo{width:360px;height:360px}
    .logo svg{width:100%;height:100%;display:block}
  </style>
</head>
<body>
  <div class="center-wrap">
    <div class="center-content">
      <div class="logo" aria-hidden="true">
        <!-- Hamburger icon (inline SVG) -->
        <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Hamburger logo">
          <g fill="none" fill-rule="evenodd">
            <ellipse cx="32" cy="22" rx="28" ry="12" fill="#F3C78B"/>
            <path d="M8 28c0-8 8-14 24-14s24 6 24 14c0 3-2 6-5 8-3 2-8 3-13 3H26c-5 0-10-1-13-3-3-2-5-5-5-8z" fill="#E7A957"/>
            <rect x="10" y="34" width="44" height="10" rx="5" fill="#8B5E3C"/>
            <!-- sesame seeds -->
            <ellipse cx="18" cy="18" rx="1.8" ry="0.9" fill="#fff9d9"/>
            <ellipse cx="26" cy="15" rx="1.6" ry="0.8" fill="#fff9d9"/>
            <ellipse cx="36" cy="16" rx="1.6" ry="0.8" fill="#fff9d9"/>
            <ellipse cx="46" cy="18" rx="1.8" ry="0.9" fill="#fff9d9"/>
          </g>
        </svg>
      </div>
      <h1 style="text-align:center;font-size:2.5rem;margin-top:0;color:#4B2E05;letter-spacing:1px;">Food Distribution System</h1>
    </div>
  </div>
</body>
</html>