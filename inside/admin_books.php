<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$is_admin = ($_SESSION['user_id'] === 1);

// SQL修正：タグ情報も一緒に取得するように拡張
$sql = "
  SELECT 
    books.id AS book_id,
    books.title,
    books.author,
    rentals.user_id AS renter_user_id,
    rentals.rented_at,
    rentals.returned_at,
    users.username,
    GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS tag_names
  FROM books
  LEFT JOIN rentals 
    ON books.id = rentals.book_id
    AND rentals.returned_at IS NULL
  LEFT JOIN users 
    ON rentals.user_id = users.id
  LEFT JOIN book_sub_tags bst ON books.id = bst.book_id
  LEFT JOIN sub_tags s ON bst.sub_tag_id = s.id
  GROUP BY books.id, books.title, books.author, rentals.user_id, rentals.rented_at, rentals.returned_at, users.username
  ORDER BY books.id
";

$stmt = $pdo->query($sql);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>書籍管理一覧</title>
  <link rel="stylesheet" href="../style/admin_styles.css">
  <style>
    /* ボタンとバッジのスタイル整理 */
    .btn {
        padding: 4px 8px;
        text-decoration: none;
        border-radius: 4px;
        font-size: 0.85em;
        display: inline-block;
        margin: 2px;
    }
    .btn-edit { background-color: #3498db; color: white; }
    .btn-edit:hover { background-color: #2980b9; }
    
    .btn-return { background-color: #e74c3c; color: white; }
    .btn-return:hover { background-color: #c0392b; }

    .tag-container { max-width: 200px; }
    .tag-badge {
        background: #f1f1f1;
        border: 1px solid #ccc;
        color: #666;
        padding: 2px 5px;
        border-radius: 3px;
        font-size: 0.75em;
        margin: 1px;
        display: inline-block;
    }
    .no-tag { color: #ccc; font-style: italic; font-size: 0.8em; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background-color: #f8f9fa; }
  </style>
</head>
<body>
  <h2 class="title">📚 書籍管理一覧</h2>
  <p><a href="../index.php">← 戻る</a></p>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>タイトル / 著者</th>
        <th>タグ</th> <th>貸出状況</th>
        <?php if ($is_admin): ?><th>操作</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($books as $book): ?>
        <tr>
          <td><?= $book['book_id'] ?></td>
          <td>
            <strong><?= htmlspecialchars($book['title']) ?></strong><br>
            <small style="color:#666;"><?= htmlspecialchars($book['author']) ?></small>
          </td>
          
          <td>
            <div class="tag-container">
              <?php if ($book['tag_names']): ?>
                <?php foreach (explode(', ', $book['tag_names']) as $tn): ?>
                  <span class="tag-badge"><?= htmlspecialchars($tn) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="no-tag">なし</span>
              <?php endif; ?>
            </div>
            <a href="edit_book_tags.php?book_id=<?= $book['book_id'] ?>" class="btn btn-edit">✎ 編集</a>
          </td>

          <td>
            <?php if ($book['rented_at'] && !$book['returned_at']): ?>
              <span style="color:#e74c3c;">📕 <?= htmlspecialchars($book['username']) ?> が貸出中</span><br>
              <small>(<?= htmlspecialchars($book['rented_at']) ?>〜)</small>
            <?php else: ?>
              <span style="color:#2ecc71;">📗 貸出可能</span>
            <?php endif; ?>
          </td>

          <?php if ($is_admin): ?>
            <td>
              <?php if ($book['rented_at'] && !$book['returned_at']): ?>
                <a href="../lend/return.php?book_id=<?= $book['book_id'] ?>"
                   class="btn btn-return"
                   onclick="return confirm('強制返却しますか？');">
                   強制返却
                </a>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>