<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="FYPTracker — Final Year Project Supervision and Progress Management System, Department of Computer Science, FULafia">
  <title>FYPTracker — Sign In</title>

  <!-- Fonts: Inter + JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <!-- Lucide Icons (SVG, CDN) -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

  <link rel="stylesheet" href="assets/css/style.css">

  <style>
    /* ── Landing-page-only styles ─────────────────────────────────────── */

    body { background: var(--surface-sunken); }

    [data-theme="dark"] body {
      background: #080d13;
      background-image:
        radial-gradient(ellipse 80% 50% at 15% 0%, rgba(5,150,105,0.07) 0%, transparent 60%),
        radial-gradient(ellipse 55% 55% at 88% 100%, rgba(16,185,129,0.04) 0%, transparent 55%);
    }

    /* ── Landing wrapper ── */
    .landing-wrap {
      min-height: 100dvh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--sp-6) var(--sp-4);
    }

    /* ── Two-panel card ── */
    .auth-card {
      width: 100%;
      max-width: 1000px;
      background: var(--surface-raised);
      border: 1px solid var(--surface-border);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-xl);
      display: grid;
      grid-template-columns: 1fr 400px;
      overflow: hidden;
      animation: fadeUp var(--dur-slow) var(--ease-out) both;
    }

    /* ── Left / hero panel ── */
    .hero-panel {
      background: #0d1f17;
      background-image:
        radial-gradient(ellipse 90% 60% at 0% 0%, rgba(5,150,105,0.25) 0%, transparent 55%),
        radial-gradient(ellipse 70% 70% at 100% 100%, rgba(16,185,129,0.08) 0%, transparent 60%);
      padding: var(--sp-10) var(--sp-8);
      display: flex;
      flex-direction: column;
      gap: var(--sp-8);
      position: relative;
      overflow: hidden;
      border-right: 1px solid rgba(255,255,255,0.06);
    }

    [data-theme="light"] .hero-panel {
      background: #0f2d1e;
      background-image:
        radial-gradient(ellipse 90% 60% at 0% 0%, rgba(5,150,105,0.3) 0%, transparent 55%),
        radial-gradient(ellipse 70% 70% at 100% 100%, rgba(16,185,129,0.1) 0%, transparent 60%);
    }

    /* Decorative grid lines */
    .hero-panel::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
      background-size: 40px 40px;
      mask-image: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.6) 30%, rgba(0,0,0,0.6) 70%, transparent 100%);
      pointer-events: none;
    }

    /* Brand mark */
    .brand-mark {
      display: flex;
      flex-direction: column;
      gap: var(--sp-4);
      position: relative;
    }

    .brand-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: var(--sp-2);
      background: rgba(16,185,129,0.12);
      border: 1px solid rgba(16,185,129,0.2);
      border-radius: var(--radius-full);
      padding: 4px var(--sp-3);
      width: fit-content;
    }

    .brand-eyebrow-dot {
      width: 6px; height: 6px;
      border-radius: var(--radius-full);
      background: var(--accent-400);
      animation: pulse 2.5s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50%       { opacity: 0.4; }
    }

    .brand-eyebrow-text {
      font-size: 0.6875rem;
      font-weight: 600;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--accent-400);
    }

    .brand-name {
      font-size: 2.25rem;
      font-weight: 700;
      letter-spacing: -0.03em;
      color: #ffffff;
      line-height: 1.1;
    }

    .brand-name span { color: var(--accent-400); }

    .brand-tagline {
      font-size: var(--text-sm);
      color: rgba(255,255,255,0.5);
      line-height: 1.6;
      max-width: 320px;
    }

    /* Feature list */
    .feature-list {
      display: flex;
      flex-direction: column;
      gap: var(--sp-3);
      position: relative;
    }

    .feature-item {
      display: flex;
      align-items: flex-start;
      gap: var(--sp-3);
    }

    .feature-icon {
      width: 32px; height: 32px;
      border-radius: var(--radius-md);
      background: rgba(16,185,129,0.1);
      border: 1px solid rgba(16,185,129,0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: var(--accent-400);
      margin-top: 1px;
    }

    .feature-text { flex: 1; }
    .feature-title {
      font-size: var(--text-sm);
      font-weight: 600;
      color: rgba(255,255,255,0.85);
      margin-bottom: 2px;
      line-height: 1.3;
    }
    .feature-desc {
      font-size: var(--text-xs);
      color: rgba(255,255,255,0.4);
      line-height: 1.5;
    }

    /* Institution badge */
    .institution-badge {
      display: flex;
      align-items: center;
      gap: var(--sp-2);
      padding: var(--sp-3) var(--sp-4);
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: var(--radius-md);
      position: relative;
    }

    .institution-logo {
      width: 32px; height: 32px;
      border-radius: var(--radius-sm);
      background: linear-gradient(135deg, var(--accent-700), var(--accent-900));
      display: flex; align-items: center; justify-content: center;
      font-size: var(--text-sm);
      font-weight: 800;
      color: var(--accent-300);
      flex-shrink: 0;
      font-family: var(--font-mono);
    }

    .institution-text { flex: 1; min-width: 0; }
    .institution-name {
      font-size: var(--text-xs);
      font-weight: 600;
      color: rgba(255,255,255,0.75);
      line-height: 1.3;
    }
    .institution-dept {
      font-size: 0.6875rem;
      color: rgba(255,255,255,0.35);
      line-height: 1.3;
    }

    /* ── Right / form panel ── */
    .form-panel {
      padding: var(--sp-8) var(--sp-6);
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: var(--surface-raised);
    }

    /* Tabs */
    .auth-tabs {
      display: flex;
      gap: 4px;
      background: var(--surface-overlay);
      border: 1px solid var(--surface-border);
      border-radius: var(--radius-md);
      padding: 4px;
      margin-bottom: var(--sp-6);
    }

    .tab-btn {
      flex: 1;
      padding: 8px var(--sp-3);
      min-height: 36px; /* accessible tap target */
      border: none;
      border-radius: var(--radius-sm);
      background: transparent;
      font-family: var(--font-sans);
      font-size: var(--text-sm);
      font-weight: 500;
      color: var(--text-tertiary);
      cursor: pointer;
      transition: background var(--dur-fast) var(--ease-out),
                  color var(--dur-fast) var(--ease-out),
                  box-shadow var(--dur-fast) var(--ease-out);
    }

    .tab-btn.active {
      background: var(--surface-base);
      color: var(--text-primary);
      box-shadow: var(--shadow-sm);
    }

    .tab-btn:hover:not(.active) {
      color: var(--text-secondary);
      background: rgba(255,255,255,0.04);
    }

    /* Auth section visibility */
    .auth-section { display: none; }
    .auth-section.active { display: flex; flex-direction: column; gap: var(--sp-5); }

    .auth-heading { margin-bottom: var(--sp-1); }
    .auth-title {
      font-size: var(--text-xl);
      font-weight: 700;
      letter-spacing: -0.02em;
      color: var(--text-primary);
      margin-bottom: var(--sp-1);
    }
    .auth-subtitle {
      font-size: var(--text-sm);
      color: var(--text-tertiary);
      line-height: 1.5;
    }

    /* Form submit */
    .btn-auth {
      width: 100%;
      padding: 11px var(--sp-4);
      min-height: 44px;
      background: var(--accent-600);
      color: #fff;
      border: none;
      border-radius: var(--radius-md);
      font-family: var(--font-sans);
      font-size: var(--text-sm);
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: var(--sp-2);
      transition: background var(--dur-fast) var(--ease-out),
                  box-shadow var(--dur-fast) var(--ease-out),
                  transform var(--dur-fast) var(--ease-out);
    }

    .btn-auth:hover {
      background: var(--accent-700);
      box-shadow: 0 0 0 3px rgba(16,185,129,0.2);
    }

    .btn-auth:active { transform: scale(0.99); }

    /* Password strength */
    .pw-strength {
      height: 3px;
      border-radius: var(--radius-full);
      background: var(--surface-sunken);
      margin-top: var(--sp-2);
      overflow: hidden;
    }

    .pw-strength-fill {
      height: 100%;
      width: 0;
      border-radius: var(--radius-full);
      transition: width 250ms ease-out, background 250ms ease-out;
    }

    /* Footer text */
    .auth-footer-text {
      text-align: center;
      font-size: var(--text-xs);
      color: var(--text-tertiary);
    }
    .auth-footer-text a {
      color: var(--accent-500);
      font-weight: 500;
    }
    .auth-footer-text a:hover { text-decoration: underline; }

    /* Demo accounts box */
    .demo-box {
      padding: var(--sp-3) var(--sp-4);
      background: var(--surface-overlay);
      border: 1px solid var(--surface-border);
      border-radius: var(--radius-md);
    }

    .demo-box-label {
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--text-tertiary);
      margin-bottom: var(--sp-2);
    }

    .demo-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 5px 0;
      border-bottom: 1px solid var(--surface-divider);
    }

    .demo-row:last-child { border-bottom: none; padding-bottom: 0; }
    .demo-row:first-of-type { padding-top: 0; }

    .demo-role {
      font-size: var(--text-xs);
      font-weight: 600;
      color: var(--text-secondary);
      display: flex;
      align-items: center;
      gap: var(--sp-2);
    }

    .demo-email {
      font-family: var(--font-mono);
      font-size: 0.6875rem;
      color: var(--text-tertiary);
    }

    /* Theme toggle — top right corner */
    .landing-theme-btn {
      position: fixed;
      top: var(--sp-4);
      right: var(--sp-4);
      width: 40px; height: 40px;
      border-radius: var(--radius-md);
      background: var(--surface-raised);
      border: 1px solid var(--surface-border);
      box-shadow: var(--shadow-sm);
      display: flex; align-items: center; justify-content: center;
      color: var(--text-tertiary);
      cursor: pointer;
      transition: color var(--dur-fast), background var(--dur-fast);
      z-index: var(--z-sticky);
    }
    .landing-theme-btn:hover { color: var(--text-primary); background: var(--surface-overlay); }

    /* Responsive */
    @media (max-width: 768px) {
      .auth-card { grid-template-columns: 1fr; max-width: 440px; }
      .hero-panel { display: none; }
      .form-panel { padding: var(--sp-8) var(--sp-5); }
    }
  </style>
</head>
<body>

<?php
require_once 'includes/functions.php';

// Already logged in → dashboard
if (isLoggedIn()) {
    redirect(dashboardUrl($_SESSION['role']));
}

$activeTab   = (get('tab') === 'register') ? 'register' : 'login';
$infoMessage = get('message');
?>

<!-- Fixed theme toggle -->
<button
  class="landing-theme-btn"
  id="themeToggle"
  aria-label="Toggle theme"
  title="Toggle theme"
  onclick="toggleTheme()">
  <i data-lucide="sun" width="16" height="16" id="icon-sun"  style="display:none"></i>
  <i data-lucide="moon" width="16" height="16" id="icon-moon"></i>
</button>

<div class="landing-wrap">
  <div class="auth-card">

    <!-- ═══════════════ LEFT: HERO ═══════════════ -->
    <div class="hero-panel" aria-hidden="true">

      <!-- Brand -->
      <div class="brand-mark">
        <div class="brand-eyebrow">
          <span class="brand-eyebrow-dot"></span>
          <span class="brand-eyebrow-text">Academic Year 2024 / 2025</span>
        </div>

        <div>
          <div class="brand-name">FYP<span>Tracker</span></div>
          <p class="brand-tagline">
            Final Year Project supervision and progress management — structured, paperless, transparent.
          </p>
        </div>
      </div>

      <!-- Features -->
      <div class="feature-list">
        <div class="feature-item">
          <div class="feature-icon">
            <i data-lucide="file-text" width="15" height="15"></i>
          </div>
          <div class="feature-text">
            <div class="feature-title">Proposal Lifecycle</div>
            <div class="feature-desc">Submit, review, approve or reject proposals — with full audit trail</div>
          </div>
        </div>
        <div class="feature-item">
          <div class="feature-icon">
            <i data-lucide="target" width="15" height="15"></i>
          </div>
          <div class="feature-text">
            <div class="feature-title">Milestone Tracker</div>
            <div class="feature-desc">Chapter-by-chapter progress with due dates and completion status</div>
          </div>
        </div>
        <div class="feature-item">
          <div class="feature-icon">
            <i data-lucide="calendar" width="15" height="15"></i>
          </div>
          <div class="feature-text">
            <div class="feature-title">Meeting Scheduler</div>
            <div class="feature-desc">Book supervision sessions, record minutes and outcomes digitally</div>
          </div>
        </div>
        <div class="feature-item">
          <div class="feature-icon">
            <i data-lucide="message-square" width="15" height="15"></i>
          </div>
          <div class="feature-text">
            <div class="feature-title">Structured Feedback</div>
            <div class="feature-desc">Supervisor comments and ratings on every submitted chapter</div>
          </div>
        </div>
        <div class="feature-item">
          <div class="feature-icon">
            <i data-lucide="bar-chart-2" width="15" height="15"></i>
          </div>
          <div class="feature-text">
            <div class="feature-title">Admin Dashboard</div>
            <div class="feature-desc">Departmental overview, supervisor assignment, PDF report exports</div>
          </div>
        </div>
      </div>

      <!-- Institution -->
      <div class="institution-badge">
        <div class="institution-logo">FUL</div>
        <div class="institution-text">
          <div class="institution-name">Federal University of Lafia</div>
          <div class="institution-dept">Dept. of Computer Science · Faculty of Computing</div>
        </div>
      </div>

    </div><!-- /hero-panel -->

    <!-- ═══════════════ RIGHT: FORM ═══════════════ -->
    <div class="form-panel">

      <!-- Flash messages -->
      <?php renderFlash(); ?>

      <?php if ($infoMessage): ?>
      <div class="alert alert-info" data-auto-dismiss="6000" style="margin-bottom:var(--sp-4);">
        <i data-lucide="info" width="16" height="16" class="alert-icon"></i>
        <div class="alert-content"><?= e($infoMessage) ?></div>
        <button class="alert-close" aria-label="Dismiss"><i data-lucide="x" width="14" height="14"></i></button>
      </div>
      <?php endif; ?>

      <!-- Tabs -->
      <div class="auth-tabs" role="tablist">
        <button
          id="tab-login"
          class="tab-btn <?= $activeTab === 'login' ? 'active' : '' ?>"
          role="tab"
          aria-selected="<?= $activeTab === 'login' ? 'true' : 'false' ?>"
          aria-controls="section-login"
          onclick="switchTab('login')">
          Sign In
        </button>
        <button
          id="tab-register"
          class="tab-btn <?= $activeTab === 'register' ? 'active' : '' ?>"
          role="tab"
          aria-selected="<?= $activeTab === 'register' ? 'true' : 'false' ?>"
          aria-controls="section-register"
          onclick="switchTab('register')">
          Register
        </button>
      </div>

      <!-- ── LOGIN ── -->
      <div
        class="auth-section <?= $activeTab === 'login' ? 'active' : '' ?>"
        id="section-login"
        role="tabpanel"
        aria-labelledby="tab-login">

        <div class="auth-heading">
          <div class="auth-title">Welcome back</div>
          <div class="auth-subtitle">Sign in to your FYPTracker account.</div>
        </div>

        <form method="POST" action="auth/login.php" novalidate>
          <?= csrfField() ?>

          <div class="form-stack">
            <div class="form-group">
              <label class="form-label" for="login-email">Email Address</label>
              <input
                class="form-control"
                type="email"
                id="login-email"
                name="email"
                placeholder="you@fulafia.edu.ng"
                autocomplete="email"
                required>
            </div>

            <div class="form-group">
              <label class="form-label" for="login-password">Password</label>
              <input
                class="form-control"
                type="password"
                id="login-password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required>
            </div>

            <button type="submit" class="btn-auth">
              <i data-lucide="log-in" width="16" height="16"></i>
              Sign In
            </button>
          </div>
        </form>

        <div class="divider-text">demo accounts</div>

        <!-- Demo accounts — remove in production -->
        <div class="demo-box">
          <div class="demo-box-label">All passwords: Password123!</div>
          <div class="demo-row">
            <span class="demo-role"><i data-lucide="shield" width="12" height="12"></i>Admin</span>
            <span class="demo-email">admin@fyptracker.fulafia.edu.ng</span>
          </div>
          <div class="demo-row">
            <span class="demo-role"><i data-lucide="user-check" width="12" height="12"></i>Supervisor</span>
            <span class="demo-email">e.okonkwo@fulafia.edu.ng</span>
          </div>
          <div class="demo-row">
            <span class="demo-role"><i data-lucide="graduation-cap" width="12" height="12"></i>Student</span>
            <span class="demo-email">a.oche@student.fulafia.edu.ng</span>
          </div>
        </div>

        <p class="auth-footer-text">
          Don't have an account?
          <a href="#" onclick="switchTab('register'); return false;">Register here</a>
        </p>

      </div><!-- /section-login -->

      <!-- ── REGISTER ── -->
      <div
        class="auth-section <?= $activeTab === 'register' ? 'active' : '' ?>"
        id="section-register"
        role="tabpanel"
        aria-labelledby="tab-register">

        <div class="auth-heading">
          <div class="auth-title">Create account</div>
          <div class="auth-subtitle">Register as a student. Supervisor accounts are created by the admin.</div>
        </div>

        <form method="POST" action="auth/register.php" novalidate>
          <?= csrfField() ?>

          <div class="form-stack">
            <div class="form-group">
              <label class="form-label form-label-required" for="reg-name">Full Name</label>
              <input
                class="form-control"
                type="text"
                id="reg-name"
                name="full_name"
                placeholder="e.g. Abraham Oche"
                autocomplete="name"
                required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label form-label-required" for="reg-email">Email</label>
                <input
                  class="form-control"
                  type="email"
                  id="reg-email"
                  name="email"
                  placeholder="name@student.fulafia.edu.ng"
                  autocomplete="email"
                  required>
              </div>
              <div class="form-group">
                <label class="form-label form-label-required" for="reg-matric">Matric Number</label>
                <input
                  class="form-control"
                  type="text"
                  id="reg-matric"
                  name="matric_number"
                  placeholder="FUL/CS/2021/001"
                  autocomplete="off"
                  required>
                <span class="form-hint">Format: FUL/CS/YYYY/NNN</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label form-label-required" for="reg-password">Password</label>
                <input
                  class="form-control"
                  type="password"
                  id="reg-password"
                  name="password"
                  placeholder="Min. 8 characters"
                  autocomplete="new-password"
                  oninput="checkPasswordStrength(this.value, 'pwFill')"
                  required>
                <div class="pw-strength">
                  <div class="pw-strength-fill" id="pwFill"></div>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label form-label-required" for="reg-confirm">Confirm Password</label>
                <input
                  class="form-control"
                  type="password"
                  id="reg-confirm"
                  name="confirm_password"
                  placeholder="Repeat password"
                  autocomplete="new-password"
                  required>
              </div>
            </div>

            <button type="submit" class="btn-auth">
              <i data-lucide="user-plus" width="16" height="16"></i>
              Create Account
            </button>
          </div>
        </form>

        <p class="auth-footer-text">
          Already have an account?
          <a href="#" onclick="switchTab('login'); return false;">Sign in</a>
        </p>

      </div><!-- /section-register -->

    </div><!-- /form-panel -->
  </div><!-- /auth-card -->
</div><!-- /landing-wrap -->

<script src="assets/js/main.js"></script>
<script>
// ── Tab switching ──────────────────────────────────────────────
function switchTab(tab) {
  document.querySelectorAll('.auth-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.classList.remove('active');
    b.setAttribute('aria-selected', 'false');
  });
  document.getElementById('section-' + tab).classList.add('active');
  const btn = document.getElementById('tab-' + tab);
  btn.classList.add('active');
  btn.setAttribute('aria-selected', 'true');
  history.replaceState(null, '', tab === 'register' ? '?tab=register' : '?');

  // Focus first input in newly activated section
  const firstInput = document.getElementById('section-' + tab).querySelector('input');
  setTimeout(() => firstInput?.focus(), 50);
}

// ── Sun/moon icon swap on theme change ───────────────────────
function updateThemeIcon() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  document.getElementById('icon-sun').style.display  = isDark  ? 'none' : 'block';
  document.getElementById('icon-moon').style.display = isDark  ? 'block' : 'none';
}

// Run on load and after toggleTheme
document.addEventListener('DOMContentLoaded', () => {
  updateThemeIcon();
  lucide.createIcons();
});

const _origToggle = window.toggleTheme;
window.toggleTheme = function() {
  _origToggle();
  setTimeout(() => { updateThemeIcon(); lucide.createIcons(); }, 20);
};
</script>
</body>
</html>
