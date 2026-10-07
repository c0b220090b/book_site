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

// ISBN整形（数字とX以外を除去）
$isbn = preg_replace('/[^0-9X]/i', '', trim($isbn));

if (!preg_match('/^(97[89]\d{10}|\d{9}[\dX])$/i', $isbn)) {
    echo "ISBNではありません: $isbn";
    exit;
}

// =========================
// ① 国立国会図書館サーチ API
// =========================
$apiUrl = "https://ndlsearch.ndl.go.jp/api/opensearch?isbn=" . urlencode($isbn);
$response = @file_get_contents($apiUrl);

if ($response === false) {
    echo "API取得失敗（通信エラー）";
    exit;
}

$xml = @simplexml_load_string($response);
if (!$xml || !isset($xml->channel->item[0])) {
    echo "書籍が見つかりません ISBN=$isbn";
    exit;
}

// =========================
// ② データ取得
// =========================
$item = $xml->channel->item[0];
$dc   = $item->children('http://purl.org/dc/elements/1.1/');

$title = (string)$item->title;

// 著者は複数ある場合があるのでまとめる
$creators = [];
foreach ($dc->creator as $c) {
    $creators[] = (string)$c;
}
$authors = $creators ? implode(', ', $creators) : (string)$item->author;

// 表紙画像（存在しない本もある）
$image_path = "https://ndlsearch.ndl.go.jp/thumbnail/$isbn.jpg";

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
