<?php
// ===============================================
// battle_engine.php
// ターン制バトルシステムエンジン
// ===============================================

// バトルシステム定数
define('BATTLE_MAX_TURNS', 50);                     // 最大ターン数
define('BATTLE_DAMAGE_VARIANCE', 0.2);              // ダメージの乱数幅（±20%）
define('BATTLE_CRITICAL_MULTIPLIER', 1.5);          // クリティカルダメージ倍率
define('BATTLE_BASE_CRITICAL_CHANCE', 5);           // 基本クリティカル率（%）
define('BATTLE_MAX_ARMOR_REDUCTION_CAP', 0.90);     // 新アーマー計算：最大ダメージ軽減率（90%）
define('BATTLE_MIN_DAMAGE_PERCENTAGE', 0.10);       // 新アーマー計算：最低保証ダメージ（元のダメージの10%）
define('BATTLE_MIN_DAMAGE', 1);                     // 最小ダメージ
define('BATTLE_EQUIPMENT_ATTACK_MULTIPLIER', 0.5);  // 装備攻撃力の適用倍率
define('BATTLE_EQUIPMENT_ARMOR_MULTIPLIER', 1.0);   // 装備アーマーの適用倍率
define('BATTLE_EQUIPMENT_HEALTH_MULTIPLIER', 2.0);  // 装備体力の適用倍率
define('BATTLE_DOT_BASE_HEALTH', 1000);              // 継続ダメージ計算用の基準HP
define('BATTLE_DOT_SCALING_FACTOR', 0.3);            // 継続ダメージのスケーリング係数（0.3 = 30%）
define('BATTLE_MAX_NEW_SKILL_ACTIVATIONS', 3);      // ① 1ターンに新たに発動可能なスキルの最大数
                                                     // 除外対象: 継続バフ/デバフ、即時ダメージ、回復、DOT、シナジー
define('SYNERGY_SKILL_DURATION_THRESHOLD', 99);     // シナジースキル判定の継続ターン閾値

// ③ ヒーロースキルシステム定数（見直し：発動率を下げ、ダメージ上限を動的に設定）
define('HERO_SKILL_BASE_ACTIVATION_CHANCE', 15);     // ③ ヒーロースキル基本発動率（%）30→15に減少
define('HERO_SKILL_STAR_BONUS_CHANCE', 2);           // ③ 星レベルごとの発動率ボーナス（%）5→2に減少
define('HERO_SKILL_2ND_BASE_ACTIVATION_CHANCE', 10); // ③ 2番目のスキルの基本発動率（%）20→10に減少
define('HERO_SKILL_2ND_STAR_BONUS_CHANCE', 1);       // ③ 2番目のスキルの星レベルごとのボーナス（%）3→1に減少
define('HERO_SKILL_STAR_EFFECT_BONUS', 0.05);        // ③ 星レベルごとの効果ボーナス（10%→5%に減少）
define('HERO_SKILL_DAMAGE_RATIO_CAP', 0.3);          // ③ ヒーロースキルダメージの敵HP比率上限（30%）
define('HERO_SKILL_ATTACKER_POWER_MULTIPLIER', 1.75); // ③ 攻撃者の戦力に基づくダメージ上限倍率
define('HERO_SKILL_MIN_DAMAGE_CAP', 500);            // ③ ダメージ上限の最低保証値
define('HERO_SKILL_HEAL_RATIO_CAP', 0.3);            // ⑦ 回復量の最大HP比率上限（30%）
define('HERO_SKILL_MIN_HEAL_CAP', 100);              // ⑦ 回復量の最低保証値
define('HERO_STAR_ATTACK_BONUS', 50
);                 // 星レベルごとの攻撃力ボーナス
define('HERO_STAR_ARMOR_BONUS', 30);                  // 星レベルごとの防御力ボーナス
define('HERO_STAR_HEALTH_BONUS', 500);                // 星レベルごとの体力ボーナス

/**
 * ③⑥ ヒーロースキルのダメージ上限を動的に計算
 * 攻撃者の戦力と敵HPに基づいてダメージ上限を決定
 * 
 * ⑥ 修正: 2つの上限値の「低い方」を採用（バランス調整）
 * 理由: 以前はMAX（高い方）を採用していたため、高HP敵に対するダメージが
 * 過大になっていた。MIN（低い方）に変更することで、攻撃力に見合った
 * ダメージ上限となり、ゲームバランスが改善される。
 * 
 * @param array $attacker 攻撃者のステータス
 * @param array $defender 防御者のステータス
 * @return int ダメージ上限
 */
function calculateHeroSkillDamageCap($attacker, $defender) {
    // 攻撃者の戦力に基づく上限（攻撃力 × 倍率）
    $attackerBasedCap = (int)floor($attacker['attack'] * HERO_SKILL_ATTACKER_POWER_MULTIPLIER);
    
    // 敵HPに基づく上限（最大HPの30%）
    $defenderHpCap = HERO_SKILL_MIN_DAMAGE_CAP;
    if (isset($defender['max_health']) && $defender['max_health'] > 0) {
        $defenderHpCap = (int)floor($defender['max_health'] * HERO_SKILL_DAMAGE_RATIO_CAP);
    }
    
    // ⑥ 両方の上限の「低い方」を採用（バランス調整）
    $dynamicCap = min($attackerBasedCap, $defenderHpCap);
    
    // 最低保証値を確保（500）
    return max($dynamicCap, HERO_SKILL_MIN_DAMAGE_CAP);
}

/**
 * 特殊スキル情報を取得
 * @param PDO $pdo
 * @return array skill_id => skill_data の連想配列
 */
function getSpecialSkills($pdo) {
    static $skills = null;
    if ($skills === null) {
        $stmt = $pdo->query("SELECT * FROM battle_special_skills");
        $skills = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $skills[$row['id']] = $row;
        }
    }
    return $skills;
}

/**
 * 兵種の詳細情報を取得（特殊スキル含む）
 * @param PDO $pdo
 * @param int $troopTypeId
 * @return array|null
 */
function getTroopTypeWithSkill($pdo, $troopTypeId) {
    $stmt = $pdo->prepare("
        SELECT tt.*, ss.skill_key, ss.name as skill_name, ss.icon as skill_icon, 
               ss.effect_type, ss.effect_target, ss.effect_value, ss.duration_turns, ss.activation_chance
        FROM civilization_troop_types tt
        LEFT JOIN battle_special_skills ss ON tt.special_skill_id = ss.id
        WHERE tt.id = ?
    ");
    $stmt->execute([$troopTypeId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * ユーザーの編成中ヒーローとスキルを取得
 * @param PDO $pdo
 * @param int $userId
 * @param string $battleType バトルタイプ (conquest, world_boss, wandering_monster, war, defense)
 * @return array|null ヒーロー情報と選択スキル
 */
function getUserBattleHero($pdo, $userId, $battleType = null) {
    // バトルタイプ別のヒーロー選択を確認
    if ($battleType) {
        $stmt = $pdo->prepare("
            SELECT ubhs.*, h.*, uh.star_level, uh.shards
            FROM user_battle_hero_selection ubhs
            JOIN heroes h ON ubhs.hero_id = h.id
            LEFT JOIN user_heroes uh ON ubhs.user_id = uh.user_id AND ubhs.hero_id = uh.hero_id
            WHERE ubhs.user_id = ? AND ubhs.battle_type = ?
        ");
        $stmt->execute([$userId, $battleType]);
        $selection = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($selection && $selection['star_level'] > 0) {
            return $selection;
        }
    }
    
    // バトルタイプ別選択がない場合は編成中のヒーローを使用
    $stmt = $pdo->prepare("
        SELECT uh.*, h.*
        FROM user_heroes uh
        JOIN heroes h ON uh.hero_id = h.id
        WHERE uh.user_id = ? AND uh.is_equipped = 1 AND uh.star_level > 0
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $hero = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $hero ?: null;
}

/**
 * ヒーロースキルをバトルユニットに適用
 * @param array $unit バトルユニット
 * @param array $heroData ヒーロー情報
 * @param int $skillType1 スキル1の種類 (1=第1バトルスキル, 2=第2バトルスキル)
 * @param int|null $skillType2 スキル2の種類
 * @return array 更新されたバトルユニット
 */
function applyHeroSkillsToUnit($unit, $heroData, $skillType1 = 1, $skillType2 = null) {
    if (!$heroData) {
        return $unit;
    }
    
    $heroSkills = [];
    $starLevel = (int)($heroData['star_level'] ?? 1);
    
    // スキル効果はヒーローの星レベルで増加 (基本100% + 星レベル*HERO_SKILL_STAR_EFFECT_BONUS)
    $skillMultiplier = 1.0 + ($starLevel - 1) * HERO_SKILL_STAR_EFFECT_BONUS;
    
    // 第1スキル追加
    if ($skillType1 == 1 && !empty($heroData['battle_skill_name'])) {
        $effectData = json_decode($heroData['battle_skill_effect'] ?? '{}', true);
        $heroSkills[] = [
            'skill_key' => 'hero_skill_1',
            'skill_name' => $heroData['battle_skill_name'],
            'skill_icon' => $heroData['icon'] ?? '⚔️',
            'effect_type' => 'hero_battle',
            'effect_target' => 'enemy',
            'effect_value' => $skillMultiplier,
            'effect_data' => $effectData,
            'duration_turns' => $effectData['duration'] ?? 1,
            'activation_chance' => HERO_SKILL_BASE_ACTIVATION_CHANCE + $starLevel * HERO_SKILL_STAR_BONUS_CHANCE,
            'troop_type_id' => 0,
            'troop_name' => $heroData['name'],
            'troop_icon' => $heroData['icon'],
            'is_hero_skill' => true
        ];
    } else if ($skillType1 == 2 && !empty($heroData['battle_skill_2_name'])) {
        $effectData = json_decode($heroData['battle_skill_2_effect'] ?? '{}', true);
        $heroSkills[] = [
            'skill_key' => 'hero_skill_2',
            'skill_name' => $heroData['battle_skill_2_name'],
            'skill_icon' => $heroData['icon'] ?? '⚔️',
            'effect_type' => 'hero_battle',
            'effect_target' => 'enemy',
            'effect_value' => $skillMultiplier,
            'effect_data' => $effectData,
            'duration_turns' => $effectData['duration'] ?? 1,
            'activation_chance' => HERO_SKILL_BASE_ACTIVATION_CHANCE + $starLevel * HERO_SKILL_STAR_BONUS_CHANCE,
            'troop_type_id' => 0,
            'troop_name' => $heroData['name'],
            'troop_icon' => $heroData['icon'],
            'is_hero_skill' => true
        ];
    }
    
    // 第2スキル追加（選択されている場合）
    if ($skillType2) {
        if ($skillType2 == 1 && !empty($heroData['battle_skill_name'])) {
            $effectData = json_decode($heroData['battle_skill_effect'] ?? '{}', true);
            $heroSkills[] = [
                'skill_key' => 'hero_skill_1_second',
                'skill_name' => $heroData['battle_skill_name'],
                'skill_icon' => $heroData['icon'] ?? '⚔️',
                'effect_type' => 'hero_battle',
                'effect_target' => 'enemy',
                'effect_value' => $skillMultiplier,
                'effect_data' => $effectData,
                'duration_turns' => $effectData['duration'] ?? 1,
                'activation_chance' => HERO_SKILL_2ND_BASE_ACTIVATION_CHANCE + $starLevel * HERO_SKILL_2ND_STAR_BONUS_CHANCE,
                'troop_type_id' => 0,
                'troop_name' => $heroData['name'],
                'troop_icon' => $heroData['icon'],
                'is_hero_skill' => true
            ];
        } else if ($skillType2 == 2 && !empty($heroData['battle_skill_2_name'])) {
            $effectData = json_decode($heroData['battle_skill_2_effect'] ?? '{}', true);
            $heroSkills[] = [
                'skill_key' => 'hero_skill_2_second',
                'skill_name' => $heroData['battle_skill_2_name'],
                'skill_icon' => $heroData['icon'] ?? '⚔️',
                'effect_type' => 'hero_battle',
                'effect_target' => 'enemy',
                'effect_value' => $skillMultiplier,
                'effect_data' => $effectData,
                'duration_turns' => $effectData['duration'] ?? 1,
                'activation_chance' => HERO_SKILL_2ND_BASE_ACTIVATION_CHANCE + $starLevel * HERO_SKILL_2ND_STAR_BONUS_CHANCE,
                'troop_type_id' => 0,
                'troop_name' => $heroData['name'],
                'troop_icon' => $heroData['icon'],
                'is_hero_skill' => true
            ];
        }
    }
    
    // ヒーロースキルをユニットに追加
    $unit['skills'] = array_merge($unit['skills'], $heroSkills);
    $unit['hero'] = [
        'id' => $heroData['hero_id'] ?? $heroData['id'],
        'name' => $heroData['name'],
        'icon' => $heroData['icon'],
        'star_level' => $starLevel
    ];
    
    // ヒーローの星レベルに応じて基本ステータスボーナスを追加
    $heroAttackBonus = $starLevel * HERO_STAR_ATTACK_BONUS;
    $heroArmorBonus = $starLevel * HERO_STAR_ARMOR_BONUS;
    $heroHealthBonus = $starLevel * HERO_STAR_HEALTH_BONUS;
    
    $unit['attack'] += $heroAttackBonus;
    $unit['armor'] += $heroArmorBonus;
    $unit['max_health'] += $heroHealthBonus;
    $unit['current_health'] += $heroHealthBonus;
    
    return $unit;
}

/**
 * ヒーロースキルの効果を処理
 * @param array $skill スキル情報
 * @param array $attacker 攻撃者ユニット
 * @param array $defender 防御者ユニット
 * @return array [damage, heal, messages, attacker_effects, defender_effects]
 */
function processHeroSkillEffect($skill, $attacker, $defender) {
    $result = [
        'damage' => 0,
        'heal' => 0,
        'messages' => [],
        'attacker_effects' => [],
        'defender_effects' => []
    ];
    
    if (empty($skill['effect_data'])) {
        return $result;
    }
    
    $effectData = $skill['effect_data'];
    $multiplier = $skill['effect_value'] ?? 1.0;
    $skillName = $skill['skill_name'];
    $icon = $skill['skill_icon'];
    
    // ③ ダメージ系スキル（動的ダメージ上限を適用）
    if (isset($effectData['damage_multiplier'])) {
        $damage = (int)floor($attacker['attack'] * $effectData['damage_multiplier'] * $multiplier);
        
        // ③ 動的ダメージ上限を適用（攻撃者の戦力と敵HPに基づく）
        $maxDamage = calculateHeroSkillDamageCap($attacker, $defender);
        $damage = min($damage, $maxDamage);
        
        // 連続攻撃（hit_count）
        if (isset($effectData['hit_count']) && $effectData['hit_count'] > 1) {
            $damagePerHit = (int)floor($damage / $effectData['hit_count']);
            $result['damage'] = $damage;
            $result['messages'][] = "{$icon} {$skillName}発動！{$effectData['hit_count']}連撃で合計{$damage}ダメージ！";
        } else {
            $result['damage'] = $damage;
            $result['messages'][] = "{$icon} {$skillName}発動！{$damage}ダメージ！";
        }
    }
    
    // 回復系スキル
    if (isset($effectData['heal_percent'])) {
        $heal = (int)floor($attacker['max_health'] * ($effectData['heal_percent'] / 100) * $multiplier);
        
        // ⑦ 回復量のキャップを適用（最大HPの30%、最低保証100）
        $maxHeal = max(
            HERO_SKILL_MIN_HEAL_CAP,
            (int)floor($attacker['max_health'] * HERO_SKILL_HEAL_RATIO_CAP)
        );
        $heal = min($heal, $maxHeal);
        
        $result['heal'] = $heal;
        $result['messages'][] = "{$icon} {$skillName}発動！{$heal}回復！";
        
        // 継続回復（HOT: Heal Over Time）
        if (isset($effectData['hot_percent'])) {
            // ⑦ 継続回復にもキャップを適用
            $hotPercent = min($effectData['hot_percent'], HERO_SKILL_HEAL_RATIO_CAP * 100);
            $result['attacker_effects'][] = [
                'skill_key' => 'heal_over_time',
                'skill_name' => '継続回復',
                'skill_icon' => '💚',
                'effect_type' => 'hot',
                'effect_target' => 'self',
                'effect_value' => $hotPercent,
                'remaining_turns' => $effectData['hot_duration'] ?? 2
            ];
            $hotDuration = $effectData['hot_duration'] ?? 2;
            $result['messages'][] = "💚 継続回復を{$hotDuration}ターン付与！";
        }
    }
    
    // バフ系スキル
    if (isset($effectData['armor_buff'])) {
        $result['attacker_effects'][] = [
            'skill_key' => 'armor_harden',
            'skill_name' => $skillName,
            'skill_icon' => $icon,
            'effect_type' => 'buff',
            'effect_target' => 'self',
            'effect_value' => $effectData['armor_buff'] * $multiplier,
            'remaining_turns' => $effectData['duration'] ?? 2
        ];
        $result['messages'][] = "{$icon} {$skillName}発動！アーマー+{$effectData['armor_buff']}%！";
        
        // タウント効果
        if (isset($effectData['taunt_duration'])) {
            $result['attacker_effects'][] = [
                'skill_key' => 'taunt',
                'skill_name' => '挑発',
                'skill_icon' => '🛡️',
                'effect_type' => 'buff',
                'effect_target' => 'self',
                'effect_value' => 100,
                'remaining_turns' => $effectData['taunt_duration']
            ];
            $result['messages'][] = "🛡️ 敵の攻撃を引き付ける！";
        }
    }
    
    // 毒スキル（新しいヒーロー: プレイグドクター）
    if (isset($effectData['poison_percent'])) {
        $result['defender_effects'][] = [
            'skill_key' => 'poison',
            'skill_name' => '毒',
            'skill_icon' => '☠️',
            'effect_type' => 'dot',
            'effect_target' => 'enemy',
            'effect_value' => $effectData['poison_percent'],
            'remaining_turns' => $effectData['poison_duration'] ?? 3
        ];
        $result['messages'][] = "{$icon} {$skillName}発動！敵に毒を付与！";
        
        // 攻撃力デバフ
        if (isset($effectData['attack_debuff'])) {
            $result['defender_effects'][] = [
                'skill_key' => 'attack_down',
                'skill_name' => '攻撃力低下',
                'skill_icon' => '⬇️',
                'effect_type' => 'debuff',
                'effect_target' => 'enemy',
                'effect_value' => $effectData['attack_debuff'],
                'remaining_turns' => $effectData['poison_duration'] ?? 3
            ];
            $result['messages'][] = "⬇️ 敵の攻撃力を{$effectData['attack_debuff']}%減少！";
        }
    }
    
    // デバフ系スキル（凍結）
    if (isset($effectData['freeze_duration']) || isset($effectData['freeze_chance'])) {
        $freezeChance = $effectData['freeze_chance'] ?? 100;
        if (mt_rand(1, 100) <= $freezeChance) {
            $result['defender_effects'][] = [
                'skill_key' => 'frozen',
                'skill_name' => '凍結',
                'skill_icon' => '❄️',
                'effect_type' => 'debuff',
                'effect_target' => 'enemy',
                'effect_value' => 100,
                'remaining_turns' => $effectData['freeze_duration'] ?? 1
            ];
            $result['messages'][] = "{$icon} {$skillName}発動！敵を凍結させた！";
        }
    }
    
    // 燃焼（継続ダメージ）
    if (isset($effectData['burn']) && $effectData['burn']) {
        $result['defender_effects'][] = [
            'skill_key' => 'burn',
            'skill_name' => '燃焼',
            'skill_icon' => '🔥',
            'effect_type' => 'dot',
            'effect_target' => 'enemy',
            'effect_value' => $effectData['burn_damage'] ?? 10,
            'remaining_turns' => $effectData['duration'] ?? 2
        ];
        $result['messages'][] = "{$icon} {$skillName}発動！敵を燃焼状態に！";
    }
    
    // 戦利品ボーナス（トレジャーハンター）
    if (isset($effectData['loot_bonus'])) {
        $result['attacker_effects'][] = [
            'skill_key' => 'loot_bonus',
            'skill_name' => '戦利品ボーナス',
            'skill_icon' => '💰',
            'effect_type' => 'buff',
            'effect_target' => 'self',
            'effect_value' => $effectData['loot_bonus'],
            'remaining_turns' => 999 // 戦闘中ずっと有効
        ];
        $result['messages'][] = "{$icon} {$skillName}発動！戦利品が{$effectData['loot_bonus']}%増加！";
    }
    
    // AOE（全体攻撃）
    if (isset($effectData['aoe']) && $effectData['aoe']) {
        $result['messages'][] = "{$icon} {$skillName}発動！全体攻撃！";
    }
    
    // ④ スタン（カオスロード用: 2ターン気絶）
    if (isset($effectData['stun_duration'])) {
        $result['defender_effects'][] = [
            'skill_key' => 'stun',
            'skill_name' => '気絶',
            'skill_icon' => '💫',
            'effect_type' => 'debuff',
            'effect_target' => 'enemy',
            'effect_value' => 100,
            'remaining_turns' => $effectData['stun_duration']
        ];
        $stunDuration = $effectData['stun_duration'];
        $result['messages'][] = "{$icon} {$skillName}発動！敵を{$stunDuration}ターン気絶させた！";
    }
    
    // ③ 即死（動的ダメージ上限を適用）
    if (isset($effectData['instant_kill_chance'])) {
        if (mt_rand(1, 100) <= $effectData['instant_kill_chance']) {
            // 即死の場合は通常上限の2倍まで
            $instantKillDamage = $defender['current_health'];
            $baseCap = calculateHeroSkillDamageCap($attacker, $defender);
            $maxInstantDamage = $baseCap * 2;
            $result['damage'] = min($instantKillDamage, max($maxInstantDamage, 1000));
            $result['messages'][] = "{$icon} {$skillName}発動！即死攻撃成功！";
        }
    }
    
    // ③ 半壊（シャドウアサシン弱体化: 20%で敵HPを半分にする、動的ダメージ上限適用）
    if (isset($effectData['half_kill_chance'])) {
        if (mt_rand(1, 100) <= $effectData['half_kill_chance']) {
            $halfDamage = (int)floor($defender['current_health'] / 2);
            $baseCap = calculateHeroSkillDamageCap($attacker, $defender);
            $maxHalfDamage = (int)floor($baseCap * 1.5);
            $result['damage'] = min($halfDamage, max($maxHalfDamage, 500));
            $result['messages'][] = "{$icon} {$skillName}発動！半壊攻撃成功！敵のHPを半分に！";
        }
    }
    
    return $result;
}

/**
 * バトルユニットを準備
 * @param array $troops [{troop_type_id, count, ...}, ...]
 * @param array $equipmentBuffs {attack, armor, health}
 * @param PDO $pdo
 * @return array バトルユニット情報
 */
function prepareBattleUnit($troops, $equipmentBuffs, $pdo) {
    $totalAttack = 0;
    $totalArmor = 0;
    $totalHealth = 0;
    $troopDetails = [];
    $skills = [];
    $troopKeys = [];  // 出撃中の兵種キーを収集（シナジー判定用）
    $domainCategories = [];  // 出撃中の領域カテゴリを収集（陸・海・空）
    
    // 第1パス: 兵種キーとカテゴリを収集（シナジー判定用）
    foreach ($troops as $troop) {
        $troopType = getTroopTypeWithSkill($pdo, $troop['troop_type_id']);
        if (!$troopType) continue;
        
        $count = (int)$troop['count'];
        if ($count <= 0) continue;
        
        if (!empty($troopType['troop_key'])) {
            $troopKeys[] = $troopType['troop_key'];
        }
        if (!empty($troopType['domain_category'])) {
            $domainCategories[] = $troopType['domain_category'];
        }
    }
    
    // シナジー条件をチェック
    $hasSubmarineSynergy = in_array('cruiser', $troopKeys) && (in_array('submarine', $troopKeys) || in_array('nuclear_submarine', $troopKeys));
    $hasMarineSynergy = in_array('assault_ship', $troopKeys) && in_array('marine', $troopKeys);
    $hasAirSuperiority = in_array('assault_carrier', $troopKeys) && in_array('air', $domainCategories);
    
    $synergyMessages = [];
    if ($hasSubmarineSynergy) {
        $synergyMessages[] = '🔱 対潜連携発動！巡洋艦のステータス2倍！';
    }
    if ($hasMarineSynergy) {
        $synergyMessages[] = '⚓ 上陸支援発動！強襲揚陸艦のステータス3倍！';
    }
    if ($hasAirSuperiority) {
        $synergyMessages[] = '✈️ 制空権準備完了！';
    }
    
    // 第2パス: ステータスを計算（個別兵種にシナジーを適用）
    foreach ($troops as $troop) {
        $troopType = getTroopTypeWithSkill($pdo, $troop['troop_type_id']);
        if (!$troopType) continue;
        
        $count = (int)$troop['count'];
        if ($count <= 0) continue;
        
        $troopKey = $troopType['troop_key'] ?? '';
        
        // 個別兵種のシナジー倍率を適用
        $troopAttackMultiplier = 1.0;
        $troopArmorMultiplier = 1.0;
        $troopHealthMultiplier = 1.0;
        
        // 潜水艦シナジー: 巡洋艦のみステータス2倍
        if ($hasSubmarineSynergy && $troopKey === 'cruiser') {
            $troopAttackMultiplier += 1.0;  // +100% = 2倍
            $troopArmorMultiplier += 1.0;
            $troopHealthMultiplier += 1.0;
        }
        
        // 海兵隊シナジー: 強襲揚陸艦のみステータス3倍
        if ($hasMarineSynergy && $troopKey === 'assault_ship') {
            $troopAttackMultiplier += 2.0;  // +200% = 3倍
            $troopArmorMultiplier += 2.0;
            $troopHealthMultiplier += 2.0;
        }
        
        // ステータス計算（シナジー倍率を個別適用）
        $attack = (int)floor((int)$troopType['attack_power'] * $count * $troopAttackMultiplier);
        $defense = (int)floor((int)$troopType['defense_power'] * $count * $troopArmorMultiplier);
        $health = (int)floor((int)($troopType['health_points'] ?? 100) * $count * $troopHealthMultiplier);
        
        $totalAttack += $attack;
        $totalArmor += $defense;
        $totalHealth += $health;
        
        // スキル情報を収集
        if (!empty($troopType['skill_key'])) {
            $skills[] = [
                'skill_key' => $troopType['skill_key'],
                'skill_name' => $troopType['skill_name'],
                'skill_icon' => $troopType['skill_icon'],
                'effect_type' => $troopType['effect_type'],
                'effect_target' => $troopType['effect_target'],
                'effect_value' => (float)$troopType['effect_value'],
                'duration_turns' => (int)$troopType['duration_turns'],
                'activation_chance' => (float)$troopType['activation_chance'],
                'troop_type_id' => $troop['troop_type_id'],
                'troop_name' => $troopType['name'],
                'troop_icon' => $troopType['icon'],
                'count' => $count,
                'troop_attack_power' => $attack  // ④ スキル持ち部隊の合計攻撃力（兵士1体の攻撃力×兵数×シナジー倍率）
            ];
        }
        
        $troopDetails[] = [
            'troop_type_id' => $troop['troop_type_id'],
            'name' => $troopType['name'],
            'icon' => $troopType['icon'],
            'count' => $count,
            'attack' => $attack,
            'defense' => $defense,
            'health' => $health,
            'category' => $troopType['troop_category'] ?? 'infantry',
            'domain_category' => $troopType['domain_category'] ?? 'land',
            'troop_key' => $troopKey,
            'is_disposable' => !empty($troopType['is_disposable'])
        ];
    }
    
    // 装備バフを追加
    $equipAttackBonus = (int)floor(($equipmentBuffs['attack'] ?? 0) * BATTLE_EQUIPMENT_ATTACK_MULTIPLIER);
    $equipArmorBonus = (int)floor(($equipmentBuffs['armor'] ?? 0) * BATTLE_EQUIPMENT_ARMOR_MULTIPLIER);
    $equipHealthBonus = (int)floor(($equipmentBuffs['health'] ?? 0) * BATTLE_EQUIPMENT_HEALTH_MULTIPLIER);
    
    // 装備ボーナスを追加（シナジーは既に個別適用済み）
    $finalAttack = $totalAttack + $equipAttackBonus;
    $finalArmor = $totalArmor + $equipArmorBonus;
    $finalHealth = $totalHealth + $equipHealthBonus;
    
    return [
        'attack' => $finalAttack,
        'armor' => $finalArmor,
        'max_health' => $finalHealth,
        'current_health' => $finalHealth,
        'troops' => $troopDetails,
        'skills' => $skills,
        'equipment_buffs' => $equipmentBuffs,
        'active_effects' => [], // 現在適用中の状態異常
        'is_frozen' => false,
        'is_stunned' => false,
        'extra_attacks' => 0,   // 加速による追加攻撃回数
        'troop_keys' => $troopKeys,  // シナジー判定用
        'domain_categories' => array_unique($domainCategories),  // 領域カテゴリ
        'synergy_messages' => $synergyMessages  // シナジー発動メッセージ
    ];
}

/**
 * ダメージを計算（乱数幅あり）
 * @param int $baseAttack 基本攻撃力
 * @param int $targetArmor 対象のアーマー
 * @param array $attackerEffects 攻撃者の状態効果
 * @param array $defenderEffects 防御者の状態効果
 * @param array $defenderData 防御側のデータ（カテゴリチェック用、オプション）
 * @return array [damage, isCritical, messages]
 */
function calculateDamage($baseAttack, $targetArmor, $attackerEffects = [], $defenderEffects = [], $defenderData = null) {
    $messages = [];
    
    // 攻撃力の調整（状態異常による）
    $attackMultiplier = 1.0;
    foreach ($attackerEffects as $effect) {
        if ($effect['skill_key'] === 'attack_up') {
            $attackMultiplier += $effect['effect_value'] / 100;
            $messages[] = "⚔️ 攻撃力上昇中 (+{$effect['effect_value']}%)";
        }
        if ($effect['skill_key'] === 'war_cry') {
            $attackMultiplier += $effect['effect_value'] / 100;
            $messages[] = "📣 雄叫び！攻撃力上昇 (+{$effect['effect_value']}%)";
        }
        if ($effect['skill_key'] === 'bloodlust') {
            $attackMultiplier += $effect['effect_value'] / 100;
            $messages[] = "🩸 血の渇望！攻撃力上昇 (+{$effect['effect_value']}%)";
        }
        
        // シナジースキル: 空カテゴリシナジー（全体適用）
        if ($effect['skill_key'] === 'air_superiority') {
            $attackMultiplier += $effect['effect_value'] / 100;
            $messages[] = "✈️ 制空権！攻撃力上昇 (+{$effect['effect_value']}%)";
        }
        
        // 対空掃射スキル：相手に空カテゴリがいる場合、攻撃力40%アップ
        if ($effect['skill_key'] === 'anti_air_barrage' && $defenderData !== null) {
            if (isset($defenderData['domain_categories']) && in_array('air', $defenderData['domain_categories'])) {
                $attackMultiplier += $effect['effect_value'] / 100;
                $messages[] = "🎯 対空掃射！空カテゴリに対して攻撃力上昇 (+{$effect['effect_value']}%)";
            }
        }
        
        // 戦車駆逐スキル：相手に陸カテゴリかつ騎兵カテゴリがいる場合、攻撃力40%アップ
        if ($effect['skill_key'] === 'tank_destroyer' && $defenderData !== null) {
            $hasLandCavalry = false;
            if (isset($defenderData['troops'])) {
                foreach ($defenderData['troops'] as $troop) {
                    if (isset($troop['domain_category']) && $troop['domain_category'] === 'land' && 
                        isset($troop['category']) && $troop['category'] === 'cavalry') {
                        $hasLandCavalry = true;
                        break;
                    }
                }
            }
            if ($hasLandCavalry) {
                $attackMultiplier += $effect['effect_value'] / 100;
                $messages[] = "🎖️ 戦車駆逐！陸上騎兵に対して攻撃力上昇 (+{$effect['effect_value']}%)";
            }
        }
    }
    foreach ($defenderEffects as $effect) {
        if ($effect['skill_key'] === 'attack_down') {
            $attackMultiplier -= $effect['effect_value'] / 100;
            $messages[] = "⬇️ 攻撃低下中 (-{$effect['effect_value']}%)";
        }
        if ($effect['skill_key'] === 'weakness') {
            $attackMultiplier -= $effect['effect_value'] / 100;
            $messages[] = "😵 弱体化中 (-{$effect['effect_value']}%)";
        }
        if ($effect['skill_key'] === 'weaken') {
            $attackMultiplier -= $effect['effect_value'] / 100;
            $messages[] = "💀 弱体化！攻撃力低下 (-{$effect['effect_value']}%)";
        }
        if ($effect['skill_key'] === 'fear') {
            $attackMultiplier -= $effect['effect_value'] / 100;
            $messages[] = "😱 恐怖！攻撃力低下 (-{$effect['effect_value']}%)";
        }
        if ($effect['skill_key'] === 'disarm') {
            $attackMultiplier -= $effect['effect_value'] / 100;
            $messages[] = "🚫 武装解除！攻撃力低下 (-{$effect['effect_value']}%)";
        }
    }
    $attackMultiplier = max(0.1, $attackMultiplier);
    
    // アーマーの調整（状態異常による）
    $armorMultiplier = 1.0;
    foreach ($attackerEffects as $effect) {
        if ($effect['skill_key'] === 'armor_harden') {
            // 自分のアーマー硬化は防御時に効果あり
        }
        if ($effect['skill_key'] === 'defense_break') {
            $armorMultiplier = 0; // アーマー無視
            $messages[] = "🔨 防御破壊！アーマー無視";
        }
    }
    foreach ($defenderEffects as $effect) {
        if ($effect['skill_key'] === 'vulnerable') {
            $armorMultiplier -= $effect['effect_value'] / 100;
            $messages[] = "💔 無防備状態 (-{$effect['effect_value']}%アーマー)";
        }
        if ($effect['skill_key'] === 'armor_crush') {
            $armorMultiplier -= $effect['effect_value'] / 100;
            $messages[] = "💔 鎧砕き！(-{$effect['effect_value']}%アーマー)";
        }
        if ($effect['skill_key'] === 'armor_harden') {
            $armorMultiplier += $effect['effect_value'] / 100;
            $messages[] = "🛡️ アーマー硬化中 (+{$effect['effect_value']}%)";
        }
        if ($effect['skill_key'] === 'defense_formation') {
            $armorMultiplier += $effect['effect_value'] / 100;
            $messages[] = "🛡️ 防御陣形！防御力上昇 (+{$effect['effect_value']}%)";
        }
        if ($effect['skill_key'] === 'fortify') {
            $armorMultiplier += $effect['effect_value'] / 100;
            $messages[] = "🛡️ 防御陣形 (+{$effect['effect_value']}%防御力)";
        }
        if ($effect['skill_key'] === 'phalanx_formation') {
            $armorMultiplier += $effect['effect_value'] / 100;
            $messages[] = "⚔️ ファランクス陣形 (+{$effect['effect_value']}%防御力)";
        }
        if ($effect['skill_key'] === 'shield_wall') {
            $armorMultiplier += $effect['effect_value'] / 100;
            $messages[] = "🔰 盾の壁 (+{$effect['effect_value']}%ダメージ軽減)";
        }
        if ($effect['skill_key'] === 'weaken') {
            $armorMultiplier -= $effect['effect_value'] / 100;
            $messages[] = "💀 弱体化！防御力低下 (-{$effect['effect_value']}%)";
        }
    }
    $armorMultiplier = max(0, $armorMultiplier);
    
    // 調整後の攻撃力
    $adjustedAttack = $baseAttack * $attackMultiplier;
    
    // 乱数幅を適用（±BATTLE_DAMAGE_VARIANCE）
    $variance = 1 + (mt_rand(-100, 100) / 100) * BATTLE_DAMAGE_VARIANCE;
    $attackWithVariance = $adjustedAttack * $variance;
    
    // クリティカル判定
    $critChance = BATTLE_BASE_CRITICAL_CHANCE;
    $critMultiplier = BATTLE_CRITICAL_MULTIPLIER;
    foreach ($attackerEffects as $effect) {
        if ($effect['skill_key'] === 'critical') {
            $critChance += $effect['effect_value'];
        }
        if ($effect['skill_key'] === 'precision') {
            $critChance += $effect['effect_value'];
            $messages[] = "🎯 精密射撃！クリティカル率上昇";
        }
        if ($effect['skill_key'] === 'precision_shot') {
            $critChance += $effect['effect_value'];
            $messages[] = "🔭 精密射撃！クリティカル率大幅上昇";
        }
    }
    // 相手に弱点露出デバフがある場合はクリティカルダメージ増加
    foreach ($defenderEffects as $effect) {
        if ($effect['skill_key'] === 'expose_weakness') {
            $critMultiplier += $effect['effect_value'] / 100;
        }
    }
    
    $isCritical = mt_rand(1, 100) <= $critChance;
    if ($isCritical) {
        $attackWithVariance *= $critMultiplier;
        $messages[] = "💥 クリティカルヒット！";
    }
    
    // アーマーによるダメージ軽減（直接引き算方式、ダメージの90%を上限）
    $effectiveArmor = $targetArmor * $armorMultiplier;
    
    // ダメージからアーマーを引く
    $damageAfterArmor = $attackWithVariance - $effectiveArmor;
    
    // 最低でも元のダメージの10%は通す（90%軽減が上限）
    $minDamage = $attackWithVariance * BATTLE_MIN_DAMAGE_PERCENTAGE;
    $finalDamage = (int)max($minDamage, $damageAfterArmor);
    
    // 絶対最小値を保証
    $finalDamage = (int)max(BATTLE_MIN_DAMAGE, $finalDamage);
    
    // 軽減率を計算（情報表示用）
    // damageAfterArmor が負の場合は、軽減されたダメージ量は攻撃力を超えている
    $damageReduced = max(0, $attackWithVariance - max(0, $damageAfterArmor));
    $armorReduction = ($attackWithVariance > 0) ? 
        min(BATTLE_MAX_ARMOR_REDUCTION_CAP, $damageReduced / $attackWithVariance) : 0;
    
    return [
        'damage' => $finalDamage,
        'is_critical' => $isCritical,
        'messages' => $messages,
        'attack_multiplier' => $attackMultiplier,
        'armor_reduction' => $armorReduction
    ];
}

/**
 * 現在アクティブな効果のスキルキーを取得
 * @param array $activeEffects アクティブな効果のリスト
 * @return array スキルキーの連想配列 [skill_key => true]
 */
function getActiveEffectKeys($activeEffects) {
    $activeEffectKeys = [];
    foreach ($activeEffects as $effect) {
        if (isset($effect['skill_key'])) {
            $activeEffectKeys[$effect['skill_key']] = true;
        }
    }
    return $activeEffectKeys;
}

/**
 * スキル発動判定と効果適用
 * @param array $unit バトルユニット
 * @param array $target ターゲットユニット
 * @param bool $isAttacker 攻撃側かどうか
 * @return array [skill_activated, effect, messages]
 */
function tryActivateSkill($unit, $target, $isAttacker) {
    $messages = [];
    $newEffects = [];
    $extraAttacks = 0;
    $heroSkillResult = null;
    
    // ① 新たに発動したスキルの数をカウント（継続バフ/デバフはカウントから除外）
    $newSkillActivations = 0;
    $maxNewActivations = defined('BATTLE_MAX_NEW_SKILL_ACTIVATIONS') ? BATTLE_MAX_NEW_SKILL_ACTIVATIONS : 3;
    
    // 現在アクティブな継続効果のスキルキーを取得（これらはカウントから除外）
    $activeEffectKeys = getActiveEffectKeys($unit['active_effects']);
    
    // ① 兵種スキルとヒーロースキルを独立して発動
    // 複数の兵種スキルが同時に発動可能（ただし新規発動は最大3つまで）
    foreach ($unit['skills'] as $skill) {
        // ① 新規スキル発動数が上限に達した場合はスキップ（ヒーロースキルは別枠）
        $isHeroSkill = !empty($skill['is_hero_skill']);
        if (!$isHeroSkill && $newSkillActivations >= $maxNewActivations) {
            continue;
        }
        
        if (mt_rand(1, 100) <= $skill['activation_chance']) {
            // ヒーロースキルの特別処理（兵種スキルとは独立して発動、カウント対象外）
            if ($isHeroSkill) {
                $heroSkillResult = processHeroSkillEffect($skill, $unit, $target);
                $messages = array_merge($messages, $heroSkillResult['messages']);
                $newEffects = array_merge($newEffects, $heroSkillResult['attacker_effects']);
                // 敵へのデバフは呼び出し元で処理
                continue;
            }
            
            // ① 既にアクティブな継続バフ/デバフと同じスキルはカウント対象外
            $isAlreadyActive = isset($activeEffectKeys[$skill['skill_key']]);
            
            // 継続スキル（duration_turns >= SYNERGY_SKILL_DURATION_THRESHOLD）が既にアクティブな場合はスキップ
            if ($isAlreadyActive && (int)$skill['duration_turns'] >= SYNERGY_SKILL_DURATION_THRESHOLD) {
                continue; // 重複発動を防ぐため、既にアクティブな継続スキルは追加しない
            }
            
            $effect = [
                'skill_key' => $skill['skill_key'],
                'skill_name' => $skill['skill_name'],
                'skill_icon' => $skill['skill_icon'],
                'effect_type' => $skill['effect_type'],
                'effect_target' => $skill['effect_target'],
                'effect_value' => $skill['effect_value'],
                'remaining_turns' => $skill['duration_turns'],
                'troop_name' => $skill['troop_name'],
                'troop_icon' => $skill['troop_icon']
            ];
            
            $messages[] = "{$skill['troop_icon']} {$skill['troop_name']}が「{$skill['skill_icon']} {$skill['skill_name']}」を発動！";
            
            // 加速スキルの特別処理
            if ($skill['skill_key'] === 'acceleration') {
                $extraAttacks += (int)$skill['effect_value'] - 1;
                $messages[] = "⚡ 加速！{$skill['effect_value']}回連続攻撃！";
            }
            // 量子戦スキル（5%で敵HP半減）
            else if ($skill['skill_key'] === 'quantum_warfare') {
                $halfDamage = (int)floor($target['current_health'] / 2);
                $effect['instant_damage'] = $halfDamage;
                $effect['effect_type'] = 'instant_damage';
                $newEffects[] = $effect;
                $messages[] = "⚛️ 量子戦発動！敵のHPを半減（{$halfDamage}ダメージ）！";
            }
            // 寝返りスキル（敵にダメージを与えその分回復）
            else if ($skill['skill_key'] === 'defection') {
                $defectionDamage = (int)floor($skill['troop_attack_power'] * ($skill['effect_value'] / 100));
                $effect['instant_damage'] = $defectionDamage;
                $effect['instant_heal'] = $defectionDamage;
                $effect['effect_type'] = 'drain';
                $newEffects[] = $effect;
                $messages[] = "🕵️ 寝返り発動！{$defectionDamage}ダメージを与え、その分回復！";
            }
            // 反射スキル（受けた攻撃をそのまま跳ね返す）
            else if ($skill['skill_key'] === 'agitation') {
                $effect['effect_type'] = 'reflect';
                $newEffects[] = $effect;
                $messages[] = "⛵ 扇動発動！受けた攻撃を跳ね返す！";
            }
            // 反撃スキル（ダメージを受けた時に反撃）
            else if ($skill['skill_key'] === 'counter') {
                $effect['effect_type'] = 'counter';
                $newEffects[] = $effect;
                $messages[] = "⚔️ 反撃構え！ダメージを受けた時に反撃する！";
            }
            // 回避スキル（攻撃を回避）
            else if ($skill['skill_key'] === 'evasion') {
                $effect['effect_type'] = 'evasion';
                $newEffects[] = $effect;
                $messages[] = "💨 回避体制！攻撃を回避する確率が上昇！";
            }
            // 鼓舞スキル（味方全体の攻撃力上昇）
            else if ($skill['skill_key'] === 'inspire') {
                $effect['effect_type'] = 'buff';
                $newEffects[] = $effect;
                $messages[] = "📣 鼓舞！味方全体の攻撃力を上昇させる！";
            }
            // 放射能攻撃（継続ダメージ - 戦闘終了まで継続）
            else if ($skill['skill_key'] === 'radiation_attack') {
                $effect['effect_type'] = 'damage_over_time';
                $effect['remaining_turns'] = SYNERGY_SKILL_DURATION_THRESHOLD; // 戦闘終了まで継続
                $newEffects[] = $effect;
                $messages[] = "☢️ 放射能攻撃！敵に継続的な放射能ダメージを与える！";
            }
            // サイバー攻撃（デバフ）
            else if ($skill['skill_key'] === 'cyber_attack') {
                $effect['effect_type'] = 'debuff';
                $newEffects[] = $effect;
                $messages[] = "💻 サイバー攻撃！敵のシステムを麻痺させる！";
            }
            // ドローン一斉攻撃（即時ダメージ）
            else if ($skill['skill_key'] === 'drone_barrage') {
                $barrageDamage = (int)floor($skill['troop_attack_power'] * ($skill['effect_value'] / 100));
                $effect['instant_damage'] = $barrageDamage;
                $effect['effect_type'] = 'instant_damage';
                $newEffects[] = $effect;
                $messages[] = "🚁 ドローン一斉攻撃！{$barrageDamage}ダメージ！";
            }
            // バイラルプロパガンダ（デバフ）
            else if ($skill['skill_key'] === 'viral_propaganda') {
                $effect['effect_type'] = 'debuff';
                $newEffects[] = $effect;
                $messages[] = "📲 バイラルプロパガンダ！敵の士気を下げる！";
            }
            // 電子妨害（デバフ）
            else if ($skill['skill_key'] === 'electronic_jamming') {
                $effect['effect_type'] = 'debuff';
                $newEffects[] = $effect;
                $messages[] = "📡 電子妨害！敵のスキル発動率を下げる！";
            }
            // 量子トンネル効果（即時ダメージ、防御無視）
            else if ($skill['skill_key'] === 'quantum_tunneling') {
                $tunnelingDamage = (int)floor($skill['troop_attack_power'] * ($skill['effect_value'] / 100));
                $effect['instant_damage'] = $tunnelingDamage;
                $effect['effect_type'] = 'instant_damage';
                $effect['ignore_defense'] = true;
                $newEffects[] = $effect;
                $messages[] = "🌀 量子トンネル効果！防御を無視して{$tunnelingDamage}ダメージ！";
            }
            // 遺伝子強化（バフ）
            else if ($skill['skill_key'] === 'gene_enhancement') {
                $effect['effect_type'] = 'buff';
                $newEffects[] = $effect;
                $messages[] = "💪 遺伝子強化！能力を大幅強化！";
            }
            // 生体再生（回復）
            else if ($skill['skill_key'] === 'bio_regeneration') {
                $regenHeal = (int)floor($unit['max_health'] * ($skill['effect_value'] / 100));
                $effect['instant_heal'] = $regenHeal;
                $effect['effect_type'] = 'heal';
                $newEffects[] = $effect;
                $messages[] = "🧬 生体再生！{$regenHeal}回復！";
            }
            // 疫病散布（継続ダメージ + デバフ）
            else if ($skill['skill_key'] === 'plague_release') {
                $effect['effect_type'] = 'damage_over_time';
                $newEffects[] = $effect;
                $messages[] = "🦠 疫病散布！敵に継続ダメージと弱体化！";
            }
            // 反物質爆発（大ダメージ）
            else if ($skill['skill_key'] === 'antimatter_explosion') {
                $explosionDamage = (int)floor($skill['troop_attack_power'] * ($skill['effect_value'] / 100));
                $effect['instant_damage'] = $explosionDamage;
                $effect['effect_type'] = 'instant_damage';
                $newEffects[] = $effect;
                $messages[] = "💥 反物質爆発！巨大なダメージ{$explosionDamage}！";
            }
            // ワープストライク（即時ダメージ）
            else if ($skill['skill_key'] === 'warp_strike') {
                $warpDamage = (int)floor($skill['troop_attack_power'] * ($skill['effect_value'] / 100));
                $effect['instant_damage'] = $warpDamage;
                $effect['effect_type'] = 'instant_damage';
                $newEffects[] = $effect;
                $messages[] = "🛸 ワープストライク！瞬間移動で{$warpDamage}ダメージ！";
            }
            // スマート照準（クリティカル率上昇）
            else if ($skill['skill_key'] === 'smart_targeting') {
                $effect['effect_type'] = 'critical';
                $newEffects[] = $effect;
                $messages[] = "🎯 スマート照準！クリティカル率大幅上昇！";
            }
            // 自動修復（継続回復）
            else if ($skill['skill_key'] === 'auto_repair') {
                $effect['effect_type'] = 'hot'; // heal over time
                $newEffects[] = $effect;
                $messages[] = "🔧 自動修復！ダメージを自動で回復！";
            }
            // 爆弾投下（海カテゴリにダメージ倍増）
            else if ($skill['skill_key'] === 'bomb_drop') {
                // 敵が海カテゴリかチェック
                if (isset($target['domain_categories']) && in_array('sea', $target['domain_categories'])) {
                    // effect_value = 100 → 100%増加 = 2倍
                    $multiplier = 1 + ($skill['effect_value'] / 100);
                    $bombDamage = (int)floor($skill['troop_attack_power'] * $multiplier);
                    $effect['instant_damage'] = $bombDamage;
                    $effect['effect_type'] = 'instant_damage';
                    $newEffects[] = $effect;
                    $messages[] = "💣 爆弾投下！海カテゴリに{$bombDamage}ダメージ！";
                }
            }
            // レーザー照射（空カテゴリにダメージ倍増）
            else if ($skill['skill_key'] === 'laser_irradiation') {
                // 敵が空カテゴリかチェック
                if (isset($target['domain_categories']) && in_array('air', $target['domain_categories'])) {
                    // effect_value = 100 → 100%増加 = 2倍
                    $multiplier = 1 + ($skill['effect_value'] / 100);
                    $laserDamage = (int)floor($skill['troop_attack_power'] * $multiplier);
                    $effect['instant_damage'] = $laserDamage;
                    $effect['effect_type'] = 'instant_damage';
                    $newEffects[] = $effect;
                    $messages[] = "🔦 レーザー照射！空カテゴリに{$laserDamage}ダメージ！";
                }
            }
            // 散弾発射（陸カテゴリにダメージ倍増）
            else if ($skill['skill_key'] === 'shrapnel_fire') {
                // 敵が陸カテゴリかチェック
                if (isset($target['domain_categories']) && in_array('land', $target['domain_categories'])) {
                    // effect_value = 100 → 100%増加 = 2倍
                    $multiplier = 1 + ($skill['effect_value'] / 100);
                    $shrapnelDamage = (int)floor($skill['troop_attack_power'] * $multiplier);
                    $effect['instant_damage'] = $shrapnelDamage;
                    $effect['effect_type'] = 'instant_damage';
                    $newEffects[] = $effect;
                    $messages[] = "💥 散弾発射！陸カテゴリに{$shrapnelDamage}ダメージ！";
                }
            }
            // 投石（アーマー貫通ダメージ）
            else if ($skill['skill_key'] === 'stone_throw') {
                // effect_value = 100 → 100%の攻撃力
                $stoneDamage = (int)floor($skill['troop_attack_power'] * ($skill['effect_value'] / 100));
                $effect['instant_damage'] = $stoneDamage;
                $effect['effect_type'] = 'instant_damage';
                $effect['ignore_defense'] = true; // アーマー貫通
                $newEffects[] = $effect;
                $messages[] = "🪨 投石！アーマーを貫通して{$stoneDamage}ダメージ！";
            }
            // 自律飛行（3回連続攻撃）
            else if ($skill['skill_key'] === 'autonomous_flight') {
                // effect_value = 3 → 3回攻撃
                $extraAttacks += (int)$skill['effect_value'] - 1; // 通常の1回 + 追加(3-1)回
                $messages[] = "🚀 自律飛行！{$skill['effect_value']}回連続攻撃！";
            }
            // 核武装解除（核カテゴリに大ダメージ）
            else if ($skill['skill_key'] === 'nuclear_disarm') {
                // 敵に核カテゴリのユニットがいるかチェック
                $hasNuclearUnit = false;
                if (isset($target['troops'])) {
                    foreach ($target['troops'] as $troop) {
                        // 核カテゴリユニット（nuclear_submarine, icbm, nuclear_bomberなど）
                        if (isset($troop['troop_key']) && 
                            (strpos($troop['troop_key'], 'nuclear') !== false || 
                             $troop['troop_key'] === 'icbm' || 
                             $troop['troop_key'] === 'nuclear_bomber')) {
                            $hasNuclearUnit = true;
                            break;
                        }
                    }
                }
                if ($hasNuclearUnit) {
                    // effect_value = 100 → 100%増加 = 2倍
                    $multiplier = 1 + ($skill['effect_value'] / 100);
                    $nuclearDamage = (int)floor($skill['troop_attack_power'] * $multiplier);
                    $effect['instant_damage'] = $nuclearDamage;
                    $effect['effect_type'] = 'instant_damage';
                    $newEffects[] = $effect;
                    $messages[] = "☢️ 核武装解除！核ユニットに{$nuclearDamage}ダメージ！";
                }
            }
            else {
                $newEffects[] = $effect;
            }
            
            // ① スキル発動数のカウント制御
            // 以下のタイプはカウント対象外:
            // - 既にアクティブな継続効果（バフ/デバフ）
            // - 即時ダメージスキル（instant_damage, damage, drain）
            // - 回復スキル（heal, hot）
            // - 継続ダメージ（damage_over_time, dot, nuclear_dot）
            // - シナジースキル（duration_turns が 99 の buff/debuff）
            $shouldCount = true;
            
            // 既にアクティブな継続効果はカウントしない
            if ($isAlreadyActive) {
                $shouldCount = false;
            }
            // 即時ダメージ系はカウントしない
            else if (isset($effect['instant_damage']) || in_array($effect['effect_type'], ['instant_damage', 'damage', 'drain'])) {
                $shouldCount = false;
            }
            // 回復系はカウントしない
            else if (isset($effect['instant_heal']) || in_array($effect['effect_type'], ['heal', 'hot'])) {
                $shouldCount = false;
            }
            // 継続ダメージ（放射能含む）はカウントしない
            else if (in_array($effect['effect_type'], ['damage_over_time', 'dot', 'nuclear_dot'])) {
                $shouldCount = false;
            }
            // シナジースキル（duration_turns が SYNERGY_SKILL_DURATION_THRESHOLD 以上）はカウントしない
            else if (isset($effect['remaining_turns']) && $effect['remaining_turns'] >= SYNERGY_SKILL_DURATION_THRESHOLD) {
                $shouldCount = false;
            }
            
            if ($shouldCount) {
                $newSkillActivations++;
            }
            
            // 複数の兵種スキルが発動可能（breakを削除）
        }
    }
    
    return [
        'effects' => $newEffects,
        'messages' => $messages,
        'extra_attacks' => $extraAttacks,
        'hero_skill_result' => $heroSkillResult
    ];
}

/**
 * 継続ダメージを計算（毒、燃焼など）
 * 平方根スケーリングを使用して、兵数/HPが増えても緩やかに増加
 * @param int $maxHealth ユニットの最大HP
 * @param float $effectValue 効果値（パーセンテージ）
 * @return int 計算されたダメージ
 */
function calculateDoTDamage($maxHealth, $effectValue) {
    // ゼロ除算防止
    $baseHealth = max(1, BATTLE_DOT_BASE_HEALTH);
    
    // HP比率を計算（最大HP / 基準HP）
    $healthRatio = $maxHealth / $baseHealth;
    
    // 平方根スケーリング係数を適用
    $scalingFactor = sqrt($healthRatio);
    
    // 効果値のパーセンテージを適用
    $effectMultiplier = $effectValue / 100;
    
    // 最終ダメージ計算
    $baseDamage = $baseHealth * $scalingFactor * BATTLE_DOT_SCALING_FACTOR * $effectMultiplier;
    $dotDamage = (int)floor($baseDamage);
    
    // 最小ダメージを保証
    return max(BATTLE_MIN_DAMAGE, $dotDamage);
}

/**
 * 継続ダメージを処理（毒、燃焼、核汚染など）
 * 兵数やHPが増えても継続ダメージが比例して大きくならないよう、
 * 平方根スケーリングを使用して調整
 * @param array $unit ユニット
 * @return array [damage, heal, messages, updated_effects]
 */
function processDamageOverTime($unit) {
    $totalDamage = 0;
    $totalHeal = 0;
    $messages = [];
    $updatedEffects = [];
    
    foreach ($unit['active_effects'] as $effect) {
        // 全ての継続ダメージ系効果タイプを処理
        // damage_over_time, dot, nuclear_dot を統一的に処理
        // 'dot' は damage_over_time の短縮形として使用される
        if (in_array($effect['effect_type'], ['damage_over_time', 'dot', 'nuclear_dot'])) {
            // スキルキーによる特別処理
            $skillKey = $effect['skill_key'] ?? '';
            
            // 核汚染系は固定ダメージ（兵数に応じて上限あり）
            // skill_keyまたはeffect_typeで判定
            if ($skillKey === 'nuclear_contamination' || $effect['effect_type'] === 'nuclear_dot') {
                $baseDamage = $effect['effect_value'];
                // 最大HPに応じてスケール（ただし上限50000ダメージ）
                $nuclearDamage = min(50000, max($baseDamage, (int)floor(sqrt($unit['max_health']) * 2)));
                $totalDamage += $nuclearDamage;
                $messages[] = "{$effect['skill_icon']} {$effect['skill_name']}により{$nuclearDamage}の放射能ダメージ！";
            } else {
                // その他の継続ダメージは平方根スケーリングを使用
                $dotDamage = calculateDoTDamage($unit['max_health'], $effect['effect_value']);
                $totalDamage += $dotDamage;
                $messages[] = "{$effect['skill_icon']} {$effect['skill_name']}により{$dotDamage}ダメージ！";
            }
        }
        // 継続回復処理 (hot = heal over time)
        else if ($effect['effect_type'] === 'hot') {
            $hotHeal = (int)floor($unit['max_health'] * ($effect['effect_value'] / 100));
            // 回復量のキャップを適用
            $maxHeal = max(
                HERO_SKILL_MIN_HEAL_CAP,
                (int)floor($unit['max_health'] * HERO_SKILL_HEAL_RATIO_CAP)
            );
            $hotHeal = min($hotHeal, $maxHeal);
            $totalHeal += $hotHeal;
            $messages[] = "{$effect['skill_icon']} {$effect['skill_name']}により{$hotHeal}回復！";
        }
        
        // 効果ターン減少
        $effect['remaining_turns']--;
        if ($effect['remaining_turns'] > 0) {
            $updatedEffects[] = $effect;
        } else {
            $messages[] = "{$effect['skill_icon']} {$effect['skill_name']}の効果が切れた";
        }
    }
    
    return [
        'damage' => $totalDamage,
        'heal' => $totalHeal,
        'messages' => $messages,
        'updated_effects' => $updatedEffects
    ];
}

/**
 * シナジースキルを全て発動（ターン1のみ、スキル発動上限には含めない）
 * duration_turns が SYNERGY_SKILL_DURATION_THRESHOLD 以上のスキルを自動発動
 * @param array $unit バトルユニット
 * @param array $target ターゲットユニット
 * @return array [effects, messages]
 */
function activateSynergySkills($unit, $target) {
    $messages = [];
    $newEffects = [];
    
    // 現在アクティブなスキルキーを取得（重複発動防止用）
    $activeEffectKeys = getActiveEffectKeys($unit['active_effects']);
    
    // duration_turns が SYNERGY_SKILL_DURATION_THRESHOLD 以上のスキルをシナジースキルとして判定
    foreach ($unit['skills'] as $skill) {
        // ヒーロースキルは除外
        if (!empty($skill['is_hero_skill'])) {
            continue;
        }
        
        // シナジースキル（duration_turns >= SYNERGY_SKILL_DURATION_THRESHOLD）のみを対象
        if ((int)$skill['duration_turns'] >= SYNERGY_SKILL_DURATION_THRESHOLD) {
            // 既にアクティブなシナジースキルはスキップ（重複発動防止）
            if (isset($activeEffectKeys[$skill['skill_key']])) {
                continue;
            }
            
            // シナジー条件をチェック
            // 条件付きシナジースキル: submarine_synergy, marine_synergy, air_superiority
            // submarine_synergy と marine_synergy は prepareBattleUnit で既に適用済みなので、
            // ここでは発動しない（全体適用を防ぐため）
            // air_superiority のみ全体適用のためここで発動
            // これら以外の長期継続スキル（例: radiation_attack）は常に発動
            $shouldActivate = false;
            
            // 潜水艦シナジー: prepareBattleUnit で既に適用済みなのでスキップ
            if ($skill['skill_key'] === 'submarine_synergy') {
                $shouldActivate = false;
            }
            // 海兵隊シナジー: prepareBattleUnit で既に適用済みなのでスキップ
            else if ($skill['skill_key'] === 'marine_synergy') {
                $shouldActivate = false;
            }
            // 空カテゴリシナジー（強襲型空母）: 空カテゴリが同時出撃している必要あり（全体適用）
            else if ($skill['skill_key'] === 'air_superiority') {
                if (in_array('air', $unit['domain_categories'])) {
                    $shouldActivate = true;
                }
            }
            // その他の長期継続スキル（放射能攻撃など）は条件なしで発動
            else if (!in_array($skill['skill_key'], ['submarine_synergy', 'marine_synergy', 'air_superiority'])) {
                $shouldActivate = true;
            }
            
            if ($shouldActivate) {
                $effect = [
                    'skill_key' => $skill['skill_key'],
                    'skill_name' => $skill['skill_name'],
                    'skill_icon' => $skill['skill_icon'],
                    'effect_type' => $skill['effect_type'],
                    'effect_target' => $skill['effect_target'],
                    'effect_value' => $skill['effect_value'],
                    'remaining_turns' => $skill['duration_turns'],
                    'troop_name' => $skill['troop_name'],
                    'troop_icon' => $skill['troop_icon']
                ];
                
                $newEffects[] = $effect;
                $messages[] = "{$skill['troop_icon']} {$skill['troop_name']}が「{$skill['skill_icon']} {$skill['skill_name']}」を発動！";
            }
        }
    }
    
    return [
        'effects' => $newEffects,
        'messages' => $messages
    ];
}

/**
 * ターン制バトルを実行
 * @param array $attacker 攻撃側ユニット
 * @param array $defender 防御側ユニット
 * @param int|null $maxTurns 最大ターン数（nullの場合はBATTLE_MAX_TURNSを使用）
 * @return array バトル結果
 */
function executeTurnBattle($attacker, $defender, $maxTurns = null) {
    $turnLogs = [];
    $currentTurn = 0;
    $battleSummary = [];
    $maxTurnsLimit = $maxTurns ?? BATTLE_MAX_TURNS;
    
    // バトルループ
    while ($attacker['current_health'] > 0 && $defender['current_health'] > 0 && $currentTurn < $maxTurnsLimit) {
        $currentTurn++;
        $turnMessages = [];
        $turnMessages[] = "===== ターン {$currentTurn} =====";
        
        // ターン1でシナジースキルを全て発動（スキル発動上限の3つには含めない）
        if ($currentTurn === 1) {
            // 攻撃側のシナジースキル発動
            $attackerSynergyResult = activateSynergySkills($attacker, $defender);
            if (!empty($attackerSynergyResult['messages'])) {
                $turnMessages = array_merge($turnMessages, $attackerSynergyResult['messages']);
            }
            foreach ($attackerSynergyResult['effects'] as $effect) {
                $attacker['active_effects'][] = $effect;
            }
            // 事前適用されたシナジーメッセージも表示
            if (!empty($attacker['synergy_messages'])) {
                $turnMessages = array_merge($turnMessages, $attacker['synergy_messages']);
            }
            
            // 防御側のシナジースキル発動
            $defenderSynergyResult = activateSynergySkills($defender, $attacker);
            if (!empty($defenderSynergyResult['messages'])) {
                $turnMessages = array_merge($turnMessages, $defenderSynergyResult['messages']);
            }
            foreach ($defenderSynergyResult['effects'] as $effect) {
                $defender['active_effects'][] = $effect;
            }
            // 事前適用されたシナジーメッセージも表示
            if (!empty($defender['synergy_messages'])) {
                $turnMessages = array_merge($turnMessages, $defender['synergy_messages']);
            }
        }
        
        // --- 攻撃側のターン ---
        $attackerFrozen = false;
        $attackerStunned = false;
        
        // 凍結/スタンチェック
        foreach ($attacker['active_effects'] as $effect) {
            if ($effect['skill_key'] === 'freeze' && $effect['remaining_turns'] > 0) {
                $attackerFrozen = true;
                $turnMessages[] = "❄️ 攻撃側は凍結中！行動不能";
            }
            if ($effect['skill_key'] === 'stun' && $effect['remaining_turns'] > 0) {
                $attackerStunned = true;
                $turnMessages[] = "💫 攻撃側はスタン中！行動不能";
            }
        }
        
        if (!$attackerFrozen && !$attackerStunned) {
            // 継続ダメージ/回復処理
            $dotResult = processDamageOverTime($attacker);
            if ($dotResult['damage'] > 0) {
                $attacker['current_health'] -= $dotResult['damage'];
            }
            if ($dotResult['heal'] > 0) {
                $attacker['current_health'] = min($attacker['max_health'], $attacker['current_health'] + $dotResult['heal']);
            }
            $turnMessages = array_merge($turnMessages, $dotResult['messages']);
            $attacker['active_effects'] = $dotResult['updated_effects'];
            
            if ($attacker['current_health'] <= 0) {
                $turnMessages[] = "☠️ 攻撃側は継続ダメージで敗北！";
                $turnLogs[] = [
                    'turn' => $currentTurn,
                    'actor' => 'attacker',
                    'action' => 'defeat',
                    'messages' => $turnMessages,
                    'attacker_hp' => 0,
                    'defender_hp' => $defender['current_health']
                ];
                break;
            }
            
            // スキル発動判定
            $skillResult = tryActivateSkill($attacker, $defender, true);
            $turnMessages = array_merge($turnMessages, $skillResult['messages']);
            
            // 新しい効果を適用
            foreach ($skillResult['effects'] as $effect) {
                // 即時ダメージ効果（量子戦など）
                if (isset($effect['effect_type']) && $effect['effect_type'] === 'instant_damage') {
                    $instantDamage = $effect['instant_damage'] ?? 0;
                    $defender['current_health'] -= $instantDamage;
                    $defender['current_health'] = max(0, $defender['current_health']);
                    $turnMessages[] = "防御側HP: {$defender['current_health']}/{$defender['max_health']}";
                }
                // 即時回復効果（生体再生など）
                else if (isset($effect['effect_type']) && $effect['effect_type'] === 'heal') {
                    $instantHeal = $effect['instant_heal'] ?? 0;
                    $attacker['current_health'] = min($attacker['max_health'], $attacker['current_health'] + $instantHeal);
                    $turnMessages[] = "攻撃側HP: {$attacker['current_health']}/{$attacker['max_health']}";
                }
                // 吸収効果（寝返りなど）
                else if (isset($effect['effect_type']) && $effect['effect_type'] === 'drain') {
                    $drainDamage = $effect['instant_damage'] ?? 0;
                    $drainHeal = $effect['instant_heal'] ?? 0;
                    $defender['current_health'] -= $drainDamage;
                    $defender['current_health'] = max(0, $defender['current_health']);
                    $attacker['current_health'] = min($attacker['max_health'], $attacker['current_health'] + $drainHeal);
                    $turnMessages[] = "防御側HP: {$defender['current_health']}/{$defender['max_health']}";
                    $turnMessages[] = "攻撃側HP: {$attacker['current_health']}/{$attacker['max_health']}";
                }
                // 反射効果（扇動など）は継続効果として追加
                else if ($effect['effect_target'] === 'self') {
                    $attacker['active_effects'][] = $effect;
                } else if ($effect['effect_target'] === 'enemy') {
                    $defender['active_effects'][] = $effect;
                }
            }
            
            // ヒーロースキルの効果を適用（ダメージ/回復/敵へのデバフ）
            if ($skillResult['hero_skill_result']) {
                $heroResult = $skillResult['hero_skill_result'];
                
                // ヒーロースキルのダメージを適用
                if ($heroResult['damage'] > 0) {
                    $defender['current_health'] -= $heroResult['damage'];
                    $defender['current_health'] = max(0, $defender['current_health']);
                    $turnMessages[] = "🦸 ヒーロースキル: {$heroResult['damage']}ダメージを与えた！";
                    $turnMessages[] = "防御側HP: {$defender['current_health']}/{$defender['max_health']}";
                }
                
                // ヒーロースキルの回復を適用
                if ($heroResult['heal'] > 0) {
                    $attacker['current_health'] = min($attacker['max_health'], $attacker['current_health'] + $heroResult['heal']);
                    $turnMessages[] = "🦸 ヒーロースキル: {$heroResult['heal']}回復！";
                    $turnMessages[] = "攻撃側HP: {$attacker['current_health']}/{$attacker['max_health']}";
                }
                
                // ヒーロースキルの敵へのデバフを適用
                if (!empty($heroResult['defender_effects'])) {
                    foreach ($heroResult['defender_effects'] as $effect) {
                        $defender['active_effects'][] = $effect;
                    }
                }
            }
            
            // 攻撃回数（通常 + 加速）
            $attackCount = 1 + $skillResult['extra_attacks'];
            
            for ($i = 0; $i < $attackCount; $i++) {
                if ($defender['current_health'] <= 0) break;
                
                // 回避スキルチェック（防御側）
                $evaded = false;
                foreach ($defender['active_effects'] as $evasionEffect) {
                    if (isset($evasionEffect['skill_key']) && $evasionEffect['skill_key'] === 'evasion') {
                        $evasionChance = $evasionEffect['effect_value'] ?? 35;
                        if (mt_rand(1, 100) <= $evasionChance) {
                            $evaded = true;
                            $attackNum = $i + 1;
                            $attackLabel = $attackCount > 1 ? "[攻撃{$attackNum}] " : "";
                            $turnMessages[] = "{$attackLabel}💨 回避！攻撃を回避した！";
                            break;
                        }
                    }
                }
                
                if ($evaded) {
                    continue; // 回避した場合はダメージ計算をスキップ
                }
                
                // ダメージ計算
                $damageResult = calculateDamage(
                    $attacker['attack'],
                    $defender['armor'],
                    $attacker['active_effects'],
                    $defender['active_effects'],
                    $defender  // 防御側のデータを渡す（カテゴリチェック用）
                );
                
                $defender['current_health'] -= $damageResult['damage'];
                $defender['current_health'] = max(0, $defender['current_health']);
                
                // 反撃スキルチェック（防御側）
                foreach ($defender['active_effects'] as $counterEffect) {
                    if (isset($counterEffect['skill_key']) && $counterEffect['skill_key'] === 'counter') {
                        $counterChance = $counterEffect['activation_chance'] ?? 30;
                        if (mt_rand(1, 100) <= $counterChance) {
                            $counterDamage = (int)floor($damageResult['damage'] * ($counterEffect['effect_value'] / 100));
                            $attacker['current_health'] -= $counterDamage;
                            $attacker['current_health'] = max(0, $attacker['current_health']);
                            $turnMessages[] = "⚔️ 反撃！{$counterDamage}ダメージを与えた！";
                            $turnMessages[] = "攻撃側HP: {$attacker['current_health']}/{$attacker['max_health']}";
                        }
                    }
                }
                
                // 反射効果チェック（防御側）
                foreach ($defender['active_effects'] as $reflectEffect) {
                    if (isset($reflectEffect['effect_type']) && $reflectEffect['effect_type'] === 'reflect') {
                        $reflectDamage = $damageResult['damage'];
                        $attacker['current_health'] -= $reflectDamage;
                        $attacker['current_health'] = max(0, $attacker['current_health']);
                        $turnMessages[] = "⛵ 反射！{$reflectDamage}ダメージを跳ね返した！";
                        $turnMessages[] = "攻撃側HP: {$attacker['current_health']}/{$attacker['max_health']}";
                    }
                }
                
                $attackNum = $i + 1;
                $attackLabel = $attackCount > 1 ? "[攻撃{$attackNum}] " : "";
                $turnMessages[] = "{$attackLabel}⚔️ 攻撃側が{$damageResult['damage']}ダメージを与えた！";
                $turnMessages = array_merge($turnMessages, $damageResult['messages']);
                $turnMessages[] = "防御側HP: {$defender['current_health']}/{$defender['max_health']}";
            }
            
            // 回復スキルチェック
            foreach ($attacker['active_effects'] as $effect) {
                if ($effect['skill_key'] === 'heal') {
                    $healAmount = (int)floor($attacker['max_health'] * ($effect['effect_value'] / 100));
                    $attacker['current_health'] = min($attacker['max_health'], $attacker['current_health'] + $healAmount);
                    $turnMessages[] = "💚 攻撃側が{$healAmount}回復！";
                }
            }
        }
        
        // 効果ターン減少（凍結/スタン）
        $newAttackerEffects = [];
        foreach ($attacker['active_effects'] as $effect) {
            if (in_array($effect['skill_key'], ['freeze', 'stun'])) {
                $effect['remaining_turns']--;
            }
            if ($effect['remaining_turns'] > 0) {
                $newAttackerEffects[] = $effect;
            }
        }
        $attacker['active_effects'] = $newAttackerEffects;
        
        if ($defender['current_health'] <= 0) {
            $turnMessages[] = "🏆 攻撃側の勝利！";
            $turnLogs[] = [
                'turn' => $currentTurn,
                'actor' => 'attacker',
                'action' => 'attack',
                'messages' => $turnMessages,
                'attacker_hp' => $attacker['current_health'],
                'defender_hp' => 0
            ];
            break;
        }
        
        // --- 防御側のターン ---
        $defenderFrozen = false;
        $defenderStunned = false;
        
        // 凍結/スタンチェック
        foreach ($defender['active_effects'] as $effect) {
            if ($effect['skill_key'] === 'freeze' && $effect['remaining_turns'] > 0) {
                $defenderFrozen = true;
                $turnMessages[] = "❄️ 防御側は凍結中！行動不能";
            }
            if ($effect['skill_key'] === 'stun' && $effect['remaining_turns'] > 0) {
                $defenderStunned = true;
                $turnMessages[] = "💫 防御側はスタン中！行動不能";
            }
        }
        
        if (!$defenderFrozen && !$defenderStunned) {
            // 継続ダメージ/回復処理
            $dotResult = processDamageOverTime($defender);
            if ($dotResult['damage'] > 0) {
                $defender['current_health'] -= $dotResult['damage'];
            }
            if ($dotResult['heal'] > 0) {
                $defender['current_health'] = min($defender['max_health'], $defender['current_health'] + $dotResult['heal']);
            }
            $turnMessages = array_merge($turnMessages, $dotResult['messages']);
            $defender['active_effects'] = $dotResult['updated_effects'];
            
            if ($defender['current_health'] <= 0) {
                $turnMessages[] = "☠️ 防御側は継続ダメージで敗北！";
                $turnLogs[] = [
                    'turn' => $currentTurn,
                    'actor' => 'defender',
                    'action' => 'defeat',
                    'messages' => $turnMessages,
                    'attacker_hp' => $attacker['current_health'],
                    'defender_hp' => 0
                ];
                break;
            }
            
            // スキル発動判定
            $skillResult = tryActivateSkill($defender, $attacker, false);
            $turnMessages = array_merge($turnMessages, $skillResult['messages']);
            
            // 新しい効果を適用
            foreach ($skillResult['effects'] as $effect) {
                // 即時ダメージ効果（量子戦など）
                if (isset($effect['effect_type']) && $effect['effect_type'] === 'instant_damage') {
                    $instantDamage = $effect['instant_damage'] ?? 0;
                    $attacker['current_health'] -= $instantDamage;
                    $attacker['current_health'] = max(0, $attacker['current_health']);
                    $turnMessages[] = "攻撃側HP: {$attacker['current_health']}/{$attacker['max_health']}";
                }
                // 即時回復効果（生体再生など）
                else if (isset($effect['effect_type']) && $effect['effect_type'] === 'heal') {
                    $instantHeal = $effect['instant_heal'] ?? 0;
                    $defender['current_health'] = min($defender['max_health'], $defender['current_health'] + $instantHeal);
                    $turnMessages[] = "防御側HP: {$defender['current_health']}/{$defender['max_health']}";
                }
                // 吸収効果（寝返りなど）
                else if (isset($effect['effect_type']) && $effect['effect_type'] === 'drain') {
                    $drainDamage = $effect['instant_damage'] ?? 0;
                    $drainHeal = $effect['instant_heal'] ?? 0;
                    $attacker['current_health'] -= $drainDamage;
                    $attacker['current_health'] = max(0, $attacker['current_health']);
                    $defender['current_health'] = min($defender['max_health'], $defender['current_health'] + $drainHeal);
                    $turnMessages[] = "攻撃側HP: {$attacker['current_health']}/{$attacker['max_health']}";
                    $turnMessages[] = "防御側HP: {$defender['current_health']}/{$defender['max_health']}";
                }
                // 反射効果（扇動など）は継続効果として追加
                else if ($effect['effect_target'] === 'self') {
                    $defender['active_effects'][] = $effect;
                } else if ($effect['effect_target'] === 'enemy') {
                    $attacker['active_effects'][] = $effect;
                }
            }
            
            // ヒーロースキルの効果を適用（ダメージ/回復/敵へのデバフ）
            if ($skillResult['hero_skill_result']) {
                $heroResult = $skillResult['hero_skill_result'];
                
                // ヒーロースキルのダメージを適用
                if ($heroResult['damage'] > 0) {
                    $attacker['current_health'] -= $heroResult['damage'];
                    $attacker['current_health'] = max(0, $attacker['current_health']);
                    $turnMessages[] = "🦸 ヒーロースキル: {$heroResult['damage']}ダメージを与えた！";
                    $turnMessages[] = "攻撃側HP: {$attacker['current_health']}/{$attacker['max_health']}";
                }
                
                // ヒーロースキルの回復を適用
                if ($heroResult['heal'] > 0) {
                    $defender['current_health'] = min($defender['max_health'], $defender['current_health'] + $heroResult['heal']);
                    $turnMessages[] = "🦸 ヒーロースキル: {$heroResult['heal']}回復！";
                    $turnMessages[] = "防御側HP: {$defender['current_health']}/{$defender['max_health']}";
                }
                
                // ヒーロースキルの敵へのデバフを適用
                if (!empty($heroResult['defender_effects'])) {
                    foreach ($heroResult['defender_effects'] as $effect) {
                        $attacker['active_effects'][] = $effect;
                    }
                }
            }
            
            // 攻撃回数
            $attackCount = 1 + $skillResult['extra_attacks'];
            
            for ($i = 0; $i < $attackCount; $i++) {
                if ($attacker['current_health'] <= 0) break;
                
                // 回避スキルチェック（攻撃側）
                $evaded = false;
                foreach ($attacker['active_effects'] as $evasionEffect) {
                    if (isset($evasionEffect['skill_key']) && $evasionEffect['skill_key'] === 'evasion') {
                        $evasionChance = $evasionEffect['effect_value'] ?? 35;
                        if (mt_rand(1, 100) <= $evasionChance) {
                            $evaded = true;
                            $attackNum = $i + 1;
                            $attackLabel = $attackCount > 1 ? "[攻撃{$attackNum}] " : "";
                            $turnMessages[] = "{$attackLabel}💨 回避！攻撃を回避した！";
                            break;
                        }
                    }
                }
                
                if ($evaded) {
                    continue; // 回避した場合はダメージ計算をスキップ
                }
                
                // ダメージ計算
                $damageResult = calculateDamage(
                    $defender['attack'],
                    $attacker['armor'],
                    $defender['active_effects'],
                    $attacker['active_effects'],
                    $attacker  // 攻撃側のデータを渡す（カテゴリチェック用）
                );
                
                $attacker['current_health'] -= $damageResult['damage'];
                $attacker['current_health'] = max(0, $attacker['current_health']);
                
                // 反撃スキルチェック（攻撃側）
                foreach ($attacker['active_effects'] as $counterEffect) {
                    if (isset($counterEffect['skill_key']) && $counterEffect['skill_key'] === 'counter') {
                        $counterChance = $counterEffect['activation_chance'] ?? 30;
                        if (mt_rand(1, 100) <= $counterChance) {
                            $counterDamage = (int)floor($damageResult['damage'] * ($counterEffect['effect_value'] / 100));
                            $defender['current_health'] -= $counterDamage;
                            $defender['current_health'] = max(0, $defender['current_health']);
                            $turnMessages[] = "⚔️ 反撃！{$counterDamage}ダメージを与えた！";
                            $turnMessages[] = "防御側HP: {$defender['current_health']}/{$defender['max_health']}";
                        }
                    }
                }
                
                // 反射効果チェック（攻撃側）
                foreach ($attacker['active_effects'] as $reflectEffect) {
                    if (isset($reflectEffect['effect_type']) && $reflectEffect['effect_type'] === 'reflect') {
                        $reflectDamage = $damageResult['damage'];
                        $defender['current_health'] -= $reflectDamage;
                        $defender['current_health'] = max(0, $defender['current_health']);
                        $turnMessages[] = "⛵ 反射！{$reflectDamage}ダメージを跳ね返した！";
                        $turnMessages[] = "防御側HP: {$defender['current_health']}/{$defender['max_health']}";
                    }
                }
                
                $attackNum = $i + 1;
                $attackLabel = $attackCount > 1 ? "[攻撃{$attackNum}] " : "";
                $turnMessages[] = "{$attackLabel}🛡️ 防御側が{$damageResult['damage']}ダメージを与えた！";
                $turnMessages = array_merge($turnMessages, $damageResult['messages']);
                $turnMessages[] = "攻撃側HP: {$attacker['current_health']}/{$attacker['max_health']}";
            }
            
            // 回復スキルチェック
            foreach ($defender['active_effects'] as $effect) {
                if ($effect['skill_key'] === 'heal') {
                    $healAmount = (int)floor($defender['max_health'] * ($effect['effect_value'] / 100));
                    $defender['current_health'] = min($defender['max_health'], $defender['current_health'] + $healAmount);
                    $turnMessages[] = "💚 防御側が{$healAmount}回復！";
                }
            }
        }
        
        // 効果ターン減少（凍結/スタン）
        $newDefenderEffects = [];
        foreach ($defender['active_effects'] as $effect) {
            if (in_array($effect['skill_key'], ['freeze', 'stun'])) {
                $effect['remaining_turns']--;
            }
            if ($effect['remaining_turns'] > 0) {
                $newDefenderEffects[] = $effect;
            }
        }
        $defender['active_effects'] = $newDefenderEffects;
        
        $turnLogs[] = [
            'turn' => $currentTurn,
            'actor' => 'both',
            'action' => 'attack',
            'messages' => $turnMessages,
            'attacker_hp' => $attacker['current_health'],
            'defender_hp' => $defender['current_health']
        ];
        
        if ($attacker['current_health'] <= 0) {
            $battleSummary[] = "🏆 防御側の勝利！";
            break;
        }
    }
    
    // 最大ターン数に達した場合
    if ($currentTurn >= $maxTurnsLimit) {
        // HPが多い方が勝ち
        if ($attacker['current_health'] > $defender['current_health']) {
            $battleSummary[] = "⏰ 時間切れ！攻撃側の勝利！（残りHP: {$attacker['current_health']} vs {$defender['current_health']}）";
        } else if ($defender['current_health'] > $attacker['current_health']) {
            $battleSummary[] = "⏰ 時間切れ！防御側の勝利！（残りHP: {$defender['current_health']} vs {$attacker['current_health']}）";
        } else {
            $battleSummary[] = "⏰ 時間切れ！引き分け！";
        }
    }
    
    // 勝者判定
    $attackerWins = $attacker['current_health'] > 0 && 
                   ($defender['current_health'] <= 0 || $attacker['current_health'] > $defender['current_health']);
    
    return [
        'attacker_wins' => $attackerWins,
        'attacker_final_hp' => max(0, $attacker['current_health']),
        'defender_final_hp' => max(0, $defender['current_health']),
        'attacker_max_hp' => $attacker['max_health'],
        'defender_max_hp' => $defender['max_health'],
        'total_turns' => $currentTurn,
        'turn_logs' => $turnLogs,
        'summary' => $battleSummary
    ];
}

/**
 * バトルログをデータベースに保存（占領戦用）
 * @param PDO $pdo
 * @param int $battleId conquest_battle_logs.id
 * @param array $turnLogs ターンログ配列
 */
function saveConquestBattleTurnLogs($pdo, $battleId, $turnLogs) {
    $stmt = $pdo->prepare("
        INSERT INTO conquest_battle_turn_logs 
        (battle_id, turn_number, actor_side, action_type, 
         attacker_hp_before, attacker_hp_after, defender_hp_before, defender_hp_after,
         log_message, status_effects)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $prevAttackerHp = null;
    $prevDefenderHp = null;
    
    foreach ($turnLogs as $log) {
        $attackerHpBefore = $prevAttackerHp ?? $log['attacker_hp'];
        $defenderHpBefore = $prevDefenderHp ?? $log['defender_hp'];
        
        $stmt->execute([
            $battleId,
            $log['turn'],
            $log['actor'] === 'both' ? 'attacker' : $log['actor'],
            $log['action'],
            $attackerHpBefore,
            $log['attacker_hp'],
            $defenderHpBefore,
            $log['defender_hp'],
            implode("\n", $log['messages']),
            json_encode($log['status_effects'] ?? [])
        ]);
        
        $prevAttackerHp = $log['attacker_hp'];
        $prevDefenderHp = $log['defender_hp'];
    }
}

/**
 * バトルログをデータベースに保存（文明戦争用）
 * @param PDO $pdo
 * @param int $warLogId civilization_war_logs.id
 * @param array $turnLogs ターンログ配列
 */
function saveCivilizationBattleTurnLogs($pdo, $warLogId, $turnLogs) {
    $stmt = $pdo->prepare("
        INSERT INTO civilization_battle_turn_logs 
        (war_log_id, turn_number, actor_side, action_type, 
         attacker_hp_before, attacker_hp_after, defender_hp_before, defender_hp_after,
         log_message, status_effects)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $prevAttackerHp = null;
    $prevDefenderHp = null;
    
    foreach ($turnLogs as $log) {
        $attackerHpBefore = $prevAttackerHp ?? $log['attacker_hp'];
        $defenderHpBefore = $prevDefenderHp ?? $log['defender_hp'];
        
        $stmt->execute([
            $warLogId,
            $log['turn'],
            $log['actor'] === 'both' ? 'attacker' : $log['actor'],
            $log['action'],
            $attackerHpBefore,
            $log['attacker_hp'],
            $defenderHpBefore,
            $log['defender_hp'],
            implode("\n", $log['messages']),
            json_encode($log['status_effects'] ?? [])
        ]);
        
        $prevAttackerHp = $log['attacker_hp'];
        $prevDefenderHp = $log['defender_hp'];
    }
}

/**
 * NPC防御ユニットを準備
 * @param int $npcPower NPC防御パワー
 * @return array バトルユニット情報
 */
function prepareNpcDefenseUnit($npcPower) {
    // NPCのステータスはパワーから導出
    $attack = (int)floor($npcPower * 0.4);
    $armor = (int)floor($npcPower * 0.3);
    $health = (int)floor($npcPower * 3);
    
    return [
        'attack' => $attack,
        'armor' => $armor,
        'max_health' => $health,
        'current_health' => $health,
        'troops' => [
            [
                'troop_type_id' => 0,
                'name' => 'NPC守備隊',
                'icon' => '🏰',
                'count' => 1,
                'attack' => $attack,
                'defense' => $armor,
                'health' => $health,
                'category' => 'infantry'
            ]
        ],
        'skills' => [],
        'equipment_buffs' => ['attack' => 0, 'armor' => 0, 'health' => 0],
        'active_effects' => [],
        'is_frozen' => false,
        'is_stunned' => false,
        'extra_attacks' => 0,
    ];
}

/**
 * バトルログ概要を生成
 * @param array $battleResult バトル結果
 * @return string 概要テキスト
 */
function generateBattleSummary($battleResult) {
    $summary = [];
    $summary[] = "総ターン数: {$battleResult['total_turns']}";
    $summary[] = "攻撃側最終HP: {$battleResult['attacker_final_hp']}/{$battleResult['attacker_max_hp']}";
    $summary[] = "防御側最終HP: {$battleResult['defender_final_hp']}/{$battleResult['defender_max_hp']}";
    $summary[] = $battleResult['attacker_wins'] ? "結果: 攻撃側の勝利" : "結果: 防御側の勝利";
    
    return implode("\n", $summary);
}

/**
 * バトルログをデータベースに保存（放浪モンスター用）
 * @param PDO $pdo
 * @param int $battleLogId wandering_monster_battle_logs.id
 * @param array $turnLogs ターンログ配列
 */
function saveWanderingMonsterBattleTurnLogs($pdo, $battleLogId, $turnLogs) {
    $stmt = $pdo->prepare("
        INSERT INTO wandering_monster_turn_logs 
        (battle_log_id, turn_number, actor_side, action_type, 
         attacker_hp_before, attacker_hp_after, defender_hp_before, defender_hp_after,
         log_message, status_effects)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $prevAttackerHp = null;
    $prevDefenderHp = null;
    
    foreach ($turnLogs as $log) {
        $attackerHpBefore = $prevAttackerHp ?? $log['attacker_hp'];
        $defenderHpBefore = $prevDefenderHp ?? $log['defender_hp'];
        
        $stmt->execute([
            $battleLogId,
            $log['turn'],
            $log['actor'] === 'both' ? 'attacker' : $log['actor'],
            $log['action'],
            $attackerHpBefore,
            $log['attacker_hp'],
            $defenderHpBefore,
            $log['defender_hp'],
            implode("\n", $log['messages']),
            json_encode($log['status_effects'] ?? [])
        ]);
        
        $prevAttackerHp = $log['attacker_hp'];
        $prevDefenderHp = $log['defender_hp'];
    }
}

/**
 * バトルログをデータベースに保存（ワールドボス用）
 * @param PDO $pdo
 * @param int $instanceId world_boss_instances.id
 * @param int $userId ユーザーID
 * @param int $attackNumber 攻撃回数（このユーザーの何回目の攻撃か）
 * @param array $turnLogs ターンログ配列
 */
function saveWorldBossBattleTurnLogs($pdo, $instanceId, $userId, $attackNumber, $turnLogs) {
    $stmt = $pdo->prepare("
        INSERT INTO world_boss_turn_logs 
        (instance_id, user_id, attack_number, turn_number, actor_side, action_type, 
         attacker_hp_before, attacker_hp_after, defender_hp_before, defender_hp_after,
         log_message, status_effects)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $prevAttackerHp = null;
    $prevDefenderHp = null;
    
    foreach ($turnLogs as $log) {
        $attackerHpBefore = $prevAttackerHp ?? $log['attacker_hp'];
        $defenderHpBefore = $prevDefenderHp ?? $log['defender_hp'];
        
        $stmt->execute([
            $instanceId,
            $userId,
            $attackNumber,
            $log['turn'],
            $log['actor'] === 'both' ? 'attacker' : $log['actor'],
            $log['action'],
            $attackerHpBefore,
            $log['attacker_hp'],
            $defenderHpBefore,
            $log['defender_hp'],
            implode("\n", $log['messages']),
            json_encode($log['status_effects'] ?? [])
        ]);
        
        $prevAttackerHp = $log['attacker_hp'];
        $prevDefenderHp = $log['defender_hp'];
    }
}

/**
 * スキル効果をログに保存（戦闘ログと統計用）
 * @param PDO $pdo
 * @param string $battleType 戦闘タイプ（conquest, wandering_monster, world_boss, portal_boss, war）
 * @param int $battleId 戦闘ID
 * @param int $turnNumber ターン番号
 * @param int $userId ユーザーID
 * @param int|null $troopTypeId 兵種ID（ヒーロースキルの場合はNULL）
 * @param int|null $skillId スキルID
 * @param string $skillName スキル名
 * @param string $skillIcon スキルアイコン
 * @param string $effectType 効果タイプ（damage, buff, debuff, heal, special）
 * @param string $effectTarget 効果対象（self, enemy, ally, all）
 * @param float $effectValue 効果量
 * @param int $effectDuration 効果持続ターン数
 * @param string $description 効果の説明
 */
function logSkillEffect($pdo, $battleType, $battleId, $turnNumber, $userId, $troopTypeId, $skillId, $skillName, $skillIcon, $effectType, $effectTarget, $effectValue, $effectDuration = 0, $description = '') {
    try {
        // スキル効果ログを保存
        $stmt = $pdo->prepare("
            INSERT INTO battle_skill_effect_logs 
            (battle_type, battle_id, turn_number, user_id, troop_type_id, skill_id, skill_name, skill_icon, 
             effect_type, effect_target, effect_value, effect_duration, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $battleType, $battleId, $turnNumber, $userId, $troopTypeId, $skillId,
            $skillName, $skillIcon, $effectType, $effectTarget, $effectValue, $effectDuration, $description
        ]);
        
        // 兵士スキル統計を更新（兵種スキルの場合のみ）
        if ($troopTypeId !== null) {
            updateTroopSkillStats($pdo, $userId, $troopTypeId, $effectType, $effectValue);
        }
    } catch (PDOException $e) {
        // ログ保存に失敗しても戦闘は続行
        error_log("logSkillEffect error: " . $e->getMessage());
    }
}

/**
 * 兵士スキル統計を更新
 * @param PDO $pdo
 * @param int $userId ユーザーID
 * @param int $troopTypeId 兵種ID
 * @param string $effectType 効果タイプ
 * @param float $effectValue 効果量
 */
function updateTroopSkillStats($pdo, $userId, $troopTypeId, $effectType, $effectValue) {
    try {
        // 効果タイプに応じたカラム名を決定
        $columnName = 'total_damage_dealt';
        switch ($effectType) {
            case 'damage':
                $columnName = 'total_damage_dealt';
                break;
            case 'buff':
                $columnName = 'total_buff_value';
                break;
            case 'debuff':
                $columnName = 'total_debuff_value';
                break;
            case 'heal':
                $columnName = 'total_heal_value';
                break;
            default:
                $columnName = 'total_damage_dealt';
        }
        
        // UPSERT で統計を更新
        // abs()を使用して効果量の絶対値を記録（バフ/デバフともに正の値として累積）
        $sql = "
            INSERT INTO user_troop_skill_stats (user_id, troop_type_id, total_skill_activations, {$columnName})
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE 
                total_skill_activations = total_skill_activations + 1,
                {$columnName} = {$columnName} + VALUES({$columnName})
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $troopTypeId, abs($effectValue)]);
    } catch (PDOException $e) {
        // 統計更新に失敗しても戦闘は続行
        error_log("updateTroopSkillStats error: " . $e->getMessage());
    }
}

/**
 * スキル効果からログ用のデータを抽出
 * @param array $effect スキル効果データ
 * @return array [effectType, effectValue, description]
 */
function extractSkillEffectData($effect) {
    $effectType = 'special';
    $effectValue = 0;
    $description = '';
    
    switch ($effect['effect_type'] ?? '') {
        case 'damage_over_time':
            $effectType = 'damage';
            $effectValue = $effect['effect_value'] ?? 0;
            $description = "継続ダメージ {$effectValue}%";
            break;
        case 'attack_buff':
        case 'defense_buff':
        case 'speed_buff':
            $effectType = 'buff';
            $effectValue = $effect['effect_value'] ?? 0;
            $description = ($effect['skill_name'] ?? 'スキル') . "によるバフ +{$effectValue}%";
            break;
        case 'attack_debuff':
        case 'defense_debuff':
        case 'speed_debuff':
            $effectType = 'debuff';
            $effectValue = $effect['effect_value'] ?? 0;
            $description = ($effect['skill_name'] ?? 'スキル') . "によるデバフ -{$effectValue}%";
            break;
        case 'heal':
            $effectType = 'heal';
            $effectValue = $effect['effect_value'] ?? 0;
            $description = "{$effectValue}% 回復";
            break;
        case 'stun':
        case 'freeze':
            $effectType = 'debuff';
            $effectValue = 100;
            $description = "行動不能";
            break;
        default:
            $effectType = 'special';
            $effectValue = $effect['effect_value'] ?? 0;
            $description = $effect['skill_name'] ?? 'スキル効果';
    }
    
    return [$effectType, $effectValue, $description];
}
