-- ===============================================
-- minibird_feature_expansion_2026.sqlで追加した兵種にスキルを割り当て
-- 既存のスキルを使用し、不足しているスキルは新規追加
-- ===============================================

USE syugetsu2025_clone;

-- ===============================================
-- ① 不足しているスキルを追加
-- ===============================================

-- 既存スキル確認用（add_new_troops_2026.sqlで追加済みのスキル）:
-- anti_air_barrage, tank_destroyer, precision_shot, bloodlust, fear, 
-- armor_crush, disarm, weaken, counter, evasion, inspire, defense_formation

-- minibird_feature_expansion_2026.sqlで使用されているが、追加が必要なスキル:

INSERT IGNORE INTO battle_special_skills (skill_key, name, icon, description, effect_type, effect_target, effect_value, duration_turns, activation_chance) VALUES
-- 攻撃力上昇
('attack_up', '攻撃強化', '⚔️', '攻撃力を35%上昇させる', 'buff', 'self', 35, 3, 30),

-- 防御力上昇
('defense_up', '防御強化', '🛡️', '防御力を35%上昇させる', 'buff', 'self', 35, 3, 30),

-- 装甲硬化（防御力大幅上昇）
('armor_harden', '装甲硬化', '🛡️', '装甲を硬化させ、防御力を50%上昇させる', 'buff', 'self', 50, 3, 25),

-- クリティカル
('critical', 'クリティカル', '💥', 'クリティカル率が上昇し、2倍ダメージの確率40%', 'buff', 'self', 100, 1, 40),

-- 加速
('acceleration', '加速', '⚡', '素早さを上げ、次のターンで2回攻撃', 'buff', 'self', 100, 1, 25),

-- 二回攻撃
('double_attack', '二回攻撃', '⚔️⚔️', '1ターンに2回攻撃する', 'buff', 'self', 100, 1, 35),

-- 治癒
('heal', '治癒', '💚', '味方全体のHPを20%回復する', 'heal', 'ally', 20, 1, 30),

-- 再生
('regeneration', '再生', '🩹', '毎ターン自身のHPを15%回復する', 'heal', 'self', 15, 99, 100),

-- 脆弱化
('vulnerable', '脆弱化', '🎯', '敵の防御力を40%低下させる', 'debuff', 'enemy', 40, 2, 30);

-- ===============================================
-- ② 兵種にスキルを割り当て（UPDATE文）
-- ===============================================

-- 現代Ⅵ（時代15）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'attack_up' LIMIT 1) WHERE troop_key = 'cyber_warrior';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_harden' LIMIT 1) WHERE troop_key = 'dark_matter_tank';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'critical' LIMIT 1) WHERE troop_key = 'energy_drone';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'evasion' LIMIT 1) WHERE troop_key = 'quantum_soldier';

-- 地球大革命時代（時代16）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'heal' LIMIT 1) WHERE troop_key = 'eco_guardian';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'acceleration' LIMIT 1) WHERE troop_key = 'portal_knight';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'double_attack' LIMIT 1) WHERE troop_key = 'tech_mech';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_formation' LIMIT 1) WHERE troop_key = 'global_defender';

-- 近未来時代（時代17）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'bloodlust' LIMIT 1) WHERE troop_key = 'antimatter_soldier';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'counter' LIMIT 1) WHERE troop_key = 'synthetic_warrior';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'inspire' LIMIT 1) WHERE troop_key = 'space_marine_elite';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_crush' LIMIT 1) WHERE troop_key = 'mega_mech';

-- 近未来時代Ⅱ（時代18）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'attack_up' LIMIT 1) WHERE troop_key = 'generation_trooper';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'regeneration' LIMIT 1) WHERE troop_key = 'gene_warrior';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_up' LIMIT 1) WHERE troop_key = 'colony_guard';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'precision_shot' LIMIT 1) WHERE troop_key = 'orbital_bomber';

-- 近未来時代Ⅲ（時代19）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'critical' LIMIT 1) WHERE troop_key = 'movement_assassin';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_harden' LIMIT 1) WHERE troop_key = 'quantum_tank';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'evasion' LIMIT 1) WHERE troop_key = 'star_trooper';

-- 惑星革命時代（時代20）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'bloodlust' LIMIT 1) WHERE troop_key = 'universe_soldier';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'disarm' LIMIT 1) WHERE troop_key = 'planet_crusher';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'inspire' LIMIT 1) WHERE troop_key = 'un_peacekeeper';

-- 近未来時代Ⅳ（時代21）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'vulnerable' LIMIT 1) WHERE troop_key = 'cache_hacker';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'counter' LIMIT 1) WHERE troop_key = 'cosmic_knight';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'double_attack' LIMIT 1) WHERE troop_key = 'energy_titan';

-- 近未来時代Ⅴ（時代22）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'inspire' LIMIT 1) WHERE troop_key = 'quantum_commander';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_formation' LIMIT 1) WHERE troop_key = 'planet_guardian';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'weaken' LIMIT 1) WHERE troop_key = 'transmutation_mage';

-- 宇宙船革命時代（時代23）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'attack_up' LIMIT 1) WHERE troop_key = 'container_soldier';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'critical' LIMIT 1) WHERE troop_key = 'cosmic_archaeologist';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'acceleration' LIMIT 1) WHERE troop_key = 'lightspeed_fighter';

-- 銀河時代（時代24）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'double_attack' LIMIT 1) WHERE troop_key = 'ai_legion';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'fear' LIMIT 1) WHERE troop_key = 'cosmic_operator';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_crush' LIMIT 1) WHERE troop_key = 'galactic_titan';

-- 銀河時代Ⅱ（時代25）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'regeneration' LIMIT 1) WHERE troop_key = 'federation_elite';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_up' LIMIT 1) WHERE troop_key = 'harmony_guardian';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'disarm' LIMIT 1) WHERE troop_key = 'universal_destroyer';

-- ===============================================
-- ③ 完了メッセージ
-- ===============================================
SELECT 'スキルの追加と兵種へのスキル割り当てが完了しました' AS status;
SELECT CONCAT('スキルが割り当てられた兵種: ', COUNT(*), '体') AS troops_with_skills 
FROM civilization_troop_types 
WHERE troop_key IN (
    'cyber_warrior', 'dark_matter_tank', 'energy_drone', 'quantum_soldier',
    'eco_guardian', 'portal_knight', 'tech_mech', 'global_defender',
    'antimatter_soldier', 'synthetic_warrior', 'space_marine_elite', 'mega_mech',
    'generation_trooper', 'gene_warrior', 'colony_guard', 'orbital_bomber',
    'movement_assassin', 'quantum_tank', 'star_trooper',
    'universe_soldier', 'planet_crusher', 'un_peacekeeper',
    'cache_hacker', 'cosmic_knight', 'energy_titan',
    'quantum_commander', 'planet_guardian', 'transmutation_mage',
    'container_soldier', 'cosmic_archaeologist', 'lightspeed_fighter',
    'ai_legion', 'cosmic_operator', 'galactic_titan',
    'federation_elite', 'harmony_guardian', 'universal_destroyer'
) AND special_skill_id IS NOT NULL;
