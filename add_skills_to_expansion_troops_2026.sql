-- ===============================================
-- minibird_feature_expansion_2026.sqlで追加した兵種にスキルを割り当て
-- 既存のスキルを使用し、不足しているスキルは新規追加
-- ユニークスキルも追加して個性を出す
-- ===============================================

USE syugetsu2025_clone;

-- ===============================================
-- ① 不足しているスキルを追加（基本スキル）
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
-- ② ユニークスキルを追加（個性的なスキル）
-- ===============================================

INSERT IGNORE INTO battle_special_skills (skill_key, name, icon, description, effect_type, effect_target, effect_value, duration_turns, activation_chance) VALUES
-- ダークマターフィールド：敵全体の命中率を低下させる
('dark_matter_field', 'ダークマター領域', '🌑', '暗黒物質で敵を包み、命中率を30%低下させる', 'debuff', 'enemy', 30, 2, 25),

-- エネルギーサージ：攻撃時にエネルギーが溜まり、3ターン目に大ダメージ
('energy_surge', 'エネルギーサージ', '⚡', '蓄積したエネルギーで50%追加ダメージ', 'buff', 'self', 50, 1, 30),

-- 量子もつれ：攻撃を受けたダメージを敵にも反射
('quantum_entanglement', '量子もつれ', '💠', '受けたダメージの40%を敵に反射', 'special', 'self', 40, 99, 100),

-- ポータルシフト：敵の攻撃を別次元に転送
('portal_shift', 'ポータルシフト', '🌀', '40%の確率で攻撃を無効化', 'buff', 'self', 40, 99, 40),

-- エコリンク：自然の力で味方全体のHPを徐々に回復
('eco_link', 'エコリンク', '🌿', '味方全体のHPを毎ターン10%回復', 'heal', 'ally', 10, 99, 100),

-- 反物質爆発：戦闘不能時に敵全体にダメージ
('antimatter_explosion', '反物質爆発', '⚛️', '戦闘不能時に敵全体に自身の最大HPの50%分のダメージ', 'special', 'enemy', 50, 1, 100),

-- 合成再構築：戦闘中に一度だけHP全回復
('synthetic_rebuild', '合成再構築', '🔬', '一度だけHP100%回復（発動率20%）', 'heal', 'self', 100, 1, 20),

-- 遺伝子変異：ターン毎に攻撃力が15%ずつ上昇
('gene_mutation', '遺伝子変異', '🧬', '毎ターン攻撃力15%上昇（最大60%）', 'buff', 'self', 15, 99, 100),

-- 軌道砲撃：無視できない超威力の一撃
('orbital_strike', '軌道砲撃', '💥', '防御力を無視して150%ダメージ', 'buff', 'self', 150, 1, 20),

-- 高速機動：回避率大幅上昇
('high_speed_maneuver', '高速機動', '🎯', '50%の確率で攻撃を回避', 'buff', 'self', 50, 99, 50),

-- AI戦術解析：敵の弱点を見抜く
('ai_tactical_analysis', 'AI戦術解析', '📦', '敵の防御力を50%無視', 'buff', 'self', 50, 99, 100),

-- 宇宙シャード共鳴：宇宙の力で攻防同時強化
('cosmic_resonance', '宇宙共鳴', '💎', '攻撃力と防御力を同時に30%上昇', 'buff', 'self', 30, 3, 30),

-- 変換魔法：敵のバフを奪う
('transmutation_magic', '変換魔法', '🧪', '敵のバフ効果を奪い取る', 'special', 'enemy', 100, 1, 25),

-- 次元跳躍：戦闘開始時に先制攻撃
('dimension_leap', '次元跳躍', '🌀', '戦闘開始時に確実に先制攻撃', 'buff', 'self', 100, 1, 100),

-- 銀河の威光：味方全体に強力なバフ
('galactic_majesty', '銀河の威光', '🌌', '味方全体の攻撃力と防御力を25%上昇', 'buff', 'ally', 25, 3, 25),

-- ユニバーサル破壊：全てを破壊する究極の一撃
('universal_destruction', 'ユニバーサル破壊', '💥', '敵全体に自身の攻撃力の80%でダメージ', 'special', 'enemy', 80, 1, 15);

-- ===============================================
-- ③ 兵種にスキルを割り当て（UPDATE文）
-- ===============================================

-- 現代Ⅵ（時代15）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'attack_up' LIMIT 1) WHERE troop_key = 'cyber_warrior';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'dark_matter_field' LIMIT 1) WHERE troop_key = 'dark_matter_tank';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'energy_surge' LIMIT 1) WHERE troop_key = 'energy_drone';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'quantum_entanglement' LIMIT 1) WHERE troop_key = 'quantum_soldier';

-- 地球大革命時代（時代16）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'eco_link' LIMIT 1) WHERE troop_key = 'eco_guardian';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'portal_shift' LIMIT 1) WHERE troop_key = 'portal_knight';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'double_attack' LIMIT 1) WHERE troop_key = 'tech_mech';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_formation' LIMIT 1) WHERE troop_key = 'global_defender';

-- 近未来時代（時代17）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'antimatter_explosion' LIMIT 1) WHERE troop_key = 'antimatter_soldier';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'synthetic_rebuild' LIMIT 1) WHERE troop_key = 'synthetic_warrior';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'inspire' LIMIT 1) WHERE troop_key = 'space_marine_elite';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_crush' LIMIT 1) WHERE troop_key = 'mega_mech';

-- 近未来時代Ⅱ（時代18）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'attack_up' LIMIT 1) WHERE troop_key = 'generation_trooper';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'gene_mutation' LIMIT 1) WHERE troop_key = 'gene_warrior';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_up' LIMIT 1) WHERE troop_key = 'colony_guard';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'orbital_strike' LIMIT 1) WHERE troop_key = 'orbital_bomber';

-- 近未来時代Ⅲ（時代19）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'high_speed_maneuver' LIMIT 1) WHERE troop_key = 'movement_assassin';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_harden' LIMIT 1) WHERE troop_key = 'quantum_tank';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'evasion' LIMIT 1) WHERE troop_key = 'star_trooper';

-- 惑星革命時代（時代20）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'bloodlust' LIMIT 1) WHERE troop_key = 'universe_soldier';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'disarm' LIMIT 1) WHERE troop_key = 'planet_crusher';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'inspire' LIMIT 1) WHERE troop_key = 'un_peacekeeper';

-- 近未来時代Ⅳ（時代21）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'vulnerable' LIMIT 1) WHERE troop_key = 'cache_hacker';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'cosmic_resonance' LIMIT 1) WHERE troop_key = 'cosmic_knight';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'double_attack' LIMIT 1) WHERE troop_key = 'energy_titan';

-- 近未来時代Ⅴ（時代22）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'inspire' LIMIT 1) WHERE troop_key = 'quantum_commander';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_formation' LIMIT 1) WHERE troop_key = 'planet_guardian';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'transmutation_magic' LIMIT 1) WHERE troop_key = 'transmutation_mage';

-- 宇宙船革命時代（時代23）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'attack_up' LIMIT 1) WHERE troop_key = 'container_soldier';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'critical' LIMIT 1) WHERE troop_key = 'cosmic_archaeologist';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'dimension_leap' LIMIT 1) WHERE troop_key = 'lightspeed_fighter';

-- 銀河時代（時代24）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'ai_tactical_analysis' LIMIT 1) WHERE troop_key = 'ai_legion';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'fear' LIMIT 1) WHERE troop_key = 'cosmic_operator';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_crush' LIMIT 1) WHERE troop_key = 'galactic_titan';

-- 銀河時代Ⅱ（時代25）の兵種
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'regeneration' LIMIT 1) WHERE troop_key = 'federation_elite';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'galactic_majesty' LIMIT 1) WHERE troop_key = 'harmony_guardian';
UPDATE civilization_troop_types SET special_skill_id = (SELECT id FROM battle_special_skills WHERE skill_key = 'universal_destruction' LIMIT 1) WHERE troop_key = 'universal_destroyer';

-- ===============================================
-- ④ 完了メッセージ
-- ===============================================
SELECT 'スキルの追加と兵種へのスキル割り当てが完了しました' AS status;
SELECT '基本スキル: 9個、ユニークスキル: 16個を追加しました' AS skill_summary;
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
