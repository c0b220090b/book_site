<?php
require '../inside/db.php';
require '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

// session_start(); // db.php 内で開始されていない場合はコメントを外してください

// --- 1. Cloudflare 設定 (ここを書き換える) ---
define('CF_TEAM_NAME', getenv('CF_TEAM_NAME') ?: ''); // .env で設定
define('CF_AUD_TAG', getenv('CF_AUD_TAG') ?: '');       // .env で設定   

// --- 2. Cloudflare からの認証情報を取得 ---
$jwt = $_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'] ?? '';

if (empty($jwt)) {
    // JWTがない場合は、直接アクセスされたと判断
    $error = "Cloudflare Access経由のアクセスではありません。";
} else {
    try {
        // --- 3. 公開鍵を取得 ---
        $certs_url = "https://" . CF_TEAM_NAME . ".cloudflareaccess.com/cdn-cgi/access/certs";
        $jwks_json = @file_get_contents($certs_url);
        
        if ($jwks_json === false) {
            throw new Exception("Cloudflareの公開鍵が取得できません。チーム名「" . CF_TEAM_NAME . "」が正しいか確認してください。URL: " . $certs_url);
        }
        
        $jwks = json_decode($jwks_json, true);

        // --- 4. デコードと検証 ---
        // ここで失敗すると Exception が投げられます
        try {
            $decoded = JWT::decode($jwt, JWK::parseKeySet($jwks));
        } catch (Exception $jwtEx) {
            // デコード失敗時に、何が届いているか詳細を表示する（デバッグ用）
            $tks = explode('.', $jwt);
            $payload = json_decode(base64_decode($tks[1]), true);
            
            $debug_msg = "<div style='background:#f8d7da; color:#721c24; padding:15px; border:1px solid #f5c6cb; font-family:monospace;'>";
            $debug_msg .= "<strong>【デバッグ情報】</strong><br>";
            $debug_msg .= "Cloudflareから届いた本当のAUD: <span style='background:#fff; padding:2px;'>" . ($payload['aud'] ?? '不明') . "</span><br>";
            $debug_msg .= "PHPに設定されているCF_AUD_TAG: <span style='background:#fff; padding:2px;'>" . CF_AUD_TAG . "</span><br><br>";
            $debug_msg .= "エラー詳細: " . $jwtEx->getMessage();
            $debug_msg .= "</div>";
            
            die($debug_msg . "<br><a href='login.php'>再試行</a>");
        }

        // --- 5. ログイン処理 ---
        $email = $decoded->email;
        
        // DBからユーザーを検索
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // ログイン成功
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'] ?? 'Guest';
            header("Location: ../index.php");
            exit;
        } else {
            $error = "未登録のメールアドレスです: " . htmlspecialchars($email);
        }

    } catch (Exception $e) {
        $error = "認証エラー: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ログイン - Book System</title>
  <link href="https://unpkg.com/nes.css@latest/css/nes.min.css" rel="stylesheet" />
  <style>
    body { background-color: #212529; color: white; padding: 50px; }
    .container { max-width: 600px; margin: 0 auto; }
  </style>
</head>
<body>
  <div class="container nes-container is-dark with-title">
    <p class="title">LOGIN STATUS</p>
    
    <?php if (isset($error)): ?>
      <p class="nes-text is-error">⚠️ <?= htmlspecialchars($error) ?></p>
      
      <div class="nes-container is-rounded is-dark">
        <p>現在ログイン中のアドレス: <br>
           <span class="nes-text is-warning"><?= htmlspecialchars($email) ?></span>
        </p>
      </div>

      <div style="margin-top: 20px;">
        <p>新しくアカウントを作りますか？</p>
        <a href="../signup/signup.php" class="nes-btn is-success">アカウントを作成する</a>
      </div>

      <hr style="margin: 30px 0; border-color: #555;">

      <div style="margin-top: 20px;">
        <p>別のアカウント（Google等）に切り替えたい場合：</p>
        <a href="logout.php" class="nes-btn is-error">
          別のアカウントでログイン
        </a>
      </div>

    <?php else: ?>
      <p>認証を待機中...</p>
    <?php endif; ?>

    <br>
  </div>
</body>
</html>
