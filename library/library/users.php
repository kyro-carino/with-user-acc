<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, must-revalidate');
$v = static fn(string $f): int => (int)@filemtime(__DIR__ . '/' . $f);
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#174d2c">
  <link rel="icon" type="image/png" href="img/favicon-64.png">
  <title>FEU Roosevelt Library | Reader Portal</title>
  <script src="js/theme-init.js"></script>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="stylesheet" href="style.css?v=<?= $v('style.css') ?>">
  <link rel="stylesheet" href="users.css?v=<?= $v('users.css') ?>">
  <script src="users.js?v=<?= $v('users.js') ?>" defer></script>
</head>
<body>
  <section class="login-screen" id="reader-auth-screen" aria-labelledby="reader-login-title">
    <section class="login-card reader-login-card">
      <img class="login-logo" src="img/logo-112.png" alt="FEU Roosevelt" width="46" height="56">
      <p class="login-kicker">FEU Roosevelt · Library</p>
      <h1 id="reader-login-title">Reader sign in</h1>
      <p class="login-description">Sign in or create an account to explore your library dashboard.</p>
      <div class="reader-auth-tabs" role="tablist" aria-label="Reader account access">
        <button class="reader-auth-tab is-active" id="signin-tab" type="button" role="tab" aria-selected="true" aria-controls="signin-panel">Sign in</button>
        <button class="reader-auth-tab" id="signup-tab" type="button" role="tab" aria-selected="false" aria-controls="signup-panel">Create account</button>
      </div>
      <div id="signin-panel" role="tabpanel" aria-labelledby="signin-tab">
        <form id="signin-form" class="reader-auth-form" novalidate>
          <div class="field"><label for="signin-identity">Username or email</label><input id="signin-identity" name="username" autocomplete="username" required></div>
          <div class="field"><label for="signin-password">Password</label><input id="signin-password" name="password" type="password" autocomplete="current-password" required></div>
          <p id="reader-login-error" class="login-error" role="alert" hidden></p>
          <button class="btn btn-primary btn-block" type="submit">Sign in</button>
        </form>
      </div>
      <div id="signup-panel" role="tabpanel" aria-labelledby="signup-tab" hidden>
        <form id="signup-form" class="reader-auth-form" novalidate>
          <div class="reader-name-row">
            <div class="field"><label for="first-name">First name</label><input id="first-name" name="firstName" autocomplete="given-name" maxlength="100" required></div>
            <div class="field"><label for="last-name">Last name</label><input id="last-name" name="lastName" autocomplete="family-name" maxlength="100" required></div>
          </div>
          <div class="field"><label for="signup-email">Email</label><input id="signup-email" name="email" type="email" autocomplete="email" maxlength="255" required></div>
          <div class="field"><label for="signup-username">Username</label><input id="signup-username" name="username" autocomplete="username" minlength="3" maxlength="24" required></div>
          <div class="field"><label for="signup-password">Password <span class="reader-label-note">12 characters minimum</span></label><input id="signup-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></div>
          <p id="reader-signup-error" class="login-error" role="alert" hidden></p>
          <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>
      </div>
      <p class="reader-login-note">Staff sign in through the administration dashboard.</p>
    </section>
  </section>

  <div id="reader-app" hidden>
    <div class="shell">
      <header class="app-header" id="reader-app-header">
        <div class="header-row">
          <div class="header-left"><button class="hamburger reader-hamburger" id="reader-hamburger" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="reader-navigation"><span></span></button><a class="brand reader-brand" href="users.php" aria-label="FEU Roosevelt Library — Discover"><img class="brand-logo" src="img/logo-112.png" alt="FEU Roosevelt" width="36" height="44"></a></div>
          <nav class="menubar reader-menubar" id="reader-navigation" aria-label="Reader navigation">
            <button class="menu-link active" type="button" data-view="overview" aria-current="page">Discover</button>
            <button class="menu-link" type="button" data-view="books">Collection</button>
            <div class="menu-drop" data-nav-group="transactions">
              <button class="menu-link" type="button" aria-haspopup="true" aria-expanded="false">Transactions <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg></button>
              <div class="dropdown-panel">
                <button class="drop-item" type="button" data-view="loans">My Loans</button>
                <button class="drop-item" type="button" data-view="reservations">My Requests</button>
              </div>
            </div>
            <button class="menu-link" type="button" data-view="account">Account</button>
          </nav>
          <div class="header-actions">
            <form class="search-global reader-global-search" id="global-search-form" role="search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
              <input id="global-search-input" type="search" placeholder="Search books, authors, subjects…" aria-label="Search books, authors, subjects">
            </form>
            <div class="header-pop reader-account-menu">
              <button class="avatar-btn" id="reader-avatar-btn" type="button" aria-label="Reader account menu" aria-expanded="false" aria-controls="reader-profile-menu"><span class="avatar-chip" id="reader-avatar">R</span></button>
              <div class="dropdown-panel profile-menu" id="reader-profile-menu" hidden>
                <div class="profile-menu-head"><span class="avatar-chip sm" id="reader-menu-avatar">R</span><div><div class="pm-name" id="reader-menu-name">Reader</div><div class="pm-user" id="reader-menu-user">@reader</div></div></div>
                <button class="drop-item" type="button" data-view="account">My account</button>
                <button class="drop-item" type="button" id="reader-theme-toggle">Dark Mode</button>
                <button class="drop-item danger" type="button" id="reader-logout">Sign out</button>
              </div>
            </div>
          </div>
        </div>
      </header>

      <div class="main"><main class="view-port reader-view-port" id="reader-main" tabindex="-1">
        <section class="view active reader-page" data-section="overview" aria-label="Discover">
          <div class="site-hero reader-hero">
            <div class="site-hero-copy">
              <div class="site-hero-eyebrow">FEU Roosevelt Library</div>
              <h1 class="site-hero-heading">There is always something worth discovering.</h1>
              <p class="site-hero-sub">Explore the FEU Roosevelt Library collection, find your next book, and follow your curiosity.</p>
              <form class="site-hero-search" id="hero-search-form" role="search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="hero-search-input" type="search" placeholder="Search books, authors, subjects…" autocomplete="off" aria-label="Search books, authors, subjects">
                <button class="btn btn-primary hero-search-btn" type="submit">Search</button>
              </form>
            </div>
            <div class="site-hero-visual"><div class="hero-emblem"><img src="img/logo-tamaraw.png" alt="FEU Roosevelt Tamaraw" width="400" height="567"></div></div>
          </div>
          <section class="site-section" aria-labelledby="reader-featured-heading">
            <div class="site-section-head"><div><h2 class="site-section-heading" id="reader-featured-heading">Featured collection</h2><p class="site-section-sub">Books selected for curious minds.</p></div></div>
            <div class="book-row reader-featured-row" id="featured-grid"></div>
          </section>

        <!-- ========================= LIBRARY PHOTO ========================= -->
        <section class="site-section library-photo-section" aria-label="The FEU Roosevelt Library building">
        <div class="library-photo-frame">
        <div class="library-photo-media">
        <img class="library-photo-img" src="https://images.unsplash.com/photo-1760166699654-5d0e10f51994?auto=format&amp;fit=crop&amp;w=1800&amp;q=80" alt="A warm, light-filled reading room inside the FEU Roosevelt Library" loading="lazy">
        </div>
        <div class="library-photo-caption">
        <span class="library-photo-eyebrow">Our home</span>
        <span class="library-photo-line">FEU Roosevelt Library</span>
        <p class="library-photo-body">A quiet, light-filled space built for reading, research, and everything in between.</p>
        <button class="btn library-photo-cta" type="button" data-anchor="about">
        Learn more
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
        </div>
        </div>
        </section>

        <!-- ========================= LIBRARY SERVICES ========================= -->
        <section class="site-section services-section" id="services" aria-labelledby="services-heading">
        <div class="site-section-head">
        <div>
        <h2 class="site-section-heading" id="services-heading">Library services</h2>
        <p class="site-section-sub">Everything the collection offers, beyond the shelves.</p>
        </div>
        </div>
        <div class="services-grid">
        <div class="service-card">
        <div class="service-card-photo"><img src="https://images.unsplash.com/photo-1614849963640-9cc74b2a826f?auto=format&amp;fit=crop&amp;w=600&amp;q=75" alt="" loading="lazy"></div>
        <div class="service-card-body">
        <div class="service-index">01</div>
        <h3>Borrowing</h3>
        <p id="svc-borrowing-text">Take home titles from the collection, with straightforward loan periods for students and faculty.</p>
        </div>
        </div>
        <div class="service-card">
        <div class="service-card-photo"><img src="https://images.unsplash.com/photo-1544640808-32ca72ac7f37?auto=format&amp;fit=crop&amp;w=600&amp;q=75" alt="" loading="lazy"></div>
        <div class="service-card-body">
        <div class="service-index">02</div>
        <h3>Reservations</h3>
        <p>Hold a title that's out on loan and we'll set it aside the moment it's returned.</p>
        </div>
        </div>
        <div class="service-card">
        <div class="service-card-photo"><img src="https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&amp;fit=crop&amp;w=600&amp;q=75" alt="" loading="lazy"></div>
        <div class="service-card-body">
        <div class="service-index">03</div>
        <h3>Visit us</h3>
        <p id="svc-contact-text">Visit the front desk for help finding a title.</p>
        </div>
        </div>
        <div class="service-card">
        <div class="service-card-photo"><img src="https://images.unsplash.com/photo-1521920592574-49e0b121c964?auto=format&amp;fit=crop&amp;w=600&amp;q=75" alt="" loading="lazy"></div>
        <div class="service-card-body">
        <div class="service-index">04</div>
        <h3>Research assistance</h3>
        <p>Sit down with a librarian to shape a search, a citation list, or a reading path.</p>
        </div>
        </div>
        <div class="service-card">
        <div class="service-card-photo"><img src="https://images.unsplash.com/photo-1656849093674-937910978e7a?auto=format&amp;fit=crop&amp;w=600&amp;q=75" alt="" loading="lazy"></div>
        <div class="service-card-body">
        <div class="service-index">05</div>
        <h3>Digital resources</h3>
        <p>Journals, archives, and databases available on and off campus with your student account.</p>
        </div>
        </div>
        </div>
        </section>

        <!-- ========================= EDITORIAL STATEMENT ========================= -->
        <section class="editorial-statement" id="about" aria-label="Library statement">
        <p>Read widely.<br>Think deeply.</p>
        <span class="editorial-rule"></span>
        <p class="editorial-sub">A good library changes what you notice.</p>
        </section>

        <!-- ========================= FOOTER ========================= -->
        <footer class="site-footer">
        <div class="site-footer-top">
        <div class="site-footer-brand">
        <span class="mark">FEU Roosevelt</span>
        <span class="tag">Library</span>
        </div>
        <div class="site-footer-col">
        <div class="site-footer-label">Navigate</div>
        <button type="button" data-view="overview">Discover</button>
        <button type="button" data-view="books">Collection</button>
        <button type="button" data-anchor="services">Services</button>
        <button type="button" data-anchor="about">About</button>
        </div>
        <div class="site-footer-col">
        <div class="site-footer-label">Visit</div>
        <span id="footer-address"></span>
        <span id="footer-phone"></span>
        <span id="footer-email"></span>
        </div>
        </div>
        <div class="site-footer-bottom">
        <span>&copy; <span id="footer-year">2026</span> FEU Roosevelt Library. All rights reserved. Made by Mikhail D. Pabular - Kyro Carino - John Patrick De Luna - Dirk Martin Sotto</span>
        </div>
        </footer>
        </section>

        <section class="view reader-page" data-section="books" aria-label="Collection" hidden>
          <div class="collection-hero reader-collection-hero"><div class="collection-hero-top"><div class="collection-hero-copy"><div class="view-eyebrow">Reader collection</div><h1 class="collection-hero-heading">Explore the collection</h1><p class="collection-hero-sub">Find stories, ideas, and perspectives across the FEU Roosevelt Library.</p></div></div>
            <form id="book-search-form" class="collection-search-wrap reader-collection-search" role="search"><label class="collection-search" for="book-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg><input id="book-search" type="search" placeholder="Search titles, authors, or categories…"><button class="collection-search-clear" id="clear-book-search" type="button" aria-label="Clear search" hidden>×</button></label><button class="btn btn-primary" type="submit">Search</button></form>
          </div>
          <div class="collection-toolbar"><span class="collection-count" id="catalog-count"></span><span class="reader-catalog-hint">Request any title here. Library staff will review it and complete checkout at the desk.</span></div>
          <div id="catalog-grid" class="book-grid reader-book-grid"></div>
        </section>

        <section class="view reader-page" data-section="loans" aria-label="My loans" hidden>
          <div class="view-head"><div><div class="view-eyebrow">Your account</div><h1 class="view-heading">My loans</h1><p class="view-sub">See your due dates and return history. Fines are paid at the library desk.</p></div></div>
          <div class="reader-home-lists reader-loan-panels"><section class="panel"><div class="panel-head"><h2 class="panel-title">Currently borrowed</h2></div><div id="active-loans" class="reader-list"></div></section><section class="panel"><div class="panel-head"><h2 class="panel-title">Loan history</h2></div><div id="loan-history" class="reader-list"></div></section></div>
        </section>

        <section class="view reader-page" data-section="reservations" aria-label="My requests" hidden>
          <div class="view-head"><div><div class="view-eyebrow">Your account</div><h1 class="view-heading">My Requests</h1><p class="view-sub">Track borrowing requests and cancel a request while it is pending.</p></div></div>
          <section id="reservation-list" class="panel reader-reservation-list"></section>
        </section>

        <section class="view reader-page" data-section="account" aria-label="My account" hidden>
          <div class="view-head"><div><div class="view-eyebrow">Reader profile</div><h1 class="view-heading">My account</h1><p class="view-sub">Your library reader information.</p></div><button id="account-logout" class="btn btn-secondary" type="button">Sign out</button></div>
          <section class="panel reader-account-panel"><dl class="account-details"><div><dt>Name</dt><dd id="reader-account-name"></dd></div><div><dt>Username</dt><dd id="reader-account-username"></dd></div><div><dt>Email</dt><dd id="reader-account-email"></dd></div><div><dt>Library ID</dt><dd id="reader-account-id"></dd></div><div><dt>Program</dt><dd id="reader-account-program"></dd></div><div><dt>Year level</dt><dd id="reader-account-year"></dd></div></dl><p class="account-help">Need to update your details or password? Please ask library staff for help.</p></section>
          <section class="panel reader-account-panel reader-appearance-panel" aria-labelledby="reader-appearance-heading">
            <div class="panel-head"><h2 class="panel-title" id="reader-appearance-heading">Appearance</h2></div>
            <p class="account-help reader-appearance-lead">Choose how the library looks on this device. Your choice is saved in this browser.</p>
            <div class="theme-grid" id="theme-grid">
              <button class="theme-card" type="button" data-theme-pref="light" aria-pressed="false">
                <span class="theme-preview light"></span>
                <strong>Light Mode</strong>
                <span>Paper canvas, green type</span>
              </button>
              <button class="theme-card" type="button" data-theme-pref="dark" aria-pressed="false">
                <span class="theme-preview dark"></span>
                <strong>Dark Mode</strong>
                <span>Forest surfaces, gold accents</span>
              </button>
              <button class="theme-card" type="button" data-theme-pref="system" aria-pressed="false">
                <span class="theme-preview system"></span>
                <strong>System Default</strong>
                <span>Follows the device setting</span>
              </button>
            </div>
          </section>
        </section>

        <p id="reader-feedback" class="reader-feedback" role="status" aria-live="polite"></p>
      </main></div>
    </div>
  </div>
</body>
</html>