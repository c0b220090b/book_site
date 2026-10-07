<?php
require '../inside/db.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
$message = "";

// --- サブタグ追加処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub_tag_name'])) {
    $main_tag_id = $_POST['main_tag_id'] ?? null;
    $sub_tag_name = trim($_POST['sub_tag_name'] ?? '');

    if ($main_tag_id && $sub_tag_name !== '') {
        $check = $pdo->prepare("SELECT COUNT(*) FROM sub_tags WHERE main_tag_id = ? AND name = ?");
        $check->execute([$main_tag_id, $sub_tag_name]);
        $exists = $check->fetchColumn();

        if ($exists > 0) {
            $message = "⚠ '{$sub_tag_name}' は既に存在します。";
        } else {
            $stmt = $pdo->prepare("INSERT INTO sub_tags (main_tag_id, name) VALUES (?, ?)");
            $stmt->execute([$main_tag_id, $sub_tag_name]);
            $message = "✅ '{$sub_tag_name}' を追加しました。";
        }
    }
}

// --- メインタグ一覧取得 ---
$stmt = $pdo->query("SELECT id, name FROM main_tags ORDER BY id ASC");
$main_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 全サブタグ一覧取得 ---
$sql = "
    SELECT sub_tags.id, sub_tags.name AS sub_name, main_tags.name AS main_name, sub_tags.main_tag_id
    FROM sub_tags
    JOIN main_tags ON sub_tags.main_tag_id = main_tags.id
    ORDER BY main_tags.id ASC, sub_tags.id ASC
";
$tags = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Ajax対応 (動的フィルタリング用)
if (isset($_GET['ajax_main_tag_id'])) {
    $main_tag_id = (int)$_GET['ajax_main_tag_id'];
    $stmt = $pdo->prepare("SELECT id, name FROM sub_tags WHERE main_tag_id = ? ORDER BY id ASC");
    $stmt->execute([$main_tag_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>サブタグ管理</title>
    <link href="https://unpkg.com/nes.css/css/nes.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #212529; 
            color: white; 
            padding: 20px; 
            font-family: "MS Gothic", sans-serif;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            position: relative;
        }
        
        /* ヘッダーの重なり防止 */
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            position: relative;
            z-index: 100; /* 最前面に */
        }

        /* 全てのボタンのクリック判定を確実に */
        .nes-btn {
            cursor: pointer !important;
            pointer-events: auto !important;
            display: inline-block;
        }

        .form-box { 
            margin-bottom: 40px; 
            padding: 20px; 
            position: relative;
            z-index: 50;
        }
        
        .msg { 
            margin-bottom: 20px; 
            padding: 10px; 
            border: 4px solid; 
        }
        .msg-success { border-color: #2ecc71; color: #2ecc71; }
        .msg-error { border-color: #e74c3c; color: #e74c3c; }

        .table-section {
            position: relative;
            z-index: 10;
        }

        /* セレクトボックスのコンテナ */
        .nes-select {
            margin-top: 10px;
        }

        /* 戻るボタンのスタイル調整 */
        .back-link {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ヘッダーエリア -->
        <div class="header">
            <h2 class="nes-text is-primary" style="margin:0;">サブタグ管理</h2>
            <a href="../index.php" class="nes-btn back-link">戻る</a>
        </div>

        <!-- メッセージ表示 -->
        <?php if ($message): ?>
            <div class="msg <?= strpos($message, '✅') !== false ? 'msg-success' : 'msg-error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- サブタグ追加フォーム -->
        <div class="nes-container with-title is-dark form-box">
            <p class="title">サブタグの追加</p>
            <form method="POST">
                <div class="nes-field" style="margin-bottom: 20px;">
                    <label for="main_tag_id">メインタグ（ジャンル）</label>
                    <div class="nes-select">
                        <select name="main_tag_id" id="main_tag_id" required>
                            <option value="">-- ジャンルを選択 --</option>
                            <?php foreach ($main_tags as $tag): ?>
                                <option value="<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="nes-field" style="margin-bottom: 30px;">
                    <label for="sub_tag_name">新しいサブタグ名</label>
                    <input type="text" name="sub_tag_name" id="sub_tag_name" class="nes-input" placeholder="例: PHP, Pythonなど" required>
                </div>

                <div style="text-align: right;">
                    <button type="submit" class="nes-btn is-success">追加</button>
                </div>
            </form>
        </div>

        <!-- 登録済み一覧 -->
        <div class="table-section">
            <h3 class="nes-text">登録済みサブタグ一覧</h3>
            <div class="nes-table-responsive">
                <table class="nes-table is-bordered is-dark" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>メインジャンル</th>
                            <th>サブタグ名</th>
                        </tr>
                    </thead>
                    <tbody id="subtag-tbody">
                        <?php foreach ($tags as $tag): ?>
                            <tr>
                                <td><?= htmlspecialchars($tag['id']) ?></td>
                                <td><?= htmlspecialchars($tag['main_name']) ?></td>
                                <td><?= htmlspecialchars($tag['sub_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // メインタグ選択時の動的フィルタリング（Ajax）
    const mainSelect = document.getElementById('main_tag_id');
    const tbody = document.getElementById('subtag-tbody');

    mainSelect.addEventListener('change', async function() {
        const mainTagId = this.value;

        if (!mainTagId) {
            // ジャンルが選択されていない場合はページをリロードして全表示に戻す
            window.location.href = 'admin_tags.php';
            return;
        }

        try {
            const res = await fetch(`admin_tags.php?ajax_main_tag_id=${mainTagId}`);
            if (!res.ok) throw new Error('Network response was not ok');
            
            const data = await res.json();
            const mainName = this.options[this.selectedIndex].text;

            tbody.innerHTML = '';
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">データがありません</td></tr>';
            } else {
                data.forEach(tag => {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td>${tag.id}</td><td>${mainName}</td><td>${tag.name}</td>`;
                    tbody.appendChild(row);
                });
            }
        } catch (e) {
            console.error("Fetch error:", e);
        }
    });
    </script>
</body>
</html>