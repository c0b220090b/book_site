-- データベースの初期化
USE book_db;

-- ユーザーテーブル
CREATE TABLE users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    password VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    PRIMARY KEY (id),
    KEY idx_username (username),
    KEY idx_email (email)
);

-- 書籍テーブル
CREATE TABLE books (
    id INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
    author VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
    image_path VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
    PRIMARY KEY (id)
);

-- 貸出テーブル
CREATE TABLE rentals (
    id INT(11) NOT NULL AUTO_INCREMENT,
    book_id INT(11) DEFAULT NULL,
    user_id INT(11) DEFAULT NULL,
    rented_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    returned_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_book_id (book_id),
    KEY idx_user_id (user_id)
);
-- 1. メインタグテーブル
CREATE TABLE main_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. サブタグテーブル
CREATE TABLE sub_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    main_tag_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    -- メインタグとの紐付け
    CONSTRAINT fk_main_tag
        FOREIGN KEY (main_tag_id) 
        REFERENCES main_tags(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. 書籍とサブタグの中間テーブル
CREATE TABLE book_sub_tags (
    book_id INT NOT NULL,
    sub_tag_id INT NOT NULL,
    -- 複合主キー（同じ本に同じタグを重複して登録できないようにする）
    PRIMARY KEY (book_id, sub_tag_id),
    -- 書籍テーブル（books）との紐付け（※booksテーブルが既に存在することを想定）
    CONSTRAINT fk_book
        FOREIGN KEY (book_id) 
        REFERENCES books(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    -- サブタグテーブルとの紐付け
    CONSTRAINT fk_sub_tag
        FOREIGN KEY (sub_tag_id) 
        REFERENCES sub_tags(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;