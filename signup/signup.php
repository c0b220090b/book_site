<?php
require '../inside/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $email = $_POST['email'];
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

  $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
  if ($stmt->execute([$username, $email, $password])) {
    echo "登録完了！<a href='../login/login.php'>ログインへ</a>";
    exit;
  } else {
    echo "登録失敗";
  }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>サインイン</title>
  <link href="https://unpkg.com/nes.css@latest/css/nes.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../style/styles.css">
</head>
<body>
  <h2>新規登録</h2>
  <form method="POST">
    ユーザー名: <input type="text" name="username" required><br>
    Email: <input type="email" name="email" required><br>
    パスワード: <input type="password" name="password" required><br>
    <button type="submit">登録</button>
  </form>
</body>
</html>