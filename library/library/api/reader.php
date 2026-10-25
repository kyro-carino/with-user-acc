<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

$method = requestMethod();
$actor = requireAuth(['patron']);
$patronId = (string)($actor['patronId'] ?? '');
if ($patronId === '') {
    sendError('Reader profile is not linked to a library record.', 403);
}
$patronStmt = $pdo->prepare('SELECT id, first_name, last_name, program, year_level, status FROM users WHERE id = ? AND deleted_at IS NULL');
$patronStmt->execute([$patronId]);
$patron = $patronStmt->fetch();
if (!$patron || $patron['status'] !== 'Active') {
    sendError('This reader account is unavailable. Please contact the library.', 403);
}

function readerLoans(PDO $pdo, string $patronId): array {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? AND type = 'issue' ORDER BY created_at DESC, id DESC LIMIT 200");
    $stmt->execute([$patronId]);
    return array_map('mapTransaction', $stmt->fetchAll());
}

function readerReservations(PDO $pdo, string $patronId): array {
    $stmt = $pdo->prepare('SELECT r.id, r.book_id, r.status, r.created_at, b.title, b.author
        FROM reservations r LEFT JOIN books b ON b.id = r.book_id WHERE r.user_id = ?
        ORDER BY r.created_at DESC, r.id DESC LIMIT 200');
    $stmt->execute([$patronId]);
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['id'],
        'bookId' => (string)$row['book_id'],
        'title' => (string)($row['title'] ?? 'Removed book'),
        'author' => (string)($row['author'] ?? ''),
        'status' => (string)$row['status'],
        'createdAt' => (string)$row['created_at'],
    ], $stmt->fetchAll());
}

if ($method === 'GET') {
    $view = (string)getQueryParam('view', 'overview');
    if ($view === 'books') {
        [$limit, $offset] = paginationParams(50, 100);
        $search = likeTerm(getQueryParam('search'));
        $where = ['deleted_at IS NULL'];
        $params = [];
        if ($search) {
            $where[] = '(title LIKE ? ESCAPE \'\\\\\' OR author LIKE ? ESCAPE \'\\\\\' OR category LIKE ? ESCAPE \'\\\\\')';
            array_push($params, $search, $search, $search);
        }
        $whereSql = implode(' AND ', $where);
        $total = countRows($pdo, 'SELECT COUNT(*) FROM books WHERE ' . $whereSql, $params);
        $stmt = $pdo->prepare("SELECT b.id, b.title, b.author, b.category, b.year, b.location, b.status, b.due_date, b.cover_url,
                EXISTS(SELECT 1 FROM reservations r WHERE r.book_id = b.id AND r.user_id = ? AND r.status = 'active') AS requested
            FROM books b WHERE $whereSql ORDER BY b.title ASC, b.id ASC LIMIT ? OFFSET ?");
        $stmt->execute(array_merge([$patronId], $params, [$limit, $offset]));
        $books = array_map(static function (array $row): array {
            $status = effectiveLoanStatus((string)$row['status'], $row['due_date'] ?? null);
            if ($row['status'] === 'Available') $status = 'Available';
            $cover = (string)($row['cover_url'] ?? '');
            if ($cover !== '' && !preg_match('#^https://#i', $cover)) $cover = '';
            return [
                'id' => (string)$row['id'],
                'title' => (string)$row['title'],
                'author' => (string)$row['author'],
                'category' => (string)($row['category'] ?? 'General'),
                'year' => $row['year'] === null ? null : (int)$row['year'],
                'location' => (string)($row['location'] ?? ''),
                'status' => $status === 'Available' ? 'Available' : 'On loan',
                'cover' => $cover,
                'requested' => (bool)$row['requested'],
            ];
        }, $stmt->fetchAll());
        sendResponse(['books' => $books, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
    }

    if ($view === 'loans') {
        $loans = readerLoans($pdo, $patronId);
        sendResponse(['loans' => $loans]);
    }

    if ($view === 'reservations') {
        sendResponse(['reservations' => readerReservations($pdo, $patronId)]);
    }

    if ($view === 'account') {
        $authRow = loadAuthUser($pdo, (int)$actor['id']);
        sendResponse(['account' => [
            'username' => (string)$authRow['username'],
            'email' => (string)$authRow['email'],
            'displayName' => (string)$authRow['display_name'],
            'patronId' => $patronId,
            'program' => (string)($patron['program'] ?? ''),
            'yearLevel' => $patron['year_level'] === null ? null : (int)$patron['year_level'],
        ]]);
    }

    if ($view !== 'overview') {
        sendError('Unknown reader view.', 400);
    }
    $loans = readerLoans($pdo, $patronId);
    $reservations = readerReservations($pdo, $patronId);
    $activeLoans = array_values(array_filter($loans, static fn(array $loan): bool => in_array($loan['status'], ['Borrowed', 'Overdue'], true) && empty($loan['returnDate'])));
    usort($activeLoans, static fn(array $a, array $b): int => strcmp((string)$a['dueDate'], (string)$b['dueDate']));
    $unpaidFines = array_sum(array_map(static fn(array $loan): float => empty($loan['finePaid']) ? (float)$loan['fine'] : 0.0, $loans));
    $today = new DateTimeImmutable('today', new DateTimeZone(APP_TIMEZONE));
    $dueSoonUntil = $today->modify('+3 days')->format('Y-m-d');
    $account = loadAuthUser($pdo, (int)$actor['id']);
    sendResponse([
        'account' => [
            'username' => (string)$account['username'],
            'email' => (string)$account['email'],
            'displayName' => (string)$account['display_name'],
            'patronId' => $patronId,
        ],
        'summary' => [
            'activeLoans' => count($activeLoans),
            'dueSoon' => count(array_filter($activeLoans, static fn(array $loan): bool => $loan['status'] === 'Borrowed' && $loan['dueDate'] >= $today->format('Y-m-d') && $loan['dueDate'] <= $dueSoonUntil)),
            'activeReservations' => count(array_filter($reservations, static fn(array $hold): bool => $hold['status'] === 'active')),
            'unpaidFines' => round($unpaidFines, 2),
        ],
        'loans' => $activeLoans,
        'reservations' => $reservations,
    ]);
}

if ($method !== 'POST') {
    sendError('Method not allowed.', 405);
}

$data = getJsonBody();
$action = (string)($data['action'] ?? '');
if ($action === 'cancel_reservation') {
    $reservationId = trim((string)($data['reservationId'] ?? ''));
    if ($reservationId === '' || strlen($reservationId) > 20) {
        sendValidationError(['reservationId' => 'Choose a reservation to cancel.']);
    }
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare("SELECT book_id FROM reservations WHERE id = ? AND user_id = ? AND status = 'active' FOR UPDATE");
        $lock->execute([$reservationId, $patronId]);
        $reservation = $lock->fetch();
        if (!$reservation) {
            $pdo->rollBack();
            sendError('Active reservation not found.', 404);
        }
        $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND user_id = ?")->execute([$reservationId, $patronId]);
        auditLog($pdo, 'reservation.cancel', 'book', (string)$reservation['book_id'], ['reservationId' => $reservationId, 'userId' => $patronId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    sendResponse(['message' => 'Reservation cancelled.']);
}

if ($action === 'reserve') {
    $bookId = trim((string)($data['bookId'] ?? ''));
    if ($bookId === '' || strlen($bookId) > 20) {
        sendValidationError(['bookId' => 'Choose a book to reserve.']);
    }
    $pdo->beginTransaction();
    try {
        $bookStmt = $pdo->prepare('SELECT id, status FROM books WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $bookStmt->execute([$bookId]);
        $book = $bookStmt->fetch();
        if (!$book) {
            $pdo->rollBack();
            sendError('Book not found.', 404);
        }
        $duplicate = $pdo->prepare("SELECT id FROM reservations WHERE book_id = ? AND user_id = ? AND status = 'active' FOR UPDATE");
        $duplicate->execute([$bookId, $patronId]);
        if ($duplicate->fetch()) {
            $pdo->rollBack();
            sendError('You already have an active reservation for this book.', 409);
        }
        $reservationId = nextReservationId($pdo);
        $pdo->prepare('INSERT INTO reservations (id, book_id, user_id, status) VALUES (?, ?, ?, ?)')->execute([$reservationId, $bookId, $patronId, 'active']);
        auditLog($pdo, 'reservation.create', 'book', $bookId, ['reservationId' => $reservationId, 'userId' => $patronId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    sendResponse(['message' => 'Borrow request sent to library staff.', 'reservationId' => $reservationId], 201);
}

sendError('Unknown reader action.', 400);
