<?php
require 'db.php';

header('Content-Type: text/plain; charset=UTF-8');

// =========================
// 設定
// =========================
$apikey = getenv('GOOGLE_BOOKS_API_KEY') ?: ''; // .env で設定

// =========================
// 関数
// =========================

// Google Books を検索して items 配列を返す（失敗・0件なら空配列）
function googleSearch($q, $apikey, $max = 5) {
    $url = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($q)
         . "&maxResults=$max&key=" . urlencode($apikey);
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
    $res = @file_get_contents($url, false, $context);
    if ($res === false) return [];

    $data = json_decode($res, true);
    if (isset($data['error'])) {
        // キーの間違い・上限超えなどはログに残す（画面には出さない）
        error_log("Google Books APIエラー: " . $data['error']['code'] . " " . $data['error']['message']);
        return [];
    }
    return $data['items'] ?? [];
}

// Google の検索結果に指定ISBNが含まれているか
function hasIsbn($volumeInfo, $isbn13, $isbn10) {
    foreach ($volumeInfo['industryIdentifiers'] ?? [] as $id) {
        $v = strtoupper($id['identifier'] ?? '');
        if ($v === $isbn13 || ($isbn10 && $v === $isbn10)) return true;
    }
    return false;
}

// ISBN-10 → ISBN-13
function isbn10to13($isbn10) {
    $core = '978' . substr($isbn10, 0, 9);
    $sum = 0;
    for ($i = 0; $i < 12; $i++) $sum += (int)$core[$i] * ($i % 2 ? 3 : 1);
    return $core . ((10 - $sum % 10) % 10);
}

// ISBN-13 → ISBN-10（978始まりのみ）
function isbn13to10($isbn13) {
    if (substr($isbn13, 0, 3) !== '978') return null;
    $core = substr($isbn13, 3, 9);
    $sum = 0;
    for ($i = 0; $i < 9; $i++) $sum += (int)$core[$i] * (10 - $i);
    $check = (11 - $sum % 11) % 11;
    return $core . ($check === 10 ? 'X' : $check);
}

// URLの画像が実在するか
function imageExists($url) {
    $context = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 5]]);
    $headers = @get_headers($url, false, $context);
    return $headers && strpos($headers[0], '200') !== false;
}

// =========================
// 入力取得（JSON形式）
// =========================
$input = json_decode(file_get_contents('php://input'), true);
$isbn  = $input['isbn'] ?? null;
$force = $input['force'] ?? false; // 重複を無視して登録するフラグ

if (!$isbn) {
    http_response_code(400);
    echo "ISBNが見つかりません";
    exit;
}

// ISBN整形（数字とX以外を除去）・形式チェック
$isbn = strtoupper(preg_replace('/[^0-9X]/i', '', trim($isbn)));

if (preg_match('/^\d{9}[\dX]$/', $isbn)) {
    $isbn = isbn10to13($isbn); // 10桁は13桁にそろえる
} elseif (!preg_match('/^97[89]\d{10}$/', $isbn)) {
    echo "ISBNではありません（上段の978で始まるバーコードを読み取ってください）: $isbn";
    exit;
}
$isbn10 = isbn13to10($isbn);

$title = '';
$authors = '';
$image_path = '';

// =========================
// ① Google Books でISBN検索（書き方を変えて数回試す）
// =========================
$queries = ["isbn:$isbn", $isbn];
if ($isbn10) $queries[] = "isbn:$isbn10";

foreach ($queries as $q) {
    foreach (googleSearch($q, $apikey) as $item) {
        $book = $item['volumeInfo'];
        if (hasIsbn($book, $isbn, $isbn10)) {
            $title = $book['title'] ?? '';
            if (!empty($book['subtitle'])) $title .= ' : ' . $book['subtitle'];
            $authors = implode(', ', $book['authors'] ?? []);
            $image_path = $book['imageLinks']['thumbnail'] ?? '';
            break 2;
        }
    }
}

// =========================
// ② 見つからなければ 国立国会図書館サーチ
// =========================
if ($title === '') {
    $ndlUrl = "https://ndlsearch.ndl.go.jp/api/opensearch?isbn=" . urlencode($isbn);
    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $xmlStr = @file_get_contents($ndlUrl, false, $context);
    $xml = $xmlStr ? @simplexml_load_string($xmlStr) : false;

    if ($xml && isset($xml->channel->item[0])) {
        $item = $xml->channel->item[0];
        $dc = $item->children('http://purl.org/dc/elements/1.1/');
        $title = trim((string)$item->title);

        $creators = [];
        foreach ($dc->creator as $c) {
            $name = preg_replace('/,\s*\d{4}-(\d{4})?$/u', '', (string)$c); // 生没年を除去
            $creators[] = str_replace([', ', ','], '', $name);              // 「姓, 名」→「姓名」
        }
        if (!$creators && isset($item->author)) $creators[] = (string)$item->author;
        $authors = implode(', ', $creators);

        // ③ タイトル＋著者で Google を再検索し、ISBNが一致する本の表紙画像を取得
        $mainTitle = trim(preg_split('/\s*:\s*/u', $title)[0]);
        $q = "intitle:$mainTitle" . ($creators ? " inauthor:{$creators[0]}" : '');
        foreach (googleSearch($q, $apikey, 10) as $g) {
            if (hasIsbn($g['volumeInfo'], $isbn, $isbn10)) {
                $image_path = $g['volumeInfo']['imageLinks']['thumbnail'] ?? '';
                break;
            }
        }
    }
}

if ($title === '') {
    echo "書籍が見つかりません ISBN=$isbn";
    exit;
}

// =========================
// 画像がなければ NDL の表紙を試す
// =========================
if ($image_path === '') {
    $ndlImg = "https://ndlsearch.ndl.go.jp/thumbnail/$isbn.jpg";
    if (imageExists($ndlImg)) $image_path = $ndlImg;
}
$image_path = str_replace('http://', 'https://', $image_path);

// =========================
// ③ すでに登録済みかチェック
// =========================
if (!$force) {
    $stmt = $pdo->prepare("SELECT id FROM books WHERE title = ?");
    $stmt->execute([$title]);

    if ($stmt->fetch()) {
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
    echo "OK";
} catch (PDOException $e) {
    echo "DBエラー: " . $e->getMessage();
    exit;
}
