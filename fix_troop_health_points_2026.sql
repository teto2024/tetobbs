-- ===============================================
-- 兵種体力値の調整 2026
-- ID 119-164の兵種の体力値を特徴に応じて適切に設定
-- 環境キラーにならない程度にバランス調整
-- ===============================================

USE microblog;

-- ===============================================
-- 量子革命時代・現代時代の兵種（ID 119-128）
-- ===============================================

-- ID 119: 量子統兵 - 反撃スキル持ち歩兵、バランス型
-- 攻撃250、防御180 → 体力450（歩兵としてやや高め）
UPDATE civilization_troop_types SET health_points = 450 WHERE id = 119;

-- ID 120: 量子戦艦 - 回避スキル持ち海上騎兵、高防御
-- 攻撃280、防御200 → 体力600（戦艦として適度）
UPDATE civilization_troop_types SET health_points = 600 WHERE id = 120;

-- ID 121: 生成軍隊 - 鼓舞スキル持ち歩兵、バランス型
-- 攻撃300、防御220 → 体力500（強化系としてバランス）
UPDATE civilization_troop_types SET health_points = 500 WHERE id = 121;

-- ID 122: 宇宙戦車 - 出血スキル持ち騎兵、高攻撃高防御
-- 攻撃350、防御250 → 体力700（戦車として高耐久）
UPDATE civilization_troop_types SET health_points = 700 WHERE id = 122;

-- ID 123: 海軍戦闘機 - 爆弾投下スキル、空の攻城兵器、低防御
-- 攻撃190、防御65 → 体力170（軽量戦闘機）
UPDATE civilization_troop_types SET health_points = 170 WHERE id = 123;

-- ID 124: 艦上レーザー兵器 - レーザー照射スキル、海の攻城兵器
-- 攻撃185、防御100 → 体力280（艦船として中程度）
UPDATE civilization_troop_types SET health_points = 280 WHERE id = 124;

-- ID 125: 対戦キャノン砲 - 散弾発射スキル、陸の攻城兵器
-- 攻撃175、防御70 → 体力190（砲兵として脆弱）
UPDATE civilization_troop_types SET health_points = 190 WHERE id = 125;

-- ID 126: 投石兵 - 投石スキル、初期時代の攻城兵器
-- 攻撃65、防御40 → 体力100（初期ユニットとして低体力）
UPDATE civilization_troop_types SET health_points = 100 WHERE id = 126;

-- ID 127: 巡航ミサイル - 自律飛行スキル、使い捨て攻城兵器
-- 攻撃280、防御60 → 体力120（使い捨てなので低体力）
UPDATE civilization_troop_types SET health_points = 120 WHERE id = 127;

-- ID 128: 防護兵 - 核武装解除スキル、対核専門歩兵
-- 攻撃170、防御130 → 体力350（防護装備で高耐久）
UPDATE civilization_troop_types SET health_points = 350 WHERE id = 128;

-- ===============================================
-- 現代Ⅵ〜近未来時代の兵種（ID 129-164）
-- ===============================================

-- ID 129: サイバーウォリアー - 高攻撃高防御の歩兵
-- 攻撃600、防御500 → 体力800（強化兵士として高耐久）
UPDATE civilization_troop_types SET health_points = 800 WHERE id = 129;

-- ID 130: ダークマタータンク - 超高攻撃超高防御の攻城兵器
-- 攻撃900、防御800 → 体力1200（重装甲タンク）
UPDATE civilization_troop_types SET health_points = 1200 WHERE id = 130;

-- ID 131: エネルギードローン - 高攻撃中防御の遠距離兵器
-- 攻撃700、防御400 → 体力600（ドローンとして中程度）
UPDATE civilization_troop_types SET health_points = 600 WHERE id = 131;

-- ID 132: 量子兵士 - 高攻撃高防御の歩兵
-- 攻撃750、防御600 → 体力900（量子強化で高耐久）
UPDATE civilization_troop_types SET health_points = 900 WHERE id = 132;

-- ID 133: エコガーディアン - バランス型の環境保護歩兵
-- 攻撃800、防御700 → 体力1000（防衛型として高HP）
UPDATE civilization_troop_types SET health_points = 1000 WHERE id = 133;

-- ID 134: ポータルナイト - 高攻撃中防御の騎兵
-- 攻撃950、防御650 → 体力850（機動力重視で中HP）
UPDATE civilization_troop_types SET health_points = 850 WHERE id = 134;

-- ID 135: テックメック - 超高攻撃超高防御の攻城兵器
-- 攻撃1100、防御900 → 体力1500（巨大メックとして最高級）
UPDATE civilization_troop_types SET health_points = 1500 WHERE id = 135;

-- ID 136: グローバルディフェンダー - 防御特化の歩兵
-- 攻撃700、防御1000 → 体力1100（防衛専門で高HP）
UPDATE civilization_troop_types SET health_points = 1100 WHERE id = 136;

-- ID 137: 反物質兵 - 超高攻撃の歩兵
-- 攻撃1200、防御800 → 体力1000（攻撃特化でバランス）
UPDATE civilization_troop_types SET health_points = 1000 WHERE id = 137;

-- ID 138: 合成戦士 - 高防御の歩兵
-- 攻撃1000、防御1100 → 体力1200（防御重視で高HP）
UPDATE civilization_troop_types SET health_points = 1200 WHERE id = 138;

-- ID 139: エリートスペースマリーン - バランス型エリート歩兵
-- 攻撃1300、防御1000 → 体力1100（エリート部隊として高耐久）
UPDATE civilization_troop_types SET health_points = 1100 WHERE id = 139;

-- ID 140: メガメック - 最高攻撃防御の攻城兵器
-- 攻撃1500、防御1200 → 体力1800（超巨大メック）
UPDATE civilization_troop_types SET health_points = 1800 WHERE id = 140;

-- ID 141: ジェネレーション兵 - 高性能歩兵
-- 攻撃1400、防御1100 → 体力1300（生成技術で高性能）
UPDATE civilization_troop_types SET health_points = 1300 WHERE id = 141;

-- ID 142: 遺伝子戦士 - 超高攻撃の歩兵
-- 攻撃1600、防御1000 → 体力1200（遺伝子改造で強力）
UPDATE civilization_troop_types SET health_points = 1200 WHERE id = 142;

-- ID 143: コロニーガード - 超高防御の歩兵
-- 攻撃1200、防御1500 → 体力1400（防衛専門で高HP）
UPDATE civilization_troop_types SET health_points = 1400 WHERE id = 143;

-- ID 144: 軌道爆撃機 - 超高攻撃低防御の遠距離兵器
-- 攻撃2000、防御600 → 体力800（攻撃特化で脆い）
UPDATE civilization_troop_types SET health_points = 800 WHERE id = 144;

-- ID 145: ムーブメントアサシン - 高攻撃低防御の騎兵
-- 攻撃1800、防御900 → 体力1000（暗殺者として低HP）
UPDATE civilization_troop_types SET health_points = 1000 WHERE id = 145;

-- ID 146: スタートルーパー - 高攻撃高防御の歩兵
-- 攻撃2000、防御1400 → 体力1600（恒星間部隊として強力）
UPDATE civilization_troop_types SET health_points = 1600 WHERE id = 146;

-- ID 147: ユニバースソルジャー - 超高性能歩兵
-- 攻撃2500、防御1800 → 体力2000（宇宙規模の戦士）
UPDATE civilization_troop_types SET health_points = 2000 WHERE id = 147;

-- ID 148: プラネットクラッシャー - 最高攻撃の攻城兵器
-- 攻撃3500、防御1500 → 体力2200（惑星破壊兵器として高HP）
UPDATE civilization_troop_types SET health_points = 2200 WHERE id = 148;

-- ID 149: 国連平和維持軍 - 超高バランス型歩兵
-- 攻撃2200、防御2200 → 体力2000（精鋭部隊として高HP）
UPDATE civilization_troop_types SET health_points = 2000 WHERE id = 149;

-- ID 150: キャッシュハッカー - 高攻撃高防御の遠距離兵器
-- 攻撃2000、防御1500 → 体力1400（サイバー戦特化）
UPDATE civilization_troop_types SET health_points = 1400 WHERE id = 150;

-- ID 151: コズミックナイト - 超高攻撃超高防御の騎兵
-- 攻撃3000、防御2500 → 体力2400（宇宙騎士として最高級）
UPDATE civilization_troop_types SET health_points = 2400 WHERE id = 151;

-- ID 152: エネルギータイタン - 最高攻撃の攻城兵器
-- 攻撃4000、防御2000 → 体力2600（巨人兵器として高HP）
UPDATE civilization_troop_types SET health_points = 2600 WHERE id = 152;

-- ID 153: 量子コマンダー - 超高性能歩兵指揮官
-- 攻撃3500、防御2500 → 体力2300（指揮官として高耐久）
UPDATE civilization_troop_types SET health_points = 2300 WHERE id = 153;

-- ID 154: 惑星ガーディアン - 超高防御の歩兵
-- 攻撃3000、防御3500 → 体力2800（守護者として最高HP）
UPDATE civilization_troop_types SET health_points = 2800 WHERE id = 154;

-- ID 155: 変換術士 - 超高攻撃低防御の遠距離兵器
-- 攻撃4500、防御2000 → 体力1800（魔術師として脆い）
UPDATE civilization_troop_types SET health_points = 1800 WHERE id = 155;

-- ID 156: コンテナソルジャー - 高性能歩兵
-- 攻撃4000、防御3000 → 体力2600（特殊装備で高耐久）
UPDATE civilization_troop_types SET health_points = 2600 WHERE id = 156;

-- ID 157: コズミック考古学者 - 高攻撃高防御の遠距離兵器
-- 攻撃3800、防御2800 → 体力2400（研究者として中HP）
UPDATE civilization_troop_types SET health_points = 2400 WHERE id = 157;

-- ID 158: 光速戦闘機 - 超高攻撃の遠距離兵器
-- 攻撃5500、防御2500 → 体力2200（光速機として中HP）
UPDATE civilization_troop_types SET health_points = 2200 WHERE id = 158;

-- ID 159: AIレギオン - 超高性能歩兵
-- 攻撃5000、防御4000 → 体力3200（AI軍団として高HP）
UPDATE civilization_troop_types SET health_points = 3200 WHERE id = 159;

-- ID 160: 宇宙オペレーター - 高性能遠距離兵器
-- 攻撃4500、防御3500 → 体力2800（技術兵として高HP）
UPDATE civilization_troop_types SET health_points = 2800 WHERE id = 160;

-- ID 161: 銀河タイタン - 最高級攻城兵器
-- 攻撃8000、防御5000 → 体力4000（銀河規模の兵器）
UPDATE civilization_troop_types SET health_points = 4000 WHERE id = 161;

-- ID 162: 連邦エリート - 超高性能歩兵
-- 攻撃7000、防御6000 → 体力4500（連邦精鋭として最高級）
UPDATE civilization_troop_types SET health_points = 4500 WHERE id = 162;

-- ID 163: ハーモニーガーディアン - 超高防御歩兵
-- 攻撃6000、防御8000 → 体力5000（守護者として最高HP）
UPDATE civilization_troop_types SET health_points = 5000 WHERE id = 163;

-- ID 164: ユニバーサルデストロイヤー - 究極の攻城兵器
-- 攻撃12000、防御6000 → 体力4000（破壊兵器として攻撃特化）
UPDATE civilization_troop_types SET health_points = 4000 WHERE id = 164;

-- ===============================================
-- 完了メッセージ
-- ===============================================
SELECT '兵種体力値の調整が完了しました' AS status;
SELECT CONCAT('Updated ', COUNT(*), ' troop types') AS troops_updated 
FROM civilization_troop_types 
WHERE id BETWEEN 119 AND 164;
