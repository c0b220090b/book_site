<?php
require '../inside/db.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login/login.php');
  exit;
}

$book_id = $_GET['book_id'] ?? null;

if ($book_id) {
  // すでに借りられていないか確認
  $stmt = $pdo->prepare("SELECT * FROM rentals WHERE book_id = ? AND returned_at IS NULL");
  $stmt->execute([$book_id]);
  $rental = $stmt->fetch();

  if (!$rental) {
    $stmt = $pdo->prepare("INSERT INTO rentals (book_id, user_id) VALUES (?, ?)");
    $stmt->execute([$book_id, $_SESSION['user_id']]);
  }
}

header('Location: ../index.php');
exit;
