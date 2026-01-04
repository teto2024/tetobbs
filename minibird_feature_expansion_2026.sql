-- ===============================================
-- MiniBird 機能拡張スキーマ 2026
-- 新時代、新資源、新建物、新兵種、新研究、クエスト追加
-- 保管庫・シェルター機能追加
-- ===============================================

USE syugetsu2025_clone;

-- ===============================================
-- ④ 新時代を追加（現代Ⅵ → 銀河時代Ⅱ）
-- 注意: 既存の宇宙時代(era_order=14)の後に追加
-- ===============================================
INSERT IGNORE INTO civilization_eras (era_key, name, icon, description, era_order, unlock_population, unlock_research_points, color) VALUES
('modern_6', '現代Ⅵ', '🌐', '情報技術の極限。AIと量子技術が融合する。', 15, 1000000, 2000000, '#00BFFF'),
('earth_revolution', '地球大革命時代', '🌍', '地球規模の変革。環境と技術の調和。', 16, 2000000, 3000000, '#228B22'),
('near_future', '近未来時代', '🔮', '未来技術の夜明け。人類の新たな一歩。', 17, 3000000, 4000000, '#9370DB'),
('near_future_2', '近未来時代Ⅱ', '🚀', 'スペースコロニーの実現。宇宙への進出が本格化。', 18, 4000000, 6000000, '#4682B4'),
('near_future_3', '近未来時代Ⅲ', '🛸', '恒星間航行の可能性。未知の領域へ。', 19, 7000000, 10000000, '#8A2BE2'),
('planet_revolution', '惑星革命時代', '🪐', '惑星規模の文明。複数の惑星に跨る帝国。', 20, 10000000, 15000000, '#FF6347'),
('near_future_4', '近未来時代Ⅳ', '⚡', 'エネルギー革命。無限のエネルギーを手に入れる。', 21, 15000000, 25000000, '#FFD700'),
('near_future_5', '近未来時代Ⅴ', '🧪', '物質変換技術。錬金術の夢が現実に。', 22, 20000000, 30000000, '#00FA9A'),
('spaceship_revolution', '宇宙船革命時代', '🚀', '次世代宇宙船技術。光速に近づく。', 23, 40000000, 50000000, '#FF1493'),
('galactic', '銀河時代', '🌌', '銀河系文明への進化。星々を支配する。', 24, 70000000, 100000000, '#191970'),
('galactic_2', '銀河時代Ⅱ', '✨', '銀河連邦の確立。宇宙の覇者として君臨。', 25, 100000000, 200000000, '#FFD700');

-- ===============================================
-- ⑤ 新資源を追加（市場取引不可）
-- is_tradable カラムを追加（なければ追加）
-- ===============================================

-- is_tradable カラムを追加（存在しなければ）
ALTER TABLE civilization_resource_types
ADD COLUMN IF NOT EXISTS is_tradable BOOLEAN NOT NULL DEFAULT TRUE COMMENT '市場取引可能かどうか';

-- 新資源を追加（市場取引不可）
INSERT IGNORE INTO civilization_resource_types (resource_key, name, icon, description, unlock_order, color, is_tradable) VALUES
-- 現代Ⅵ
('dark_matter', 'ダークマター', '🌑', '宇宙の謎の物質。高度な技術に必要。', 20, '#1C1C1C', FALSE),
('energy_charger', 'エネルギーチャージャー', '🔋', '高密度エネルギー貯蔵装置。', 20, '#FFFF00', FALSE),
-- 地球大革命時代
('tech_core', 'テックコア', '💾', '高度技術の結晶。', 21, '#00CED1', FALSE),
('portal_token', 'ポータルトークン', '🌀', '次元転移に必要なトークン。', 21, '#9400D3', FALSE),
-- 近未来時代
('antimatter_particle', '反物質粒子', '⚛️', '反物質から生成される粒子。', 22, '#FF00FF', FALSE),
('synthetic_particle', '合成素粒子', '🔬', '人工的に合成された素粒子。', 22, '#00FF7F', FALSE),
-- 近未来時代Ⅱ
('generation_unit', '生成単位', '📊', '物質生成の基本単位。', 23, '#4169E1', FALSE),
('generation_gene', '生成遺伝子', '🧬', '遺伝子操作の結果。', 23, '#32CD32', FALSE),
-- 近未来時代Ⅲ
('movement_core', 'ムーブメントコア', '🎯', '高速移動の核心技術。', 24, '#FF4500', FALSE),
('generation_quantum', '生成量子', '💠', '量子レベルで生成された物質。', 24, '#00BFFF', FALSE),
-- 惑星革命時代
('universe_tech', 'ユニバーステック', '🌟', '宇宙規模の技術結晶。', 25, '#FFD700', FALSE),
('scrap_charge', 'スクラップチャージ', '♻️', 'リサイクルエネルギー。', 25, '#8B4513', FALSE),
-- 近未来時代Ⅳ
('cache_cluster', 'キャッシュクラスタ', '💽', 'データ処理の集積体。', 26, '#C0C0C0', FALSE),
('cosmic_shard', '宇宙シャード', '💎', '宇宙の欠片。', 26, '#E0FFFF', FALSE),
-- 近未来時代Ⅴ
('quantum_module', '量子モジュール', '🔷', '量子技術モジュール。', 27, '#1E90FF', FALSE),
('planet_memory', '惑星メモリ', '🗄️', '惑星規模のデータ記憶装置。', 27, '#696969', FALSE),
-- 宇宙船革命時代
('container_unlock_key', 'コンテナアンロックキー', '🔑', '特殊コンテナを開ける鍵。', 28, '#FFD700', FALSE),
('cosmic_fossil', '宇宙化石', '🦴', '宇宙生命の化石。', 28, '#D2691E', FALSE),
-- 銀河時代
('ai_crate', 'AIクレート', '📦', 'AI技術が詰まった箱。', 29, '#4682B4', FALSE),
('cosmic_console', '宇宙操作盤', '🎛️', '宇宙船の操作盤。', 29, '#708090', FALSE);

-- ===============================================
-- ⑥ 新建物を追加
-- ===============================================

-- 13/14. 保管庫とシェルターの追加（産業革命時代）
-- resource_protection_ratio: 人口の何倍の資源を守れるか
-- troop_protection_ratio: 軍事力の何分の1の兵士を守れるか（1/1000 = 0.001）
ALTER TABLE civilization_building_types
ADD COLUMN IF NOT EXISTS resource_protection_ratio DECIMAL(10,2) NULL COMMENT '資源保護倍率（人口×この値の資源を守る）',
ADD COLUMN IF NOT EXISTS troop_protection_ratio DECIMAL(10,6) NULL COMMENT '兵士保護倍率（軍事力×この値の兵士を守る）';

-- 保管庫（Vault）- 産業革命時代
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power, resource_protection_ratio, troop_protection_ratio) VALUES
('vault', '保管庫', '🏦', '資源を略奪から守る堅牢な保管施設。人口×20の資源を保護。', 'special', NULL, 0, 10, 6, 15000, '{"iron": 500, "stone": 800}', 7200, 0, 0, 20.0, NULL);

-- シェルター（Shelter）- 産業革命時代
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power, resource_protection_ratio, troop_protection_ratio) VALUES
('shelter', 'シェルター', '🛡️', '兵士を攻撃から守る防空施設。軍事力の1/1000の兵士を保護。', 'military', NULL, 0, 10, 6, 20000, '{"iron": 600, "stone": 400, "oil": 100}', 9000, 0, 100, NULL, 0.001);

-- ③ 宇宙時代以前の時代に人口を増やす建物を追加
-- 原子力時代（era 8）に追加の住居
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('atomic_apartment', '原子力集合住宅', '🏢', '原子力発電で快適な大規模集合住宅', 'housing', NULL, 0, 10, 8, 85000, '{"iron": 1200, "stone": 1500, "uranium": 10}', 39600, 250, 0),
('civil_defense_shelter', '民間防衛シェルター', '🏘️', '核攻撃に備えた地下住居', 'housing', NULL, 0, 10, 8, 70000, '{"stone": 2000, "iron": 800}', 32400, 180, 0);

-- 現代Ⅱ（era 9）に追加の住居
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('digital_residence', 'デジタルレジデンス', '🏠', 'インターネット完備の近代住宅', 'housing', NULL, 0, 10, 9, 190000, '{"iron": 2000, "silicon": 300, "glass": 500}', 57600, 450, 0),
('net_community', 'ネットコミュニティ', '🌐', 'オンライン機能統合の住宅地', 'housing', NULL, 0, 10, 9, 160000, '{"iron": 1500, "silicon": 200, "stone": 1000}', 50400, 350, 0);

-- 現代Ⅲ（era 10）に追加の住居
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('smart_apartment', 'スマートアパートメント', '📱', 'IoT完備のスマート住宅', 'housing', NULL, 0, 10, 10, 320000, '{"iron": 2800, "silicon": 800, "rare_earth": 100}', 68400, 650, 0),
('connected_tower', 'コネクテッドタワー', '🗼', 'SNS統合の超高層住宅', 'housing', NULL, 0, 10, 10, 380000, '{"iron": 3500, "silicon": 1000, "glass": 1200}', 79200, 750, 0);

-- 量子革命時代（era 11）に追加の住居
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('quantum_residence', '量子レジデンス', '⚛️', '量子技術で守られた住居', 'housing', NULL, 0, 10, 11, 520000, '{"iron": 4000, "quantum_crystal": 80, "silicon": 1500}', 93600, 900, 0),
('quantum_habitat', '量子ハビタット', '🔮', '量子空間を利用した大容量住居', 'housing', NULL, 0, 10, 11, 600000, '{"iron": 4500, "quantum_crystal": 120, "rare_earth": 300}', 108000, 1100, 0);

-- 現代Ⅳ（era 12）に追加の住居
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('ai_managed_complex', 'AI管理コンプレックス', '🤖', 'AI完全管理の住宅複合施設', 'housing', NULL, 0, 10, 12, 850000, '{"iron": 6000, "ai_core": 80, "silicon": 3000}', 144000, 1600, 0),
('automated_city_block', '自動化シティブロック', '🏙️', 'ロボットが管理する住宅街', 'housing', NULL, 0, 10, 12, 950000, '{"iron": 7000, "ai_core": 100, "silicon": 4000}', 158400, 1900, 0);

-- 現代Ⅴ（era 13）に追加の住居
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('bio_habitat', 'バイオハビタット', '🧬', '遺伝子技術で最適化された住居', 'housing', NULL, 0, 10, 13, 1100000, '{"iron": 5000, "gene_sample": 100, "glass": 3000}', 165600, 2500, 0),
('genetic_paradise', '遺伝子パラダイス', '🌺', 'バイオ技術による理想の住環境', 'housing', NULL, 0, 10, 13, 1250000, '{"iron": 6500, "gene_sample": 150, "ai_core": 50}', 180000, 2900, 0);

-- 現代Ⅵの建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('dark_matter_collector', 'ダークマター収集機', '🌑', 'ダークマターを収集する', 'production', NULL, 0, 10, 15, 3000000, '{"iron": 20000, "quantum_crystal": 500}', 172800, 0, 0),
('energy_hub', 'エネルギーハブ', '🔋', 'エネルギーチャージャーを生産', 'production', NULL, 0, 10, 15, 2500000, '{"silicon": 10000, "rare_earth": 2000}', 144000, 0, 0),
('mega_tower', '超高層タワー', '🏙️', '巨大な居住施設', 'housing', NULL, 0, 10, 15, 5000000, '{"iron": 30000, "silicon": 5000}', 259200, 10000, 0);

-- 地球大革命時代の建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('tech_core_factory', 'テックコア工場', '💾', 'テックコアを生産する', 'production', NULL, 0, 10, 16, 4000000, '{"silicon": 15000, "ai_core": 200}', 201600, 0, 0),
('portal_gate', 'ポータルゲート', '🌀', 'ポータルトークンを生産', 'production', NULL, 0, 5, 16, 6000000, '{"quantum_crystal": 1000, "dark_matter": 100}', 288000, 0, 500),
('eco_dome', 'エコドーム', '🌿', '環境に優しい大規模住居', 'housing', NULL, 0, 10, 16, 5500000, '{"iron": 25000, "gene_sample": 500}', 230400, 15000, 0),
('global_defense_center', '地球防衛センター', '🛡️', '地球規模の防衛施設', 'military', NULL, 0, 5, 16, 8000000, '{"iron": 40000, "ai_core": 500}', 345600, 0, 15000);

-- 近未来時代の建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('antimatter_generator', '反物質生成装置', '⚛️', '反物質粒子を生成', 'production', NULL, 0, 10, 17, 7000000, '{"antimatter": 50, "quantum_crystal": 800}', 259200, 0, 0),
('synthetic_lab', '合成素粒子研究所', '🔬', '合成素粒子を生産', 'research', NULL, 0, 10, 17, 6500000, '{"silicon": 20000, "knowledge": 5000}', 230400, 0, 0),
('space_habitat', '宇宙居住区', '🏠', '宇宙空間の居住施設', 'housing', NULL, 0, 10, 17, 8000000, '{"iron": 35000, "dark_matter": 200}', 302400, 20000, 0),
('mech_factory', 'メック工場', '🤖', '戦闘メックを生産', 'military', NULL, 0, 10, 17, 10000000, '{"iron": 50000, "ai_core": 800}', 374400, 0, 20000);

-- 近未来時代Ⅱの建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('generation_plant', '生成単位プラント', '📊', '生成単位を生産', 'production', NULL, 0, 10, 18, 9000000, '{"tech_core": 200, "silicon": 25000}', 288000, 0, 0),
('gene_lab', '遺伝子研究所', '🧬', '生成遺伝子を生産', 'research', NULL, 0, 10, 18, 8500000, '{"gene_sample": 1000, "knowledge": 8000}', 259200, 0, 0),
('colony_ship_dock', 'コロニー船ドック', '🚀', 'コロニー船を建造', 'special', NULL, 0, 5, 18, 15000000, '{"iron": 80000, "antimatter": 100}', 432000, 0, 5000),
('orbital_fortress', '軌道要塞', '🏰', '軌道上の軍事要塞', 'military', NULL, 0, 5, 18, 12000000, '{"iron": 60000, "dark_matter": 500}', 388800, 0, 30000);

-- 近未来時代Ⅲの建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('movement_lab', 'ムーブメント研究所', '🎯', 'ムーブメントコアを研究生産', 'research', NULL, 0, 10, 19, 12000000, '{"quantum_crystal": 2000, "tech_core": 500}', 345600, 0, 0),
('quantum_forge', '量子フォージ', '💠', '生成量子を生産', 'production', NULL, 0, 10, 19, 14000000, '{"quantum_crystal": 3000, "antimatter_particle": 200}', 388800, 0, 0),
('star_base', 'スターベース', '⭐', '恒星系の拠点', 'special', NULL, 0, 5, 19, 20000000, '{"iron": 100000, "dark_matter": 1000}', 518400, 30000, 10000),
('interstellar_academy', '恒星間アカデミー', '🎓', '高度な軍事訓練施設', 'military', NULL, 0, 10, 19, 15000000, '{"knowledge": 15000, "ai_core": 1000}', 432000, 0, 40000);

-- 惑星革命時代の建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('universe_tech_lab', 'ユニバーステック研究所', '🌟', 'ユニバーステックを生産', 'research', NULL, 0, 10, 20, 18000000, '{"generation_quantum": 500, "tech_core": 1000}', 432000, 0, 0),
('scrap_recycler', 'スクラップリサイクラー', '♻️', 'スクラップチャージを生産', 'production', NULL, 0, 10, 20, 12000000, '{"iron": 50000}', 302400, 0, 0),
('planetary_city', '惑星都市', '🌍', '惑星規模の都市', 'housing', NULL, 0, 5, 20, 25000000, '{"iron": 150000, "generation_unit": 1000}', 604800, 100000, 0),
('un_facility', '国連施設', '🏛️', '大使館の上位互換。同盟支援上限大幅アップ。', 'special', NULL, 0, 5, 20, 20000000, '{"gold": 10000, "knowledge": 20000}', 518400, 0, 0);

-- 近未来時代Ⅳの建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('cache_server', 'キャッシュサーバー', '💽', 'キャッシュクラスタを生産', 'production', NULL, 0, 10, 21, 20000000, '{"silicon": 50000, "ai_core": 2000}', 432000, 0, 0),
('cosmic_harvester', '宇宙シャード収集機', '💎', '宇宙シャードを収集', 'production', NULL, 0, 10, 21, 22000000, '{"dark_matter": 2000, "iron": 80000}', 475200, 0, 0),
('energy_citadel', 'エネルギー城塞', '⚡', 'エネルギー技術の軍事拠点', 'military', NULL, 0, 5, 21, 30000000, '{"energy_charger": 1000, "iron": 100000}', 604800, 0, 60000);

-- 近未来時代Ⅴの建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('quantum_factory', '量子モジュール工場', '🔷', '量子モジュールを生産', 'production', NULL, 0, 10, 22, 25000000, '{"generation_quantum": 1000, "quantum_crystal": 5000}', 518400, 0, 0),
('planet_archive', '惑星アーカイブ', '🗄️', '惑星メモリを生産', 'research', NULL, 0, 10, 22, 28000000, '{"cache_cluster": 500, "knowledge": 30000}', 561600, 0, 0),
('transmutation_tower', '変換タワー', '🧪', '物質変換施設', 'special', NULL, 0, 5, 22, 35000000, '{"quantum_module": 200, "cosmic_shard": 500}', 691200, 0, 0);

-- 宇宙船革命時代の建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('container_factory', 'コンテナ工場', '🔑', 'コンテナアンロックキーを生産', 'production', NULL, 0, 10, 23, 30000000, '{"iron": 100000, "tech_core": 2000}', 604800, 0, 0),
('cosmic_museum', '宇宙博物館', '🦴', '宇宙化石を収集展示', 'special', NULL, 0, 10, 23, 35000000, '{"cosmic_shard": 1000, "planet_memory": 500}', 648000, 0, 0),
('lightspeed_dock', '光速ドック', '💫', '光速宇宙船の建造施設', 'military', NULL, 0, 5, 23, 50000000, '{"antimatter_particle": 2000, "movement_core": 1000}', 777600, 0, 100000);

-- 銀河時代の建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('ai_factory', 'AIクレート工場', '📦', 'AIクレートを生産', 'production', NULL, 0, 10, 24, 40000000, '{"ai_core": 5000, "quantum_module": 1000}', 691200, 0, 0),
('cosmic_command', '宇宙操作センター', '🎛️', '宇宙操作盤を生産', 'production', NULL, 0, 10, 24, 45000000, '{"cache_cluster": 2000, "cosmic_shard": 2000}', 734400, 0, 0),
('galactic_fortress', '銀河要塞', '🌌', '銀河規模の軍事要塞', 'military', NULL, 0, 5, 24, 80000000, '{"iron": 200000, "dark_matter": 10000}', 864000, 0, 200000),
('galactic_megacity', '銀河メガシティ', '🌃', '銀河規模の都市', 'housing', NULL, 0, 5, 24, 60000000, '{"iron": 150000, "universe_tech": 2000}', 777600, 500000, 0);

-- 銀河時代Ⅱの建物
INSERT IGNORE INTO civilization_building_types (building_key, name, icon, description, category, produces_resource_id, production_rate, max_level, unlock_era_id, base_build_cost_coins, base_build_cost_resources, base_build_time_seconds, population_capacity, military_power) VALUES
('galactic_federation_hq', '銀河連邦本部', '✨', '銀河連邦の中枢', 'special', NULL, 0, 1, 25, 100000000, '{"universe_tech": 5000, "ai_crate": 2000, "cosmic_console": 1000}', 1209600, 0, 100000),
('universal_harmony', '宇宙調和施設', '🕊️', '究極の平和施設', 'special', NULL, 0, 1, 25, 150000000, '{"universe_tech": 5000, "ai_crate": 2000, "cosmic_console": 2000}', 1814400, 1000000, 500000);

-- ===============================================
-- ⑦ 新兵種を追加（各時代3〜4個）
-- ① 各兵種にスキルを追加（発動率と効果はバランス考慮）
-- ===============================================

-- スキル追加用のカラムを追加（存在しない場合）
ALTER TABLE civilization_troop_types
ADD COLUMN IF NOT EXISTS health_points INT UNSIGNED NULL COMMENT '体力値' AFTER defense_power,
ADD COLUMN IF NOT EXISTS troop_category ENUM('infantry', 'cavalry', 'ranged', 'siege') NULL COMMENT '兵種カテゴリ' AFTER train_time_seconds,
ADD COLUMN IF NOT EXISTS special_skill_id INT UNSIGNED NULL COMMENT '特殊スキルID' AFTER troop_category;

INSERT IGNORE INTO civilization_troop_types (troop_key, name, icon, description, unlock_era_id, attack_power, defense_power, health_points, train_cost_coins, train_cost_resources, train_time_seconds, troop_category, special_skill_id) VALUES
-- 現代Ⅵ（時代15）
('cyber_warrior', 'サイバーウォリアー', '🦾', 'サイバネティクス強化された戦士', 15, 600, 500, 1200, 150000, '{"food": 500, "ai_core": 20}', 4500, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'attack_up' LIMIT 1)),
('dark_matter_tank', 'ダークマタータンク', '🌑', 'ダークマター装甲の戦車', 15, 900, 800, 1800, 250000, '{"iron": 2000, "dark_matter": 50}', 7200, 'siege', (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_harden' LIMIT 1)),
('energy_drone', 'エネルギードローン', '⚡', 'エネルギー兵器搭載ドローン', 15, 700, 400, 1000, 180000, '{"silicon": 1000, "energy_charger": 30}', 5400, 'ranged', (SELECT id FROM battle_special_skills WHERE skill_key = 'critical' LIMIT 1)),
('quantum_soldier', '量子兵士', '💠', '量子技術で強化された兵士', 15, 750, 600, 1300, 200000, '{"food": 600, "quantum_crystal": 30}', 6000, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'evasion' LIMIT 1)),

-- 地球大革命時代（時代16）
('eco_guardian', 'エコガーディアン', '🌿', '環境保護型戦闘ユニット', 16, 800, 700, 1400, 280000, '{"food": 700, "gene_sample": 50}', 7800, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'heal' LIMIT 1)),
('portal_knight', 'ポータルナイト', '🌀', 'ポータル技術を使う騎士', 16, 950, 650, 1350, 350000, '{"iron": 2500, "portal_token": 20}', 9000, 'cavalry', (SELECT id FROM battle_special_skills WHERE skill_key = 'acceleration' LIMIT 1)),
('tech_mech', 'テックメック', '🤖', '高度技術の戦闘メック', 16, 1100, 900, 2000, 400000, '{"iron": 3000, "tech_core": 40}', 10800, 'siege', (SELECT id FROM battle_special_skills WHERE skill_key = 'double_attack' LIMIT 1)),
('global_defender', 'グローバルディフェンダー', '🛡️', '地球防衛専門部隊', 16, 700, 1000, 1600, 300000, '{"food": 800, "ai_core": 30}', 8400, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_formation' LIMIT 1)),

-- 近未来時代（時代17）
('antimatter_soldier', '反物質兵', '⚛️', '反物質エネルギーを使う兵士', 17, 1200, 800, 1600, 500000, '{"food": 1000, "antimatter_particle": 30}', 12000, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'bloodlust' LIMIT 1)),
('synthetic_warrior', '合成戦士', '🔬', '合成素粒子で強化された戦士', 17, 1000, 1100, 1800, 450000, '{"food": 900, "synthetic_particle": 40}', 10800, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'counter' LIMIT 1)),
('space_marine_elite', 'エリートスペースマリーン', '🚀', '宇宙戦闘のエリート', 17, 1300, 1000, 1700, 550000, '{"food": 1200, "dark_matter": 100}', 13200, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'inspire' LIMIT 1)),
('mega_mech', 'メガメック', '⚙️', '巨大戦闘メック', 17, 1500, 1200, 2200, 650000, '{"iron": 5000, "ai_core": 100}', 15000, 'siege', (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_crush' LIMIT 1)),

-- 近未来時代Ⅱ（時代18）
('generation_trooper', 'ジェネレーション兵', '📊', '生成技術で強化された兵士', 18, 1400, 1100, 1900, 700000, '{"food": 1500, "generation_unit": 50}', 16200, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'attack_up' LIMIT 1)),
('gene_warrior', '遺伝子戦士', '🧬', '遺伝子改造された超人兵士', 18, 1600, 1000, 2000, 800000, '{"food": 1800, "generation_gene": 60}', 18000, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'regeneration' LIMIT 1)),
('colony_guard', 'コロニーガード', '🏠', 'コロニー防衛専門部隊', 18, 1200, 1500, 2100, 750000, '{"food": 1600, "iron": 4000}', 17100, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_up' LIMIT 1)),
('orbital_bomber', '軌道爆撃機', '💥', '軌道からの爆撃', 18, 2000, 600, 1400, 900000, '{"iron": 6000, "antimatter_particle": 80}', 21600, 'ranged', (SELECT id FROM battle_special_skills WHERE skill_key = 'precision_shot' LIMIT 1)),

-- 近未来時代Ⅲ（時代19）
('movement_assassin', 'ムーブメントアサシン', '🎯', '高速移動暗殺者', 19, 1800, 900, 1600, 950000, '{"food": 2000, "movement_core": 40}', 21600, 'cavalry', (SELECT id FROM battle_special_skills WHERE skill_key = 'critical' LIMIT 1)),
('quantum_tank', '量子タンク', '💠', '量子シールド搭載戦車', 19, 2200, 1800, 2500, 1200000, '{"iron": 8000, "generation_quantum": 100}', 27000, 'siege', (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_harden' LIMIT 1)),
('star_trooper', 'スタートルーパー', '⭐', '恒星間戦闘部隊', 19, 2000, 1400, 2200, 1100000, '{"food": 2500, "dark_matter": 200}', 25200, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'evasion' LIMIT 1)),

-- 惑星革命時代（時代20）
('universe_soldier', 'ユニバースソルジャー', '🌟', '宇宙規模の戦闘兵', 20, 2500, 1800, 2600, 1500000, '{"food": 3000, "universe_tech": 80}', 32400, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'bloodlust' LIMIT 1)),
('planet_crusher', 'プラネットクラッシャー', '🪐', '惑星規模の破壊兵器', 20, 3500, 1500, 2800, 2000000, '{"iron": 15000, "scrap_charge": 200}', 43200, 'siege', (SELECT id FROM battle_special_skills WHERE skill_key = 'disarm' LIMIT 1)),
('un_peacekeeper', '国連平和維持軍', '🕊️', '国連直属の精鋭部隊', 20, 2200, 2200, 2700, 1800000, '{"food": 3500, "knowledge": 1000}', 36000, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'inspire' LIMIT 1)),

-- 近未来時代Ⅳ（時代21）
('cache_hacker', 'キャッシュハッカー', '💽', 'サイバー戦のスペシャリスト', 21, 2000, 1500, 2300, 1600000, '{"food": 3000, "cache_cluster": 100}', 32400, 'ranged', (SELECT id FROM battle_special_skills WHERE skill_key = 'vulnerable' LIMIT 1)),
('cosmic_knight', 'コズミックナイト', '💎', '宇宙シャード装甲の騎士', 21, 3000, 2500, 3000, 2200000, '{"iron": 12000, "cosmic_shard": 150}', 46800, 'cavalry', (SELECT id FROM battle_special_skills WHERE skill_key = 'counter' LIMIT 1)),
('energy_titan', 'エネルギータイタン', '⚡', 'エネルギー兵器の巨人', 21, 4000, 2000, 3200, 2800000, '{"iron": 18000, "energy_charger": 300}', 54000, 'siege', (SELECT id FROM battle_special_skills WHERE skill_key = 'double_attack' LIMIT 1)),

-- 近未来時代Ⅴ（時代22）
('quantum_commander', '量子コマンダー', '🔷', '量子技術の指揮官', 22, 3500, 2500, 3100, 2500000, '{"food": 4000, "quantum_module": 120}', 50400, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'inspire' LIMIT 1)),
('planet_guardian', '惑星ガーディアン', '🗄️', '惑星防衛の守護者', 22, 3000, 3500, 3500, 2800000, '{"food": 4500, "planet_memory": 150}', 54000, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_formation' LIMIT 1)),
('transmutation_mage', '変換術士', '🧪', '物質変換能力者', 22, 4500, 2000, 2800, 3200000, '{"food": 5000, "quantum_module": 200}', 61200, 'ranged', (SELECT id FROM battle_special_skills WHERE skill_key = 'weaken' LIMIT 1)),

-- 宇宙船革命時代（時代23）
('container_soldier', 'コンテナソルジャー', '🔑', '特殊装備の兵士', 23, 4000, 3000, 3400, 3500000, '{"food": 5500, "container_unlock_key": 50}', 64800, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'attack_up' LIMIT 1)),
('cosmic_archaeologist', 'コズミック考古学者', '🦴', '宇宙化石の力を使う', 23, 3800, 2800, 3200, 3200000, '{"food": 5000, "cosmic_fossil": 80}', 61200, 'ranged', (SELECT id FROM battle_special_skills WHERE skill_key = 'critical' LIMIT 1)),
('lightspeed_fighter', '光速戦闘機', '💫', '光速に近い戦闘機', 23, 5500, 2500, 3600, 4500000, '{"iron": 25000, "antimatter_particle": 500}', 79200, 'ranged', (SELECT id FROM battle_special_skills WHERE skill_key = 'acceleration' LIMIT 1)),

-- 銀河時代（時代24）
('ai_legion', 'AIレギオン', '📦', 'AI制御の軍団', 24, 5000, 4000, 4000, 5000000, '{"ai_crate": 100, "iron": 20000}', 86400, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'double_attack' LIMIT 1)),
('cosmic_operator', '宇宙オペレーター', '🎛️', '宇宙操作盤を使う技術兵', 24, 4500, 3500, 3800, 4500000, '{"food": 6000, "cosmic_console": 80}', 79200, 'ranged', (SELECT id FROM battle_special_skills WHERE skill_key = 'fear' LIMIT 1)),
('galactic_titan', '銀河タイタン', '🌌', '銀河規模の巨大兵器', 24, 8000, 5000, 5000, 8000000, '{"iron": 40000, "dark_matter": 1000}', 129600, 'siege', (SELECT id FROM battle_special_skills WHERE skill_key = 'armor_crush' LIMIT 1)),

-- 銀河時代Ⅱ（時代25）
('federation_elite', '連邦エリート', '✨', '銀河連邦の精鋭', 25, 7000, 6000, 5500, 7000000, '{"food": 8000, "universe_tech": 300}', 108000, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'regeneration' LIMIT 1)),
('harmony_guardian', 'ハーモニーガーディアン', '🕊️', '宇宙調和の守護者', 25, 6000, 8000, 6000, 8000000, '{"food": 10000, "ai_crate": 200}', 122400, 'infantry', (SELECT id FROM battle_special_skills WHERE skill_key = 'defense_up' LIMIT 1)),
('universal_destroyer', 'ユニバーサルデストロイヤー', '💥', '究極の破壊兵器', 25, 12000, 6000, 5000, 15000000, '{"iron": 80000, "cosmic_console": 500}', 172800, 'siege', (SELECT id FROM battle_special_skills WHERE skill_key = 'disarm' LIMIT 1));

-- ===============================================
-- ⑧ 新研究を追加（各時代5〜10個）
-- ===============================================
INSERT IGNORE INTO civilization_researches (research_key, name, icon, description, era_id, unlock_building_id, unlock_resource_id, research_cost_points, research_time_seconds, prerequisite_research_id) VALUES
-- 現代Ⅵ（時代15）
('dark_matter_theory', 'ダークマター理論', '🌑', 'ダークマターの性質を解明', 15, NULL, NULL, 60000, 180000, NULL),
('cybernetic_enhancement', 'サイバネティクス強化', '🦾', '人体とテクノロジーの融合', 15, NULL, NULL, 65000, 194400, NULL),
('energy_storage_advanced', '高度エネルギー貯蔵', '🔋', 'エネルギー効率の革命', 15, NULL, NULL, 55000, 165600, NULL),
('mega_construction', 'メガ建設技術', '🏙️', '超大規模建造物の建設', 15, NULL, NULL, 70000, 208800, NULL),
('quantum_soldier_program', '量子兵士計画', '💠', '量子技術を兵士に応用', 15, NULL, NULL, 75000, 223200, NULL),

-- 地球大革命時代（時代16）
('global_ecosystem', 'グローバルエコシステム', '🌿', '地球規模の環境管理', 16, NULL, NULL, 90000, 259200, NULL),
('portal_technology', 'ポータル技術', '🌀', '次元転移の基礎', 16, NULL, NULL, 100000, 288000, NULL),
('tech_core_manufacturing', 'テックコア製造', '💾', '高度技術結晶の生産', 16, NULL, NULL, 95000, 273600, NULL),
('global_defense_network', '地球防衛ネットワーク', '🛡️', '地球規模の防衛システム', 16, NULL, NULL, 110000, 316800, NULL),
('advanced_mech_warfare', '高度メック戦', '🤖', 'メック技術の軍事応用', 16, NULL, NULL, 105000, 302400, NULL),

-- 近未来時代（時代17）
('antimatter_manipulation', '反物質操作', '⚛️', '反物質の制御と応用', 17, NULL, NULL, 130000, 345600, NULL),
('synthetic_particle_science', '合成素粒子科学', '🔬', '素粒子レベルの物質合成', 17, NULL, NULL, 125000, 331200, NULL),
('space_colonization', '宇宙植民', '🏠', '宇宙空間での居住技術', 17, NULL, NULL, 140000, 374400, NULL),
('mega_mech_design', 'メガメック設計', '⚙️', '巨大メックの設計技術', 17, NULL, NULL, 150000, 403200, NULL),
('elite_space_combat', 'エリート宇宙戦闘', '🚀', '宇宙戦闘のエリート訓練', 17, NULL, NULL, 145000, 388800, NULL),
('interstellar_navigation', '恒星間航法', '🧭', '恒星間移動の技術', 17, NULL, NULL, 155000, 417600, NULL),

-- 近未来時代Ⅱ（時代18）
('generation_technology', '生成技術', '📊', '物質生成の基礎技術', 18, NULL, NULL, 180000, 446400, NULL),
('genetic_super_soldier', '遺伝子超人兵', '🧬', '超人兵士の遺伝子設計', 18, NULL, NULL, 190000, 475200, NULL),
('colony_ship_design', 'コロニー船設計', '🚀', '大規模移民船の設計', 18, NULL, NULL, 200000, 504000, NULL),
('orbital_warfare', '軌道戦争', '💥', '軌道からの戦闘技術', 18, NULL, NULL, 185000, 460800, NULL),
('orbital_fortress_construction', '軌道要塞建設', '🏰', '軌道上の要塞建設', 18, NULL, NULL, 210000, 532800, NULL),

-- 近未来時代Ⅲ（時代19）
('movement_technology', 'ムーブメント技術', '🎯', '高速移動の革新', 19, NULL, NULL, 250000, 561600, NULL),
('quantum_armor', '量子装甲', '💠', '量子シールドの装甲応用', 19, NULL, NULL, 260000, 590400, NULL),
('star_base_construction', 'スターベース建設', '⭐', '恒星系拠点の建設', 19, NULL, NULL, 280000, 648000, NULL),
('interstellar_combat', '恒星間戦闘', '⚔️', '恒星間での戦闘技術', 19, NULL, NULL, 270000, 619200, NULL),
('advanced_training_methods', '高度訓練法', '🎓', '革新的な訓練手法', 19, NULL, NULL, 240000, 532800, NULL),

-- 惑星革命時代（時代20）
('universe_tech_synthesis', 'ユニバーステック合成', '🌟', '宇宙技術の結晶化', 20, NULL, NULL, 350000, 734400, NULL),
('planetary_engineering', '惑星工学', '🪐', '惑星規模の改造技術', 20, NULL, NULL, 380000, 806400, NULL),
('un_peacekeeping_doctrine', '国連平和維持ドクトリン', '🕊️', '国際平和維持の教義', 20, NULL, NULL, 320000, 676800, NULL),
('scrap_energy_recycling', 'スクラップエネルギー再生', '♻️', 'リサイクルエネルギーの最大化', 20, NULL, NULL, 300000, 619200, NULL),
('planetary_city_planning', '惑星都市計画', '🌍', '惑星規模の都市設計', 20, NULL, NULL, 400000, 864000, NULL),

-- 近未来時代Ⅳ（時代21）
('cache_technology', 'キャッシュ技術', '💽', 'データ処理の革命', 21, NULL, NULL, 450000, 907200, NULL),
('cosmic_harvesting', '宇宙シャード収穫', '💎', '宇宙資源の効率的収集', 21, NULL, NULL, 480000, 979200, NULL),
('energy_warfare', 'エネルギー戦争', '⚡', 'エネルギー兵器の軍事化', 21, NULL, NULL, 500000, 1036800, NULL),
('cyber_warfare_advanced', '高度サイバー戦', '💻', 'サイバー戦の極限', 21, NULL, NULL, 420000, 849600, NULL),
('cosmic_knight_training', 'コズミックナイト訓練', '🗡️', '宇宙騎士の育成', 21, NULL, NULL, 470000, 950400, NULL),

-- 近未来時代Ⅴ（時代22）
('quantum_module_engineering', '量子モジュール工学', '🔷', '量子技術のモジュール化', 22, NULL, NULL, 550000, 1094400, NULL),
('planet_memory_systems', '惑星メモリシステム', '🗄️', '惑星規模のデータ管理', 22, NULL, NULL, 580000, 1166400, NULL),
('transmutation_science', '変換科学', '🧪', '物質変換の科学', 22, NULL, NULL, 600000, 1224000, NULL),
('planetary_defense', '惑星防衛', '🛡️', '惑星防衛システム', 22, NULL, NULL, 520000, 1036800, NULL),
('quantum_command_structure', '量子指揮構造', '👨‍✈️', '量子通信による指揮', 22, NULL, NULL, 560000, 1123200, NULL),

-- 宇宙船革命時代（時代23）
('container_technology', 'コンテナ技術', '🔑', '特殊コンテナの開発', 23, NULL, NULL, 700000, 1296000, NULL),
('cosmic_archaeology', '宇宙考古学', '🦴', '宇宙の歴史を解明', 23, NULL, NULL, 750000, 1411200, NULL),
('lightspeed_propulsion', '光速推進', '💫', '光速に近い推進技術', 23, NULL, NULL, 800000, 1526400, NULL),
('near_light_combat', '亜光速戦闘', '⚔️', '亜光速での戦闘技術', 23, NULL, NULL, 720000, 1339200, NULL),
('cosmic_exploration', '宇宙探査', '🔭', '深宇宙の探査技術', 23, NULL, NULL, 680000, 1252800, NULL),

-- 銀河時代（時代24）
('ai_legion_control', 'AIレギオン制御', '📦', 'AI軍団の制御技術', 24, NULL, NULL, 900000, 1641600, NULL),
('cosmic_operations', '宇宙作戦', '🎛️', '宇宙規模の作戦立案', 24, NULL, NULL, 950000, 1756800, NULL),
('galactic_warfare', '銀河戦争', '🌌', '銀河規模の戦争技術', 24, NULL, NULL, 1000000, 1872000, NULL),
('galactic_megacity_design', '銀河メガシティ設計', '🌃', '銀河規模の都市設計', 24, NULL, NULL, 850000, 1526400, NULL),
('galactic_fortress_engineering', '銀河要塞工学', '🏰', '銀河要塞の建設技術', 24, NULL, NULL, 1100000, 2073600, NULL),

-- 銀河時代Ⅱ（時代25）
('galactic_federation', '銀河連邦', '✨', '銀河連邦の確立', 25, NULL, NULL, 1500000, 2592000, NULL),
('universal_harmony', '宇宙調和', '🕊️', '宇宙の平和と調和', 25, NULL, NULL, 1800000, 3110400, NULL),
('ultimate_destruction', '究極破壊', '💥', '究極の破壊兵器技術', 25, NULL, NULL, 2000000, 3456000, NULL),
('galactic_supremacy', '銀河覇権', '👑', '銀河の支配権確立', 25, NULL, NULL, 2500000, 4320000, NULL);

-- ===============================================
-- ⑩ 対応するクエストを追加
-- ===============================================
INSERT IGNORE INTO civilization_quests (quest_key, quest_category, era_id, title, description, icon, quest_type, target_key, target_count, reward_coins, reward_crystals, reward_diamonds, reward_resources, is_repeatable, cooldown_hours, sort_order) VALUES
-- 現代Ⅵ
('modern6_train_cyber_warriors', 'training', 15, 'サイバーウォリアーを10人訓練', 'サイバネティクス強化された戦士を訓練しましょう', '🦾', 'train', 'cyber_warrior', 10, 500000, 500, 50, '{"dark_matter": 100}', FALSE, NULL, 10),
('modern6_build_mega_tower', 'building', 15, '超高層タワーを建設', '巨大な居住施設を建設しましょう', '🏙️', 'build', 'mega_tower', 1, 800000, 800, 80, '{"energy_charger": 50}', FALSE, NULL, 20),
('modern6_research_dark_matter', 'research', 15, 'ダークマター理論を研究', 'ダークマターの性質を解明しましょう', '🌑', 'research', 'dark_matter_theory', 1, 600000, 600, 60, NULL, FALSE, NULL, 30),

-- 地球大革命時代
('earth_train_eco_guardians', 'training', 16, 'エコガーディアンを10人訓練', '環境保護型戦闘ユニットを訓練しましょう', '🌿', 'train', 'eco_guardian', 10, 700000, 700, 70, '{"tech_core": 100}', FALSE, NULL, 10),
('earth_build_portal_gate', 'building', 16, 'ポータルゲートを建設', 'ポータル技術の拠点を建設しましょう', '🌀', 'build', 'portal_gate', 1, 1000000, 1000, 100, '{"portal_token": 50}', FALSE, NULL, 20),

-- 近未来時代
('nearfuture_train_antimatter_soldiers', 'training', 17, '反物質兵を5人訓練', '反物質エネルギーを使う兵士を訓練しましょう', '⚛️', 'train', 'antimatter_soldier', 5, 900000, 900, 90, '{"antimatter_particle": 50}', FALSE, NULL, 10),
('nearfuture_build_mech_factory', 'building', 17, 'メック工場を建設', '戦闘メックを生産する施設を建設しましょう', '🤖', 'build', 'mech_factory', 1, 1200000, 1200, 120, '{"synthetic_particle": 100}', FALSE, NULL, 20),

-- 近未来時代Ⅱ
('nearfuture2_train_gene_warriors', 'training', 18, '遺伝子戦士を5人訓練', '遺伝子改造された超人兵士を訓練しましょう', '🧬', 'train', 'gene_warrior', 5, 1100000, 1100, 110, '{"generation_gene": 100}', FALSE, NULL, 10),
('nearfuture2_build_orbital_fortress', 'building', 18, '軌道要塞を建設', '軌道上の軍事要塞を建設しましょう', '🏰', 'build', 'orbital_fortress', 1, 1500000, 1500, 150, '{"generation_unit": 200}', FALSE, NULL, 20),

-- 近未来時代Ⅲ
('nearfuture3_train_star_troopers', 'training', 19, 'スタートルーパーを5人訓練', '恒星間戦闘部隊を訓練しましょう', '⭐', 'train', 'star_trooper', 5, 1400000, 1400, 140, '{"movement_core": 100}', FALSE, NULL, 10),
('nearfuture3_build_star_base', 'building', 19, 'スターベースを建設', '恒星系の拠点を建設しましょう', '⭐', 'build', 'star_base', 1, 2000000, 2000, 200, '{"generation_quantum": 200}', FALSE, NULL, 20),

-- 惑星革命時代
('planet_train_un_peacekeepers', 'training', 20, '国連平和維持軍を5人訓練', '国連直属の精鋭部隊を訓練しましょう', '🕊️', 'train', 'un_peacekeeper', 5, 2000000, 2000, 200, '{"universe_tech": 150}', FALSE, NULL, 10),
('planet_build_un_facility', 'building', 20, '国連施設を建設', '大使館の上位互換施設を建設しましょう', '🏛️', 'build', 'un_facility', 1, 2500000, 2500, 250, '{"scrap_charge": 500}', FALSE, NULL, 20),

-- 近未来時代Ⅳ
('nearfuture4_train_cosmic_knights', 'training', 21, 'コズミックナイトを3人訓練', '宇宙シャード装甲の騎士を訓練しましょう', '💎', 'train', 'cosmic_knight', 3, 2500000, 2500, 250, '{"cosmic_shard": 300}', FALSE, NULL, 10),
('nearfuture4_build_energy_citadel', 'building', 21, 'エネルギー城塞を建設', 'エネルギー技術の軍事拠点を建設しましょう', '⚡', 'build', 'energy_citadel', 1, 3500000, 3500, 350, '{"cache_cluster": 200}', FALSE, NULL, 20),

-- 近未来時代Ⅴ
('nearfuture5_train_transmutation_mages', 'training', 22, '変換術士を3人訓練', '物質変換能力者を訓練しましょう', '🧪', 'train', 'transmutation_mage', 3, 3500000, 3500, 350, '{"quantum_module": 300}', FALSE, NULL, 10),
('nearfuture5_build_transmutation_tower', 'building', 22, '変換タワーを建設', '物質変換施設を建設しましょう', '🧪', 'build', 'transmutation_tower', 1, 4000000, 4000, 400, '{"planet_memory": 300}', FALSE, NULL, 20),

-- 宇宙船革命時代
('spaceship_train_lightspeed_fighters', 'training', 23, '光速戦闘機を3機生産', '光速に近い戦闘機を生産しましょう', '💫', 'train', 'lightspeed_fighter', 3, 5000000, 5000, 500, '{"container_unlock_key": 100}', FALSE, NULL, 10),
('spaceship_build_lightspeed_dock', 'building', 23, '光速ドックを建設', '光速宇宙船の建造施設を建設しましょう', '💫', 'build', 'lightspeed_dock', 1, 6000000, 6000, 600, '{"cosmic_fossil": 200}', FALSE, NULL, 20),

-- 銀河時代
('galactic_train_ai_legion', 'training', 24, 'AIレギオンを3部隊訓練', 'AI制御の軍団を訓練しましょう', '📦', 'train', 'ai_legion', 3, 6000000, 6000, 600, '{"ai_crate": 200}', FALSE, NULL, 10),
('galactic_build_galactic_fortress', 'building', 24, '銀河要塞を建設', '銀河規模の軍事要塞を建設しましょう', '🌌', 'build', 'galactic_fortress', 1, 10000000, 10000, 1000, '{"cosmic_console": 300}', FALSE, NULL, 20),

-- 銀河時代Ⅱ
('galactic2_train_federation_elite', 'training', 25, '連邦エリートを3人訓練', '銀河連邦の精鋭を訓練しましょう', '✨', 'train', 'federation_elite', 3, 8000000, 8000, 800, '{"universe_tech": 500}', FALSE, NULL, 10),
('galactic2_build_federation_hq', 'building', 25, '銀河連邦本部を建設', '銀河連邦の中枢を建設しましょう', '✨', 'build', 'galactic_federation_hq', 1, 15000000, 15000, 1500, '{"ai_crate": 500, "cosmic_console": 300}', FALSE, NULL, 20),
('galactic2_ultimate_victory', 'conquest', 25, '宇宙の覇者', '銀河を支配しましょう', '👑', 'conquest', NULL, 10, 50000000, 50000, 5000, NULL, FALSE, NULL, 100);

-- ===============================================
-- ⑨ 前提条件を追加（建物）
-- ===============================================

-- 現代Ⅵの建物：軍事基地または空軍基地が前提
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'military_base' LIMIT 1) WHERE building_key = 'dark_matter_collector';
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'military_base' LIMIT 1) WHERE building_key = 'energy_hub';
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'apartment' LIMIT 1) WHERE building_key = 'mega_tower';

-- 地球大革命時代の建物
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'dark_matter_collector' LIMIT 1) WHERE building_key = 'tech_core_factory';
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'tech_core_factory' LIMIT 1) WHERE building_key = 'portal_gate';
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'mega_tower' LIMIT 1) WHERE building_key = 'eco_dome';
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'air_base' LIMIT 1) WHERE building_key = 'global_defense_center';

-- 近未来時代の建物
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'portal_gate' LIMIT 1) WHERE building_key = 'antimatter_generator';
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'university' LIMIT 1) WHERE building_key = 'synthetic_lab';
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'eco_dome' LIMIT 1) WHERE building_key = 'space_habitat';
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'global_defense_center' LIMIT 1) WHERE building_key = 'mech_factory';

-- 惑星革命時代の建物
UPDATE civilization_building_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'castle' LIMIT 1) WHERE building_key = 'un_facility';

-- ===============================================
-- ⑨ 前提条件を追加（兵種）
-- ===============================================

-- 現代Ⅵの兵種：軍事基地が前提
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'military_base' LIMIT 1) WHERE troop_key = 'cyber_warrior';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'military_base' LIMIT 1) WHERE troop_key = 'dark_matter_tank';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'air_base' LIMIT 1) WHERE troop_key = 'energy_drone';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'military_base' LIMIT 1) WHERE troop_key = 'quantum_soldier';

-- 地球大革命時代の兵種
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'global_defense_center' LIMIT 1) WHERE troop_key = 'eco_guardian';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'portal_gate' LIMIT 1) WHERE troop_key = 'portal_knight';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'mech_factory' LIMIT 1) WHERE troop_key = 'tech_mech';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'global_defense_center' LIMIT 1) WHERE troop_key = 'global_defender';

-- 近未来時代の兵種
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'antimatter_generator' LIMIT 1) WHERE troop_key = 'antimatter_soldier';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'synthetic_lab' LIMIT 1) WHERE troop_key = 'synthetic_warrior';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'space_habitat' LIMIT 1) WHERE troop_key = 'space_marine_elite';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'mech_factory' LIMIT 1) WHERE troop_key = 'mega_mech';

-- 惑星革命時代の兵種
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'un_facility' LIMIT 1) WHERE troop_key = 'un_peacekeeper';

-- 銀河時代の兵種
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'galactic_fortress' LIMIT 1) WHERE troop_key = 'galactic_titan';

-- 銀河時代Ⅱの兵種
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'galactic_federation_hq' LIMIT 1) WHERE troop_key = 'federation_elite';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'galactic_federation_hq' LIMIT 1) WHERE troop_key = 'harmony_guardian';
UPDATE civilization_troop_types SET prerequisite_building_id = (SELECT id FROM civilization_building_types WHERE building_key = 'galactic_fortress' LIMIT 1) WHERE troop_key = 'universal_destroyer';

-- ===============================================
-- 完了メッセージ
-- ===============================================
SELECT 'MiniBird feature expansion 2026 schema applied successfully' AS status;
SELECT CONCAT('Added ', COUNT(*), ' new eras') AS eras_count FROM civilization_eras WHERE era_order >= 15;
