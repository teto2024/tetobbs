-- ===============================================
-- 時代IDの繰り上げスクリプト
-- 時代ID 9が欠番のため、ID 10以降のIDを1つずつ小さくする（-1）
-- 
-- 現状: ID 8 → ID 10 → ID 11 → ... （ID 9が欠番）
-- 目標: ID 8 → ID 9 → ID 10 → ... （ID 9の欠番を埋める）
-- ===============================================

USE syugetsu2025_clone;

-- 一時的に外部キー制約を無効化
SET FOREIGN_KEY_CHECKS = 0;

-- ステップ1: 関連テーブルの参照を一時保存して NULL に設定
-- 建物テーブル
CREATE TEMPORARY TABLE IF NOT EXISTS temp_building_eras (
    building_id BIGINT,
    old_era_id INT,
    new_era_id INT
);

INSERT INTO temp_building_eras (building_id, old_era_id, new_era_id)
SELECT id, unlock_era_id, unlock_era_id - 1
FROM civilization_building_types
WHERE unlock_era_id >= 10;

UPDATE civilization_building_types 
SET unlock_era_id = NULL 
WHERE unlock_era_id >= 10;

-- 研究テーブル
CREATE TEMPORARY TABLE IF NOT EXISTS temp_research_eras (
    research_id INT,
    old_era_id INT,
    new_era_id INT
);

INSERT INTO temp_research_eras (research_id, old_era_id, new_era_id)
SELECT id, era_id, era_id - 1
FROM civilization_researches
WHERE era_id >= 10;

UPDATE civilization_researches 
SET era_id = NULL 
WHERE era_id >= 10;

-- 兵種テーブル
CREATE TEMPORARY TABLE IF NOT EXISTS temp_troop_eras (
    troop_id INT,
    old_era_id INT,
    new_era_id INT
);

INSERT INTO temp_troop_eras (troop_id, old_era_id, new_era_id)
SELECT id, unlock_era_id, unlock_era_id - 1
FROM civilization_troop_types
WHERE unlock_era_id >= 10;

UPDATE civilization_troop_types 
SET unlock_era_id = NULL 
WHERE unlock_era_id >= 10;

-- ユーザー文明テーブル
CREATE TEMPORARY TABLE IF NOT EXISTS temp_user_eras (
    user_id INT,
    old_era_id INT,
    new_era_id INT
);

INSERT INTO temp_user_eras (user_id, old_era_id, new_era_id)
SELECT user_id, current_era_id, current_era_id - 1
FROM user_civilizations
WHERE current_era_id >= 10;

UPDATE user_civilizations 
SET current_era_id = NULL 
WHERE current_era_id >= 10;

-- ステップ2: 時代テーブルのIDを小さい順に更新（ID - 1）
-- 小さい順に更新することで重複を避ける
UPDATE civilization_eras SET id = 9 WHERE id = 10;
UPDATE civilization_eras SET id = 10 WHERE id = 11;
UPDATE civilization_eras SET id = 11 WHERE id = 12;
UPDATE civilization_eras SET id = 12 WHERE id = 13;
UPDATE civilization_eras SET id = 13 WHERE id = 14;
UPDATE civilization_eras SET id = 14 WHERE id = 15;
UPDATE civilization_eras SET id = 15 WHERE id = 16;
UPDATE civilization_eras SET id = 16 WHERE id = 17;
UPDATE civilization_eras SET id = 17 WHERE id = 18;
UPDATE civilization_eras SET id = 18 WHERE id = 19;
UPDATE civilization_eras SET id = 19 WHERE id = 20;
UPDATE civilization_eras SET id = 20 WHERE id = 21;
UPDATE civilization_eras SET id = 21 WHERE id = 22;
UPDATE civilization_eras SET id = 22 WHERE id = 23;
UPDATE civilization_eras SET id = 23 WHERE id = 24;
UPDATE civilization_eras SET id = 24 WHERE id = 25;
UPDATE civilization_eras SET id = 25 WHERE id = 26;

-- ステップ3: 関連テーブルの参照を新しいIDで復元
UPDATE civilization_building_types bt
JOIN temp_building_eras tbe ON bt.id = tbe.building_id
SET bt.unlock_era_id = tbe.new_era_id;

UPDATE civilization_researches cr
JOIN temp_research_eras tre ON cr.id = tre.research_id
SET cr.era_id = tre.new_era_id;

UPDATE civilization_troop_types ctt
JOIN temp_troop_eras tte ON ctt.id = tte.troop_id
SET ctt.unlock_era_id = tte.new_era_id;

UPDATE user_civilizations uc
JOIN temp_user_eras tue ON uc.user_id = tue.user_id
SET uc.current_era_id = tue.new_era_id;

-- 一時テーブルを削除
DROP TEMPORARY TABLE IF EXISTS temp_building_eras;
DROP TEMPORARY TABLE IF EXISTS temp_research_eras;
DROP TEMPORARY TABLE IF EXISTS temp_troop_eras;
DROP TEMPORARY TABLE IF EXISTS temp_user_eras;

-- AUTO_INCREMENTを調整（次のIDが正しく続くように）
ALTER TABLE civilization_eras AUTO_INCREMENT = 26;

-- 外部キー制約を再度有効化
SET FOREIGN_KEY_CHECKS = 1;

-- 確認
SELECT '✅ 時代IDの繰り上げが完了しました（ID 10以降を ID - 1 に更新）' AS status;
SELECT '' AS '';
SELECT 'ID 8〜12の時代一覧:' AS info;
SELECT id, era_key, name, era_order, unlock_population 
FROM civilization_eras 
WHERE id BETWEEN 8 AND 12 
ORDER BY id;
