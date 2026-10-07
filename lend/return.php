<?php
require '../inside/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$book_id = $_GET['book_id'] ?? null;
if (!$book_id) {
    exit('invalid request');
}

$user_id = $_SESSION['user_id'];
$is_admin = ($user_id === 1); // 管理者判定

if ($is_admin) {
    // ===== 管理者：強制返却 =====
    $stmt = $pdo->prepare("
        UPDATE rentals
        SET returned_at = NOW()
        WHERE book_id = ?
          AND returned_at IS NULL
    ");
    $stmt->execute([$book_id]);

    header('Location: ../index.php');
    exit;

} else {
    // ===== 一般ユーザー：自分が借りた本のみ返却 =====
    $stmt = $pdo->prepare("
        UPDATE rentals
        SET returned_at = NOW()
        WHERE book_id = ?
          AND user_id = ?
          AND returned_at IS NULL
    ");
    $stmt->execute([$book_id, $user_id]);

    header('Location: ../index.php');
    exit;
}
