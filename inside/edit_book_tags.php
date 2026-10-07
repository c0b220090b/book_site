<?php
require 'db.php';

$book_id = filter_input(INPUT_GET, 'book_id', FILTER_VALIDATE_INT);
if (!$book_id) die("書籍IDが指定されていません。");

// 1. 書籍情報の取得
$stmt = $pdo->prepare("SELECT title FROM books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();
if (!$book) die("書籍が見つかりません。");

// 2. 現在その本に付いているサブタグIDのリストを取得
$stmt = $pdo->prepare("SELECT sub_tag_id FROM book_sub_tags WHERE book_id = ?");
$stmt->execute([$book_id]);
$current_tags = $stmt->fetchAll(PDO::FETCH_COLUMN); // IDだけの配列にする [1, 12, 16]

// 3. 全てのメインタグとサブタグを「親子構造」で取得
$sql = "SELECT m.name AS main_name, s.id AS sub_id, s.name AS sub_name 
        FROM main_tags m 
        JOIN sub_tags s ON m.id = s.main_tag_id 
        ORDER BY m.id, s.id";
$all_tags = $pdo->query($sql)->fetchAll(PDO::FETCH_GROUP); // main_nameでグループ化される
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>タグ編集: <?= htmlspecialchars($book['title']) ?></title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; color: #333; }
        .main-tag-group { margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 8px; }
        .main-tag-title { font-weight: bold; font-size: 1.1em; color: #2c3e50; border-bottom: 2px solid #3498db; margin-bottom: 10px; padding-bottom: 5px; }
        .sub-tag-item { display: inline-block; margin-right: 15px; cursor: pointer; }
        .btn-submit { background: #3498db; color: white; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; }
        .btn-submit:hover { background: #2980b9; }
        .footer { margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <h2>🏷️ タグ編集</h2>
    <p>書籍名: <strong><?= htmlspecialchars($book['title']) ?></strong></p>

    <form action="update_tags.php" method="POST">
        <input type="hidden" name="book_id" value="<?= $book_id ?>">

        <?php foreach ($all_tags as $main_name => $subs): ?>
            <div class="main-tag-group">
                <div class="main-tag-title"><?= htmlspecialchars($main_name) ?></div>
                <?php foreach ($subs as $sub): ?>
                    <label class="sub-tag-item">
                        <input type="checkbox" name="tag_ids[]" value="<?= $sub['sub_id'] ?>" 
                            <?= in_array($sub['sub_id'], $current_tags) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($sub['sub_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn-submit">この内容で更新する</button>
    </form>

    <div class="footer">
        <a href="admin_books.php">戻る</a>
    </div>
</body>
</html>
