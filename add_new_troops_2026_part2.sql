-- ===============================================
-- 新規兵種追加 2026 Part 2
-- 海軍戦闘機、艦上レーザー兵器、対戦キャノン砲、投石兵、巡航ミサイル、防護兵
-- ===============================================

USE microblog;

-- ===============================================
-- ① 新スキルの追加
-- ===============================================

INSERT IGNORE INTO battle_special_skills (skill_key, name, icon, description, effect_type, effect_target, effect_value, duration_turns, activation_chance) VALUES
-- 爆弾投下：海カテゴリに与えるダメージ倍増（発動率100%）
('bomb_drop', '爆弾投下', '💣', '海カテゴリに与えるダメージを2倍にする', 'special', 'enemy', 100, 1, 100),

-- レーザー照射：空カテゴリに与えるダメージ倍増（発動率100%）
('laser_irradiation', 'レーザー照射', '🔦', '空カテゴリに与えるダメージを2倍にする', 'special', 'enemy', 100, 1, 100),

-- 散弾発射：陸カテゴリに与えるダメージ倍増（発動率100%）
('shrapnel_fire', '散弾発射', '💥', '陸カテゴリに与えるダメージを2倍にする', 'special', 'enemy', 100, 1, 100),

-- 投石：アーマー貫通ダメージ（発動率15%）
('stone_throw', '投石', '🪨', '15%の確率でアーマーを貫通してダメージを与える', 'special', 'enemy', 100, 1, 15),

-- 自律飛行：3回連続攻撃（発動率10%）
('autonomous_flight', '自律飛行', '🚀', '10%の確率で1ターンに3回連続攻撃する', 'special', 'self', 3, 1, 10),

-- 核武装解除：核カテゴリに大ダメージ（発動率20%）
('nuclear_disarm', '核武装解除', '☢️', '核カテゴリのユニットに対して大ダメージを与える', 'special', 'enemy', 100, 1, 20);

-- ===============================================
-- ② 現代時代（era_order = 7）の新兵種
-- ===============================================

INSERT IGNORE INTO civilization_troop_types (
    troop_key, name, icon, description, unlock_era_id, 
    attack_power, defense_power, health_points, troop_category, domain_category,
    train_cost_coins, train_cost_resources, train_time_seconds,
    heal_time_seconds, heal_cost_coins, special_skill_id
) VALUES
-- 海軍戦闘機：空・攻城・爆弾投下
('naval_fighter', '海軍戦闘機', '✈️', '海上目標を攻撃する戦闘機。爆弾投下スキルで海カテゴリに大ダメージ【空・攻城】',
    7, 190, 65, 170, 'siege', 'air',
    21000, '{"iron": 420, "oil": 160, "electronics": 55}', 4200,
    155, 115, (SELECT id FROM battle_special_skills WHERE skill_key = 'bomb_drop' LIMIT 1)),

-- 艦上レーザー兵器：海・攻城・レーザー照射
('ship_laser_weapon', '艦上レーザー兵器', '🔦', 'レーザー兵器を搭載した艦船。レーザー照射スキルで空カテゴリに大ダメージ【海・攻城】',
    7, 185, 100, 280, 'siege', 'sea',
    23000, '{"iron": 450, "steel": 100, "electronics": 80}', 4600,
    165, 120, (SELECT id FROM battle_special_skills WHERE skill_key = 'laser_irradiation' LIMIT 1)),

-- 対戦キャノン砲：陸・攻城・散弾発射
('anti_tank_cannon', '対戦キャノン砲', '🎯', '対戦車用大砲。散弾発射スキルで陸カテゴリに大ダメージ【陸・攻城】',
    7, 175, 70, 190, 'siege', 'land',
    19000, '{"iron": 380, "gunpowder": 100}', 3800,
    145, 105, (SELECT id FROM battle_special_skills WHERE skill_key = 'shrapnel_fire' LIMIT 1));

-- ===============================================
-- ③ ルネサンス時代（era_order = 5）の新兵種
-- ===============================================

INSERT IGNORE INTO civilization_troop_types (
    troop_key, name, icon, description, unlock_era_id, 
    attack_power, defense_power, health_points, troop_category, domain_category,
    train_cost_coins, train_cost_resources, train_time_seconds,
    heal_time_seconds, heal_cost_coins, special_skill_id
) VALUES
-- 投石兵：陸・攻城・投石
('slinger', '投石兵', '🪨', '投石でアーマーを貫通する。投石スキルでアーマー貫通ダメージ【陸・攻城】',
    5, 65, 40, 100, 'siege', 'land',
    3000, '{"stone": 80, "wood": 40}', 900,
    45, 28, (SELECT id FROM battle_special_skills WHERE skill_key = 'stone_throw' LIMIT 1));

-- ===============================================
-- ④ 原子力時代（era_order = 8）の新兵種
-- ===============================================

INSERT IGNORE INTO civilization_troop_types (
    troop_key, name, icon, description, unlock_era_id, 
    attack_power, defense_power, health_points, troop_category, domain_category,
    is_disposable, train_cost_coins, train_cost_resources, train_time_seconds,
    heal_time_seconds, heal_cost_coins, special_skill_id
) VALUES
-- 巡航ミサイル：空・攻城・自律飛行・使い捨て
('cruise_missile', '巡航ミサイル', '🚀', '【使い捨て】自律飛行する巡航ミサイル。自律飛行スキルで3回連続攻撃【空・攻城】',
    8, 280, 60, 120, 'siege', 'air', TRUE,
    80000, '{"iron": 800, "uranium": 100, "electronics": 200}', 9000,
    0, 0, (SELECT id FROM battle_special_skills WHERE skill_key = 'autonomous_flight' LIMIT 1)),

-- 防護兵：陸・歩兵・核武装解除
('protection_soldier', '防護兵', '🛡️', '核対策専門部隊。核武装解除スキルで核ユニットに大ダメージ【陸・歩兵】',
    8, 170, 130, 350, 'infantry', 'land', FALSE,
    32000, '{"iron": 650, "uranium": 40, "electronics": 100}', 6400,
    200, 160, (SELECT id FROM battle_special_skills WHERE skill_key = 'nuclear_disarm' LIMIT 1));

-- ===============================================
-- ⑤ 前提条件の設定
-- ===============================================

-- ルネサンス時代の兵種：航海術研究が前提
UPDATE civilization_troop_types 
SET prerequisite_research_id = (SELECT id FROM civilization_researches WHERE research_key = 'navigation' LIMIT 1)
WHERE troop_key = 'slinger' 
AND prerequisite_research_id IS NULL;

-- 現代時代の兵種：電気研究が前提
UPDATE civilization_troop_types 
SET prerequisite_research_id = (SELECT id FROM civilization_researches WHERE research_key = 'electricity' LIMIT 1)
WHERE troop_key IN ('naval_fighter', 'ship_laser_weapon', 'anti_tank_cannon') 
AND prerequisite_research_id IS NULL;

-- 原子力時代の兵種：核技術研究が前提
UPDATE civilization_troop_types 
SET prerequisite_research_id = (SELECT id FROM civilization_researches WHERE research_key = 'nuclear_fission' LIMIT 1)
WHERE troop_key IN ('cruise_missile', 'protection_soldier') 
AND prerequisite_research_id IS NULL;

-- ===============================================
-- ⑥ 建物前提条件の設定
-- ===============================================

-- 空軍兵種は空軍基地が必要
UPDATE civilization_troop_types 
SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'air_base' LIMIT 1)
WHERE troop_key IN ('naval_fighter', 'cruise_missile')
AND prerequisite_building_id IS NULL;

-- 海軍兵種は造船所が必要
UPDATE civilization_troop_types 
SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'naval_dock' LIMIT 1)
WHERE troop_key = 'ship_laser_weapon'
AND prerequisite_building_id IS NULL;

-- 攻城兵器は攻城工房が必要
UPDATE civilization_troop_types 
SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'siege_workshop' LIMIT 1)
WHERE troop_key IN ('slinger', 'anti_tank_cannon')
AND prerequisite_building_id IS NULL;

-- 防護兵は兵舎が必要
UPDATE civilization_troop_types 
SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'barracks' LIMIT 1)
WHERE troop_key = 'protection_soldier'
AND prerequisite_building_id IS NULL;

-- ===============================================
-- 完了メッセージ
-- ===============================================
SELECT 'New troops 2026 Part 2 schema created successfully' AS status;
SELECT CONCAT('Added ', COUNT(*), ' new troop types') AS troops_added 
FROM civilization_troop_types 
WHERE troop_key IN (
    'naval_fighter', 'ship_laser_weapon', 'anti_tank_cannon',
    'slinger', 'cruise_missile', 'protection_soldier'
);
