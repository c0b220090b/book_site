<?php
require 'db.php';

// POSTデータ取得（JSON形式）
$input = json_decode(file_get_contents('php://input'), true);
$isbn = $input['isbn'] ?? null;
$force = $input['force'] ?? false; // 重複を無視して登録するフラグ

if (!$isbn) {
    http_response_code(400);
    echo "ISBNが見つかりません";
    exit;
}

// ISBN整形（ハイフン除去）
$isbn = str_replace('-', '', trim($isbn));

// =========================
// ① Google Books API
// =========================
$apikey = getenv('GOOGLE_BOOKS_API_KEY') ?: ''; // .env で設定
$apiUrl = "https://www.googleapis.com/books/v1/volumes?q=isbn:$isbn&key=$apikey";
$response = @file_get_contents($apiUrl);


if ($response === false) {
    echo "API取得失敗（制限 or 通信エラー）";
    exit;
}

$data = json_decode($response, true);

if (empty($data['items'])) {
    echo "書籍が見つかりません";
    exit;
}

// =========================
// ② データ取得
// =========================
$book = $data['items'][0]['volumeInfo'];
$title = $book['title'] ?? '';
$authors = implode(', ', $book['authors'] ?? []);
$image_path = $book['imageLinks']['thumbnail'] ?? '';

// =========================
// ③ すでに登録済みかチェック
// =========================
// forceフラグが立っていない場合のみ重複チェックを行う
if (!$force) {
    $stmt = $pdo->prepare("SELECT id FROM books WHERE title = ?");
    $stmt->execute([$title]);

    if ($stmt->fetch()) {
        // JS側に「重複合図」と「タイトル」を返して終了
        echo "DUPLICATE:" . $title;
        exit;
    }
}

// =========================
// ④ DBに保存
// =========================
try {
    $stmt = $pdo->prepare("INSERT INTO books (title, author, image_path) VALUES (?, ?, ?)");
    $stmt->execute([$title, $authors, $image_path]);
    echo "OK"; // 成功の合図
    // exit;

} catch (PDOException $e) {
    echo "DBエラー: " . $e->getMessage();
    exit;
}
