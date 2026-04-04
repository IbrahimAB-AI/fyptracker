<?php
/**
 * FYPTracker — Shared Page Header
 * includes/header.php
 */

if (!defined('BASE_URL')) define('BASE_URL', '..');
if (!isset($pageTitle))   $pageTitle  = 'FYPTracker';
if (!isset($activeNav))   $activeNav  = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <!-- theme-color updated dynamically by JS to match dark/light mode -->
  <meta name="theme-color" content="#161b22">
  <title><?= e($pageTitle) ?> — FYPTracker</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <!-- Lucide icons -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <!-- Immediately apply theme to avoid flash.
       Priority: stored preference → system preference → dark (default) -->
  <script>
    (function(){
      var stored = null;
      try { stored = localStorage.getItem('fyp-theme'); } catch(e){}
      var system = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      var theme  = stored || system;
      document.documentElement.setAttribute('data-theme', theme);
      // Update theme-color meta immediately
      var meta = document.querySelector('meta[name="theme-color"]');
      if (meta) meta.content = theme === 'dark' ? '#161b22' : '#f8f9fa';
    })();
  </script>
</head>
<body>
<!-- skill: skip-links -->
<a href="#main-content" class="sr-only" style="position:absolute;top:var(--sp-2);left:var(--sp-2);z-index:9999;padding:var(--sp-2) var(--sp-4);background:var(--accent-600);color:#fff;border-radius:var(--radius-md);font-size:var(--text-sm);font-weight:600;">Skip to main content</a>
<div class="app-layout">
