<?php
require __DIR__ . '/../config.php';

$method = requestMethod();
$actor = requireAuth();

if ($method === 'GET') {
    $userId = getQueryParam('user_id') ?: getQueryParam('userId');
    $bookId = getQueryParam('book_id') ?: getQueryParam('bookId');
    if (!isLibrarian($actor)) {
        $userId = $actor['patronId'] ?? '__none__';
    }
    $where = ["status = 'active'"];
    $params = [];
    if ($userId) {
        $where[] = 'user_id = ?';
        $params[] = $userId;
    }
    if ($bookId) {
        $where[] = 'book_id = ?';
        $params[] = $bookId;
    }
    [$limit, $offset] = paginationParams(200, 500);
    $total = countRows($pdo, 'SELECT COUNT(*) FROM reservations WHERE ' . implode(' AND ', $where), $params);
    $sql = 'SELECT * FROM reservations WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $rows = array_map(static function ($row) {
        return [
            'id' => $row['id'],
            'bookId' => $row['book_id'],
            'userId' => $row['user_id'],
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
        ];
    }, $stmt->fetchAll());
    sendResponse(['reservations' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
}

if ($method !== 'POST') {
    sendError('Method not allowed', 405);
}

$data = getJsonBody();
$action = $data['action'] ?? 'create';
$bookId = $data['bookId'] ?? $data['book_id'] ?? null;
$userId = $data['userId'] ?? $data['user_id'] ?? $actor['patronId'] ?? null;
if (!isLibrarian($actor)) {
    $userId = $actor['patronId'] ?? null;
}
if (!$bookId || !$userId) {
    sendError('Book and patron are required.', 400);
}

$pdo->beginTransaction();
try {
    if ($action === 'cancel') {
        $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE book_id = ? AND user_id = ? AND status = 'active'")
            ->execute([$bookId, $userId]);
        auditLog($pdo, 'reservation.cancel', 'book', (string)$bookId, ['userId' => (string)$userId]);
        $pdo->commit();
        sendResponse(['message' => 'Reservation cancelled.']);
    }

    $bookStmt = $pdo->prepare('SELECT * FROM books WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
    $bookStmt->execute([$bookId]);
    $book = $bookStmt->fetch();
    if (!$book) {
        $pdo->rollBack();
        sendError('Book not found', 404);
    }
    $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL');
    $userStmt->execute([$userId]);
    $patron = $userStmt->fetch();
    if (!$patron || $patron['status'] !== 'Active') {
        $pdo->rollBack();
        sendError('Patron is not eligible to reserve.', 400);
    }
    $dup = $pdo->prepare("SELECT id FROM reservations WHERE book_id = ? AND user_id = ? AND status = 'active' FOR UPDATE");
    $dup->execute([$bookId, $userId]);
    if ($dup->fetch()) {
        $pdo->rollBack();
        sendError('You already have an active reservation for this title.', 409);
    }
    $id = nextReservationId($pdo);
    $pdo->prepare('INSERT INTO reservations (id, book_id, user_id, status) VALUES (?, ?, ?, ?)')
        ->execute([$id, $bookId, $userId, 'active']);
    auditLog($pdo, 'reservation.create', 'book', (string)$bookId, ['reservationId' => $id, 'userId' => (string)$userId]);
    $pdo->commit();
    sendResponse(['message' => 'Borrow request sent to library staff.', 'id' => $id], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
