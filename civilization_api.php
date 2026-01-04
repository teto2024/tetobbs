<?php
// ===============================================
// civilization_api.php
// 文明育成システムAPI
// ===============================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/battle_engine.php';
require_once __DIR__ . '/exp_system.php';
require_once __DIR__ . '/battle_mail_helpers.php';

// 戦争レート制限の定数
define('WAR_RATE_LIMIT_MAX_ATTACKS', 3);  // 1時間あたりの最大攻撃回数
define('WAR_RATE_LIMIT_WINDOW_HOURS', 1); // レート制限の時間枠（時間）

/**
 * デイリータスクの進捗を更新（civilization_events_api.phpと同一ロジック）
 */
function updateDailyTaskProgressFromCiv($pdo, $userId, $taskType, $amount = 1) {
    $today = date('Y-m-d');
    
    // タスクタイプに該当するタスクを取得
    $stmt = $pdo->prepare("SELECT id, target_count FROM civilization_daily_tasks WHERE task_type = ? AND is_active = TRUE");
    $stmt->execute([$taskType]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tasks as $task) {
        // ⑥修正: 進捗を更新（2段階のクエリで確実に更新）
        // まずINSERT OR UPDATE
        $stmt = $pdo->prepare("
            INSERT INTO user_daily_task_progress (user_id, task_id, task_date, current_progress, is_completed)
            VALUES (?, ?, ?, LEAST(?, ?), LEAST(?, ?) >= ?)
            ON DUPLICATE KEY UPDATE 
                current_progress = LEAST(current_progress + VALUES(current_progress), ?)
        ");
        $stmt->execute([
            $userId, $task['id'], $today, 
            $amount, $task['target_count'],  // 初期値: min($amount, target_count)
            $amount, $task['target_count'], $task['target_count'],  // 初期is_completed
            $task['target_count']  // ON DUPLICATE KEY: 上限
        ]);
        
        // 次にis_completedを更新（current_progress >= target_count）
        $stmt = $pdo->prepare("
            UPDATE user_daily_task_progress 
            SET is_completed = (current_progress >= ?)
            WHERE user_id = ? AND task_id = ? AND task_date = ?
        ");
        $stmt->execute([$task['target_count'], $userId, $task['id'], $today]);
    }
}

// ヒーローイベントタスク進捗を更新するヘルパー関数
function updateHeroEventTaskProgressFromCiv($pdo, $userId, $taskType, $amount = 1) {
    $now = date('Y-m-d H:i:s');
    
    // アクティブなヒーローイベントに関連するタスクを取得
    $stmt = $pdo->prepare("
        SELECT het.id, het.target_count, he.event_id
        FROM hero_event_tasks het
        JOIN hero_events he ON het.hero_event_id = he.id
        JOIN civilization_events ce ON he.event_id = ce.id
        WHERE het.task_type = ?
          AND ce.event_type = 'hero'
          AND ce.is_active = TRUE
          AND ce.start_date <= ?
          AND ce.end_date >= ?
    ");
    $stmt->execute([$taskType, $now, $now]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tasks as $task) {
        // ⑥修正: 進捗を更新（2段階のクエリで確実に更新）
        $stmt = $pdo->prepare("
            INSERT INTO user_hero_event_task_progress (user_id, task_id, current_progress, is_completed)
            VALUES (?, ?, LEAST(?, ?), LEAST(?, ?) >= ?)
            ON DUPLICATE KEY UPDATE 
                current_progress = LEAST(current_progress + VALUES(current_progress), ?)
        ");
        $stmt->execute([
            $userId, $task['id'], 
            $amount, $task['target_count'],  // 初期値: min($amount, target_count)
            $amount, $task['target_count'], $task['target_count'],  // 初期is_completed
            $task['target_count']  // ON DUPLICATE KEY: 上限
        ]);
        
        // 次にis_completedを更新（current_progress >= target_count）
        $stmt = $pdo->prepare("
            UPDATE user_hero_event_task_progress 
            SET is_completed = (current_progress >= ?)
            WHERE user_id = ? AND task_id = ?
        ");
        $stmt->execute([$task['target_count'], $userId, $task['id']]);
    }
}

// 文明システム設定定数
define('CIV_COINS_TO_RESEARCH_RATIO', 10);     // 研究ポイント1あたりのコイン
define('CIV_RESOURCE_BONUS_RATIO', 10);        // 資源ボーナス1あたりのコイン
define('CIV_ATTACKER_BONUS', 1.1);             // 攻撃側のボーナス倍率
define('CIV_LOOT_RESOURCE_RATE', 0.1);         // 略奪時の資源比率（10%）
define('CIV_LOOT_COINS_RATE', 0.05);           // 略奪時のコイン比率（5%）
define('CIV_INSTANT_BUILDING_MIN_COST', 5);    // 建物即完了の最低クリスタルコスト
define('CIV_INSTANT_RESEARCH_MIN_COST', 3);    // 研究即完了の最低クリスタルコスト
define('CIV_INSTANT_SECONDS_PER_CRYSTAL', 60); // クリスタル1個あたりの秒数
define('CIV_ARMOR_MAX_REDUCTION', 0.5);        // アーマーによる最大ダメージ軽減率（50%）
define('CIV_ARMOR_PERCENT_DIVISOR', 100);      // アーマー値を軽減率に変換する除数
define('CIV_HEALTH_TO_POWER_RATIO', 10);       // 体力から軍事力への変換比率
define('CIV_TROOP_HEALTH_TO_POWER_RATIO', 50); // 兵種体力から軍事力への変換比率
define('CIV_TROOP_ADVANTAGE_BONUS', 1.25);     // 相性有利時のダメージ倍率（25%増加）
define('CIV_TROOP_DISADVANTAGE_PENALTY', 0.75); // 相性不利時のダメージ倍率（25%減少）
define('CIV_ADVANTAGE_DISPLAY_THRESHOLD', 0.05); // 相性表示の閾値（±5%）

// 負傷兵・治療システム定数
define('CIV_WOUNDED_RATE', 0.3);                  // 負傷兵発生率（30%）
define('CIV_DEATH_RATE', 0.1);                    // 戦死率（10%）
define('CIV_BASE_HEAL_TIME_SECONDS', 30);         // 基本治療時間（秒/兵士）
define('CIV_HEAL_COST_COINS_PER_UNIT', 10);       // 治療コスト（コイン/兵士）

// 時代制限定数
define('CIV_MAX_ERA_DIFFERENCE', 2);              // 攻撃可能な最大時代差（2つまで許容、3つ以上は不可）
define('CIV_ERA_ADVANCE_RESEARCH_LIMIT', 3);      // 時代進化時に表示する未完了研究の最大数

// 訓練キューシステム定数
define('CIV_INSTANT_TRAINING_MIN_COST', 2);       // 訓練即完了の最低クリスタルコスト
define('CIV_INSTANT_HEALING_MIN_COST', 1);        // 治療即完了の最低クリスタルコスト
define('CIV_BASE_QUEUE_SLOTS', 1);                // 基本キュースロット数
define('CIV_QUEUE_SLOTS_PER_BUILDING', 1);        // 建物1つあたりの追加キュースロット
define('CIV_QUEUE_SLOTS_PER_LEVEL', 0.5);         // レベル1あたりの追加キュースロット

// ダイヤモンド即時完了（クリスタルより安い）
define('CIV_INSTANT_SECONDS_PER_DIAMOND', 120);   // ダイヤモンド1個あたりの秒数
define('CIV_DIAMOND_DISCOUNT_RATE', 0.5);         // ダイヤモンドの割引率（クリスタルの50%）
define('CIV_INSTANT_DIAMOND_MIN_COST', 1);        // ダイヤモンド即完了の最低コスト

// 出撃兵士数上限システム定数
define('CIV_BASE_TROOP_DEPLOYMENT_LIMIT', 100);   // 基本出撃兵士数上限

// ③④ 治療資源要求の時代閾値
define('CIV_HEAL_MEDICINE_ERA_THRESHOLD', 4);     // 医薬品が必要になる時代閾値
define('CIV_HEAL_BANDAGE_ERA_THRESHOLD', 6);      // 包帯が必要になる時代閾値

// 訓練・治療時の追加資源消費（微量）
// 布、薬草、馬、ガラス、石油、医薬品、硫黄、石炭を消費する機会を設ける
$TRAINING_SUPPLEMENTARY_COSTS = [
    'cloth' => 1,      // 布：1 per 10 troops (歩兵・遠距離系)
    'horses' => 2,     // 馬：2 per 10 troops (騎兵系用)
    'glass' => 1,      // ガラス：1 per 10 troops (遠距離系・航空機)
    'oil' => 2,        // 石油：2 per 10 troops (現代兵種用)
    'sulfur' => 1,     // 硫黄：1 per 10 troops (攻城兵器・火薬系)
    'coal' => 1        // 石炭：1 per 10 troops (産業時代以降)
];

$HEALING_SUPPLEMENTARY_COSTS = [
    'herbs' => 1,      // 薬草：1 per 5 troops
    'medicine' => 2    // 医薬品：2 per 10 troops
];

/**
 * 建物の複数前提条件をチェック
 * @param PDO $pdo
 * @param int $userId
 * @param int $buildingTypeId
 * @param array $userBuildings ユーザーの建物配列
 * @param array $userResearches ユーザーの研究配列
 * @return array ['met' => bool, 'missing' => array]
 */
function checkBuildingPrerequisites($pdo, $userId, $buildingTypeId, $userBuildings, $userResearches) {
    // 複数前提条件テーブルから取得
    $stmt = $pdo->prepare("
        SELECT bp.prerequisite_building_id, bp.prerequisite_research_id, bp.is_required,
               b.name as building_name, r.name as research_name
        FROM civilization_building_prerequisites bp
        LEFT JOIN civilization_building_types b ON bp.prerequisite_building_id = b.id
        LEFT JOIN civilization_researches r ON bp.prerequisite_research_id = r.id
        WHERE bp.building_type_id = ?
    ");
    $stmt->execute([$buildingTypeId]);
    $prerequisites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($prerequisites)) {
        return ['met' => true, 'missing' => []];
    }
    
    $requiredMissing = [];
    $optionalMissing = [];
    $hasOptionalMet = false;
    
    foreach ($prerequisites as $prereq) {
        $isMet = false;
        
        // 建物前提条件チェック
        if ($prereq['prerequisite_building_id']) {
            foreach ($userBuildings as $ub) {
                if ($ub['building_type_id'] == $prereq['prerequisite_building_id'] && !$ub['is_constructing']) {
                    $isMet = true;
                    break;
                }
            }
        }
        
        // 研究前提条件チェック
        if ($prereq['prerequisite_research_id'] && !$isMet) {
            foreach ($userResearches as $ur) {
                if ($ur['research_id'] == $prereq['prerequisite_research_id'] && $ur['is_completed']) {
                    $isMet = true;
                    break;
                }
            }
        }
        
        // 必須条件（AND）の場合
        if ($prereq['is_required']) {
            if (!$isMet) {
                $name = $prereq['building_name'] ?: $prereq['research_name'] ?: '不明';
                $icon = $prereq['building_name'] ? '🏗️' : '📚';
                $requiredMissing[] = "{$icon} {$name}";
            }
        } else {
            // オプション条件（OR）の場合
            if ($isMet) {
                $hasOptionalMet = true;
            } else {
                $name = $prereq['building_name'] ?: $prereq['research_name'] ?: '不明';
                $icon = $prereq['building_name'] ? '🏗️' : '📚';
                $optionalMissing[] = "{$icon} {$name}";
            }
        }
    }
    
    // オプション条件がある場合、1つでも満たしていればOK
    $optionalCheck = empty($optionalMissing) || $hasOptionalMet;
    
    $allMet = empty($requiredMissing) && $optionalCheck;
    $missing = $requiredMissing;
    if (!$hasOptionalMet && !empty($optionalMissing)) {
        $missing[] = "いずれか: " . implode(' または ', $optionalMissing);
    }
    
    return ['met' => $allMet, 'missing' => $missing];
}

/**
 * 兵種の複数前提条件をチェック
 * @param PDO $pdo
 * @param int $userId
 * @param int $troopTypeId
 * @return array ['met' => bool, 'missing' => array]
 */
function checkTroopPrerequisites($pdo, $userId, $troopTypeId) {
    $stmt = $pdo->prepare("
        SELECT tp.prerequisite_building_id, tp.prerequisite_research_id, tp.is_required,
               b.name as building_name, r.name as research_name
        FROM civilization_troop_prerequisites tp
        LEFT JOIN civilization_building_types b ON tp.prerequisite_building_id = b.id
        LEFT JOIN civilization_researches r ON tp.prerequisite_research_id = r.id
        WHERE tp.troop_type_id = ?
    ");
    $stmt->execute([$troopTypeId]);
    $prerequisites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($prerequisites)) {
        return ['met' => true, 'missing' => []];
    }
    
    $requiredMissing = [];
    $optionalMissing = [];
    $hasOptionalMet = false;
    
    foreach ($prerequisites as $prereq) {
        $isMet = false;
        
        // 建物前提条件チェック
        if ($prereq['prerequisite_building_id']) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM user_civilization_buildings 
                WHERE user_id = ? AND building_type_id = ? AND is_constructing = FALSE
            ");
            $stmt->execute([$userId, $prereq['prerequisite_building_id']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $isMet = true;
            }
        }
        
        // 研究前提条件チェック
        if ($prereq['prerequisite_research_id'] && !$isMet) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM user_civilization_researches 
                WHERE user_id = ? AND research_id = ? AND is_completed = TRUE
            ");
            $stmt->execute([$userId, $prereq['prerequisite_research_id']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $isMet = true;
            }
        }
        
        if ($prereq['is_required']) {
            if (!$isMet) {
                $name = $prereq['building_name'] ?: $prereq['research_name'] ?: '不明';
                $icon = $prereq['building_name'] ? '🏗️' : '📚';
                $requiredMissing[] = "{$icon} {$name}";
            }
        } else {
            if ($isMet) {
                $hasOptionalMet = true;
            } else {
                $name = $prereq['building_name'] ?: $prereq['research_name'] ?: '不明';
                $icon = $prereq['building_name'] ? '🏗️' : '📚';
                $optionalMissing[] = "{$icon} {$name}";
            }
        }
    }
    
    $optionalCheck = empty($optionalMissing) || $hasOptionalMet;
    $allMet = empty($requiredMissing) && $optionalCheck;
    $missing = $requiredMissing;
    if (!$hasOptionalMet && !empty($optionalMissing)) {
        $missing[] = "いずれか: " . implode(' または ', $optionalMissing);
    }
    
    return ['met' => $allMet, 'missing' => $missing];
}

/**
 * 研究の複数前提条件をチェック
 * @param PDO $pdo
 * @param int $userId
 * @param int $researchId
 * @return array ['met' => bool, 'missing' => array]
 */
function checkResearchPrerequisites($pdo, $userId, $researchId) {
    $stmt = $pdo->prepare("
        SELECT rp.prerequisite_research_id, rp.is_required, r.name as research_name
        FROM civilization_research_prerequisites rp
        JOIN civilization_researches r ON rp.prerequisite_research_id = r.id
        WHERE rp.research_id = ?
    ");
    $stmt->execute([$researchId]);
    $prerequisites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($prerequisites)) {
        return ['met' => true, 'missing' => []];
    }
    
    $requiredMissing = [];
    $optionalMissing = [];
    $hasOptionalMet = false;
    
    foreach ($prerequisites as $prereq) {
        $stmt = $pdo->prepare("
            SELECT is_completed FROM user_civilization_researches 
            WHERE user_id = ? AND research_id = ?
        ");
        $stmt->execute([$userId, $prereq['prerequisite_research_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $isMet = $result && $result['is_completed'];
        
        if ($prereq['is_required']) {
            if (!$isMet) {
                $requiredMissing[] = "📚 " . $prereq['research_name'];
            }
        } else {
            if ($isMet) {
                $hasOptionalMet = true;
            } else {
                $optionalMissing[] = "📚 " . $prereq['research_name'];
            }
        }
    }
    
    $optionalCheck = empty($optionalMissing) || $hasOptionalMet;
    $allMet = empty($requiredMissing) && $optionalCheck;
    $missing = $requiredMissing;
    if (!$hasOptionalMet && !empty($optionalMissing)) {
        $missing[] = "いずれか: " . implode(' または ', $optionalMissing);
    }
    
    return ['met' => $allMet, 'missing' => $missing];
}

/**
 * 研究完了時に複数のアンロック対象を処理
 * @param PDO $pdo
 * @param int $userId
 * @param int $researchId
 */
function unlockResearchTargets($pdo, $userId, $researchId) {
    $stmt = $pdo->prepare("
        SELECT unlock_building_id, unlock_resource_id
        FROM civilization_research_unlocks
        WHERE research_id = ?
    ");
    $stmt->execute([$researchId]);
    $unlocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($unlocks as $unlock) {
        if ($unlock['unlock_resource_id']) {
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_resources (user_id, resource_type_id, amount, unlocked, unlocked_at)
                VALUES (?, ?, 0, TRUE, NOW())
                ON DUPLICATE KEY UPDATE unlocked = TRUE, unlocked_at = NOW()
            ");
            $stmt->execute([$userId, $unlock['unlock_resource_id']]);
        }
        
        // 建物のアンロックは自動的に利用可能になるため、特別な処理は不要
        // unlock_building_id は情報として保持されるのみ
    }
}

// 資源価値の定義（市場交換レート計算用）
// 値が高いほど価値が高い資源
$RESOURCE_VALUES = [
    'food' => 1.0,       // 基本資源
    'wood' => 1.0,       // 基本資源
    'stone' => 1.2,      // やや希少
    'bronze' => 1.5,     // 中程度の価値
    'iron' => 2.0,       // 価値が高い
    'gold' => 3.0,       // 高価値
    'knowledge' => 2.5,  // 価値が高い
    'oil' => 3.5,        // かなり高価値
    'crystal' => 4.0,    // 非常に高価値
    'mana' => 4.5,       // 非常に高価値
    'uranium' => 5.0,    // 最高価値
    'diamond' => 6.0,    // 最高価値
    // 追加資源
    'sulfur' => 2.0,
    'gems' => 4.0,
    'cloth' => 1.5,
    'marble' => 2.5,
    'horses' => 2.0,
    'coal' => 2.0,
    'glass' => 2.5,
    'spices' => 3.0,
    // 新規追加資源
    'bandages' => 1.5,
    'rubber' => 2.5,
    'titanium' => 4.0,
    'electronics' => 2.5,
    'herbs' => 2.5,
    'medicine' => 2.5,
    'gunpowder' => 2.5
    // 新時代の資源
    'plutonium' => 5.5,
    'silicon' => 3.0,
    'rare_earth' => 4.5,
    'quantum_crystal' => 6.0,
    'ai_core' => 5.5,
    'gene_sample' => 5.0,
    'dark_matter' => 7.0,
    'antimatter' => 8.0
];

// 資源キーから日本語名への変換マップ
$RESOURCE_KEY_TO_NAME = [
    'food' => '食料',
    'wood' => '木材',
    'stone' => '石材',
    'bronze' => '青銅',
    'iron' => '鉄',
    'gold' => '金',
    'knowledge' => '知識',
    'oil' => '石油',
    'crystal' => 'クリスタル',
    'mana' => 'マナ',
    'uranium' => 'ウラニウム',
    'diamond' => 'ダイヤモンド',
    'sulfur' => '硫黄',
    'gems' => '宝石',
    'cloth' => '布',
    'marble' => '大理石',
    'horses' => '馬',
    'coal' => '石炭',
    'glass' => 'ガラス',
    'spices' => '香辛料',
    'herbs' => '薬草',
    'medicine' => '医薬品',
    'steel' => '鋼鉄',
    'gunpowder' => '火薬',
    'gunpowder_res' => '火薬資源',
    'electronics' => '電子部品',
    // 新規追加
    'bandages' => '包帯',
    'rubber' => 'ゴム',
    'titanium' => 'チタン',
    // 新時代の資源
    'plutonium' => 'プルトニウム',
    'silicon' => 'シリコン',
    'rare_earth' => 'レアアース',
    'quantum_crystal' => '量子結晶',
    'ai_core' => 'AIコア',
    'gene_sample' => '遺伝子サンプル',
    'dark_matter' => 'ダークマター',
    'antimatter' => '反物質'
];

/**
 * 資源キーを日本語名に変換する
 * @param string $resourceKey 資源キー
 * @return string 日本語名
 */
function getResourceName($resourceKey) {
    global $RESOURCE_KEY_TO_NAME;
    return $RESOURCE_KEY_TO_NAME[$resourceKey] ?? $resourceKey;
}

header('Content-Type: application/json');

$me = user();
if (!$me) {
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}

$pdo = db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

// メンテナンス状態取得はメンテナンス中でも許可
if ($action === 'check_game_maintenance') {
    $inMaintenance = is_game_in_maintenance();
    echo json_encode([
        'ok' => true,
        'maintenance' => $inMaintenance,
        'message' => $inMaintenance ? GAME_MAINTENANCE_MESSAGE : null
    ]);
    exit;
}

// ゲームメンテナンスモードのチェック
check_game_maintenance();

// ユーザーの文明を取得または作成
function getUserCivilization($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM user_civilizations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $civ = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$civ) {
        // 新規文明作成
        $stmt = $pdo->prepare("
            INSERT INTO user_civilizations (user_id, civilization_name, current_era_id, population, max_population)
            VALUES (?, '新しい文明', 1, 0, 10)
        ");
        $stmt->execute([$userId]);
        
        // 初期資源をアンロック（food, wood, stone）
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO user_civilization_resources (user_id, resource_type_id, amount, unlocked, unlocked_at)
            SELECT ?, id, 100, TRUE, NOW()
            FROM civilization_resource_types 
            WHERE unlock_order = 0
        ");
        $stmt->execute([$userId]);
        
        // 再取得
        $stmt = $pdo->prepare("SELECT * FROM user_civilizations WHERE user_id = ?");
        $stmt->execute([$userId]);
        $civ = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $civ;
}

/**
 * ユーザーの装備バフを取得するヘルパー関数
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @return array ['attack' => float, 'armor' => float, 'health' => float] 各バフの合計値
 */
function getUserEquipmentBuffs($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT buffs FROM user_equipment 
        WHERE user_id = ? AND is_equipped = 1
    ");
    $stmt->execute([$userId]);
    $equippedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalBuffs = [
        'attack' => 0,
        'armor' => 0,
        'health' => 0
    ];
    
    foreach ($equippedItems as $item) {
        $buffs = json_decode($item['buffs'], true) ?: [];
        foreach ($totalBuffs as $key => $value) {
            if (isset($buffs[$key])) {
                $totalBuffs[$key] += (float)$buffs[$key];
            }
        }
    }
    
    return $totalBuffs;
}

/**
 * 出撃兵士数上限を計算するヘルパー関数
 * 建物のtroop_deployment_bonus列をレベルと掛け合わせて計算
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @return array ['base_limit' => int, 'building_bonus' => int, 'total_limit' => int, 'buildings' => array]
 */
function calculateTroopDeploymentLimit($pdo, $userId) {
    // 軍事建物からの出撃上限ボーナスを取得
    $stmt = $pdo->prepare("
        SELECT bt.name, bt.icon, bt.building_key, ucb.level, 
               COALESCE(bt.troop_deployment_bonus, 0) as bonus_per_level,
               COALESCE(bt.troop_deployment_bonus, 0) * ucb.level as total_bonus
        FROM user_civilization_buildings ucb
        JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
        WHERE ucb.user_id = ? 
          AND ucb.is_constructing = FALSE
          AND COALESCE(bt.troop_deployment_bonus, 0) > 0
        ORDER BY total_bonus DESC
    ");
    $stmt->execute([$userId]);
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $buildingBonus = 0;
    foreach ($buildings as $building) {
        $buildingBonus += (int)$building['total_bonus'];
    }
    
    $baseLimit = CIV_BASE_TROOP_DEPLOYMENT_LIMIT;
    $totalLimit = $baseLimit + $buildingBonus;
    
    return [
        'base_limit' => $baseLimit,
        'building_bonus' => $buildingBonus,
        'total_limit' => $totalLimit,
        'buildings' => $buildings
    ];
}

/**
 * 訓練キューの上限を計算するヘルパー関数
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @return array ['count' => int, 'level' => int, 'max_queues' => int] 兵舎数、レベル合計、最大キュー数
 */
function calculateTrainingQueueLimit($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as barracks_count, COALESCE(SUM(ucb.level), 0) as total_level
        FROM user_civilization_buildings ucb
        JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
        WHERE ucb.user_id = ? 
          AND bt.building_key IN ('barracks', 'training_ground', 'military_academy')
          AND ucb.is_constructing = FALSE
    ");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int)($data['barracks_count'] ?? 0);
    $level = (int)($data['total_level'] ?? 0);
    
    // 利用可能なキュー数を計算（基本1 + 兵舎数 + 兵舎レベル合計/2）
    $maxQueues = CIV_BASE_QUEUE_SLOTS + ($count * CIV_QUEUE_SLOTS_PER_BUILDING) + (int)floor($level * CIV_QUEUE_SLOTS_PER_LEVEL);
    
    return [
        'count' => $count,
        'level' => $level,
        'max_queues' => $maxQueues
    ];
}

/**
 * 治療キューの上限を計算するヘルパー関数
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @return array ['count' => int, 'level' => int, 'max_queues' => int, 'capacity' => int] 病院数、レベル合計、最大キュー数、容量
 */

function calculateHealingQueueLimit($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as hospital_count, COALESCE(SUM(ucb.level), 0) as total_level,
               SUM(CASE 
                   WHEN bt.building_key = 'field_hospital' THEN 10
                   WHEN bt.building_key = 'hospital' THEN 50
                   WHEN bt.building_key = 'medical_center' THEN 100
                   ELSE 0
               END) as building_capacity
        FROM user_civilization_buildings ucb
        JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
        WHERE ucb.user_id = ? 
          AND bt.building_key IN ('field_hospital', 'hospital', 'medical_center')
          AND ucb.is_constructing = FALSE
    ");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int)($data['hospital_count'] ?? 0);
    $level = (int)($data['total_level'] ?? 0);
    $buildingCapacity = (int)($data['building_capacity'] ?? 0);
    
    // 利用可能なキュー数を計算（最低1 + 病院数 + 病院レベル合計/2）
    $maxQueues = max(1, CIV_BASE_QUEUE_SLOTS + ($count * CIV_QUEUE_SLOTS_PER_BUILDING) + (int)floor($level * CIV_QUEUE_SLOTS_PER_LEVEL));
    
    // 病院容量（建物別ロジックに基づく総合値 + 5床/レベル）
    $capacity = $buildingCapacity + $level * 5;
    
    return [
        'count' => $count,
        'level' => $level,
        'max_queues' => $maxQueues,
        'capacity' => $capacity
    ];
}
/**
 * 大使館の援助制限を計算するヘルパー関数
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @return array ['embassy_level' => int, 'resource_limit' => int, 'troop_limit' => int, 'resources_used' => float, 'troops_used' => int]
 */
function calculateEmbassyTransferLimits($pdo, $userId) {
    // 大使館のレベルを取得
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ucb.level), 0) as total_level, COUNT(*) as count
        FROM user_civilization_buildings ucb
        JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
        WHERE ucb.user_id = ? 
          AND bt.building_key = 'embassy'
          AND ucb.is_constructing = FALSE
    ");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $embassyLevel = (int)($data['total_level'] ?? 0);
    $embassyCount = (int)($data['count'] ?? 0);
    
    // 1時間あたりの援助上限
    // 大使館なし: 0（援助不可）
    // 大使館レベル1: 資源1000、兵士50
    // 各レベルで +1000資源, +50兵士
    $resourceLimit = $embassyLevel * 1000;
    $troopLimit = $embassyLevel * 50;
    
    // 過去1時間の転送量を取得
    $hourAgo = date('Y-m-d H:i:s', time() - 3600);
    
    // 資源転送量
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_resources
        FROM civilization_resource_transfers
        WHERE sender_user_id = ? AND transferred_at >= ?
    ");
    $stmt->execute([$userId, $hourAgo]);
    $resourcesUsed = (float)$stmt->fetchColumn();
    
    // 兵士転送量
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(count), 0) as total_troops
        FROM civilization_troop_transfers
        WHERE sender_user_id = ? AND transferred_at >= ?
    ");
    $stmt->execute([$userId, $hourAgo]);
    $troopsUsed = (int)$stmt->fetchColumn();
    
    return [
        'embassy_count' => $embassyCount,
        'embassy_level' => $embassyLevel,
        'resource_limit' => $resourceLimit,
        'troop_limit' => $troopLimit,
        'resources_used' => $resourcesUsed,
        'troops_used' => $troopsUsed,
        'resources_available' => max(0, $resourceLimit - $resourcesUsed),
        'troops_available' => max(0, $troopLimit - $troopsUsed)
    ];
}

/**
 * 総合軍事力を計算するヘルパー関数（装備バフを含む）
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @param bool $includeEquipmentBuffs 装備バフを含めるかどうか
 * @return array 軍事力の内訳と合計
 */
function calculateTotalMilitaryPower($pdo, $userId, $includeEquipmentBuffs = true) {
    // 建物からの軍事力
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(bt.military_power * ucb.level), 0) as building_power
        FROM user_civilization_buildings ucb
        JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
        WHERE ucb.user_id = ? AND ucb.is_constructing = FALSE
    ");
    $stmt->execute([$userId]);
    $buildingPower = (int)$stmt->fetchColumn();
    
    // 兵士からの軍事力（攻撃力 + 防御力の半分 + 体力/CIV_TROOP_HEALTH_TO_POWER_RATIO）
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM((tt.attack_power + FLOOR(tt.defense_power / 2) + FLOOR(COALESCE(tt.health_points, 100) / " . CIV_TROOP_HEALTH_TO_POWER_RATIO . ")) * uct.count), 0) as troop_power
        FROM user_civilization_troops uct
        JOIN civilization_troop_types tt ON uct.troop_type_id = tt.id
        WHERE uct.user_id = ?
    ");
    $stmt->execute([$userId]);
    $troopPower = (int)$stmt->fetchColumn();
    
    // 装備バフを取得
    $equipmentBuffs = ['attack' => 0, 'armor' => 0, 'health' => 0];
    $equipmentPower = 0;
    if ($includeEquipmentBuffs) {
        $equipmentBuffs = getUserEquipmentBuffs($pdo, $userId);
        // 装備からの追加軍事力: 攻撃力 + 体力/CIV_HEALTH_TO_POWER_RATIO（体力は戦闘力への影響を小さめに）
        $equipmentPower = (int)floor($equipmentBuffs['attack'] + ($equipmentBuffs['health'] / CIV_HEALTH_TO_POWER_RATIO));
    }
    
    return [
        'building_power' => $buildingPower,
        'troop_power' => $troopPower,
        'equipment_power' => $equipmentPower,
        'equipment_buffs' => $equipmentBuffs,
        'total_power' => $buildingPower + $troopPower + $equipmentPower
    ];
}

/**
 * ユーザーの兵種構成を取得するヘルパー関数
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @return array 兵種カテゴリごとの合計パワー
 */
function getUserTroopComposition($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(tt.troop_category, 'infantry') as category,
            SUM((tt.attack_power + FLOOR(tt.defense_power / 2) + FLOOR(COALESCE(tt.health_points, 100) / " . CIV_TROOP_HEALTH_TO_POWER_RATIO . ")) * uct.count) as power,
            SUM(uct.count) as troop_count,
            SUM(COALESCE(tt.health_points, 100) * uct.count) as total_health
        FROM user_civilization_troops uct
        JOIN civilization_troop_types tt ON uct.troop_type_id = tt.id
        WHERE uct.user_id = ?
        GROUP BY COALESCE(tt.troop_category, 'infantry')
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $composition = [
        'infantry' => ['power' => 0, 'count' => 0, 'health' => 0],
        'cavalry' => ['power' => 0, 'count' => 0, 'health' => 0],
        'ranged' => ['power' => 0, 'count' => 0, 'health' => 0],
        'siege' => ['power' => 0, 'count' => 0, 'health' => 0]
    ];
    
    foreach ($rows as $row) {
        $category = $row['category'] ?? 'infantry';
        if (isset($composition[$category])) {
            $composition[$category]['power'] = (int)$row['power'];
            $composition[$category]['count'] = (int)$row['troop_count'];
            $composition[$category]['health'] = (int)$row['total_health'];
        }
    }
    
    return $composition;
}

/**
 * 兵種相性ボーナスを計算するヘルパー関数
 * 相性ルール:
 *   - infantry（歩兵）は ranged（遠距離）に強い
 *   - ranged（遠距離）は cavalry（騎兵）に強い
 *   - cavalry（騎兵）は infantry（歩兵）に強い
 *   - siege（攻城）は infantry に強いが cavalry に弱い
 * 
 * @param array $attackerComposition 攻撃者の兵種構成
 * @param array $defenderComposition 防御者の兵種構成
 * @return float 相性ボーナス倍率（1.0 = 変化なし）
 */
function calculateTroopAdvantageMultiplier($attackerComposition, $defenderComposition) {
    // 相性マップ: [攻撃側カテゴリ => [有利な相手カテゴリ => ボーナス, 不利な相手カテゴリ => ペナルティ]]
    $advantageMap = [
        'infantry' => ['advantage' => 'ranged', 'disadvantage' => 'cavalry'],
        'ranged' => ['advantage' => 'cavalry', 'disadvantage' => 'infantry'],
        'cavalry' => ['advantage' => 'infantry', 'disadvantage' => 'ranged'],
        'siege' => ['advantage' => 'infantry', 'disadvantage' => 'cavalry']
    ];
    
    $totalAttackerPower = 0;
    $totalAdvantageBonus = 0;
    
    // 各攻撃側兵種カテゴリについて、相性ボーナスを計算
    foreach ($attackerComposition as $attackCategory => $attackData) {
        $attackPower = $attackData['power'];
        if ($attackPower <= 0) continue;
        
        $totalAttackerPower += $attackPower;
        
        if (!isset($advantageMap[$attackCategory])) continue;
        
        $advantage = $advantageMap[$attackCategory]['advantage'];
        $disadvantage = $advantageMap[$attackCategory]['disadvantage'];
        
        $defenderTotalPower = array_sum(array_column($defenderComposition, 'power'));
        if ($defenderTotalPower <= 0) continue;
        
        // 防御側の有利/不利カテゴリの割合を計算
        // 例: 攻撃側が歩兵で、防御側に遠距離が50%いれば advantageRatio = 0.5
        $advantageRatio = isset($defenderComposition[$advantage]) 
            ? $defenderComposition[$advantage]['power'] / $defenderTotalPower 
            : 0;
        $disadvantageRatio = isset($defenderComposition[$disadvantage]) 
            ? $defenderComposition[$disadvantage]['power'] / $defenderTotalPower 
            : 0;
        
        // 相性ボーナス/ペナルティを加重平均で計算
        // 計算式: (有利割合 × 有利ボーナス) - (不利割合 × 不利ペナルティ)
        // 例: 有利ボーナス=1.25なら (1.25-1.0)=0.25 → 25%の追加ダメージ
        // 例: 不利ペナルティ=0.75なら (1.0-0.75)=0.25 → 25%のダメージ減少
        $categoryBonus = ($advantageRatio * (CIV_TROOP_ADVANTAGE_BONUS - 1.0)) - ($disadvantageRatio * (1.0 - CIV_TROOP_DISADVANTAGE_PENALTY));
        // 攻撃力で加重して合計（強い部隊の相性が全体に大きく影響）
        $totalAdvantageBonus += $attackPower * $categoryBonus;
    }
    
    if ($totalAttackerPower <= 0) {
        return 1.0;
    }
    
    // 全体の相性ボーナス倍率を計算（1.0を基準に加算）
    // 加重平均により、部隊構成全体の相性効果を算出
    return 1.0 + ($totalAdvantageBonus / $totalAttackerPower);
}

/**
 * 訓練時の追加資源を消費するヘルパー関数
 * 布、馬、ガラス、石油、硫黄、石炭を微量消費する（持っている場合のみ）
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @param int $count 訓練数
 * @param string $troopCategory 兵種カテゴリ（cavalry等で馬を多く消費）
 * @param int $unlockEraId 兵種のアンロック時代ID（時代による資源消費の判定用）
 * @return array 消費した資源のリスト
 */
function consumeTrainingSupplementaryResources($pdo, $userId, $count, $troopCategory = 'infantry', $unlockEraId = 1) {
    global $TRAINING_SUPPLEMENTARY_COSTS;
    $consumed = [];
    
    // 10体ごとに1の追加資源を消費
    $baseCost = max(1, floor($count / 10));
    
    foreach ($TRAINING_SUPPLEMENTARY_COSTS as $resourceKey => $costPer10) {
        // 馬は騎兵系のみ消費
        if ($resourceKey === 'horses' && $troopCategory !== 'cavalry') {
            continue;
        }
        // 布は歩兵・遠距離系（中世以降）
        // スキップ条件: 対象カテゴリでない、または中世未満
        if ($resourceKey === 'cloth' && (!in_array($troopCategory, ['infantry', 'ranged']) || $unlockEraId < 4)) {
            continue;
        }
        // ガラスは遠距離系・攻城系（ルネサンス以降、光学機器用）
        // スキップ条件: 対象カテゴリでない、またはルネサンス未満
        if ($resourceKey === 'glass' && (!in_array($troopCategory, ['ranged', 'siege']) || $unlockEraId < 5)) {
            continue;
        }
        // 石油は産業革命以降の全兵種（車両・航空機・近代兵器）
        if ($resourceKey === 'oil' && $unlockEraId < 6) {
            continue;
        }
        // 硫黄は攻城系（中世以降、火薬用）
        // スキップ条件: 攻城系でない、または中世未満
        if ($resourceKey === 'sulfur' && ($troopCategory !== 'siege' || $unlockEraId < 4)) {
            continue;
        }
        // 石炭は産業系（産業革命以降）
        if ($resourceKey === 'coal' && $unlockEraId < 6) {
            continue;
        }
        
        $requiredAmount = $baseCost * $costPer10;
        if ($requiredAmount <= 0) continue;
        
        // 資源を持っているか確認
        $stmt = $pdo->prepare("
            SELECT ucr.amount 
            FROM user_civilization_resources ucr
            JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
            WHERE ucr.user_id = ? AND rt.resource_key = ? AND ucr.unlocked = TRUE
        ");
        $stmt->execute([$userId, $resourceKey]);
        $currentAmount = (float)$stmt->fetchColumn();
        
        // 持っている分だけ消費（なければスキップ）
        if ($currentAmount > 0) {
            $toConsume = min($requiredAmount, $currentAmount);
            $stmt = $pdo->prepare("
                UPDATE user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                SET ucr.amount = ucr.amount - ?
                WHERE ucr.user_id = ? AND rt.resource_key = ?
            ");
            $stmt->execute([$toConsume, $userId, $resourceKey]);
            $consumed[$resourceKey] = $toConsume;
        }
    }
    
    return $consumed;
}

/**
 * ②③④ 治療時に必要な資源を確認するヘルパー関数
 * 兵種のコスト（時代）に応じて必要資源を変更
 * - 低コスト兵（時代1-3）: 薬草のみ
 * - 中コスト兵（時代4-5）: 薬草 + 医薬品
 * - 高コスト兵（時代6-7、歩兵・騎兵・遠距離）: 薬草 + 医薬品 + 包帯
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @param int $troopTypeId 兵種ID
 * @param int $count 治療数
 * @return array ['ok' => bool, 'required' => [...], 'missing' => [...]]
 */
function checkHealingResourcesAvailableForTroop($pdo, $userId, $troopTypeId, $count) {
    $required = [];
    $missing = [];
    
    // 兵種の情報を取得
    $stmt = $pdo->prepare("
        SELECT unlock_era_id, troop_category 
        FROM civilization_troop_types 
        WHERE id = ?
    ");
    $stmt->execute([$troopTypeId]);
    $troopInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $eraId = $troopInfo ? (int)$troopInfo['unlock_era_id'] : 1;
    $category = $troopInfo ? $troopInfo['troop_category'] : 'infantry';
    
    // 必要資源を決定
    $healingCosts = [];
    
    // 全ての兵種は薬草が必要（5体ごとに1）
    $healingCosts['herbs'] = ['divisor' => 5, 'amount' => 1];
    
    // 時代4以降は医薬品が必要（10体ごとに2）
    if ($eraId >= CIV_HEAL_MEDICINE_ERA_THRESHOLD) {
        $healingCosts['medicine'] = ['divisor' => 10, 'amount' => 2];
    }
    
    // 時代6以降で歩兵・騎兵・遠距離は包帯も必要（10体ごとに1）
    if ($eraId >= CIV_HEAL_BANDAGE_ERA_THRESHOLD && in_array($category, ['infantry', 'cavalry', 'ranged'])) {
        $healingCosts['bandages'] = ['divisor' => 10, 'amount' => 1];
    }
    
    foreach ($healingCosts as $resourceKey => $costInfo) {
        $requiredAmount = max(1, floor($count / $costInfo['divisor'])) * $costInfo['amount'];
        
        if ($requiredAmount <= 0) continue;
        
        // 資源を持っているか確認
        $stmt = $pdo->prepare("
            SELECT ucr.amount, rt.name as resource_name
            FROM user_civilization_resources ucr
            JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
            WHERE ucr.user_id = ? AND rt.resource_key = ? AND ucr.unlocked = TRUE
        ");
        $stmt->execute([$userId, $resourceKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentAmount = $result ? (float)$result['amount'] : 0;
        $resourceName = $result ? $result['resource_name'] : getResourceName($resourceKey);
        
        $required[$resourceKey] = [
            'name' => $resourceName,
            'required' => $requiredAmount,
            'available' => $currentAmount
        ];
        
        // 資源が不足している場合
        if ($currentAmount < $requiredAmount) {
            $missing[$resourceKey] = [
                'name' => $resourceName,
                'required' => $requiredAmount,
                'available' => $currentAmount,
                'shortage' => $requiredAmount - $currentAmount
            ];
        }
    }
    
    return [
        'ok' => empty($missing),
        'required' => $required,
        'missing' => $missing
    ];
}

/**
 * ②③④ 治療時に必要な資源を確認するヘルパー関数（レガシー：troop_type_idなしの場合）
 * 薬草のみを要求（低コスト兵用）
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @param int $count 治療数
 * @return array ['ok' => bool, 'required' => [...], 'missing' => [...]]
 */
function checkHealingResourcesAvailable($pdo, $userId, $count) {
    $required = [];
    $missing = [];
    
    // 低コスト兵として扱い、薬草のみを要求（5体ごとに1）
    $resourceKey = 'herbs';
    $requiredAmount = max(1, floor($count / 5));
    
    // 資源を持っているか確認
    $stmt = $pdo->prepare("
        SELECT ucr.amount, rt.name as resource_name
        FROM user_civilization_resources ucr
        JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
        WHERE ucr.user_id = ? AND rt.resource_key = ? AND ucr.unlocked = TRUE
    ");
    $stmt->execute([$userId, $resourceKey]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentAmount = $result ? (float)$result['amount'] : 0;
    $resourceName = $result ? $result['resource_name'] : getResourceName($resourceKey);
    
    $required[$resourceKey] = [
        'name' => $resourceName,
        'required' => $requiredAmount,
        'available' => $currentAmount
    ];
    
    if ($currentAmount < $requiredAmount) {
        $missing[$resourceKey] = [
            'name' => $resourceName,
            'required' => $requiredAmount,
            'available' => $currentAmount,
            'shortage' => $requiredAmount - $currentAmount
        ];
    }
    
    return [
        'ok' => empty($missing),
        'required' => $required,
        'missing' => $missing
    ];
}

/**
 * ③④ 治療時の追加資源を消費するヘルパー関数
 * 兵種のコスト（時代）に応じて消費資源を変更
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @param int $troopTypeId 兵種ID
 * @param int $count 治療数
 * @return array 消費した資源のリスト
 */
function consumeHealingSupplementaryResourcesForTroop($pdo, $userId, $troopTypeId, $count) {
    $consumed = [];
    
    // 兵種の情報を取得
    $stmt = $pdo->prepare("
        SELECT unlock_era_id, troop_category 
        FROM civilization_troop_types 
        WHERE id = ?
    ");
    $stmt->execute([$troopTypeId]);
    $troopInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $eraId = $troopInfo ? (int)$troopInfo['unlock_era_id'] : 1;
    $category = $troopInfo ? $troopInfo['troop_category'] : 'infantry';
    
    // 必要資源を決定
    $healingCosts = [];
    
    // 全ての兵種は薬草が必要（5体ごとに1）
    $healingCosts['herbs'] = ['divisor' => 5, 'amount' => 1];
    
    // 時代4以降は医薬品が必要（10体ごとに2）
    if ($eraId >= CIV_HEAL_MEDICINE_ERA_THRESHOLD) {
        $healingCosts['medicine'] = ['divisor' => 10, 'amount' => 2];
    }
    
    // 時代6以降で歩兵・騎兵・遠距離は包帯も必要（10体ごとに1）
    if ($eraId >= CIV_HEAL_BANDAGE_ERA_THRESHOLD && in_array($category, ['infantry', 'cavalry', 'ranged'])) {
        $healingCosts['bandages'] = ['divisor' => 10, 'amount' => 1];
    }
    
    foreach ($healingCosts as $resourceKey => $costInfo) {
        $requiredAmount = max(1, floor($count / $costInfo['divisor'])) * $costInfo['amount'];
        
        if ($requiredAmount <= 0) continue;
        
        // 資源を消費
        $stmt = $pdo->prepare("
            UPDATE user_civilization_resources ucr
            JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
            SET ucr.amount = ucr.amount - ?
            WHERE ucr.user_id = ? AND rt.resource_key = ?
        ");
        $stmt->execute([$requiredAmount, $userId, $resourceKey]);
        $consumed[$resourceKey] = $requiredAmount;
    }
    
    return $consumed;
}

/**
 * 治療時の追加資源を消費するヘルパー関数（レガシー：troop_type_idなしの場合）
 * 薬草のみを消費（低コスト兵用）
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 * @param int $count 治療数
 * @return array 消費した資源のリスト
 */
function consumeHealingSupplementaryResources($pdo, $userId, $count) {
    $consumed = [];
    
    // 低コスト兵として扱い、薬草のみを消費（5体ごとに1）
    $resourceKey = 'herbs';
    $requiredAmount = max(1, floor($count / 5));
    
    // 資源を消費
    $stmt = $pdo->prepare("
        UPDATE user_civilization_resources ucr
        JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
        SET ucr.amount = ucr.amount - ?
        WHERE ucr.user_id = ? AND rt.resource_key = ?
    ");
    $stmt->execute([$requiredAmount, $userId, $resourceKey]);
    $consumed[$resourceKey] = $requiredAmount;
    
    return $consumed;
}

// 資源を収集（時間経過分）
function collectResources($pdo, $userId) {
    $civ = getUserCivilization($pdo, $userId);
    $lastCollection = strtotime($civ['last_resource_collection']);
    $now = time();
    $hoursPassed = ($now - $lastCollection) / 3600;
    
    // 完了した建設を確認し、住宅の場合は人口も増やす
    $stmt = $pdo->prepare("
        SELECT ucb.id, bt.population_capacity, bt.name, ucb.level
        FROM user_civilization_buildings ucb
        JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
        WHERE ucb.user_id = ? AND ucb.is_constructing = TRUE AND ucb.construction_completes_at <= NOW()
    ");
    $stmt->execute([$userId]);
    $completedBuildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $populationIncrease = 0;
    foreach ($completedBuildings as $building) {
        // 住宅建物の場合、人口増加
        if ($building['population_capacity'] > 0) {
            $populationIncrease += $building['population_capacity'] * $building['level'];
        }
    }
    
    // 建設完了をマーク
    $stmt = $pdo->prepare("
        UPDATE user_civilization_buildings 
        SET is_constructing = FALSE 
        WHERE user_id = ? AND is_constructing = TRUE AND construction_completes_at <= NOW()
    ");
    $stmt->execute([$userId]);
    
    // 人口を増加
    if ($populationIncrease > 0) {
        $stmt = $pdo->prepare("
            UPDATE user_civilizations 
            SET population = population + ?,
                max_population = max_population + ?
            WHERE user_id = ?
        ");
        $stmt->execute([$populationIncrease, $populationIncrease, $userId]);
    }
    
    // 時間経過が少なすぎる場合は資源収集をスキップ（約36秒未満）
    if ($hoursPassed < 0.01) {
        return [];
    }
    
    // 生産建物からの資源を計算
    $stmt = $pdo->prepare("
        SELECT bt.produces_resource_id, bt.production_rate, SUM(ucb.level) as total_level, COUNT(*) as building_count
        FROM user_civilization_buildings ucb
        JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
        WHERE ucb.user_id = ? AND ucb.is_constructing = FALSE AND bt.produces_resource_id IS NOT NULL
        GROUP BY bt.produces_resource_id, bt.production_rate
    ");
    $stmt->execute([$userId]);
    $productions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $collectedResources = [];
    
    foreach ($productions as $prod) {
        $rate = $prod['production_rate'] * $prod['total_level'];
        $produced = $rate * $hoursPassed;
        
        if ($produced > 0) {
            // 資源がまだアンロックされていない場合はアンロックする
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_resources (user_id, resource_type_id, amount, unlocked, unlocked_at)
                VALUES (?, ?, ?, TRUE, NOW())
                ON DUPLICATE KEY UPDATE amount = amount + ?, unlocked = TRUE
            ");
            $stmt->execute([$userId, $prod['produces_resource_id'], $produced, $produced]);
            
            // 資源名を取得
            $stmt = $pdo->prepare("SELECT name, icon, resource_key FROM civilization_resource_types WHERE id = ?");
            $stmt->execute([$prod['produces_resource_id']]);
            $resInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resInfo) {
                $collectedResources[] = [
                    'resource_id' => $prod['produces_resource_id'],
                    'name' => $resInfo['name'],
                    'icon' => $resInfo['icon'],
                    'amount' => round($produced, 2),
                    'resource_key' => $resInfo['resource_key']
                ];
                
                // 資源収集クエストの進捗を更新
                $intProduced = (int)floor($produced);
                if ($intProduced > 0) {
                    updateCivilizationQuestProgress($pdo, $userId, 'collect', $resInfo['resource_key'], $intProduced);
                }
            }
        }
    }
    
    // 12: 銀行からのコイン生産を処理
    $stmt = $pdo->prepare("
        SELECT SUM(ucb.level) as total_level, COUNT(*) as building_count
        FROM user_civilization_buildings ucb
        JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
        WHERE ucb.user_id = ? AND ucb.is_constructing = FALSE AND bt.building_key = 'bank'
    ");
    $stmt->execute([$userId]);
    $bankData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bankData && $bankData['building_count'] > 0) {
        // 銀行1レベルあたり時間あたり10コインを生産
        $coinRate = 10 * $bankData['total_level'];
        $coinsProduced = (int)floor($coinRate * $hoursPassed);
        
        if ($coinsProduced > 0) {
            $stmt = $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
            $stmt->execute([$coinsProduced, $userId]);
            
            $collectedResources[] = [
                'resource_id' => 0,
                'name' => 'コイン',
                'icon' => '🪙',
                'amount' => $coinsProduced,
                'resource_key' => 'coins',
                'is_coin' => true
            ];
        }
    }
    
    // 最終収集時刻を更新
    $stmt = $pdo->prepare("UPDATE user_civilizations SET last_resource_collection = NOW() WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    return $collectedResources;
}

// 文明データを取得
if ($action === 'get_data') {
    try {
        $civ = getUserCivilization($pdo, $me['id']);
        $collected = collectResources($pdo, $me['id']);
        
        // ③ デイリータスク「ログイン」進捗を更新
        try {
            updateDailyTaskProgressFromCiv($pdo, $me['id'], 'login', 1);
            updateHeroEventTaskProgressFromCiv($pdo, $me['id'], 'login', 1);
        } catch (Exception $e) {
            // デイリータスクテーブルがない場合は無視
        }
        
        // ③ デイリータスク「資源収集」進捗を更新（収集した資源種類の数をカウント）
        if (!empty($collected)) {
            try {
                updateDailyTaskProgressFromCiv($pdo, $me['id'], 'collect', count($collected));
            } catch (Exception $e) {
                // テーブルがない場合は無視
            }
        }
        
        // 時代情報
        $stmt = $pdo->prepare("SELECT * FROM civilization_eras ORDER BY era_order");
        $stmt->execute();
        $eras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 現在の時代
        $stmt = $pdo->prepare("SELECT * FROM civilization_eras WHERE id = ?");
        $stmt->execute([$civ['current_era_id']]);
        $currentEra = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ユーザーの資源
        $stmt = $pdo->prepare("
            SELECT ucr.*, rt.resource_key, rt.name, rt.icon, rt.color
            FROM user_civilization_resources ucr
            JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
            WHERE ucr.user_id = ?
        ");
        $stmt->execute([$me['id']]);
        $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ユーザーの建物
        $stmt = $pdo->prepare("
            SELECT ucb.*, bt.building_key, bt.name, bt.icon, bt.description, bt.category, 
                   bt.produces_resource_id, bt.production_rate, bt.max_level, bt.population_capacity, bt.military_power
            FROM user_civilization_buildings ucb
            JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
            WHERE ucb.user_id = ?
        ");
        $stmt->execute([$me['id']]);
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 研究進捗（建物の前提条件チェックに必要なので先に取得）
        $stmt = $pdo->prepare("
            SELECT ucr.*, r.research_key, r.name, r.icon, r.description, r.era_id, 
                   r.unlock_building_id, r.unlock_resource_id, r.research_cost_points, r.research_time_seconds
            FROM user_civilization_researches ucr
            JOIN civilization_researches r ON ucr.research_id = r.id
            WHERE ucr.user_id = ?
        ");
        $stmt->execute([$me['id']]);
        $userResearches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 利用可能な建物タイプ（現在の時代まで）
        $stmt = $pdo->prepare("
            SELECT bt.*, e.name as era_name,
                   prereq_b.name as prerequisite_building_name,
                   prereq_r.name as prerequisite_research_name,
                   COALESCE(bt.troop_deployment_bonus, 0) as troop_deployment_bonus,
                   COALESCE(bt.transfer_limit_bonus, 0) as transfer_limit_bonus,
                   res.name as produces_resource_name,
                   res.icon as produces_resource_icon
            FROM civilization_building_types bt
            LEFT JOIN civilization_eras e ON bt.unlock_era_id = e.id
            LEFT JOIN civilization_building_types prereq_b ON bt.prerequisite_building_id = prereq_b.id
            LEFT JOIN civilization_researches prereq_r ON bt.prerequisite_research_id = prereq_r.id
            LEFT JOIN civilization_resource_types res ON bt.produces_resource_id = res.id
            WHERE bt.unlock_era_id IS NULL OR bt.unlock_era_id <= ?
            ORDER BY bt.unlock_era_id, bt.id
        ");
        $stmt->execute([$civ['current_era_id']]);
        $availableBuildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 各建物の前提条件を満たしているかチェック
        foreach ($availableBuildings as &$building) {
            // 複数前提条件をチェック
            $prereqCheck = checkBuildingPrerequisites($pdo, $me['id'], $building['id'], $buildings, $userResearches);
            $building['can_build'] = $prereqCheck['met'];
            $building['missing_prerequisites'] = $prereqCheck['missing'];
            
            // 後方互換性: 単一前提条件もチェック（新テーブルにデータがない場合）
            if ($prereqCheck['met'] && empty($prereqCheck['missing'])) {
                // 前提建物チェック（レガシー）
                if (!empty($building['prerequisite_building_id'])) {
                    $hasPrereq = false;
                    foreach ($buildings as $userBuilding) {
                        if ($userBuilding['building_type_id'] == $building['prerequisite_building_id'] && !$userBuilding['is_constructing']) {
                            $hasPrereq = true;
                            break;
                        }
                    }
                    if (!$hasPrereq) {
                        $building['can_build'] = false;
                        $building['missing_prerequisites'][] = "🏗️ " . ($building['prerequisite_building_name'] ?? '必要な建物');
                    }
                }
                
                // 前提研究チェック（レガシー）
                if (!empty($building['prerequisite_research_id'])) {
                    $hasPrereq = false;
                    foreach ($userResearches as $research) {
                        if ($research['research_id'] == $building['prerequisite_research_id'] && $research['is_completed']) {
                            $hasPrereq = true;
                            break;
                        }
                    }
                    if (!$hasPrereq) {
                        $building['can_build'] = false;
                        $building['missing_prerequisites'][] = "📚 " . ($building['prerequisite_research_name'] ?? '必要な研究');
                    }
                }
            }
        }
        unset($building);
        
        // 利用可能な研究
        $stmt = $pdo->prepare("
            SELECT r.*, e.name as era_name
            FROM civilization_researches r
            JOIN civilization_eras e ON r.era_id = e.id
            WHERE r.era_id <= ?
            ORDER BY r.era_id, r.id
        ");
        $stmt->execute([$civ['current_era_id']]);
        $availableResearches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ユーザーのコイン・クリスタル・ダイヤモンド残高
        $stmt = $pdo->prepare("SELECT coins, crystals, diamonds FROM users WHERE id = ?");
        $stmt->execute([$me['id']]);
        $balance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 総合軍事力を計算
        $militaryPowerData = calculateTotalMilitaryPower($pdo, $me['id']);
        
        // 文明データに軍事力を追加
        $civ['military_power'] = $militaryPowerData['total_power'];
        $civ['building_power'] = $militaryPowerData['building_power'];
        $civ['troop_power'] = $militaryPowerData['troop_power'];
        
        echo json_encode([
            'ok' => true,
            'civilization' => $civ,
            'current_era' => $currentEra,
            'eras' => $eras,
            'resources' => $resources,
            'buildings' => $buildings,
            'available_buildings' => $availableBuildings,
            'user_researches' => $userResearches,
            'available_researches' => $availableResearches,
            'collected_resources' => $collected,
            'balance' => $balance,
            'military_power_breakdown' => $militaryPowerData
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// コインを投資
if ($action === 'invest_coins') {
    $amount = (int)($input['amount'] ?? 0);
    
    if ($amount < 100) {
        echo json_encode(['ok' => false, 'error' => '最低投資額は100コインです']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // ユーザーのコインを確認
        $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['coins'] < $amount) {
            throw new Exception('コインが不足しています');
        }
        
        // コインを消費
        $stmt = $pdo->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
        $stmt->execute([$amount, $me['id']]);
        
        // 文明データ更新
        $civ = getUserCivilization($pdo, $me['id']);
        $stmt = $pdo->prepare("
            UPDATE user_civilizations 
            SET total_invested_coins = total_invested_coins + ?,
                research_points = research_points + ?
            WHERE user_id = ?
        ");
        $researchPointsGained = (int)floor($amount / CIV_COINS_TO_RESEARCH_RATIO);
        $stmt->execute([$amount, $researchPointsGained, $me['id']]);
        
        // 資源をボーナスとして追加（投資額に応じた食料・木材・石材）
        $resourceBonus = (int)floor($amount / CIV_RESOURCE_BONUS_RATIO);
        $stmt = $pdo->prepare("
            UPDATE user_civilization_resources 
            SET amount = amount + ?
            WHERE user_id = ? AND resource_type_id IN (1, 2, 3)
        ");
        $stmt->execute([$resourceBonus, $me['id']]);
        
        // クエスト進捗を更新（投資額をカウント）
        updateCivilizationQuestProgress($pdo, $me['id'], 'invest', null, $amount);
        
        // ③ デイリータスク「コイン投資」進捗を更新
        try {
            updateDailyTaskProgressFromCiv($pdo, $me['id'], 'invest', 1);
            // ヒーローイベントタスク進捗も更新
            updateHeroEventTaskProgressFromCiv($pdo, $me['id'], 'invest', 1);
        } catch (Exception $e) {
            // テーブルがない場合は無視
        }
        
        $pdo->commit();
        
        // ①⑤修正: grant_expはトランザクション外で呼び出す（内部で別トランザクションを開始するため）
        try {
            grant_exp($me['id'], 'civilization_invest', 0);
        } catch (Exception $e) {
            // 経験値付与エラーは無視（メイン処理に影響させない）
        }
        
        echo json_encode([
            'ok' => true,
            'message' => "投資成功！研究ポイント +{$researchPointsGained}、基本資源 +{$resourceBonus}",
            'research_points_gained' => $researchPointsGained,
            'resource_bonus' => $resourceBonus
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 建物を建設
if ($action === 'build') {
    $buildingTypeId = (int)($input['building_type_id'] ?? 0);
    
    $pdo->beginTransaction();
    try {
        // 建物タイプを確認
        $stmt = $pdo->prepare("SELECT * FROM civilization_building_types WHERE id = ?");
        $stmt->execute([$buildingTypeId]);
        $buildingType = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$buildingType) {
            throw new Exception('建物タイプが見つかりません');
        }
        
        // 文明データを確認
        $civ = getUserCivilization($pdo, $me['id']);
        
        // 時代制限チェック
        if ($buildingType['unlock_era_id'] && $buildingType['unlock_era_id'] > $civ['current_era_id']) {
            throw new Exception('この建物はまだ利用できません');
        }
        
        // 前提条件チェック（複数前提条件対応）
        $userBuildings = [];
        $stmt = $pdo->prepare("SELECT building_type_id, is_constructing FROM user_civilization_buildings WHERE user_id = ?");
        $stmt->execute([$me['id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userBuildings[] = $row;
        }
        
        $userResearches = [];
        $stmt = $pdo->prepare("SELECT research_id, is_completed FROM user_civilization_researches WHERE user_id = ?");
        $stmt->execute([$me['id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userResearches[] = $row;
        }
        
        $prereqCheck = checkBuildingPrerequisites($pdo, $me['id'], $buildingType['id'], $userBuildings, $userResearches);
        if (!$prereqCheck['met']) {
            $missingList = implode(', ', $prereqCheck['missing']);
            throw new Exception("前提条件を満たしていません: {$missingList}");
        }
        
        // 後方互換性: レガシー単一前提条件チェック
        if (empty($prereqCheck['missing'])) {
            // 前提建物チェック
            if (!empty($buildingType['prerequisite_building_id'])) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM user_civilization_buildings ucb
                    WHERE ucb.user_id = ? AND ucb.building_type_id = ? AND ucb.is_constructing = FALSE
                ");
                $stmt->execute([$me['id'], $buildingType['prerequisite_building_id']]);
                $hasPrereqBuilding = (int)$stmt->fetchColumn() > 0;
                
                if (!$hasPrereqBuilding) {
                    // 前提建物名を取得
                    $stmt = $pdo->prepare("SELECT name FROM civilization_building_types WHERE id = ?");
                    $stmt->execute([$buildingType['prerequisite_building_id']]);
                    $prereqName = $stmt->fetchColumn() ?: '必要な建物';
                    throw new Exception("「{$prereqName}」を先に建設してください");
                }
            }
            
            // 前提研究チェック
            if (!empty($buildingType['prerequisite_research_id'])) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM user_civilization_researches
                    WHERE user_id = ? AND research_id = ? AND is_completed = TRUE
                ");
                $stmt->execute([$me['id'], $buildingType['prerequisite_research_id']]);
                $hasPrereqResearch = (int)$stmt->fetchColumn() > 0;
                
                if (!$hasPrereqResearch) {
                    // 前提研究名を取得
                    $stmt = $pdo->prepare("SELECT name FROM civilization_researches WHERE id = ?");
                    $stmt->execute([$buildingType['prerequisite_research_id']]);
                    $prereqName = $stmt->fetchColumn() ?: '必要な研究';
                    throw new Exception("「{$prereqName}」を先に研究してください");
                }
            }
        }
        
        // コストを確認
        $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['coins'] < $buildingType['base_build_cost_coins']) {
            throw new Exception('コインが不足しています');
        }
        
        // 資源コストを確認
        $resourceCosts = json_decode($buildingType['base_build_cost_resources'], true) ?: [];
        foreach ($resourceCosts as $resourceKey => $required) {
            $stmt = $pdo->prepare("
                SELECT ucr.amount 
                FROM user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                WHERE ucr.user_id = ? AND rt.resource_key = ?
            ");
            $stmt->execute([$me['id'], $resourceKey]);
            $currentAmount = (float)$stmt->fetchColumn();
            
            if ($currentAmount < $required) {
                $resourceName = getResourceName($resourceKey);
                throw new Exception("{$resourceName}が不足しています（必要: {$required}、所持: " . round($currentAmount) . "）");
            }
        }
        
        // コストを消費
        $stmt = $pdo->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
        $stmt->execute([$buildingType['base_build_cost_coins'], $me['id']]);
        
        foreach ($resourceCosts as $resourceKey => $required) {
            $stmt = $pdo->prepare("
                UPDATE user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                SET ucr.amount = ucr.amount - ?
                WHERE ucr.user_id = ? AND rt.resource_key = ?
            ");
            $stmt->execute([$required, $me['id'], $resourceKey]);
        }
        
        // 建物を作成（建設中）
        $completesAt = date('Y-m-d H:i:s', time() + $buildingType['base_build_time_seconds']);
        $stmt = $pdo->prepare("
            INSERT INTO user_civilization_buildings 
            (user_id, building_type_id, level, is_constructing, construction_started_at, construction_completes_at)
            VALUES (?, ?, 1, TRUE, NOW(), ?)
        ");
        $stmt->execute([$me['id'], $buildingTypeId, $completesAt]);
        $buildingId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "{$buildingType['name']}の建設を開始しました",
            'building_id' => $buildingId,
            'completes_at' => $completesAt
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 研究を開始
if ($action === 'research') {
    $researchId = (int)($input['research_id'] ?? 0);
    
    $pdo->beginTransaction();
    try {
        // 研究情報を確認
        $stmt = $pdo->prepare("SELECT * FROM civilization_researches WHERE id = ?");
        $stmt->execute([$researchId]);
        $research = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$research) {
            throw new Exception('研究が見つかりません');
        }
        
        // 文明データを確認
        $civ = getUserCivilization($pdo, $me['id']);
        
        // 時代制限チェック
        if ($research['era_id'] > $civ['current_era_id']) {
            throw new Exception('この研究はまだ利用できません');
        }
        
        // 研究ポイントを確認
        if ($civ['research_points'] < $research['research_cost_points']) {
            throw new Exception('研究ポイントが不足しています');
        }
        
        // 既に研究済みか確認
        $stmt = $pdo->prepare("SELECT * FROM user_civilization_researches WHERE user_id = ? AND research_id = ?");
        $stmt->execute([$me['id'], $researchId]);
        $existingResearch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingResearch && $existingResearch['is_completed']) {
            throw new Exception('この研究は既に完了しています');
        }
        
        if ($existingResearch && $existingResearch['is_researching']) {
            throw new Exception('この研究は既に進行中です');
        }
        
        // 前提研究チェック（複数前提条件対応）
        $prereqCheck = checkResearchPrerequisites($pdo, $me['id'], $researchId);
        if (!$prereqCheck['met']) {
            $missingList = implode(', ', $prereqCheck['missing']);
            throw new Exception("前提研究が完了していません: {$missingList}");
        }
        
        // 後方互換性: レガシー単一前提条件チェック
        if (empty($prereqCheck['missing']) && $research['prerequisite_research_id']) {
            $stmt = $pdo->prepare("
                SELECT is_completed 
                FROM user_civilization_researches 
                WHERE user_id = ? AND research_id = ?
            ");
            $stmt->execute([$me['id'], $research['prerequisite_research_id']]);
            $prereq = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$prereq || !$prereq['is_completed']) {
                throw new Exception('前提研究が完了していません');
            }
        }
        
        // 研究ポイントを消費
        $stmt = $pdo->prepare("
            UPDATE user_civilizations 
            SET research_points = research_points - ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$research['research_cost_points'], $me['id']]);
        
        // 研究を開始
        $completesAt = date('Y-m-d H:i:s', time() + $research['research_time_seconds']);
        $stmt = $pdo->prepare("
            INSERT INTO user_civilization_researches 
            (user_id, research_id, is_researching, research_started_at, research_completes_at)
            VALUES (?, ?, TRUE, NOW(), ?)
            ON DUPLICATE KEY UPDATE 
                is_researching = TRUE, 
                research_started_at = NOW(), 
                research_completes_at = ?
        ");
        $stmt->execute([$me['id'], $researchId, $completesAt, $completesAt]);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "{$research['name']}の研究を開始しました",
            'completes_at' => $completesAt
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 完了した研究を確認
if ($action === 'complete_researches') {
    $pdo->beginTransaction();
    try {
        // 完了した研究を取得
        $stmt = $pdo->prepare("
            SELECT ucr.*, r.name, r.research_key, r.unlock_building_id, r.unlock_resource_id
            FROM user_civilization_researches ucr
            JOIN civilization_researches r ON ucr.research_id = r.id
            WHERE ucr.user_id = ? AND ucr.is_researching = TRUE AND ucr.research_completes_at <= NOW()
        ");
        $stmt->execute([$me['id']]);
        $completedResearches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $completedNames = [];
        
        foreach ($completedResearches as $research) {
            // 研究を完了
            $stmt = $pdo->prepare("
                UPDATE user_civilization_researches 
                SET is_researching = FALSE, is_completed = TRUE, completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$research['id']]);
            
            // 複数アンロック対象を処理
            unlockResearchTargets($pdo, $me['id'], $research['research_id']);
            
            // 後方互換性: レガシー単一アンロックチェック
            if ($research['unlock_resource_id']) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_civilization_resources (user_id, resource_type_id, amount, unlocked, unlocked_at)
                    VALUES (?, ?, 0, TRUE, NOW())
                    ON DUPLICATE KEY UPDATE unlocked = TRUE, unlocked_at = NOW()
                ");
                $stmt->execute([$me['id'], $research['unlock_resource_id']]);
            }
            
            $completedNames[] = $research['name'];
            
            // クエスト進捗を更新（研究完了ごとに1回カウント）
            updateCivilizationQuestProgress($pdo, $me['id'], 'research', $research['research_key'] ?? null, 1);
        }
        
        // ③ デイリータスク「研究」進捗を更新
        $researchCompletedCount = count($completedNames);
        if ($researchCompletedCount > 0) {
            try {
                updateDailyTaskProgressFromCiv($pdo, $me['id'], 'research', $researchCompletedCount);
            } catch (Exception $e) {
                // テーブルがない場合は無視
            }
        }
        
        $pdo->commit();
        
        // ⑤修正: grant_expはトランザクション外で呼び出す
        if ($researchCompletedCount > 0) {
            try {
                grant_exp($me['id'], 'civilization_research', 0);
            } catch (Exception $e) {
                // 経験値付与エラーは無視
            }
        }
        
        echo json_encode([
            'ok' => true,
            'completed' => $completedNames,
            'count' => count($completedNames)
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 時代を進化
if ($action === 'advance_era') {
    $pdo->beginTransaction();
    try {
        $civ = getUserCivilization($pdo, $me['id']);
        
        // 次の時代を取得
        $stmt = $pdo->prepare("
            SELECT * FROM civilization_eras 
            WHERE era_order > (SELECT era_order FROM civilization_eras WHERE id = ?)
            ORDER BY era_order ASC LIMIT 1
        ");
        $stmt->execute([$civ['current_era_id']]);
        $nextEra = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nextEra) {
            throw new Exception('既に最高の時代に達しています');
        }
        
        // 条件チェック
        if ($civ['population'] < $nextEra['unlock_population']) {
            throw new Exception("人口が不足しています（必要: {$nextEra['unlock_population']}、現在: {$civ['population']}）");
        }
        
        if ($civ['research_points'] < $nextEra['unlock_research_points']) {
            throw new Exception("研究ポイントが不足しています（必要: {$nextEra['unlock_research_points']}、現在: {$civ['research_points']}）");
        }
        
        // ③ 全ての研究が完了しているかチェック
        $stmt = $pdo->prepare("
            SELECT cr.id, cr.name 
            FROM civilization_researches cr
            WHERE cr.era_id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM user_civilization_researches ucr 
                  WHERE ucr.user_id = ? AND ucr.research_id = cr.id AND ucr.is_completed = TRUE
              )
        ");
        $stmt->execute([$civ['current_era_id'], $me['id']]);
        $incompleteResearches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($incompleteResearches)) {
            $incompleteNames = array_slice(array_column($incompleteResearches, 'name'), 0, CIV_ERA_ADVANCE_RESEARCH_LIMIT);
            $remaining = count($incompleteResearches) - CIV_ERA_ADVANCE_RESEARCH_LIMIT;
            $message = "現在の時代の研究がまだ完了していません。未完了: " . implode('、', $incompleteNames);
            if ($remaining > 0) {
                $message .= " 他{$remaining}件";
            }
            throw new Exception($message);
        }
        
        // 時代を進化
        $stmt = $pdo->prepare("
            UPDATE user_civilizations 
            SET current_era_id = ?, 
                research_points = research_points - ?
            WHERE user_id = ?
        ");
        $stmt->execute([$nextEra['id'], $nextEra['unlock_research_points'], $me['id']]);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "{$nextEra['name']}に進化しました！",
            'new_era' => $nextEra
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 戦争（他プレイヤーを攻撃）
if ($action === 'attack') {
    $targetUserId = (int)($input['target_user_id'] ?? 0);
    
    if ($targetUserId === $me['id']) {
        echo json_encode(['ok' => false, 'error' => '自分を攻撃することはできません']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // ⑦ 戦争レート制限チェック（1時間に3回まで）
        // civilization_war_logsテーブルを使用して攻撃回数をカウント
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-' . WAR_RATE_LIMIT_WINDOW_HOURS . ' hour'));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attack_count 
            FROM civilization_war_logs 
            WHERE attacker_user_id = ? AND battle_at >= ?
        ");
        $stmt->execute([$me['id'], $oneHourAgo]);
        $attackCount = (int)$stmt->fetchColumn();
        
        if ($attackCount >= WAR_RATE_LIMIT_MAX_ATTACKS) {
            // 最も古い攻撃の時刻を取得して、次に攻撃可能な時刻を計算
            $stmt = $pdo->prepare("
                SELECT battle_at 
                FROM civilization_war_logs 
                WHERE attacker_user_id = ? AND battle_at >= ?
                ORDER BY battle_at ASC
                LIMIT 1
            ");
            $stmt->execute([$me['id'], $oneHourAgo]);
            $oldestAttack = $stmt->fetchColumn();
            $nextAvailable = date('Y-m-d H:i:s', strtotime($oldestAttack . ' +' . WAR_RATE_LIMIT_WINDOW_HOURS . ' hour'));
            $waitMinutes = max(0, ceil((strtotime($nextAvailable) - time()) / 60));
            
            $pdo->rollBack(); // トランザクションをロールバック
            echo json_encode([
                'ok' => false, 
                'error' => "戦争は1時間に" . WAR_RATE_LIMIT_MAX_ATTACKS . "回までです。次の攻撃まであと{$waitMinutes}分お待ちください。",
                'rate_limited' => true,
                'next_available' => $nextAvailable,
                'wait_minutes' => $waitMinutes
            ]);
            exit;
        }
        
        // 攻撃者の文明
        $myCiv = getUserCivilization($pdo, $me['id']);
        
        // 防御者の文明
        $stmt = $pdo->prepare("SELECT * FROM user_civilizations WHERE user_id = ?");
        $stmt->execute([$targetUserId]);
        $targetCiv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$targetCiv) {
            throw new Exception('相手の文明が見つかりません');
        }
        
        // ⑪ 時代差チェック（CIV_MAX_ERA_DIFFERENCE以上離れていると攻め込めない）
        $stmt = $pdo->prepare("SELECT era_order FROM civilization_eras WHERE id = ?");
        $stmt->execute([$myCiv['current_era_id']]);
        $myEraOrder = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT era_order, name FROM civilization_eras WHERE id = ?");
        $stmt->execute([$targetCiv['current_era_id']]);
        $targetEraData = $stmt->fetch(PDO::FETCH_ASSOC);
        $targetEraOrder = (int)$targetEraData['era_order'];
        $targetEraName = $targetEraData['name'];
        
        $eraDiff = abs($myEraOrder - $targetEraOrder);
        if ($eraDiff > CIV_MAX_ERA_DIFFERENCE) {
            $maxDiff = CIV_MAX_ERA_DIFFERENCE + 1;
            throw new Exception("時代が{$maxDiff}つ以上離れている相手には攻め込めません（相手: {$targetEraName}、時代差: {$eraDiff}）");
        }
        
        // 軍事力を計算（建物 + 兵士 + 装備）
        $myPowerData = calculateTotalMilitaryPower($pdo, $me['id']);
        $myPower = $myPowerData['total_power'];
        
        $targetPowerData = calculateTotalMilitaryPower($pdo, $targetUserId);
        $targetPower = $targetPowerData['total_power'];
        
        if ($myPower <= 0) {
            throw new Exception('軍事力がありません。兵舎や軍事施設を建設するか、兵士を訓練してください。');
        }
        
        // 装備バフを取得
        $myEquipmentBuffs = $myPowerData['equipment_buffs'];
        $targetEquipmentBuffs = $targetPowerData['equipment_buffs'];
        
        // 兵種構成を取得して相性ボーナスを計算
        $myTroopComposition = getUserTroopComposition($pdo, $me['id']);
        $targetTroopComposition = getUserTroopComposition($pdo, $targetUserId);
        
        // 攻撃側の相性ボーナス（攻撃者 vs 防御者）
        $myAdvantageMultiplier = calculateTroopAdvantageMultiplier($myTroopComposition, $targetTroopComposition);
        // 防御側の相性ボーナス（防御者 vs 攻撃者）
        $targetAdvantageMultiplier = calculateTroopAdvantageMultiplier($targetTroopComposition, $myTroopComposition);
        
        // 攻撃力計算（自分の攻撃力バフ - 相手のアーマーで相手へのダメージ軽減）
        // アーマーは敵の攻撃力を軽減する（1アーマー = 1%軽減、最大CIV_ARMOR_MAX_REDUCTIONまで）
        $targetArmorReduction = min(CIV_ARMOR_MAX_REDUCTION, $targetEquipmentBuffs['armor'] / CIV_ARMOR_PERCENT_DIVISOR);
        $myArmorReduction = min(CIV_ARMOR_MAX_REDUCTION, $myEquipmentBuffs['armor'] / CIV_ARMOR_PERCENT_DIVISOR);
        
        // 最終的な攻撃力（装備攻撃力バフを含み、相手のアーマーで軽減、相性ボーナス適用）
        $myEffectivePower = $myPower * (1 - $targetArmorReduction) * $myAdvantageMultiplier;
        $targetEffectivePower = $targetPower * (1 - $myArmorReduction) * $targetAdvantageMultiplier;
        
        // 戦闘判定（攻撃側ボーナス適用）
        $myRoll = mt_rand(1, 100) + ($myEffectivePower * CIV_ATTACKER_BONUS);
        $targetRoll = mt_rand(1, 100) + $targetEffectivePower;
        
        $winnerId = ($myRoll > $targetRoll) ? $me['id'] : $targetUserId;
        $loserId = ($winnerId === $me['id']) ? $targetUserId : $me['id'];
        
        // 略奪
        $lootCoins = 0;
        $lootResources = [];
        
        if ($winnerId === $me['id']) {
            // ⑬ 保管庫による資源保護を計算
            $protectedResources = 0;
            $stmt = $pdo->prepare("
                SELECT SUM(bt.resource_protection_ratio * ucb.level) as total_protection_ratio, tc.population
                FROM user_civilization_buildings ucb
                JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
                JOIN user_civilizations tc ON ucb.user_id = tc.user_id
                WHERE ucb.user_id = ? AND ucb.is_constructing = FALSE 
                  AND bt.resource_protection_ratio IS NOT NULL
            ");
            $stmt->execute([$targetUserId]);
            $protectionData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($protectionData && $protectionData['total_protection_ratio'] > 0) {
                $protectedResources = floor($protectionData['population'] * $protectionData['total_protection_ratio']);
            }
            
            // 勝利時：相手の資源を略奪
            $stmt = $pdo->prepare("
                SELECT ucr.resource_type_id, ucr.amount, rt.resource_key
                FROM user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                WHERE ucr.user_id = ?
            ");
            $stmt->execute([$targetUserId]);
            $targetResources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 合計資源量を計算
            $totalResources = 0;
            foreach ($targetResources as $res) {
                $totalResources += $res['amount'];
            }
            
            // 略奪可能な資源量を計算（保護分を引く）
            $lootableResources = max(0, $totalResources - $protectedResources);
            $lootRatio = $totalResources > 0 ? ($lootableResources / $totalResources) : 0;
            
            foreach ($targetResources as $res) {
                // 保護率を考慮した略奪量
                $loot = floor($res['amount'] * CIV_LOOT_RESOURCE_RATE * $lootRatio);
                if ($loot > 0) {
                    $lootResources[$res['resource_key']] = $loot;
                    
                    // 資源を移動
                    $stmt = $pdo->prepare("
                        UPDATE user_civilization_resources 
                        SET amount = amount - ? 
                        WHERE user_id = ? AND resource_type_id = ?
                    ");
                    $stmt->execute([$loot, $targetUserId, $res['resource_type_id']]);
                    
                    $stmt = $pdo->prepare("
                        UPDATE user_civilization_resources 
                        SET amount = amount + ? 
                        WHERE user_id = ? AND resource_type_id = ?
                    ");
                    $stmt->execute([$loot, $me['id'], $res['resource_type_id']]);
                }
            }
            
            // コインも略奪
            $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $targetCoins = (int)$stmt->fetchColumn();
            $lootCoins = (int)floor($targetCoins * CIV_LOOT_COINS_RATE);
            
            if ($lootCoins > 0) {
                $stmt = $pdo->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
                $stmt->execute([$lootCoins, $targetUserId]);
                
                $stmt = $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                $stmt->execute([$lootCoins, $me['id']]);
            }
        }
        
        // 戦争ログを記録（詳細情報を含む）- レート制限もこのテーブルを参照
        $stmt = $pdo->prepare("
            INSERT INTO civilization_war_logs 
            (attacker_user_id, defender_user_id, attacker_power, attacker_troop_power, attacker_equipment_power, defender_power, defender_troop_power, defender_equipment_power, troop_advantage_bonus, winner_user_id, loot_coins, loot_resources)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $me['id'], $targetUserId, 
            $myPower, $myPowerData['troop_power'], $myPowerData['equipment_power'],
            $targetPower, $targetPowerData['troop_power'], $targetPowerData['equipment_power'],
            round($myAdvantageMultiplier - 1.0, 2),
            $winnerId, $lootCoins, json_encode($lootResources)
        ]);
        
        // ③ デイリータスク「戦闘参加」進捗を更新
        try {
            updateDailyTaskProgressFromCiv($pdo, $me['id'], 'battle', 1);
            updateHeroEventTaskProgressFromCiv($pdo, $me['id'], 'battle', 1);
        } catch (Exception $e) {
            // テーブルがない場合は無視
        }
        
        $pdo->commit();
        
        // ⑤修正: grant_expはトランザクション外で呼び出す
        try {
            grant_exp($me['id'], 'civilization_battle', 0);
        } catch (Exception $e) {
            // 経験値付与エラーは無視
        }
        
        $result = ($winnerId === $me['id']) ? 'victory' : 'defeat';
        $advantageText = '';
        $advantageThresholdHigh = 1.0 + CIV_ADVANTAGE_DISPLAY_THRESHOLD;
        $advantageThresholdLow = 1.0 - CIV_ADVANTAGE_DISPLAY_THRESHOLD;
        if ($myAdvantageMultiplier > $advantageThresholdHigh) {
            $advantageText = '（相性有利）';
        } else if ($myAdvantageMultiplier < $advantageThresholdLow) {
            $advantageText = '（相性不利）';
        }
        $message = ($result === 'victory') 
            ? "勝利{$advantageText}！{$lootCoins}コインと資源を略奪しました！" 
            : "敗北{$advantageText}...相手の防御が強すぎました。";
        
        echo json_encode([
            'ok' => true,
            'result' => $result,
            'message' => $message,
            'my_power' => $myPower,
            'target_power' => $targetPower,
            'my_effective_power' => round($myEffectivePower, 2),
            'target_effective_power' => round($targetEffectivePower, 2),
            'troop_advantage_multiplier' => round($myAdvantageMultiplier, 2),
            'loot_coins' => $lootCoins,
            'loot_resources' => $lootResources
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 攻撃可能なプレイヤー一覧
if ($action === 'get_targets') {
    try {
        // 同盟相手のIDリストを取得
        $stmt = $pdo->prepare("
            SELECT 
                CASE WHEN requester_user_id = ? THEN target_user_id ELSE requester_user_id END as ally_user_id
            FROM civilization_alliances 
            WHERE status = 'accepted' AND is_active = TRUE
              AND (requester_user_id = ? OR target_user_id = ?)
        ");
        $stmt->execute([$me['id'], $me['id'], $me['id']]);
        $allyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 自分の時代を取得
        $stmt = $pdo->prepare("
            SELECT e.era_order, e.name as era_name
            FROM user_civilizations uc
            JOIN civilization_eras e ON uc.current_era_id = e.id
            WHERE uc.user_id = ?
        ");
        $stmt->execute([$me['id']]);
        $myEra = $stmt->fetch(PDO::FETCH_ASSOC);
        $myEraOrder = $myEra ? (int)$myEra['era_order'] : 1;
        
        // ⑪ 時代情報を含めてターゲットを取得
        $stmt = $pdo->prepare("
            SELECT uc.user_id, uc.civilization_name, uc.population, u.handle, u.display_name,
                   e.id as era_id, e.era_order, e.name as era_name, e.icon as era_icon
            FROM user_civilizations uc
            JOIN users u ON uc.user_id = u.id
            JOIN civilization_eras e ON uc.current_era_id = e.id
            WHERE uc.user_id != ?
            ORDER BY uc.population DESC
            LIMIT 20
        ");
        $stmt->execute([$me['id']]);
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 自分の軍事力と兵種構成を取得
        $myPowerData = calculateTotalMilitaryPower($pdo, $me['id']);
        $myTroopComposition = getUserTroopComposition($pdo, $me['id']);
        
        // 各ターゲットの軍事力と装備バフを計算
        foreach ($targets as &$target) {
            $targetPowerData = calculateTotalMilitaryPower($pdo, $target['user_id']);
            $target['military_power'] = $targetPowerData['total_power'];
            $target['equipment_buffs'] = $targetPowerData['equipment_buffs'];
            
            // 相性ボーナスを計算
            $targetTroopComposition = getUserTroopComposition($pdo, $target['user_id']);
            $advantageMultiplier = calculateTroopAdvantageMultiplier($myTroopComposition, $targetTroopComposition);
            $target['troop_advantage_multiplier'] = round($advantageMultiplier, 2);
            $target['troop_composition'] = $targetTroopComposition;
            
            // 同盟相手かどうかをマーク
            $target['is_ally'] = in_array($target['user_id'], $allyIds);
            
            // ⑪ 時代差を計算（2つまでは許容、3つ以上離れていると攻め込めない）
            $eraDiff = abs($myEraOrder - (int)$target['era_order']);
            $target['era_difference'] = $eraDiff;
            $target['can_attack'] = $eraDiff <= CIV_MAX_ERA_DIFFERENCE; // CIV_MAX_ERA_DIFFERENCEまでは許容
        }
        unset($target);
        
        echo json_encode([
            'ok' => true, 
            'targets' => $targets,
            'my_military_power' => $myPowerData,
            'my_troop_composition' => $myTroopComposition,
            'my_era_order' => $myEraOrder,
            'my_era_name' => $myEra['era_name'] ?? '不明'
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 戦争レート制限の状態を取得
if ($action === 'get_war_rate_limit_status') {
    try {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-' . WAR_RATE_LIMIT_WINDOW_HOURS . ' hour'));
        
        // civilization_war_logsテーブルから過去1時間以内の攻撃回数と攻撃時刻を取得
        $stmt = $pdo->prepare("
            SELECT battle_at 
            FROM civilization_war_logs 
            WHERE attacker_user_id = ? AND battle_at >= ?
            ORDER BY battle_at ASC
        ");
        $stmt->execute([$me['id'], $oneHourAgo]);
        $attacks = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $attackCount = count($attacks);
        $remainingAttacks = max(0, WAR_RATE_LIMIT_MAX_ATTACKS - $attackCount);
        $isLimited = $attackCount >= WAR_RATE_LIMIT_MAX_ATTACKS;
        
        $nextAvailable = null;
        $waitSeconds = 0;
        
        if ($isLimited && !empty($attacks)) {
            // 最も古い攻撃の1時間後が次の利用可能時刻
            $oldestAttack = $attacks[0];
            $nextAvailable = date('Y-m-d H:i:s', strtotime($oldestAttack . ' +' . WAR_RATE_LIMIT_WINDOW_HOURS . ' hour'));
            $waitSeconds = max(0, strtotime($nextAvailable) - time());
        }
        
        echo json_encode([
            'ok' => true,
            'attack_count' => $attackCount,
            'max_attacks' => WAR_RATE_LIMIT_MAX_ATTACKS,
            'remaining_attacks' => $remainingAttacks,
            'is_limited' => $isLimited,
            'next_available' => $nextAvailable,
            'wait_seconds' => $waitSeconds
        ]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'レート制限状態の取得に失敗しました']);
    }
    exit;
}

// 文明名を変更
if ($action === 'rename') {
    $newName = trim($input['name'] ?? '');
    
    if (mb_strlen($newName) < 1 || mb_strlen($newName) > 50) {
        echo json_encode(['ok' => false, 'error' => '文明名は1〜50文字で入力してください']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE user_civilizations SET civilization_name = ? WHERE user_id = ?");
        $stmt->execute([$newName, $me['id']]);
        
        echo json_encode(['ok' => true, 'message' => '文明名を変更しました']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 完了した建物を確認
if ($action === 'complete_buildings') {
    $pdo->beginTransaction();
    try {
        // 完了した建設を取得
        $stmt = $pdo->prepare("
            SELECT ucb.id, ucb.level, bt.name, bt.population_capacity, bt.building_key
            FROM user_civilization_buildings ucb
            JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
            WHERE ucb.user_id = ? AND ucb.is_constructing = TRUE AND ucb.construction_completes_at <= NOW()
        ");
        $stmt->execute([$me['id']]);
        $completedBuildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $completedNames = [];
        $populationIncrease = 0;
        $buildingCount = 0;
        
        foreach ($completedBuildings as $building) {
            // 建設を完了
            $stmt = $pdo->prepare("
                UPDATE user_civilization_buildings 
                SET is_constructing = FALSE 
                WHERE id = ?
            ");
            $stmt->execute([$building['id']]);
            
            // 住宅の場合は人口を増やす
            if ($building['population_capacity'] > 0) {
                $populationIncrease += $building['population_capacity'] * $building['level'];
            }
            
            $completedNames[] = $building['name'];
            $buildingCount++;
            
            // クエスト進捗を更新（建物タイプごとに1回カウント）
            updateCivilizationQuestProgress($pdo, $me['id'], 'build', $building['building_key'], 1);
        }
        
        // 人口を増加
        if ($populationIncrease > 0) {
            $stmt = $pdo->prepare("
                UPDATE user_civilizations 
                SET population = population + ?,
                    max_population = max_population + ?
                WHERE user_id = ?
            ");
            $stmt->execute([$populationIncrease, $populationIncrease, $me['id']]);
        }
        
        // ③ デイリータスク「建設」進捗を更新
        if ($buildingCount > 0) {
            try {
                updateDailyTaskProgressFromCiv($pdo, $me['id'], 'build', $buildingCount);
            } catch (Exception $e) {
                // テーブルがない場合は無視
            }
        }
        
        $pdo->commit();
        
        // ⑤修正: grant_expはトランザクション外で呼び出す
        $totalExpGained = 0;
        if ($buildingCount > 0) {
            try {
                $expResult = grant_exp($me['id'], 'civilization_build', 0);
                $totalExpGained = $expResult['exp_gained'];
            } catch (Exception $e) {
                // 経験値付与エラーは無視
            }
        }
        
        echo json_encode([
            'ok' => true,
            'completed' => $completedNames,
            'count' => count($completedNames),
            'population_increase' => $populationIncrease,
            'exp_gained' => $totalExpGained
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 建物を即座に完成（クリスタル消費）
if ($action === 'instant_complete_building') {
    $buildingId = (int)($input['building_id'] ?? 0);
    
    $pdo->beginTransaction();
    try {
        // 建設中の建物を確認
        $stmt = $pdo->prepare("
            SELECT ucb.*, bt.name, bt.population_capacity, bt.building_key
            FROM user_civilization_buildings ucb
            JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
            WHERE ucb.id = ? AND ucb.user_id = ? AND ucb.is_constructing = TRUE
        ");
        $stmt->execute([$buildingId, $me['id']]);
        $building = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$building) {
            throw new Exception('建設中の建物が見つかりません');
        }
        
        // 残り時間に応じたクリスタルコストを計算
        $remainingSeconds = max(0, strtotime($building['construction_completes_at']) - time());
        $crystalCost = max(CIV_INSTANT_BUILDING_MIN_COST, (int)ceil($remainingSeconds / CIV_INSTANT_SECONDS_PER_CRYSTAL));
        
        // クリスタルを確認
        $stmt = $pdo->prepare("SELECT crystals FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['crystals'] < $crystalCost) {
            throw new Exception("クリスタルが不足しています（必要: {$crystalCost}、所持: {$user['crystals']}）");
        }
        
        // クリスタルを消費
        $stmt = $pdo->prepare("UPDATE users SET crystals = crystals - ? WHERE id = ?");
        $stmt->execute([$crystalCost, $me['id']]);
        
        // 建設を完了
        $stmt = $pdo->prepare("
            UPDATE user_civilization_buildings 
            SET is_constructing = FALSE, construction_completes_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$buildingId]);
        
        // 住宅の場合は人口を増やす
        $populationIncrease = 0;
        if ($building['population_capacity'] > 0) {
            $populationIncrease = $building['population_capacity'] * $building['level'];
            $stmt = $pdo->prepare("
                UPDATE user_civilizations 
                SET population = population + ?,
                    max_population = max_population + ?
                WHERE user_id = ?
            ");
            $stmt->execute([$populationIncrease, $populationIncrease, $me['id']]);
        }
        
        // クエスト進捗を更新（即完了時もクエスト進捗に反映）
        updateCivilizationQuestProgress($pdo, $me['id'], 'build', $building['building_key'], 1);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "{$building['name']}の建設が完了しました！",
            'crystals_spent' => $crystalCost,
            'population_increase' => $populationIncrease
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 研究を即座に完成（クリスタル消費）
if ($action === 'instant_complete_research') {
    $researchId = (int)($input['user_research_id'] ?? 0);
    
    $pdo->beginTransaction();
    try {
        // 研究中の研究を確認
        $stmt = $pdo->prepare("
            SELECT ucr.*, r.name, r.unlock_resource_id, r.research_key
            FROM user_civilization_researches ucr
            JOIN civilization_researches r ON ucr.research_id = r.id
            WHERE ucr.id = ? AND ucr.user_id = ? AND ucr.is_researching = TRUE
        ");
        $stmt->execute([$researchId, $me['id']]);
        $research = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$research) {
            throw new Exception('研究中の研究が見つかりません');
        }
        
        // 残り時間に応じたクリスタルコストを計算
        $remainingSeconds = max(0, strtotime($research['research_completes_at']) - time());
        $crystalCost = max(CIV_INSTANT_RESEARCH_MIN_COST, (int)ceil($remainingSeconds / CIV_INSTANT_SECONDS_PER_CRYSTAL));
        
        // クリスタルを確認
        $stmt = $pdo->prepare("SELECT crystals FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['crystals'] < $crystalCost) {
            throw new Exception("クリスタルが不足しています（必要: {$crystalCost}、所持: {$user['crystals']}）");
        }
        
        // クリスタルを消費
        $stmt = $pdo->prepare("UPDATE users SET crystals = crystals - ? WHERE id = ?");
        $stmt->execute([$crystalCost, $me['id']]);
        
        // 研究を完了
        $stmt = $pdo->prepare("
            UPDATE user_civilization_researches 
            SET is_researching = FALSE, is_completed = TRUE, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$researchId]);
        
        // 複数アンロック対象を処理
        unlockResearchTargets($pdo, $me['id'], $research['research_id']);
        
        // 後方互換性: レガシー単一アンロックチェック
        if ($research['unlock_resource_id']) {
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_resources (user_id, resource_type_id, amount, unlocked, unlocked_at)
                VALUES (?, ?, 0, TRUE, NOW())
                ON DUPLICATE KEY UPDATE unlocked = TRUE, unlocked_at = NOW()
            ");
            $stmt->execute([$me['id'], $research['unlock_resource_id']]);
        }
        
        // クエスト進捗を更新（即完了時もクエスト進捗に反映）
        updateCivilizationQuestProgress($pdo, $me['id'], 'research', $research['research_key'] ?? null, 1);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "{$research['name']}の研究が完了しました！",
            'crystals_spent' => $crystalCost
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 兵士を訓練
if ($action === 'train_troops') {
    $troopTypeId = (int)($input['troop_type_id'] ?? 0);
    $count = (int)($input['count'] ?? 1);
    
    if ($count < 1 || $count > 100) {
        echo json_encode(['ok' => false, 'error' => '訓練数は1〜100の範囲で指定してください']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // 兵種を確認
        $stmt = $pdo->prepare("SELECT * FROM civilization_troop_types WHERE id = ?");
        $stmt->execute([$troopTypeId]);
        $troopType = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$troopType) {
            throw new Exception('兵種が見つかりません');
        }
        
        // 文明データを確認
        $civ = getUserCivilization($pdo, $me['id']);
        
        // 時代制限チェック
        if ($troopType['unlock_era_id'] && $troopType['unlock_era_id'] > $civ['current_era_id']) {
            throw new Exception('この兵種はまだ利用できません');
        }
        
        // 前提条件チェック（複数前提条件対応）
        $prereqCheck = checkTroopPrerequisites($pdo, $me['id'], $troopType['id']);
        if (!$prereqCheck['met']) {
            $missingList = implode(', ', $prereqCheck['missing']);
            throw new Exception("前提条件を満たしていません: {$missingList}");
        }
        
        // 後方互換性: レガシー単一前提条件チェック
        if (empty($prereqCheck['missing'])) {
            // 前提建物チェック
            if (!empty($troopType['prerequisite_building_id'])) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM user_civilization_buildings ucb
                    WHERE ucb.user_id = ? AND ucb.building_type_id = ? AND ucb.is_constructing = FALSE
                ");
                $stmt->execute([$me['id'], $troopType['prerequisite_building_id']]);
                $hasPrereqBuilding = (int)$stmt->fetchColumn() > 0;
                
                if (!$hasPrereqBuilding) {
                    // 前提建物名を取得
                    $stmt = $pdo->prepare("SELECT name FROM civilization_building_types WHERE id = ?");
                    $stmt->execute([$troopType['prerequisite_building_id']]);
                    $prereqName = $stmt->fetchColumn() ?: '必要な建物';
                    throw new Exception("「{$prereqName}」を先に建設してください");
                }
            }
            
            // 前提研究チェック
            if (!empty($troopType['prerequisite_research_id'])) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM user_civilization_researches
                    WHERE user_id = ? AND research_id = ? AND is_completed = TRUE
                ");
                $stmt->execute([$me['id'], $troopType['prerequisite_research_id']]);
                $hasPrereqResearch = (int)$stmt->fetchColumn() > 0;
                
                if (!$hasPrereqResearch) {
                    // 前提研究名を取得
                    $stmt = $pdo->prepare("SELECT name FROM civilization_researches WHERE id = ?");
                    $stmt->execute([$troopType['prerequisite_research_id']]);
                    $prereqName = $stmt->fetchColumn() ?: '必要な研究';
                    throw new Exception("「{$prereqName}」を先に研究してください");
                }
            }
        }
        
        // コストを計算
        $totalCoinCost = $troopType['train_cost_coins'] * $count;
        $resourceCosts = json_decode($troopType['train_cost_resources'], true) ?: [];
        
        // コインを確認
        $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['coins'] < $totalCoinCost) {
            throw new Exception('コインが不足しています');
        }
        
        // 資源コストを確認・消費
        foreach ($resourceCosts as $resourceKey => $required) {
            $totalRequired = $required * $count;
            $stmt = $pdo->prepare("
                SELECT ucr.amount 
                FROM user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                WHERE ucr.user_id = ? AND rt.resource_key = ?
            ");
            $stmt->execute([$me['id'], $resourceKey]);
            $currentAmount = (float)$stmt->fetchColumn();
            
            if ($currentAmount < $totalRequired) {
                $resourceName = getResourceName($resourceKey);
                throw new Exception("{$resourceName}が不足しています");
            }
            
            $stmt = $pdo->prepare("
                UPDATE user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                SET ucr.amount = ucr.amount - ?
                WHERE ucr.user_id = ? AND rt.resource_key = ?
            ");
            $stmt->execute([$totalRequired, $me['id'], $resourceKey]);
        }
        
        // コインを消費
        $stmt = $pdo->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
        $stmt->execute([$totalCoinCost, $me['id']]);
        
        // 兵士を追加または更新
        $stmt = $pdo->prepare("
            INSERT INTO user_civilization_troops (user_id, troop_type_id, count)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE count = count + ?
        ");
        $stmt->execute([$me['id'], $troopTypeId, $count, $count]);
        
        // 軍事力を更新
        $totalMilitaryPower = $troopType['attack_power'] * $count;
        $stmt = $pdo->prepare("
            UPDATE user_civilizations 
            SET military_power = military_power + ?
            WHERE user_id = ?
        ");
        $stmt->execute([$totalMilitaryPower, $me['id']]);
        
        // クエスト進捗を更新（訓練した兵士数でカウント）
        updateCivilizationQuestProgress($pdo, $me['id'], 'train', $troopType['troop_key'], $count);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "{$troopType['name']} ×{$count} を訓練しました！",
            'military_power_increase' => $totalMilitaryPower
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 兵種一覧を取得
if ($action === 'get_troops') {
    try {
        $civ = getUserCivilization($pdo, $me['id']);
        
        // ユーザーの建物を取得（前提条件チェック用）
        $stmt = $pdo->prepare("
            SELECT building_type_id FROM user_civilization_buildings 
            WHERE user_id = ? AND is_constructing = FALSE
        ");
        $stmt->execute([$me['id']]);
        $userBuildingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // ユーザーの研究を取得（前提条件チェック用）
        $stmt = $pdo->prepare("
            SELECT research_id FROM user_civilization_researches 
            WHERE user_id = ? AND is_completed = TRUE
        ");
        $stmt->execute([$me['id']]);
        $userResearchIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 利用可能な兵種（特殊スキル情報を含む）
        $stmt = $pdo->prepare("
            SELECT tt.*, e.name as era_name,
                   prereq_b.name as prerequisite_building_name,
                   prereq_r.name as prerequisite_research_name,
                   ss.skill_key, ss.name as skill_name, ss.icon as skill_icon,
                   ss.description as skill_description, ss.effect_type,
                   ss.effect_value, ss.duration_turns, ss.activation_chance,
                   COALESCE(tt.is_stealth, FALSE) as is_stealth
            FROM civilization_troop_types tt
            LEFT JOIN civilization_eras e ON tt.unlock_era_id = e.id
            LEFT JOIN civilization_building_types prereq_b ON tt.prerequisite_building_id = prereq_b.id
            LEFT JOIN civilization_researches prereq_r ON tt.prerequisite_research_id = prereq_r.id
            LEFT JOIN battle_special_skills ss ON tt.special_skill_id = ss.id
            WHERE tt.unlock_era_id IS NULL OR tt.unlock_era_id <= ?
            ORDER BY tt.unlock_era_id, tt.id
        ");
        $stmt->execute([$civ['current_era_id']]);
        $availableTroops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 各兵種の前提条件をチェック
        foreach ($availableTroops as &$troop) {
            $troop['can_train'] = true;
            $troop['missing_prerequisites'] = [];
            
            // 前提建物チェック
            if (!empty($troop['prerequisite_building_id'])) {
                if (!in_array($troop['prerequisite_building_id'], $userBuildingIds)) {
                    $troop['can_train'] = false;
                    $troop['missing_prerequisites'][] = "🏗️ " . ($troop['prerequisite_building_name'] ?? '必要な建物');
                }
            }
            
            // 前提研究チェック
            if (!empty($troop['prerequisite_research_id'])) {
                if (!in_array($troop['prerequisite_research_id'], $userResearchIds)) {
                    $troop['can_train'] = false;
                    $troop['missing_prerequisites'][] = "📚 " . ($troop['prerequisite_research_name'] ?? '必要な研究');
                }
            }
        }
        unset($troop);
        
        // ユーザーの兵士
        $stmt = $pdo->prepare("
            SELECT uct.*, tt.troop_key, tt.name, tt.icon, tt.attack_power, tt.defense_power, 
                   COALESCE(tt.health_points, 100) as health_points, 
                   COALESCE(tt.troop_category, 'infantry') as troop_category,
                   COALESCE(tt.is_stealth, FALSE) as is_stealth,
                   COALESCE(tt.is_disposable, FALSE) as is_disposable
            FROM user_civilization_troops uct
            JOIN civilization_troop_types tt ON uct.troop_type_id = tt.id
            WHERE uct.user_id = ?
        ");
        $stmt->execute([$me['id']]);
        $userTroops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 兵種カテゴリの相性情報を追加
        $troopAdvantageInfo = [
            'infantry' => ['name' => '歩兵', 'icon' => '🗡️', 'strong_against' => 'ranged', 'weak_against' => 'cavalry'],
            'cavalry' => ['name' => '騎兵', 'icon' => '🐴', 'strong_against' => 'infantry', 'weak_against' => 'ranged'],
            'ranged' => ['name' => '遠距離', 'icon' => '🏹', 'strong_against' => 'cavalry', 'weak_against' => 'infantry'],
            'siege' => ['name' => '攻城', 'icon' => '💣', 'strong_against' => 'infantry', 'weak_against' => 'cavalry']
        ];
        
        // 出撃兵士数上限を計算
        $deploymentLimit = calculateTroopDeploymentLimit($pdo, $me['id']);
        
        echo json_encode([
            'ok' => true,
            'available_troops' => $availableTroops,
            'user_troops' => $userTroops,
            'troop_advantage_info' => $troopAdvantageInfo,
            'deployment_limit' => $deploymentLimit
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ダイヤモンドでVIPブースト購入
if ($action === 'buy_vip_boost') {
    $boostType = $input['boost_type'] ?? '';
    
    $boostCosts = [
        'production_2x' => ['diamonds' => 5, 'duration_hours' => 24, 'description' => '資源生産2倍（24時間）'],
        'research_speed' => ['diamonds' => 3, 'duration_hours' => 12, 'description' => '研究速度2倍（12時間）'],
        'build_speed' => ['diamonds' => 3, 'duration_hours' => 12, 'description' => '建設速度2倍（12時間）'],
        'resource_pack' => ['diamonds' => 10, 'resources' => ['food' => 1000, 'wood' => 1000, 'stone' => 1000], 'description' => '資源パック']
    ];
    
    if (!isset($boostCosts[$boostType])) {
        echo json_encode(['ok' => false, 'error' => '無効なブーストタイプです']);
        exit;
    }
    
    $boost = $boostCosts[$boostType];
    
    $pdo->beginTransaction();
    try {
        // ダイヤモンドを確認
        $stmt = $pdo->prepare("SELECT diamonds FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['diamonds'] < $boost['diamonds']) {
            throw new Exception("ダイヤモンドが不足しています（必要: {$boost['diamonds']}、所持: {$user['diamonds']}）");
        }
        
        // ダイヤモンドを消費
        $stmt = $pdo->prepare("UPDATE users SET diamonds = diamonds - ? WHERE id = ?");
        $stmt->execute([$boost['diamonds'], $me['id']]);
        
        // ブースト適用
        if ($boostType === 'resource_pack') {
            // 資源を追加
            foreach ($boost['resources'] as $resourceKey => $amount) {
                $stmt = $pdo->prepare("
                    UPDATE user_civilization_resources ucr
                    JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                    SET ucr.amount = ucr.amount + ?
                    WHERE ucr.user_id = ? AND rt.resource_key = ?
                ");
                $stmt->execute([$amount, $me['id'], $resourceKey]);
            }
        } else {
            // ブースト記録
            $expiresAt = date('Y-m-d H:i:s', time() + ($boost['duration_hours'] * 3600));
            $stmt = $pdo->prepare("
                INSERT INTO civilization_boosts (user_id, boost_type, multiplier, expires_at)
                VALUES (?, ?, 2.0, ?)
                ON DUPLICATE KEY UPDATE expires_at = ?, multiplier = 2.0
            ");
            $stmt->execute([$me['id'], $boostType, $expiresAt, $expiresAt]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => $boost['description'] . 'を購入しました！',
            'diamonds_spent' => $boost['diamonds']
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 資源交換（市場機能）
if ($action === 'exchange_resources') {
    $fromResourceId = (int)($input['from_resource_id'] ?? 0);
    $toResourceId = (int)($input['to_resource_id'] ?? 0);
    $amount = (int)($input['amount'] ?? 0);
    
    if ($fromResourceId === $toResourceId) {
        echo json_encode(['ok' => false, 'error' => '同じ資源は交換できません']);
        exit;
    }
    
    if ($amount < 2) {
        echo json_encode(['ok' => false, 'error' => '最低交換量は2です']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // 市場建物の数を確認（建設数に応じてレートが改善される）
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as market_count, SUM(ucb.level) as total_level
            FROM user_civilization_buildings ucb
            JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
            WHERE ucb.user_id = ? AND bt.building_key = 'market' AND ucb.is_constructing = FALSE
        ");
        $stmt->execute([$me['id']]);
        $marketData = $stmt->fetch(PDO::FETCH_ASSOC);
        $marketCount = (int)($marketData['market_count'] ?? 0);
        $totalMarketLevel = (int)($marketData['total_level'] ?? 0);
        
        if ($marketCount === 0) {
            throw new Exception('市場を建設してから交換してください');
        }
        
        // ⑤ 市場交換制限チェック（1時間ごとに10k × 市場建築数）- 全資源合計で管理
        $hourlyLimit = 10000 * $marketCount;
        
        // 現在の交換制限状態を確認（全資源の合計）
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(exchanged_amount), 0) as total_exchanged, MAX(reset_at) as reset_at
            FROM user_market_exchange_limits 
            WHERE user_id = ? AND reset_at > NOW()
        ");
        $stmt->execute([$me['id']]);
        $limitData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentExchanged = (int)($limitData['total_exchanged'] ?? 0);
        $now = new DateTime();
        
        // 交換可能量をチェック
        $remainingLimit = $hourlyLimit - $currentExchanged;
        if ($remainingLimit <= 0) {
            $resetAt = $limitData ? new DateTime($limitData['reset_at']) : (clone $now)->modify('+1 hour');
            $remainingMinutes = max(0, (int)floor(($resetAt->getTimestamp() - $now->getTimestamp()) / 60));
            throw new Exception("1時間の交換上限（{$hourlyLimit}）に達しました。あと約{$remainingMinutes}分でリセットされます。");
        }
        
        if ($amount > $remainingLimit) {
            throw new Exception("交換可能量を超えています。現在あと{$remainingLimit}まで交換できます（上限: {$hourlyLimit}/時間）");
        }
        
        // 資源を確認
        $stmt = $pdo->prepare("
            SELECT ucr.amount, rt.name as from_name, rt.icon as from_icon, rt.resource_key as from_key
            FROM user_civilization_resources ucr
            JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
            WHERE ucr.user_id = ? AND ucr.resource_type_id = ?
        ");
        $stmt->execute([$me['id'], $fromResourceId]);
        $fromResource = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fromResource) {
            throw new Exception('交換元の資源がアンロックされていません');
        }
        
        if ((float)$fromResource['amount'] < $amount) {
            throw new Exception("資源が不足しています（必要: {$amount}、所持: " . round($fromResource['amount']) . "）");
        }
        
        // 交換先の資源を確認
        $stmt = $pdo->prepare("
            SELECT rt.name as to_name, rt.icon as to_icon, rt.resource_key as to_key
            FROM user_civilization_resources ucr
            JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
            WHERE ucr.user_id = ? AND ucr.resource_type_id = ?
        ");
        $stmt->execute([$me['id'], $toResourceId]);
        $toResource = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$toResource) {
            throw new Exception('交換先の資源がアンロックされていません');
        }
        
        // グローバルの資源価値定義を使用
        global $RESOURCE_VALUES;
        
        $fromValue = $RESOURCE_VALUES[$fromResource['from_key']] ?? 1.0;
        $toValue = $RESOURCE_VALUES[$toResource['to_key']] ?? 1.0;
        
        // 基本交換レート（価値の比率）
        $baseRate = $fromValue / $toValue;
        
        // 市場建設数によるボーナス（市場1つあたり5%改善、最大50%まで）
        // 市場レベルも加味（レベル合計 * 2%）
        $marketBonus = min(0.5, ($marketCount * 0.05) + ($totalMarketLevel * 0.02));
        
        // 最終交換レート（市場ボーナス適用）
        $finalRate = $baseRate * (1 + $marketBonus);
        
        // 受け取り量を計算
        $received = (int)floor($amount * $finalRate);
        
        if ($received < 1) {
            throw new Exception('交換量が少なすぎます。もう少し多くの量を指定してください。');
        }
        
        // 資源を消費
        $stmt = $pdo->prepare("
            UPDATE user_civilization_resources 
            SET amount = amount - ? 
            WHERE user_id = ? AND resource_type_id = ?
        ");
        $stmt->execute([$amount, $me['id'], $fromResourceId]);
        
        // 資源を追加
        $stmt = $pdo->prepare("
            UPDATE user_civilization_resources 
            SET amount = amount + ? 
            WHERE user_id = ? AND resource_type_id = ?
        ");
        $stmt->execute([$received, $me['id'], $toResourceId]);
        
        // ⑤ 交換制限カウンターを更新（全資源合計で管理するため、resource_type_id=0を使用）
        $nextResetAt = (clone $now)->modify('+1 hour')->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO user_market_exchange_limits (user_id, resource_type_id, exchanged_amount, reset_at)
            VALUES (?, 0, ?, ?)
            ON DUPLICATE KEY UPDATE 
                exchanged_amount = IF(reset_at <= NOW(), ?, exchanged_amount + ?),
                reset_at = IF(reset_at <= NOW(), ?, reset_at)
        ");
        $stmt->execute([
            $me['id'], $amount, $nextResetAt,
            $amount, $amount, $nextResetAt
        ]);
        
        $pdo->commit();
        
        $ratePercent = round($finalRate * 100);
        $newExchanged = $currentExchanged + $amount;
        $newRemaining = max(0, $hourlyLimit - $newExchanged);
        echo json_encode([
            'ok' => true,
            'message' => "{$fromResource['from_icon']} {$amount} → {$toResource['to_icon']} {$received} に交換しました！（レート: {$ratePercent}%）",
            'from_amount' => $amount,
            'to_amount' => $received,
            'exchange_rate' => $finalRate,
            'market_count' => $marketCount,
            'market_bonus' => $marketBonus,
            'hourly_limit' => $hourlyLimit,
            'remaining_limit' => $newRemaining
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 市場情報を取得（交換レート計算用）
if ($action === 'get_market_info') {
    try {
        // 市場建物の数を確認
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as market_count, COALESCE(SUM(ucb.level), 0) as total_level
            FROM user_civilization_buildings ucb
            JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
            WHERE ucb.user_id = ? AND bt.building_key = 'market' AND ucb.is_constructing = FALSE
        ");
        $stmt->execute([$me['id']]);
        $marketData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $marketCount = (int)($marketData['market_count'] ?? 0);
        $totalMarketLevel = (int)($marketData['total_level'] ?? 0);
        $marketBonus = min(0.5, ($marketCount * 0.05) + ($totalMarketLevel * 0.02));
        
        // 交換制限情報を取得（全資源合計で管理）
        $hourlyLimit = 10000 * max(1, $marketCount);
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(exchanged_amount), 0) as total_exchanged, MAX(reset_at) as reset_at
            FROM user_market_exchange_limits 
            WHERE user_id = ? AND reset_at > NOW()
        ");
        $stmt->execute([$me['id']]);
        $limitData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $now = new DateTime();
        $totalExchanged = (int)($limitData['total_exchanged'] ?? 0);
        $resetAt = $limitData['reset_at'] ? new DateTime($limitData['reset_at']) : null;
        
        $exchangeLimitInfo = [
            'total_exchanged' => $totalExchanged,
            'remaining' => max(0, $hourlyLimit - $totalExchanged),
            'reset_at' => $limitData['reset_at'],
            'reset_in_seconds' => $resetAt ? max(0, $resetAt->getTimestamp() - $now->getTimestamp()) : 0
        ];
        
        // グローバルの資源価値定義を使用
        global $RESOURCE_VALUES;
        
        echo json_encode([
            'ok' => true,
            'market_count' => $marketCount,
            'total_market_level' => $totalMarketLevel,
            'market_bonus' => $marketBonus,
            'resource_values' => $RESOURCE_VALUES,
            'hourly_limit' => $hourlyLimit,
            'exchange_limits' => $exchangeLimitInfo
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 戦争ログを取得
if ($action === 'get_war_logs') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                wl.*,
                attacker.handle as attacker_handle,
                attacker.display_name as attacker_name,
                defender.handle as defender_handle,
                defender.display_name as defender_name,
                ac.civilization_name as attacker_civ_name,
                dc.civilization_name as defender_civ_name
            FROM civilization_war_logs wl
            JOIN users attacker ON wl.attacker_user_id = attacker.id
            JOIN users defender ON wl.defender_user_id = defender.id
            LEFT JOIN user_civilizations ac ON wl.attacker_user_id = ac.user_id
            LEFT JOIN user_civilizations dc ON wl.defender_user_id = dc.user_id
            WHERE wl.attacker_user_id = ? OR wl.defender_user_id = ?
            ORDER BY wl.battle_at DESC
            LIMIT 50
        ");
        $stmt->execute([$me['id'], $me['id']]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'war_logs' => $logs,
            'my_user_id' => $me['id']
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 総合軍事力を計算（建物 + 兵士）
if ($action === 'get_military_power') {
    try {
        // 建物からの軍事力
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(bt.military_power * ucb.level), 0) as building_power
            FROM user_civilization_buildings ucb
            JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
            WHERE ucb.user_id = ? AND ucb.is_constructing = FALSE
        ");
        $stmt->execute([$me['id']]);
        $buildingPower = (int)$stmt->fetchColumn();
        
        // 兵士からの軍事力（攻撃力 + 防御力の半分）
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM((tt.attack_power + FLOOR(tt.defense_power / 2)) * uct.count), 0) as troop_power
            FROM user_civilization_troops uct
            JOIN civilization_troop_types tt ON uct.troop_type_id = tt.id
            WHERE uct.user_id = ?
        ");
        $stmt->execute([$me['id']]);
        $troopPower = (int)$stmt->fetchColumn();
        
        $totalPower = $buildingPower + $troopPower;
        
        echo json_encode([
            'ok' => true,
            'building_power' => $buildingPower,
            'troop_power' => $troopPower,
            'total_power' => $totalPower
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 兵士選択による攻撃（ターン制バトルシステム）
// ===============================================
if ($action === 'attack_with_troops') {
    $targetUserId = (int)($input['target_user_id'] ?? 0);
    $troops = $input['troops'] ?? []; // [{troop_type_id: 1, count: 10}, ...]
    
    if ($targetUserId <= 0) {
        echo json_encode(['ok' => false, 'error' => '攻撃対象が指定されていません']);
        exit;
    }
    
    if ($targetUserId === $me['id']) {
        echo json_encode(['ok' => false, 'error' => '自分を攻撃することはできません']);
        exit;
    }
    
    if (empty($troops)) {
        echo json_encode(['ok' => false, 'error' => '攻撃部隊を選択してください']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // 対象ユーザーが存在するか確認
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        if (!$stmt->fetch()) {
            throw new Exception('攻撃対象のユーザーが存在しません');
        }
        
        // 同盟関係チェック（同盟相手は攻撃できない）
        $stmt = $pdo->prepare("
            SELECT 1 FROM civilization_alliances 
            WHERE status = 'accepted' AND is_active = TRUE
              AND ((requester_user_id = ? AND target_user_id = ?)
                   OR (requester_user_id = ? AND target_user_id = ?))
            LIMIT 1
        ");
        $stmt->execute([$me['id'], $targetUserId, $targetUserId, $me['id']]);
        if ($stmt->fetch()) {
            throw new Exception('同盟相手を攻撃することはできません');
        }
        
        // 攻撃者の文明
        $myCiv = getUserCivilization($pdo, $me['id']);
        
        // 防御者の文明
        $stmt = $pdo->prepare("SELECT * FROM user_civilizations WHERE user_id = ?");
        $stmt->execute([$targetUserId]);
        $targetCiv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$targetCiv) {
            throw new Exception('相手の文明が見つかりません');
        }
        
        // 攻撃部隊を検証
        $attackerTroops = [];
        foreach ($troops as $troop) {
            $troopTypeId = (int)$troop['troop_type_id'];
            $count = (int)$troop['count'];
            
            if ($count <= 0) continue;
            
            // 所有兵士数を確認
            $stmt = $pdo->prepare("
                SELECT uct.count FROM user_civilization_troops uct
                WHERE uct.user_id = ? AND uct.troop_type_id = ?
            ");
            $stmt->execute([$me['id'], $troopTypeId]);
            $ownedCount = (int)$stmt->fetchColumn();
            
            if ($ownedCount < $count) {
                throw new Exception('兵士が不足しています');
            }
            
            $attackerTroops[] = [
                'troop_type_id' => $troopTypeId,
                'count' => $count
            ];
        }
        
        if (empty($attackerTroops)) {
            throw new Exception('攻撃部隊を選択してください');
        }
        
        // 出撃兵士数上限チェック
        $totalTroopCount = 0;
        foreach ($attackerTroops as $troop) {
            $totalTroopCount += $troop['count'];
        }
        $deploymentLimit = calculateTroopDeploymentLimit($pdo, $me['id']);
        if ($totalTroopCount > $deploymentLimit['total_limit']) {
            throw new Exception('出撃兵士数の上限（' . $deploymentLimit['total_limit'] . '人）を超えています。司令部や軍事センターを建設すると上限が増加します。');
        }
        
        // 防御側の部隊を取得
        $defenderTroops = [];
        
        // 防御部隊設定を確認（なければ全兵士を使用）
        $stmt = $pdo->prepare("SELECT * FROM user_civilization_defense_troops WHERE user_id = ?");
        $stmt->execute([$targetUserId]);
        $defenseSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($defenseSettings)) {
            // 防御設定がない場合は全兵士を使用
            $stmt = $pdo->prepare("
                SELECT troop_type_id, count FROM user_civilization_troops
                WHERE user_id = ? AND count > 0
            ");
            $stmt->execute([$targetUserId]);
            $allTroops = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($allTroops as $troop) {
                $defenderTroops[] = [
                    'troop_type_id' => $troop['troop_type_id'],
                    'count' => $troop['count']
                ];
            }
        } else {
            // 防御設定がある場合
            foreach ($defenseSettings as $setting) {
                if ($setting['assigned_count'] > 0) {
                    $defenderTroops[] = [
                        'troop_type_id' => $setting['troop_type_id'],
                        'count' => $setting['assigned_count']
                    ];
                }
            }
        }
        
        // 装備バフを取得
        $myEquipmentBuffs = getUserEquipmentBuffs($pdo, $me['id']);
        $targetEquipmentBuffs = getUserEquipmentBuffs($pdo, $targetUserId);
        
        // バトルユニットを準備
        $attackerUnit = prepareBattleUnit($attackerTroops, $myEquipmentBuffs, $pdo);
        $defenderUnit = prepareBattleUnit($defenderTroops, $targetEquipmentBuffs, $pdo);
        
        // 攻撃側にヒーロースキルを適用（戦争）
        $attackerHero = getUserBattleHero($pdo, $me['id'], 'war');
        if ($attackerHero) {
            $skillType1 = (int)($attackerHero['skill_1_type'] ?? 1);
            $skillType2 = isset($attackerHero['skill_2_type']) ? (int)$attackerHero['skill_2_type'] : null;
            $attackerUnit = applyHeroSkillsToUnit($attackerUnit, $attackerHero, $skillType1, $skillType2);
        }
        
        // 防御側にもヒーロースキルを適用（防衛）
        $defenderHero = getUserBattleHero($pdo, $targetUserId, 'defense');
        if ($defenderHero) {
            $defSkillType1 = (int)($defenderHero['skill_1_type'] ?? 1);
            $defSkillType2 = isset($defenderHero['skill_2_type']) ? (int)$defenderHero['skill_2_type'] : null;
            $defenderUnit = applyHeroSkillsToUnit($defenderUnit, $defenderHero, $defSkillType1, $defSkillType2);
        }
        
        // ターン制バトルを実行
        $battleResult = executeTurnBattle($attackerUnit, $defenderUnit);
        $attackerWins = $battleResult['attacker_wins'];
        
        // 損失と負傷兵を計算（HPの減少率に基づく）
        $attackerLosses = [];
        $attackerWounded = [];
        $defenderLosses = [];
        $defenderWounded = [];
        
        $attackerHpLossRate = 1 - ($battleResult['attacker_final_hp'] / max(1, $battleResult['attacker_max_hp']));
        $defenderHpLossRate = 1 - ($battleResult['defender_final_hp'] / max(1, $battleResult['defender_max_hp']));
        
        // 攻撃側の損失処理
        foreach ($attackerUnit['troops'] as $troop) {
            $troopTypeId = $troop['troop_type_id'];
            $count = $troop['count'];
            
            // ② 使い捨てユニットは全員死亡扱い
            if (!empty($troop['is_disposable'])) {
                $deaths = $count;
                $wounded = 0;
                $totalLossCount = $count;
            } else {
                // HPの減少率に応じた損失（死亡+負傷）
                $totalLossCount = (int)floor($count * $attackerHpLossRate);
                $deaths = (int)floor($totalLossCount * CIV_DEATH_RATE / (CIV_DEATH_RATE + CIV_WOUNDED_RATE));
                $wounded = $totalLossCount - $deaths;
            }
            
            if ($deaths > 0) {
                $attackerLosses[$troopTypeId] = $deaths;
            }
            if ($wounded > 0) {
                $attackerWounded[$troopTypeId] = $wounded;
            }
            
            // 兵士を減少
            if ($totalLossCount > 0) {
                $stmt = $pdo->prepare("
                    UPDATE user_civilization_troops
                    SET count = count - ?
                    WHERE user_id = ? AND troop_type_id = ?
                ");
                $stmt->execute([$totalLossCount, $me['id'], $troopTypeId]);
            }
            
            // 負傷兵を追加
            if ($wounded > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_civilization_wounded_troops (user_id, troop_type_id, count)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE count = count + ?
                ");
                $stmt->execute([$me['id'], $troopTypeId, $wounded, $wounded]);
            }
        }
        
        // 防御側の損失処理
        // ⑭ シェルターによる兵士保護を計算
        $shelterProtection = 0;
        $stmt = $pdo->prepare("
            SELECT SUM(bt.troop_protection_ratio * ucb.level) as total_protection_ratio, 
                   (SELECT SUM(bt2.military_power * ucb2.level) FROM user_civilization_buildings ucb2 
                    JOIN civilization_building_types bt2 ON ucb2.building_type_id = bt2.id 
                    WHERE ucb2.user_id = ? AND ucb2.is_constructing = FALSE) as military_power
            FROM user_civilization_buildings ucb
            JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
            WHERE ucb.user_id = ? AND ucb.is_constructing = FALSE 
              AND bt.troop_protection_ratio IS NOT NULL
        ");
        $stmt->execute([$targetUserId, $targetUserId]);
        $shelterData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($shelterData && $shelterData['total_protection_ratio'] > 0 && $shelterData['military_power'] > 0) {
            // 軍事力 × 保護倍率 = 保護される兵士数
            $shelterProtection = floor($shelterData['military_power'] * $shelterData['total_protection_ratio']);
        }
        
        // 防御側の総兵士数を計算
        $totalDefenderTroops = array_sum(array_column($defenderUnit['troops'], 'count'));
        // シェルター保護率を計算（保護される兵士の割合）
        $shelterProtectionRate = $totalDefenderTroops > 0 ? min(1, $shelterProtection / $totalDefenderTroops) : 0;
        
        foreach ($defenderUnit['troops'] as $troop) {
            $troopTypeId = $troop['troop_type_id'];
            $count = $troop['count'];
            
            // ② 使い捨てユニットは全員死亡扱い
            if (!empty($troop['is_disposable'])) {
                $deaths = $count;
                $wounded = 0;
                $totalLossCount = $count;
            } else {
                // HPの減少率に応じた損失（死亡+負傷）
                // ⑭ シェルター保護を適用（保護された兵士は損失から除外）
                $effectiveHpLossRate = $defenderHpLossRate * (1 - $shelterProtectionRate);
                $totalLossCount = (int)floor($count * $effectiveHpLossRate);
                $deaths = (int)floor($totalLossCount * CIV_DEATH_RATE / (CIV_DEATH_RATE + CIV_WOUNDED_RATE));
                $wounded = $totalLossCount - $deaths;
            }
            
            if ($deaths > 0) {
                $defenderLosses[$troopTypeId] = $deaths;
            }
            if ($wounded > 0) {
                $defenderWounded[$troopTypeId] = $wounded;
            }
            
            // 兵士を減少
            if ($totalLossCount > 0) {
                $stmt = $pdo->prepare("
                    UPDATE user_civilization_troops
                    SET count = count - ?
                    WHERE user_id = ? AND troop_type_id = ?
                ");
                $stmt->execute([$totalLossCount, $targetUserId, $troopTypeId]);
            }
            
            // 負傷兵を追加
            if ($wounded > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_civilization_wounded_troops (user_id, troop_type_id, count)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE count = count + ?
                ");
                $stmt->execute([$targetUserId, $troopTypeId, $wounded, $wounded]);
            }
        }
        
        // 略奪処理（勝利時のみ）
        $lootCoins = 0;
        $lootResources = [];
        
        if ($attackerWins) {
            // 資源を略奪
            $stmt = $pdo->prepare("
                SELECT ucr.resource_type_id, ucr.amount, rt.resource_key
                FROM user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                WHERE ucr.user_id = ?
            ");
            $stmt->execute([$targetUserId]);
            $targetResources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($targetResources as $res) {
                $loot = floor($res['amount'] * CIV_LOOT_RESOURCE_RATE);
                if ($loot > 0) {
                    $lootResources[$res['resource_key']] = $loot;
                    
                    // 資源を移動
                    $stmt = $pdo->prepare("UPDATE user_civilization_resources SET amount = amount - ? WHERE user_id = ? AND resource_type_id = ?");
                    $stmt->execute([$loot, $targetUserId, $res['resource_type_id']]);
                    
                    $stmt = $pdo->prepare("UPDATE user_civilization_resources SET amount = amount + ? WHERE user_id = ? AND resource_type_id = ?");
                    $stmt->execute([$loot, $me['id'], $res['resource_type_id']]);
                }
            }
            
            // コインを略奪
            $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $targetCoins = (int)$stmt->fetchColumn();
            $lootCoins = (int)floor($targetCoins * CIV_LOOT_COINS_RATE);
            
            if ($lootCoins > 0) {
                $stmt = $pdo->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
                $stmt->execute([$lootCoins, $targetUserId]);
                $stmt = $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                $stmt->execute([$lootCoins, $me['id']]);
            }
        }
        
        // 戦争ログを記録（ターン制バトル情報を含む）
        $battleSummary = generateBattleSummary($battleResult);
        $stmt = $pdo->prepare("
            INSERT INTO civilization_war_logs 
            (attacker_user_id, defender_user_id, attacker_power, defender_power, 
             winner_user_id, loot_coins, loot_resources,
             attacker_troops_used, defender_troops_used, 
             attacker_losses, defender_losses, attacker_wounded, defender_wounded,
             total_turns, battle_log_summary, attacker_final_hp, defender_final_hp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $me['id'], $targetUserId, 
            $attackerUnit['attack'], $defenderUnit['attack'],
            $attackerWins ? $me['id'] : $targetUserId,
            $lootCoins, json_encode($lootResources),
            json_encode($attackerUnit['troops']), json_encode($defenderUnit['troops']),
            json_encode($attackerLosses), json_encode($defenderLosses),
            json_encode($attackerWounded), json_encode($defenderWounded),
            $battleResult['total_turns'], $battleSummary,
            $battleResult['attacker_final_hp'], $battleResult['defender_final_hp']
        ]);
        $warLogId = $pdo->lastInsertId();
        
        // ターン制バトルログを保存
        saveCivilizationBattleTurnLogs($pdo, $warLogId, $battleResult['turn_logs']);
        
        // 戦争メールを作成（攻撃者と防御者両方に送信）
        try {
            list($attackerMailId, $defenderMailId) = createWarBattleMails(
                $pdo, $me['id'], $targetUserId,
                $attackerUnit, $defenderUnit, $battleResult,
                $attackerLosses, $defenderLosses,
                $lootCoins, $lootResources, $warLogId
            );
            
            // 戦争ログにメールIDを紐付け
            $stmt = $pdo->prepare("UPDATE civilization_war_logs SET attacker_mail_id = ?, defender_mail_id = ? WHERE id = ?");
            $stmt->execute([$attackerMailId, $defenderMailId, $warLogId]);
        } catch (Exception $e) {
            // メール作成に失敗しても戦闘処理は継続
            error_log("Failed to create war battle mails: " . $e->getMessage());
        }
        
        // クエスト進捗を更新（攻撃を1回カウント、勝利時は conquest も更新）
        updateCivilizationQuestProgress($pdo, $me['id'], 'attack', null, 1);
        if ($attackerWins) {
            updateCivilizationQuestProgress($pdo, $me['id'], 'conquest', null, 1);
        }
        
        $pdo->commit();
        
        $result = $attackerWins ? 'victory' : 'defeat';
        $message = $attackerWins 
            ? "勝利！{$battleResult['total_turns']}ターンの激戦の末、{$lootCoins}コインと資源を略奪しました！" 
            : "敗北...{$battleResult['total_turns']}ターンの戦いの末、相手の防御に敗れました。";
        
        echo json_encode([
            'ok' => true,
            'result' => $result,
            'message' => $message,
            'battle_result' => [
                'total_turns' => $battleResult['total_turns'],
                'attacker_final_hp' => $battleResult['attacker_final_hp'],
                'attacker_max_hp' => $battleResult['attacker_max_hp'],
                'defender_final_hp' => $battleResult['defender_final_hp'],
                'defender_max_hp' => $battleResult['defender_max_hp']
            ],
            'war_log_id' => $warLogId,
            'attacker_losses' => $attackerLosses,
            'attacker_wounded' => $attackerWounded,
            'defender_losses' => $defenderLosses,
            'defender_wounded' => $defenderWounded,
            'loot_coins' => $lootCoins,
            'loot_resources' => $lootResources
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 防御部隊を設定
// ===============================================
if ($action === 'set_defense_troops') {
    $troops = $input['troops'] ?? []; // [{troop_type_id: 1, count: 10}, ...]
    
    $pdo->beginTransaction();
    try {
        // 既存の防御設定をクリア
        $stmt = $pdo->prepare("DELETE FROM user_civilization_defense_troops WHERE user_id = ?");
        $stmt->execute([$me['id']]);
        
        // 新しい防御設定を追加
        foreach ($troops as $troop) {
            $troopTypeId = (int)$troop['troop_type_id'];
            $count = (int)$troop['count'];
            
            if ($count <= 0) continue;
            
            // 所有兵士数を確認
            $stmt = $pdo->prepare("SELECT count FROM user_civilization_troops WHERE user_id = ? AND troop_type_id = ?");
            $stmt->execute([$me['id'], $troopTypeId]);
            $ownedCount = (int)$stmt->fetchColumn();
            
            if ($count > $ownedCount) {
                $count = $ownedCount;
            }
            
            if ($count > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_civilization_defense_troops (user_id, troop_type_id, assigned_count)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$me['id'], $troopTypeId, $count]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => '防御部隊を設定しました'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 防御部隊設定を取得
// ===============================================
if ($action === 'get_defense_troops') {
    try {
        $stmt = $pdo->prepare("
            SELECT udt.*, tt.name, tt.icon, tt.attack_power, tt.defense_power,
                   COALESCE(tt.health_points, 100) as health_points,
                   COALESCE(tt.troop_category, 'infantry') as troop_category
            FROM user_civilization_defense_troops udt
            JOIN civilization_troop_types tt ON udt.troop_type_id = tt.id
            WHERE udt.user_id = ?
        ");
        $stmt->execute([$me['id']]);
        $defenseTroops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'defense_troops' => $defenseTroops
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 負傷兵一覧を取得
// ===============================================
if ($action === 'get_wounded_troops') {
    try {
        $stmt = $pdo->prepare("
            SELECT uwt.*, tt.name, tt.icon, tt.attack_power, tt.defense_power,
                   COALESCE(tt.heal_time_seconds, 30) as heal_time_seconds,
                   COALESCE(tt.heal_cost_coins, 10) as heal_cost_coins,
                   tt.heal_cost_resources
            FROM user_civilization_wounded_troops uwt
            JOIN civilization_troop_types tt ON uwt.troop_type_id = tt.id
            WHERE uwt.user_id = ? AND uwt.count > 0
        ");
        $stmt->execute([$me['id']]);
        $woundedTroops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 治療中のキューを取得
        $stmt = $pdo->prepare("
            SELECT ucq.*, tt.name, tt.icon
            FROM user_civilization_healing_queue ucq
            JOIN civilization_troop_types tt ON ucq.troop_type_id = tt.id
            WHERE ucq.user_id = ?
            ORDER BY ucq.healing_completes_at ASC
        ");
        $stmt->execute([$me['id']]);
        $healingQueue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ヘルパー関数を使用してキュー上限と容量を計算
        $queueLimit = calculateHealingQueueLimit($pdo, $me['id']);
        
        // 治療中の兵士総数を計算
        $currentHealingCount = 0;
        foreach ($healingQueue as $queue) {
            $currentHealingCount += (int)$queue['count'];
        }
        
        echo json_encode([
            'ok' => true,
            'wounded_troops' => $woundedTroops,
            'healing_queue' => $healingQueue,
            'hospital_capacity' => $queueLimit['capacity'],
            'beds_used' => $currentHealingCount,
            'beds_available' => max(0, $queueLimit['capacity'] - $currentHealingCount),
            'queue_used' => count($healingQueue),
            'queue_max' => $queueLimit['max_queues']
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 負傷兵を治療キューに追加
// ===============================================
if ($action === 'heal_troops') {
    $troopTypeId = (int)($input['troop_type_id'] ?? 0);
    $count = (int)($input['count'] ?? 1);
    
    if ($count < 1) {
        echo json_encode(['ok' => false, 'error' => '治療数を指定してください']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // ヘルパー関数を使用してキュー上限を計算
        $queueLimit = calculateHealingQueueLimit($pdo, $me['id']);
        $maxHealingQueues = $queueLimit['max_queues'];
        
        if ($queueLimit['count'] === 0) {
            throw new Exception('病院または野戦病院を建設してください');
        }
        
        // 現在の治療キュー数と治療中の兵士総数を確認
        $stmt = $pdo->prepare("SELECT COUNT(*) as queue_count, COALESCE(SUM(count), 0) as total_healing FROM user_civilization_healing_queue WHERE user_id = ?");
        $stmt->execute([$me['id']]);
        $queueStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentQueueCount = (int)$queueStats['queue_count'];
        $currentHealingCount = (int)$queueStats['total_healing'];
        
        if ($currentQueueCount >= $maxHealingQueues) {
            throw new Exception("治療キューが満杯です（最大{$maxHealingQueues}個）。病院を建設するとキュー数が増えます。");
        }
        
        // 病床数の確認（空き病床数をチェック）
        $bedCapacity = $queueLimit['capacity'];
        $availableBeds = $bedCapacity - $currentHealingCount;
        
        if ($availableBeds <= 0) {
            throw new Exception("病床が満杯です（現在{$currentHealingCount}/{$bedCapacity}床使用中）。病院を建設すると病床数が増えます。");
        }
        
        // 負傷兵を確認
        $stmt = $pdo->prepare("
            SELECT uwt.count, tt.name, tt.icon,
                   COALESCE(tt.heal_time_seconds, 30) as heal_time_seconds,
                   COALESCE(tt.heal_cost_coins, 10) as heal_cost_coins
            FROM user_civilization_wounded_troops uwt
            JOIN civilization_troop_types tt ON uwt.troop_type_id = tt.id
            WHERE uwt.user_id = ? AND uwt.troop_type_id = ?
        ");
        $stmt->execute([$me['id'], $troopTypeId]);
        $wounded = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$wounded || $wounded['count'] < $count) {
            throw new Exception('負傷兵が不足しています');
        }
        
        // 治療数が空き病床数を超えないように制限
        if ($count > $availableBeds) {
            throw new Exception("空き病床数（{$availableBeds}床）を超えて治療することはできません");
        }
        
        // コストを計算
        $totalCost = $wounded['heal_cost_coins'] * $count;
        
        // コインを確認
        $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['coins'] < $totalCost) {
            throw new Exception('コインが不足しています');
        }
        
        // ③④ 兵種に応じた追加資源（薬草、医薬品、包帯）が十分にあるかチェック
        $resourceCheck = checkHealingResourcesAvailableForTroop($pdo, $me['id'], $troopTypeId, $count);
        if (!$resourceCheck['ok']) {
            $missingList = [];
            foreach ($resourceCheck['missing'] as $res) {
                $missingList[] = "{$res['name']}（必要: {$res['required']}、所持: {$res['available']}）";
            }
            throw new Exception('治療に必要な資源が不足しています: ' . implode('、', $missingList));
        }
        
        // コインを消費
        $stmt = $pdo->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
        $stmt->execute([$totalCost, $me['id']]);
        
        // ③④ 兵種に応じた追加資源を消費（薬草、医薬品、包帯）
        $healingSupplementaryConsumed = consumeHealingSupplementaryResourcesForTroop($pdo, $me['id'], $troopTypeId, $count);
        
        // 治療時間を計算
        $healTime = $wounded['heal_time_seconds'] * $count;
        $completesAt = date('Y-m-d H:i:s', time() + $healTime);
        
        // 負傷兵を減少
        $stmt = $pdo->prepare("
            UPDATE user_civilization_wounded_troops
            SET count = count - ?
            WHERE user_id = ? AND troop_type_id = ?
        ");
        $stmt->execute([$count, $me['id'], $troopTypeId]);
        
        // 治療キューに追加
        $stmt = $pdo->prepare("
            INSERT INTO user_civilization_healing_queue (user_id, troop_type_id, count, healing_started_at, healing_completes_at)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$me['id'], $troopTypeId, $count, $completesAt]);
        
        $pdo->commit();
        
        // ⑤ デイリータスク進捗を更新（heal）
        updateDailyTaskProgressFromCiv($pdo, $me['id'], 'heal', $count);
        
        echo json_encode([
            'ok' => true,
            'message' => "{$wounded['name']} ×{$count} の治療を開始しました",
            'completes_at' => $completesAt,
            'queue_used' => $currentQueueCount + 1,
            'queue_max' => $maxHealingQueues,
            'beds_used' => $currentHealingCount + $count,
            'beds_max' => $bedCapacity
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 治療完了を確認
// ===============================================
if ($action === 'complete_healing') {
    $pdo->beginTransaction();
    try {
        // 完了した治療を取得
        $stmt = $pdo->prepare("
            SELECT ucq.*, tt.name
            FROM user_civilization_healing_queue ucq
            JOIN civilization_troop_types tt ON ucq.troop_type_id = tt.id
            WHERE ucq.user_id = ? AND ucq.healing_completes_at <= NOW()
        ");
        $stmt->execute([$me['id']]);
        $completedHealing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $completedNames = [];
        
        foreach ($completedHealing as $healing) {
            // 兵士を追加
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_troops (user_id, troop_type_id, count)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE count = count + ?
            ");
            $stmt->execute([$me['id'], $healing['troop_type_id'], $healing['count'], $healing['count']]);
            
            // キューから削除
            $stmt = $pdo->prepare("DELETE FROM user_civilization_healing_queue WHERE id = ?");
            $stmt->execute([$healing['id']]);
            
            $completedNames[] = "{$healing['name']} ×{$healing['count']}";
        }
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'completed' => $completedNames,
            'count' => count($completedNames)
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 訓練キューに兵士を追加
// ===============================================
if ($action === 'queue_training') {
    $troopTypeId = (int)($input['troop_type_id'] ?? 0);
    $count = (int)($input['count'] ?? 1);
    
    if ($count < 1 || $count > 100) {
        echo json_encode(['ok' => false, 'error' => '訓練数は1〜100の範囲で指定してください']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // ヘルパー関数を使用してキュー上限を計算
        $queueLimit = calculateTrainingQueueLimit($pdo, $me['id']);
        $maxTrainingQueues = $queueLimit['max_queues'];
        
        // 現在の訓練キュー数を確認
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_civilization_training_queue WHERE user_id = ?");
        $stmt->execute([$me['id']]);
        $currentQueueCount = (int)$stmt->fetchColumn();
        
        if ($currentQueueCount >= $maxTrainingQueues) {
            throw new Exception("訓練キューが満杯です（最大{$maxTrainingQueues}個）。兵舎を建設するとキュー数が増えます。");
        }
        
        // 兵種を確認
        $stmt = $pdo->prepare("SELECT * FROM civilization_troop_types WHERE id = ?");
        $stmt->execute([$troopTypeId]);
        $troopType = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$troopType) {
            throw new Exception('兵種が見つかりません');
        }
        
        // 文明データを確認
        $civ = getUserCivilization($pdo, $me['id']);
        
        // 時代制限チェック
        if ($troopType['unlock_era_id'] && $troopType['unlock_era_id'] > $civ['current_era_id']) {
            throw new Exception('この兵種はまだ利用できません');
        }
        
        // コストを計算
        $totalCoinCost = $troopType['train_cost_coins'] * $count;
        $resourceCosts = json_decode($troopType['train_cost_resources'], true) ?: [];
        
        // コインを確認
        $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['coins'] < $totalCoinCost) {
            throw new Exception('コインが不足しています');
        }
        
        // 資源コストを確認・消費
        foreach ($resourceCosts as $resourceKey => $required) {
            $totalRequired = $required * $count;
            $stmt = $pdo->prepare("
                SELECT ucr.amount 
                FROM user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                WHERE ucr.user_id = ? AND rt.resource_key = ?
            ");
            $stmt->execute([$me['id'], $resourceKey]);
            $currentAmount = (float)$stmt->fetchColumn();
            
            if ($currentAmount < $totalRequired) {
                $resourceName = getResourceName($resourceKey);
                throw new Exception("{$resourceName}が不足しています");
            }
            
            $stmt = $pdo->prepare("
                UPDATE user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                SET ucr.amount = ucr.amount - ?
                WHERE ucr.user_id = ? AND rt.resource_key = ?
            ");
            $stmt->execute([$totalRequired, $me['id'], $resourceKey]);
        }
        
        // コインを消費
        $stmt = $pdo->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
        $stmt->execute([$totalCoinCost, $me['id']]);
        
        // 追加資源を消費（布、馬、ガラス、石油、硫黄、石炭など - 持っている場合のみ）
        $troopCategory = $troopType['troop_category'] ?? 'infantry';
        $unlockEraId = $troopType['unlock_era_id'] ?? 1;
        $supplementaryConsumed = consumeTrainingSupplementaryResources($pdo, $me['id'], $count, $troopCategory, $unlockEraId);
        
        // 訓練時間を計算
        $trainTime = $troopType['train_time_seconds'] * $count;
        $completesAt = date('Y-m-d H:i:s', time() + $trainTime);
        
        // 訓練キューに追加
        $stmt = $pdo->prepare("
            INSERT INTO user_civilization_training_queue (user_id, troop_type_id, count, training_started_at, training_completes_at)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$me['id'], $troopTypeId, $count, $completesAt]);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "{$troopType['name']} ×{$count} の訓練を開始しました",
            'completes_at' => $completesAt,
            'queue_used' => $currentQueueCount + 1,
            'queue_max' => $maxTrainingQueues
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 訓練キューを取得
// ===============================================
if ($action === 'get_training_queue') {
    try {
        // ヘルパー関数を使用してキュー上限を計算
        $queueLimit = calculateTrainingQueueLimit($pdo, $me['id']);
        $maxTrainingQueues = $queueLimit['max_queues'];
        
        $stmt = $pdo->prepare("
            SELECT utq.*, tt.name, tt.icon, tt.attack_power, tt.defense_power
            FROM user_civilization_training_queue utq
            JOIN civilization_troop_types tt ON utq.troop_type_id = tt.id
            WHERE utq.user_id = ?
            ORDER BY utq.training_completes_at ASC
        ");
        $stmt->execute([$me['id']]);
        $trainingQueue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'training_queue' => $trainingQueue,
            'queue_used' => count($trainingQueue),
            'queue_max' => $maxTrainingQueues
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 訓練完了を確認
// ===============================================
if ($action === 'complete_training') {
    $pdo->beginTransaction();
    try {
        // 完了した訓練を取得
        $stmt = $pdo->prepare("
            SELECT utq.*, tt.name
            FROM user_civilization_training_queue utq
            JOIN civilization_troop_types tt ON utq.troop_type_id = tt.id
            WHERE utq.user_id = ? AND utq.training_completes_at <= NOW()
        ");
        $stmt->execute([$me['id']]);
        $completedTraining = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $completedNames = [];
        $totalTrained = 0;
        
        foreach ($completedTraining as $training) {
            // 兵士を追加
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_troops (user_id, troop_type_id, count)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE count = count + ?
            ");
            $stmt->execute([$me['id'], $training['troop_type_id'], $training['count'], $training['count']]);
            
            // キューから削除
            $stmt = $pdo->prepare("DELETE FROM user_civilization_training_queue WHERE id = ?");
            $stmt->execute([$training['id']]);
            
            $completedNames[] = "{$training['name']} ×{$training['count']}";
            $totalTrained += (int)$training['count'];
        }
        
        // ③ デイリータスク「兵士訓練」進捗を更新
        if ($totalTrained > 0) {
            try {
                updateDailyTaskProgressFromCiv($pdo, $me['id'], 'train', $totalTrained);
            } catch (Exception $e) {
                // テーブルがない場合は無視
            }
        }
        
        $pdo->commit();
        
        // ⑤修正: grant_expはトランザクション外で呼び出す
        if ($totalTrained > 0) {
            try {
                grant_exp($me['id'], 'civilization_train', 0);
            } catch (Exception $e) {
                // 経験値付与エラーは無視
            }
        }
        
        echo json_encode([
            'ok' => true,
            'completed' => $completedNames,
            'count' => count($completedNames)
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 訓練・治療を即座に完了（クリスタルまたはダイヤモンド）
// ===============================================
if ($action === 'instant_complete_queue') {
    $queueType = $input['queue_type'] ?? ''; // 'training' or 'healing'
    $queueId = (int)($input['queue_id'] ?? 0);
    $currency = $input['currency'] ?? 'crystal'; // 'crystal' or 'diamond'
    
    if (!in_array($queueType, ['training', 'healing'])) {
        echo json_encode(['ok' => false, 'error' => '無効なキュータイプです']);
        exit;
    }
    
    if (!in_array($currency, ['crystal', 'diamond'])) {
        echo json_encode(['ok' => false, 'error' => '無効な通貨です']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // キューを取得
        $table = $queueType === 'training' ? 'user_civilization_training_queue' : 'user_civilization_healing_queue';
        $timeColumn = $queueType === 'training' ? 'training_completes_at' : 'healing_completes_at';
        
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? AND user_id = ?");
        $stmt->execute([$queueId, $me['id']]);
        $queue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$queue) {
            throw new Exception('キューが見つかりません');
        }
        
        // 残り時間を計算
        $remainingSeconds = max(0, strtotime($queue[$timeColumn]) - time());
        
        // コストを計算
        if ($currency === 'crystal') {
            $minCost = $queueType === 'training' ? CIV_INSTANT_TRAINING_MIN_COST : CIV_INSTANT_HEALING_MIN_COST;
            $cost = max($minCost, (int)ceil($remainingSeconds / CIV_INSTANT_SECONDS_PER_CRYSTAL));
            $currencyColumn = 'crystals';
            $currencyName = 'クリスタル';
        } else {
            $minCost = CIV_INSTANT_DIAMOND_MIN_COST;
            $cost = max($minCost, (int)ceil($remainingSeconds / CIV_INSTANT_SECONDS_PER_DIAMOND));
            $currencyColumn = 'diamonds';
            $currencyName = 'ダイヤモンド';
        }
        
        // 通貨を確認
        $stmt = $pdo->prepare("SELECT {$currencyColumn} FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $balance = (int)$stmt->fetchColumn();
        
        if ($balance < $cost) {
            throw new Exception("{$currencyName}が不足しています（必要: {$cost}、所持: {$balance}）");
        }
        
        // 通貨を消費
        $stmt = $pdo->prepare("UPDATE users SET {$currencyColumn} = {$currencyColumn} - ? WHERE id = ?");
        $stmt->execute([$cost, $me['id']]);
        
        // 兵士を追加
        $stmt = $pdo->prepare("
            INSERT INTO user_civilization_troops (user_id, troop_type_id, count)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE count = count + ?
        ");
        $stmt->execute([$me['id'], $queue['troop_type_id'], $queue['count'], $queue['count']]);
        
        // キューを削除
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$queueId]);
        
        // 兵種名を取得
        $stmt = $pdo->prepare("SELECT name, troop_key FROM civilization_troop_types WHERE id = ?");
        $stmt->execute([$queue['troop_type_id']]);
        $troopInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $troopName = $troopInfo['name'] ?? '';
        $troopKey = $troopInfo['troop_key'] ?? null;
        
        // クエスト進捗を更新（訓練即完了時もクエスト進捗に反映）
        if ($queueType === 'training') {
            updateCivilizationQuestProgress($pdo, $me['id'], 'train', $troopKey, $queue['count']);
        }
        
        $pdo->commit();
        
        // ③ 経験値を付与（トランザクション外で呼び出す）
        try {
            if ($queueType === 'training') {
                grant_exp($me['id'], 'civilization_train', 0);
            }
        } catch (Exception $e) {
            // 経験値付与エラーは無視
        }
        
        $actionName = $queueType === 'training' ? '訓練' : '治療';
        echo json_encode([
            'ok' => true,
            'message' => "{$troopName} ×{$queue['count']} の{$actionName}が完了しました！",
            'currency_spent' => $cost,
            'currency_type' => $currency
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// ダイヤモンドで建物を即完了
// ===============================================
if ($action === 'instant_complete_building_diamond') {
    $buildingId = (int)($input['building_id'] ?? 0);
    
    $pdo->beginTransaction();
    try {
        // 建設中の建物を確認
        $stmt = $pdo->prepare("
            SELECT ucb.*, bt.name, bt.population_capacity, bt.building_key
            FROM user_civilization_buildings ucb
            JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
            WHERE ucb.id = ? AND ucb.user_id = ? AND ucb.is_constructing = TRUE
        ");
        $stmt->execute([$buildingId, $me['id']]);
        $building = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$building) {
            throw new Exception('建設中の建物が見つかりません');
        }
        
        // 残り時間に応じたダイヤモンドコストを計算
        $remainingSeconds = max(0, strtotime($building['construction_completes_at']) - time());
        $diamondCost = max(1, (int)ceil($remainingSeconds / CIV_INSTANT_SECONDS_PER_DIAMOND));
        
        // ダイヤモンドを確認
        $stmt = $pdo->prepare("SELECT diamonds FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['diamonds'] < $diamondCost) {
            throw new Exception("ダイヤモンドが不足しています（必要: {$diamondCost}、所持: {$user['diamonds']}）");
        }
        
        // ダイヤモンドを消費
        $stmt = $pdo->prepare("UPDATE users SET diamonds = diamonds - ? WHERE id = ?");
        $stmt->execute([$diamondCost, $me['id']]);
        
        // 建設を完了
        $stmt = $pdo->prepare("
            UPDATE user_civilization_buildings 
            SET is_constructing = FALSE, construction_completes_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$buildingId]);
        
        // 住宅の場合は人口を増やす
        $populationIncrease = 0;
        if ($building['population_capacity'] > 0) {
            $populationIncrease = $building['population_capacity'] * $building['level'];
            $stmt = $pdo->prepare("
                UPDATE user_civilizations 
                SET population = population + ?,
                    max_population = max_population + ?
                WHERE user_id = ?
            ");
            $stmt->execute([$populationIncrease, $populationIncrease, $me['id']]);
        }
        
        // クエスト進捗を更新（即完了時もクエスト進捗に反映）
        updateCivilizationQuestProgress($pdo, $me['id'], 'build', $building['building_key'], 1);
        
        $pdo->commit();
        
        // ③ 経験値を付与（トランザクション外で呼び出す）
        try {
            grant_exp($me['id'], 'civilization_build', 0);
        } catch (Exception $e) {
            // 経験値付与エラーは無視
        }
        
        echo json_encode([
            'ok' => true,
            'message' => "{$building['name']}の建設が完了しました！",
            'diamonds_spent' => $diamondCost,
            'population_increase' => $populationIncrease
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// ダイヤモンドで研究を即完了
// ===============================================
if ($action === 'instant_complete_research_diamond') {
    $researchId = (int)($input['user_research_id'] ?? 0);
    
    $pdo->beginTransaction();
    try {
        // 研究中の研究を確認
        $stmt = $pdo->prepare("
            SELECT ucr.*, r.name, r.unlock_resource_id, r.research_key
            FROM user_civilization_researches ucr
            JOIN civilization_researches r ON ucr.research_id = r.id
            WHERE ucr.id = ? AND ucr.user_id = ? AND ucr.is_researching = TRUE
        ");
        $stmt->execute([$researchId, $me['id']]);
        $research = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$research) {
            throw new Exception('研究中の研究が見つかりません');
        }
        
        // 残り時間に応じたダイヤモンドコストを計算
        $remainingSeconds = max(0, strtotime($research['research_completes_at']) - time());
        $diamondCost = max(1, (int)ceil($remainingSeconds / CIV_INSTANT_SECONDS_PER_DIAMOND));
        
        // ダイヤモンドを確認
        $stmt = $pdo->prepare("SELECT diamonds FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$me['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['diamonds'] < $diamondCost) {
            throw new Exception("ダイヤモンドが不足しています（必要: {$diamondCost}、所持: {$user['diamonds']}）");
        }
        
        // ダイヤモンドを消費
        $stmt = $pdo->prepare("UPDATE users SET diamonds = diamonds - ? WHERE id = ?");
        $stmt->execute([$diamondCost, $me['id']]);
        
        // 研究を完了
        $stmt = $pdo->prepare("
            UPDATE user_civilization_researches 
            SET is_researching = FALSE, is_completed = TRUE, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$researchId]);
        
        // 複数アンロック対象を処理
        unlockResearchTargets($pdo, $me['id'], $research['research_id']);
        
        // 後方互換性: レガシー単一アンロックチェック
        if ($research['unlock_resource_id']) {
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_resources (user_id, resource_type_id, amount, unlocked, unlocked_at)
                VALUES (?, ?, 0, TRUE, NOW())
                ON DUPLICATE KEY UPDATE unlocked = TRUE, unlocked_at = NOW()
            ");
            $stmt->execute([$me['id'], $research['unlock_resource_id']]);
        }
        
        // クエスト進捗を更新（即完了時もクエスト進捗に反映）
        updateCivilizationQuestProgress($pdo, $me['id'], 'research', $research['research_key'] ?? null, 1);
        
        $pdo->commit();
        
        // ③ 経験値を付与（トランザクション外で呼び出す）
        try {
            grant_exp($me['id'], 'civilization_research', 0);
        } catch (Exception $e) {
            // 経験値付与エラーは無視
        }
        
        echo json_encode([
            'ok' => true,
            'message' => "{$research['name']}の研究が完了しました！",
            'diamonds_spent' => $diamondCost
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 戦闘ログ詳細（ターンログ）を取得
// ===============================================
if ($action === 'get_battle_turn_logs') {
    $warLogId = (int)($input['war_log_id'] ?? 0);
    
    if ($warLogId <= 0) {
        echo json_encode(['ok' => false, 'error' => '戦闘ログIDが指定されていません']);
        exit;
    }
    
    try {
        // 戦闘ログの基本情報を取得（自分が関係している戦闘のみ）
        $stmt = $pdo->prepare("
            SELECT 
                wl.*,
                attacker.handle as attacker_handle,
                attacker.display_name as attacker_name,
                defender.handle as defender_handle,
                defender.display_name as defender_name,
                ac.civilization_name as attacker_civ_name,
                dc.civilization_name as defender_civ_name
            FROM civilization_war_logs wl
            JOIN users attacker ON wl.attacker_user_id = attacker.id
            JOIN users defender ON wl.defender_user_id = defender.id
            LEFT JOIN user_civilizations ac ON wl.attacker_user_id = ac.user_id
            LEFT JOIN user_civilizations dc ON wl.defender_user_id = dc.user_id
            WHERE wl.id = ? AND (wl.attacker_user_id = ? OR wl.defender_user_id = ?)
        ");
        $stmt->execute([$warLogId, $me['id'], $me['id']]);
        $warLog = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$warLog) {
            echo json_encode(['ok' => false, 'error' => '戦闘ログが見つかりません']);
            exit;
        }
        
        // ターンログを取得
        $stmt = $pdo->prepare("
            SELECT * FROM civilization_battle_turn_logs
            WHERE war_log_id = ?
            ORDER BY turn_number ASC, id ASC
        ");
        $stmt->execute([$warLogId]);
        $turnLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 兵種情報を取得して名前を付与
        $troopNames = [];
        $stmt = $pdo->query("SELECT id, name, icon FROM civilization_troop_types");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $troopNames[$row['id']] = [
                'name' => $row['name'],
                'icon' => $row['icon']
            ];
        }
        
        echo json_encode([
            'ok' => true,
            'war_log' => $warLog,
            'turn_logs' => $turnLogs,
            'troop_names' => $troopNames,
            'my_user_id' => $me['id']
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 特殊スキル一覧を取得
// ===============================================
if ($action === 'get_special_skills') {
    try {
        $stmt = $pdo->query("
            SELECT ss.*, 
                   GROUP_CONCAT(tt.name SEPARATOR ', ') as troop_names,
                   GROUP_CONCAT(tt.icon SEPARATOR '') as troop_icons
            FROM battle_special_skills ss
            LEFT JOIN civilization_troop_types tt ON tt.special_skill_id = ss.id
            GROUP BY ss.id
            ORDER BY ss.id
        ");
        $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'special_skills' => $skills
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 兵種と特殊スキル情報を取得
// ===============================================
if ($action === 'get_troops_with_skills') {
    try {
        $stmt = $pdo->prepare("
            SELECT tt.*, 
                   ss.skill_key, ss.name as skill_name, ss.icon as skill_icon,
                   ss.description as skill_description, ss.effect_type,
                   ss.effect_value, ss.duration_turns, ss.activation_chance,
                   COALESCE(uct.count, 0) as owned_count
            FROM civilization_troop_types tt
            LEFT JOIN battle_special_skills ss ON tt.special_skill_id = ss.id
            LEFT JOIN user_civilization_troops uct ON uct.troop_type_id = tt.id AND uct.user_id = ?
            ORDER BY tt.unlock_era_id, tt.id
        ");
        $stmt->execute([$me['id']]);
        $troops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 文明データを取得して時代情報を付与
        $civ = getUserCivilization($pdo, $me['id']);
        
        echo json_encode([
            'ok' => true,
            'troops' => $troops,
            'current_era_id' => $civ['current_era_id']
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 同盟システム API
// ===============================================

/**
 * 2人のユーザーが同盟関係にあるかチェック
 * @param PDO $pdo
 * @param int $userId1
 * @param int $userId2
 * @return bool
 */
function isAllied($pdo, $userId1, $userId2) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM civilization_alliances 
        WHERE status = 'accepted' AND is_active = TRUE
          AND ((requester_user_id = ? AND target_user_id = ?)
               OR (requester_user_id = ? AND target_user_id = ?))
        LIMIT 1
    ");
    $stmt->execute([$userId1, $userId2, $userId2, $userId1]);
    return (bool)$stmt->fetch();
}

// 同盟一覧を取得
if ($action === 'get_alliances') {
    try {
        // 自分に関連する同盟を取得（申請中・締結済み）
        $stmt = $pdo->prepare("
            SELECT 
                ca.*,
                requester.handle as requester_handle,
                requester.display_name as requester_name,
                target.handle as target_handle,
                target.display_name as target_name,
                requester_civ.civilization_name as requester_civ_name,
                target_civ.civilization_name as target_civ_name
            FROM civilization_alliances ca
            JOIN users requester ON ca.requester_user_id = requester.id
            JOIN users target ON ca.target_user_id = target.id
            LEFT JOIN user_civilizations requester_civ ON ca.requester_user_id = requester_civ.user_id
            LEFT JOIN user_civilizations target_civ ON ca.target_user_id = target_civ.user_id
            WHERE (ca.requester_user_id = ? OR ca.target_user_id = ?)
              AND ca.is_active = TRUE
            ORDER BY ca.requested_at DESC
        ");
        $stmt->execute([$me['id'], $me['id']]);
        $alliances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 受信した申請（未処理）
        $stmt = $pdo->prepare("
            SELECT 
                ca.*,
                requester.handle as requester_handle,
                requester.display_name as requester_name,
                requester_civ.civilization_name as requester_civ_name
            FROM civilization_alliances ca
            JOIN users requester ON ca.requester_user_id = requester.id
            LEFT JOIN user_civilizations requester_civ ON ca.requester_user_id = requester_civ.user_id
            WHERE ca.target_user_id = ? AND ca.status = 'pending' AND ca.is_active = TRUE
            ORDER BY ca.requested_at DESC
        ");
        $stmt->execute([$me['id']]);
        $pendingReceived = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 送信した申請（未処理）
        $stmt = $pdo->prepare("
            SELECT 
                ca.*,
                target.handle as target_handle,
                target.display_name as target_name,
                target_civ.civilization_name as target_civ_name
            FROM civilization_alliances ca
            JOIN users target ON ca.target_user_id = target.id
            LEFT JOIN user_civilizations target_civ ON ca.target_user_id = target_civ.user_id
            WHERE ca.requester_user_id = ? AND ca.status = 'pending' AND ca.is_active = TRUE
            ORDER BY ca.requested_at DESC
        ");
        $stmt->execute([$me['id']]);
        $pendingSent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 締結済み同盟
        $stmt = $pdo->prepare("
            SELECT 
                ca.id,
                CASE WHEN ca.requester_user_id = ? THEN ca.target_user_id ELSE ca.requester_user_id END as ally_user_id,
                CASE WHEN ca.requester_user_id = ? THEN target.handle ELSE requester.handle END as ally_handle,
                CASE WHEN ca.requester_user_id = ? THEN target.display_name ELSE requester.display_name END as ally_name,
                CASE WHEN ca.requester_user_id = ? THEN target_civ.civilization_name ELSE requester_civ.civilization_name END as ally_civ_name,
                ca.responded_at as allied_at
            FROM civilization_alliances ca
            JOIN users requester ON ca.requester_user_id = requester.id
            JOIN users target ON ca.target_user_id = target.id
            LEFT JOIN user_civilizations requester_civ ON ca.requester_user_id = requester_civ.user_id
            LEFT JOIN user_civilizations target_civ ON ca.target_user_id = target_civ.user_id
            WHERE (ca.requester_user_id = ? OR ca.target_user_id = ?)
              AND ca.status = 'accepted' AND ca.is_active = TRUE
            ORDER BY ca.responded_at DESC
        ");
        $stmt->execute([$me['id'], $me['id'], $me['id'], $me['id'], $me['id'], $me['id']]);
        $activeAlliances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'alliances' => $alliances,
            'pending_received' => $pendingReceived,
            'pending_sent' => $pendingSent,
            'active_alliances' => $activeAlliances
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 同盟を申請
if ($action === 'request_alliance') {
    $targetUserId = (int)($input['target_user_id'] ?? 0);
    
    if ($targetUserId <= 0) {
        echo json_encode(['ok' => false, 'error' => '対象ユーザーが指定されていません']);
        exit;
    }
    
    if ($targetUserId === $me['id']) {
        echo json_encode(['ok' => false, 'error' => '自分自身と同盟することはできません']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // 対象ユーザーが存在するか確認
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        if (!$stmt->fetch()) {
            throw new Exception('ユーザーが見つかりません');
        }
        
        // 既存の同盟/申請をチェック
        $stmt = $pdo->prepare("
            SELECT * FROM civilization_alliances 
            WHERE ((requester_user_id = ? AND target_user_id = ?)
                   OR (requester_user_id = ? AND target_user_id = ?))
              AND is_active = TRUE
        ");
        $stmt->execute([$me['id'], $targetUserId, $targetUserId, $me['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            if ($existing['status'] === 'accepted') {
                throw new Exception('既に同盟を結んでいます');
            } elseif ($existing['status'] === 'pending') {
                throw new Exception('既に同盟申請が進行中です');
            }
        }
        
        // 同盟申請を作成（既存の非アクティブ/拒否済みのみ上書き可能）
        $stmt = $pdo->prepare("
            INSERT INTO civilization_alliances (requester_user_id, target_user_id, status, requested_at)
            VALUES (?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE 
                status = IF(status != 'accepted' OR is_active = FALSE, 'pending', status), 
                requested_at = IF(status != 'accepted' OR is_active = FALSE, NOW(), requested_at), 
                is_active = IF(status != 'accepted' OR is_active = FALSE, TRUE, is_active), 
                responded_at = IF(status != 'accepted' OR is_active = FALSE, NULL, responded_at), 
                ended_at = IF(status != 'accepted' OR is_active = FALSE, NULL, ended_at)
        ");
        $stmt->execute([$me['id'], $targetUserId]);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => '同盟申請を送信しました'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 同盟申請に応答
if ($action === 'respond_alliance') {
    $allianceId = (int)($input['alliance_id'] ?? 0);
    $accept = (bool)($input['accept'] ?? false);
    
    if ($allianceId <= 0) {
        echo json_encode(['ok' => false, 'error' => '同盟IDが指定されていません']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // 同盟申請を取得（自分が対象者であること）
        $stmt = $pdo->prepare("
            SELECT * FROM civilization_alliances 
            WHERE id = ? AND target_user_id = ? AND status = 'pending' AND is_active = TRUE
        ");
        $stmt->execute([$allianceId, $me['id']]);
        $alliance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$alliance) {
            throw new Exception('同盟申請が見つかりません');
        }
        
        if ($accept) {
            $stmt = $pdo->prepare("
                UPDATE civilization_alliances 
                SET status = 'accepted', responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$allianceId]);
            $message = '同盟を締結しました';
            
            // 同盟クエスト進捗を更新（受け入れた側と申請した側の両方）
            updateCivilizationQuestProgress($pdo, $me['id'], 'alliance', null, 1);
            updateCivilizationQuestProgress($pdo, $alliance['requester_user_id'], 'alliance', null, 1);
        } else {
            $stmt = $pdo->prepare("
                UPDATE civilization_alliances 
                SET status = 'rejected', responded_at = NOW(), is_active = FALSE
                WHERE id = ?
            ");
            $stmt->execute([$allianceId]);
            $message = '同盟申請を拒否しました';
        }
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => $message
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 同盟を解消
if ($action === 'break_alliance') {
    $allianceId = (int)($input['alliance_id'] ?? 0);
    
    if ($allianceId <= 0) {
        echo json_encode(['ok' => false, 'error' => '同盟IDが指定されていません']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // 同盟を取得（自分が関係者であること）
        $stmt = $pdo->prepare("
            SELECT * FROM civilization_alliances 
            WHERE id = ? AND (requester_user_id = ? OR target_user_id = ?) AND status = 'accepted' AND is_active = TRUE
        ");
        $stmt->execute([$allianceId, $me['id'], $me['id']]);
        $alliance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$alliance) {
            throw new Exception('同盟が見つかりません');
        }
        
        $stmt = $pdo->prepare("
            UPDATE civilization_alliances 
            SET is_active = FALSE, ended_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$allianceId]);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => '同盟を解消しました'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 同盟申請をキャンセル
if ($action === 'cancel_alliance_request') {
    $allianceId = (int)($input['alliance_id'] ?? 0);
    
    if ($allianceId <= 0) {
        echo json_encode(['ok' => false, 'error' => '同盟IDが指定されていません']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // 同盟申請を取得（自分が申請者であること）
        $stmt = $pdo->prepare("
            SELECT * FROM civilization_alliances 
            WHERE id = ? AND requester_user_id = ? AND status = 'pending' AND is_active = TRUE
        ");
        $stmt->execute([$allianceId, $me['id']]);
        $alliance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$alliance) {
            throw new Exception('同盟申請が見つかりません');
        }
        
        $stmt = $pdo->prepare("
            UPDATE civilization_alliances 
            SET is_active = FALSE, ended_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$allianceId]);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => '同盟申請をキャンセルしました'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 送兵（兵士援助）API
// ===============================================
if ($action === 'transfer_troops') {
    $targetUserId = (int)($input['target_user_id'] ?? 0);
    $troops = $input['troops'] ?? []; // [{troop_type_id: 1, count: 10}, ...]
    
    if ($targetUserId <= 0) {
        echo json_encode(['ok' => false, 'error' => '対象ユーザーが指定されていません']);
        exit;
    }
    
    if ($targetUserId === $me['id']) {
        echo json_encode(['ok' => false, 'error' => '自分自身に送ることはできません']);
        exit;
    }
    
    if (empty($troops)) {
        echo json_encode(['ok' => false, 'error' => '兵士を選択してください']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // 同盟関係を確認
        if (!isAllied($pdo, $me['id'], $targetUserId)) {
            throw new Exception('同盟相手にのみ兵士を送ることができます');
        }
        
        // 大使館の援助制限を確認
        $transferLimits = calculateEmbassyTransferLimits($pdo, $me['id']);
        
        if ($transferLimits['embassy_level'] === 0) {
            throw new Exception('大使館を建設すると同盟国に援助できるようになります');
        }
        
        // 対象ユーザーが存在するか確認
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        if (!$stmt->fetch()) {
            throw new Exception('ユーザーが見つかりません');
        }
        
        // 転送する兵士総数を計算
        $totalToTransfer = 0;
        foreach ($troops as $troop) {
            $totalToTransfer += (int)($troop['count'] ?? 0);
        }
        
        // 援助上限チェック
        if ($totalToTransfer > $transferLimits['troops_available']) {
            $limit = $transferLimits['troop_limit'];
            $used = $transferLimits['troops_used'];
            $available = $transferLimits['troops_available'];
            throw new Exception("1時間あたりの兵士援助上限を超えています（上限: {$limit}人/時間、使用済み: {$used}人、残り: {$available}人）。大使館をアップグレードすると上限が増えます。");
        }
        
        $totalTransferred = 0;
        
        foreach ($troops as $troop) {
            $troopTypeId = (int)$troop['troop_type_id'];
            $count = (int)$troop['count'];
            
            if ($count <= 0) continue;
            
            // 所有兵士数を確認
            $stmt = $pdo->prepare("
                SELECT count FROM user_civilization_troops 
                WHERE user_id = ? AND troop_type_id = ?
            ");
            $stmt->execute([$me['id'], $troopTypeId]);
            $ownedCount = (int)$stmt->fetchColumn();
            
            if ($ownedCount < $count) {
                throw new Exception('兵士が不足しています');
            }
            
            // 自分から兵士を減少
            $stmt = $pdo->prepare("
                UPDATE user_civilization_troops 
                SET count = count - ?
                WHERE user_id = ? AND troop_type_id = ?
            ");
            $stmt->execute([$count, $me['id'], $troopTypeId]);
            
            // 相手に兵士を追加
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_troops (user_id, troop_type_id, count)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE count = count + ?
            ");
            $stmt->execute([$targetUserId, $troopTypeId, $count, $count]);
            
            // 転送ログを記録
            $stmt = $pdo->prepare("
                INSERT INTO civilization_troop_transfers (sender_user_id, receiver_user_id, troop_type_id, count, transferred_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$me['id'], $targetUserId, $troopTypeId, $count]);
            
            $totalTransferred += $count;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "兵士{$totalTransferred}体を送りました"
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 物資援助（資源援助）API
// ===============================================
if ($action === 'transfer_resources') {
    $targetUserId = (int)($input['target_user_id'] ?? 0);
    $resources = $input['resources'] ?? []; // [{resource_type_id: 1, amount: 100}, ...]
    
    if ($targetUserId <= 0) {
        echo json_encode(['ok' => false, 'error' => '対象ユーザーが指定されていません']);
        exit;
    }
    
    if ($targetUserId === $me['id']) {
        echo json_encode(['ok' => false, 'error' => '自分自身に送ることはできません']);
        exit;
    }
    
    if (empty($resources)) {
        echo json_encode(['ok' => false, 'error' => '資源を選択してください']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // 同盟関係を確認
        if (!isAllied($pdo, $me['id'], $targetUserId)) {
            throw new Exception('同盟相手にのみ資源を送ることができます');
        }
        
        // 大使館の援助制限を確認
        $transferLimits = calculateEmbassyTransferLimits($pdo, $me['id']);
        
        if ($transferLimits['embassy_level'] === 0) {
            throw new Exception('大使館を建設すると同盟国に援助できるようになります');
        }
        
        // 対象ユーザーが存在するか確認
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        if (!$stmt->fetch()) {
            throw new Exception('ユーザーが見つかりません');
        }
        
        // 転送する資源総量を計算
        $totalToTransfer = 0.0;
        foreach ($resources as $resource) {
            $totalToTransfer += (float)($resource['amount'] ?? 0);
        }
        
        // 援助上限チェック
        if ($totalToTransfer > $transferLimits['resources_available']) {
            $limit = $transferLimits['resource_limit'];
            $used = (int)$transferLimits['resources_used'];
            $available = (int)$transferLimits['resources_available'];
            throw new Exception("1時間あたりの資源援助上限を超えています（上限: {$limit}/時間、使用済み: {$used}、残り: {$available}）。大使館をアップグレードすると上限が増えます。");
        }
        
        $totalTransferred = 0;
        
        foreach ($resources as $resource) {
            $resourceTypeId = (int)$resource['resource_type_id'];
            $amount = (float)$resource['amount'];
            
            if ($amount <= 0) continue;
            
            // 所有資源量を確認
            $stmt = $pdo->prepare("
                SELECT amount FROM user_civilization_resources 
                WHERE user_id = ? AND resource_type_id = ?
            ");
            $stmt->execute([$me['id'], $resourceTypeId]);
            $ownedAmount = (float)$stmt->fetchColumn();
            
            if ($ownedAmount < $amount) {
                throw new Exception('資源が不足しています');
            }
            
            // 自分から資源を減少
            $stmt = $pdo->prepare("
                UPDATE user_civilization_resources 
                SET amount = amount - ?
                WHERE user_id = ? AND resource_type_id = ?
            ");
            $stmt->execute([$amount, $me['id'], $resourceTypeId]);
            
            // 相手に資源を追加（まだ持っていない場合は作成）
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_resources (user_id, resource_type_id, amount, unlocked, unlocked_at)
                VALUES (?, ?, ?, TRUE, NOW())
                ON DUPLICATE KEY UPDATE amount = amount + ?, unlocked = TRUE
            ");
            $stmt->execute([$targetUserId, $resourceTypeId, $amount, $amount]);
            
            // 転送ログを記録
            $stmt = $pdo->prepare("
                INSERT INTO civilization_resource_transfers (sender_user_id, receiver_user_id, resource_type_id, amount, transferred_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$me['id'], $targetUserId, $resourceTypeId, $amount]);
            
            $totalTransferred += $amount;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "資源を送りました（合計{$totalTransferred}）"
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 援助ログを取得
// ===============================================
if ($action === 'get_transfer_logs') {
    try {
        // 兵士の受信ログ
        $stmt = $pdo->prepare("
            SELECT ctt.*, tt.name as troop_name, tt.icon as troop_icon, u.handle as sender_handle, uc.civilization_name as sender_civ_name
            FROM civilization_troop_transfers ctt
            JOIN civilization_troop_types tt ON ctt.troop_type_id = tt.id
            JOIN users u ON ctt.sender_user_id = u.id
            LEFT JOIN user_civilizations uc ON ctt.sender_user_id = uc.user_id
            WHERE ctt.receiver_user_id = ?
            ORDER BY ctt.transferred_at DESC
            LIMIT 20
        ");
        $stmt->execute([$me['id']]);
        $troopReceived = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 兵士の送信ログ
        $stmt = $pdo->prepare("
            SELECT ctt.*, tt.name as troop_name, tt.icon as troop_icon, u.handle as receiver_handle, uc.civilization_name as receiver_civ_name
            FROM civilization_troop_transfers ctt
            JOIN civilization_troop_types tt ON ctt.troop_type_id = tt.id
            JOIN users u ON ctt.receiver_user_id = u.id
            LEFT JOIN user_civilizations uc ON ctt.receiver_user_id = uc.user_id
            WHERE ctt.sender_user_id = ?
            ORDER BY ctt.transferred_at DESC
            LIMIT 20
        ");
        $stmt->execute([$me['id']]);
        $troopSent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 資源の受信ログ
        $stmt = $pdo->prepare("
            SELECT crt.*, rt.name as resource_name, rt.icon as resource_icon, u.handle as sender_handle, uc.civilization_name as sender_civ_name
            FROM civilization_resource_transfers crt
            JOIN civilization_resource_types rt ON crt.resource_type_id = rt.id
            JOIN users u ON crt.sender_user_id = u.id
            LEFT JOIN user_civilizations uc ON crt.sender_user_id = uc.user_id
            WHERE crt.receiver_user_id = ?
            ORDER BY crt.transferred_at DESC
            LIMIT 20
        ");
        $stmt->execute([$me['id']]);
        $resourceReceived = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 資源の送信ログ
        $stmt = $pdo->prepare("
            SELECT crt.*, rt.name as resource_name, rt.icon as resource_icon, u.handle as receiver_handle, uc.civilization_name as receiver_civ_name
            FROM civilization_resource_transfers crt
            JOIN civilization_resource_types rt ON crt.resource_type_id = rt.id
            JOIN users u ON crt.receiver_user_id = u.id
            LEFT JOIN user_civilizations uc ON crt.receiver_user_id = uc.user_id
            WHERE crt.sender_user_id = ?
            ORDER BY crt.transferred_at DESC
            LIMIT 20
        ");
        $stmt->execute([$me['id']]);
        $resourceSent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'troop_received' => $troopReceived,
            'troop_sent' => $troopSent,
            'resource_received' => $resourceReceived,
            'resource_sent' => $resourceSent
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 大使館の援助制限情報を取得
// ===============================================
if ($action === 'get_embassy_limits') {
    try {
        $limits = calculateEmbassyTransferLimits($pdo, $me['id']);
        
        echo json_encode([
            'ok' => true,
            'embassy_level' => $limits['embassy_level'],
            'embassy_count' => $limits['embassy_count'],
            'resource_limit_per_hour' => $limits['resource_limit'],
            'troop_limit_per_hour' => $limits['troop_limit'],
            'resources_used_this_hour' => (int)$limits['resources_used'],
            'troops_used_this_hour' => $limits['troops_used'],
            'resources_available' => (int)$limits['resources_available'],
            'troops_available' => $limits['troops_available']
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// チュートリアルシステム API
// ===============================================

/**
 * ユーザーのチュートリアル進捗を取得・初期化するヘルパー関数
 */
function getUserTutorialProgress($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM user_civilization_tutorial_progress WHERE user_id = ?");
    $stmt->execute([$userId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$progress) {
        // 初期化：最初のクエストを設定
        $stmt = $pdo->prepare("SELECT id FROM civilization_tutorial_quests ORDER BY quest_order ASC LIMIT 1");
        $stmt->execute();
        $firstQuestId = $stmt->fetchColumn();
        
        if ($firstQuestId) {
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_tutorial_progress (user_id, current_quest_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $firstQuestId]);
            
            $stmt = $pdo->prepare("SELECT * FROM user_civilization_tutorial_progress WHERE user_id = ?");
            $stmt->execute([$userId]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    return $progress;
}

/**
 * クエストの達成条件をチェックするヘルパー関数
 */
function checkQuestCompletion($pdo, $userId, $quest) {
    switch ($quest['quest_type']) {
        case 'invest':
            // 累計投資額をチェック
            $stmt = $pdo->prepare("SELECT total_invested_coins FROM user_civilizations WHERE user_id = ?");
            $stmt->execute([$userId]);
            $invested = (int)$stmt->fetchColumn();
            return $invested >= $quest['target_count'];
            
        case 'build':
            // 建物を持っているかチェック
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM user_civilization_buildings ucb
                JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
                WHERE ucb.user_id = ? AND bt.building_key = ? AND ucb.is_constructing = FALSE
            ");
            $stmt->execute([$userId, $quest['target_key']]);
            return (int)$stmt->fetchColumn() >= $quest['target_count'];
            
        case 'train':
            // 兵士を持っているかチェック
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(uct.count), 0) FROM user_civilization_troops uct
                JOIN civilization_troop_types tt ON uct.troop_type_id = tt.id
                WHERE uct.user_id = ? AND tt.troop_key = ?
            ");
            $stmt->execute([$userId, $quest['target_key']]);
            return (int)$stmt->fetchColumn() >= $quest['target_count'];
            
        case 'research':
            // 完了した研究があるかチェック
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM user_civilization_researches
                WHERE user_id = ? AND is_completed = TRUE
            ");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn() >= $quest['target_count'];
            
        case 'era':
            // 指定した時代に達しているかチェック
            $stmt = $pdo->prepare("
                SELECT ce.era_order FROM user_civilizations uc
                JOIN civilization_eras ce ON uc.current_era_id = ce.id
                WHERE uc.user_id = ?
            ");
            $stmt->execute([$userId]);
            $currentEraOrder = (int)$stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT era_order FROM civilization_eras WHERE era_key = ?");
            $stmt->execute([$quest['target_key']]);
            $targetEraOrder = (int)$stmt->fetchColumn();
            
            return $currentEraOrder >= $targetEraOrder;
            
        case 'collect':
            // 最終クエスト用（常にtrue）
            return true;
            
        default:
            return false;
    }
}

// チュートリアルデータを取得
if ($action === 'get_tutorial') {
    try {
        // チュートリアルテーブルが存在するかチェック
        $stmt = $pdo->query("SHOW TABLES LIKE 'civilization_tutorial_quests'");
        if (!$stmt->fetch()) {
            echo json_encode([
                'ok' => true,
                'tutorial_available' => false,
                'message' => 'チュートリアルシステムはまだ初期化されていません'
            ]);
            exit;
        }
        
        $progress = getUserTutorialProgress($pdo, $me['id']);
        
        if (!$progress) {
            echo json_encode([
                'ok' => true,
                'tutorial_available' => false,
                'message' => 'チュートリアルクエストが設定されていません'
            ]);
            exit;
        }
        
        // チュートリアル完了済みの場合
        if ($progress['is_tutorial_completed']) {
            echo json_encode([
                'ok' => true,
                'tutorial_available' => true,
                'is_completed' => true,
                'completed_at' => $progress['tutorial_completed_at']
            ]);
            exit;
        }
        
        // 現在のクエストを取得
        $stmt = $pdo->prepare("SELECT * FROM civilization_tutorial_quests WHERE id = ?");
        $stmt->execute([$progress['current_quest_id']]);
        $currentQuest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 達成済みクエストを取得
        $stmt = $pdo->prepare("
            SELECT q.* FROM user_civilization_tutorial_completed uctc
            JOIN civilization_tutorial_quests q ON uctc.quest_id = q.id
            WHERE uctc.user_id = ?
            ORDER BY q.quest_order ASC
        ");
        $stmt->execute([$me['id']]);
        $completedQuests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 全クエスト一覧を取得
        $stmt = $pdo->query("SELECT * FROM civilization_tutorial_quests ORDER BY quest_order ASC");
        $allQuests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 現在のクエストの達成状況をチェック
        $isCurrentQuestCompleted = false;
        if ($currentQuest) {
            $isCurrentQuestCompleted = checkQuestCompletion($pdo, $me['id'], $currentQuest);
        }
        
        echo json_encode([
            'ok' => true,
            'tutorial_available' => true,
            'is_completed' => false,
            'current_quest' => $currentQuest,
            'is_current_quest_completed' => $isCurrentQuestCompleted,
            'completed_quests' => $completedQuests,
            'all_quests' => $allQuests
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// チュートリアルクエストを完了して報酬を受け取る
if ($action === 'complete_tutorial_quest') {
    $pdo->beginTransaction();
    try {
        // チュートリアルテーブルが存在するかチェック
        $stmt = $pdo->query("SHOW TABLES LIKE 'civilization_tutorial_quests'");
        if (!$stmt->fetch()) {
            throw new Exception('チュートリアルシステムが初期化されていません');
        }
        
        $progress = getUserTutorialProgress($pdo, $me['id']);
        
        if (!$progress) {
            throw new Exception('チュートリアル進捗が見つかりません');
        }
        
        if ($progress['is_tutorial_completed']) {
            throw new Exception('チュートリアルは既に完了しています');
        }
        
        // 現在のクエストを取得
        $stmt = $pdo->prepare("SELECT * FROM civilization_tutorial_quests WHERE id = ?");
        $stmt->execute([$progress['current_quest_id']]);
        $currentQuest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentQuest) {
            throw new Exception('クエストが見つかりません');
        }
        
        // 達成条件をチェック
        if (!checkQuestCompletion($pdo, $me['id'], $currentQuest)) {
            throw new Exception('クエストの条件を満たしていません');
        }
        
        // 報酬を付与
        $rewardCoins = (int)$currentQuest['reward_coins'];
        $rewardCrystals = (int)$currentQuest['reward_crystals'];
        $rewardDiamonds = (int)$currentQuest['reward_diamonds'];
        
        if ($rewardCoins > 0 || $rewardCrystals > 0 || $rewardDiamonds > 0) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET coins = coins + ?,
                    crystals = crystals + ?,
                    diamonds = diamonds + ?
                WHERE id = ?
            ");
            $stmt->execute([$rewardCoins, $rewardCrystals, $rewardDiamonds, $me['id']]);
        }
        
        // 資源報酬を付与
        $rewardResources = json_decode($currentQuest['reward_resources'], true) ?: [];
        foreach ($rewardResources as $resourceKey => $amount) {
            $stmt = $pdo->prepare("
                UPDATE user_civilization_resources ucr
                JOIN civilization_resource_types rt ON ucr.resource_type_id = rt.id
                SET ucr.amount = ucr.amount + ?
                WHERE ucr.user_id = ? AND rt.resource_key = ?
            ");
            $stmt->execute([$amount, $me['id'], $resourceKey]);
        }
        
        // クエストを完了済みとして記録
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO user_civilization_tutorial_completed (user_id, quest_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$me['id'], $currentQuest['id']]);
        
        // 次のクエストに進む
        if ($currentQuest['is_final']) {
            // 最終クエスト完了 - チュートリアル完了
            $stmt = $pdo->prepare("
                UPDATE user_civilization_tutorial_progress
                SET is_tutorial_completed = TRUE, tutorial_completed_at = NOW(), current_quest_id = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$me['id']]);
            
            $pdo->commit();
            
            echo json_encode([
                'ok' => true,
                'message' => '🎉 チュートリアル完了！豪華報酬を獲得しました！',
                'is_tutorial_completed' => true,
                'reward_coins' => $rewardCoins,
                'reward_crystals' => $rewardCrystals,
                'reward_diamonds' => $rewardDiamonds,
                'reward_resources' => $rewardResources
            ]);
        } else {
            // 次のクエストを設定
            $stmt = $pdo->prepare("
                SELECT id FROM civilization_tutorial_quests 
                WHERE quest_order > ? 
                ORDER BY quest_order ASC 
                LIMIT 1
            ");
            $stmt->execute([$currentQuest['quest_order']]);
            $nextQuestId = $stmt->fetchColumn();
            
            if ($nextQuestId) {
                $stmt = $pdo->prepare("
                    UPDATE user_civilization_tutorial_progress
                    SET current_quest_id = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$nextQuestId, $me['id']]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'ok' => true,
                'message' => "クエスト「{$currentQuest['title']}」を完了しました！",
                'is_tutorial_completed' => false,
                'reward_coins' => $rewardCoins,
                'reward_crystals' => $rewardCrystals,
                'reward_diamonds' => $rewardDiamonds,
                'reward_resources' => $rewardResources,
                'next_quest_id' => $nextQuestId
            ]);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 文明クエストシステム API（チュートリアル以外のクエスト）
// ===============================================

/**
 * ユーザーの文明クエスト進捗を更新するヘルパー関数
 * クエスト達成条件をチェックして進捗を更新する
 */
function updateCivilizationQuestProgress($pdo, $userId, $questType, $targetKey, $incrementCount = 1) {
    try {
        // ユーザーの現在の時代を取得
        $stmt = $pdo->prepare("SELECT current_era_id FROM user_civilizations WHERE user_id = ?");
        $stmt->execute([$userId]);
        $currentEraId = (int)$stmt->fetchColumn();
        
        // 該当するアクティブなクエストを取得
        $stmt = $pdo->prepare("
            SELECT cq.* FROM civilization_quests cq
            WHERE cq.quest_type = ? 
              AND (cq.target_key = ? OR cq.target_key IS NULL)
              AND cq.era_id <= ?
              AND cq.is_active = TRUE
        ");
        $stmt->execute([$questType, $targetKey, $currentEraId]);
        $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($quests as $quest) {
            // ユーザーのクエスト進捗を取得または作成
            $stmt = $pdo->prepare("
                SELECT * FROM user_civilization_quest_progress 
                WHERE user_id = ? AND quest_id = ?
            ");
            $stmt->execute([$userId, $quest['id']]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 繰り返し可能でリセット期間を過ぎている場合はリセット
            if ($progress && $quest['is_repeatable'] && $progress['is_claimed']) {
                $cooldownExpired = false;
                if ($quest['cooldown_hours'] && $progress['claimed_at']) {
                    $claimedTime = strtotime($progress['claimed_at']);
                    $cooldownEnd = $claimedTime + ($quest['cooldown_hours'] * 3600);
                    if (time() >= $cooldownEnd) {
                        $cooldownExpired = true;
                    }
                }
                
                if ($cooldownExpired) {
                    // 進捗をリセット
                    $stmt = $pdo->prepare("
                        UPDATE user_civilization_quest_progress 
                        SET current_progress = 0, is_completed = FALSE, is_claimed = FALSE, 
                            completed_at = NULL, claimed_at = NULL, last_reset_at = NOW()
                        WHERE user_id = ? AND quest_id = ?
                    ");
                    $stmt->execute([$userId, $quest['id']]);
                    $progress['current_progress'] = 0;
                    $progress['is_completed'] = false;
                    $progress['is_claimed'] = false;
                }
            }
            
            // 既に報酬を受け取っている場合はスキップ
            if ($progress && $progress['is_claimed'] && !$quest['is_repeatable']) {
                continue;
            }
            
            if (!$progress) {
                // 進捗レコードを作成
                // 初期進捗がターゲット数以上の場合は完了フラグも設定
                $initialProgress = min($incrementCount, $quest['target_count']);
                $isCompleted = $initialProgress >= $quest['target_count'];
                $stmt = $pdo->prepare("
                    INSERT INTO user_civilization_quest_progress (user_id, quest_id, current_progress, is_completed, completed_at)
                    VALUES (?, ?, ?, ?, IF(?, NOW(), NULL))
                ");
                $stmt->execute([$userId, $quest['id'], $initialProgress, $isCompleted, $isCompleted]);
            } else if (!$progress['is_claimed']) {
                // 進捗を更新
                $newProgress = min($progress['current_progress'] + $incrementCount, $quest['target_count']);
                $isCompleted = $newProgress >= $quest['target_count'];
                
                $stmt = $pdo->prepare("
                    UPDATE user_civilization_quest_progress 
                    SET current_progress = ?, is_completed = ?, completed_at = IF(? AND completed_at IS NULL, NOW(), completed_at)
                    WHERE user_id = ? AND quest_id = ?
                ");
                $stmt->execute([$newProgress, $isCompleted, $isCompleted, $userId, $quest['id']]);
            }
        }
    } catch (Exception $e) {
        // クエスト進捗の更新エラーは黙って無視（メインの処理に影響を与えない）
        error_log("Quest progress update error for user {$userId}, type {$questType}, target {$targetKey}: " . $e->getMessage());
    }
}

/**
 * 繰り返し可能クエストを毎日自動リセットするヘルパー関数
 * クールダウン期間が経過した繰り返し可能クエストの進捗をリセットする
 * CRONを使わずに、クエスト一覧取得時に遅延リセットを行う
 * 
 * @param PDO $pdo データベース接続
 * @param int $userId ユーザーID
 */
function resetDailyRepeatableQuests($pdo, $userId) {
    try {
        // 繰り返し可能クエストで、報酬を受け取り済みかつクールダウン期間が経過したものを一括リセット
        // DATE_ADD(claimed_at, INTERVAL cooldown_hours HOUR) <= NOW() でクールダウン経過を判定
        $stmt = $pdo->prepare("
            UPDATE user_civilization_quest_progress ucqp
            JOIN civilization_quests cq ON ucqp.quest_id = cq.id
            SET ucqp.current_progress = 0, 
                ucqp.is_completed = FALSE, 
                ucqp.is_claimed = FALSE, 
                ucqp.completed_at = NULL, 
                ucqp.claimed_at = NULL, 
                ucqp.last_reset_at = NOW()
            WHERE ucqp.user_id = ?
              AND ucqp.is_claimed = TRUE
              AND cq.is_repeatable = TRUE
              AND cq.cooldown_hours IS NOT NULL
              AND cq.cooldown_hours > 0
              AND ucqp.claimed_at IS NOT NULL
              AND DATE_ADD(ucqp.claimed_at, INTERVAL cq.cooldown_hours HOUR) <= NOW()
        ");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        // データベースエラーのみログに記録（ユーザー体験を妨げないため例外は投げない）
        error_log("Daily quest reset DB error for user {$userId}: " . $e->getMessage());
    } catch (Exception $e) {
        // その他のエラーもログに記録
        error_log("Daily quest reset error for user {$userId}: " . $e->getMessage());
    }
}

// 文明クエスト一覧を取得
if ($action === 'get_civilization_quests') {
    try {
        // civilization_questsテーブルが存在するかチェック
        $stmt = $pdo->query("SHOW TABLES LIKE 'civilization_quests'");
        if (!$stmt->fetch()) {
            echo json_encode([
                'ok' => true,
                'quests_available' => false,
                'message' => 'クエストシステムはまだ初期化されていません'
            ]);
            exit;
        }
        
        // 毎日リセット処理（CRONを使わずに遅延リセット）
        resetDailyRepeatableQuests($pdo, $me['id']);
        
        $civ = getUserCivilization($pdo, $me['id']);
        
        // ユーザーの時代以下のクエストを取得
        $stmt = $pdo->prepare("
            SELECT cq.*, e.name as era_name, e.icon as era_icon
            FROM civilization_quests cq
            JOIN civilization_eras e ON cq.era_id = e.id
            WHERE cq.era_id <= ? AND cq.is_active = TRUE
            ORDER BY cq.era_id ASC, cq.sort_order ASC
        ");
        $stmt->execute([$civ['current_era_id']]);
        $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ユーザーの進捗を取得
        $stmt = $pdo->prepare("
            SELECT * FROM user_civilization_quest_progress WHERE user_id = ?
        ");
        $stmt->execute([$me['id']]);
        $progressRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $progressMap = [];
        foreach ($progressRows as $row) {
            $progressMap[$row['quest_id']] = $row;
        }
        
        // 各クエストに進捗情報を付加
        foreach ($quests as &$quest) {
            $progress = $progressMap[$quest['id']] ?? null;
            $quest['current_progress'] = $progress ? (int)$progress['current_progress'] : 0;
            $quest['is_completed'] = $progress ? (bool)$progress['is_completed'] : false;
            $quest['is_claimed'] = $progress ? (bool)$progress['is_claimed'] : false;
            $quest['completed_at'] = $progress ? $progress['completed_at'] : null;
            $quest['claimed_at'] = $progress ? $progress['claimed_at'] : null;
            
            // 繰り返し可能クエストのクールダウン確認
            if ($quest['is_repeatable'] && $quest['is_claimed'] && $quest['cooldown_hours']) {
                $claimedTime = strtotime($quest['claimed_at']);
                $cooldownEnd = $claimedTime + ($quest['cooldown_hours'] * 3600);
                $quest['cooldown_remaining'] = max(0, $cooldownEnd - time());
                $quest['cooldown_ends_at'] = date('Y-m-d H:i:s', $cooldownEnd);
            } else {
                $quest['cooldown_remaining'] = 0;
                $quest['cooldown_ends_at'] = null;
            }
            
            // 報酬資源をデコード
            $quest['reward_resources'] = json_decode($quest['reward_resources'], true) ?: [];
        }
        unset($quest);
        
        // カテゴリごとにグループ化
        $questsByCategory = [];
        foreach ($quests as $quest) {
            $category = $quest['quest_category'];
            if (!isset($questsByCategory[$category])) {
                $questsByCategory[$category] = [];
            }
            $questsByCategory[$category][] = $quest;
        }
        
        // カテゴリ情報
        $categoryInfo = [
            'training' => ['name' => '兵士訓練', 'icon' => '⚔️'],
            'production' => ['name' => '資源生産', 'icon' => '📦'],
            'building' => ['name' => '建物建築', 'icon' => '🏗️'],
            'research' => ['name' => '研究', 'icon' => '📚'],
            'conquest' => ['name' => '占領戦', 'icon' => '🏴'],
            'monster' => ['name' => '放浪モンスター', 'icon' => '👹'],
            'world_boss' => ['name' => 'ワールドボス', 'icon' => '🐉'],
            'alliance' => ['name' => '同盟', 'icon' => '🤝'],
            'trade' => ['name' => '交易', 'icon' => '🏪']
        ];
        
        echo json_encode([
            'ok' => true,
            'quests_available' => true,
            'quests' => $quests,
            'quests_by_category' => $questsByCategory,
            'category_info' => $categoryInfo,
            'current_era_id' => $civ['current_era_id']
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 文明クエスト報酬を受け取る
if ($action === 'claim_civilization_quest_reward') {
    $questId = (int)($input['quest_id'] ?? 0);
    
    if ($questId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'クエストIDが指定されていません']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // クエスト情報を取得
        $stmt = $pdo->prepare("SELECT * FROM civilization_quests WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$questId]);
        $quest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quest) {
            throw new Exception('クエストが見つかりません');
        }
        
        // 時代チェック
        $civ = getUserCivilization($pdo, $me['id']);
        if ($quest['era_id'] > $civ['current_era_id']) {
            throw new Exception('このクエストはまだ利用できません');
        }
        
        // ユーザーの進捗を取得
        $stmt = $pdo->prepare("
            SELECT * FROM user_civilization_quest_progress 
            WHERE user_id = ? AND quest_id = ?
        ");
        $stmt->execute([$me['id'], $questId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$progress) {
            throw new Exception('クエストを開始していません');
        }
        
        if (!$progress['is_completed']) {
            throw new Exception('クエストがまだ完了していません');
        }
        
        if ($progress['is_claimed'] && !$quest['is_repeatable']) {
            throw new Exception('報酬は既に受け取り済みです');
        }
        
        // 繰り返し可能クエストのクールダウンチェック
        if ($progress['is_claimed'] && $quest['is_repeatable'] && $quest['cooldown_hours']) {
            $claimedTime = strtotime($progress['claimed_at']);
            $cooldownEnd = $claimedTime + ($quest['cooldown_hours'] * 3600);
            if (time() < $cooldownEnd) {
                $remainingHours = ceil(($cooldownEnd - time()) / 3600);
                throw new Exception("クールダウン中です（あと約{$remainingHours}時間）");
            }
        }
        
        // 報酬を付与
        $rewardCoins = (int)$quest['reward_coins'];
        $rewardCrystals = (int)$quest['reward_crystals'];
        $rewardDiamonds = (int)$quest['reward_diamonds'];
        
        if ($rewardCoins > 0 || $rewardCrystals > 0 || $rewardDiamonds > 0) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET coins = coins + ?,
                    crystals = crystals + ?,
                    diamonds = diamonds + ?
                WHERE id = ?
            ");
            $stmt->execute([$rewardCoins, $rewardCrystals, $rewardDiamonds, $me['id']]);
        }
        
        // 資源報酬を付与
        $rewardResources = json_decode($quest['reward_resources'], true) ?: [];
        foreach ($rewardResources as $resourceKey => $amount) {
            // 資源がアンロックされていなければアンロック
            $stmt = $pdo->prepare("
                INSERT INTO user_civilization_resources (user_id, resource_type_id, amount, unlocked, unlocked_at)
                SELECT ?, id, ?, TRUE, NOW()
                FROM civilization_resource_types WHERE resource_key = ?
                ON DUPLICATE KEY UPDATE amount = amount + ?, unlocked = TRUE
            ");
            $stmt->execute([$me['id'], $amount, $resourceKey, $amount]);
        }
        
        // 進捗を報酬受取済みに更新
        if ($quest['is_repeatable']) {
            // 繰り返し可能クエストはリセット
            $stmt = $pdo->prepare("
                UPDATE user_civilization_quest_progress 
                SET is_completed = FALSE, is_claimed = TRUE, claimed_at = NOW(), current_progress = 0
                WHERE user_id = ? AND quest_id = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE user_civilization_quest_progress 
                SET is_claimed = TRUE, claimed_at = NOW()
                WHERE user_id = ? AND quest_id = ?
            ");
        }
        $stmt->execute([$me['id'], $questId]);
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => "クエスト「{$quest['title']}」の報酬を受け取りました！",
            'reward_coins' => $rewardCoins,
            'reward_crystals' => $rewardCrystals,
            'reward_diamonds' => $rewardDiamonds,
            'reward_resources' => $rewardResources
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// チュートリアルモーダル設定を取得
if ($action === 'get_tutorial_modal_config') {
    try {
        // テーブルが存在するかチェック
        $stmt = $pdo->query("SHOW TABLES LIKE 'civilization_tutorial_modal_config'");
        if (!$stmt->fetch()) {
            echo json_encode([
                'ok' => true,
                'modal_available' => false,
                'message' => 'チュートリアルモーダルシステムはまだ初期化されていません'
            ]);
            exit;
        }
        
        // チュートリアル進捗テーブルが存在するかチェック
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_civilization_tutorial_progress'");
        if (!$stmt->fetch()) {
            echo json_encode([
                'ok' => true,
                'modal_available' => false,
                'message' => 'チュートリアル進捗システムはまだ初期化されていません'
            ]);
            exit;
        }
        
        // ユーザーのチュートリアル進捗を取得
        $stmt = $pdo->prepare("SELECT * FROM user_civilization_tutorial_progress WHERE user_id = ?");
        $stmt->execute([$me['id']]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // チュートリアル完了済みの場合
        if ($progress && $progress['is_tutorial_completed']) {
            echo json_encode([
                'ok' => true,
                'modal_available' => true,
                'show_modal' => false,
                'is_tutorial_completed' => true
            ]);
            exit;
        }
        
        // 現在のクエストIDを取得
        $currentQuestId = $progress ? $progress['current_quest_id'] : null;
        
        if (!$currentQuestId) {
            // 最初のクエストを取得
            $stmt = $pdo->prepare("SELECT id FROM civilization_tutorial_quests ORDER BY quest_order ASC LIMIT 1");
            $stmt->execute();
            $currentQuestId = $stmt->fetchColumn();
        }
        
        if (!$currentQuestId) {
            echo json_encode([
                'ok' => true,
                'modal_available' => false,
                'message' => 'チュートリアルクエストが設定されていません'
            ]);
            exit;
        }
        
        // 現在のクエストのモーダル設定を取得
        $stmt = $pdo->prepare("
            SELECT ctmc.*, ctq.title as quest_title, ctq.description as quest_description,
                   ctq.icon as quest_icon, ctq.quest_type, ctq.target_key, ctq.target_count
            FROM civilization_tutorial_modal_config ctmc
            JOIN civilization_tutorial_quests ctq ON ctmc.quest_id = ctq.id
            WHERE ctmc.quest_id = ?
        ");
        $stmt->execute([$currentQuestId]);
        $modalConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ユーザーのモーダル表示状態を取得
        $showModal = true;
        $stmt = $pdo->prepare("SELECT * FROM user_tutorial_modal_state WHERE user_id = ?");
        $stmt->execute([$me['id']]);
        $modalState = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($modalState && $modalState['current_modal_quest_id'] == $currentQuestId && $modalState['modal_dismissed']) {
            $showModal = false;
        }
        
        echo json_encode([
            'ok' => true,
            'modal_available' => true,
            'show_modal' => $showModal && $modalConfig !== false,
            'modal_config' => $modalConfig ?: null,
            'current_quest_id' => $currentQuestId,
            'is_tutorial_completed' => false
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// チュートリアルモーダルを閉じた状態を保存
if ($action === 'dismiss_tutorial_modal') {
    $questId = (int)($input['quest_id'] ?? 0);
    
    try {
        // テーブルが存在するかチェック
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_tutorial_modal_state'");
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'モーダル状態テーブルが存在しません']);
            exit;
        }
        
        // モーダル状態を保存
        $stmt = $pdo->prepare("
            INSERT INTO user_tutorial_modal_state (user_id, current_modal_quest_id, modal_dismissed, modal_shown_at)
            VALUES (?, ?, TRUE, NOW())
            ON DUPLICATE KEY UPDATE 
                current_modal_quest_id = ?,
                modal_dismissed = TRUE,
                modal_shown_at = NOW()
        ");
        $stmt->execute([$me['id'], $questId, $questId]);
        
        echo json_encode([
            'ok' => true,
            'message' => 'モーダルを閉じました'
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// チュートリアルモーダルをリセット（次のクエストに移動した際）
if ($action === 'reset_tutorial_modal') {
    try {
        $stmt = $pdo->prepare("
            UPDATE user_tutorial_modal_state 
            SET modal_dismissed = FALSE 
            WHERE user_id = ?
        ");
        $stmt->execute([$me['id']]);
        
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// リーダーボード（ランキング）を取得
// ===============================================
if ($action === 'get_leaderboards') {
    try {
        $rankingType = $input['ranking_type'] ?? 'population';
        $limit = min(50, max(10, (int)($input['limit'] ?? 20)));
        
        $rankings = [];
        $myRank = null;
        $myValue = null;
        
        switch ($rankingType) {
            case 'population':
                // 人口ランキング
                $stmt = $pdo->query("
                    SELECT uc.user_id, uc.civilization_name, uc.population as value, u.handle
                    FROM user_civilizations uc
                    JOIN users u ON uc.user_id = u.id
                    ORDER BY uc.population DESC
                    LIMIT {$limit}
                ");
                $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 自分の順位を取得
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) + 1 as rank_position, 
                           (SELECT population FROM user_civilizations WHERE user_id = ?) as value
                    FROM user_civilizations 
                    WHERE population > (SELECT population FROM user_civilizations WHERE user_id = ?)
                ");
                $stmt->execute([$me['id'], $me['id']]);
                $myRankData = $stmt->fetch(PDO::FETCH_ASSOC);
                $myRank = $myRankData['rank_position'] ?? null;
                $myValue = $myRankData['value'] ?? 0;
                break;
                
            case 'military_power':
                // 軍事力ランキング（動的に計算）
                // 建物パワー + 兵士パワー + 装備パワーを計算
                $stmt = $pdo->query("
                    SELECT 
                        uc.user_id, 
                        uc.civilization_name, 
                        u.handle,
                        (
                            COALESCE((
                                SELECT SUM(bt.military_power * ucb.level)
                                FROM user_civilization_buildings ucb
                                JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
                                WHERE ucb.user_id = uc.user_id AND ucb.is_constructing = FALSE
                            ), 0) +
                            COALESCE((
                                SELECT SUM((tt.attack_power + FLOOR(tt.defense_power / 2) + FLOOR(COALESCE(tt.health_points, 100) / " . CIV_TROOP_HEALTH_TO_POWER_RATIO . ")) * uct.count)
                                FROM user_civilization_troops uct
                                JOIN civilization_troop_types tt ON uct.troop_type_id = tt.id
                                WHERE uct.user_id = uc.user_id
                            ), 0)
                        ) as value
                    FROM user_civilizations uc
                    JOIN users u ON uc.user_id = u.id
                    ORDER BY value DESC
                    LIMIT {$limit}
                ");
                $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 自分の軍事力を計算（装備は含めない - SQLと同じ計算）
                $myPowerData = calculateTotalMilitaryPower($pdo, $me['id'], false);
                $myValue = $myPowerData['building_power'] + $myPowerData['troop_power'];
                
                // 自分の順位を取得
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) + 1 as rank_position
                    FROM user_civilizations uc
                    WHERE (
                        COALESCE((
                            SELECT SUM(bt.military_power * ucb.level)
                            FROM user_civilization_buildings ucb
                            JOIN civilization_building_types bt ON ucb.building_type_id = bt.id
                            WHERE ucb.user_id = uc.user_id AND ucb.is_constructing = FALSE
                        ), 0) +
                        COALESCE((
                            SELECT SUM((tt.attack_power + FLOOR(tt.defense_power / 2) + FLOOR(COALESCE(tt.health_points, 100) / " . CIV_TROOP_HEALTH_TO_POWER_RATIO . ")) * uct.count)
                            FROM user_civilization_troops uct
                            JOIN civilization_troop_types tt ON uct.troop_type_id = tt.id
                            WHERE uct.user_id = uc.user_id
                        ), 0)
                    ) > ?
                ");
                $stmt->execute([$myValue]);
                $myRank = (int)$stmt->fetchColumn();
                break;
                
            case 'total_soldiers':
                // 総兵士数ランキング
                $stmt = $pdo->query("
                    SELECT uct.user_id, uc.civilization_name, SUM(uct.count) as value, u.handle
                    FROM user_civilization_troops uct
                    JOIN user_civilizations uc ON uct.user_id = uc.user_id
                    JOIN users u ON uct.user_id = u.id
                    GROUP BY uct.user_id, uc.civilization_name, u.handle
                    ORDER BY value DESC
                    LIMIT {$limit}
                ");
                $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(count), 0) as my_total FROM user_civilization_troops WHERE user_id = ?
                ");
                $stmt->execute([$me['id']]);
                $myValue = (int)$stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) + 1 as rank_position
                    FROM (
                        SELECT user_id, SUM(count) as total
                        FROM user_civilization_troops
                        GROUP BY user_id
                    ) as totals
                    WHERE total > ?
                ");
                $stmt->execute([$myValue]);
                $myRank = (int)$stmt->fetchColumn();
                break;
                
            case 'total_buildings':
                // 総建築物数ランキング
                $stmt = $pdo->query("
                    SELECT ucb.user_id, uc.civilization_name, COUNT(*) as value, u.handle
                    FROM user_civilization_buildings ucb
                    JOIN user_civilizations uc ON ucb.user_id = uc.user_id
                    JOIN users u ON ucb.user_id = u.id
                    WHERE ucb.is_constructing = FALSE
                    GROUP BY ucb.user_id, uc.civilization_name, u.handle
                    ORDER BY value DESC
                    LIMIT {$limit}
                ");
                $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as my_total FROM user_civilization_buildings WHERE user_id = ? AND is_constructing = FALSE
                ");
                $stmt->execute([$me['id']]);
                $myValue = (int)$stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) + 1 as rank_position
                    FROM (
                        SELECT user_id, COUNT(*) as total
                        FROM user_civilization_buildings
                        WHERE is_constructing = FALSE
                        GROUP BY user_id
                    ) as totals
                    WHERE total > ?
                ");
                $stmt->execute([$myValue]);
                $myRank = (int)$stmt->fetchColumn();
                break;
            
            case 'battle_wins':
                // 戦闘勝利数ランキング（ユーザー間戦闘のみ）
                // civilization_war_logsはユーザー間戦闘のみを記録
                $stmt = $pdo->query("
                    SELECT wl.winner_user_id as user_id, uc.civilization_name, COUNT(*) as value, u.handle
                    FROM civilization_war_logs wl
                    JOIN user_civilizations uc ON wl.winner_user_id = uc.user_id
                    JOIN users u ON wl.winner_user_id = u.id
                    GROUP BY wl.winner_user_id, uc.civilization_name, u.handle
                    ORDER BY value DESC
                    LIMIT {$limit}
                ");
                $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as my_total FROM civilization_war_logs WHERE winner_user_id = ?
                ");
                $stmt->execute([$me['id']]);
                $myValue = (int)$stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) + 1 as rank_position
                    FROM (
                        SELECT winner_user_id, COUNT(*) as total
                        FROM civilization_war_logs
                        GROUP BY winner_user_id
                    ) as totals
                    WHERE total > ?
                ");
                $stmt->execute([$myValue]);
                $myRank = (int)$stmt->fetchColumn();
                break;
            
            case 'battle_losses':
                // 戦闘敗北数ランキング（攻撃者として敗北 OR 防御者として敗北）
                // ユーザー間戦闘のみカウント（civilization_war_logsはユーザー間戦闘のみ）
                $stmt = $pdo->query("
                    SELECT losses.loser_user_id as user_id, uc.civilization_name, losses.value, u.handle
                    FROM (
                        SELECT 
                            CASE 
                                WHEN wl.winner_user_id = wl.attacker_user_id THEN wl.defender_user_id
                                ELSE wl.attacker_user_id
                            END as loser_user_id,
                            COUNT(*) as value
                        FROM civilization_war_logs wl
                        GROUP BY loser_user_id
                    ) as losses
                    JOIN user_civilizations uc ON losses.loser_user_id = uc.user_id
                    JOIN users u ON losses.loser_user_id = u.id
                    ORDER BY losses.value DESC
                    LIMIT {$limit}
                ");
                $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 自分の敗北数を取得（ユーザー間戦闘のみ）
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as my_total 
                    FROM civilization_war_logs 
                    WHERE (attacker_user_id = ? AND winner_user_id != ?) 
                       OR (defender_user_id = ? AND winner_user_id != ?)
                ");
                $stmt->execute([$me['id'], $me['id'], $me['id'], $me['id']]);
                $myValue = (int)$stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) + 1 as rank_position
                    FROM (
                        SELECT 
                            CASE 
                                WHEN wl.winner_user_id = wl.attacker_user_id THEN wl.defender_user_id
                                ELSE wl.attacker_user_id
                            END as loser_user_id,
                            COUNT(*) as value
                        FROM civilization_war_logs wl
                        GROUP BY loser_user_id
                    ) as losses
                    WHERE losses.value > ?
                ");
                $stmt->execute([$myValue]);
                $myRank = (int)$stmt->fetchColumn();
                break;
                
            case 'conquest_wins':
                // 占領戦優勝回数ランキング
                $stmt = $pdo->query("
                    SELECT cs.winner_user_id as user_id, uc.civilization_name, COUNT(*) as value, u.handle
                    FROM conquest_seasons cs
                    JOIN user_civilizations uc ON cs.winner_user_id = uc.user_id
                    JOIN users u ON cs.winner_user_id = u.id
                    WHERE cs.winner_user_id IS NOT NULL
                    GROUP BY cs.winner_user_id, uc.civilization_name, u.handle
                    ORDER BY value DESC
                    LIMIT {$limit}
                ");
                $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as my_total FROM conquest_seasons WHERE winner_user_id = ?
                ");
                $stmt->execute([$me['id']]);
                $myValue = (int)$stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) + 1 as rank_position
                    FROM (
                        SELECT winner_user_id, COUNT(*) as total
                        FROM conquest_seasons
                        WHERE winner_user_id IS NOT NULL
                        GROUP BY winner_user_id
                    ) as totals
                    WHERE total > ?
                ");
                $stmt->execute([$myValue]);
                $myRank = (int)$stmt->fetchColumn();
                break;
                
            case 'castle_captures':
                // 拠点占領回数ランキング
                $stmt = $pdo->query("
                    SELECT cbl.attacker_user_id as user_id, uc.civilization_name, COUNT(*) as value, u.handle
                    FROM conquest_battle_logs cbl
                    JOIN user_civilizations uc ON cbl.attacker_user_id = uc.user_id
                    JOIN users u ON cbl.attacker_user_id = u.id
                    WHERE cbl.castle_captured = TRUE
                    GROUP BY cbl.attacker_user_id, uc.civilization_name, u.handle
                    ORDER BY value DESC
                    LIMIT {$limit}
                ");
                $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as my_total FROM conquest_battle_logs WHERE attacker_user_id = ? AND castle_captured = TRUE
                ");
                $stmt->execute([$me['id']]);
                $myValue = (int)$stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) + 1 as rank_position
                    FROM (
                        SELECT attacker_user_id, COUNT(*) as total
                        FROM conquest_battle_logs
                        WHERE castle_captured = TRUE
                        GROUP BY attacker_user_id
                    ) as totals
                    WHERE total > ?
                ");
                $stmt->execute([$myValue]);
                $myRank = (int)$stmt->fetchColumn();
                break;
            
            case 'era':
                // ⑫ 時代ランキング
                $stmt = $pdo->query("
                    SELECT uc.user_id, uc.civilization_name, e.era_order as value, u.handle, e.name as era_name, e.icon as era_icon
                    FROM user_civilizations uc
                    JOIN users u ON uc.user_id = u.id
                    JOIN civilization_eras e ON uc.current_era_id = e.id
                    ORDER BY e.era_order DESC, uc.population DESC
                    LIMIT {$limit}
                ");
                $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 自分の時代情報を取得
                $stmt = $pdo->prepare("
                    SELECT e.era_order, e.name as era_name, e.icon as era_icon
                    FROM user_civilizations uc
                    JOIN civilization_eras e ON uc.current_era_id = e.id
                    WHERE uc.user_id = ?
                ");
                $stmt->execute([$me['id']]);
                $myEraData = $stmt->fetch(PDO::FETCH_ASSOC);
                $myValue = $myEraData ? (int)$myEraData['era_order'] : 0;
                
                // 自分の順位を取得（同じ時代の場合は人口で比較）
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) + 1 as rank_position
                    FROM user_civilizations uc
                    JOIN civilization_eras e ON uc.current_era_id = e.id
                    WHERE e.era_order > ? 
                       OR (e.era_order = ? AND uc.population > (SELECT population FROM user_civilizations WHERE user_id = ?))
                ");
                $stmt->execute([$myValue, $myValue, $me['id']]);
                $myRank = (int)$stmt->fetchColumn();
                break;
                
            default:
                // 資源別ランキング（resource_food, resource_wood, etc.）
                if (strpos($rankingType, 'resource_') === 0) {
                    $resourceKey = substr($rankingType, 9); // "resource_" を除去
                    
                    // resource_keyからresource_type_idを取得
                    $stmt = $pdo->prepare("SELECT id, name, icon FROM civilization_resource_types WHERE resource_key = ?");
                    $stmt->execute([$resourceKey]);
                    $resourceType = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($resourceType) {
                        $stmt = $pdo->prepare("
                            SELECT ucr.user_id, uc.civilization_name, ucr.amount as value, u.handle
                            FROM user_civilization_resources ucr
                            JOIN user_civilizations uc ON ucr.user_id = uc.user_id
                            JOIN users u ON ucr.user_id = u.id
                            WHERE ucr.resource_type_id = ?
                            ORDER BY ucr.amount DESC
                            LIMIT {$limit}
                        ");
                        $stmt->execute([$resourceType['id']]);
                        $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $stmt = $pdo->prepare("
                            SELECT COALESCE(amount, 0) as my_total 
                            FROM user_civilization_resources 
                            WHERE user_id = ? AND resource_type_id = ?
                        ");
                        $stmt->execute([$me['id'], $resourceType['id']]);
                        $myValue = (float)$stmt->fetchColumn();
                        
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) + 1 as rank_position
                            FROM user_civilization_resources
                            WHERE resource_type_id = ? AND amount > ?
                        ");
                        $stmt->execute([$resourceType['id'], $myValue]);
                        $myRank = (int)$stmt->fetchColumn();
                    }
                }
                break;
        }
        
        // ランキング情報を整形
        $formattedRankings = [];
        $rank = 1;
        foreach ($rankings as $row) {
            $formattedRankings[] = [
                'rank' => $rank,
                'user_id' => (int)$row['user_id'],
                'civilization_name' => $row['civilization_name'],
                'handle' => $row['handle'],
                'value' => is_numeric($row['value']) ? (strpos($row['value'], '.') !== false ? round((float)$row['value'], 0) : (int)$row['value']) : 0,
                'is_me' => (int)$row['user_id'] === $me['id']
            ];
            $rank++;
        }
        
        // 利用可能な資源タイプを取得
        $stmt = $pdo->query("SELECT resource_key, name, icon FROM civilization_resource_types ORDER BY unlock_order ASC");
        $resourceTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'ranking_type' => $rankingType,
            'rankings' => $formattedRankings,
            'my_rank' => $myRank,
            'my_value' => $myValue !== null ? (is_float($myValue) ? round($myValue, 0) : (int)$myValue) : 0,
            'resource_types' => $resourceTypes
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// バトル用ヒーロー選択API
// ===============================================
if ($action === 'set_battle_hero') {
    $battleType = $input['battle_type'] ?? '';
    $heroId = isset($input['hero_id']) ? (int)$input['hero_id'] : null;
    $skillType1 = (int)($input['skill_1_type'] ?? 1);
    $skillType2 = isset($input['skill_2_type']) ? (int)$input['skill_2_type'] : null;
    
    $validBattleTypes = ['conquest', 'world_boss', 'wandering_monster', 'war', 'defense'];
    if (!in_array($battleType, $validBattleTypes)) {
        echo json_encode(['ok' => false, 'error' => '無効なバトルタイプです']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // ヒーローが指定されている場合は所有確認
        if ($heroId) {
            $stmt = $pdo->prepare("
                SELECT uh.*, h.name, h.icon, h.battle_skill_name, h.battle_skill_2_name
                FROM user_heroes uh
                JOIN heroes h ON uh.hero_id = h.id
                WHERE uh.user_id = ? AND uh.hero_id = ? AND uh.star_level > 0
            ");
            $stmt->execute([$me['id'], $heroId]);
            $hero = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$hero) {
                throw new Exception('このヒーローを所有していないか、アンロックされていません');
            }
            
            // スキルタイプの検証
            if (!in_array($skillType1, [1, 2])) {
                $skillType1 = 1;
            }
            if ($skillType2 !== null && !in_array($skillType2, [1, 2])) {
                $skillType2 = null;
            }
            // 同じスキルを2回選択することはできない
            if ($skillType2 === $skillType1) {
                $skillType2 = null;
            }
        }
        
        // 選択を保存
        if ($heroId) {
            $stmt = $pdo->prepare("
                INSERT INTO user_battle_hero_selection (user_id, battle_type, hero_id, skill_1_type, skill_2_type)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    hero_id = VALUES(hero_id),
                    skill_1_type = VALUES(skill_1_type),
                    skill_2_type = VALUES(skill_2_type),
                    updated_at = NOW()
            ");
            $stmt->execute([$me['id'], $battleType, $heroId, $skillType1, $skillType2]);
            
            $message = "{$hero['name']}をバトル用ヒーローに設定しました";
        } else {
            // ヒーロー選択を解除
            $stmt = $pdo->prepare("DELETE FROM user_battle_hero_selection WHERE user_id = ? AND battle_type = ?");
            $stmt->execute([$me['id'], $battleType]);
            
            $message = 'バトル用ヒーローの選択を解除しました';
        }
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true,
            'message' => $message
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// バトル用ヒーロー選択情報を取得
// ===============================================
if ($action === 'get_battle_hero_selection') {
    $battleType = $input['battle_type'] ?? '';
    
    $validBattleTypes = ['conquest', 'world_boss', 'wandering_monster', 'war', 'defense'];
    if (!in_array($battleType, $validBattleTypes)) {
        echo json_encode(['ok' => false, 'error' => '無効なバトルタイプです']);
        exit;
    }
    
    try {
        // 現在の選択を取得
        $stmt = $pdo->prepare("
            SELECT ubhs.*, h.name as hero_name, h.icon as hero_icon,
                   h.battle_skill_name, h.battle_skill_desc,
                   h.battle_skill_2_name, h.battle_skill_2_desc,
                   uh.star_level
            FROM user_battle_hero_selection ubhs
            JOIN heroes h ON ubhs.hero_id = h.id
            LEFT JOIN user_heroes uh ON ubhs.user_id = uh.user_id AND ubhs.hero_id = uh.hero_id
            WHERE ubhs.user_id = ? AND ubhs.battle_type = ?
        ");
        $stmt->execute([$me['id'], $battleType]);
        $selection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 利用可能なヒーロー一覧を取得
        $stmt = $pdo->prepare("
            SELECT uh.*, h.name, h.icon, h.title,
                   h.battle_skill_name, h.battle_skill_desc,
                   h.battle_skill_2_name, h.battle_skill_2_desc,
                   h.passive_skill_name, h.passive_skill_desc
            FROM user_heroes uh
            JOIN heroes h ON uh.hero_id = h.id
            WHERE uh.user_id = ? AND uh.star_level > 0
            ORDER BY uh.star_level DESC, h.name ASC
        ");
        $stmt->execute([$me['id']]);
        $availableHeroes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'battle_type' => $battleType,
            'current_selection' => $selection,
            'available_heroes' => $availableHeroes
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===============================================
// 全バトルタイプのヒーロー編成情報を取得
// ===============================================
if ($action === 'get_all_hero_assignments') {
    try {
        $validBattleTypes = ['conquest', 'world_boss', 'wandering_monster', 'war', 'defense'];
        $battleTypeLabels = [
            'conquest' => ['name' => '占領戦', 'icon' => '🏰'],
            'world_boss' => ['name' => 'ワールドボス', 'icon' => '🐲'],
            'wandering_monster' => ['name' => '放浪モンスター', 'icon' => '👹'],
            'war' => ['name' => '戦争', 'icon' => '⚔️'],
            'defense' => ['name' => '防衛', 'icon' => '🛡️']
        ];
        
        // 全バトルタイプの選択を取得
        $stmt = $pdo->prepare("
            SELECT ubhs.*, h.name as hero_name, h.icon as hero_icon, h.title as hero_title,
                   h.rarity, h.battle_skill_name, h.battle_skill_desc,
                   h.battle_skill_2_name, h.battle_skill_2_desc,
                   h.battle_skill_effect, h.battle_skill_2_effect,
                   h.passive_skill_name, h.passive_skill_desc,
                   uh.star_level
            FROM user_battle_hero_selection ubhs
            JOIN heroes h ON ubhs.hero_id = h.id
            LEFT JOIN user_heroes uh ON ubhs.user_id = uh.user_id AND ubhs.hero_id = uh.hero_id
            WHERE ubhs.user_id = ?
        ");
        $stmt->execute([$me['id']]);
        $selections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // バトルタイプでインデックス化
        $assignmentsByType = [];
        foreach ($validBattleTypes as $type) {
            $assignmentsByType[$type] = null;
        }
        foreach ($selections as $selection) {
            $assignmentsByType[$selection['battle_type']] = $selection;
        }
        
        // 利用可能なヒーロー一覧を取得（アンロック済み）
        $stmt = $pdo->prepare("
            SELECT uh.hero_id, uh.star_level, uh.shards,
                   h.name, h.icon, h.title, h.rarity,
                   h.battle_skill_name, h.battle_skill_desc,
                   h.battle_skill_2_name, h.battle_skill_2_desc,
                   h.battle_skill_effect, h.battle_skill_2_effect,
                   h.passive_skill_name, h.passive_skill_desc
            FROM user_heroes uh
            JOIN heroes h ON uh.hero_id = h.id
            WHERE uh.user_id = ? AND uh.star_level > 0
            ORDER BY uh.star_level DESC, h.rarity DESC, h.name ASC
        ");
        $stmt->execute([$me['id']]);
        $availableHeroes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'battle_types' => $battleTypeLabels,
            'assignments' => $assignmentsByType,
            'available_heroes' => $availableHeroes,
            'hero_count' => count($availableHeroes)
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'invalid_action']);
