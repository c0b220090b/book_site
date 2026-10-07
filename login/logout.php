<?php
require '../inside/db.php';
require '../vendor/autoload.php';

// --- 1. Cloudflare 設定 (ここを書き換える) ---
define('CF_TEAM_NAME', getenv('CF_TEAM_NAME') ?: ''); // .env で設定
define('CF_AUD_TAG', getenv('CF_AUD_TAG') ?: '');       // .env で設定   

// session_start() は一度だけでOK
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = array();
session_destroy();

// 1. Cloudflare Access のログアウト用URL
$logout_base = "https://" . CF_TEAM_NAME . ".cloudflareaccess.com/cdn-cgi/access/logout";

// 2. ログアウト後に戻る先（ここを「認証が必要なページ」に設定する）
// login.php ではなく、トップページやダッシュボードなど、
// アクセスした瞬間に Cloudflare のログイン画面が出る URL を指定します。
$return_to = getenv('APP_URL') . "/index.php"; // .env で設定

// 3. リダイレクト
header("Location: " . $logout_base . "?return_to=" . urlencode($return_to));
exit;