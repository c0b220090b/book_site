<?php
require 'inside/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php");
    exit;
}

$my_user_id = $_SESSION['user_id'];

// --- 0. 自分が借りている本の取得 ---
$stmt_my = $pdo->prepare("
    SELECT books.* FROM books
    JOIN rentals ON books.id = rentals.book_id
    WHERE rentals.user_id = ? AND rentals.returned_at IS NULL
");
$stmt_my->execute([$my_user_id]);
$my_books = $stmt_my->fetchAll(PDO::FETCH_ASSOC);

// --- 1. 検索パラメータの取得 ---
$search_main = isset($_GET['main_tag']) ? (int)$_GET['main_tag'] : 0;
$search_sub  = isset($_GET['sub_tag']) ? (int)$_GET['sub_tag'] : 0;
$page        = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit       = 16;
$offset      = ($page - 1) * $limit;

// --- 2. 検索用のタグデータを取得 ---
$main_tags = $pdo->query("SELECT * FROM main_tags ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sub_tags_all = $pdo->query("SELECT * FROM sub_tags ORDER BY main_tag_id, id")->fetchAll(PDO::FETCH_ASSOC);
$sub_tags_json = [];
foreach ($sub_tags_all as $s) {
    $sub_tags_json[$s['main_tag_id']][] = $s;
}

// --- 3. 検索条件に合わせたSQLの構築 ---
$where_clauses = [];
$params = [];

if ($search_sub > 0) {
    $where_clauses[] = "books.id IN (SELECT book_id FROM book_sub_tags WHERE sub_tag_id = ?)";
    $params[] = $search_sub;
} elseif ($search_main > 0) {
    $where_clauses[] = "books.id IN (
        SELECT bst.book_id FROM book_sub_tags bst
        JOIN sub_tags st ON bst.sub_tag_id = st.id
        WHERE st.main_tag_id = ?
    )";
    $params[] = $search_main;
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

// --- 4. 総件数とページネーションの計算 ---
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM books" . $where_sql);
$total_stmt->execute($params);
$total_books = $total_stmt->fetchColumn();
$total_pages = ceil($total_books / $limit);

// --- 5. 本データの取得（貸出状況も1回のSQLでまとめて取得） ---
$sql = "SELECT books.*, r.user_id AS rental_user_id
        FROM books
        LEFT JOIN rentals r ON r.book_id = books.id AND r.returned_at IS NULL"
     . $where_sql
     . " ORDER BY books.id LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================
// 背表紙の描画
// =========================
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// 背表紙用にサブタイトルを落とす（「拡散モデル : データ生成…」→「拡散モデル」）
function spine_title($t) {
    return trim(preg_split('/\s*[:：]\s*/u', $t)[0]);
}

function render_spine($book, $rented, $owned) {
    $id = (int)$book['id'];
    // 本ごとに色・厚み・高さを変える（IDから決めるので毎回同じ見た目になる）
    $hue = ($id * 47) % 360;
    $sat = 28 + ($id * 7) % 22;
    $lig = 24 + ($id * 11) % 12;
    $w   = 42 + ($id * 13) % 20;
    $hgt = 178 + ($id * 29) % 54;

    $classes = 'spine';
    if ($rented && !$owned) $classes .= ' is-rented';
    if ($owned) $classes .= ' is-mine';

    $authors = explode(',', $book['author'] ?? '');
    $first_author = trim($authors[0]);
    ?>
    <div class="slot">
        <button type="button" class="<?= $classes ?>"
            style="--hue:<?= $hue ?>;--sat:<?= $sat ?>%;--lig:<?= $lig ?>%;--w:<?= $w ?>px;--h:<?= $hgt ?>px"
            data-book-id="<?= $id ?>"
            data-title="<?= h($book['title']) ?>"
            data-author="<?= h($book['author'] ?? '') ?>"
            data-img="<?= h($book['image_path'] ?? '') ?>"
            data-rented="<?= $rented ? 1 : 0 ?>"
            data-owned="<?= $owned ? 1 : 0 ?>"
            aria-label="<?= h($book['title']) ?>">
            <?php if ($owned): ?><span class="ribbon" aria-hidden="true"></span><?php endif; ?>
            <span class="spine-title"><?= h(spine_title($book['title'])) ?></span>
            <?php if ($first_author !== ''): ?>
                <span class="spine-author"><?= h($first_author) ?></span>
            <?php endif; ?>
            <?php if ($rented && !$owned): ?><span class="spine-tag">貸出中</span><?php endif; ?>
        </button>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>書籍一覧 - 本棚</title>
    <link href="https://unpkg.com/nes.css/css/nes.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Noto+Serif+JP:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wood-dark: #3b2212;
            --wood: #6e4220;
            --wood-light: #9a6435;
            --back: #1d120a;
            --gold: #f1c40f;
            --row: 248px;      /* 1段の高さ（本を置くスペース） */
            --plank: 22px;     /* 棚板の厚み */
        }
        body {
            font-family: "MS Gothic", sans-serif;
            background: url('bg_image/bg_mario.png') repeat-x;
            background-size: auto 100%;
            background-color: #222;
            color: white;
            margin: 0;
            padding: 2rem;
        }
        .nes-font, .title, .nes-btn, .nes-text {
            font-family: 'Press Start 2P', cursive, "MS Gothic", sans-serif;
        }
        .header-nav {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; flex-wrap: wrap; gap: 15px;
        }
        .admin-menu { display: flex; gap: 10px; flex-wrap: wrap; }
        .search-container { background-color: rgba(33, 37, 41, 0.9) !important; margin-bottom: 2rem; }
        .search-row { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; }
        .section-title {
            margin: 2rem 0 1rem; border-left: 10px solid #2ecc71; padding-left: 15px;
            text-shadow: 2px 2px 0 #000;
        }
        .section-title.is-warning { border-left-color: var(--gold); }

        /* =========================
           本棚
           ========================= */
        .bookcase {
            position: relative;
            padding: 18px 18px 0;
            background:
                linear-gradient(90deg, rgba(0,0,0,.35), transparent 6%, transparent 94%, rgba(0,0,0,.35)),
                repeating-linear-gradient(90deg, rgba(255,255,255,.015) 0 3px, transparent 3px 11px),
                var(--back);
            border: 16px solid var(--wood);
            border-top-width: 26px;
            border-image: linear-gradient(180deg, var(--wood-light), var(--wood) 30%, var(--wood-dark)) 1;
            box-shadow: 0 18px 40px rgba(0,0,0,.6), inset 0 0 60px rgba(0,0,0,.8);
        }
        .shelf {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            column-gap: 3px;
            row-gap: var(--plank);
            padding: 0 14px var(--plank);
            /* 1段ごとに「奥の影」と「棚板」を描く */
            background: repeating-linear-gradient(
                to bottom,
                rgba(0,0,0,.55) 0,
                transparent 34px,
                transparent calc(var(--row) - 10px),
                rgba(0,0,0,.45) var(--row),
                var(--wood-light) var(--row),
                var(--wood) calc(var(--row) + 6px),
                var(--wood-dark) calc(var(--row) + var(--plank))
            );
            min-height: calc(var(--row) + var(--plank));
        }
        .slot {
            height: var(--row);
            display: flex;
            align-items: flex-end;
        }
        .shelf-empty {
            height: var(--row); display: flex; align-items: center; padding: 0 1rem;
        }

        /* --- 背表紙 --- */
        .spine {
            all: unset;
            box-sizing: border-box;
            position: relative;
            width: var(--w);
            height: var(--h);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 22px 0 14px;
            border-radius: 3px 3px 1px 1px;
            color: #f4ead5;
            background:
                linear-gradient(90deg,
                    rgba(0,0,0,.45) 0%,
                    rgba(255,255,255,.12) 18%,
                    rgba(255,255,255,.04) 40%,
                    rgba(0,0,0,.08) 75%,
                    rgba(0,0,0,.5) 100%),
                hsl(var(--hue) var(--sat) var(--lig));
            box-shadow: 2px 0 3px rgba(0,0,0,.5), inset 0 -3px 0 rgba(0,0,0,.25);
            transition: transform .25s cubic-bezier(.2,.8,.2,1), box-shadow .25s, filter .25s;
        }
        /* 上下の金の帯 */
        .spine::before, .spine::after {
            content: "";
            position: absolute; left: 4px; right: 4px; height: 3px;
            background: linear-gradient(90deg, #8a6a1e, #e8c96a, #8a6a1e);
            opacity: .85;
        }
        .spine::before { top: 10px; box-shadow: 0 6px 0 -1px #c9a64a; }
        .spine::after  { bottom: 8px; }

        .spine:hover, .spine:focus-visible {
            transform: translateY(-16px);
            box-shadow: 4px 10px 14px rgba(0,0,0,.6);
            filter: brightness(1.15);
            z-index: 2;
        }
        .spine:focus-visible { outline: 3px solid var(--gold); outline-offset: 2px; }

        .spine-title {
            writing-mode: vertical-rl;
            font-family: 'Noto Serif JP', 'Hiragino Mincho ProN', 'Yu Mincho', 'Noto Serif CJK JP', serif;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .1em;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-height: calc(var(--h) - 92px);
            flex-shrink: 0;
            text-shadow: 0 1px 1px rgba(0,0,0,.6);
        }
        .spine-author {
            writing-mode: vertical-rl;
            margin-top: auto;
            font-family: 'Noto Serif JP', 'Hiragino Mincho ProN', 'Yu Mincho', 'Noto Serif CJK JP', serif;
            font-size: 10px;
            letter-spacing: .05em;
            white-space: nowrap;
            overflow: hidden;
            max-height: 58px;
            min-height: 0;
            flex-shrink: 1;
            opacity: .8;
        }
        /* 他の人が借りている本：少し奥に引っ込んで暗い */
        .spine.is-rented { filter: brightness(.55) saturate(.6); transform: translateY(2px) scale(.97); }
        .spine.is-rented:hover { filter: brightness(.8) saturate(.8); transform: translateY(-10px) scale(.97); }
        .spine-tag {
            position: absolute; bottom: 22px; left: 50%; transform: translateX(-50%);
            writing-mode: vertical-rl;
            font-size: 9px; padding: 4px 2px;
            background: #c0392b; color: #fff; border: 1px solid #fff;
        }
        /* 自分が借りている本：金のしおり */
        .ribbon {
            position: absolute; top: -4px; right: 6px;
            width: 8px; height: 34px;
            background: var(--gold);
            clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 80%, 0 100%);
            box-shadow: 0 0 6px var(--gold);
        }
        .spine.is-mine { box-shadow: 0 0 0 2px var(--gold), 2px 0 3px rgba(0,0,0,.5); }

        /* 取り出し中の本は棚から消える（すき間が残る） */
        .spine.is-out { visibility: hidden; }

        /* =========================
           取り出した本
           ========================= */
        .overlay {
            position: fixed; inset: 0; z-index: 1000;
            display: flex; justify-content: center; align-items: center;
            padding: 24px;
            background: rgba(12, 7, 3, .78);
            backdrop-filter: blur(4px);
            opacity: 0; pointer-events: none;
            transition: opacity .35s;
        }
        .overlay.open { opacity: 1; pointer-events: auto; }
        .stage {
            display: flex; align-items: center; gap: 48px;
            max-width: 900px; width: 100%;
            justify-content: center;
        }
        .stage-book {
            flex: none;
            width: min(240px, 50vw);
            aspect-ratio: 2 / 3;
        }
        .panel {
            flex: 1; max-width: 440px;
            background-color: rgba(33, 37, 41, .95) !important;
            opacity: 0; transform: translateX(24px);
            transition: opacity .35s .35s, transform .35s .35s;
        }
        .overlay.open .panel { opacity: 1; transform: none; }
        .panel h3 {
            font-family: 'Noto Serif JP', 'Hiragino Mincho ProN', 'Yu Mincho', 'Noto Serif CJK JP', serif; font-size: 1.4rem; line-height: 1.5;
            margin: 0 0 .5rem;
        }
        .panel .author { opacity: .8; margin-bottom: 1.2rem; }
        .panel .status { font-size: .8rem; line-height: 1.8; }
        .panel .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 1.2rem; }

        /* 飛んでくる本（背表紙 ⇄ 表紙） */
        .fly {
            position: fixed; z-index: 1001;
            transform-style: preserve-3d;
            pointer-events: none;
        }
        .fly .face {
            position: absolute; inset: 0;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            border-radius: 3px;
            overflow: hidden;
        }
        .fly .face.spine { width: 100%; height: 100%; cursor: default; transition: none; }
        .fly .cover {
            transform: rotateY(180deg);
            background: hsl(var(--hue) var(--sat) var(--lig));
            box-shadow: 0 25px 50px rgba(0,0,0,.7), inset 4px 0 6px rgba(0,0,0,.35);
        }
        .fly .cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
        /* 表紙画像がない本は文字で表紙を作る */
        .fly .cover-text {
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            height: 100%; padding: 20px; box-sizing: border-box; text-align: center;
            font-family: 'Noto Serif JP', 'Hiragino Mincho ProN', 'Yu Mincho', 'Noto Serif CJK JP', serif; color: #f4ead5;
            border: 6px double rgba(232, 201, 106, .7);
        }
        .fly .cover-text strong { font-size: 1.15rem; line-height: 1.5; }
        .fly .cover-text small { margin-top: 1rem; opacity: .8; }

        .pagination { margin-top: 30px; text-align: center; }

        @media (max-width: 720px) {
            body { padding: 1rem; }
            :root { --row: 220px; }
            .stage { flex-direction: column; gap: 20px; }
            .stage-book { width: min(170px, 45vw); }
            .panel { transform: translateY(16px); width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            .spine, .panel, .overlay { transition: none; }
        }
    </style>
</head>
<body>

<div class="header-nav">
    <h2 class="nes-text is-primary" style="margin:0;">📚 <a href="inside/admin_books.php" style="color:inherit; text-decoration:none;">書籍管理システム</a></h2>
    <div class="admin-menu">
        <a href="inside/index.html" class="nes-btn is-success">📖 本を追加</a>
        <a href="tag/add_sub_tag.php" class="nes-btn is-warning">🏷️ タグ管理</a>
    </div>
</div>

<div class="nes-container with-title is-dark search-container">
    <p class="title">SEARCH</p>
    <form method="GET" action="" id="searchForm" class="search-row">
        <div class="nes-select" style="width: 250px;">
            <label for="main_tag">ジャンル</label>
            <select id="main_tag" name="main_tag">
                <option value="0">すべて</option>
                <?php foreach ($main_tags as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $search_main == $m['id'] ? 'selected' : '' ?>>
                        <?= h($m['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="nes-select" style="width: 250px;">
            <label for="sub_tag">カテゴリー</label>
            <select id="sub_tag" name="sub_tag">
                <option value="0">すべて</option>
            </select>
        </div>
        <button type="submit" class="nes-btn is-primary">検索</button>
        <a href="index.php" class="nes-btn is-error">クリア</a>
        <a href="login/logout.php" class="nes-btn">ログアウト</a>
    </form>
</div>

<?php if (!empty($my_books) && $page == 1): ?>
    <h3 class="section-title nes-text is-warning">🎒 あなたが持ち出している本</h3>
    <div class="bookcase" style="margin-bottom: 3rem;">
        <div class="shelf">
            <?php foreach ($my_books as $book) render_spine($book, true, true); ?>
        </div>
    </div>
<?php endif; ?>

<h3 class="section-title nes-text">🌍 全ての書籍</h3>
<div class="bookcase">
    <div class="shelf">
        <?php if (empty($books)): ?>
            <p class="shelf-empty nes-text is-warning">対象の書籍が見つかりませんでした。</p>
        <?php endif; ?>
        <?php foreach ($books as $book):
            $rented = $book['rental_user_id'] !== null;
            $owned  = $rented && $book['rental_user_id'] == $my_user_id;
            render_spine($book, $rented, $owned);
        endforeach; ?>
    </div>
</div>

<div class="pagination">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php $query = http_build_query(array_merge($_GET, ['page' => $i])); ?>
        <a href="?<?= h($query) ?>" class="nes-btn <?= $i == $page ? 'is-disabled' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>

<!-- 取り出した本の表示エリア -->
<div class="overlay" id="bookOverlay" aria-hidden="true">
    <div class="stage">
        <div class="stage-book" id="stageBook"></div>
        <div class="panel nes-container is-dark with-title" role="dialog" aria-modal="true" aria-labelledby="pTitle">
            <p class="title">BOOK</p>
            <h3 id="pTitle"></h3>
            <p class="author" id="pAuthor"></p>
            <p class="status nes-text" id="pStatus"></p>
            <div class="actions">
                <span id="pButtons"></span>
                <button type="button" class="nes-btn" id="closeBtn">本棚に戻す</button>
            </div>
        </div>
    </div>
</div>

<script>
// ---------- タグ検索 ----------
const subTagsData = <?= json_encode($sub_tags_json) ?>;
const currentSubTag = <?= $search_sub ?>;
const mainSelect = document.getElementById('main_tag');
const subSelect = document.getElementById('sub_tag');

function updateSubTags() {
    const mainId = mainSelect.value;
    subSelect.innerHTML = '<option value="0">すべて</option>';
    if (mainId !== "0" && subTagsData[mainId]) {
        subTagsData[mainId].forEach(sub => {
            const option = document.createElement('option');
            option.value = sub.id;
            option.textContent = sub.name;
            if (sub.id == currentSubTag) option.selected = true;
            subSelect.appendChild(option);
        });
    }
}
mainSelect.addEventListener('change', updateSubTags);
window.addEventListener('load', updateSubTags);

// ---------- 本を棚から取り出す ----------
const overlay   = document.getElementById('bookOverlay');
const stageBook = document.getElementById('stageBook');
const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
let current = null; // { spine, fly, anim }

function esc(s) {
    return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function openBook(spine) {
    if (current) return;
    const d = spine.dataset;
    const rented = d.rented === '1';
    const owned  = d.owned === '1';

    // パネルの中身
    document.getElementById('pTitle').textContent  = d.title;
    document.getElementById('pAuthor').textContent = d.author ? '著：' + d.author : '';
    const status = document.getElementById('pStatus');
    status.className = 'status nes-text ' + (owned ? 'is-warning' : rented ? 'is-error' : 'is-success');
    status.textContent = owned ? '⭐ あなたが借りている本です。'
                       : rented ? '📕 他の人が冒険中です。'
                       : '📗 貸出可能です！';
    const btns = document.getElementById('pButtons');
    btns.innerHTML = '';
    if (!rented) {
        btns.innerHTML = `<a href="lend/rent.php?book_id=${encodeURIComponent(d.bookId)}" class="nes-btn is-primary">借りる</a>`;
    } else if (owned) {
        btns.innerHTML = `<a href="lend/return.php?book_id=${encodeURIComponent(d.bookId)}" class="nes-btn is-warning">返却する</a>`;
    }

    // 飛ばす本（表＝背表紙、裏＝表紙）を作る
    const from = spine.getBoundingClientRect();
    const fly = document.createElement('div');
    fly.className = 'fly';
    fly.style.cssText = spine.getAttribute('style');
    const coverInner = d.img
        ? `<img src="${esc(d.img)}" alt="">`
        : `<div class="cover-text"><strong>${esc(d.title)}</strong><small>${esc(d.author)}</small></div>`;
    fly.innerHTML = `
        <div class="face spine">${spine.innerHTML}</div>
        <div class="face cover">${coverInner}</div>`;
    // 画像が読めなかったら文字の表紙に差し替え
    const img = fly.querySelector('img');
    if (img) img.onerror = () => {
        img.outerHTML = `<div class="cover-text"><strong>${esc(d.title)}</strong><small>${esc(d.author)}</small></div>`;
    };
    document.body.appendChild(fly);

    // オーバーレイを出して、表紙の着地点を測る
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    const to = stageBook.getBoundingClientRect();

    spine.classList.add('is-out');

    const px = v => v + 'px';
    const anim = fly.animate([
        // 棚の位置
        { left: px(from.left), top: px(from.top), width: px(from.width), height: px(from.height),
          transform: 'perspective(1400px) rotateY(0deg)' },
        // 上に引き抜く
        { offset: 0.28, left: px(from.left), top: px(from.top - 40), width: px(from.width), height: px(from.height),
          transform: 'perspective(1400px) rotateY(0deg)' },
        // 回転しながら手前へ → 表紙を向ける
        { left: px(to.left), top: px(to.top), width: px(to.width), height: px(to.height),
          transform: 'perspective(1400px) rotateY(-180deg)' }
    ], {
        duration: reduceMotion ? 0 : 900,
        easing: 'cubic-bezier(.25,.8,.25,1)',
        fill: 'forwards'
    });

    current = { spine, fly, anim };
    document.getElementById('closeBtn').focus({ preventScroll: true });
}

function closeBook() {
    if (!current) return;
    const { spine, fly, anim } = current;
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    anim.reverse();
    anim.onfinish = () => {
        spine.classList.remove('is-out');
        fly.remove();
        document.body.style.overflow = '';
        spine.focus({ preventScroll: true });
        current = null;
    };
}

document.querySelectorAll('.shelf .spine').forEach(spine => {
    spine.addEventListener('click', () => openBook(spine));
});
document.getElementById('closeBtn').addEventListener('click', closeBook);
overlay.addEventListener('click', e => { if (e.target === overlay || e.target.classList.contains('stage')) closeBook(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeBook(); });
</script>
</body>
</html>
