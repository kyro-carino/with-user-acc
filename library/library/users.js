(() => {
  const authEndpoint = 'api/user_auth.php';
  const readerEndpoint = 'api/reader.php';
  const authScreen = document.getElementById('reader-auth-screen');
  const app = document.getElementById('reader-app');
  const feedback = document.getElementById('reader-feedback');
  const signinTab = document.getElementById('signin-tab');
  const signupTab = document.getElementById('signup-tab');
  const signinPanel = document.getElementById('signin-panel');
  const signupPanel = document.getElementById('signup-panel');
  const menu = document.querySelector('.reader-menubar');
  let readerName = '';

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  }

  function dateLabel(value) {
    if (!value) return '—';
    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
  }

  function showMessage(message, isError = true) {
    feedback.textContent = message || '';
    feedback.classList.toggle('is-success', !isError);
    feedback.hidden = !message;
  }

  async function request(endpoint, payload) {
    const response = await fetch(endpoint, {
      method: payload ? 'POST' : 'GET',
      credentials: 'same-origin',
      headers: payload ? { 'Content-Type': 'application/json' } : {},
      body: payload ? JSON.stringify(payload) : undefined,
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
      const errors = result.errors ? Object.values(result.errors).join(' ') : '';
      throw new Error(errors || result.error || 'Something went wrong. Please try again.');
    }
    return result;
  }

  const readerRequest = (query = '', payload = null) => request(`${readerEndpoint}${query}`, payload);

  function showAuthError(id, message) {
    const error = document.getElementById(id);
    error.textContent = message;
    error.hidden = !message;
  }

  function showDashboard(user) {
    readerName = user.displayName || user.username;
    authScreen.hidden = true;
    app.hidden = false;
    document.getElementById('reader-menu-name').textContent = readerName;
    document.getElementById('reader-menu-user').textContent = `@${user.username}`;
    const initials = readerName.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase() || 'R';
    document.getElementById('reader-avatar').textContent = initials;
    document.getElementById('reader-menu-avatar').textContent = initials;
    showMessage('');
    loadSection('overview');
  }

  function showForms() {
    app.hidden = true;
    authScreen.hidden = false;
    document.getElementById('reader-profile-menu').hidden = true;
    document.getElementById('reader-avatar-btn').setAttribute('aria-expanded', 'false');
    showAuthError('reader-login-error', '');
    showAuthError('reader-signup-error', '');
  }

  function switchTab(tab) {
    const signup = tab === 'signup';
    signinTab.classList.toggle('is-active', !signup);
    signupTab.classList.toggle('is-active', signup);
    signinTab.setAttribute('aria-selected', String(!signup));
    signupTab.setAttribute('aria-selected', String(signup));
    signinPanel.hidden = signup;
    signupPanel.hidden = !signup;
    showAuthError('reader-login-error', '');
    showAuthError('reader-signup-error', '');
  }

  function bookCard(book, index, reserve = true, plain = false) {
    const available = book.status === 'Available';
    const tone = index % 5;
    const cover = /^https:\/\//i.test(book.cover || '') ? `<img class="book-card-cover" src="${escapeHtml(book.cover)}" alt="" loading="lazy">` : '';
    const availability = available ? 'Available' : 'On loan';
    const badge = available ? 'badge-available' : 'badge-borrowed';
    return `<article class="book-card reader-book-card">
      <div class="book-card-face reader-book-tone-${tone}">${cover}<span class="book-card-availability-pill ${badge}">${availability}</span><span class="book-card-initial" aria-hidden="true">${escapeHtml((book.title || 'Book').slice(0, 1))}</span></div>
      <div class="book-card-body"><div class="book-card-top-row"><span class="book-card-cat">${escapeHtml(book.category)}</span><span class="book-card-dot ${badge}" aria-label="${availability}"></span></div>
        <h3 class="book-card-title">${escapeHtml(book.title)}</h3><p class="book-card-author">${escapeHtml(book.author)}${!plain && book.year ? ` · ${escapeHtml(book.year)}` : ''}</p>
        ${plain ? '' : book.requested ? '<p class="reader-book-note">Your request is pending with library staff.</p>' : reserve ? `<button class="btn btn-secondary btn-sm reader-reserve-button" type="button" data-reserve="${escapeHtml(book.id)}">Request to borrow</button>` : available ? '<p class="reader-book-note">Request this title and library staff will help with checkout.</p>' : ''}
      </div></article>`;
  }

  function rowMarkup(title, detail, side = '', badge = '') {
    return `<article class="reader-row"><div class="row-main"><strong>${escapeHtml(title)}</strong><span>${escapeHtml(detail)}</span></div><div class="row-side">${badge ? `<span class="status-badge ${badge === 'Overdue' ? 'is-overdue' : ''}">${escapeHtml(badge)}</span>` : ''}${side ? `<span>${escapeHtml(side)}</span>` : ''}</div></article>`;
  }

  const emptyMarkup = message => `<div class="reader-empty">${escapeHtml(message)}</div>`;

  // Same rule as the staff Discover page: one title from each of the first five categories.
  function renderOverview(books) {
    const seen = new Set();
    const featured = [];
    for (const book of books) {
      const category = book.category || 'Uncategorized';
      if (!seen.has(category)) { seen.add(category); featured.push(book); }
      if (featured.length >= 5) break;
    }
    document.getElementById('featured-grid').innerHTML = featured.map((book, index) => bookCard(book, index, false, true)).join('') || emptyMarkup('The catalog is empty right now.');
  }

  let libraryInfoLoaded = false;

  // Same library details the staff Discover page shows (rules, address, phone, email), read from the saved settings.
  async function loadLibraryInfo() {
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || ''; };
    setText('footer-year', String(new Date().getFullYear()));
    if (libraryInfoLoaded) return;
    try {
      const { settings } = await request('api/settings.php');
      const max = Number(settings.maxBooksPerUser), days = Number(settings.loanDays);
      if (max && days) setText('svc-borrowing-text', `Take home up to ${max} ${max === 1 ? 'title' : 'titles'} at a time, with a ${days}-day loan period.`);
      const contact = [settings.libraryAddress, settings.libraryPhone, settings.libraryEmail].filter(Boolean);
      if (contact.length) setText('svc-contact-text', contact.join(' \u00b7 '));
      setText('footer-address', settings.libraryAddress);
      setText('footer-phone', settings.libraryPhone);
      setText('footer-email', settings.libraryEmail);
      libraryInfoLoaded = true;
    } catch (error) {
      // The default text in the page stays if the settings cannot be read.
    }
  }

  async function loadOverview() {
    loadLibraryInfo();
    const books = await readerRequest('?view=books&limit=100');
    renderOverview(books.books);
  }

  function renderBooks(books, total) {
    document.getElementById('catalog-count').textContent = `${total} ${total === 1 ? 'book' : 'books'} in the collection`;
    document.getElementById('catalog-grid').innerHTML = books.length
      ? books.map(bookCard).join('')
      : `<div class="collection-empty"><div class="t">No books found</div><div class="s">Try another title, author, or category.</div></div>`;
  }

  async function loadBooks() {
    const query = document.getElementById('book-search').value.trim();
    const data = await readerRequest(`?view=books&limit=100&search=${encodeURIComponent(query)}`);
    renderBooks(data.books, data.total);
  }

  function renderLoans(loans) {
    const open = loans.filter(loan => !loan.returnDate && ['Borrowed', 'Overdue'].includes(loan.status));
    const history = loans.filter(loan => !open.includes(loan));
    document.getElementById('active-loans').innerHTML = open.length ? open.map(loan => {
      const fine = Number(loan.fine || 0) > 0 ? `Fine assessed: ₱${Number(loan.fine).toFixed(2)}` : `Due ${dateLabel(loan.dueDate)}`;
      return rowMarkup(loan.bookTitle, `Issued ${dateLabel(loan.issueDate)} · ${fine}`, `Due ${dateLabel(loan.dueDate)}`, loan.status);
    }).join('') : emptyMarkup('You have no active loans.');
    document.getElementById('loan-history').innerHTML = history.length ? history.map(loan => {
      const fine = Number(loan.fine || 0) > 0 ? `Fine ₱${Number(loan.fine).toFixed(2)}${loan.finePaid ? ' · paid' : ' · unpaid'}` : `Returned ${dateLabel(loan.returnDate)}`;
      return rowMarkup(loan.bookTitle, `Issued ${dateLabel(loan.issueDate)} · ${fine}`, dateLabel(loan.returnDate), loan.status);
    }).join('') : emptyMarkup('Your loan history will appear here.');
  }

  async function loadLoans() {
    const data = await readerRequest('?view=loans');
    renderLoans(data.loans);
  }

  function reservationMarkup(item) {
    const active = item.status === 'active';
    return `<article class="reader-row"><div class="row-main"><strong>${escapeHtml(item.title)}</strong><span>${escapeHtml(item.author || '')} · Requested ${dateLabel(item.createdAt?.slice(0, 10))}</span></div><div class="row-side"><span class="status-badge">${escapeHtml(active ? 'Pending' : item.status)}</span>${active ? `<button class="btn btn-secondary btn-sm" type="button" data-cancel="${escapeHtml(item.id)}">Cancel request</button>` : ''}</div></article>`;
  }

  async function loadReservations() {
    const data = await readerRequest('?view=reservations');
    document.getElementById('reservation-list').innerHTML = data.reservations.length
      ? data.reservations.map(reservationMarkup).join('')
      : emptyMarkup('No borrow requests yet. Choose a title in Collection to request it.');
  }

  async function loadAccount() {
    const data = await readerRequest('?view=account');
    const account = data.account;
    const fields = {
      'reader-account-name': account.displayName,
      'reader-account-username': account.username,
      'reader-account-email': account.email,
      'reader-account-id': account.patronId,
      'reader-account-program': account.program || 'Not provided',
      'reader-account-year': account.yearLevel ? `Year ${account.yearLevel}` : 'Not provided',
    };
    Object.entries(fields).forEach(([id, value]) => { document.getElementById(id).textContent = value; });
  }

  // Dropdown groups in the menu bar (Transactions), same behaviour as the staff menu.
  function closeNavDrops() {
    document.querySelectorAll('.reader-menubar .menu-drop').forEach(drop => {
      drop.classList.remove('open');
      drop.querySelector(':scope > .menu-link').setAttribute('aria-expanded', 'false');
    });
  }

  async function loadSection(view) {
    document.querySelectorAll('.reader-menubar [data-view]').forEach(button => {
      const active = button.dataset.view === view;
      button.classList.toggle('active', active);
      if (active) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
    });
    document.querySelectorAll('[data-section]').forEach(section => {
      const active = section.dataset.section === view;
      section.hidden = !active;
      section.classList.toggle('active', active);
    });
    document.querySelectorAll('.reader-menubar .menu-drop').forEach(drop => {
      drop.querySelector(':scope > .menu-link').classList.toggle('active', Boolean(drop.querySelector(`[data-view="${view}"]`)));
    });
    closeNavDrops();
    menu.classList.remove('open');
    document.getElementById('reader-hamburger').setAttribute('aria-expanded', 'false');
    document.getElementById('reader-profile-menu').hidden = true;
    document.getElementById('reader-avatar-btn').setAttribute('aria-expanded', 'false');
    showMessage('');
    try {
      if (view === 'overview') await loadOverview();
      if (view === 'books') await loadBooks();
      if (view === 'loans') await loadLoans();
      if (view === 'reservations') await loadReservations();
      if (view === 'account') await loadAccount();
    } catch (error) {
      showMessage(error.message);
    }
  }

  async function submitAuth(form, action, errorId) {
    showAuthError(errorId, '');
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      const result = await request(authEndpoint, { action, ...values });
      form.reset();
      showDashboard(result.user);
    } catch (error) {
      showAuthError(errorId, error.message);
    } finally {
      button.disabled = false;
    }
  }

  async function signOut() {
    try {
      await request(authEndpoint, { action: 'logout' });
      showForms();
      switchTab('signin');
    } catch (error) {
      showMessage(error.message);
    }
  }

  function searchCatalog(value) {
    document.getElementById('book-search').value = value.trim();
    loadSection('books');
  }

  signinTab.addEventListener('click', () => switchTab('signin'));
  signupTab.addEventListener('click', () => switchTab('signup'));
  document.getElementById('signin-form').addEventListener('submit', event => { event.preventDefault(); submitAuth(event.currentTarget, 'login', 'reader-login-error'); });
  document.getElementById('signup-form').addEventListener('submit', event => { event.preventDefault(); submitAuth(event.currentTarget, 'signup', 'reader-signup-error'); });
  document.querySelectorAll('.reader-menubar .menu-drop > .menu-link').forEach(link => {
    const drop = link.parentElement;
    link.addEventListener('click', event => {
      event.stopPropagation();
      const willOpen = !drop.classList.contains('open');
      closeNavDrops();
      if (willOpen) { drop.classList.add('open'); link.setAttribute('aria-expanded', 'true'); }
    });
    drop.addEventListener('focusout', event => {
      if (event.relatedTarget && drop.contains(event.relatedTarget)) return;
      drop.classList.remove('open');
      link.setAttribute('aria-expanded', 'false');
    });
  });
  document.addEventListener('click', closeNavDrops);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeNavDrops(); });

  document.querySelectorAll('button[data-view]').forEach(button => button.addEventListener('click', () => loadSection(button.dataset.view)));
  // In-page anchors (Services / About / Learn more) live on Discover; go there first when needed.
  document.querySelectorAll('[data-anchor]').forEach(button => button.addEventListener('click', async () => {
    const jump = () => document.getElementById(button.dataset.anchor)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (document.querySelector('[data-section="overview"]').classList.contains('active')) { jump(); return; }
    await loadSection('overview');
    requestAnimationFrame(jump);
  }));
  document.getElementById('hero-search-form').addEventListener('submit', event => { event.preventDefault(); searchCatalog(document.getElementById('hero-search-input').value); });
  document.getElementById('book-search-form').addEventListener('submit', event => { event.preventDefault(); loadBooks().catch(error => showMessage(error.message)); });
  document.getElementById('global-search-form').addEventListener('submit', event => { event.preventDefault(); searchCatalog(document.getElementById('global-search-input').value); });
  document.getElementById('clear-book-search').addEventListener('click', () => { document.getElementById('book-search').value = ''; loadBooks().catch(error => showMessage(error.message)); });
  document.getElementById('book-search').addEventListener('input', event => { document.getElementById('clear-book-search').hidden = !event.target.value; });
  document.querySelectorAll('#featured-grid, #catalog-grid').forEach(grid => grid.addEventListener('click', async event => {
    const button = event.target.closest('[data-reserve]');
    if (!button) return;
    button.disabled = true;
    try {
      const result = await readerRequest('', { action: 'reserve', bookId: button.dataset.reserve });
      showMessage(result.message, false);
      if (grid.id === 'featured-grid') await loadOverview(); else await loadBooks();
    } catch (error) {
      showMessage(error.message);
    } finally {
      button.disabled = false;
    }
  }));
  document.getElementById('reservation-list').addEventListener('click', async event => {
    const button = event.target.closest('[data-cancel]');
    if (!button) return;
    button.disabled = true;
    try {
      const result = await readerRequest('', { action: 'cancel_reservation', reservationId: button.dataset.cancel });
      showMessage(result.message, false);
      await loadReservations();
    } catch (error) {
      showMessage(error.message);
    } finally {
      button.disabled = false;
    }
  });
  document.getElementById('reader-avatar-btn').addEventListener('click', event => {
    const popup = document.getElementById('reader-profile-menu');
    popup.hidden = !popup.hidden;
    event.currentTarget.setAttribute('aria-expanded', String(!popup.hidden));
  });
  document.getElementById('reader-hamburger').addEventListener('click', event => {
    const open = menu.classList.toggle('open');
    event.currentTarget.setAttribute('aria-expanded', String(open));
  });
  document.getElementById('reader-logout').addEventListener('click', signOut);
  document.getElementById('account-logout').addEventListener('click', signOut);
  document.addEventListener('click', event => {
    const pop = document.getElementById('reader-profile-menu');
    const host = document.querySelector('.reader-account-menu');
    if (!host.contains(event.target) && !pop.hidden) {
      pop.hidden = true;
      document.getElementById('reader-avatar-btn').setAttribute('aria-expanded', 'false');
    }
  });

  // ---- Colour theme (light / dark / system): same storage key and behaviour as the staff portal ----
  const THEME_KEY = 'feu-theme';
  const darkQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : { matches: false };
  function storedTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'light'; } catch (error) { return 'light'; }
  }
  function applyTheme(pref, persist = true) {
    if (!['light', 'dark', 'system'].includes(pref)) pref = 'light';
    const dark = pref === 'dark' || (pref === 'system' && darkQuery.matches);
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme-pref', pref);
    if (persist) { try { localStorage.setItem(THEME_KEY, pref); } catch (error) { /* private mode: theme applies for this visit only */ } }
    document.querySelectorAll('.theme-card').forEach(card => {
      const active = card.dataset.themePref === pref;
      card.classList.toggle('active', active);
      card.setAttribute('aria-pressed', String(active));
    });
    const toggle = document.getElementById('reader-theme-toggle');
    if (toggle) toggle.textContent = dark ? 'Light Mode' : 'Dark Mode';
  }
  applyTheme(storedTheme(), false);
  document.querySelectorAll('.theme-card').forEach(card => card.addEventListener('click', () => applyTheme(card.dataset.themePref)));
  document.getElementById('reader-theme-toggle').addEventListener('click', () => {
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    document.getElementById('reader-profile-menu').hidden = true;
    document.getElementById('reader-avatar-btn').setAttribute('aria-expanded', 'false');
  });
  if (darkQuery.addEventListener) darkQuery.addEventListener('change', () => { if (storedTheme() === 'system') applyTheme('system', false); });
  window.addEventListener('storage', event => { if (event.key === THEME_KEY) applyTheme(event.newValue || 'light', false); });

  request(authEndpoint).then(result => {
    if (result.authenticated && result.user) showDashboard(result.user);
  }).catch(() => {});
})();