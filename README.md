# 書籍管理サイト

ISBN（バーコード）から本を登録し、本棚UIで貸出・返却を管理するサイトです。
書誌情報は Google Books API と国立国会図書館サーチから取得します。

## アカウント登録に必要なもの
* メアド
* パスワード

## セットアップ

```bash
# 1. 設定ファイルを作って値を入れる
cp .env.example .env

# 2. PHPライブラリをインストール
composer install

# 3. 起動
docker compose up -d
```

起動後、 http://localhost:18242 で開けます。

## 設定項目（.env）

| 名前 | 内容 |
|---|---|
| `GOOGLE_BOOKS_API_KEY` | Google Books API のキー |
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | データベース接続情報 |
| `MYSQL_ROOT_PASSWORD` | MySQL の root パスワード |
| `CF_TEAM_NAME` / `CF_AUD_TAG` | Cloudflare Access のチーム名と Application Audience (AUD) タグ |
| `APP_URL` | サイトのURL（ログアウト後の戻り先） |
