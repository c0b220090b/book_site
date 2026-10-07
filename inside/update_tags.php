<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("不正なアクセスです。");

$book_id = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
$tag_ids = isset($_POST['tag_ids']) ? $_POST['tag_ids'] : [];

if (!$book_id) die("書籍IDが正しくありません。");

try {
    $pdo->beginTransaction();

    // 1. 一旦、その本の全タグ紐付けをリセット
    $stmt = $pdo->prepare("DELETE FROM book_sub_tags WHERE book_id = ?");
    $stmt->execute([$book_id]);

    // 2. 新しくチェックされたタグがあれば登録
    if (!empty($tag_ids)) {
        $sql = "INSERT INTO book_sub_tags (book_id, sub_tag_id) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        foreach ($tag_ids as $sub_id) {
            $stmt->execute([$book_id, (int)$sub_id]);
        }
    }

    $pdo->commit();
    // 成功したら一覧画面へ（book_idを渡すと強調表示などに使えます）
    header("Location: admin_books.php?success=1&updated_id=" . $book_id);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("エラーが発生しました: " . $e->getMessage());
}