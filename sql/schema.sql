-- =============================================
-- 洋菓子店 注文システム スキーマ（最新版）
-- 単店舗 / 顧客管理なし / 在庫管理なし / 画像アップロード対応 / 注文番号は日付＋4桁連番
-- =============================================

-- 商品マスタ
CREATE TABLE IF NOT EXISTS products (
  id             BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT '商品ID（自動採番）',
  name           VARCHAR(120) NOT NULL COMMENT '商品名',
  price_yen      INT NOT NULL COMMENT '税込価格（円・持ち帰り8%想定）',
  image_path     VARCHAR(255) COMMENT '商品画像の保存パス（例：/uploads/products/xxxx.jpg）',
  status         ENUM('selling','ended') NOT NULL DEFAULT 'selling' COMMENT '販売状態（selling:販売中／ended:販売終了）',
  display_order  INT NOT NULL DEFAULT 1000 COMMENT '表示順（小さいほど上に表示）',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登録日時',
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  INDEX(status),
  INDEX(display_order)
) ENGINE=InnoDB COMMENT='商品マスタ（画像アップロード対応）';

-- 注文（顧客管理なし・在庫管理なし）
CREATE TABLE IF NOT EXISTS orders (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT '注文ID（自動採番）',
  order_no      VARCHAR(20) NOT NULL UNIQUE COMMENT '注文番号（例: 250813-0001／日付＋4桁連番）',
  order_date    DATE NOT NULL COMMENT '注文日（受取日／通常は当日）',
  pickup_slot   TIME COMMENT '受取時間帯の目安（任意）',
  status        ENUM('confirmed','preparing','ready','picked_up','canceled')
                 NOT NULL DEFAULT 'confirmed' COMMENT '注文ステータス',
  items_total   INT NOT NULL COMMENT '合計数量',
  amount_yen    INT NOT NULL COMMENT '合計金額（税込）',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登録日時',
  INDEX(order_date),
  INDEX(status)
) ENGINE=InnoDB COMMENT='注文情報（単店舗・顧客情報なし・在庫管理なし）';

-- 注文アイテム
CREATE TABLE IF NOT EXISTS order_items (
  id         BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT '注文アイテムID',
  order_id   BIGINT NOT NULL COMMENT '注文ID（orders.id）',
  product_id BIGINT NOT NULL COMMENT '商品ID（products.id）',
  qty        INT NOT NULL COMMENT '数量',
  unit_price INT NOT NULL COMMENT '単価（税込・注文時点）',
  -- 先にインデックス
  INDEX idx_oi_order (order_id),
  INDEX idx_oi_product (product_id),
  -- 外部キー（制約名を付ける）
  CONSTRAINT fk_oi_order   FOREIGN KEY (order_id)   REFERENCES orders(id),
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB COMMENT='注文詳細（アイテム単位）';

-- 管理ユーザー
CREATE TABLE IF NOT EXISTS admin_users (
  id              BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'ユーザーID（自動採番）',
  username        VARCHAR(60) NOT NULL UNIQUE COMMENT 'ログインID（半角英数・記号）',
  password_hash   VARCHAR(255) NOT NULL COMMENT 'パスワードハッシュ（password_hashで生成）',
  role            ENUM('owner','staff') NOT NULL DEFAULT 'staff' COMMENT '権限（owner=全権／staff=一般）',
  is_active       TINYINT(1) NOT NULL DEFAULT 1 COMMENT '有効フラグ（1=有効／0=無効）',
  login_attempts  INT NOT NULL DEFAULT 0 COMMENT '連続失敗回数（ロック制御用）',
  locked_until    DATETIME DEFAULT NULL COMMENT 'ロック解除予定時刻（NULL=ロックなし）',
  last_login_at   DATETIME DEFAULT NULL COMMENT '最終ログイン日時',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'
) ENGINE=InnoDB COMMENT='管理ユーザー';

-- 初期ユーザー（admin / Admin@1234）※初回ログイン後に必ず変更
INSERT INTO admin_users (username, password_hash, role, is_active)
VALUES ('admin', '$2y$10$Jyw2vW3y0pV1G0e2oE7kOeK1lO9uIYxwQmFZ5p6tCO8b6M1m6XkDO', 'owner', 1)
ON DUPLICATE KEY UPDATE username=username;
