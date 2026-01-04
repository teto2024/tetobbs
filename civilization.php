<?php
// ===============================================
// civilization.php
// 文明育成システム（Home Quest風）
// ===============================================

require_once __DIR__ . '/config.php';

$me = user();
if (!$me) {
    header('Location: ./login.php');
    exit;
}

$pdo = db();
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>文明育成 - MiniBird</title>
<link rel="stylesheet" href="assets/style.css?v=<?= ASSETS_VERSION ?>">
<style>
* {
    box-sizing: border-box;
}

body {
    background: linear-gradient(180deg, #1a0f0a 0%, #2d1810 50%, #1a0f0a 100%);
    min-height: 100vh;
    margin: 0;
    color: #f5deb3;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.civ-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.back-link {
    display: inline-block;
    margin-bottom: 20px;
    padding: 10px 20px;
    background: rgba(255,255,255,0.1);
    color: #f5deb3;
    border-radius: 10px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s;
}

.back-link:hover {
    background: #d4a574;
    color: #1a0f0a;
}

/* ヘッダー */
.civ-header {
    background: linear-gradient(135deg, rgba(139, 69, 19, 0.8) 0%, rgba(101, 67, 33, 0.8) 100%);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    border: 2px solid #d4a574;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.civ-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.civ-name {
    font-size: 28px;
    font-weight: bold;
    color: #ffd700;
    text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
    cursor: pointer;
}

.civ-era {
    font-size: 18px;
    color: #f5deb3;
    background: rgba(0,0,0,0.3);
    padding: 8px 16px;
    border-radius: 8px;
}

.civ-stats {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.stat-box {
    background: rgba(0,0,0,0.3);
    padding: 12px 20px;
    border-radius: 10px;
    text-align: center;
    min-width: 100px;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #ffd700;
}

.stat-label {
    font-size: 12px;
    color: #c0a080;
}

/* 資源バー */
.resources-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 25px;
    background: rgba(0,0,0,0.3);
    padding: 15px;
    border-radius: 12px;
}

.resource-item {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.05);
    padding: 8px 14px;
    border-radius: 8px;
    min-width: 100px;
}

.resource-icon {
    font-size: 20px;
}

.resource-amount {
    font-weight: bold;
    color: #ffd700;
}

/* タブ */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.tab-btn {
    padding: 12px 24px;
    border: 2px solid #8b4513;
    border-radius: 10px;
    background: rgba(0,0,0,0.3);
    color: #c0a080;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s;
}

.tab-btn.active {
    background: linear-gradient(135deg, #8b4513 0%, #d4a574 100%);
    color: #fff;
    border-color: #ffd700;
}

.tab-btn {
    position: relative;
}

.tab-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
    color: #fff;
    font-size: 10px;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 16px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    animation: pulse-badge 2s infinite;
}

@keyframes pulse-badge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* 投資セクション */
.invest-section {
    background: linear-gradient(135deg, rgba(139, 69, 19, 0.5) 0%, rgba(101, 67, 33, 0.5) 100%);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    border: 2px solid #8b4513;
}

.invest-section h3 {
    margin: 0 0 20px 0;
    color: #ffd700;
    font-size: 20px;
}

.invest-form {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.invest-input {
    padding: 12px 16px;
    font-size: 18px;
    background: rgba(0,0,0,0.3);
    border: 2px solid #8b4513;
    border-radius: 10px;
    color: #f5deb3;
    width: 150px;
}

.invest-input:focus {
    border-color: #ffd700;
    outline: none;
}

.invest-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%);
    color: #1a0f0a;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.invest-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
}

.quick-invest-btns {
    display: flex;
    gap: 10px;
}

.quick-invest-btn {
    padding: 8px 16px;
    background: rgba(255,255,255,0.1);
    border: 1px solid #8b4513;
    border-radius: 8px;
    color: #f5deb3;
    cursor: pointer;
    transition: all 0.3s;
}

.quick-invest-btn:hover {
    background: #8b4513;
}

/* 建物グリッド */
.buildings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.building-card {
    background: linear-gradient(135deg, rgba(50, 30, 15, 0.9) 0%, rgba(80, 50, 25, 0.9) 100%);
    border-radius: 12px;
    padding: 20px;
    border: 2px solid #8b4513;
    transition: all 0.3s;
}

.building-card:hover {
    border-color: #ffd700;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
}

.building-card.owned {
    border-color: #228b22;
    background: linear-gradient(135deg, rgba(34, 70, 34, 0.5) 0%, rgba(50, 80, 50, 0.5) 100%);
}

.building-card.constructing {
    border-color: #ffa500;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.building-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.building-icon {
    font-size: 32px;
}

.building-name {
    font-size: 18px;
    font-weight: bold;
    color: #ffd700;
}

.building-level {
    background: rgba(0,0,0,0.3);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 14px;
}

.building-desc {
    color: #c0a080;
    font-size: 14px;
    margin-bottom: 15px;
}

.building-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
}

.building-stat {
    background: rgba(0,0,0,0.2);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
}

.building-cost {
    background: rgba(139, 69, 19, 0.3);
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 12px;
    font-size: 13px;
}

.build-btn, .upgrade-btn {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.build-btn {
    background: linear-gradient(135deg, #228b22 0%, #32cd32 100%);
    color: #fff;
}

.upgrade-btn {
    background: linear-gradient(135deg, #4169e1 0%, #6495ed 100%);
    color: #fff;
}

.build-btn:hover, .upgrade-btn:hover {
    transform: translateY(-2px);
}

.build-btn:disabled, .upgrade-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* 研究ツリー */
.research-tree {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.research-card {
    background: linear-gradient(135deg, rgba(30, 30, 60, 0.9) 0%, rgba(60, 60, 100, 0.9) 100%);
    border-radius: 12px;
    padding: 18px;
    border: 2px solid #4169e1;
    transition: all 0.3s;
}

.research-card.completed {
    border-color: #228b22;
    background: linear-gradient(135deg, rgba(34, 70, 34, 0.5) 0%, rgba(50, 80, 50, 0.5) 100%);
}

.research-card.researching {
    border-color: #ffa500;
    animation: pulse 2s ease-in-out infinite;
}

.research-card.locked {
    opacity: 0.5;
    border-color: #444;
}

.research-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.research-icon {
    font-size: 28px;
}

.research-name {
    font-size: 16px;
    font-weight: bold;
    color: #87ceeb;
}

.research-desc {
    color: #a0a0c0;
    font-size: 13px;
    margin-bottom: 12px;
}

.research-cost {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(0,0,0,0.2);
    padding: 8px 12px;
    border-radius: 6px;
    margin-bottom: 12px;
    font-size: 13px;
}

.research-btn {
    width: 100%;
    padding: 10px;
    background: linear-gradient(135deg, #4169e1 0%, #6495ed 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.research-btn:hover {
    transform: translateY(-2px);
}

.research-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* 戦争タブ */
.war-section {
    background: linear-gradient(135deg, rgba(100, 20, 20, 0.5) 0%, rgba(50, 10, 10, 0.5) 100%);
    border-radius: 16px;
    padding: 25px;
    border: 2px solid #8b0000;
}

.war-section h3 {
    color: #ff6b6b;
    margin: 0 0 20px 0;
}

.targets-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}

.target-card {
    background: rgba(0,0,0,0.3);
    border-radius: 10px;
    padding: 15px;
    border: 1px solid #8b0000;
}

.target-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.target-name {
    font-weight: bold;
    color: #ff6b6b;
}

.target-power {
    background: rgba(255,0,0,0.2);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 13px;
}

.attack-btn {
    width: 100%;
    padding: 10px;
    background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.attack-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(220, 20, 60, 0.4);
}

/* 時代進化 */
.era-progress {
    background: rgba(0,0,0,0.3);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}

.era-progress h3 {
    color: #ffd700;
    margin: 0 0 15px 0;
}

.era-requirements {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.era-req {
    background: rgba(255,255,255,0.05);
    padding: 10px 16px;
    border-radius: 8px;
}

.req-label {
    font-size: 12px;
    color: #888;
}

.req-value {
    font-weight: bold;
}

.req-value.met {
    color: #32cd32;
}

.req-value.unmet {
    color: #ff6b6b;
}

.advance-era-btn {
    padding: 14px 28px;
    background: linear-gradient(135deg, #9932cc 0%, #da70d6 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.advance-era-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(153, 50, 204, 0.5);
}

.advance-era-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* 通知 */
.notification {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: linear-gradient(135deg, #228b22 0%, #32cd32 100%);
    color: #fff;
    padding: 15px 25px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    z-index: 1000;
    animation: slideIn 0.3s ease-out;
}

.notification.error {
    background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* レスポンシブ */
@media (max-width: 768px) {
    .civ-header {
        flex-direction: column;
        text-align: center;
    }
    
    .civ-stats {
        justify-content: center;
    }
    
    .invest-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .invest-input {
        width: 100%;
    }
    
    .tabs {
        justify-content: center;
    }
    
    .buildings-grid, .research-tree, .targets-list {
        grid-template-columns: 1fr;
    }
}

/* ローディング */
.loading {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 50px;
    color: #c0a080;
}

.loading::after {
    content: '';
    width: 40px;
    height: 40px;
    border: 4px solid #8b4513;
    border-top-color: #ffd700;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 15px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* 攻撃モーダル */
.attack-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.85);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.attack-modal-overlay.active {
    display: flex;
}

.attack-modal {
    background: linear-gradient(135deg, #1a0f0a 0%, #2d1810 100%);
    border-radius: 16px;
    padding: 25px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    border: 2px solid #8b4513;
}

.attack-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.attack-modal-title {
    font-size: 20px;
    font-weight: bold;
    color: #ffd700;
}

.attack-modal-close {
    background: none;
    border: none;
    color: #c0a080;
    font-size: 24px;
    cursor: pointer;
}

.troop-select-row {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.05);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.troop-select-info {
    flex: 1;
    min-width: 120px;
}

.troop-select-icon {
    font-size: 20px;
}

.troop-select-name {
    color: #f5deb3;
    font-weight: bold;
    font-size: 14px;
}

.troop-select-stats {
    font-size: 11px;
    color: #a08060;
}

.troop-select-slider {
    width: 100px;
    -webkit-appearance: none;
    height: 8px;
    border-radius: 4px;
    background: #8b4513;
}

.troop-select-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #ffd700;
    cursor: pointer;
}

.troop-select-slider::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #ffd700;
    cursor: pointer;
    border: none;
}

.troop-select-count {
    width: 60px;
    padding: 8px;
    background: rgba(0,0,0,0.3);
    border: 1px solid #8b4513;
    border-radius: 6px;
    color: #f5deb3;
    text-align: center;
}

.troop-select-count:focus {
    border-color: #ffd700;
    outline: none;
}

.troop-select-max {
    font-size: 11px;
    color: #888;
    min-width: 50px;
    text-align: right;
}

.attack-power-display {
    background: rgba(0,0,0,0.3);
    padding: 15px;
    border-radius: 10px;
    margin: 15px 0;
    text-align: center;
}

.attack-power-value {
    font-size: 24px;
    font-weight: bold;
    color: #ff6b6b;
}

.attack-confirm-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.attack-confirm-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(220, 20, 60, 0.5);
}

.attack-confirm-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* チュートリアルモーダル */
.tutorial-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.tutorial-modal-overlay.show {
    display: flex;
}

.tutorial-modal {
    background: linear-gradient(135deg, #2d1810 0%, #4a2c2a 100%);
    border: 3px solid #ffd700;
    border-radius: 20px;
    padding: 30px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 0 30px rgba(255, 215, 0, 0.3);
    animation: slideIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.tutorial-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid rgba(255, 215, 0, 0.3);
}

.tutorial-modal-title {
    font-size: 24px;
    font-weight: bold;
    color: #ffd700;
    margin: 0;
}

.tutorial-modal-close {
    background: none;
    border: none;
    color: #f5deb3;
    font-size: 28px;
    cursor: pointer;
    opacity: 0.7;
    transition: all 0.3s;
}

.tutorial-modal-close:hover {
    opacity: 1;
    color: #ff6b6b;
}

.tutorial-modal-content {
    color: #f5deb3;
    line-height: 1.8;
    margin-bottom: 20px;
}

.tutorial-modal-content p {
    margin: 10px 0;
}

.tutorial-modal-content strong {
    color: #ffd700;
}

.tutorial-hint {
    background: rgba(255, 215, 0, 0.1);
    border-left: 4px solid #ffd700;
    padding: 15px;
    margin: 20px 0;
    border-radius: 0 10px 10px 0;
}

.tutorial-hint-title {
    font-weight: bold;
    color: #ffd700;
    margin-bottom: 5px;
}

.tutorial-modal-footer {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.tutorial-btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.tutorial-btn-primary {
    background: linear-gradient(135deg, #ffd700 0%, #ffb800 100%);
    color: #1a0f0a;
}

.tutorial-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.5);
}

.tutorial-btn-secondary {
    background: rgba(255,255,255,0.1);
    color: #f5deb3;
    border: 1px solid rgba(255,255,255,0.2);
}

.tutorial-btn-secondary:hover {
    background: rgba(255,255,255,0.2);
}

/* クエストタブ */
.quest-tab-content {
    display: none;
}

.quest-tab-content.active {
    display: block;
}

.quest-category-section {
    margin-bottom: 25px;
}

.quest-category-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(212, 165, 116, 0.3);
}

.quest-category-icon {
    font-size: 24px;
}

.quest-category-name {
    font-size: 18px;
    font-weight: bold;
    color: #ffd700;
}

.quest-card {
    background: rgba(0,0,0,0.3);
    border: 2px solid rgba(212, 165, 116, 0.4);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 12px;
    transition: all 0.3s;
}

.quest-card:hover {
    border-color: #d4a574;
    background: rgba(0,0,0,0.4);
}

.quest-card.completed {
    border-color: #48bb78;
    background: rgba(72, 187, 120, 0.1);
}

.quest-card.claimed {
    opacity: 0.6;
}

.quest-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.quest-title {
    font-size: 16px;
    font-weight: bold;
    color: #f5deb3;
    display: flex;
    align-items: center;
    gap: 8px;
}

.quest-era-badge {
    font-size: 12px;
    padding: 2px 8px;
    background: rgba(255, 215, 0, 0.2);
    border-radius: 10px;
    color: #ffd700;
}

.quest-description {
    font-size: 14px;
    color: #c4a882;
    margin-bottom: 12px;
}

.quest-progress-bar {
    height: 8px;
    background: rgba(0,0,0,0.3);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 12px;
}

.quest-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #d4a574 0%, #ffd700 100%);
    border-radius: 4px;
    transition: width 0.5s;
}

.quest-progress-fill.completed {
    background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
}

.quest-progress-text {
    font-size: 12px;
    color: #c4a882;
    margin-bottom: 10px;
}

.quest-rewards {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.quest-reward-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    padding: 4px 10px;
    background: rgba(255,255,255,0.1);
    border-radius: 15px;
}

.quest-claim-btn {
    width: 100%;
    padding: 10px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.quest-claim-btn.available {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: white;
}

.quest-claim-btn.available:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(72, 187, 120, 0.5);
}

.quest-claim-btn.in-progress {
    background: rgba(255,255,255,0.1);
    color: #c4a882;
    cursor: default;
}

.quest-claim-btn.claimed {
    background: rgba(0,0,0,0.2);
    color: #666;
    cursor: default;
}

.quest-cooldown {
    font-size: 12px;
    color: #ff9800;
    margin-top: 5px;
}
</style>
</head>
<body>
<div class="civ-container">
    <a href="./" class="back-link">← フィードに戻る</a>
    
    <div id="app">
        <div class="loading">データを読み込み中...</div>
    </div>
</div>

<!-- 攻撃部隊選択モーダル -->
<div class="attack-modal-overlay" id="attackModal">
    <div class="attack-modal">
        <div class="attack-modal-header">
            <h3 class="attack-modal-title">⚔️ 出撃部隊を選択</h3>
            <button class="attack-modal-close" onclick="closeAttackModal()">×</button>
        </div>
        <div id="attackModalTarget"></div>
        <div id="attackAdvantageDisplay"></div>
        <div id="attackTroopSelector"></div>
        <div class="attack-power-display">
            <div>出撃パワー</div>
            <div class="attack-power-value" id="attackPowerDisplay">0</div>
        </div>
        <button class="attack-confirm-btn" id="confirmAttackBtn" onclick="confirmAttack()">
            ⚔️ 攻撃開始
        </button>
    </div>
</div>

<!-- チュートリアルガイドモーダル -->
<div class="tutorial-modal-overlay" id="tutorialModal">
    <div class="tutorial-modal">
        <div class="tutorial-modal-header">
            <h3 class="tutorial-modal-title" id="tutorialModalTitle">📖 チュートリアル</h3>
            <button class="tutorial-modal-close" onclick="closeTutorialModal()">×</button>
        </div>
        <div class="tutorial-modal-content" id="tutorialModalContent">
            <!-- 動的にコンテンツが挿入される -->
        </div>
        <div class="tutorial-hint" id="tutorialHint" style="display: none;">
            <div class="tutorial-hint-title">💡 ヒント</div>
            <div id="tutorialHintText"></div>
        </div>
        <div class="tutorial-modal-footer">
            <button class="tutorial-btn tutorial-btn-secondary" onclick="closeTutorialModal()">後で見る</button>
            <button class="tutorial-btn tutorial-btn-primary" onclick="closeTutorialModal()">わかった！</button>
        </div>
    </div>
</div>

<script>
// メンテナンスモード検出（定期的にチェック）
let maintenanceCheckInterval = null;
let isMaintenanceMode = false;

async function checkGameMaintenance() {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'check_game_maintenance'})
        });
        const data = await res.json();
        
        if (data.maintenance && !isMaintenanceMode) {
            isMaintenanceMode = true;
            showMaintenanceOverlay(data.message);
        } else if (!data.maintenance && isMaintenanceMode) {
            isMaintenanceMode = false;
            hideMaintenanceOverlay();
        }
    } catch (e) {
        console.error('メンテナンス状態チェックエラー:', e);
    }
}

function showMaintenanceOverlay(message) {
    // 既存のオーバーレイがあれば削除
    const existing = document.getElementById('maintenance-overlay');
    if (existing) existing.remove();
    
    const overlay = document.createElement('div');
    overlay.id = 'maintenance-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.9);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 99999;
        color: #f5deb3;
        font-size: 1.2em;
        text-align: center;
        padding: 20px;
    `;
    overlay.innerHTML = `
        <div style="font-size: 4em; margin-bottom: 20px;">🔧</div>
        <h2 style="margin-bottom: 20px;">メンテナンス中</h2>
        <p style="max-width: 400px;">${message || 'ゲームシステムはメンテナンス中です。しばらくお待ちください。'}</p>
        <p style="margin-top: 30px; font-size: 0.9em; color: #888;">メンテナンス終了後、自動的に再開します</p>
    `;
    document.body.appendChild(overlay);
}

function hideMaintenanceOverlay() {
    const overlay = document.getElementById('maintenance-overlay');
    if (overlay) {
        overlay.remove();
        // データを再読み込み
        loadData();
    }
}

// 初回チェック & 30秒ごとにチェック
document.addEventListener('DOMContentLoaded', () => {
    checkGameMaintenance();
    maintenanceCheckInterval = setInterval(checkGameMaintenance, 30000);
});

// 戦闘計算用定数（サーバーサイドと同期）
const CIV_ARMOR_MAX_REDUCTION = 0.5;    // アーマーによる最大ダメージ軽減率（50%）
const CIV_ARMOR_PERCENT_DIVISOR = 100;  // アーマー値を軽減率に変換する除数
const CIV_ADVANTAGE_DISPLAY_THRESHOLD = 0.05; // 相性表示の閾値（±5%）
const CIV_MAX_ERA_DIFFERENCE = 2;       // 攻撃可能な最大時代差（サーバーと同期）

// 資源キーから日本語名への変換マップ
const RESOURCE_KEY_TO_NAME = {
    'food': '食料', 'wood': '木材', 'stone': '石材', 'bronze': '青銅',
    'iron': '鉄', 'gold': '金', 'knowledge': '知識', 'oil': '石油',
    'crystal': 'クリスタル', 'mana': 'マナ', 'uranium': 'ウラニウム',
    'diamond': 'ダイヤモンド', 'sulfur': '硫黄', 'gems': '宝石',
    'cloth': '布', 'marble': '大理石', 'horses': '馬', 'coal': '石炭',
    'glass': 'ガラス', 'spices': '香辛料', 'herbs': '薬草',
    'medicine': '医薬品', 'steel': '鋼鉄', 'gunpowder': '火薬',
    'gunpowder_res': '火薬資源', 'electronics': '電子部品',
    'bandages': '包帯', 'rubber': 'ゴム', 'titanium': 'チタン',
    'plutonium': 'プルトニウム', 'silicon': 'シリコン',
    'rare_earth': 'レアアース', 'quantum_crystal': '量子結晶',
    'ai_core': 'AIコア', 'gene_sample': '遺伝子サンプル',
    'dark_matter': 'ダークマター', 'antimatter': '反物質'
};

// 資源キーを日本語名に変換
function getResourceName(key) {
    return RESOURCE_KEY_TO_NAME[key] || key;
}

// ③ 設定保持用のlocalStorageキー
const DEPLOYMENT_SETTINGS_KEY = 'minibird_deployment_settings';

// ③ 設定を保存
function saveDeploymentSettings(type) {
    const excludeDisposable = document.getElementById(`${type}-exclude-disposable`)?.checked || false;
    const excludeNuclear = document.getElementById(`${type}-exclude-nuclear`)?.checked || false;
    const prioritizeStealth = document.getElementById(`${type}-prioritize-stealth`)?.checked || false;
    const keepSettings = document.getElementById(`${type}-keep-settings`)?.checked || false;
    
    const settings = JSON.parse(localStorage.getItem(DEPLOYMENT_SETTINGS_KEY) || '{}');
    settings[type] = {
        excludeDisposable,
        excludeNuclear,
        prioritizeStealth,
        keepSettings
    };
    localStorage.setItem(DEPLOYMENT_SETTINGS_KEY, JSON.stringify(settings));
}

// ③ 設定を読み込み
function loadDeploymentSettings(type) {
    const settings = JSON.parse(localStorage.getItem(DEPLOYMENT_SETTINGS_KEY) || '{}');
    return settings[type] || { excludeDisposable: false, excludeNuclear: false, prioritizeStealth: false, keepSettings: false };
}

// ③ 保存された設定をチェックボックスに適用
function applyDeploymentSettings(type) {
    const settings = loadDeploymentSettings(type);
    if (!settings.keepSettings) return; // 設定保持が無効なら適用しない
    
    const excludeDisposable = document.getElementById(`${type}-exclude-disposable`);
    const excludeNuclear = document.getElementById(`${type}-exclude-nuclear`);
    const prioritizeStealth = document.getElementById(`${type}-prioritize-stealth`);
    const keepSettings = document.getElementById(`${type}-keep-settings`);
    
    if (excludeDisposable) excludeDisposable.checked = settings.excludeDisposable;
    if (excludeNuclear) excludeNuclear.checked = settings.excludeNuclear;
    if (prioritizeStealth) prioritizeStealth.checked = settings.prioritizeStealth;
    if (keepSettings) keepSettings.checked = settings.keepSettings;
}

let civData = null;
let currentTab = 'buildings'; // 現在のアクティブタブを保持
let selectedAttackTarget = null; // 攻撃対象のユーザーID
let selectedAttackTargetPower = 0; // 攻撃対象の防御力
let userTroops = []; // ユーザーの兵士データ
let deploymentLimit = { base_limit: 100, building_bonus: 0, total_limit: 100 }; // 出撃上限

// ① 兵士タブのフィルター状態を保持
let troopFilterState = {
    categoryFilter: '',
    domainFilter: '',
    stealthFilter: '',
    nuclearFilter: '',
    disposableFilter: ''
};
// ① 兵士タブのスクロール位置を保持
let troopScrollPosition = 0;

// 攻撃モーダルを開く
function openAttackModal(targetUserId, targetCivName, targetPower) {
    selectedAttackTarget = targetUserId;
    selectedAttackTargetPower = parseInt(targetPower) || 0;
    
    // モーダルを表示
    document.getElementById('attackModal').classList.add('active');
    
    // ターゲット情報を表示
    document.getElementById('attackModalTarget').innerHTML = `
        <div style="background: rgba(139, 0, 0, 0.3); padding: 12px; border-radius: 8px; margin-bottom: 15px;">
            <div style="color: #ff6b6b; font-weight: bold;">攻撃対象: ${escapeHtml(targetCivName)}</div>
            <div style="color: #888; font-size: 12px; margin-top: 5px;">防御力: ⚔️ ${targetPower}</div>
        </div>
    `;
    
    // 有利/不利表示をリセット
    updateAdvantageDisplay(0);
    
    // 兵士データを読み込んで表示
    loadAttackTroops();
}

// 攻撃モーダルを閉じる
function closeAttackModal() {
    document.getElementById('attackModal').classList.remove('active');
    selectedAttackTarget = null;
    selectedAttackTargetPower = 0;
}

// 有利/不利表示を更新
function updateAdvantageDisplay(myPower) {
    const targetPower = selectedAttackTargetPower;
    const advantageEl = document.getElementById('attackAdvantageDisplay');
    if (!advantageEl) return;
    
    if (myPower <= 0) {
        advantageEl.innerHTML = '';
        return;
    }
    
    const powerDiff = myPower - targetPower;
    const threshold = targetPower * 0.2;
    
    let advantageHtml = '';
    if (powerDiff > threshold) {
        advantageHtml = `
            <div style="background: rgba(50, 205, 50, 0.3); padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #32cd32; font-weight: bold; font-size: 14px;">✅ 有利</span>
                    <span style="color: #888; font-size: 12px;">あなたの戦力が上回っています</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 8px;">
                    <div style="text-align: center;">
                        <div style="color: #888; font-size: 11px;">出撃戦力</div>
                        <div style="color: #32cd32; font-weight: bold;">⚔️ ${myPower}</div>
                    </div>
                    <div style="align-self: center; color: #888;">VS</div>
                    <div style="text-align: center;">
                        <div style="color: #888; font-size: 11px;">敵防御力</div>
                        <div style="color: #ff6b6b; font-weight: bold;">🛡️ ${targetPower}</div>
                    </div>
                </div>
            </div>
        `;
    } else if (powerDiff < -threshold) {
        advantageHtml = `
            <div style="background: rgba(255, 100, 100, 0.3); padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #ff6b6b; font-weight: bold; font-size: 14px;">⚠️ 不利</span>
                    <span style="color: #888; font-size: 12px;">敵の戦力が上回っています</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 8px;">
                    <div style="text-align: center;">
                        <div style="color: #888; font-size: 11px;">出撃戦力</div>
                        <div style="color: #32cd32; font-weight: bold;">⚔️ ${myPower}</div>
                    </div>
                    <div style="align-self: center; color: #888;">VS</div>
                    <div style="text-align: center;">
                        <div style="color: #888; font-size: 11px;">敵防御力</div>
                        <div style="color: #ff6b6b; font-weight: bold;">🛡️ ${targetPower}</div>
                    </div>
                </div>
            </div>
        `;
    } else {
        advantageHtml = `
            <div style="background: rgba(255, 215, 0, 0.2); padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #ffd700; font-weight: bold; font-size: 14px;">⚖️ 互角</span>
                    <span style="color: #888; font-size: 12px;">戦力は拮抗しています</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 8px;">
                    <div style="text-align: center;">
                        <div style="color: #888; font-size: 11px;">出撃戦力</div>
                        <div style="color: #32cd32; font-weight: bold;">⚔️ ${myPower}</div>
                    </div>
                    <div style="align-self: center; color: #888;">VS</div>
                    <div style="text-align: center;">
                        <div style="color: #888; font-size: 11px;">敵防御力</div>
                        <div style="color: #ff6b6b; font-weight: bold;">🛡️ ${targetPower}</div>
                    </div>
                </div>
            </div>
        `;
    }
    
    advantageEl.innerHTML = advantageHtml;
}

// 攻撃用兵士を読み込む
async function loadAttackTroops() {
    const container = document.getElementById('attackTroopSelector');
    container.innerHTML = '<div class="loading">兵士を読み込み中...</div>';
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_troops'})
        });
        const data = await res.json();
        
        if (data.ok && data.user_troops && data.user_troops.filter(t => t.count > 0).length > 0) {
            userTroops = data.user_troops.filter(t => t.count > 0);
            
            // 出撃上限を保存
            if (data.deployment_limit) {
                deploymentLimit = data.deployment_limit;
            }
            
            container.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <div style="color: #ffd700; font-size: 12px;">出撃上限: <span id="attackTroopCount">0</span>/${deploymentLimit.total_limit}人</div>
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <button type="button" class="quick-invest-btn" style="font-size: 11px;" onclick="selectMaxByStrongest()">💪 強い順に選択</button>
                        <button type="button" class="quick-invest-btn" style="font-size: 11px; background: linear-gradient(135deg, #4169e1 0%, #87ceeb 100%);" onclick="selectByLargestNumber()">📊 数が多い順に選択</button>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 10px; padding: 6px; background: rgba(0,0,0,0.15); border-radius: 4px; font-size: 11px; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 3px; cursor: pointer; color: #ddd;">
                        <input type="checkbox" id="war-exclude-disposable" onchange="saveDeploymentSettings('war')" style="cursor: pointer;">
                        <span>🗑️ 使い捨てを除外</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 3px; cursor: pointer; color: #ddd;">
                        <input type="checkbox" id="war-exclude-nuclear" onchange="saveDeploymentSettings('war')" style="cursor: pointer;">
                        <span>☢️ 核ユニットを除外</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 3px; cursor: pointer; color: #ddd;">
                        <input type="checkbox" id="war-prioritize-stealth" onchange="saveDeploymentSettings('war')" style="cursor: pointer;">
                        <span>🥷 ステルスを優先</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 3px; cursor: pointer; color: #ffd700; border-left: 1px solid #555; padding-left: 10px; margin-left: 5px;">
                        <input type="checkbox" id="war-keep-settings" onchange="saveDeploymentSettings('war')" style="cursor: pointer;">
                        <span>💾 設定を保持</span>
                    </label>
                </div>
            ` + userTroops.map(troop => `
                <div class="troop-select-row">
                    <div class="troop-select-info">
                        <span class="troop-select-icon">${troop.icon}</span>
                        <span class="troop-select-name">${troop.name}${getTroopLabelsHtml(troop)}</span>
                        <div class="troop-select-stats">⚔️${troop.attack_power} 🛡️${troop.defense_power}</div>
                    </div>
                    <input type="range" class="troop-select-slider" 
                           id="attack-slider-${parseInt(troop.troop_type_id)}"
                           min="0" max="${parseInt(troop.count)}" value="0"
                           data-troop-id="${parseInt(troop.troop_type_id)}"
                           data-attack="${parseInt(troop.attack_power)}"
                           data-defense="${parseInt(troop.defense_power)}"
                           oninput="syncAttackTroopInput(${parseInt(troop.troop_type_id)}, this.value)">
                    <input type="number" class="troop-select-count" 
                           id="attack-count-${parseInt(troop.troop_type_id)}"
                           min="0" max="${parseInt(troop.count)}" value="0"
                           data-troop-id="${parseInt(troop.troop_type_id)}"
                           oninput="syncAttackTroopSlider(${parseInt(troop.troop_type_id)}, this.value)">
                    <span class="troop-select-max">/ ${parseInt(troop.count)}</span>
                </div>
            `).join('');
            
            updateAttackPowerDisplay();
            // ③ 保存された設定を適用
            setTimeout(() => applyDeploymentSettings('war'), 0);
        } else {
            container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;">兵士がいません。兵士タブで兵士を訓練してください。</p>';
        }
    } catch (e) {
        container.innerHTML = '<p style="color: #ff6b6b; text-align: center;">兵士の読み込みに失敗しました</p>';
    }
}

// フィルタ適用後の兵種を取得
function getFilteredTroops() {
    const excludeDisposable = document.getElementById('war-exclude-disposable')?.checked || false;
    const excludeNuclear = document.getElementById('war-exclude-nuclear')?.checked || false;
    const prioritizeStealth = document.getElementById('war-prioritize-stealth')?.checked || false;
    
    let filtered = [...userTroops];
    
    // フィルタを適用
    if (excludeDisposable) {
        filtered = filtered.filter(t => !isDisposableUnit(t));
    }
    if (excludeNuclear) {
        filtered = filtered.filter(t => !isNuclearUnit(t));
    }
    
    return { filtered, prioritizeStealth };
}

// 強い順に一括選択
function selectMaxByStrongest() {
    // まずすべてをリセット
    document.querySelectorAll('[id^="attack-count-"]').forEach(input => {
        input.value = 0;
        const troopId = input.dataset.troopId;
        const slider = document.getElementById(`attack-slider-${troopId}`);
        if (slider) slider.value = 0;
    });
    
    const { filtered, prioritizeStealth } = getFilteredTroops();
    
    // ステルスを優先する場合は2段階でソート
    let sortedTroops;
    if (prioritizeStealth) {
        // ステルスユニットを最優先
        const stealthTroops = filtered.filter(t => isStealthUnit(t));
        const nonStealthTroops = filtered.filter(t => !isStealthUnit(t));
        
        // それぞれを強さでソート
        const sortByPower = (a, b) => {
            const powerA = parseInt(a.attack_power) + Math.floor(parseInt(a.defense_power) / 2);
            const powerB = parseInt(b.attack_power) + Math.floor(parseInt(b.defense_power) / 2);
            return powerB - powerA;
        };
        
        sortedTroops = [...stealthTroops.sort(sortByPower), ...nonStealthTroops.sort(sortByPower)];
    } else {
        // 兵種を攻撃力+防御力/2でソート（強い順）
        sortedTroops = filtered.sort((a, b) => {
            const powerA = parseInt(a.attack_power) + Math.floor(parseInt(a.defense_power) / 2);
            const powerB = parseInt(b.attack_power) + Math.floor(parseInt(b.defense_power) / 2);
            return powerB - powerA;
        });
    }
    
    let remaining = deploymentLimit.total_limit;
    
    for (const troop of sortedTroops) {
        if (remaining <= 0) break;
        
        const troopId = troop.troop_type_id;
        const available = parseInt(troop.count);
        const toSelect = Math.min(available, remaining);
        
        const input = document.getElementById(`attack-count-${troopId}`);
        const slider = document.getElementById(`attack-slider-${troopId}`);
        
        if (input && slider) {
            input.value = toSelect;
            slider.value = toSelect;
            remaining -= toSelect;
        }
    }
    
    updateAttackPowerDisplay();
}

// 数が多い順に一括選択
function selectByLargestNumber() {
    // まずすべてをリセット
    document.querySelectorAll('[id^="attack-count-"]').forEach(input => {
        input.value = 0;
        const troopId = input.dataset.troopId;
        const slider = document.getElementById(`attack-slider-${troopId}`);
        if (slider) slider.value = 0;
    });
    
    const { filtered, prioritizeStealth } = getFilteredTroops();
    
    // ステルスを優先する場合は2段階でソート
    let sortedTroops;
    if (prioritizeStealth) {
        // ステルスユニットを最優先
        const stealthTroops = filtered.filter(t => isStealthUnit(t));
        const nonStealthTroops = filtered.filter(t => !isStealthUnit(t));
        
        // それぞれを数でソート
        const sortByCount = (a, b) => parseInt(b.count) - parseInt(a.count);
        
        sortedTroops = [...stealthTroops.sort(sortByCount), ...nonStealthTroops.sort(sortByCount)];
    } else {
        // 兵種を数の多い順でソート
        sortedTroops = filtered.sort((a, b) => parseInt(b.count) - parseInt(a.count));
    }
    
    let remaining = deploymentLimit.total_limit;
    
    for (const troop of sortedTroops) {
        if (remaining <= 0) break;
        
        const troopId = troop.troop_type_id;
        const available = parseInt(troop.count);
        const toSelect = Math.min(available, remaining);
        
        const input = document.getElementById(`attack-count-${troopId}`);
        const slider = document.getElementById(`attack-slider-${troopId}`);
        
        if (input && slider) {
            input.value = toSelect;
            slider.value = toSelect;
            remaining -= toSelect;
        }
    }
    
    updateAttackPowerDisplay();
}

// スライダーと数値入力を同期
function syncAttackTroopInput(troopId, value) {
    const countInput = document.getElementById(`attack-count-${troopId}`);
    if (countInput) {
        countInput.value = value;
    }
    updateAttackPowerDisplay();
}

function syncAttackTroopSlider(troopId, value) {
    const slider = document.getElementById(`attack-slider-${troopId}`);
    if (slider) {
        const max = parseInt(slider.max);
        let val = parseInt(value) || 0;
        val = Math.max(0, Math.min(max, val));
        slider.value = val;
        document.getElementById(`attack-count-${troopId}`).value = val;
    }
    updateAttackPowerDisplay();
}

// 攻撃パワーを計算・表示
function updateAttackPowerDisplay() {
    let totalPower = 0;
    let totalTroops = 0;
    
    document.querySelectorAll('[id^="attack-count-"]').forEach(input => {
        const count = parseInt(input.value) || 0;
        const troopId = input.dataset.troopId;
        if (count > 0 && troopId) {
            totalTroops += count;
            const slider = document.getElementById(`attack-slider-${troopId}`);
            if (slider) {
                const attack = parseInt(slider.dataset.attack) || 0;
                const defense = parseInt(slider.dataset.defense) || 0;
                totalPower += (attack + Math.floor(defense / 2)) * count;
            }
        }
    });
    
    document.getElementById('attackPowerDisplay').textContent = totalPower;
    
    // 合計兵数を更新
    const troopCountEl = document.getElementById('attackTroopCount');
    if (troopCountEl) {
        troopCountEl.textContent = totalTroops;
        // 上限超えの場合は赤くする
        if (totalTroops > deploymentLimit.total_limit) {
            troopCountEl.style.color = '#ff6b6b';
        } else {
            troopCountEl.style.color = '#32cd32';
        }
    }
    
    // 有利/不利表示を更新
    updateAdvantageDisplay(totalPower);
    
    // 出撃ボタンの有効/無効
    const overLimit = totalTroops > deploymentLimit.total_limit;
    document.getElementById('confirmAttackBtn').disabled = totalPower === 0 || overLimit;
}

// 攻撃を実行
async function confirmAttack() {
    if (!selectedAttackTarget) return;
    
    // 攻撃対象を先に保存（closeAttackModalでnullになるため）
    const targetUserId = selectedAttackTarget;
    
    // 選択した部隊を収集
    const troops = [];
    document.querySelectorAll('[id^="attack-count-"]').forEach(input => {
        const count = parseInt(input.value) || 0;
        const troopId = input.dataset.troopId;
        if (count > 0 && troopId) {
            troops.push({
                troop_type_id: parseInt(troopId),
                count: count
            });
        }
    });
    
    if (troops.length === 0) {
        showNotification('出撃部隊を選択してください', true);
        return;
    }
    
    closeAttackModal();
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'attack_with_troops',
                target_user_id: targetUserId,
                troops: troops
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            const isVictory = data.result === 'victory';
            showNotification(data.message, !isVictory);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 攻撃モーダルの外側クリックで閉じる
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('attackModal');
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeAttackModal();
        }
    });
});

// 初期データ読み込み
async function loadData() {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_data'})
        });
        civData = await res.json();
        
        if (civData.ok) {
            renderApp();
            
            // 収集された資源を通知
            if (civData.collected_resources && civData.collected_resources.length > 0) {
                const resourcesText = civData.collected_resources.map(r => 
                    `${r.icon} ${r.name}: +${Math.floor(r.amount)}`
                ).join('、');
                showNotification(`資源を収集しました: ${resourcesText}`);
            }
        } else {
            document.getElementById('app').innerHTML = `<div class="loading">エラー: ${civData.error}</div>`;
        }
    } catch (e) {
        console.error(e);
        document.getElementById('app').innerHTML = '<div class="loading">読み込みエラーが発生しました</div>';
    }
}

// メイン描画
function renderApp() {
    const civ = civData.civilization;
    const era = civData.current_era;
    const resources = civData.resources;
    const buildings = civData.buildings;
    const availableBuildings = civData.available_buildings;
    const balance = civData.balance;
    
    // 次の時代を取得
    const nextEra = civData.eras.find(e => e.era_order > era.era_order);
    
    document.getElementById('app').innerHTML = `
        <!-- ヘッダー -->
        <div class="civ-header">
            <div class="civ-title">
                <span class="civ-name" onclick="renameCiv()">${escapeHtml(civ.civilization_name)} ✏️</span>
                <span class="civ-era">${era.icon} ${era.name}</span>
            </div>
            <div class="civ-stats">
                <div class="stat-box">
                    <div class="stat-value">${civ.population}/${civ.max_population}</div>
                    <div class="stat-label">👥 人口</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${civ.research_points}</div>
                    <div class="stat-label">📚 研究ポイント</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${civ.military_power || 0}</div>
                    <div class="stat-label">⚔️ 軍事力</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${Number(balance.coins).toLocaleString()}</div>
                    <div class="stat-label">🪙 コイン</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${Number(balance.crystals || 0).toLocaleString()}</div>
                    <div class="stat-label">💎 クリスタル</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${Number(balance.diamonds || 0).toLocaleString()}</div>
                    <div class="stat-label">💠 ダイヤモンド</div>
                </div>
            </div>
        </div>
        
        <!-- 資源バー -->
        <div class="resources-bar">
            ${resources.filter(r => r.unlocked).map(r => `
                <div class="resource-item">
                    <span class="resource-icon">${r.icon}</span>
                    <span class="resource-amount">${Math.floor(r.amount)}</span>
                    <span style="font-size: 12px; color: #888;">${r.name}</span>
                </div>
            `).join('')}
        </div>
        
        <!-- 投資セクション -->
        <div class="invest-section">
            <h3>💰 コインを投資</h3>
            <p style="color: #c0a080; margin-bottom: 15px;">コインを投資して研究ポイントと資源を獲得！（10コイン = 1研究ポイント + 基本資源ボーナス）</p>
            <div class="invest-form">
                <input type="number" id="investAmount" class="invest-input" value="1000" min="100" step="100">
                <button class="invest-btn" onclick="investCoins()">投資する</button>
                <div class="quick-invest-btns">
                    <button class="quick-invest-btn" onclick="setInvestAmount(500)">500</button>
                    <button class="quick-invest-btn" onclick="setInvestAmount(1000)">1000</button>
                    <button class="quick-invest-btn" onclick="setInvestAmount(5000)">5000</button>
                    <button class="quick-invest-btn" onclick="setInvestAmount(10000)">10000</button>
                </div>
            </div>
        </div>
        
        <!-- 時代進化 -->
        ${nextEra ? `
        <div class="era-progress">
            <h3>🌟 次の時代: ${nextEra.icon} ${nextEra.name}</h3>
            <p style="color: #c0a080; margin-bottom: 15px;">${nextEra.description}</p>
            <div class="era-requirements">
                <div class="era-req">
                    <div class="req-label">必要人口</div>
                    <div class="req-value ${civ.population >= nextEra.unlock_population ? 'met' : 'unmet'}">
                        ${civ.population} / ${nextEra.unlock_population}
                    </div>
                </div>
                <div class="era-req">
                    <div class="req-label">必要研究ポイント</div>
                    <div class="req-value ${civ.research_points >= nextEra.unlock_research_points ? 'met' : 'unmet'}">
                        ${civ.research_points} / ${nextEra.unlock_research_points}
                    </div>
                </div>
            </div>
            <button class="advance-era-btn" onclick="advanceEra()" 
                ${civ.population >= nextEra.unlock_population && civ.research_points >= nextEra.unlock_research_points ? '' : 'disabled'}>
                ${nextEra.name}に進化する
            </button>
        </div>
        ` : '<div class="era-progress"><h3>🏆 最高の時代に到達しました！</h3></div>'}
        
        <!-- タブ -->
        <div class="tabs">
            <button class="tab-btn ${currentTab === 'buildings' ? 'active' : ''}" data-tab="buildings">🏠 建物</button>
            <button class="tab-btn ${currentTab === 'protection' ? 'active' : ''}" data-tab="protection" style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.3) 0%, rgba(184, 134, 11, 0.3) 100%);">🛡️ 保護設定</button>
            <button class="tab-btn ${currentTab === 'research' ? 'active' : ''}" data-tab="research">📚 研究</button>
            <button class="tab-btn ${currentTab === 'market' ? 'active' : ''}" data-tab="market">🏪 市場</button>
            <button class="tab-btn ${currentTab === 'troops' ? 'active' : ''}" data-tab="troops">🎖️ 兵士<span id="wounded-badge" class="tab-badge" style="display:none;"></span></button>
            <button class="tab-btn ${currentTab === 'heroes' ? 'active' : ''}" data-tab="heroes" style="background: linear-gradient(135deg, rgba(255, 107, 107, 0.3) 0%, rgba(255, 193, 7, 0.3) 100%);">🦸 ヒーロー</button>
            <button class="tab-btn ${currentTab === 'alliance' ? 'active' : ''}" data-tab="alliance">🤝 同盟</button>
            <button class="tab-btn ${currentTab === 'war' ? 'active' : ''}" data-tab="war">⚔️ 戦争</button>
            <button class="tab-btn ${currentTab === 'conquest' ? 'active' : ''}" data-tab="conquest">🏰 占領戦</button>
            <button class="tab-btn ${currentTab === 'mail' ? 'active' : ''}" data-tab="mail" style="background: linear-gradient(135deg, rgba(100, 149, 237, 0.3) 0%, rgba(65, 105, 225, 0.3) 100%);">📬 メール<span id="mail-badge" class="tab-badge" style="display:none;"></span></button>
            <button class="tab-btn ${currentTab === 'monster' ? 'active' : ''}" data-tab="monster">🐉 モンスター</button>
            <button class="tab-btn ${currentTab === 'events' ? 'active' : ''}" data-tab="events" style="background: linear-gradient(135deg, rgba(255, 105, 180, 0.3) 0%, rgba(255, 20, 147, 0.3) 100%);">🎉 イベント<span id="events-badge" class="tab-badge" style="display:none;"></span></button>
            <button class="tab-btn ${currentTab === 'leaderboard' ? 'active' : ''}" data-tab="leaderboard" style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 165, 0, 0.3) 100%);">🏆 リーダーボード</button>
            <button class="tab-btn ${currentTab === 'quests' ? 'active' : ''}" data-tab="quests" style="background: linear-gradient(135deg, rgba(72, 187, 120, 0.3) 0%, rgba(56, 161, 105, 0.3) 100%);">📋 クエスト<span id="quests-badge" class="tab-badge" style="display:none;"></span></button>
            <button class="tab-btn ${currentTab === 'shop' ? 'active' : ''}" data-tab="shop">💠 VIPショップ</button>
            <button class="tab-btn ${currentTab === 'tutorial' ? 'active' : ''}" data-tab="tutorial" style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 140, 0, 0.3) 100%);">📜 チュートリアル</button>
        </div>
        
        <!-- 建物タブ -->
        <div class="tab-content ${currentTab === 'buildings' ? 'active' : ''}" id="tab-buildings">
            <h3 style="color: #ffd700; margin-bottom: 20px;">🏗️ 建設可能な建物</h3>
            <div class="buildings-grid">
                ${renderBuildingsGrid(availableBuildings, buildings, resources)}
            </div>
        </div>
        
        <!-- 研究タブ -->
        <div class="tab-content ${currentTab === 'research' ? 'active' : ''}" id="tab-research">
            <h3 style="color: #87ceeb; margin-bottom: 20px;">🔬 研究ツリー</h3>
            <div class="research-tree">
                ${renderResearchTree()}
            </div>
        </div>
        
        <!-- 兵士タブ -->
        <div class="tab-content ${currentTab === 'troops' ? 'active' : ''}" id="tab-troops">
            <!-- 訓練キュー -->
            <div class="war-section" style="background: linear-gradient(135deg, rgba(70, 130, 180, 0.5) 0%, rgba(25, 25, 112, 0.5) 100%); border-color: #4682b4; margin-bottom: 20px;">
                <h3 style="color: #87ceeb;">⏳ 訓練キュー</h3>
                <div id="trainingQueueList">
                    <div class="loading">訓練キューを読み込み中...</div>
                </div>
            </div>
            
            <!-- 負傷兵 -->
            <div class="war-section" style="background: linear-gradient(135deg, rgba(220, 20, 60, 0.3) 0%, rgba(139, 0, 0, 0.3) 100%); border-color: #dc143c; margin-bottom: 20px;">
                <h3 style="color: #ff6b6b;">🏥 負傷兵</h3>
                <p style="color: #c0a080; margin-bottom: 10px;">病院または野戦病院を建設して負傷兵を治療しましょう</p>
                <div id="woundedTroopsList">
                    <div class="loading">負傷兵を読み込み中...</div>
                </div>
                <div id="healingQueueList" style="margin-top: 15px;"></div>
            </div>
            
            <!-- 防御設定 -->
            <div class="war-section" style="background: linear-gradient(135deg, rgba(50, 205, 50, 0.3) 0%, rgba(0, 100, 0, 0.3) 100%); border-color: #32cd32; margin-bottom: 20px;">
                <h3 style="color: #90ee90;">🛡️ 防御部隊設定</h3>
                <p style="color: #c0a080; margin-bottom: 10px;">攻撃された時に自動的に防御に使用される兵士を設定します</p>
                <div id="defenseSettingsList">
                    <div class="loading">防御設定を読み込み中...</div>
                </div>
            </div>
            
            <!-- 兵士訓練 -->
            <div class="war-section" style="background: linear-gradient(135deg, rgba(139, 69, 19, 0.5) 0%, rgba(50, 30, 10, 0.5) 100%); border-color: #8b4513;">
                <h3 style="color: #ffd700;">🎖️ 兵士を訓練</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">兵舎や軍事施設を建設すると、より多くの兵士を訓練できます。訓練には時間がかかります。</p>
                
                <!-- ① 絞り込みフィルター -->
                <div id="troop-filter-section" style="margin-bottom: 20px; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 10px; border: 1px solid rgba(139, 69, 19, 0.5);">
                    <h4 style="color: #ffd700; margin: 0 0 12px 0; font-size: 14px;">🔍 絞り込みフィルター</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                        <!-- 兵種相性フィルター -->
                        <div style="flex: 1; min-width: 180px;">
                            <label style="display: block; color: #c0a080; font-size: 12px; margin-bottom: 5px;">⚔️ 兵種相性</label>
                            <select id="filter-troop-category" onchange="applyTroopFilters()" style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #8b4513; border-radius: 6px; color: #f5deb3; font-size: 13px;">
                                <option value="">すべて</option>
                                <option value="infantry">🗡️ 歩兵</option>
                                <option value="cavalry">🐴 騎兵</option>
                                <option value="ranged">🏹 遠距離</option>
                                <option value="siege">💣 攻城</option>
                            </select>
                        </div>
                        <!-- カテゴリフィルター（陸・海・空） -->
                        <div style="flex: 1; min-width: 180px;">
                            <label style="display: block; color: #c0a080; font-size: 12px; margin-bottom: 5px;">🌍 領域カテゴリ</label>
                            <select id="filter-domain-category" onchange="applyTroopFilters()" style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #8b4513; border-radius: 6px; color: #f5deb3; font-size: 13px;">
                                <option value="">すべて</option>
                                <option value="land">🏔️ 陸</option>
                                <option value="sea">🌊 海</option>
                                <option value="air">✈️ 空</option>
                            </select>
                        </div>
                        <!-- ステルスフィルター -->
                        <div style="flex: 1; min-width: 180px;">
                            <label style="display: block; color: #c0a080; font-size: 12px; margin-bottom: 5px;">👻 ステルス</label>
                            <select id="filter-stealth" onchange="applyTroopFilters()" style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #8b4513; border-radius: 6px; color: #f5deb3; font-size: 13px;">
                                <option value="">すべて</option>
                                <option value="yes">ステルスのみ</option>
                                <option value="no">ステルス以外</option>
                            </select>
                        </div>
                        <!-- 核フィルター -->
                        <div style="flex: 1; min-width: 180px;">
                            <label style="display: block; color: #c0a080; font-size: 12px; margin-bottom: 5px;">☢️ 核</label>
                            <select id="filter-nuclear" onchange="applyTroopFilters()" style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #8b4513; border-radius: 6px; color: #f5deb3; font-size: 13px;">
                                <option value="">すべて</option>
                                <option value="yes">核のみ</option>
                                <option value="no">核以外</option>
                            </select>
                        </div>
                        <!-- 使い捨てフィルター -->
                        <div style="flex: 1; min-width: 180px;">
                            <label style="display: block; color: #c0a080; font-size: 12px; margin-bottom: 5px;">💀 使い捨て</label>
                            <select id="filter-disposable" onchange="applyTroopFilters()" style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #8b4513; border-radius: 6px; color: #f5deb3; font-size: 13px;">
                                <option value="">すべて</option>
                                <option value="yes">使い捨てのみ</option>
                                <option value="no">使い捨て以外</option>
                            </select>
                        </div>
                    </div>
                    <button onclick="resetTroopFilters()" style="margin-top: 10px; padding: 8px 16px; background: rgba(139, 69, 19, 0.5); border: 1px solid #8b4513; border-radius: 6px; color: #f5deb3; cursor: pointer; font-size: 12px;">
                        🔄 フィルターをリセット
                    </button>
                </div>
                
                <div class="targets-list" id="troopsList">
                    <div class="loading">兵種を読み込み中...</div>
                </div>
            </div>
        </div>
        
        <!-- 占領戦タブ -->
        <div class="tab-content ${currentTab === 'conquest' ? 'active' : ''}" id="tab-conquest">
            <div class="war-section" style="background: linear-gradient(135deg, rgba(153, 50, 204, 0.5) 0%, rgba(75, 0, 130, 0.5) 100%); border-color: #9932cc;">
                <h3 style="color: #da70d6;">🏰 占領戦</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">
                    占領戦は毎週月曜日にリセットされるシーズン制のコンテンツです。<br>
                    マップ上の城を攻め落とし、中央の神城⛩️を目指しましょう！
                </p>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
                    <div class="stat-box" style="background: rgba(0,0,0,0.3);">
                        <div style="font-size: 32px;">🏰</div>
                        <div style="color: #ffd700; font-size: 14px;">城を占領</div>
                    </div>
                    <div class="stat-box" style="background: rgba(0,0,0,0.3);">
                        <div style="font-size: 32px;">🛡️</div>
                        <div style="color: #ffd700; font-size: 14px;">防御部隊配置</div>
                    </div>
                    <div class="stat-box" style="background: rgba(0,0,0,0.3);">
                        <div style="font-size: 32px;">⛩️</div>
                        <div style="color: #ffd700; font-size: 14px;">神城を奪取</div>
                    </div>
                </div>
                <a href="./conquest.php" class="invest-btn" style="display: inline-block; text-decoration: none; padding: 15px 30px; font-size: 18px; background: linear-gradient(135deg, #9932cc 0%, #da70d6 100%);">
                    ⚔️ 占領戦に参加する
                </a>
            </div>
        </div>
        
        <!-- メールタブ -->
        <div class="tab-content ${currentTab === 'mail' ? 'active' : ''}" id="tab-mail">
            <div class="mail-section" style="background: linear-gradient(135deg, rgba(100, 149, 237, 0.3) 0%, rgba(65, 105, 225, 0.3) 100%); border: 2px solid #6495ed; border-radius: 16px; padding: 25px; margin-bottom: 25px;">
                <h3 style="color: #87ceeb; margin-bottom: 20px;">📬 メールボックス</h3>
                
                <!-- メールフィルター -->
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;" id="mail-filter-container">
                    <button class="mail-filter-btn ${mailCurrentFilter === 'all' ? 'active' : ''}" data-filter="all" style="padding: 8px 16px; border: 1px solid #6495ed; background: ${mailCurrentFilter === 'all' ? 'rgba(100, 149, 237, 0.3)' : 'rgba(0,0,0,0.3)'}; color: #87ceeb; border-radius: 8px; cursor: pointer;">📋 すべて</button>
                    <button class="mail-filter-btn ${mailCurrentFilter === 'info' ? 'active' : ''}" data-filter="info" style="padding: 8px 16px; border: 1px solid #6495ed; background: ${mailCurrentFilter === 'info' ? 'rgba(100, 149, 237, 0.3)' : 'rgba(0,0,0,0.3)'}; color: #87ceeb; border-radius: 8px; cursor: pointer;">📢 情報</button>
                    <button class="mail-filter-btn ${mailCurrentFilter === 'war' ? 'active' : ''}" data-filter="war" style="padding: 8px 16px; border: 1px solid #6495ed; background: ${mailCurrentFilter === 'war' ? 'rgba(100, 149, 237, 0.3)' : 'rgba(0,0,0,0.3)'}; color: #87ceeb; border-radius: 8px; cursor: pointer;">⚔️ 戦争</button>
                    <button class="mail-filter-btn ${mailCurrentFilter === 'conquest' ? 'active' : ''}" data-filter="conquest" style="padding: 8px 16px; border: 1px solid #6495ed; background: ${mailCurrentFilter === 'conquest' ? 'rgba(100, 149, 237, 0.3)' : 'rgba(0,0,0,0.3)'}; color: #87ceeb; border-radius: 8px; cursor: pointer;">🏰 占領戦</button>
                    <button class="mail-filter-btn ${mailCurrentFilter === 'reconnaissance' ? 'active' : ''}" data-filter="reconnaissance" style="padding: 8px 16px; border: 1px solid #6495ed; background: ${mailCurrentFilter === 'reconnaissance' ? 'rgba(100, 149, 237, 0.3)' : 'rgba(0,0,0,0.3)'}; color: #87ceeb; border-radius: 8px; cursor: pointer;">🔭 偵察</button>
                </div>
                
                <!-- メールリスト -->
                <div id="mail-list" style="max-height: 500px; overflow-y: auto;">
                    <div style="text-align: center; padding: 40px; color: #888;">
                        📭 メールを読み込み中...
                    </div>
                </div>
                
                <!-- ページネーション -->
                <div id="mail-pagination" style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;"></div>
            </div>
            
            <!-- 偵察セクション -->
            <div class="reconnaissance-section" style="background: linear-gradient(135deg, rgba(50, 205, 50, 0.3) 0%, rgba(34, 139, 34, 0.3) 100%); border: 2px solid #32cd32; border-radius: 16px; padding: 25px;">
                <h3 style="color: #90ee90; margin-bottom: 20px;">🔭 偵察システム</h3>
                <p style="color: #c0a080; margin-bottom: 15px;">
                    敵の防御部隊を事前に偵察できます。偵察結果はメールに送信されます。
                </p>
                
                <!-- 偵察レート制限表示 -->
                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
                    <!-- 戦争偵察 -->
                    <div id="warReconRateLimitSection" style="flex: 1; min-width: 280px; background: rgba(0,0,0,0.3); border: 2px solid #ffa500; padding: 15px; border-radius: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <span style="font-size: 24px;">⚔️</span>
                            <div>
                                <div style="color: #ffa500; font-weight: bold;">戦争偵察</div>
                                <div style="color: #888; font-size: 11px;">1時間に5回まで</div>
                            </div>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                                <span style="color: #c0a080; font-size: 12px;">残り偵察可能回数</span>
                                <span id="warReconRemaining" style="color: #ffd700; font-weight: bold; font-size: 12px;">-- / 5</span>
                            </div>
                            <div style="background: rgba(0,0,0,0.3); border-radius: 6px; height: 12px; overflow: hidden;">
                                <div id="warReconProgressBar" style="background: linear-gradient(90deg, #32cd32 0%, #228b22 100%); height: 100%; width: 100%; transition: width 0.3s;"></div>
                            </div>
                        </div>
                        <div id="warReconMessage" style="color: #888; font-size: 11px; text-align: center;">読み込み中...</div>
                    </div>
                    
                    <!-- 占領戦偵察 -->
                    <div id="conquestReconRateLimitSection" style="flex: 1; min-width: 280px; background: rgba(0,0,0,0.3); border: 2px solid #da70d6; padding: 15px; border-radius: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <span style="font-size: 24px;">🏰</span>
                            <div>
                                <div style="color: #da70d6; font-weight: bold;">占領戦偵察</div>
                                <div style="color: #888; font-size: 11px;">1時間に15回まで</div>
                            </div>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                                <span style="color: #c0a080; font-size: 12px;">残り偵察可能回数</span>
                                <span id="conquestReconRemaining" style="color: #ffd700; font-weight: bold; font-size: 12px;">-- / 15</span>
                            </div>
                            <div style="background: rgba(0,0,0,0.3); border-radius: 6px; height: 12px; overflow: hidden;">
                                <div id="conquestReconProgressBar" style="background: linear-gradient(90deg, #32cd32 0%, #228b22 100%); height: 100%; width: 100%; transition: width 0.3s;"></div>
                            </div>
                        </div>
                        <div id="conquestReconMessage" style="color: #888; font-size: 11px; text-align: center;">読み込み中...</div>
                    </div>
                </div>
                
                <div style="margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 8px;">
                    <p style="color: #ffcc00; font-size: 12px; margin: 0;">
                        ⚠️ 偵察は30%の確率で失敗します。ステルス部隊の数値には25%〜175%の誤差が生じます。
                    </p>
                </div>
            </div>
        </div>
        
        <!-- モンスタータブ -->
        <div class="tab-content ${currentTab === 'monster' ? 'active' : ''}" id="tab-monster">
            <div class="war-section" style="background: linear-gradient(135deg, rgba(139, 0, 0, 0.5) 0%, rgba(75, 0, 130, 0.5) 100%); border-color: #dc143c;">
                <h3 style="color: #ff6b6b;">🐉 モンスター討伐</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">
                    放浪モンスターを倒してコイン、クリスタル、ダイヤモンド、資源、兵士を獲得しよう！<br>
                    ワールドボスはみんなで協力して討伐する強敵です。ダメージランキング上位者には豪華報酬！
                </p>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
                    <div class="stat-box" style="background: rgba(0,0,0,0.3);">
                        <div style="font-size: 32px;">🐺</div>
                        <div style="color: #ffa500; font-size: 14px;">放浪モンスター</div>
                        <div style="color: #888; font-size: 11px;">レベルに応じた敵</div>
                    </div>
                    <div class="stat-box" style="background: rgba(0,0,0,0.3);">
                        <div style="font-size: 32px;">🐉</div>
                        <div style="color: #dc143c; font-size: 14px;">ワールドボス</div>
                        <div style="color: #888; font-size: 11px;">みんなで討伐</div>
                    </div>
                    <div class="stat-box" style="background: rgba(0,0,0,0.3);">
                        <div style="font-size: 32px;">💎</div>
                        <div style="color: #ffd700; font-size: 14px;">豪華報酬</div>
                        <div style="color: #888; font-size: 11px;">コイン・資源・兵士</div>
                    </div>
                </div>
                <a href="./monster_battle.php" class="invest-btn" style="display: inline-block; text-decoration: none; padding: 15px 30px; font-size: 18px; background: linear-gradient(135deg, #dc143c 0%, #ff6b6b 100%);">
                    ⚔️ モンスター討伐に挑戦
                </a>
            </div>
        </div>
        
        <!-- 戦争タブ -->
        <div class="tab-content ${currentTab === 'war' ? 'active' : ''}" id="tab-war">
            <div class="war-section">
                <h3>⚔️ 他の文明を攻撃</h3>
                <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <h4 style="color: #ff6b6b; margin: 0 0 10px 0;">あなたの軍事力</h4>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div>
                            <span style="color: #888;">🏰 建物:</span>
                            <span style="color: #ffd700; font-weight: bold;" id="myBuildingPower">${civ.building_power || 0}</span>
                        </div>
                        <div>
                            <span style="color: #888;">🎖️ 兵士:</span>
                            <span style="color: #ffd700; font-weight: bold;" id="myTroopPower">${civ.troop_power || 0}</span>
                        </div>
                        <div>
                            <span style="color: #888;">⚔️ 装備:</span>
                            <span style="color: #9932cc; font-weight: bold;" id="myEquipmentPower">${civData.military_power_breakdown?.equipment_power || 0}</span>
                        </div>
                        <div>
                            <span style="color: #888;">🛡️ アーマー:</span>
                            <span style="color: #87ceeb; font-weight: bold;" id="myArmor">${Math.floor(civData.military_power_breakdown?.equipment_buffs?.armor || 0)}</span>
                        </div>
                        <div>
                            <span style="color: #888;">❤️ 体力:</span>
                            <span style="color: #ff6b6b; font-weight: bold;" id="myHealth">${Math.floor(civData.military_power_breakdown?.equipment_buffs?.health || 0)}</span>
                        </div>
                        <div>
                            <span style="color: #888;">⚔️ 合計:</span>
                            <span style="color: #ff6b6b; font-weight: bold; font-size: 1.2em;" id="myTotalPower">${civ.military_power || 0}</span>
                        </div>
                    </div>
                    <p style="color: #888; font-size: 11px; margin-top: 10px;">💡 装備のバフ（攻撃力・体力・アーマー）が戦闘力に影響します。アーマーは敵の攻撃を軽減します。</p>
                </div>
                
                <!-- 戦争レート制限表示 -->
                <div id="warRateLimitSection" style="background: rgba(139, 0, 0, 0.2); border: 2px solid #8b0000; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: none;">
                    <h4 style="color: #ff6b6b; margin: 0 0 10px 0;">⏱️ 戦争レート制限（1時間に3回まで）</h4>
                    <div style="margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: #c0a080;">残り攻撃可能回数</span>
                            <span style="color: #ffd700; font-weight: bold;" id="remainingAttacks">3 / 3</span>
                        </div>
                        <div style="background: rgba(0,0,0,0.3); height: 20px; border-radius: 10px; overflow: hidden;">
                            <div id="rateLimitProgressBar" style="background: linear-gradient(90deg, #32cd32 0%, #ffd700 50%, #ff6b6b 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    <div id="rateLimitMessage" style="color: #888; font-size: 12px; text-align: center;"></div>
                </div>
                
                <p style="color: #c0a080; margin-bottom: 20px;">軍事施設を建設して軍事力を上げ、他の文明から資源を略奪しましょう！</p>
                <div class="targets-list" id="targetsList">
                    <div class="loading">攻撃対象を読み込み中...</div>
                </div>
            </div>
            
            <!-- 戦争ログセクション -->
            <div class="war-section" style="margin-top: 20px;">
                <h3>📜 戦争ログ</h3>
                <div id="warLogsList" style="max-height: 400px; overflow-y: auto;">
                    <div class="loading">戦争ログを読み込み中...</div>
                </div>
            </div>
        </div>
        
        <!-- イベントタブ -->
        <div class="tab-content ${currentTab === 'events' ? 'active' : ''}" id="tab-events">
            <div class="war-section" style="background: linear-gradient(135deg, rgba(255, 105, 180, 0.3) 0%, rgba(255, 20, 147, 0.3) 100%); border-color: #ff69b4;">
                <h3 style="color: #ff69b4;">🎉 イベント</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">
                    様々なイベントに参加して限定報酬をゲットしよう！
                </p>
                
                <!-- イベントサブタブ -->
                <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                    <button class="stat-box event-subtab-btn active" data-event-type="daily" style="cursor: pointer; flex: 1; min-width: 100px;">
                        <div style="font-size: 24px;">📅</div>
                        <div style="color: #ffa500; font-size: 14px;">デイリー</div>
                    </button>
                    <button class="stat-box event-subtab-btn" data-event-type="special" style="cursor: pointer; flex: 1; min-width: 100px;">
                        <div style="font-size: 24px;">🎊</div>
                        <div style="color: #ff69b4; font-size: 14px;">スペシャル</div>
                    </button>
                    <button class="stat-box event-subtab-btn" data-event-type="hero" style="cursor: pointer; flex: 1; min-width: 100px;">
                        <div style="font-size: 24px;">🦸</div>
                        <div style="color: #9932cc; font-size: 14px;">ヒーロー</div>
                    </button>
                </div>
                
                <!-- イベントコンテンツ -->
                <div id="eventContentArea">
                    <div class="loading">イベントを読み込み中...</div>
                </div>
            </div>
        </div>
        
        <!-- リーダーボードタブ -->
        <div class="tab-content ${currentTab === 'leaderboard' ? 'active' : ''}" id="tab-leaderboard">
            <div class="war-section" style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 165, 0, 0.3) 100%); border-color: #ffd700;">
                <h3 style="color: #ffd700;">🏆 リーダーボード</h3>
                <p style="color: #c0a080; margin-bottom: 15px;">各カテゴリのトップランキングを確認しましょう</p>
                
                <!-- メインランキングカテゴリボタン -->
                <div style="margin-bottom: 15px;">
                    <div style="color: #c0a080; margin-bottom: 8px; font-size: 14px;">📊 メインランキング</div>
                    <div id="main-ranking-buttons" style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <button class="ranking-btn active" data-ranking="population" style="padding: 8px 12px; background: rgba(255, 215, 0, 0.3); border: 2px solid #ffd700; border-radius: 6px; color: #f5deb3; cursor: pointer; font-size: 12px; transition: all 0.2s;">👥 人口</button>
                        <button class="ranking-btn" data-ranking="era" style="padding: 8px 12px; background: rgba(0,0,0,0.3); border: 2px solid #666; border-radius: 6px; color: #888; cursor: pointer; font-size: 12px; transition: all 0.2s;">🏛️ 時代</button>
                        <button class="ranking-btn" data-ranking="military_power" style="padding: 8px 12px; background: rgba(0,0,0,0.3); border: 2px solid #666; border-radius: 6px; color: #888; cursor: pointer; font-size: 12px; transition: all 0.2s;">⚔️ 軍事力</button>
                        <button class="ranking-btn" data-ranking="total_soldiers" style="padding: 8px 12px; background: rgba(0,0,0,0.3); border: 2px solid #666; border-radius: 6px; color: #888; cursor: pointer; font-size: 12px; transition: all 0.2s;">🎖️ 総兵士数</button>
                        <button class="ranking-btn" data-ranking="total_buildings" style="padding: 8px 12px; background: rgba(0,0,0,0.3); border: 2px solid #666; border-radius: 6px; color: #888; cursor: pointer; font-size: 12px; transition: all 0.2s;">🏠 総建築物数</button>
                        <button class="ranking-btn" data-ranking="battle_wins" style="padding: 8px 12px; background: rgba(0,0,0,0.3); border: 2px solid #666; border-radius: 6px; color: #888; cursor: pointer; font-size: 12px; transition: all 0.2s;">🏅 戦闘勝利数</button>
                        <button class="ranking-btn" data-ranking="battle_losses" style="padding: 8px 12px; background: rgba(0,0,0,0.3); border: 2px solid #666; border-radius: 6px; color: #888; cursor: pointer; font-size: 12px; transition: all 0.2s;">💀 戦闘敗北数</button>
                        <button class="ranking-btn" data-ranking="conquest_wins" style="padding: 8px 12px; background: rgba(0,0,0,0.3); border: 2px solid #666; border-radius: 6px; color: #888; cursor: pointer; font-size: 12px; transition: all 0.2s;">🏆 占領戦優勝</button>
                        <button class="ranking-btn" data-ranking="castle_captures" style="padding: 8px 12px; background: rgba(0,0,0,0.3); border: 2px solid #666; border-radius: 6px; color: #888; cursor: pointer; font-size: 12px; transition: all 0.2s;">🏰 拠点占領</button>
                    </div>
                </div>
                
                <!-- 資源別ランキングボタン -->
                <div id="resource-ranking-section" style="margin-bottom: 20px;">
                    <div style="color: #c0a080; margin-bottom: 8px; font-size: 14px;">📦 資源別ランキング</div>
                    <div id="resource-ranking-buttons" style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <!-- 資源タイプは動的に読み込み -->
                        <span style="color: #666; font-size: 12px;">読み込み中...</span>
                    </div>
                </div>
                
                <!-- 自分の順位 -->
                <div id="my-rank-info" style="background: rgba(255, 215, 0, 0.2); padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                    <div style="color: #ffd700; font-size: 18px; font-weight: bold;">あなたの順位</div>
                    <div id="my-rank-display" style="color: #fff; font-size: 24px; margin-top: 5px;">読み込み中...</div>
                </div>
                
                <!-- ランキングリスト -->
                <div id="leaderboard-list" style="max-height: 500px; overflow-y: auto;">
                    <div class="loading">ランキングを読み込み中...</div>
                </div>
            </div>
        </div>
        
        <!-- VIPショップタブ -->
        <div class="tab-content ${currentTab === 'shop' ? 'active' : ''}" id="tab-shop">
            <div class="invest-section" style="background: linear-gradient(135deg, rgba(153, 50, 204, 0.5) 0%, rgba(75, 0, 130, 0.5) 100%); border-color: #9932cc;">
                <h3 style="color: #da70d6;">💠 VIPショップ（ダイヤモンド専用）</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">ダイヤモンドを使って特別なブーストやアイテムを購入できます</p>
                <div class="buildings-grid">
                    <div class="building-card" style="border-color: #9932cc;">
                        <div class="building-header">
                            <span class="building-icon">⚡</span>
                            <span class="building-name">資源生産2倍</span>
                        </div>
                        <div class="building-desc">24時間、すべての資源生産量が2倍になります</div>
                        <div class="building-cost">💠 5 ダイヤモンド</div>
                        <button class="build-btn" onclick="buyVipBoost('production_2x')" style="background: linear-gradient(135deg, #9932cc 0%, #da70d6 100%);">
                            購入する
                        </button>
                    </div>
                    <div class="building-card" style="border-color: #9932cc;">
                        <div class="building-header">
                            <span class="building-icon">📚</span>
                            <span class="building-name">研究速度2倍</span>
                        </div>
                        <div class="building-desc">12時間、研究速度が2倍になります</div>
                        <div class="building-cost">💠 3 ダイヤモンド</div>
                        <button class="build-btn" onclick="buyVipBoost('research_speed')" style="background: linear-gradient(135deg, #9932cc 0%, #da70d6 100%);">
                            購入する
                        </button>
                    </div>
                    <div class="building-card" style="border-color: #9932cc;">
                        <div class="building-header">
                            <span class="building-icon">🏗️</span>
                            <span class="building-name">建設速度2倍</span>
                        </div>
                        <div class="building-desc">12時間、建設速度が2倍になります</div>
                        <div class="building-cost">💠 3 ダイヤモンド</div>
                        <button class="build-btn" onclick="buyVipBoost('build_speed')" style="background: linear-gradient(135deg, #9932cc 0%, #da70d6 100%);">
                            購入する
                        </button>
                    </div>
                    <div class="building-card" style="border-color: #9932cc;">
                        <div class="building-header">
                            <span class="building-icon">📦</span>
                            <span class="building-name">資源パック</span>
                        </div>
                        <div class="building-desc">食料、木材、石材を各1000獲得します</div>
                        <div class="building-cost">💠 10 ダイヤモンド</div>
                        <button class="build-btn" onclick="buyVipBoost('resource_pack')" style="background: linear-gradient(135deg, #9932cc 0%, #da70d6 100%);">
                            購入する
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 同盟タブ -->
        <div class="tab-content ${currentTab === 'alliance' ? 'active' : ''}" id="tab-alliance">
            <div class="invest-section" style="background: linear-gradient(135deg, rgba(70, 130, 180, 0.5) 0%, rgba(25, 25, 112, 0.5) 100%); border-color: #4682b4;">
                <h3 style="color: #87ceeb;">🤝 同盟システム</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">同盟を結ぶと、互いに攻撃できなくなり、兵士や資源を援助できるようになります。</p>
                
                <!-- 同盟申請 -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: #ffd700; margin-bottom: 10px;">📝 同盟を申請</h4>
                    <div id="allianceRequestSection">
                        <div class="loading">読み込み中...</div>
                    </div>
                </div>
                
                <!-- 受信した申請 -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: #ffd700; margin-bottom: 10px;">📩 受信した同盟申請</h4>
                    <div id="pendingAlliancesReceived">
                        <div class="loading">読み込み中...</div>
                    </div>
                </div>
                
                <!-- 送信した申請 -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: #ffd700; margin-bottom: 10px;">📤 送信した同盟申請</h4>
                    <div id="pendingAlliancesSent">
                        <div class="loading">読み込み中...</div>
                    </div>
                </div>
                
                <!-- 締結済み同盟 -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: #ffd700; margin-bottom: 10px;">🤝 同盟国</h4>
                    <div id="activeAlliancesList">
                        <div class="loading">読み込み中...</div>
                    </div>
                </div>
            </div>
            
            <!-- 援助機能 -->
            <div class="invest-section" style="background: linear-gradient(135deg, rgba(50, 205, 50, 0.3) 0%, rgba(0, 100, 0, 0.3) 100%); border-color: #32cd32; margin-top: 20px;">
                <h3 style="color: #90ee90;">🎁 同盟国への援助</h3>
                <p style="color: #c0a080; margin-bottom: 10px;">同盟国に兵士や資源を送ることができます。</p>
                
                <!-- 大使館制限表示 -->
                <div id="embassyLimitsInfo" style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #4682b4;">
                    <div style="color: #87ceeb; font-weight: bold; margin-bottom: 10px;">🏛️ 大使館レベルによる援助制限 (1時間あたり)</div>
                    <div class="loading">読み込み中...</div>
                </div>
                
                <!-- 援助対象選択 -->
                <div style="margin-bottom: 20px;">
                    <label style="color: #888; display: block; margin-bottom: 5px;">援助対象を選択:</label>
                    <select id="transferTarget" style="width: 100%; padding: 10px; background: rgba(0,0,0,0.3); border: 1px solid #32cd32; border-radius: 8px; color: #f5deb3; font-size: 14px;">
                        <option value="">-- 同盟国を選択 --</option>
                    </select>
                </div>
                
                <!-- 送兵 -->
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #ffd700; margin-bottom: 10px;">🎖️ 送兵</h4>
                    <div id="troopTransferSection">
                        <p style="color: #888;">上で同盟国を選択してください</p>
                    </div>
                    <button class="invest-btn" onclick="transferTroops()" style="margin-top: 10px; background: linear-gradient(135deg, #8b4513 0%, #d4a574 100%);">
                        🎖️ 兵士を送る
                    </button>
                </div>
                
                <!-- 物資援助 -->
                <div>
                    <h4 style="color: #ffd700; margin-bottom: 10px;">📦 物資援助</h4>
                    <div id="resourceTransferSection">
                        <p style="color: #888;">上で同盟国を選択してください</p>
                    </div>
                    <button class="invest-btn" onclick="transferResources()" style="margin-top: 10px; background: linear-gradient(135deg, #228b22 0%, #32cd32 100%);">
                        📦 資源を送る
                    </button>
                </div>
            </div>
            
            <!-- 援助ログ -->
            <div class="invest-section" style="background: linear-gradient(135deg, rgba(100, 100, 100, 0.3) 0%, rgba(50, 50, 50, 0.3) 100%); border-color: #888; margin-top: 20px;">
                <h3 style="color: #c0c0c0;">📜 援助ログ</h3>
                <div id="transferLogsSection">
                    <div class="loading">読み込み中...</div>
                </div>
            </div>
        </div>
        
        <!-- ヒーロータブ -->
        <div class="tab-content ${currentTab === 'heroes' ? 'active' : ''}" id="tab-heroes">
            <div class="war-section" style="background: linear-gradient(135deg, rgba(255, 107, 107, 0.3) 0%, rgba(255, 193, 7, 0.3) 100%); border-color: #ff6b6b;">
                <h3 style="color: #ffd700;">🦸 ヒーロー編成</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">
                    ヒーローをバトルに出陣させて戦闘を有利に進めましょう！<br>
                    <span style="color: #ff6b6b;">⚔️ ヒーローはバトルタイプごとに設定できます。ヒーローの取得は<a href="./hero_system.php" style="color: #ffd700; text-decoration: underline;">ヒーローシステム</a>から行えます。</span>
                </p>
                <div id="heroAssignmentSection">
                    <div class="loading">ヒーロー情報を読み込み中...</div>
                </div>
            </div>
        </div>
        
        <!-- 市場タブ -->
        <div class="tab-content ${currentTab === 'market' ? 'active' : ''}" id="tab-market">
            <div class="invest-section" style="background: linear-gradient(135deg, rgba(139, 115, 85, 0.5) 0%, rgba(100, 80, 60, 0.5) 100%); border-color: #d4a574;">
                <h3 style="color: #ffd700;">🏪 市場 - 資源交換</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">市場を建設していると、資源を他の資源に交換できます。交換レート: 2:1</p>
                ${renderMarketExchange(resources)}
            </div>
        </div>
        
        <!-- チュートリアルタブ -->
        <div class="tab-content ${currentTab === 'tutorial' ? 'active' : ''}" id="tab-tutorial">
            <div class="invest-section" style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 140, 0, 0.3) 100%); border-color: #ffd700;">
                <h3 style="color: #ffd700;">📜 チュートリアル</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">
                    クエストをクリアして報酬を獲得しましょう！<br>
                    <span style="color: #90ee90;">🎁 チュートリアル完了報酬: 100,000コイン、100クリスタル、50ダイヤモンド</span>
                </p>
                <div id="tutorialSection">
                    <div class="loading">読み込み中...</div>
                </div>
            </div>
        </div>
        
        <!-- クエストタブ -->
        <div class="tab-content ${currentTab === 'quests' ? 'active' : ''}" id="tab-quests">
            <div class="invest-section" style="background: linear-gradient(135deg, rgba(72, 187, 120, 0.2) 0%, rgba(56, 161, 105, 0.2) 100%); border-color: #48bb78;">
                <h3 style="color: #48bb78;">📋 文明クエスト</h3>
                <p style="color: #c0a080; margin-bottom: 20px;">
                    時代に合わせた様々なクエストをクリアして報酬を獲得しましょう！<br>
                    <span style="color: #ffd700;">⭐ 報酬: コイン、クリスタル、ダイヤモンド、各種資源</span>
                </p>
                <div id="civilizationQuestsSection">
                    <div class="loading">読み込み中...</div>
                </div>
            </div>
        </div>
        
        <!-- ② 保護設定タブ -->
        <div class="tab-content ${currentTab === 'protection' ? 'active' : ''}" id="tab-protection">
            <h3 style="color: #d4a574; margin-bottom: 20px;">🛡️ 資源・兵士の保護設定</h3>
            
            <!-- 保管庫セクション -->
            <div class="protection-section" style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 2px solid #d4a574;">
                <h4 style="color: #ffd700; margin-bottom: 15px;">🏦 保管庫 - 資源保護</h4>
                <div id="vault-info" style="margin-bottom: 15px; color: #c0a080;"></div>
                <div id="vault-resources" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;"></div>
            </div>
            
            <!-- シェルターセクション -->
            <div class="protection-section" style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 20px; border: 2px solid #d4a574;">
                <h4 style="color: #ffd700; margin-bottom: 15px;">🛡️ シェルター - 兵士保護</h4>
                <div id="shelter-info" style="margin-bottom: 15px; color: #c0a080;"></div>
                <div id="shelter-troops" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;"></div>
            </div>
        </div>
    `;
    
    // タブ切り替え
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            currentTab = btn.dataset.tab; // 現在のタブを保存
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
            
            // 戦争タブの場合、攻撃対象を読み込む
            if (btn.dataset.tab === 'war') {
                loadTargets();
            }
            // 兵士タブの場合、兵種・キュー・負傷兵を読み込む
            if (btn.dataset.tab === 'troops') {
                loadTroops();
                loadTrainingQueue();
                loadWoundedTroops();
                loadDefenseSettings();
            }
            // 市場タブの場合、市場を読み込む
            if (btn.dataset.tab === 'market') {
                loadMarketData();
            }
            // ヒーロータブの場合、ヒーロー編成を読み込む
            if (btn.dataset.tab === 'heroes') {
                loadHeroAssignments();
            }
            // 同盟タブの場合、同盟情報を読み込む
            if (btn.dataset.tab === 'alliance') {
                loadAllianceData();
            }
            // チュートリアルタブの場合、チュートリアルを読み込む
            if (btn.dataset.tab === 'tutorial') {
                loadTutorial();
            }
            // クエストタブの場合、文明クエストを読み込む
            if (btn.dataset.tab === 'quests') {
                loadCivilizationQuests();
            }
            // リーダーボードタブの場合、ランキングを読み込む
            if (btn.dataset.tab === 'leaderboard') {
                loadLeaderboard();
            }
            // ② 保護設定タブの場合、保護設定を読み込む
            if (btn.dataset.tab === 'protection') {
                loadProtectionSettings();
            }
            // イベントタブの場合、イベントを読み込む
            if (btn.dataset.tab === 'events') {
                loadEventContent(currentEventType);
            }
            // メールタブの場合、メールと偵察ステータスを読み込む
            if (btn.dataset.tab === 'mail') {
                loadMails(mailCurrentPage, mailCurrentFilter);
                loadReconnaissanceStatus();
            }
        });
    });
    
    // 市場タブがアクティブな場合、市場データを読み込む
    if (currentTab === 'market') {
        loadMarketData();
    }
    // 戦争タブがアクティブな場合、攻撃対象を読み込む
    if (currentTab === 'war') {
        loadTargets();
    }
    // 兵士タブがアクティブな場合、兵種・キュー・負傷兵を読み込む
    if (currentTab === 'troops') {
        loadTroops();
        loadTrainingQueue();
        loadWoundedTroops();
        loadDefenseSettings();
    }
    // 同盟タブがアクティブな場合、同盟情報を読み込む
    if (currentTab === 'alliance') {
        loadAllianceData();
    }
    // ヒーロータブがアクティブな場合、ヒーロー編成を読み込む
    if (currentTab === 'heroes') {
        loadHeroAssignments();
    }
    // チュートリアルタブがアクティブな場合、チュートリアルを読み込む
    if (currentTab === 'tutorial') {
        loadTutorial();
    }
    // クエストタブがアクティブな場合、文明クエストを読み込む
    if (currentTab === 'quests') {
        loadCivilizationQuests();
    }
    // リーダーボードタブがアクティブな場合、ランキングを読み込む
    if (currentTab === 'leaderboard') {
        loadLeaderboard();
    }
    // イベントタブがアクティブな場合、イベントを読み込む
    if (currentTab === 'events') {
        loadEventContent(currentEventType);
    }
    // メールタブがアクティブな場合、メールと偵察ステータスを読み込む
    if (currentTab === 'mail') {
        loadMails(mailCurrentPage, null);  // nullを渡してフィルターを変更しない
        loadReconnaissanceStatus();
    }
    
    // 初回アクセス時にチュートリアルモーダルを表示
    checkTutorialModal();
    
    // タブバッジを更新（報酬受け取り待ちクエスト、負傷兵など）
    updateTabBadges();
    
    // リーダーボードのイベントリスナーを設定
    setupLeaderboardListeners();
}

// タブバッジを更新する関数
async function updateTabBadges() {
    try {
        // クエストの報酬受け取り待ち数を取得
        const questsRes = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_civilization_quests'})
        });
        const questsData = await questsRes.json();
        
        if (questsData.ok && questsData.quests) {
            // 完了済みで報酬未受け取りのクエスト数をカウント
            const claimableCount = questsData.quests.filter(q => 
                q.is_completed && !q.is_claimed
            ).length;
            
            const questsBadge = document.getElementById('quests-badge');
            if (questsBadge) {
                if (claimableCount > 0) {
                    questsBadge.textContent = claimableCount;
                    questsBadge.style.display = 'inline-block';
                } else {
                    questsBadge.style.display = 'none';
                }
            }
        }
        
        // 負傷兵数を取得
        const woundedRes = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_wounded_troops'})
        });
        const woundedData = await woundedRes.json();
        
        if (woundedData.ok && woundedData.wounded_troops) {
            // 負傷兵の総数をカウント
            const woundedCount = woundedData.wounded_troops.reduce((sum, w) => sum + (w.count || 0), 0);
            
            const woundedBadge = document.getElementById('wounded-badge');
            if (woundedBadge) {
                if (woundedCount > 0) {
                    woundedBadge.textContent = woundedCount;
                    woundedBadge.style.display = 'inline-block';
                } else {
                    woundedBadge.style.display = 'none';
                }
            }
        }
        
        // ③ イベントタブの赤バッジを追加（デイリータスク報酬受け取り待ち）
        const eventsRes = await fetch('civilization_events_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_daily_tasks'})
        });
        const eventsData = await eventsRes.json();
        
        if (eventsData.ok && eventsData.tasks) {
            // 完了済みで報酬未受け取りのタスク数をカウント
            const eventClaimableCount = eventsData.tasks.filter(t => 
                t.is_completed && !t.is_claimed
            ).length;
            
            const eventsBadge = document.getElementById('events-badge');
            if (eventsBadge) {
                if (eventClaimableCount > 0) {
                    eventsBadge.textContent = eventClaimableCount;
                    eventsBadge.style.display = 'inline-block';
                } else {
                    eventsBadge.style.display = 'none';
                }
            }
        }
    } catch (e) {
        console.error('Failed to update tab badges:', e);
    }
}

// 建物グリッドを描画
function renderBuildingsGrid(availableBuildings, ownedBuildings, resources) {
    return availableBuildings.map(bt => {
        const owned = ownedBuildings.filter(b => b.building_type_id == bt.id);
        const ownedCount = owned.length;
        const constructing = owned.find(b => b.is_constructing);
        
        let costText = `🪙 ${bt.base_build_cost_coins}`;
        if (bt.base_build_cost_resources) {
            const costs = JSON.parse(bt.base_build_cost_resources);
            Object.entries(costs).forEach(([key, val]) => {
                const res = resources.find(r => r.resource_key === key);
                costText += ` | ${res ? res.icon : '❓'} ${val}`;
            });
        }
        
        let statusClass = '';
        let statusText = '';
        let instantCompleteBtn = '';
        if (constructing) {
            statusClass = 'constructing';
            const remaining = Math.max(0, Math.ceil((new Date(constructing.construction_completes_at) - new Date()) / 1000));
            if (remaining <= 0) {
                statusText = `建設完了！データを更新中...`;
            } else {
                statusText = `建設中... ${formatTime(remaining)}`;
                const crystalCost = Math.max(5, Math.ceil(remaining / 60));
                const diamondCost = Math.max(1, Math.ceil(remaining / 120));
                instantCompleteBtn = `
                    <div style="display: flex; gap: 5px; margin-top: 8px;">
                        <button class="instant-btn" onclick="instantCompleteBuilding(${constructing.id})" style="flex: 1; padding: 8px 12px; background: linear-gradient(135deg, #9932cc 0%, #da70d6 100%); color: white; border: none; border-radius: 6px; font-size: 11px; cursor: pointer;">💎 ${crystalCost}</button>
                        <button class="instant-btn" onclick="instantCompleteBuildingDiamond(${constructing.id})" style="flex: 1; padding: 8px 12px; background: linear-gradient(135deg, #00bfff 0%, #1e90ff 100%); color: white; border: none; border-radius: 6px; font-size: 11px; cursor: pointer;">💠 ${diamondCost}</button>
                    </div>`;
            }
        } else if (ownedCount > 0) {
            statusClass = 'owned';
        }
        
        // 前提条件表示
        const canBuild = bt.can_build !== false;
        const missingPrereqs = bt.missing_prerequisites || [];
        const prereqText = missingPrereqs.length > 0 
            ? `<div style="color: #ff6b6b; font-size: 12px; margin-bottom: 10px;">🔒 必要: ${missingPrereqs.join(', ')}</div>` 
            : '';
        
        // 建設不可の場合はスタイルを変更
        const lockedClass = !canBuild ? 'locked' : '';
        
        return `
            <div class="building-card ${statusClass} ${lockedClass}" style="${!canBuild ? 'opacity: 0.7;' : ''}">
                <div class="building-header">
                    <span class="building-icon">${bt.icon}</span>
                    <span class="building-name">${bt.name}</span>
                    ${ownedCount > 0 ? `<span class="building-level">×${ownedCount}</span>` : ''}
                </div>
                ${bt.produces_resource_name ? `<div style="color: #48bb78; font-size: 13px; margin-bottom: 8px; display: flex; align-items: center; gap: 5px;"><span>生産:</span><span style="font-size: 16px;">${bt.produces_resource_icon || '📦'}</span><span>${bt.produces_resource_name}</span></div>` : ''}
                ${bt.building_key === 'bank' ? `<div style="color: #ffd700; font-size: 13px; margin-bottom: 8px; display: flex; align-items: center; gap: 5px;"><span>生産:</span><span style="font-size: 16px;">🪙</span><span>コイン</span></div>` : ''}
                <div class="building-desc">${bt.description || ''}</div>
                <div class="building-stats">
                    ${bt.production_rate > 0 ? `<span class="building-stat">${bt.produces_resource_icon || '⚡'} ${bt.production_rate}/h</span>` : ''}
                    ${bt.building_key === 'bank' ? `<span class="building-stat">🪙 10/h×Lv</span>` : ''}
                    ${bt.population_capacity > 0 ? `<span class="building-stat">👥 +${bt.population_capacity}</span>` : ''}
                    ${bt.military_power > 0 ? `<span class="building-stat">⚔️ +${bt.military_power}</span>` : ''}
                    ${bt.troop_deployment_bonus > 0 ? `<span class="building-stat" title="出撃兵士数上限">🚀 +${bt.troop_deployment_bonus}/Lv</span>` : ''}
                    ${bt.transfer_limit_bonus > 0 ? `<span class="building-stat" title="援助上限ボーナス">🤝 援助+</span>` : ''}
                </div>
                <div class="building-cost">${costText} | ⏱️ ${formatTime(bt.base_build_time_seconds)}</div>
                ${prereqText}
                ${statusText ? `<div style="color: #ffa500; margin-bottom: 10px;">${statusText}</div>` : ''}
                ${instantCompleteBtn}
                <button class="build-btn" onclick="buildBuilding(${bt.id})" ${constructing || !canBuild ? 'disabled' : ''}>
                    ${!canBuild ? '🔒 ロック中' : '建設する'}
                </button>
            </div>
        `;
    }).join('');
}

// 研究ツリーを描画
function renderResearchTree() {
    const researches = civData.available_researches;
    const userResearches = civData.user_researches;
    
    return researches.map(r => {
        const userResearch = userResearches.find(ur => ur.research_id == r.id);
        const isCompleted = userResearch && userResearch.is_completed;
        const isResearching = userResearch && userResearch.is_researching;
        
        // 前提研究チェック
        let isLocked = false;
        if (r.prerequisite_research_id) {
            const prereq = userResearches.find(ur => ur.research_id == r.prerequisite_research_id);
            isLocked = !prereq || !prereq.is_completed;
        }
        
        let statusClass = '';
        let statusText = '';
        let instantCompleteBtn = '';
        if (isCompleted) {
            statusClass = 'completed';
            statusText = '✅ 完了';
        } else if (isResearching) {
            statusClass = 'researching';
            const remaining = Math.max(0, Math.ceil((new Date(userResearch.research_completes_at) - new Date()) / 1000));
            if (remaining <= 0) {
                statusText = `研究完了！データを更新中...`;
            } else {
                statusText = `研究中... ${formatTime(remaining)}`;
                const crystalCost = Math.max(3, Math.ceil(remaining / 60));
                const diamondCost = Math.max(1, Math.ceil(remaining / 120));
                instantCompleteBtn = `
                    <div style="display: flex; gap: 5px; margin-top: 8px;">
                        <button class="instant-btn" onclick="instantCompleteResearch(${userResearch.id})" style="flex: 1; padding: 8px 12px; background: linear-gradient(135deg, #9932cc 0%, #da70d6 100%); color: white; border: none; border-radius: 6px; font-size: 11px; cursor: pointer;">💎 ${crystalCost}</button>
                        <button class="instant-btn" onclick="instantCompleteResearchDiamond(${userResearch.id})" style="flex: 1; padding: 8px 12px; background: linear-gradient(135deg, #00bfff 0%, #1e90ff 100%); color: white; border: none; border-radius: 6px; font-size: 11px; cursor: pointer;">💠 ${diamondCost}</button>
                    </div>`;
            }
        } else if (isLocked) {
            statusClass = 'locked';
            statusText = '🔒 前提研究が必要';
        }
        
        return `
            <div class="research-card ${statusClass}">
                <div class="research-header">
                    <span class="research-icon">${r.icon}</span>
                    <span class="research-name">${r.name}</span>
                </div>
                <div class="research-desc">${r.description || ''}</div>
                <div class="research-cost">
                    <span>📚 ${r.research_cost_points} ポイント</span>
                    <span>⏱️ ${formatTime(r.research_time_seconds)}</span>
                </div>
                ${statusText ? `<div style="margin-bottom: 10px; font-size: 13px;">${statusText}</div>` : ''}
                ${instantCompleteBtn}
                ${!isCompleted && !isResearching && !isLocked ? `
                    <button class="research-btn" onclick="startResearch(${r.id})" 
                        ${civData.civilization.research_points < r.research_cost_points ? 'disabled' : ''}>
                        研究開始
                    </button>
                ` : ''}
            </div>
        `;
    }).join('');
}

// 市場交換UIを描画
function renderMarketExchange(resources) {
    // 市場建物を持っているか確認
    const markets = civData.buildings.filter(b => b.building_key === 'market' && !b.is_constructing);
    const marketCount = markets.length;
    const totalMarketLevel = markets.reduce((sum, m) => sum + (parseInt(m.level) || 1), 0);
    
    if (marketCount === 0) {
        return `
            <div style="text-align: center; padding: 40px; color: #c0a080;">
                <p style="font-size: 24px; margin-bottom: 15px;">🏪</p>
                <p>市場を建設すると、資源を交換できるようになります。</p>
                <p style="font-size: 13px; margin-top: 10px;">建物タブから「市場」を建設してください。</p>
            </div>
        `;
    }
    
    const unlockedResources = resources.filter(r => r.unlocked);
    
    if (unlockedResources.length < 2) {
        return `
            <div style="text-align: center; padding: 40px; color: #c0a080;">
                <p>交換できる資源が足りません。資源を2種類以上アンロックしてください。</p>
            </div>
        `;
    }
    
    // 市場ボーナスを計算
    const marketBonus = Math.min(50, (marketCount * 5) + (totalMarketLevel * 2));
    
    return `
        <div class="buildings-grid">
            <div class="building-card" style="border-color: #d4a574; grid-column: span 2;">
                <div class="building-header">
                    <span class="building-icon">🔄</span>
                    <span class="building-name">資源交換</span>
                </div>
                <div class="building-desc">資源を他の資源に交換します。レートは資源の価値により変動します。</div>
                
                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; margin-top: 10px;">
                    <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <span style="color: #888;">🏪 市場数:</span>
                            <span style="color: #ffd700; font-weight: bold;">${marketCount}</span>
                        </div>
                        <div>
                            <span style="color: #888;">📈 合計レベル:</span>
                            <span style="color: #ffd700; font-weight: bold;">${totalMarketLevel}</span>
                        </div>
                        <div>
                            <span style="color: #888;">✨ レートボーナス:</span>
                            <span style="color: #32cd32; font-weight: bold;">+${marketBonus}%</span>
                        </div>
                    </div>
                    <p style="color: #888; font-size: 12px; margin-top: 8px;">💡 市場を増やすとレートが改善されます（市場1つ:+5%, レベル:+2%, 最大+50%）</p>
                </div>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; margin-top: 15px;">
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 8px; color: #c0a080; font-size: 13px;">交換する資源</label>
                        <select id="fromResource" class="invest-input" style="width: 100%;">
                            ${unlockedResources.map(r => `<option value="${r.resource_type_id}" data-key="${r.resource_key}" data-amount="${r.amount}">${r.icon} ${r.name} (${Math.floor(r.amount)})</option>`).join('')}
                        </select>
                    </div>
                    <div style="flex: 0; padding-top: 25px; font-size: 24px;">→</div>
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 8px; color: #c0a080; font-size: 13px;">受け取る資源</label>
                        <select id="toResource" class="invest-input" style="width: 100%;">
                            ${unlockedResources.map(r => `<option value="${r.resource_type_id}" data-key="${r.resource_key}">${r.icon} ${r.name}</option>`).join('')}
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #c0a080; font-size: 13px;">交換する量</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="number" id="exchangeAmount" class="invest-input" value="100" min="1" step="1" style="flex: 1;">
                        <div class="quick-invest-btns">
                            <button class="quick-invest-btn" onclick="setExchangeAmount(10)">10</button>
                            <button class="quick-invest-btn" onclick="setExchangeAmount(50)">50</button>
                            <button class="quick-invest-btn" onclick="setExchangeAmount(100)">100</button>
                            <button class="quick-invest-btn" onclick="setExchangeAmount(500)">500</button>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 8px;">
                    <span style="color: #c0a080;">交換結果: </span>
                    <span id="exchangeResult" style="color: #ffd700;">--</span>
                </div>
                
                <!-- ④ 市場交換制限表示 -->
                <div id="marketLimitDisplay"></div>
                
                <button class="build-btn" onclick="exchangeResources()" style="margin-top: 15px; background: linear-gradient(135deg, #d4a574 0%, #8b4513 100%);">
                    交換する
                </button>
            </div>
        </div>
    `;
}

// 市場データを読み込む
function loadMarketData() {
    // 交換結果の計算をセットアップ
    const fromSelect = document.getElementById('fromResource');
    const toSelect = document.getElementById('toResource');
    const amountInput = document.getElementById('exchangeAmount');
    
    if (!fromSelect || !toSelect || !amountInput) {
        return; // 市場が建設されていない場合などは要素が存在しない
    }
    
    // 市場情報をサーバーから取得して資源価値を使用
    fetch('civilization_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get_market_info'})
    })
    .then(res => res.json())
    .then(marketInfo => {
        // サーバーから資源価値を取得（フォールバック用にデフォルト値も設定）
        const resourceValues = marketInfo.ok ? marketInfo.resource_values : {
            'food': 1.0, 'wood': 1.0, 'stone': 1.2, 'bronze': 1.5, 'iron': 2.0,
            'gold': 3.0, 'knowledge': 2.5, 'oil': 3.5, 'crystal': 4.0,
            'mana': 4.5, 'uranium': 5.0, 'diamond': 6.0
        };
        
        // 市場ボーナスを計算
        const markets = civData.buildings.filter(b => b.building_key === 'market' && !b.is_constructing);
        const marketCount = markets.length;
        const totalMarketLevel = markets.reduce((sum, m) => sum + (parseInt(m.level) || 1), 0);
        const marketBonus = Math.min(0.5, (marketCount * 0.05) + (totalMarketLevel * 0.02));
        
        // ④ 交換制限表示を追加（全資源合計で管理）
        const hourlyLimit = marketInfo.hourly_limit || (10000 * Math.max(1, marketCount));
        const exchangeLimitInfo = marketInfo.exchange_limits || {};
        
        // 交換制限表示を更新する関数
        const updateLimitDisplay = () => {
            const limitDisplay = document.getElementById('marketLimitDisplay');
            if (!limitDisplay) return;
            
            const totalExchanged = exchangeLimitInfo.total_exchanged || 0;
            const remaining = exchangeLimitInfo.remaining !== undefined ? exchangeLimitInfo.remaining : hourlyLimit;
            const resetSeconds = exchangeLimitInfo.reset_in_seconds || 0;
            const resetMinutes = Math.ceil(resetSeconds / 60);
            const usedPercent = Math.round((totalExchanged / hourlyLimit) * 100);
            const barColor = remaining > hourlyLimit * 0.3 ? '#32cd32' : (remaining > 0 ? '#ffa500' : '#ff6b6b');
            
            if (totalExchanged > 0) {
                limitDisplay.innerHTML = `
                    <div style="margin-top: 15px; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: #888;">🏪 全資源の交換可能量:</span>
                            <span style="color: ${barColor};">${remaining.toLocaleString()} / ${hourlyLimit.toLocaleString()}</span>
                        </div>
                        <div style="background: rgba(0,0,0,0.5); border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: ${barColor}; height: 100%; width: ${usedPercent}%; transition: width 0.3s;"></div>
                        </div>
                        <div style="color: #888; font-size: 11px; margin-top: 5px;">⏱️ あと${resetMinutes}分でリセット | 市場数: ${marketCount} | 使用済: ${totalExchanged.toLocaleString()}</div>
                    </div>
                `;
            } else {
                limitDisplay.innerHTML = `
                    <div style="margin-top: 15px; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: #888;">🏪 1時間の交換上限:</span>
                            <span style="color: #32cd32;">${hourlyLimit.toLocaleString()}</span>
                        </div>
                        <div style="color: #888; font-size: 11px;">市場数: ${marketCount} | 制限なし（未交換）</div>
                    </div>
                `;
            }
        };
        
        const updateResult = () => {
            const resultElement = document.getElementById('exchangeResult');
            if (!resultElement) return;
            
            const fromId = fromSelect.value;
            const toId = toSelect.value;
            const amount = parseInt(amountInput.value) || 0;
            
            if (fromId === toId) {
                resultElement.textContent = '同じ資源は交換できません';
                return;
            }
            
            const fromOption = fromSelect.options[fromSelect.selectedIndex];
            const toOption = toSelect.options[toSelect.selectedIndex];
            const fromName = fromOption.textContent.split('(')[0].trim();
            const toName = toOption.textContent.split('(')[0].trim();
            const fromKey = fromOption.dataset.key || 'food';
            const toKey = toOption.dataset.key || 'food';
            
            // 交換レートを計算
            const fromValue = resourceValues[fromKey] || 1.0;
            const toValue = resourceValues[toKey] || 1.0;
            const baseRate = fromValue / toValue;
            const finalRate = baseRate * (1 + marketBonus);
            
            const received = Math.floor(amount * finalRate);
            const ratePercent = Math.round(finalRate * 100);
            resultElement.innerHTML = `${amount} ${fromName} → <strong style="color: #32cd32;">${received}</strong> ${toName} <span style="color: #888; font-size: 12px;">(レート: ${ratePercent}%)</span>`;
            
            // 制限表示も更新
            updateLimitDisplay();
        };
        
        fromSelect.addEventListener('change', updateResult);
        toSelect.addEventListener('change', updateResult);
        amountInput.addEventListener('input', updateResult);
        
        updateResult();
    })
    .catch(err => {
        console.error('Failed to load market info:', err);
    });
}

// 交換量をセット
function setExchangeAmount(amount) {
    document.getElementById('exchangeAmount').value = amount;
    // 結果を更新
    document.getElementById('exchangeAmount').dispatchEvent(new Event('input'));
}

// 資源を交換
async function exchangeResources() {
    const fromResourceId = parseInt(document.getElementById('fromResource').value);
    const toResourceId = parseInt(document.getElementById('toResource').value);
    const amount = parseInt(document.getElementById('exchangeAmount').value) || 0;
    
    if (fromResourceId === toResourceId) {
        showNotification('同じ資源は交換できません', true);
        return;
    }
    
    if (amount < 1) {
        showNotification('最低交換量は1です', true);
        return;
    }
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'exchange_resources',
                from_resource_id: fromResourceId,
                to_resource_id: toResourceId,
                amount: amount
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 攻撃対象を読み込む
async function loadTargets() {
    try {
        // 並行して攻撃対象とレート制限状態を取得
        const [targetsRes, rateLimitRes] = await Promise.all([
            fetch('civilization_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_targets'})
            }),
            fetch('civilization_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_war_rate_limit_status'})
            })
        ]);
        
        const data = await targetsRes.json();
        const rateLimitData = await rateLimitRes.json();
        
        // レート制限状態を表示
        updateWarRateLimitDisplay(rateLimitData);
        
        // 自分の軍事力を更新
        if (data.my_military_power) {
            const myBuildingPower = document.getElementById('myBuildingPower');
            const myTroopPower = document.getElementById('myTroopPower');
            const myEquipmentPower = document.getElementById('myEquipmentPower');
            const myTotalPower = document.getElementById('myTotalPower');
            if (myBuildingPower) myBuildingPower.textContent = data.my_military_power.building_power || 0;
            if (myTroopPower) myTroopPower.textContent = data.my_military_power.troop_power || 0;
            if (myEquipmentPower) myEquipmentPower.textContent = data.my_military_power.equipment_power || 0;
            if (myTotalPower) myTotalPower.textContent = data.my_military_power.total_power || 0;
            
            // 装備バフを更新
            const myArmor = document.getElementById('myArmor');
            if (myArmor && data.my_military_power.equipment_buffs) {
                myArmor.textContent = Math.floor(data.my_military_power.equipment_buffs.armor || 0);
            }
            const myHealth = document.getElementById('myHealth');
            if (myHealth && data.my_military_power.equipment_buffs) {
                myHealth.textContent = Math.floor(data.my_military_power.equipment_buffs.health || 0);
            }
        }
        
        if (data.ok && data.targets.length > 0) {
            const myPower = data.my_military_power?.total_power || 0;
            const myArmor = data.my_military_power?.equipment_buffs?.armor || 0;
            
            document.getElementById('targetsList').innerHTML = data.targets.map(t => {
                const targetPower = t.military_power || 0;
                const targetArmor = t.equipment_buffs?.armor || 0;
                const isAlly = t.is_ally || false;
                
                // 同盟相手の場合は特別表示
                if (isAlly) {
                    return `
                    <div class="target-card" style="border-color: #4682b4; background: linear-gradient(135deg, rgba(70, 130, 180, 0.3) 0%, rgba(25, 25, 112, 0.3) 100%);">
                        <div class="target-header">
                            <span class="target-name">🤝 ${escapeHtml(t.civilization_name)}</span>
                            <span class="target-power" style="color: #87ceeb;">⚔️ ${targetPower}</span>
                        </div>
                        <div style="color: #888; font-size: 13px; margin-bottom: 5px;">
                            @${escapeHtml(t.handle)} | 👥 ${t.population}人
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; background: rgba(0,0,0,0.3); padding: 6px 10px; border-radius: 6px;">
                            <span style="font-size: 16px;">${t.era_icon || '🏛️'}</span>
                            <span style="color: #ffd700; font-size: 12px; font-weight: bold;">${escapeHtml(t.era_name || '不明')}</span>
                        </div>
                        <div style="background: rgba(70, 130, 180, 0.3); padding: 8px; border-radius: 6px; text-align: center; color: #87ceeb;">
                            <span style="font-weight: bold;">🤝 同盟国</span>
                            <span style="color: #888; font-size: 11px; display: block; margin-top: 3px;">攻撃できません</span>
                        </div>
                    </div>
                `;
                }
                
                // アーマーによる軽減を考慮した有利不利計算
                // 自分のアーマーは相手の攻撃を軽減、相手のアーマーは自分の攻撃を軽減
                const myArmorReduction = Math.min(CIV_ARMOR_MAX_REDUCTION, myArmor / CIV_ARMOR_PERCENT_DIVISOR);
                const targetArmorReduction = Math.min(CIV_ARMOR_MAX_REDUCTION, targetArmor / CIV_ARMOR_PERCENT_DIVISOR);
                
                // 相性ボーナスを考慮
                const troopAdvantage = t.troop_advantage_multiplier || 1.0;
                
                const myEffectivePower = myPower * (1 - targetArmorReduction) * troopAdvantage;
                const targetEffectivePower = targetPower * (1 - myArmorReduction);
                
                const powerDiff = myEffectivePower - targetEffectivePower;
                const powerClass = powerDiff > 0 ? 'color: #32cd32;' : (powerDiff < 0 ? 'color: #ff6b6b;' : 'color: #ffd700;');
                const powerIndicator = powerDiff > 0 ? '✅ 有利' : (powerDiff < 0 ? '⚠️ 不利' : '⚖️ 互角');
                
                // 相性ボーナス表示
                const advantageThresholdHigh = 1.0 + CIV_ADVANTAGE_DISPLAY_THRESHOLD;
                const advantageThresholdLow = 1.0 - CIV_ADVANTAGE_DISPLAY_THRESHOLD;
                let advantageText = '';
                if (troopAdvantage > advantageThresholdHigh) {
                    const bonusPercent = Math.round((troopAdvantage - 1) * 100);
                    advantageText = `<div style="color: #32cd32; font-size: 11px; margin-bottom: 5px;">🎯 相性有利 +${bonusPercent}%</div>`;
                } else if (troopAdvantage < advantageThresholdLow) {
                    const penaltyPercent = Math.round((1 - troopAdvantage) * 100);
                    advantageText = `<div style="color: #ff6b6b; font-size: 11px; margin-bottom: 5px;">⚠️ 相性不利 -${penaltyPercent}%</div>`;
                }
                
                // 装備バフ表示
                const equipBuffs = t.equipment_buffs || {};
                const hasEquipBuffs = (equipBuffs.attack > 0 || equipBuffs.armor > 0 || equipBuffs.health > 0);
                const equipBuffText = hasEquipBuffs ? `<div style="color: #9932cc; font-size: 11px; margin-bottom: 5px;">⚔️${Math.floor(equipBuffs.attack || 0)} 🛡️${Math.floor(equipBuffs.armor || 0)} ❤️${Math.floor(equipBuffs.health || 0)}</div>` : '';
                
                // 兵種構成表示
                const troopComp = t.troop_composition || {};
                let troopCompText = '';
                const categories = ['infantry', 'cavalry', 'ranged', 'siege'];
                const categoryIcons = {'infantry': '🗡️', 'cavalry': '🐴', 'ranged': '🏹', 'siege': '💣'};
                const hasAnyTroops = categories.some(c => (troopComp[c]?.count || 0) > 0);
                if (hasAnyTroops) {
                    troopCompText = '<div style="display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 5px;">';
                    categories.forEach(c => {
                        const count = troopComp[c]?.count || 0;
                        if (count > 0) {
                            troopCompText += `<span style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px; font-size: 10px;">${categoryIcons[c]} ${count}</span>`;
                        }
                    });
                    troopCompText += '</div>';
                }
                
                return `
                <div class="target-card" ${!t.can_attack ? 'style="opacity: 0.6;"' : ''}>
                    <div class="target-header">
                        <span class="target-name">${escapeHtml(t.civilization_name)}</span>
                        <span class="target-power" style="${powerClass}">⚔️ ${targetPower}</span>
                    </div>
                    <div style="color: #888; font-size: 13px; margin-bottom: 5px;">
                        @${escapeHtml(t.handle)} | 👥 ${t.population}人
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; background: rgba(0,0,0,0.3); padding: 6px 10px; border-radius: 6px;">
                        <span style="font-size: 16px;">${t.era_icon || '🏛️'}</span>
                        <span style="color: #ffd700; font-size: 12px; font-weight: bold;">${escapeHtml(t.era_name || '不明')}</span>
                        ${t.era_difference > CIV_MAX_ERA_DIFFERENCE ? `<span style="color: #ff6b6b; font-size: 10px; margin-left: auto;">⚠️ 時代差${t.era_difference}</span>` : 
                          t.era_difference > 0 ? `<span style="color: #888; font-size: 10px; margin-left: auto;">時代差${t.era_difference}</span>` : ''}
                    </div>
                    ${troopCompText}
                    ${equipBuffText}
                    ${advantageText}
                    <div style="font-size: 12px; margin-bottom: 10px; ${powerClass}">
                        ${powerIndicator}
                    </div>
                    ${t.can_attack ? `
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="attack-btn" data-target-id="${Number(t.user_id) || 0}" data-target-name="${escapeHtml(t.civilization_name)}" data-target-power="${Number(targetPower) || 0}">
                            ⚔️ 攻撃する
                        </button>
                        <button class="recon-btn" data-target-id="${Number(t.user_id) || 0}" data-target-name="${escapeHtml(t.civilization_name)}" style="background: linear-gradient(135deg, #32cd32, #228b22); color: #fff; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 12px;">
                            🔭 偵察
                        </button>
                    </div>
                    ` : `
                    <div style="background: rgba(255, 0, 0, 0.2); padding: 10px; border-radius: 8px; text-align: center; border: 1px solid #ff6b6b;">
                        <span style="color: #ff6b6b; font-weight: bold;">⚠️ 時代差${CIV_MAX_ERA_DIFFERENCE + 1}以上のため攻撃不可</span>
                        <div style="color: #888; font-size: 11px; margin-top: 5px;">時代が近い相手を選んでください</div>
                    </div>
                    `}
                </div>
            `}).join('');
            
            // 攻撃ボタンにイベントリスナーを追加
            document.querySelectorAll('.attack-btn[data-target-id]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const targetId = Number(btn.dataset.targetId) || 0;
                    if (targetId <= 0) {
                        showNotification('攻撃対象の情報が正しくありません', true);
                        return;
                    }
                    openAttackModal(
                        targetId,
                        btn.dataset.targetName || '不明',
                        Number(btn.dataset.targetPower) || 0
                    );
                });
            });
            
            // 偵察ボタンにイベントリスナーを追加
            document.querySelectorAll('.recon-btn[data-target-id]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const targetId = Number(btn.dataset.targetId) || 0;
                    const targetName = btn.dataset.targetName || '不明';
                    if (targetId <= 0) {
                        showNotification('偵察対象の情報が正しくありません', true);
                        return;
                    }
                    await performReconnaissance('war', targetId, targetName);
                });
            });
        } else {
            document.getElementById('targetsList').innerHTML = '<p style="color: #888;">攻撃可能な文明がありません</p>';
        }
        
        // 戦争ログも読み込む
        loadWarLogs();
    } catch (e) {
        console.error(e);
    }
}

// 戦争ログを読み込む
async function loadWarLogs() {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_war_logs'})
        });
        const data = await res.json();
        
        const warLogsList = document.getElementById('warLogsList');
        if (!warLogsList) return;
        
        if (data.ok && data.war_logs.length > 0) {
            const myUserId = data.my_user_id;
            warLogsList.innerHTML = data.war_logs.map(log => {
                const isAttacker = log.attacker_user_id == myUserId;
                const isWinner = log.winner_user_id == myUserId;
                const resultText = isWinner ? '勝利' : '敗北';
                const resultClass = isWinner ? 'color: #32cd32;' : 'color: #ff6b6b;';
                const actionText = isAttacker ? '攻撃' : '防衛';
                
                // 相手の文明名とユーザーハンドルを取得
                const opponentCivName = isAttacker ? log.defender_civ_name : log.attacker_civ_name;
                const opponentHandle = isAttacker ? log.defender_handle : log.attacker_handle;
                const opponentDisplayName = isAttacker ? log.defender_name : log.attacker_name;
                const battleTime = new Date(log.battle_at).toLocaleString('ja-JP');
                
                let lootText = '';
                if (log.loot_coins > 0 || (log.loot_resources && Object.keys(JSON.parse(log.loot_resources || '{}')).length > 0)) {
                    const lootResources = JSON.parse(log.loot_resources || '{}');
                    if (isWinner) {
                        // 勝者: 略奪した資源を表示
                        lootText = `<div style="font-size: 11px; color: #32cd32; margin-top: 5px;">💰 略奪: ${log.loot_coins}コイン`;
                        for (const [key, val] of Object.entries(lootResources)) {
                            const resourceName = getResourceName(key);
                            lootText += ` | ${resourceName}: +${val}`;
                        }
                        lootText += '</div>';
                    } else {
                        // 敗者: 奪われた資源を表示
                        lootText = `<div style="font-size: 11px; color: #ff6b6b; margin-top: 5px;">💸 損失: ${log.loot_coins}コイン`;
                        for (const [key, val] of Object.entries(lootResources)) {
                            const resourceName = getResourceName(key);
                            lootText += ` | ${resourceName}: -${val}`;
                        }
                        lootText += '</div>';
                    }
                }
                
                // ターン制バトル情報
                const totalTurns = log.total_turns || 0;
                const turnsText = totalTurns > 0 ? `<span style="color: #87ceeb; font-size: 11px; margin-left: 10px;">⚡${totalTurns}ターン</span>` : '';
                
                // バトルログ詳細ボタン
                const detailButton = totalTurns > 0 ? `
                    <button onclick="showBattleTurnLogs(${log.id})" style="padding: 4px 10px; background: linear-gradient(135deg, #4169e1 0%, #6495ed 100%); color: #fff; border: none; border-radius: 5px; font-size: 11px; cursor: pointer; margin-top: 8px;">
                        📜 バトルログ詳細
                    </button>
                ` : '';
                
                return `
                <div style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; margin-bottom: 10px; border-left: 3px solid ${isWinner ? '#32cd32' : '#ff6b6b'};">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-weight: bold; ${resultClass}">${resultText}</span>
                            <span style="color: #888;"> - ${actionText}</span>
                            ${turnsText}
                        </div>
                        <span style="color: #888; font-size: 11px;">${battleTime}</span>
                    </div>
                    <div style="margin-top: 5px; font-size: 13px;">
                        <span style="color: #c0a080;">vs</span> 
                        <span style="color: #ffd700;">${escapeHtml(opponentCivName || '不明の文明')}</span>
                        <span style="color: #87ceeb; font-size: 12px;">(@${escapeHtml(opponentHandle || '?')})</span>
                    </div>
                    <div style="margin-top: 5px; font-size: 12px; color: #888;">
                        ⚔️ ${log.attacker_power} vs 🛡️ ${log.defender_power}
                        ${log.attacker_final_hp !== undefined ? `| HP: ${log.attacker_final_hp}/${log.defender_final_hp}` : ''}
                    </div>
                    ${lootText}
                    ${detailButton}
                </div>
            `}).join('');
        } else {
            warLogsList.innerHTML = '<p style="color: #888;">戦争ログがありません</p>';
        }
    } catch (e) {
        console.error(e);
        document.getElementById('warLogsList').innerHTML = '<p style="color: #888;">戦争ログの読み込みに失敗しました</p>';
    }
}

// バトルログ詳細を表示
async function showBattleTurnLogs(warLogId) {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_battle_turn_logs', war_log_id: warLogId})
        });
        const data = await res.json();
        
        if (!data.ok) {
            showNotification(data.error || 'バトルログの取得に失敗しました', true);
            return;
        }
        
        const warLog = data.war_log;
        const turnLogs = data.turn_logs || [];
        const myUserId = data.my_user_id;
        
        const isAttacker = warLog.attacker_user_id == myUserId;
        const isWinner = warLog.winner_user_id == myUserId;
        
        // モーダルを作成
        let modalHtml = `
            <div id="battleLogModal" class="attack-modal-overlay active" onclick="if(event.target.id==='battleLogModal')closeBattleLogModal()">
                <div class="attack-modal" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
                    <div class="attack-modal-header">
                        <h3 class="attack-modal-title">📜 バトルログ詳細</h3>
                        <button class="attack-modal-close" onclick="closeBattleLogModal()">×</button>
                    </div>
                    
                    <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <div style="color: #ffd700; font-weight: bold; font-size: 16px;">
                                    ${isWinner ? '🏆 勝利' : '💀 敗北'}
                                </div>
                                <div style="color: #888; font-size: 12px; margin-top: 5px;">
                                    ${isAttacker ? '攻撃側' : '防衛側'}として参戦
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="color: #87ceeb;">⚡ ${warLog.total_turns || 0}ターン</div>
                                <div style="color: #888; font-size: 11px;">${new Date(warLog.battle_at).toLocaleString('ja-JP')}</div>
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: space-around; margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
                            <div style="text-align: center;">
                                <div style="color: #ff6b6b;">⚔️ 攻撃側</div>
                                <div style="color: #ffd700; font-size: 18px; font-weight: bold;">${escapeHtml(warLog.attacker_civ_name || '不明')}</div>
                                <div style="color: #888; font-size: 11px;">HP: ${warLog.attacker_final_hp || 0}</div>
                            </div>
                            <div style="color: #888; font-size: 24px; align-self: center;">VS</div>
                            <div style="text-align: center;">
                                <div style="color: #32cd32;">🛡️ 防御側</div>
                                <div style="color: #ffd700; font-size: 18px; font-weight: bold;">${escapeHtml(warLog.defender_civ_name || '不明')}</div>
                                <div style="color: #888; font-size: 11px;">HP: ${warLog.defender_final_hp || 0}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="max-height: 400px; overflow-y: auto; padding: 5px;">
                        ${turnLogs.length > 0 ? turnLogs.map(log => {
                            const isAttackerTurn = log.actor_side === 'attacker';
                            const turnColor = isAttackerTurn ? '#ff6b6b' : '#32cd32';
                            const turnIcon = isAttackerTurn ? '⚔️' : '🛡️';
                            
                            // ログメッセージを行ごとに分割して表示
                            const messages = (log.log_message || '').split('\n').filter(m => m.trim());
                            
                            return `
                                <div style="background: rgba(${isAttackerTurn ? '139,0,0' : '0,100,0'},0.2); padding: 10px; border-radius: 8px; margin-bottom: 8px; border-left: 3px solid ${turnColor};">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span style="color: ${turnColor}; font-weight: bold;">${turnIcon} ターン ${log.turn_number}</span>
                                        <span style="color: #888; font-size: 11px;">
                                            攻:${log.attacker_hp_after} / 防:${log.defender_hp_after}
                                        </span>
                                    </div>
                                    <div style="font-size: 12px; color: #f5deb3;">
                                        ${messages.map(m => `<div style="margin-bottom: 3px;">${escapeHtml(m)}</div>`).join('')}
                                    </div>
                                </div>
                            `;
                        }).join('') : '<p style="color: #888; text-align: center;">詳細なターンログがありません</p>'}
                    </div>
                    
                    <button onclick="closeBattleLogModal()" style="width: 100%; margin-top: 15px; padding: 12px; background: linear-gradient(135deg, #8b4513 0%, #d4a574 100%); color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">
                        閉じる
                    </button>
                </div>
            </div>
        `;
        
        // 既存のモーダルを削除
        const existingModal = document.getElementById('battleLogModal');
        if (existingModal) existingModal.remove();
        
        // モーダルを追加
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
    } catch (e) {
        console.error(e);
        showNotification('バトルログの取得に失敗しました', true);
    }
}

// バトルログモーダルを閉じる
function closeBattleLogModal() {
    const modal = document.getElementById('battleLogModal');
    if (modal) modal.remove();
}

// コイン投資
async function investCoins() {
    const amount = parseInt(document.getElementById('investAmount').value) || 0;
    
    if (amount < 100) {
        showNotification('最低投資額は100コインです', true);
        return;
    }
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'invest_coins', amount: amount})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

function setInvestAmount(amount) {
    document.getElementById('investAmount').value = amount;
}

// 建物建設
async function buildBuilding(buildingTypeId) {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'build', building_type_id: buildingTypeId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 研究開始
async function startResearch(researchId) {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'research', research_id: researchId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 時代進化
async function advanceEra() {
    if (!confirm('次の時代に進化しますか？\n研究ポイントを消費します。')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'advance_era'})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// ① 兵士絞り込み用変数
let allAvailableTroops = [];
let allUserTroops = [];
let troopAdvantageInfo = {};

// ① ステルス判定ヘルパー関数
function isStealthUnit(troop) {
    return troop.is_stealth === true || troop.is_stealth === 1 || troop.is_stealth === '1';
}

// ① 核ユニット判定ヘルパー関数
function isNuclearUnit(troop) {
    return troop.troop_key && (
        troop.troop_key.includes('nuclear') || 
        (troop.name && (troop.name.includes('原子力') || troop.name.includes('核')))
    );
}

// ① 使い捨てユニット判定ヘルパー関数
function isDisposableUnit(troop) {
    return troop.is_disposable === true || troop.is_disposable === 1 || troop.is_disposable === '1';
}

// ② 出撃画面用のラベルHTMLを生成
function getTroopLabelsHtml(troop) {
    let labels = '';
    if (isNuclearUnit(troop)) {
        labels += `<span style="background: rgba(50, 205, 50, 0.5); padding: 1px 4px; border-radius: 3px; font-size: 9px; margin-left: 3px;">☢️核</span>`;
    }
    if (isStealthUnit(troop)) {
        labels += `<span style="background: rgba(128, 0, 128, 0.5); padding: 1px 4px; border-radius: 3px; font-size: 9px; margin-left: 3px;">👻隠密</span>`;
    }
    if (isDisposableUnit(troop)) {
        labels += `<span style="background: rgba(255, 69, 0, 0.5); padding: 1px 4px; border-radius: 3px; font-size: 9px; margin-left: 3px;">💀捨</span>`;
    }
    return labels;
}

// ① フィルターを適用
function applyTroopFilters() {
    const categoryFilter = document.getElementById('filter-troop-category')?.value || '';
    const domainFilter = document.getElementById('filter-domain-category')?.value || '';
    const stealthFilter = document.getElementById('filter-stealth')?.value || '';
    const nuclearFilter = document.getElementById('filter-nuclear')?.value || '';
    const disposableFilter = document.getElementById('filter-disposable')?.value || '';
    
    // ① フィルター状態を保存
    troopFilterState = {
        categoryFilter: categoryFilter,
        domainFilter: domainFilter,
        stealthFilter: stealthFilter,
        nuclearFilter: nuclearFilter,
        disposableFilter: disposableFilter
    };
    
    const filteredTroops = allAvailableTroops.filter(t => {
        // 兵種相性フィルター
        if (categoryFilter && t.troop_category !== categoryFilter) {
            return false;
        }
        // 領域カテゴリフィルター
        if (domainFilter && (t.domain_category || 'land') !== domainFilter) {
            return false;
        }
        // ステルスフィルター
        const isStealth = isStealthUnit(t);
        if (stealthFilter === 'yes' && !isStealth) {
            return false;
        }
        if (stealthFilter === 'no' && isStealth) {
            return false;
        }
        // 核フィルター
        const isNuclear = isNuclearUnit(t);
        if (nuclearFilter === 'yes' && !isNuclear) {
            return false;
        }
        if (nuclearFilter === 'no' && isNuclear) {
            return false;
        }
        // 使い捨てフィルター
        const isDisposable = !!t.is_disposable;
        if (disposableFilter === 'yes' && !isDisposable) {
            return false;
        }
        if (disposableFilter === 'no' && isDisposable) {
            return false;
        }
        return true;
    });
    
    renderTroopsList(filteredTroops, allUserTroops, troopAdvantageInfo);
}

// ① フィルターをリセット
function resetTroopFilters() {
    troopFilterState = {
        categoryFilter: '',
        domainFilter: '',
        stealthFilter: '',
        nuclearFilter: '',
        disposableFilter: ''
    };
    document.getElementById('filter-troop-category').value = '';
    document.getElementById('filter-domain-category').value = '';
    document.getElementById('filter-stealth').value = '';
    document.getElementById('filter-nuclear').value = '';
    document.getElementById('filter-disposable').value = '';
    applyTroopFilters();
}

// ① フィルター状態を復元する関数
function restoreTroopFilters() {
    const categorySelect = document.getElementById('filter-troop-category');
    const domainSelect = document.getElementById('filter-domain-category');
    const stealthSelect = document.getElementById('filter-stealth');
    const nuclearSelect = document.getElementById('filter-nuclear');
    const disposableSelect = document.getElementById('filter-disposable');
    
    if (categorySelect) categorySelect.value = troopFilterState.categoryFilter;
    if (domainSelect) domainSelect.value = troopFilterState.domainFilter;
    if (stealthSelect) stealthSelect.value = troopFilterState.stealthFilter;
    if (nuclearSelect) nuclearSelect.value = troopFilterState.nuclearFilter;
    if (disposableSelect) disposableSelect.value = troopFilterState.disposableFilter;
}

// ① 兵種リストをレンダリング
function renderTroopsList(troops, userTroops, advantageInfo) {
    const troopsList = document.getElementById('troopsList');
    
    if (troops && troops.length > 0) {
        // 相性説明を先頭に追加
        let advantageHtml = `
            <div class="target-card" style="border-color: #ffd700; background: rgba(255, 215, 0, 0.1); grid-column: span 2;">
                <div class="target-header">
                    <span class="target-name">⚔️ 兵種相性システム</span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px;">
                    <div style="flex: 1; min-width: 200px;">
                        <div style="color: #ffd700; font-weight: bold; margin-bottom: 5px;">🗡️ 歩兵</div>
                        <div style="color: #32cd32; font-size: 12px;">✓ 遠距離に強い</div>
                        <div style="color: #ff6b6b; font-size: 12px;">✗ 騎兵に弱い</div>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <div style="color: #ffd700; font-weight: bold; margin-bottom: 5px;">🐴 騎兵</div>
                        <div style="color: #32cd32; font-size: 12px;">✓ 歩兵に強い</div>
                        <div style="color: #ff6b6b; font-size: 12px;">✗ 遠距離に弱い</div>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <div style="color: #ffd700; font-weight: bold; margin-bottom: 5px;">🏹 遠距離</div>
                        <div style="color: #32cd32; font-size: 12px;">✓ 騎兵に強い</div>
                        <div style="color: #ff6b6b; font-size: 12px;">✗ 歩兵に弱い</div>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <div style="color: #ffd700; font-weight: bold; margin-bottom: 5px;">💣 攻城</div>
                        <div style="color: #32cd32; font-size: 12px;">✓ 歩兵に強い</div>
                        <div style="color: #ff6b6b; font-size: 12px;">✗ 騎兵に弱い</div>
                    </div>
                </div>
            </div>
        `;
        
        troopsList.innerHTML = advantageHtml + troops.map(t => {
            const owned = userTroops.find(ut => ut.troop_type_id == t.id);
            const ownedCount = owned ? owned.count : 0;
            
            let costText = `🪙 ${t.train_cost_coins}`;
            if (t.train_cost_resources) {
                const costs = JSON.parse(t.train_cost_resources);
                Object.entries(costs).forEach(([key, val]) => {
                    const resName = getResourceName(key);
                    costText += ` | ${resName}: ${val}`;
                });
            }
            
            // 前提条件表示
            const canTrain = t.can_train !== false;
            const missingPrereqs = t.missing_prerequisites || [];
            const prereqText = missingPrereqs.length > 0 
                ? `<div style="color: #ff6b6b; font-size: 12px; margin-bottom: 10px;">🔒 必要: ${missingPrereqs.join(', ')}</div>` 
                : '';
            
            // 兵種カテゴリと相性を表示
            const category = t.troop_category || 'infantry';
            const categoryInfo = advantageInfo[category] || advantageInfo['infantry'];
            const healthPoints = t.health_points || 100;
            
            // 特殊スキル情報を構築
            let skillHtml = '';
            if (t.skill_name && t.skill_icon) {
                const effectType = t.effect_type || '';
                let effectColor = 'rgba(147, 112, 219, 0.4)'; // デフォルト紫色
                if (effectType === 'buff') {
                    effectColor = 'rgba(50, 205, 50, 0.4)'; // バフは緑
                } else if (effectType === 'debuff') {
                    effectColor = 'rgba(255, 100, 100, 0.4)'; // デバフは赤
                } else if (effectType === 'damage_over_time') {
                    effectColor = 'rgba(255, 165, 0, 0.4)'; // 継続ダメージはオレンジ
                }
                const activationChance = t.activation_chance ? `${t.activation_chance}%` : '';
                const effectValue = t.effect_value ? t.effect_value : '';
                const durationTurns = t.duration_turns ? `${t.duration_turns}T` : '';
                
                skillHtml = `
                    <div style="background: ${effectColor}; padding: 6px 10px; border-radius: 6px; margin-bottom: 8px; font-size: 12px;">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span style="font-size: 14px;">${t.skill_icon}</span>
                            <span style="color: #ffd700; font-weight: bold;">${t.skill_name}</span>
                            ${activationChance ? `<span style="color: #888; font-size: 10px; margin-left: auto;">発動: ${activationChance}</span>` : ''}
                        </div>
                        ${t.skill_description ? `<div style="color: #c0a080; font-size: 11px; margin-top: 3px;">${t.skill_description}</div>` : ''}
                    </div>
                `;
            }
            
            // ステルス兵種インジケーター
            const stealthBadge = isStealthUnit(t) ? `
                <span style="background: rgba(128, 0, 128, 0.5); padding: 3px 8px; border-radius: 4px; font-size: 11px;" title="敵から見えない隠密兵種">
                    👻 ステルス
                </span>
            ` : '';
            
            // 核ユニットインジケーター
            const nuclearBadge = isNuclearUnit(t) ? `
                <span style="background: rgba(50, 205, 50, 0.5); padding: 3px 8px; border-radius: 4px; font-size: 11px;" title="核兵器搭載ユニット">
                    ☢️ 核
                </span>
            ` : '';
            
            // 領域カテゴリインジケーター（陸・海・空）
            const domainCategory = t.domain_category || 'land';
            const domainIcons = {
                'land': { icon: '🏔️', name: '陸', color: 'rgba(139, 90, 43, 0.5)' },
                'sea': { icon: '🌊', name: '海', color: 'rgba(30, 144, 255, 0.5)' },
                'air': { icon: '✈️', name: '空', color: 'rgba(135, 206, 235, 0.5)' }
            };
            const domainInfo = domainIcons[domainCategory] || domainIcons['land'];
            const domainBadge = `
                <span style="background: ${domainInfo.color}; padding: 3px 8px; border-radius: 4px; font-size: 11px;" title="領域カテゴリ: ${domainInfo.name}">
                    ${domainInfo.icon} ${domainInfo.name}
                </span>
            `;
            
            // 使い捨てユニットインジケーター
            const disposableBadge = t.is_disposable ? `
                <span style="background: rgba(255, 69, 0, 0.5); padding: 3px 8px; border-radius: 4px; font-size: 11px;" title="出撃後は消滅する使い捨てユニット">
                    💀 使い捨て
                </span>
            ` : '';
            
            return `
                <div class="target-card" style="border-color: #8b4513; ${!canTrain ? 'opacity: 0.7;' : ''}">
                    <div class="target-header">
                        <span class="target-name">${t.icon} ${t.name}</span>
                        <span class="target-power">×${ownedCount}</span>
                    </div>
                    <div style="color: #888; font-size: 13px; margin-bottom: 5px;">
                        ${t.description || ''}
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                        <span style="background: rgba(139, 69, 19, 0.5); padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                            ${categoryInfo.icon} ${categoryInfo.name}
                        </span>
                        ${domainBadge}
                        <span style="background: rgba(220, 20, 60, 0.3); padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                            ⚔️ ${t.attack_power}
                        </span>
                        <span style="background: rgba(70, 130, 180, 0.3); padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                            🛡️ ${t.defense_power}
                        </span>
                        <span style="background: rgba(50, 205, 50, 0.3); padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                            ❤️ ${healthPoints}
                        </span>
                        ${stealthBadge}
                        ${nuclearBadge}
                        ${disposableBadge}
                    </div>
                    ${skillHtml}
                    <div style="color: #c0a080; font-size: 12px; margin-bottom: 10px;">
                        ${costText}
                    </div>
                    ${prereqText}
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <input type="range" class="troop-select-slider" id="train-slider-${t.id}" min="1" max="100" value="1" 
                               oninput="document.getElementById('troop-count-${t.id}').value = this.value" ${!canTrain ? 'disabled' : ''}>
                        <input type="number" id="troop-count-${t.id}" value="1" min="1" max="100" style="width: 60px; padding: 8px; background: rgba(0,0,0,0.3); border: 1px solid #8b4513; border-radius: 4px; color: #f5deb3;" 
                               oninput="document.getElementById('train-slider-${t.id}').value = Math.min(100, Math.max(1, this.value))" ${!canTrain ? 'disabled' : ''}>
                        <button class="attack-btn" onclick="trainTroops(${t.id})" style="background: linear-gradient(135deg, #8b4513 0%, #d4a574 100%); flex: 1;" ${!canTrain ? 'disabled' : ''}>
                            ${!canTrain ? '🔒 ロック中' : '訓練する'}
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    } else {
        troopsList.innerHTML = '<p style="color: #888;">フィルター条件に一致する兵種がありません。</p>';
    }
}

// 兵種を読み込む
async function loadTroops() {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_troops'})
        });
        const data = await res.json();
        
        if (data.ok) {
            // ① グローバル変数にデータを保存（フィルター用）
            allAvailableTroops = data.available_troops || [];
            allUserTroops = data.user_troops || [];
            troopAdvantageInfo = data.troop_advantage_info || {
                'infantry': {name: '歩兵', icon: '🗡️', strong_against: 'ranged', weak_against: 'cavalry'},
                'cavalry': {name: '騎兵', icon: '🐴', strong_against: 'infantry', weak_against: 'ranged'},
                'ranged': {name: '遠距離', icon: '🏹', strong_against: 'cavalry', weak_against: 'infantry'},
                'siege': {name: '攻城', icon: '💣', strong_against: 'infantry', weak_against: 'cavalry'}
            };
            
            // ① フィルターを適用してレンダリング
            applyTroopFilters();
        }
    } catch (e) {
        console.error(e);
        document.getElementById('troopsList').innerHTML = '<p style="color: #888;">兵種の読み込みに失敗しました</p>';
    }
}

// 兵士を訓練
async function trainTroops(troopTypeId) {
    const countInput = document.getElementById(`troop-count-${troopTypeId}`);
    const count = parseInt(countInput ? countInput.value : 1);
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'queue_training', troop_type_id: troopTypeId, count: count})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
            loadTroops();
            loadTrainingQueue();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 訓練キューを読み込む
async function loadTrainingQueue() {
    try {
        // 訓練完了をチェック
        await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'complete_training'})
        });
        
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_training_queue'})
        });
        const data = await res.json();
        
        const container = document.getElementById('trainingQueueList');
        if (!container) return;
        
        // キュー上限情報を表示
        const queueUsed = data.queue_used || 0;
        const queueMax = data.queue_max || 1;
        const queuePercent = Math.min(100, Math.round((queueUsed / queueMax) * 100));
        const queueColor = queueUsed >= queueMax ? '#ff6b6b' : (queueUsed >= queueMax * 0.7 ? '#ffa500' : '#32cd32');
        
        let queueInfoHtml = `
            <div style="margin-bottom: 15px; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="color: #888;">🏭 訓練キュー:</span>
                    <span style="color: ${queueColor};">${queueUsed} / ${queueMax}</span>
                </div>
                <div style="background: rgba(0,0,0,0.5); border-radius: 4px; height: 8px; overflow: hidden;">
                    <div style="background: ${queueColor}; height: 100%; width: ${queuePercent}%; transition: width 0.3s;"></div>
                </div>
                <div style="color: #888; font-size: 11px; margin-top: 5px;">💡 兵舎を建設するとキュー数が増えます</div>
            </div>
        `;
        
        if (data.ok && data.training_queue && data.training_queue.length > 0) {
            container.innerHTML = queueInfoHtml + data.training_queue.map(q => {
                const completesAt = new Date(q.training_completes_at);
                const remaining = Math.max(0, Math.floor((completesAt - Date.now()) / 1000));
                const remainingText = formatTime(remaining);
                
                return `
                    <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; margin-bottom: 8px;">
                        <div>
                            <span>${q.icon} ${q.name} ×${q.count}</span>
                            <span style="color: #87ceeb; margin-left: 10px;">⏱️ ${remainingText}</span>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <button class="quick-invest-btn" onclick="instantCompleteQueue('training', ${q.id}, 'crystal')" style="font-size: 11px;">💎 即完了</button>
                            <button class="quick-invest-btn" onclick="instantCompleteQueue('training', ${q.id}, 'diamond')" style="font-size: 11px;">💠 即完了</button>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            container.innerHTML = queueInfoHtml + '<p style="color: #888;">訓練中の兵士はいません</p>';
        }
    } catch (e) {
        console.error(e);
    }
}

// 負傷兵と治療キューを読み込む
async function loadWoundedTroops() {
    try {
        // 治療完了をチェック
        await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'complete_healing'})
        });
        
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_wounded_troops'})
        });
        const data = await res.json();
        
        const woundedContainer = document.getElementById('woundedTroopsList');
        const healingContainer = document.getElementById('healingQueueList');
        
        if (!woundedContainer) return;
        
        if (data.ok) {
            // キュー上限情報
            const queueUsed = data.queue_used || 0;
            const queueMax = data.queue_max || 1;
            const queuePercent = Math.min(100, Math.round((queueUsed / queueMax) * 100));
            const queueColor = queueUsed >= queueMax ? '#ff6b6b' : (queueUsed >= queueMax * 0.7 ? '#ffa500' : '#32cd32');
            
            // 16: 病床使用状況
            const bedsUsed = data.beds_used || 0;
            const bedsMax = data.hospital_capacity || 0;
            const bedsAvailable = data.beds_available || 0;
            const bedsPercent = bedsMax > 0 ? Math.min(100, Math.round((bedsUsed / bedsMax) * 100)) : 0;
            const bedsColor = bedsUsed >= bedsMax ? '#ff6b6b' : (bedsUsed >= bedsMax * 0.7 ? '#ffa500' : '#32cd32');
            
            // 負傷兵リスト
            if (data.wounded_troops && data.wounded_troops.length > 0) {
                woundedContainer.innerHTML = `
                    <div style="margin-bottom: 15px; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: #888;">🏥 治療キュー:</span>
                            <span style="color: ${queueColor};">${queueUsed} / ${queueMax}</span>
                        </div>
                        <div style="background: rgba(0,0,0,0.5); border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: ${queueColor}; height: 100%; width: ${queuePercent}%; transition: width 0.3s;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 10px; margin-bottom: 5px;">
                            <span style="color: #888;">🛏️ 残存病床:</span>
                            <span style="color: ${bedsColor};">${bedsAvailable} / ${bedsMax}床 (使用中: ${bedsUsed})</span>
                        </div>
                        <div style="background: rgba(0,0,0,0.5); border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: ${bedsColor}; height: 100%; width: ${bedsPercent}%; transition: width 0.3s;"></div>
                        </div>
                        <div style="color: #888; font-size: 11px; margin-top: 5px;">💡 病院を建設するとキュー数・病床数が増えます</div>
                    </div>
                    ${data.wounded_troops.map(w => {
                        let healCostText = `🪙${w.heal_cost_coins}/体`;
                        if (w.heal_cost_resources) {
                            try {
                                const healCosts = typeof w.heal_cost_resources === 'string' ? JSON.parse(w.heal_cost_resources) : w.heal_cost_resources;
                                if (healCosts) {
                                    Object.entries(healCosts).forEach(([key, val]) => {
                                        const resName = getResourceName(key);
                                        healCostText += ` | ${resName}: ${val}`;
                                    });
                                }
                            } catch(e) {
                                console.warn('Failed to parse heal_cost_resources:', e);
                            }
                        }
                        return `
                        <div style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <div>
                                    <span>${w.icon} ${w.name} ×${w.count}</span>
                                    <span style="color: #888; font-size: 11px; margin-left: 10px;">治療: ${w.heal_time_seconds}秒/体</span>
                                </div>
                            </div>
                            <div style="color: #48bb78; font-size: 11px; margin-bottom: 8px;">💊 コスト: ${healCostText}</div>
                            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                <input type="range" class="troop-select-slider" id="heal-slider-${w.troop_type_id}" min="1" max="${w.count}" value="1" 
                                       style="background: #dc143c;" oninput="document.getElementById('heal-count-${w.troop_type_id}').value = this.value">
                                <input type="number" id="heal-count-${w.troop_type_id}" value="1" min="1" max="${w.count}" style="width: 50px; padding: 5px; background: rgba(0,0,0,0.3); border: 1px solid #dc143c; border-radius: 4px; color: #f5deb3;"
                                       oninput="document.getElementById('heal-slider-${w.troop_type_id}').value = Math.min(${w.count}, Math.max(1, this.value))">
                                <button class="quick-invest-btn" onclick="healTroops(${w.troop_type_id})" style="background: linear-gradient(135deg, #32cd32 0%, #228b22 100%); color: #fff; flex: 1;">🏥 治療</button>
                            </div>
                        </div>
                    `}).join('')}
                `;
            } else {
                woundedContainer.innerHTML = '<p style="color: #888;">負傷兵はいません</p>';
            }
            
            // 治療キュー
            if (healingContainer && data.healing_queue && data.healing_queue.length > 0) {
                healingContainer.innerHTML = `
                    <h4 style="color: #90ee90; margin-bottom: 10px;">💉 治療中</h4>
                    ${data.healing_queue.map(h => {
                        const completesAt = new Date(h.healing_completes_at);
                        const remaining = Math.max(0, Math.floor((completesAt - Date.now()) / 1000));
                        const remainingText = formatTime(remaining);
                        
                        return `
                            <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(50, 205, 50, 0.2); padding: 10px; border-radius: 8px; margin-bottom: 8px;">
                                <div>
                                    <span>${h.icon} ${h.name} ×${h.count}</span>
                                    <span style="color: #90ee90; margin-left: 10px;">⏱️ ${remainingText}</span>
                                </div>
                                <div style="display: flex; gap: 5px;">
                                    <button class="quick-invest-btn" onclick="instantCompleteQueue('healing', ${h.id}, 'crystal')" style="font-size: 11px;">💎 即完了</button>
                                    <button class="quick-invest-btn" onclick="instantCompleteQueue('healing', ${h.id}, 'diamond')" style="font-size: 11px;">💠 即完了</button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                `;
            } else if (healingContainer) {
                healingContainer.innerHTML = '';
            }
            
            // 負傷兵バッジを更新
            const woundedCount = data.wounded_troops ? data.wounded_troops.reduce((sum, w) => sum + (w.count || 0), 0) : 0;
            const woundedBadge = document.getElementById('wounded-badge');
            if (woundedBadge) {
                if (woundedCount > 0) {
                    woundedBadge.textContent = woundedCount;
                    woundedBadge.style.display = 'inline-block';
                } else {
                    woundedBadge.style.display = 'none';
                }
            }
        }
    } catch (e) {
        console.error(e);
    }
}

// 負傷兵を治療
async function healTroops(troopTypeId) {
    const countInput = document.getElementById(`heal-count-${troopTypeId}`);
    const count = parseInt(countInput ? countInput.value : 1);
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'heal_troops', troop_type_id: troopTypeId, count: count})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadWoundedTroops();
            loadData();
            updateTabBadges(); // バッジを更新
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 訓練・治療を即完了
async function instantCompleteQueue(queueType, queueId, currency) {
    const currencyName = currency === 'crystal' ? 'クリスタル' : 'ダイヤモンド';
    if (!confirm(`${currencyName}を使用して即座に完了しますか？`)) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'instant_complete_queue', queue_type: queueType, queue_id: queueId, currency: currency})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
            loadTrainingQueue();
            loadWoundedTroops();
            loadTroops();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 防御設定を読み込む
async function loadDefenseSettings() {
    try {
        // 利用可能な兵士を取得
        const troopsRes = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_troops'})
        });
        const troopsData = await troopsRes.json();
        
        // 現在の防御設定を取得
        const defenseRes = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_defense_troops'})
        });
        const defenseData = await defenseRes.json();
        
        const container = document.getElementById('defenseSettingsList');
        if (!container) return;
        
        if (troopsData.ok && troopsData.user_troops && troopsData.user_troops.length > 0) {
            const defenseTroops = defenseData.ok ? (defenseData.defense_troops || []) : [];
            
            container.innerHTML = `
                <div style="margin-bottom: 15px;">
                    ${troopsData.user_troops.filter(t => t.count > 0).map(t => {
                        const assigned = defenseTroops.find(d => d.troop_type_id == t.troop_type_id);
                        const assignedCount = assigned ? assigned.assigned_count : 0;
                        
                        return `
                            <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; margin-bottom: 8px;">
                                <div>
                                    <span>${t.icon} ${t.name}</span>
                                    <span style="color: #888; margin-left: 10px;">所持: ${t.count}</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="range" id="defense-slider-${t.troop_type_id}" 
                                           min="0" max="${t.count}" value="${assignedCount}"
                                           style="width: 100px;"
                                           oninput="document.getElementById('defense-count-${t.troop_type_id}').value = this.value">
                                    <input type="number" id="defense-count-${t.troop_type_id}" 
                                           min="0" max="${t.count}" value="${assignedCount}"
                                           style="width: 60px; padding: 5px; background: rgba(0,0,0,0.3); border: 1px solid #32cd32; border-radius: 4px; color: #f5deb3;"
                                           oninput="document.getElementById('defense-slider-${t.troop_type_id}').value = this.value">
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
                <button class="invest-btn" onclick="saveDefenseSettings()" style="background: linear-gradient(135deg, #32cd32 0%, #228b22 100%);">
                    🛡️ 防御設定を保存
                </button>
            `;
        } else {
            container.innerHTML = '<p style="color: #888;">兵士がいません。まず兵士を訓練してください。</p>';
        }
    } catch (e) {
        console.error(e);
    }
}

// 防御設定を保存
async function saveDefenseSettings() {
    const troops = [];
    document.querySelectorAll('[id^="defense-count-"]').forEach(input => {
        const troopTypeId = parseInt(input.id.replace('defense-count-', ''));
        const count = parseInt(input.value) || 0;
        if (count > 0) {
            troops.push({troop_type_id: troopTypeId, count: count});
        }
    });
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'set_defense_troops', troops: troops})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 時間をフォーマット
function formatTime(seconds) {
    if (seconds <= 0) return '完了';
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) return `${hours}時間${mins}分`;
    if (mins > 0) return `${mins}分${secs}秒`;
    return `${secs}秒`;
}

// VIPブーストを購入
async function buyVipBoost(boostType) {
    if (!confirm('ダイヤモンドを消費してブーストを購入しますか？')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'buy_vip_boost', boost_type: boostType})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 攻撃
async function attack(targetUserId) {
    if (!confirm('この文明を攻撃しますか？')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'attack', target_user_id: targetUserId})
        });
        const data = await res.json();
        
        if (data.ok) {
            const isVictory = data.result === 'victory';
            showNotification(data.message, !isVictory);
            if (isVictory) {
                loadData();
            }
            // 攻撃後、レート制限状態のみを更新（効率的）
            updateWarRateLimitStatus();
        } else {
            showNotification(data.error, true);
            // エラーの場合もレート制限状態を更新（制限到達の可能性）
            if (data.rate_limited) {
                updateWarRateLimitStatus();
            }
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 文明名変更
async function renameCiv() {
    const newName = prompt('新しい文明名を入力してください:', civData.civilization.civilization_name);
    if (!newName || newName === civData.civilization.civilization_name) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'rename', name: newName})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// ユーティリティ関数
function formatTime(seconds) {
    if (seconds <= 0) return '完了';
    if (seconds < 60) return `${seconds}秒`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}分${seconds % 60}秒`;
    return `${Math.floor(seconds / 3600)}時間${Math.floor((seconds % 3600) / 60)}分`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, isError = false) {
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = `notification ${isError ? 'error' : ''}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => notification.remove(), 4000);
}

// 戦争レート制限の状態のみを更新
async function updateWarRateLimitStatus() {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_war_rate_limit_status'})
        });
        const rateLimitData = await res.json();
        updateWarRateLimitDisplay(rateLimitData);
    } catch (e) {
        console.error('Failed to update war rate limit status:', e);
    }
}

// 戦争レート制限の状態を表示
function updateWarRateLimitDisplay(rateLimitData) {
    if (!rateLimitData || !rateLimitData.ok) return;
    
    const section = document.getElementById('warRateLimitSection');
    const remainingAttacksEl = document.getElementById('remainingAttacks');
    const progressBar = document.getElementById('rateLimitProgressBar');
    const messageEl = document.getElementById('rateLimitMessage');
    
    if (!section || !remainingAttacksEl || !progressBar || !messageEl) return;
    
    const attackCount = rateLimitData.attack_count || 0;
    const maxAttacks = rateLimitData.max_attacks || 3;
    const remainingAttacks = rateLimitData.remaining_attacks || 0;
    const isLimited = rateLimitData.is_limited || false;
    const waitSeconds = rateLimitData.wait_seconds || 0;
    
    // セクションを表示
    section.style.display = 'block';
    
    // 残り回数を表示
    remainingAttacksEl.textContent = `${remainingAttacks} / ${maxAttacks}`;
    
    // プログレスバーの幅を設定（使用した割合）
    const usedPercentage = (attackCount / maxAttacks) * 100;
    progressBar.style.width = `${usedPercentage}%`;
    
    // プログレスバーの色を変更（残り回数に応じて）
    if (remainingAttacks === 0) {
        progressBar.style.background = '#ff6b6b'; // 赤（制限中）
    } else if (remainingAttacks === 1) {
        progressBar.style.background = '#ffd700'; // 黄色（残り1回）
    } else {
        progressBar.style.background = 'linear-gradient(90deg, #32cd32 0%, #ffd700 50%, #ff6b6b 100%)';
    }
    
    // メッセージを表示
    if (isLimited && waitSeconds > 0) {
        const hours = Math.floor(waitSeconds / 3600);
        const mins = Math.floor((waitSeconds % 3600) / 60);
        
        let timeText = '';
        if (hours > 0 && mins > 0) {
            timeText = `${hours}時間${mins}分`;
        } else if (hours > 0) {
            timeText = `${hours}時間`;
        } else if (mins > 0) {
            timeText = `${mins}分`;
        } else {
            timeText = '1分未満';
        }
        
        messageEl.innerHTML = `⚠️ <span style="color: #ff6b6b; font-weight: bold;">レート制限中</span> - 次の攻撃まで <span style="color: #ffd700; font-weight: bold;">${timeText}</span> お待ちください`;
        section.style.borderColor = '#ff6b6b';
        section.style.background = 'rgba(139, 0, 0, 0.3)';
    } else if (remainingAttacks === 1) {
        messageEl.innerHTML = `⚠️ 残り <span style="color: #ffd700; font-weight: bold;">1回</span> のみ攻撃可能です`;
        section.style.borderColor = '#ffd700';
        section.style.background = 'rgba(255, 215, 0, 0.1)';
    } else if (remainingAttacks === 2) {
        messageEl.innerHTML = `✅ 残り <span style="color: #32cd32; font-weight: bold;">2回</span> 攻撃可能です`;
        section.style.borderColor = '#32cd32';
        section.style.background = 'rgba(50, 205, 50, 0.1)';
    } else {
        messageEl.innerHTML = `✅ <span style="color: #32cd32; font-weight: bold;">すべて利用可能</span> - ${maxAttacks}回攻撃できます`;
        section.style.borderColor = '#32cd32';
        section.style.background = 'rgba(50, 205, 50, 0.1)';
    }
    
    // テーブルが存在しない場合の警告
    if (rateLimitData.table_missing) {
        const warningSpan = document.createElement('span');
        warningSpan.style.color = '#ff6b6b';
        warningSpan.style.fontSize = '11px';
        warningSpan.textContent = '⚠️ レート制限機能が利用できません。管理者に連絡してください。';
        messageEl.appendChild(document.createElement('br'));
        messageEl.appendChild(warningSpan);
    }
}

// 建物を即完了
async function instantCompleteBuilding(buildingId) {
    if (!confirm('クリスタルを消費して建設を即完了しますか？')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'instant_complete_building', building_id: buildingId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 建物をダイヤモンドで即完了
async function instantCompleteBuildingDiamond(buildingId) {
    if (!confirm('ダイヤモンドを消費して建設を即完了しますか？')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'instant_complete_building_diamond', building_id: buildingId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 研究を即完了
async function instantCompleteResearch(userResearchId) {
    if (!confirm('クリスタルを消費して研究を即完了しますか？')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'instant_complete_research', user_research_id: userResearchId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 研究をダイヤモンドで即完了
async function instantCompleteResearchDiamond(userResearchId) {
    if (!confirm('ダイヤモンドを消費して研究を即完了しますか？')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'instant_complete_research_diamond', user_research_id: userResearchId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 完了チェックとUIリフレッシュ
async function checkCompletions() {
    let needsRefresh = false;
    
    // 完了した建物を確認
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'complete_buildings'})
        });
        const data = await res.json();
        if (data.ok && data.count > 0) {
            let message = `建設完了: ${data.completed.join(', ')}`;
            if (data.population_increase > 0) {
                message += ` (+${data.population_increase}人口)`;
            }
            showNotification(message);
            needsRefresh = true;
        }
    } catch (e) {
        console.error('Building check error:', e);
    }
    
    // 完了した研究を確認
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'complete_researches'})
        });
        const data = await res.json();
        if (data.ok && data.count > 0) {
            showNotification(`研究完了: ${data.completed.join(', ')}`);
            needsRefresh = true;
        }
    } catch (e) {
        console.error('Research check error:', e);
    }
    
    if (needsRefresh) {
        loadData();
    }
}

// 定期的にデータを更新（10秒ごとにカウントダウンを更新し、完了チェック）
let updateInterval = null;
let isUserInteracting = false;
let interactionTimeout = null;

// ユーザー操作検出用のフラグを設定
function setUserInteracting() {
    isUserInteracting = true;
    
    // 既存のタイムアウトをクリア
    if (interactionTimeout) {
        clearTimeout(interactionTimeout);
    }
    
    // 2秒間操作がなければフラグを解除
    interactionTimeout = setTimeout(() => {
        isUserInteracting = false;
    }, 2000);
}

// スクロールイベントのスロットリング
let scrollThrottleTimer = null;
function handleScrollThrottled() {
    if (!scrollThrottleTimer) {
        scrollThrottleTimer = setTimeout(() => {
            setUserInteracting();
            scrollThrottleTimer = null;
        }, 100);
    }
}

// ユーザー操作イベントを監視
function setupInteractionListeners() {
    // 入力フィールドのフォーカスと入力
    document.addEventListener('focusin', (e) => {
        if (e.target.matches('input, select, textarea, button, [contenteditable]')) {
            setUserInteracting();
        }
    });
    
    document.addEventListener('input', (e) => {
        if (e.target.matches('input, select, textarea')) {
            setUserInteracting();
        }
    });
    
    // セレクトボックスの操作（特にドロップダウンを開いている時）
    document.addEventListener('change', (e) => {
        if (e.target.matches('select, input')) {
            setUserInteracting();
        }
    });
    
    // スクロール操作（スロットリング済み）
    document.addEventListener('scroll', handleScrollThrottled, true);
    
    // マウスダウン（スライダー、ボタン操作など）
    document.addEventListener('mousedown', (e) => {
        if (e.target.matches('input[type="range"], button, .ranking-btn, .resource-ranking-btn, select')) {
            setUserInteracting();
        }
    });
    
    // タッチ操作（モバイル対応）
    document.addEventListener('touchstart', (e) => {
        if (e.target.matches('input[type="range"], input[type="number"], button, .ranking-btn, .resource-ranking-btn, select')) {
            setUserInteracting();
        }
    }, { passive: true });
    
    // モーダル表示中はインタラクションとみなす
    document.addEventListener('click', (e) => {
        // モーダルやダイアログ内のクリック
        if (e.target.closest('.modal, .dialog, [role="dialog"]')) {
            setUserInteracting();
        }
    });
    
    // キーボード操作
    document.addEventListener('keydown', (e) => {
        // 入力フィールド内のキー操作
        if (e.target.matches('input, select, textarea, [contenteditable]')) {
            setUserInteracting();
        }
    });
}

function startUpdateTimer() {
    if (updateInterval) clearInterval(updateInterval);
    
    updateInterval = setInterval(() => {
        // ユーザー操作中は更新をスキップ
        if (isUserInteracting) {
            return;
        }
        
        // ① 兵士タブがアクティブな場合、フィルター使用中またはスクロール中は更新をスキップ
        if (currentTab === 'troops') {
            const hasActiveFilter = troopFilterState.categoryFilter || troopFilterState.domainFilter || 
                                    troopFilterState.stealthFilter || troopFilterState.nuclearFilter || 
                                    troopFilterState.disposableFilter;
            if (hasActiveFilter) {
                // フィルター使用中は更新をスキップ
                return;
            }
            // スクロール位置を保存
            const troopsList = document.getElementById('troopsList');
            if (troopsList) {
                troopScrollPosition = troopsList.scrollTop;
            }
        }
        
        // 完了チェック
        checkCompletions();
        
        // カウントダウンを更新するため、全体を再描画
        if (civData) {
            renderApp();
            
            // ① 兵士タブがアクティブな場合、スクロール位置とフィルターを復元
            if (currentTab === 'troops') {
                restoreTroopFilters();
                const troopsList = document.getElementById('troopsList');
                if (troopsList && troopScrollPosition > 0) {
                    troopsList.scrollTop = troopScrollPosition;
                }
            }
            
            // メールタブがアクティブな場合、偵察レート制限表示を更新
            if (currentTab === 'mail') {
                loadReconnaissanceStatus();
            }
        }
    }, 10000); // 10秒ごと
}

// ===============================================
// 同盟システムの関数
// ===============================================

// 同盟データを読み込む
async function loadAllianceData() {
    try {
        // 同盟情報を取得
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_alliances'})
        });
        const data = await res.json();
        
        if (data.ok) {
            // 同盟申請フォーム（攻撃対象リストから選択可能にする）
            const targetsRes = await fetch('civilization_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_targets'})
            });
            const targetsData = await targetsRes.json();
            
            // 同盟申請セクション
            if (targetsData.ok && targetsData.targets) {
                const allyIds = data.active_alliances.map(a => a.ally_user_id);
                const pendingIds = [...data.pending_sent.map(p => p.target_user_id), ...data.pending_received.map(p => p.requester_user_id)];
                const availableTargets = targetsData.targets.filter(t => !allyIds.includes(t.user_id) && !pendingIds.includes(t.user_id));
                
                document.getElementById('allianceRequestSection').innerHTML = availableTargets.length > 0 ? `
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <select id="allianceRequestTarget" style="flex: 1; min-width: 200px; padding: 10px; background: rgba(0,0,0,0.3); border: 1px solid #4682b4; border-radius: 8px; color: #f5deb3;">
                            <option value="">-- 対象を選択 --</option>
                            ${availableTargets.map(t => `<option value="${t.user_id}">${escapeHtml(t.civilization_name)} (@${escapeHtml(t.handle)})</option>`).join('')}
                        </select>
                        <button class="invest-btn" onclick="requestAlliance()" style="background: linear-gradient(135deg, #4682b4 0%, #6495ed 100%);">
                            🤝 同盟を申請
                        </button>
                    </div>
                ` : '<p style="color: #888;">同盟申請可能な文明がありません</p>';
            }
            
            // 受信した申請
            const receivedHtml = data.pending_received.length > 0 ? data.pending_received.map(p => `
                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; margin-bottom: 8px; border-left: 3px solid #ffa500;">
                    <div>
                        <span style="color: #ffd700; font-weight: bold;">${escapeHtml(p.requester_civ_name || '不明')}</span>
                        <span style="color: #888; margin-left: 10px;">(@${escapeHtml(p.requester_handle)})</span>
                        <div style="color: #888; font-size: 11px; margin-top: 3px;">${new Date(p.requested_at).toLocaleString('ja-JP')}</div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button onclick="respondAlliance(${p.id}, true)" style="padding: 8px 15px; background: linear-gradient(135deg, #32cd32 0%, #228b22 100%); color: #fff; border: none; border-radius: 6px; cursor: pointer;">
                            ✅ 承認
                        </button>
                        <button onclick="respondAlliance(${p.id}, false)" style="padding: 8px 15px; background: linear-gradient(135deg, #dc143c 0%, #8b0000 100%); color: #fff; border: none; border-radius: 6px; cursor: pointer;">
                            ❌ 拒否
                        </button>
                    </div>
                </div>
            `).join('') : '<p style="color: #888;">受信した申請はありません</p>';
            document.getElementById('pendingAlliancesReceived').innerHTML = receivedHtml;
            
            // 送信した申請
            const sentHtml = data.pending_sent.length > 0 ? data.pending_sent.map(p => `
                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; margin-bottom: 8px; border-left: 3px solid #4682b4;">
                    <div>
                        <span style="color: #87ceeb; font-weight: bold;">${escapeHtml(p.target_civ_name || '不明')}</span>
                        <span style="color: #888; margin-left: 10px;">(@${escapeHtml(p.target_handle)})</span>
                        <div style="color: #888; font-size: 11px; margin-top: 3px;">申請日: ${new Date(p.requested_at).toLocaleString('ja-JP')}</div>
                    </div>
                    <button onclick="cancelAllianceRequest(${p.id})" style="padding: 8px 15px; background: rgba(255,255,255,0.1); color: #888; border: 1px solid #888; border-radius: 6px; cursor: pointer;">
                        キャンセル
                    </button>
                </div>
            `).join('') : '<p style="color: #888;">送信した申請はありません</p>';
            document.getElementById('pendingAlliancesSent').innerHTML = sentHtml;
            
            // 締結済み同盟
            const activeHtml = data.active_alliances.length > 0 ? data.active_alliances.map(a => `
                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; margin-bottom: 8px; border-left: 3px solid #32cd32;">
                    <div>
                        <span style="color: #32cd32; font-weight: bold;">🤝 ${escapeHtml(a.ally_civ_name || '不明')}</span>
                        <span style="color: #888; margin-left: 10px;">(@${escapeHtml(a.ally_handle)})</span>
                        <div style="color: #888; font-size: 11px; margin-top: 3px;">同盟締結: ${new Date(a.allied_at).toLocaleString('ja-JP')}</div>
                    </div>
                    <button onclick="breakAlliance(${a.id})" style="padding: 8px 15px; background: linear-gradient(135deg, #dc143c 0%, #8b0000 100%); color: #fff; border: none; border-radius: 6px; cursor: pointer;">
                        💔 解消
                    </button>
                </div>
            `).join('') : '<p style="color: #888;">同盟国はいません</p>';
            document.getElementById('activeAlliancesList').innerHTML = activeHtml;
            
            // 援助対象ドロップダウンを更新
            const transferSelect = document.getElementById('transferTarget');
            if (transferSelect && data.active_alliances.length > 0) {
                transferSelect.innerHTML = '<option value="">-- 同盟国を選択 --</option>' + 
                    data.active_alliances.map(a => `<option value="${a.ally_user_id}">${escapeHtml(a.ally_civ_name)} (@${escapeHtml(a.ally_handle)})</option>`).join('');
                transferSelect.onchange = () => loadTransferSections();
            }
            
            // 援助ログを読み込む
            loadTransferLogs();
            
            // 大使館制限情報を読み込む
            loadEmbassyLimits();
        }
    } catch (e) {
        console.error(e);
    }
}

// 大使館制限情報を読み込む
async function loadEmbassyLimits() {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_embassy_limits'})
        });
        const data = await res.json();
        
        const infoDiv = document.getElementById('embassyLimitsInfo');
        if (!infoDiv) return;
        
        if (data.ok) {
            if (data.embassy_level === 0) {
                infoDiv.innerHTML = `
                    <div style="color: #ff6b6b; font-weight: bold; margin-bottom: 10px;">🏛️ 大使館未建設</div>
                    <p style="color: #888; margin: 0;">大使館を建設すると同盟国への援助が可能になります。</p>
                    <p style="color: #888; margin: 5px 0 0 0; font-size: 12px;">建築タブから大使館を建設してください。</p>
                `;
            } else {
                infoDiv.innerHTML = `
                    <div style="color: #87ceeb; font-weight: bold; margin-bottom: 10px;">🏛️ 大使館レベル ${data.embassy_level} - 援助制限 (1時間あたり)</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                            <div style="color: #f5deb3; font-size: 13px;">🎖️ 兵士援助上限</div>
                            <div style="color: #ffd700; font-size: 16px; font-weight: bold;">${data.troops_used_this_hour} / ${data.troop_limit_per_hour}人</div>
                            <div style="color: #888; font-size: 11px;">残り ${data.troops_available}人</div>
                        </div>
                        <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                            <div style="color: #f5deb3; font-size: 13px;">📦 資源援助上限</div>
                            <div style="color: #ffd700; font-size: 16px; font-weight: bold;">${data.resources_used_this_hour.toLocaleString()} / ${data.resource_limit_per_hour.toLocaleString()}</div>
                            <div style="color: #888; font-size: 11px;">残り ${data.resources_available.toLocaleString()}</div>
                        </div>
                    </div>
                    <p style="color: #888; margin: 10px 0 0 0; font-size: 12px;">💡 大使館をアップグレードすると、1時間あたりの援助上限が増加します（レベル×1000資源、レベル×50兵士）</p>
                `;
            }
        } else {
            infoDiv.innerHTML = '<p style="color: #ff6b6b;">大使館情報の取得に失敗しました</p>';
        }
    } catch (e) {
        console.error(e);
    }
}

// 同盟を申請
async function requestAlliance() {
    const select = document.getElementById('allianceRequestTarget');
    const targetUserId = parseInt(select ? select.value : 0);
    
    if (!targetUserId) {
        showNotification('対象を選択してください', true);
        return;
    }
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'request_alliance', target_user_id: targetUserId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadAllianceData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 同盟申請に応答
async function respondAlliance(allianceId, accept) {
    const confirmMsg = accept ? '同盟を承認しますか？' : '同盟申請を拒否しますか？';
    if (!confirm(confirmMsg)) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'respond_alliance', alliance_id: allianceId, accept: accept})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadAllianceData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 同盟を解消
async function breakAlliance(allianceId) {
    if (!confirm('本当に同盟を解消しますか？この操作は取り消せません。')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'break_alliance', alliance_id: allianceId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadAllianceData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 同盟申請をキャンセル
async function cancelAllianceRequest(allianceId) {
    if (!confirm('同盟申請をキャンセルしますか？')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'cancel_alliance_request', alliance_id: allianceId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadAllianceData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 援助セクションを読み込む
async function loadTransferSections() {
    const targetUserId = parseInt(document.getElementById('transferTarget').value) || 0;
    
    if (!targetUserId) {
        document.getElementById('troopTransferSection').innerHTML = '<p style="color: #888;">同盟国を選択してください</p>';
        document.getElementById('resourceTransferSection').innerHTML = '<p style="color: #888;">同盟国を選択してください</p>';
        return;
    }
    
    try {
        // 兵士データを取得
        const troopsRes = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_troops'})
        });
        const troopsData = await troopsRes.json();
        
        if (troopsData.ok && troopsData.user_troops && troopsData.user_troops.length > 0) {
            const availableTroops = troopsData.user_troops.filter(t => t.count > 0);
            document.getElementById('troopTransferSection').innerHTML = availableTroops.length > 0 ? availableTroops.map(t => {
                const troopId = parseInt(t.troop_type_id) || 0;
                const troopCount = parseInt(t.count) || 0;
                return `
                <div style="display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 6px; margin-bottom: 6px;">
                    <span style="min-width: 120px;">${escapeHtml(t.icon)} ${escapeHtml(t.name)}</span>
                    <input type="range" id="transfer-troop-slider-${troopId}" min="0" max="${troopCount}" value="0" style="flex: 1;" oninput="document.getElementById('transfer-troop-count-${troopId}').value = this.value">
                    <input type="number" id="transfer-troop-count-${troopId}" min="0" max="${troopCount}" value="0" style="width: 60px; padding: 5px; background: rgba(0,0,0,0.3); border: 1px solid #8b4513; border-radius: 4px; color: #f5deb3;" oninput="document.getElementById('transfer-troop-slider-${troopId}').value = this.value">
                    <span style="color: #888; font-size: 11px;">/ ${troopCount}</span>
                </div>
            `}).join('') : '<p style="color: #888;">送れる兵士がいません</p>';
        } else {
            document.getElementById('troopTransferSection').innerHTML = '<p style="color: #888;">兵士がいません</p>';
        }
        
        // 資源データを取得（civDataから）
        if (civData && civData.resources) {
            const availableResources = civData.resources.filter(r => r.unlocked && r.amount > 0);
            document.getElementById('resourceTransferSection').innerHTML = availableResources.length > 0 ? availableResources.map(r => {
                const resourceId = parseInt(r.resource_type_id) || 0;
                const resourceAmount = Math.floor(parseFloat(r.amount) || 0);
                return `
                <div style="display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 6px; margin-bottom: 6px;">
                    <span style="min-width: 120px;">${escapeHtml(r.icon)} ${escapeHtml(r.name)}</span>
                    <input type="range" id="transfer-resource-slider-${resourceId}" min="0" max="${resourceAmount}" value="0" style="flex: 1;" oninput="document.getElementById('transfer-resource-count-${resourceId}').value = this.value">
                    <input type="number" id="transfer-resource-count-${resourceId}" min="0" max="${resourceAmount}" value="0" style="width: 80px; padding: 5px; background: rgba(0,0,0,0.3); border: 1px solid #228b22; border-radius: 4px; color: #f5deb3;" oninput="document.getElementById('transfer-resource-slider-${resourceId}').value = this.value">
                    <span style="color: #888; font-size: 11px;">/ ${resourceAmount}</span>
                </div>
            `}).join('') : '<p style="color: #888;">送れる資源がありません</p>';
        }
    } catch (e) {
        console.error(e);
    }
}

// 兵士を送る
async function transferTroops() {
    const targetUserId = parseInt(document.getElementById('transferTarget').value) || 0;
    if (!targetUserId) {
        showNotification('同盟国を選択してください', true);
        return;
    }
    
    const troops = [];
    document.querySelectorAll('[id^="transfer-troop-count-"]').forEach(input => {
        const count = parseInt(input.value) || 0;
        const troopTypeId = parseInt(input.id.replace('transfer-troop-count-', ''));
        if (count > 0 && troopTypeId) {
            troops.push({troop_type_id: troopTypeId, count: count});
        }
    });
    
    if (troops.length === 0) {
        showNotification('送る兵士を選択してください', true);
        return;
    }
    
    if (!confirm('選択した兵士を送りますか？')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'transfer_troops', target_user_id: targetUserId, troops: troops})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
            loadAllianceData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 資源を送る
async function transferResources() {
    const targetUserId = parseInt(document.getElementById('transferTarget').value) || 0;
    if (!targetUserId) {
        showNotification('同盟国を選択してください', true);
        return;
    }
    
    const resources = [];
    document.querySelectorAll('[id^="transfer-resource-count-"]').forEach(input => {
        const amount = parseFloat(input.value) || 0;
        const resourceTypeId = parseInt(input.id.replace('transfer-resource-count-', ''));
        if (amount > 0 && resourceTypeId) {
            resources.push({resource_type_id: resourceTypeId, amount: amount});
        }
    });
    
    if (resources.length === 0) {
        showNotification('送る資源を選択してください', true);
        return;
    }
    
    if (!confirm('選択した資源を送りますか？')) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'transfer_resources', target_user_id: targetUserId, resources: resources})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadData();
            loadAllianceData();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        showNotification('エラーが発生しました', true);
    }
}

// 援助ログを読み込む
async function loadTransferLogs() {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_transfer_logs'})
        });
        const data = await res.json();
        
        if (data.ok) {
            let html = '';
            
            // ヘルパー関数：ユーザー表示（文明名とハンドルの両方を表示）
            const formatUserDisplay = (civName, handle) => {
                if (civName && handle) {
                    return `${escapeHtml(civName)} <span style="color: #87ceeb;">(@${escapeHtml(handle)})</span>`;
                } else if (handle) {
                    return `@${escapeHtml(handle)}`;
                } else if (civName) {
                    return escapeHtml(civName);
                }
                return '不明';
            };
            
            // 受信ログ（兵士）
            if (data.troop_received && data.troop_received.length > 0) {
                html += '<h4 style="color: #90ee90; margin-bottom: 10px;">🎁 受け取った兵士</h4>';
                html += data.troop_received.slice(0, 5).map(log => `
                    <div style="background: rgba(50, 205, 50, 0.1); padding: 8px; border-radius: 6px; margin-bottom: 5px; font-size: 12px;">
                        <span style="color: #32cd32;">${log.troop_icon} ${log.troop_name} ×${log.count}</span>
                        <span style="color: #888;"> from ${formatUserDisplay(log.sender_civ_name, log.sender_handle)}</span>
                        <span style="color: #666; font-size: 10px; margin-left: 10px;">${new Date(log.transferred_at).toLocaleString('ja-JP')}</span>
                    </div>
                `).join('');
            }
            
            // 送信ログ（兵士）
            if (data.troop_sent && data.troop_sent.length > 0) {
                html += '<h4 style="color: #87ceeb; margin: 15px 0 10px 0;">📤 送った兵士</h4>';
                html += data.troop_sent.slice(0, 5).map(log => `
                    <div style="background: rgba(70, 130, 180, 0.1); padding: 8px; border-radius: 6px; margin-bottom: 5px; font-size: 12px;">
                        <span style="color: #4682b4;">${log.troop_icon} ${log.troop_name} ×${log.count}</span>
                        <span style="color: #888;"> to ${formatUserDisplay(log.receiver_civ_name, log.receiver_handle)}</span>
                        <span style="color: #666; font-size: 10px; margin-left: 10px;">${new Date(log.transferred_at).toLocaleString('ja-JP')}</span>
                    </div>
                `).join('');
            }
            
            // 受信ログ（資源）
            if (data.resource_received && data.resource_received.length > 0) {
                html += '<h4 style="color: #90ee90; margin: 15px 0 10px 0;">🎁 受け取った資源</h4>';
                html += data.resource_received.slice(0, 5).map(log => `
                    <div style="background: rgba(50, 205, 50, 0.1); padding: 8px; border-radius: 6px; margin-bottom: 5px; font-size: 12px;">
                        <span style="color: #32cd32;">${log.resource_icon} ${log.resource_name} ×${Math.floor(log.amount)}</span>
                        <span style="color: #888;"> from ${formatUserDisplay(log.sender_civ_name, log.sender_handle)}</span>
                        <span style="color: #666; font-size: 10px; margin-left: 10px;">${new Date(log.transferred_at).toLocaleString('ja-JP')}</span>
                    </div>
                `).join('');
            }
            
            // 送信ログ（資源）
            if (data.resource_sent && data.resource_sent.length > 0) {
                html += '<h4 style="color: #87ceeb; margin: 15px 0 10px 0;">📤 送った資源</h4>';
                html += data.resource_sent.slice(0, 5).map(log => `
                    <div style="background: rgba(70, 130, 180, 0.1); padding: 8px; border-radius: 6px; margin-bottom: 5px; font-size: 12px;">
                        <span style="color: #4682b4;">${log.resource_icon} ${log.resource_name} ×${Math.floor(log.amount)}</span>
                        <span style="color: #888;"> to ${formatUserDisplay(log.receiver_civ_name, log.receiver_handle)}</span>
                        <span style="color: #666; font-size: 10px; margin-left: 10px;">${new Date(log.transferred_at).toLocaleString('ja-JP')}</span>
                    </div>
                `).join('');
            }
            
            document.getElementById('transferLogsSection').innerHTML = html || '<p style="color: #888;">援助ログはありません</p>';
        }
    } catch (e) {
        console.error(e);
        document.getElementById('transferLogsSection').innerHTML = '<p style="color: #888;">ログの読み込みに失敗しました</p>';
    }
}

// チュートリアルを読み込む
async function loadTutorial() {
    const section = document.getElementById('tutorialSection');
    if (!section) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_tutorial'})
        });
        const data = await res.json();
        
        if (!data.ok) {
            section.innerHTML = '<p style="color: #ff6b6b;">エラー: ' + escapeHtml(data.error || '読み込みに失敗しました') + '</p>';
            return;
        }
        
        if (!data.tutorial_available) {
            section.innerHTML = '<p style="color: #888;">チュートリアルシステムは準備中です。</p>';
            return;
        }
        
        // チュートリアル完了済み
        if (data.is_completed) {
            section.innerHTML = `
                <div style="text-align: center; padding: 30px;">
                    <div style="font-size: 48px; margin-bottom: 20px;">🎉</div>
                    <h3 style="color: #90ee90; margin-bottom: 10px;">チュートリアル完了！</h3>
                    <p style="color: #888;">おめでとうございます！全てのクエストをクリアしました。</p>
                    <p style="color: #666; font-size: 12px;">完了日時: ${new Date(data.completed_at).toLocaleString('ja-JP')}</p>
                </div>
            `;
            return;
        }
        
        // 進行中のチュートリアル
        let html = '';
        
        // プログレスバー
        const completedCount = data.completed_quests ? data.completed_quests.length : 0;
        const totalCount = data.all_quests ? data.all_quests.length : 1;
        const progressPercent = Math.round((completedCount / totalCount) * 100);
        
        html += `
            <div style="margin-bottom: 25px;">
                <div style="display: flex; justify-content: space-between; color: #888; font-size: 12px; margin-bottom: 5px;">
                    <span>進捗状況</span>
                    <span>${completedCount}/${totalCount} クエスト完了</span>
                </div>
                <div style="background: rgba(0,0,0,0.3); border-radius: 10px; height: 20px; overflow: hidden;">
                    <div style="background: linear-gradient(90deg, #32cd32, #90ee90); height: 100%; width: ${progressPercent}%; transition: width 0.5s;"></div>
                </div>
            </div>
        `;
        
        // 現在のクエスト
        if (data.current_quest) {
            const quest = data.current_quest;
            const isCompleted = data.is_current_quest_completed;
            
            html += `
                <div style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.2) 0%, rgba(255, 140, 0, 0.2) 100%); border: 2px solid ${isCompleted ? '#32cd32' : '#ffd700'}; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                        <span style="font-size: 36px;">${quest.icon}</span>
                        <div>
                            <h4 style="color: #ffd700; margin: 0 0 5px 0;">クエスト ${quest.quest_order}: ${escapeHtml(quest.title)}</h4>
                            <p style="color: #c0a080; margin: 0; font-size: 13px;">${escapeHtml(quest.description)}</p>
                        </div>
                    </div>
                    
                    <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                        <div style="color: #888; font-size: 12px; margin-bottom: 8px;">報酬:</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; font-size: 13px;">
                            ${quest.reward_coins > 0 ? `<span style="color: #ffd700;">💰 ${quest.reward_coins.toLocaleString()}コイン</span>` : ''}
                            ${quest.reward_crystals > 0 ? `<span style="color: #9932cc;">💎 ${quest.reward_crystals.toLocaleString()}クリスタル</span>` : ''}
                            ${quest.reward_diamonds > 0 ? `<span style="color: #00ced1;">💠 ${quest.reward_diamonds.toLocaleString()}ダイヤモンド</span>` : ''}
                        </div>
                    </div>
                    
                    <div style="text-align: center;">
                        ${isCompleted 
                            ? `<button onclick="completeTutorialQuest()" style="background: linear-gradient(135deg, #32cd32, #90ee90); color: #1a0f0a; border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 15px;">🎁 報酬を受け取る</button>`
                            : `<div style="color: #888; padding: 10px;">⏳ クエスト条件を達成してください</div>`
                        }
                    </div>
                </div>
            `;
        }
        
        // 完了済みクエスト一覧
        if (data.completed_quests && data.completed_quests.length > 0) {
            html += '<h4 style="color: #90ee90; margin: 20px 0 10px 0;">✅ 完了済みクエスト</h4>';
            html += '<div style="display: flex; flex-direction: column; gap: 8px;">';
            data.completed_quests.forEach(q => {
                html += `
                    <div style="background: rgba(50, 205, 50, 0.1); padding: 10px 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(50, 205, 50, 0.3);">
                        <span style="font-size: 20px;">${q.icon}</span>
                        <span style="color: #90ee90; flex: 1;">${escapeHtml(q.title)}</span>
                        <span style="color: #32cd32;">✓</span>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        section.innerHTML = html;
    } catch (e) {
        console.error(e);
        section.innerHTML = '<p style="color: #ff6b6b;">チュートリアルの読み込みに失敗しました</p>';
    }
}

// チュートリアルクエストを完了
async function completeTutorialQuest() {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'complete_tutorial_quest'})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message, 'success');
            loadTutorial();
            loadData(); // コイン等の更新のため
            
            // 次のクエストのモーダルを表示するためにリセット
            try {
                await fetch('civilization_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'reset_tutorial_modal'})
                });
                setTimeout(() => checkTutorialModal(), 500);
            } catch (e) {}
        } else {
            showNotification(data.error || '報酬の受け取りに失敗しました', 'error');
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', 'error');
    }
}

// ===============================================
// チュートリアルモーダル機能
// ===============================================
let tutorialModalShown = false;

async function checkTutorialModal() {
    if (tutorialModalShown) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_tutorial_modal_config'})
        });
        const data = await res.json();
        
        if (data.ok && data.show_modal && data.modal_config) {
            showTutorialModal(data.modal_config);
            tutorialModalShown = true;
        }
    } catch (e) {
        console.error('Tutorial modal check failed:', e);
    }
}

function showTutorialModal(config) {
    const modal = document.getElementById('tutorialModal');
    if (!modal) return;
    
    document.getElementById('tutorialModalTitle').innerHTML = config.modal_title || '📖 チュートリアル';
    document.getElementById('tutorialModalContent').innerHTML = config.modal_content || '';
    
    const hintEl = document.getElementById('tutorialHint');
    const hintText = document.getElementById('tutorialHintText');
    if (config.action_hint) {
        hintText.textContent = config.action_hint;
        hintEl.style.display = 'block';
    } else {
        hintEl.style.display = 'none';
    }
    
    modal.classList.add('show');
    
    // ハイライト表示
    if (config.highlight_selector) {
        const targetEl = document.querySelector(config.highlight_selector);
        if (targetEl) {
            targetEl.style.boxShadow = '0 0 20px 5px rgba(255, 215, 0, 0.6)';
            targetEl.style.transition = 'box-shadow 0.3s';
            setTimeout(() => {
                targetEl.style.boxShadow = '';
            }, 5000);
        }
    }
}

async function closeTutorialModal() {
    const modal = document.getElementById('tutorialModal');
    if (modal) {
        modal.classList.remove('show');
    }
    
    // モーダルを閉じた状態を保存
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_tutorial_modal_config'})
        });
        const data = await res.json();
        if (data.ok && data.current_quest_id) {
            await fetch('civilization_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'dismiss_tutorial_modal', quest_id: data.current_quest_id})
            });
        }
    } catch (e) {}
}

// ===============================================
// リーダーボード機能
// ===============================================
let currentLeaderboardType = 'population';
let leaderboardResourceTypes = [];

async function loadLeaderboard(rankingType = null) {
    const listContainer = document.getElementById('leaderboard-list');
    const myRankDisplay = document.getElementById('my-rank-display');
    const resourceButtonsContainer = document.getElementById('resource-ranking-buttons');
    
    if (!listContainer) return;
    
    // ランキングタイプを決定
    if (rankingType) {
        currentLeaderboardType = rankingType;
    }
    
    // アクティブなボタンを更新
    updateRankingButtonStyles(currentLeaderboardType);
    
    listContainer.innerHTML = '<div class="loading">ランキングを読み込み中...</div>';
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_leaderboards', ranking_type: currentLeaderboardType})
        });
        const data = await res.json();
        
        if (!data.ok) {
            listContainer.innerHTML = `<p style="color: #ff6b6b;">エラー: ${data.error}</p>`;
            return;
        }
        
        // 資源タイプのボタンを生成（DOMが空の場合は再生成）
        if (data.resource_types && resourceButtonsContainer) {
            // 資源ボタンが存在しない場合はボタンを生成
            const needsRender = resourceButtonsContainer.querySelector('.resource-ranking-btn') === null;
            if (needsRender) {
                leaderboardResourceTypes = data.resource_types;
                resourceButtonsContainer.innerHTML = data.resource_types.map(rt => 
                    `<button class="resource-ranking-btn" data-ranking="resource_${rt.resource_key}" style="padding: 6px 10px; background: rgba(0,0,0,0.3); border: 2px solid #48bb78; border-radius: 6px; color: #888; cursor: pointer; font-size: 11px; transition: all 0.2s;">${rt.icon} ${rt.name}</button>`
                ).join('');
                
                // 資源ボタンにイベントリスナーを追加
                setupResourceButtonListeners();
                
                // 現在選択中のランキングのボタンスタイルを更新
                updateRankingButtonStyles(currentLeaderboardType);
            }
        }
        
        // 自分の順位を表示
        if (myRankDisplay) {
            const rankText = data.my_rank ? `#${data.my_rank}位` : '順位なし';
            const valueText = data.my_value !== undefined ? Number(data.my_value).toLocaleString() : '0';
            myRankDisplay.innerHTML = `${rankText} <span style="color: #c0a080; font-size: 16px;">(${valueText})</span>`;
        }
        
        // ランキングリストを表示
        if (!data.rankings || data.rankings.length === 0) {
            listContainer.innerHTML = '<p style="color: #888; text-align: center;">ランキングデータがありません</p>';
            return;
        }
        
        let html = '';
        for (const entry of data.rankings) {
            const rankClass = entry.rank <= 3 ? `rank-${entry.rank}` : '';
            const rankIcon = entry.rank === 1 ? '🥇' : entry.rank === 2 ? '🥈' : entry.rank === 3 ? '🥉' : `#${entry.rank}`;
            const isMeStyle = entry.is_me ? 'background: rgba(255, 215, 0, 0.3); border: 2px solid #ffd700;' : '';
            
            // ⑫ 時代ランキングの場合は時代名とアイコンを表示
            let valueDisplay = '';
            if (currentLeaderboardType === 'era' && entry.era_name) {
                valueDisplay = `<div style="display: flex; align-items: center; gap: 8px; color: #ffd700; font-size: 16px; font-weight: bold;">
                    <span style="font-size: 20px;">${entry.era_icon || '🏛️'}</span>
                    <span>${escapeHtml(entry.era_name)}</span>
                </div>`;
            } else {
                valueDisplay = `<div style="color: #ffd700; font-size: 18px; font-weight: bold;">${Number(entry.value).toLocaleString()}</div>`;
            }
            
            html += `
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; margin-bottom: 8px; background: rgba(0,0,0,0.3); border-radius: 8px; ${isMeStyle}">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <span style="font-size: 20px; min-width: 45px; text-align: center;">${rankIcon}</span>
                        <div>
                            <div style="color: #f5deb3; font-weight: bold;">${escapeHtml(entry.civilization_name)}</div>
                            <div style="color: #888; font-size: 12px;">@${escapeHtml(entry.handle)}</div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        ${valueDisplay}
                    </div>
                </div>
            `;
        }
        
        listContainer.innerHTML = html;
    } catch (e) {
        console.error(e);
        listContainer.innerHTML = '<p style="color: #ff6b6b;">ランキングの読み込みに失敗しました</p>';
    }
}

// ランキングボタンのスタイルを更新
function updateRankingButtonStyles(activeRanking) {
    // メインランキングボタン
    document.querySelectorAll('.ranking-btn').forEach(btn => {
        if (btn.dataset.ranking === activeRanking) {
            btn.style.background = 'rgba(255, 215, 0, 0.3)';
            btn.style.borderColor = '#ffd700';
            btn.style.color = '#f5deb3';
            btn.classList.add('active');
        } else {
            btn.style.background = 'rgba(0,0,0,0.3)';
            btn.style.borderColor = '#666';
            btn.style.color = '#888';
            btn.classList.remove('active');
        }
    });
    
    // 資源ランキングボタン
    document.querySelectorAll('.resource-ranking-btn').forEach(btn => {
        if (btn.dataset.ranking === activeRanking) {
            btn.style.background = 'rgba(72, 187, 120, 0.3)';
            btn.style.borderColor = '#48bb78';
            btn.style.color = '#f5deb3';
            btn.classList.add('active');
        } else {
            btn.style.background = 'rgba(0,0,0,0.3)';
            btn.style.borderColor = '#48bb78';
            btn.style.color = '#888';
            btn.classList.remove('active');
        }
    });
}

// 資源ボタンのイベントリスナーを設定
function setupResourceButtonListeners() {
    document.querySelectorAll('.resource-ranking-btn').forEach(btn => {
        // 既にリスナーが追加されている場合はスキップ
        if (btn.dataset.listenerAdded) return;
        
        btn.addEventListener('click', () => {
            loadLeaderboard(btn.dataset.ranking);
        });
        btn.dataset.listenerAdded = 'true';
    });
}

// リーダーボードのイベントリスナーを設定
function setupLeaderboardListeners() {
    // メインランキングボタンにイベントリスナーを追加
    const mainButtons = document.querySelectorAll('.ranking-btn');
    
    mainButtons.forEach(btn => {
        // 既にリスナーが追加されている場合はスキップ
        if (btn.dataset.listenerAdded) return;
        
        btn.addEventListener('click', () => {
            loadLeaderboard(btn.dataset.ranking);
        });
        btn.dataset.listenerAdded = 'true';
    });
}

// ===============================================
// 文明クエスト機能（チュートリアル以外）
// ===============================================
async function loadCivilizationQuests() {
    const section = document.getElementById('civilizationQuestsSection');
    if (!section) return;
    
    section.innerHTML = '<div class="loading">読み込み中...</div>';
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_civilization_quests'})
        });
        const data = await res.json();
        
        if (!data.ok) {
            section.innerHTML = `<p style="color: #ff6b6b;">エラー: ${data.error}</p>`;
            return;
        }
        
        if (!data.quests_available) {
            section.innerHTML = '<p style="color: #c0a080;">クエストシステムはまだ初期化されていません</p>';
            return;
        }
        
        let html = '';
        const categoryInfo = data.category_info || {};
        const questsByCategory = data.quests_by_category || {};
        
        // 各カテゴリのクエストを表示
        for (const [category, quests] of Object.entries(questsByCategory)) {
            const catInfo = categoryInfo[category] || {name: category, icon: '📋'};
            
            html += `
                <div class="quest-category-section">
                    <div class="quest-category-header">
                        <span class="quest-category-icon">${catInfo.icon}</span>
                        <span class="quest-category-name">${catInfo.name}</span>
                        <span style="color: #888; font-size: 13px;">(${quests.length}件)</span>
                    </div>
            `;
            
            for (const quest of quests) {
                const progressPercent = Math.min(100, (quest.current_progress / quest.target_count) * 100);
                const isCompleted = quest.is_completed;
                const isClaimed = quest.is_claimed;
                
                let btnClass = 'in-progress';
                let btnText = `進行中 (${quest.current_progress}/${quest.target_count})`;
                let btnOnclick = '';
                
                if (isClaimed && !quest.is_repeatable) {
                    btnClass = 'claimed';
                    btnText = '✅ 受取済み';
                } else if (isClaimed && quest.is_repeatable && quest.cooldown_remaining > 0) {
                    // 14: クールダウン中の表示修正
                    btnClass = 'claimed';
                    const hours = Math.ceil(quest.cooldown_remaining / 3600);
                    btnText = `🕐 ${hours}時間後に再挑戦可能`;
                } else if (isClaimed && quest.is_repeatable && quest.cooldown_remaining <= 0) {
                    // 14: クールダウン終了後は進行状態に戻す
                    btnClass = 'in-progress';
                    btnText = `進行中 (${quest.current_progress}/${quest.target_count})`;
                } else if (isCompleted) {
                    btnClass = 'available';
                    btnText = '🎁 報酬を受け取る';
                    btnOnclick = `onclick="claimCivilizationQuestReward(${quest.id})"`;
                }
                
                // 報酬表示
                let rewardsHtml = '';
                if (quest.reward_coins > 0) {
                    rewardsHtml += `<span class="quest-reward-item">🪙 ${Number(quest.reward_coins).toLocaleString()}</span>`;
                }
                if (quest.reward_crystals > 0) {
                    rewardsHtml += `<span class="quest-reward-item">💎 ${Number(quest.reward_crystals).toLocaleString()}</span>`;
                }
                if (quest.reward_diamonds > 0) {
                    rewardsHtml += `<span class="quest-reward-item">💠 ${Number(quest.reward_diamonds).toLocaleString()}</span>`;
                }
                if (quest.reward_resources) {
                    for (const [key, amount] of Object.entries(quest.reward_resources)) {
                        const resourceName = getResourceName(key);
                        rewardsHtml += `<span class="quest-reward-item">📦 ${resourceName} ${Number(amount).toLocaleString()}</span>`;
                    }
                }
                
                html += `
                    <div class="quest-card ${isCompleted ? 'completed' : ''} ${isClaimed && !quest.is_repeatable ? 'claimed' : ''}">
                        <div class="quest-header">
                            <div class="quest-title">
                                <span>${quest.icon}</span>
                                <span>${escapeHtml(quest.title)}</span>
                                ${quest.is_repeatable ? '<span style="font-size: 10px; color: #48bb78;">🔄</span>' : ''}
                            </div>
                            <span class="quest-era-badge">${quest.era_icon} ${quest.era_name}</span>
                        </div>
                        <div class="quest-description">${escapeHtml(quest.description)}</div>
                        <div class="quest-progress-bar">
                            <div class="quest-progress-fill ${isCompleted ? 'completed' : ''}" style="width: ${progressPercent}%;"></div>
                        </div>
                        <div class="quest-progress-text">${quest.current_progress} / ${quest.target_count}</div>
                        <div class="quest-rewards">${rewardsHtml}</div>
                        <button class="quest-claim-btn ${btnClass}" ${btnOnclick}>${btnText}</button>
                    </div>
                `;
            }
            
            html += '</div>';
        }
        
        if (Object.keys(questsByCategory).length === 0) {
            html = '<p style="color: #c0a080; text-align: center;">現在の時代で利用可能なクエストはありません</p>';
        }
        
        section.innerHTML = html;
        
        // クエストバッジを更新
        const claimableCount = data.quests.filter(q => q.is_completed && !q.is_claimed).length;
        const questsBadge = document.getElementById('quests-badge');
        if (questsBadge) {
            if (claimableCount > 0) {
                questsBadge.textContent = claimableCount;
                questsBadge.style.display = 'inline-block';
            } else {
                questsBadge.style.display = 'none';
            }
        }
    } catch (e) {
        console.error(e);
        section.innerHTML = '<p style="color: #ff6b6b;">クエストの読み込みに失敗しました</p>';
    }
}

async function claimCivilizationQuestReward(questId) {
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'claim_civilization_quest_reward', quest_id: questId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message, 'success');
            loadCivilizationQuests();
            loadData(); // コイン等の更新のため
            updateTabBadges(); // バッジを更新
        } else {
            showNotification(data.error || '報酬の受け取りに失敗しました', 'error');
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', 'error');
    }
}

// ===============================================
// イベントシステム
// ===============================================

let currentEventType = 'daily';

// イベントサブタブのクリックイベント（イベント委譲を使用）
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.event-subtab-btn');
    if (btn) {
        document.querySelectorAll('.event-subtab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentEventType = btn.dataset.eventType;
        loadEventContent(currentEventType);
    }
});

// イベントコンテンツを読み込む
async function loadEventContent(eventType) {
    const container = document.getElementById('eventContentArea');
    if (!container) return;
    
    container.innerHTML = '<div class="loading">読み込み中...</div>';
    
    try {
        let data;
        if (eventType === 'daily') {
            const res = await fetch('civilization_events_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_daily_tasks'})
            });
            data = await res.json();
            
            if (data.ok) {
                renderDailyTasks(container, data.tasks);
            } else {
                container.innerHTML = '<p style="color: #ff6b6b;">デイリータスクの読み込みに失敗しました</p>';
            }
        } else if (eventType === 'special') {
            const res = await fetch('civilization_events_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_special_events'})
            });
            data = await res.json();
            
            if (data.ok) {
                renderSpecialEvents(container, data.events);
            } else {
                container.innerHTML = '<p style="color: #ff6b6b;">スペシャルイベントの読み込みに失敗しました</p>';
            }
        } else if (eventType === 'hero') {
            const res = await fetch('civilization_events_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_hero_events'})
            });
            data = await res.json();
            
            if (data.ok) {
                renderHeroEvents(container, data.events);
            } else {
                container.innerHTML = '<p style="color: #ff6b6b;">ヒーローイベントの読み込みに失敗しました</p>';
            }
        }
    } catch (e) {
        console.error(e);
        container.innerHTML = '<p style="color: #ff6b6b;">イベントの読み込みに失敗しました</p>';
    }
}

// デイリータスクを描画
function renderDailyTasks(container, tasks) {
    if (!tasks || tasks.length === 0) {
        container.innerHTML = '<p style="color: #888; text-align: center;">デイリータスクがありません</p>';
        return;
    }
    
    let html = `
        <div style="margin-bottom: 15px; color: #ffa500;">
            📅 本日のデイリータスク（毎日0時にリセット）
        </div>
    `;
    
    tasks.forEach(task => {
        const progressPercent = Math.min(100, (task.current_progress / task.target_count) * 100);
        const isCompleted = task.current_progress >= task.target_count;
        const isClaimed = task.is_claimed;
        
        let btnClass = 'in-progress';
        let btnText = '進行中 (' + task.current_progress + '/' + task.target_count + ')';
        let btnOnclick = '';
        
        if (isClaimed) {
            btnClass = 'claimed';
            btnText = '✅ 受取済み';
        } else if (isCompleted) {
            btnClass = 'available';
            btnText = '🎁 報酬を受け取る';
            btnOnclick = 'onclick="claimDailyTask(' + task.id + ')"';
        }
        
        let rewardsHtml = '';
        if (task.reward_coins > 0) rewardsHtml += '<span class="quest-reward-item">🪙 ' + task.reward_coins.toLocaleString() + '</span>';
        if (task.reward_crystals > 0) rewardsHtml += '<span class="quest-reward-item">💎 ' + task.reward_crystals + '</span>';
        if (task.reward_diamonds > 0) rewardsHtml += '<span class="quest-reward-item">💠 ' + task.reward_diamonds + '</span>';
        if (task.reward_exp > 0) rewardsHtml += '<span class="quest-reward-item">⭐ ' + task.reward_exp + 'EXP</span>';
        
        html += `
            <div class="quest-card ${isCompleted ? 'completed' : ''} ${isClaimed ? 'claimed' : ''}" style="margin-bottom: 10px;">
                <div class="quest-header">
                    <div class="quest-title">
                        <span>${task.icon}</span>
                        <span>${escapeHtml(task.name)}</span>
                    </div>
                </div>
                <div class="quest-description">${escapeHtml(task.description)}</div>
                <div class="quest-progress-bar">
                    <div class="quest-progress-fill ${isCompleted ? 'completed' : ''}" style="width: ${progressPercent}%;"></div>
                </div>
                <div class="quest-progress-text">${task.current_progress} / ${task.target_count}</div>
                <div class="quest-rewards">${rewardsHtml}</div>
                <button class="quest-claim-btn ${btnClass}" ${btnOnclick}>${btnText}</button>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// スペシャルイベントを描画
function renderSpecialEvents(container, events) {
    if (!events || events.length === 0) {
        container.innerHTML = '<p style="color: #888; text-align: center;">現在開催中のスペシャルイベントはありません</p>';
        return;
    }
    
    let html = '';
    
    events.forEach(event => {
        const remainingHours = Math.floor(event.remaining_seconds / 3600);
        const remainingDays = Math.floor(remainingHours / 24);
        
        html += `
            <div style="background: rgba(0,0,0,0.3); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #ff69b4;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <span style="font-size: 40px;">${event.icon}</span>
                    <div>
                        <h4 style="color: #ff69b4; margin: 0;">${escapeHtml(event.name)}</h4>
                        <p style="color: #c0a080; margin: 5px 0;">${escapeHtml(event.description)}</p>
                        <span style="color: #ffa500; font-size: 12px;">⏱️ 残り ${remainingDays}日 ${remainingHours % 24}時間</span>
                    </div>
                </div>
                
                ${event.items && event.items.length > 0 ? `
                    <div style="margin-bottom: 15px;">
                        <h5 style="color: #ffd700; margin-bottom: 10px;">📦 限定アイテム</h5>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            ${event.items.map(item => `
                                <div style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; text-align: center;">
                                    <span style="font-size: 24px;">${item.icon}</span>
                                    <div style="color: #f5deb3; font-size: 12px;">${escapeHtml(item.name)}</div>
                                    <div style="color: #48bb78; font-size: 14px;">×${item.user_count || 0}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                
                ${event.exchanges && event.exchanges.length > 0 ? `
                    <div style="margin-bottom: 15px;">
                        <h5 style="color: #48bb78; margin-bottom: 10px;">🔄 アイテム交換所</h5>
                        <div style="display: grid; gap: 10px;">
                            ${event.exchanges.map(ex => {
                                // 所持アイテム数を取得
                                const ownedItem = event.items ? event.items.find(item => item.id === ex.item_id) : null;
                                const ownedCount = ownedItem ? (ownedItem.user_count || 0) : 0;
                                const maxExchangeable = Math.floor(ownedCount / ex.required_count);
                                return `
                                <div style="background: rgba(0,100,0,0.2); padding: 12px; border-radius: 8px; border: 1px solid #48bb78;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <div>
                                            <span style="color: #f5deb3;">${ex.item_icon || '📦'} ${escapeHtml(ex.item_name || 'アイテム')} ×${ex.required_count}</span>
                                            <span style="color: #888;"> → </span>
                                            <span style="color: #48bb78;">${ex.reward_type === 'coins' ? '💰' : ex.reward_type === 'crystals' ? '💎' : ex.reward_type === 'diamonds' ? '💠' : '🎁'} ${ex.reward_amount}</span>
                                        </div>
                                        <span style="color: #888; font-size: 12px;">所持: ${ownedCount}</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" id="exchange-qty-${ex.id}" min="1" max="${Math.max(1, maxExchangeable)}" value="1" 
                                               style="flex: 1; accent-color: #48bb78;" 
                                               oninput="document.getElementById('exchange-qty-display-${ex.id}').textContent = this.value"
                                               ${maxExchangeable < 1 ? 'disabled' : ''}>
                                        <span id="exchange-qty-display-${ex.id}" style="color: #ffd700; font-weight: bold; min-width: 30px; text-align: center;">1</span>
                                        <button class="quick-invest-btn" onclick="exchangeEventItem(${ex.id}, parseInt(document.getElementById('exchange-qty-${ex.id}').value))" 
                                                style="padding: 6px 12px;" ${maxExchangeable < 1 ? 'disabled' : ''}>
                                            交換
                                        </button>
                                    </div>
                                </div>
                            `}).join('')}
                        </div>
                    </div>
                ` : ''}
                
                ${event.portal_bosses && event.portal_bosses.length > 0 ? `
                    <div style="margin-bottom: 15px;">
                        <h5 style="color: #dc143c; margin-bottom: 10px;">🌀 ポータルボス</h5>
                        ${event.portal_bosses.map(boss => `
                            <div style="background: rgba(220,20,60,0.2); padding: 15px; border-radius: 8px; border: 1px solid #dc143c;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                    <span style="font-size: 32px;">${boss.boss_icon}</span>
                                    <div>
                                        <div style="color: #ff6b6b; font-weight: bold;">${escapeHtml(boss.boss_name)}</div>
                                        <div style="color: #888; font-size: 12px;">戦力: ${boss.boss_power.toLocaleString()}</div>
                                    </div>
                                </div>
                                ${boss.can_attack ? `
                                    <button class="quick-invest-btn" onclick="openPortalBossAttack(${boss.id})" 
                                            style="background: linear-gradient(135deg, #dc143c 0%, #8b0000 100%); width: 100%;">
                                        ⚔️ ボスを攻撃
                                    </button>
                                ` : `
                                    <div style="color: #888; text-align: center; padding: 10px;">
                                        ⏱️ ${Math.ceil(boss.seconds_until_attack / 60)}分後に攻撃可能
                                    </div>
                                `}
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// イベントアイテム交換
async function exchangeEventItem(exchangeId, quantity = 1) {
    try {
        const res = await fetch('civilization_events_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'exchange_event_item', exchange_id: exchangeId, quantity: quantity})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message, 'success');
            loadEventContent('special');
            loadData();
        } else {
            showNotification(data.error || '交換に失敗しました', 'error');
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', 'error');
    }
}

// ヒーローイベントを描画（シーズンパス風UI）
function renderHeroEvents(container, events) {
    if (!events || events.length === 0) {
        container.innerHTML = '<p style="color: #888; text-align: center;">現在開催中のヒーローイベントはありません</p>';
        return;
    }
    
    let html = '';
    
    events.forEach(event => {
        const remainingHours = Math.floor(event.remaining_seconds / 3600);
        const remainingDays = Math.floor(remainingHours / 24);
        const currentPoints = event.current_points || 0;
        const claimedRewards = event.claimed_rewards || [];
        
        // ポイント報酬の最大値を計算
        const maxPoints = event.point_rewards && event.point_rewards.length > 0 
            ? Math.max(...event.point_rewards.map(r => r.required_points))
            : 100;
        const progressPercent = Math.min(100, (currentPoints / maxPoints) * 100);
        
        html += `
            <div style="background: linear-gradient(135deg, #1a1a2e 0%, #2d2d44 100%); padding: 20px; border-radius: 16px; margin-bottom: 20px; border: 2px solid #9932cc; box-shadow: 0 0 20px rgba(153, 50, 204, 0.3);">
                <!-- ヘッダー -->
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                    <div style="font-size: 60px; text-shadow: 0 0 20px rgba(153, 50, 204, 0.8);">${event.featured_hero.icon}</div>
                    <div style="flex: 1;">
                        <h3 style="color: #ffd700; margin: 0; font-size: 22px;">${escapeHtml(event.name)}</h3>
                        <p style="color: #c0a080; margin: 5px 0; font-size: 14px;">テーマヒーロー: <span style="color: #ff69b4;">${escapeHtml(event.featured_hero.name)}</span> - ${escapeHtml(event.featured_hero.title || '')}</p>
                        <div style="display: flex; gap: 15px; margin-top: 10px;">
                            <span style="color: #ffa500; font-size: 13px;">⏱️ 残り ${remainingDays}日 ${remainingHours % 24}時間</span>
                            <span style="color: #48bb78; font-size: 13px;">✨ 欠片+${event.bonus_shard_rate}%</span>
                        </div>
                    </div>
                </div>
                
                <!-- ⑥ 限定ガチャボタン（価格表示と確認アラート追加） -->
                <div style="margin-bottom: 20px;">
                    ${(() => {
                        const baseCost = 100;
                        const discount = event.gacha_discount_percent || 0;
                        const finalCost = Math.floor(baseCost * (100 - discount) / 100);
                        const cost10 = Math.floor(finalCost * 10 * 0.9); // 10連は追加10%割引
                        return `
                            <button class="quick-invest-btn" onclick="openHeroEventGacha(${event.id}, ${event.featured_hero.id}, ${finalCost})" 
                                    style="background: linear-gradient(135deg, #ff69b4 0%, #9932cc 100%); width: 100%; padding: 15px; font-size: 16px; border-radius: 12px;">
                                🎰 限定ガチャ 💎${finalCost}クリスタル ${discount > 0 ? `(${discount}%OFF!)` : ''}
                            </button>
                            <button class="quick-invest-btn" onclick="openHeroEventGacha10(${event.id}, ${cost10})" 
                                    style="background: linear-gradient(135deg, #ffd700 0%, #ff6b6b 100%); width: 100%; padding: 15px; font-size: 16px; border-radius: 12px; margin-top: 10px;">
                                🔥 10連ガチャ 💎${cost10}クリスタル <span style="font-size: 12px;">(10%OFF!)</span>
                            </button>
                        `;
                    })()}
                    <div style="text-align: center; color: #888; font-size: 12px; margin-top: 5px;">
                        ${escapeHtml(event.featured_hero.name)}の欠片排出率が大幅UP！
                    </div>
                </div>
                
                <!-- シーズンパス風ポイントトラック -->
                <div style="background: rgba(0,0,0,0.4); padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="color: #ffd700; font-size: 16px; font-weight: bold;">🏆 イベントポイント</span>
                        <span style="color: #48bb78; font-size: 24px; font-weight: bold;">${currentPoints}<span style="color: #888; font-size: 14px;">pt</span></span>
                    </div>
                    
                    <!-- プログレストラック -->
                    <div style="position: relative; height: 40px; background: rgba(0,0,0,0.4); border-radius: 20px; overflow: hidden; margin-bottom: 15px;">
                        <div style="position: absolute; left: 0; top: 0; height: 100%; width: ${progressPercent}%; background: linear-gradient(90deg, #9932cc 0%, #ff69b4 100%); border-radius: 20px; transition: width 0.5s;"></div>
                        <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; text-shadow: 1px 1px 2px black;">
                            ${currentPoints} / ${maxPoints} pt
                        </div>
                    </div>
                    
                    <!-- 報酬マイルストーン -->
                    ${event.point_rewards && event.point_rewards.length > 0 ? `
                        <div style="display: flex; gap: 10px; overflow-x: auto; padding: 10px 0;">
                            ${event.point_rewards.map((reward, idx) => {
                                const isUnlocked = currentPoints >= reward.required_points;
                                const isClaimed = claimedRewards.includes(reward.id);
                                const rewardIcon = reward.reward_type === 'hero_shards' ? '🧩' : 
                                                   reward.reward_type === 'coins' ? '💰' : 
                                                   reward.reward_type === 'crystals' ? '💎' : 
                                                   reward.reward_type === 'diamonds' ? '💠' : '🎁';
                                return `
                                    <div style="flex: 0 0 100px; background: ${isUnlocked ? 'rgba(153, 50, 204, 0.3)' : 'rgba(50,50,50,0.5)'}; border: 2px solid ${isUnlocked ? '#9932cc' : '#555'}; border-radius: 12px; padding: 10px; text-align: center; position: relative;">
                                        <div style="color: ${isUnlocked ? '#ffd700' : '#888'}; font-size: 11px; margin-bottom: 5px;">${reward.required_points}pt</div>
                                        <div style="font-size: 24px;">${rewardIcon}</div>
                                        <div style="color: ${isUnlocked ? '#48bb78' : '#888'}; font-size: 12px; margin-top: 5px;">${reward.reward_amount}</div>
                                        ${isClaimed ? `<div style="position: absolute; inset: 0; background: rgba(0,0,0,0.6); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #48bb78; font-size: 20px;">✓</div>` : ''}
                                        ${isUnlocked && !isClaimed ? `<button onclick="claimHeroEventPointReward(${reward.id})" style="position: absolute; bottom: -5px; left: 50%; transform: translateX(-50%); background: #48bb78; color: white; border: none; border-radius: 10px; padding: 3px 10px; font-size: 10px; cursor: pointer;">受取</button>` : ''}
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    ` : ''}
                </div>
                
                <!-- イベントタスク -->
                ${event.tasks && event.tasks.length > 0 ? `
                    <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 12px;">
                        <h5 style="color: #ffd700; margin: 0 0 15px 0;">📋 イベントタスク</h5>
                        <div style="display: grid; gap: 8px;">
                            ${event.tasks.map(task => {
                                const isCompleted = task.current_progress >= task.target_count;
                                const isClaimed = task.is_claimed;
                                const taskProgress = Math.min(100, (task.current_progress / task.target_count) * 100);
                                return `
                                    <div style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid ${isClaimed ? '#48bb78' : isCompleted ? '#ffd700' : '#555'};">
                                        <div style="flex: 1;">
                                            <div style="color: ${isClaimed ? '#888' : '#f5deb3'}; ${isClaimed ? 'text-decoration: line-through;' : ''}">${task.icon} ${escapeHtml(task.name)}</div>
                                            <div style="margin-top: 5px; height: 4px; background: rgba(0,0,0,0.3); border-radius: 2px; overflow: hidden;">
                                                <div style="height: 100%; width: ${taskProgress}%; background: ${isCompleted ? '#48bb78' : '#9932cc'};"></div>
                                            </div>
                                            <div style="color: #888; font-size: 11px; margin-top: 3px;">${task.current_progress}/${task.target_count}</div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px; margin-left: 15px;">
                                            <span style="color: #ffa500; font-weight: bold;">+${task.points_reward}pt</span>
                                            ${isClaimed 
                                                ? '<span style="color: #48bb78; font-size: 18px;">✓</span>' 
                                                : (isCompleted 
                                                    ? `<button class="quick-invest-btn" onclick="claimHeroEventTask(${task.id})" style="padding: 5px 12px; font-size: 12px;">受取</button>` 
                                                    : '<span style="color: #888;">-</span>')}
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ヒーローイベントポイント報酬を受け取る
async function claimHeroEventPointReward(rewardId) {
    try {
        const res = await fetch('civilization_events_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'claim_hero_event_point_reward', reward_id: rewardId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message, 'success');
            loadEventContent('hero');
            loadData();
        } else {
            showNotification(data.error || '報酬の受け取りに失敗しました', 'error');
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', 'error');
    }
}

// ⑥ ヒーローイベント限定ガチャを開く（価格確認アラート追加）
async function openHeroEventGacha(eventId, heroId, cost = 100) {
    // 確認アラートを表示
    if (!confirm(`ヒーロー限定ガチャを回しますか？\n\n💎 ${cost} クリスタルを消費します`)) {
        return;
    }
    
    try {
        const res = await fetch('civilization_events_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'hero_event_gacha', event_id: eventId, hero_id: heroId})
        });
        const data = await res.json();
        
        if (data.ok) {
            // ガチャ結果を表示
            let resultHtml = `<div style="font-size: 48px; margin-bottom: 10px;">${data.result.icon || '🎁'}</div>`;
            resultHtml += `<div style="color: #ffd700; font-size: 18px;">${escapeHtml(data.result.name || 'アイテム')}</div>`;
            if (data.result.shards) {
                resultHtml += `<div style="color: #ff69b4; margin-top: 5px;">欠片 ×${data.result.shards}</div>`;
            }
            
            // ③ ポイント獲得通知
            const pointsMsg = data.points_gained ? ` (+${data.points_gained}pt)` : '';
            showNotification(`ガチャ結果: ${data.result.name || 'アイテム'} 獲得！${pointsMsg}`, 'success');
            loadEventContent('hero');
            loadData();
        } else {
            showNotification(data.error || 'ガチャに失敗しました', 'error');
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', 'error');
    }
}

// ⑤ ヒーローイベント限定ガチャ10連
async function openHeroEventGacha10(eventId, cost10 = 900) {
    // 確認アラートを表示
    if (!confirm(`10連ガチャを回しますか？\n\n💎 ${cost10} クリスタルを消費します\n（10%割引適用済み）`)) {
        return;
    }
    
    try {
        const res = await fetch('civilization_events_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'hero_event_gacha_10', event_id: eventId})
        });
        const data = await res.json();
        
        if (data.ok) {
            // 10連ガチャ結果モーダルを表示（ポイント情報を追加）
            showHeroEventGacha10Results(data.results, data.cost, data.points_gained);
            loadEventContent('hero');
            loadData();
        } else {
            showNotification(data.error || '10連ガチャに失敗しました', 'error');
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', 'error');
    }
}

// ⑤ 10連ガチャ結果モーダルを表示
function showHeroEventGacha10Results(results, cost, pointsGained = 0) {
    // モーダルが既に存在する場合は削除
    const existingModal = document.getElementById('heroGacha10Modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // 結果をHTMLに変換
    let resultsHtml = '';
    results.forEach((result, index) => {
        const isFeatured = result.is_featured ? 'style="border: 2px solid #ffd700; box-shadow: 0 0 10px rgba(255,215,0,0.5);"' : '';
        const shardDisplay = result.shards ? `×${result.shards}` : (result.amount ? `×${result.amount}` : '');
        resultsHtml += `
            <div class="gacha10-item" ${isFeatured}>
                <div class="gacha10-icon">${result.icon || '🎁'}</div>
                <div class="gacha10-name">${escapeHtml(result.name)}</div>
                <div class="gacha10-amount">${shardDisplay}</div>
            </div>
        `;
    });
    
    // ③ ポイント表示を追加
    const pointsHtml = pointsGained > 0 ? `<span style="color: #48bb78; margin-left: 15px;">✨ +${pointsGained}pt</span>` : '';
    
    // モーダルHTMLを作成
    const modalHtml = `
        <div id="heroGacha10Modal" class="modal-overlay" onclick="closeHeroGacha10Modal(event)">
            <div class="gacha10-modal-content" onclick="event.stopPropagation()">
                <h2 style="text-align: center; color: #ffd700; margin-bottom: 20px;">🔥 10連ガチャ結果 🔥</h2>
                <div class="gacha10-results-grid">
                    ${resultsHtml}
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <span style="color: #888;">消費クリスタル: 💎 ${cost}</span>${pointsHtml}
                </div>
                <button onclick="closeHeroGacha10Modal()" class="gacha10-close-btn">OK</button>
            </div>
        </div>
    `;
    
    // スタイルが未追加の場合は追加
    if (!document.getElementById('heroGacha10Style')) {
        const style = document.createElement('style');
        style.id = 'heroGacha10Style';
        style.textContent = `
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            .gacha10-modal-content {
                background: linear-gradient(135deg, #1e1e2f 0%, #2d2d44 100%);
                border-radius: 20px;
                padding: 30px;
                max-width: 800px;
                width: 95%;
                max-height: 80vh;
                overflow-y: auto;
                animation: gachaAppear 0.5s ease-out;
            }
            @keyframes gachaAppear {
                from { transform: scale(0.5); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }
            .gacha10-results-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 15px;
            }
            .gacha10-item {
                background: rgba(0,0,0,0.3);
                border-radius: 12px;
                padding: 15px;
                text-align: center;
                border: 1px solid rgba(255,255,255,0.1);
                transition: transform 0.3s, box-shadow 0.3s;
            }
            .gacha10-item:hover {
                transform: scale(1.05);
            }
            .gacha10-icon {
                font-size: 36px;
                margin-bottom: 8px;
            }
            .gacha10-name {
                font-weight: bold;
                color: #ffd700;
                font-size: 13px;
                margin-bottom: 4px;
            }
            .gacha10-amount {
                font-size: 14px;
                color: #ff69b4;
            }
            .gacha10-close-btn {
                display: block;
                margin: 20px auto 0;
                padding: 12px 40px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                transition: transform 0.2s;
            }
            .gacha10-close-btn:hover {
                transform: scale(1.05);
            }
        `;
        document.head.appendChild(style);
    }
    
    // モーダルをDOMに追加
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

// ⑤ 10連ガチャモーダルを閉じる
function closeHeroGacha10Modal(event) {
    if (event && event.target.id !== 'heroGacha10Modal') {
        return;
    }
    const modal = document.getElementById('heroGacha10Modal');
    if (modal) {
        modal.remove();
    }
}

// デイリータスク報酬受け取り
async function claimDailyTask(taskId) {
    try {
        const res = await fetch('civilization_events_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'claim_daily_task', task_id: taskId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message, 'success');
            loadEventContent('daily');
            loadData();
        } else {
            showNotification(data.error || '報酬の受け取りに失敗しました', 'error');
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', 'error');
    }
}

// ヒーローイベントタスク報酬受け取り
async function claimHeroEventTask(taskId) {
    try {
        const res = await fetch('civilization_events_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'claim_hero_event_task', task_id: taskId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message, 'success');
            loadEventContent('hero');
        } else {
            showNotification(data.error || '報酬の受け取りに失敗しました', 'error');
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', 'error');
    }
}

// ポータルボス攻撃画面を開く
let portalBossData = null;

async function openPortalBossAttack(bossId) {
    // ボス情報を一時保存
    portalBossData = { bossId: bossId };
    
    // モーダルを表示（既存の攻撃モーダルを再利用）
    document.getElementById('attackModal').classList.add('active');
    
    // ターゲット情報を取得して表示
    document.getElementById('attackModalTarget').innerHTML = `
        <div style="background: rgba(220, 20, 60, 0.3); padding: 12px; border-radius: 8px; margin-bottom: 15px;">
            <div style="color: #dc143c; font-weight: bold; font-size: 16px;">🌀 ポータルボス攻撃</div>
            <div style="color: #f5deb3; font-size: 12px; margin-top: 5px;">部隊を選択してボスに攻撃しよう！</div>
        </div>
    `;
    
    // 有利/不利表示をクリア
    document.getElementById('attackAdvantageDisplay').innerHTML = '';
    
    // 兵士データを読み込んで表示
    await loadPortalBossTroops();
    
    // 攻撃ボタンをポータルボス用に変更
    const confirmBtn = document.getElementById('confirmAttackBtn');
    confirmBtn.innerHTML = '⚔️ ボスを攻撃';
    confirmBtn.onclick = confirmPortalBossAttack;
}

// ポータルボス用兵士を読み込む
async function loadPortalBossTroops() {
    const container = document.getElementById('attackTroopSelector');
    container.innerHTML = '<div class="loading">兵士を読み込み中...</div>';
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_troops'})
        });
        const data = await res.json();
        
        if (data.ok && data.user_troops && data.user_troops.filter(t => t.count > 0).length > 0) {
            userTroops = data.user_troops.filter(t => t.count > 0);
            
            if (data.deployment_limit) {
                deploymentLimit = data.deployment_limit;
            }
            
            container.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <div style="color: #ffd700; font-size: 12px;">出撃上限: <span id="attackTroopCount">0</span>/${deploymentLimit.total_limit}人</div>
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <button type="button" class="quick-invest-btn" style="font-size: 11px;" onclick="selectMaxByStrongest()">💪 強い順に選択</button>
                        <button type="button" class="quick-invest-btn" style="font-size: 11px; background: linear-gradient(135deg, #4169e1 0%, #87ceeb 100%);" onclick="selectByLargestNumber()">📊 数が多い順に選択</button>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 10px; padding: 6px; background: rgba(0,0,0,0.15); border-radius: 4px; font-size: 11px; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 3px; cursor: pointer; color: #ddd;">
                        <input type="checkbox" id="war-exclude-disposable" onchange="saveDeploymentSettings('war')" style="cursor: pointer;">
                        <span>🗑️ 使い捨てを除外</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 3px; cursor: pointer; color: #ddd;">
                        <input type="checkbox" id="war-exclude-nuclear" onchange="saveDeploymentSettings('war')" style="cursor: pointer;">
                        <span>☢️ 核ユニットを除外</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 3px; cursor: pointer; color: #ddd;">
                        <input type="checkbox" id="war-prioritize-stealth" onchange="saveDeploymentSettings('war')" style="cursor: pointer;">
                        <span>🥷 ステルスを優先</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 3px; cursor: pointer; color: #ffd700; border-left: 1px solid #555; padding-left: 10px; margin-left: 5px;">
                        <input type="checkbox" id="war-keep-settings" onchange="saveDeploymentSettings('war')" style="cursor: pointer;">
                        <span>💾 設定を保持</span>
                    </label>
                </div>
            ` + userTroops.map(troop => `
                <div class="troop-select-row">
                    <div class="troop-select-info">
                        <span class="troop-select-icon">${troop.icon}</span>
                        <span class="troop-select-name">${troop.name}${getTroopLabelsHtml(troop)}</span>
                        <div class="troop-select-stats">⚔️${troop.attack_power} 🛡️${troop.defense_power}</div>
                    </div>
                    <input type="range" class="troop-select-slider" 
                           id="attack-slider-${parseInt(troop.troop_type_id)}"
                           min="0" max="${parseInt(troop.count)}" value="0"
                           data-troop-id="${parseInt(troop.troop_type_id)}"
                           data-attack="${parseInt(troop.attack_power)}"
                           data-defense="${parseInt(troop.defense_power)}"
                           oninput="syncAttackTroopInput(${parseInt(troop.troop_type_id)}, this.value)">
                    <input type="number" class="troop-select-count" 
                           id="attack-count-${parseInt(troop.troop_type_id)}"
                           min="0" max="${parseInt(troop.count)}" value="0"
                           data-troop-id="${parseInt(troop.troop_type_id)}"
                           oninput="syncAttackTroopSlider(${parseInt(troop.troop_type_id)}, this.value)">
                    <span class="troop-select-max">/ ${parseInt(troop.count)}</span>
                </div>
            `).join('');
            
            updateAttackPowerDisplay();
            // ③ 保存された設定を適用
            setTimeout(() => applyDeploymentSettings('war'), 0);
        } else {
            container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;">兵士がいません。兵士タブで兵士を訓練してください。</p>';
        }
    } catch (e) {
        container.innerHTML = '<p style="color: #ff6b6b; text-align: center;">兵士の読み込みに失敗しました</p>';
    }
}

// ポータルボス攻撃を実行
async function confirmPortalBossAttack() {
    if (!portalBossData) return;
    
    // 選択された兵士を収集
    const troops = [];
    document.querySelectorAll('[id^="attack-count-"]').forEach(input => {
        const count = parseInt(input.value) || 0;
        if (count > 0) {
            troops.push({
                troop_type_id: parseInt(input.dataset.troopId),
                count: count
            });
        }
    });
    
    if (troops.length === 0) {
        showNotification('出撃部隊を選択してください', 'error');
        return;
    }
    
    const btn = document.getElementById('confirmAttackBtn');
    btn.disabled = true;
    btn.innerHTML = '攻撃中...';
    
    try {
        const res = await fetch('civilization_events_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'attack_portal_boss',
                boss_id: portalBossData.bossId,
                troops: troops
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            // 攻撃結果を表示（②ポータルボス攻撃後の獲得アイテム表示修正）
            let lootHtml = '';
            if (data.loot_received && data.loot_received.length > 0) {
                lootHtml = '\n獲得アイテム:';
                lootHtml += data.loot_received.map(l => `\n${l.icon || '📦'} ${l.name || 'アイテム'} ×${l.count}`).join('');
            }
            
            // alertで確実に表示
            alert(`${data.message}${lootHtml}`);
            showNotification(data.message, 'success');
            closeAttackModal();
            
            // イベントコンテンツを再読み込み
            loadEventContent('special');
        } else {
            showNotification(data.error || 'ボス攻撃に失敗しました', 'error');
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '⚔️ ボスを攻撃';
        // 通常攻撃用に戻す
        setTimeout(() => {
            const confirmBtn = document.getElementById('confirmAttackBtn');
            confirmBtn.onclick = confirmAttack;
            confirmBtn.innerHTML = '⚔️ 攻撃開始';
        }, 100);
        portalBossData = null;
    }
}

// ===============================================
// ヒーロー編成機能
// ===============================================

// ヒーロー編成データを読み込む
async function loadHeroAssignments() {
    const section = document.getElementById('heroAssignmentSection');
    section.innerHTML = '<div class="loading">読み込み中...</div>';
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_all_hero_assignments'})
        });
        const data = await res.json();
        
        if (!data.ok) {
            section.innerHTML = `<p style="color: #ff6b6b;">ヒーロー情報の読み込みに失敗しました: ${data.error || '不明なエラー'}</p>`;
            return;
        }
        
        // ヒーローがいない場合
        if (data.hero_count === 0) {
            section.innerHTML = `
                <div style="text-align: center; padding: 30px;">
                    <div style="font-size: 48px; margin-bottom: 20px;">🦸</div>
                    <h3 style="color: #ffd700; margin-bottom: 15px;">まだヒーローがいません</h3>
                    <p style="color: #c0a080; margin-bottom: 20px;">ヒーローガチャを回してヒーローを獲得しましょう！<br>ヒーローはバトルで強力なスキルを発動して戦闘を有利にします。</p>
                    <a href="./hero_system.php" class="invest-btn" style="display: inline-block; text-decoration: none; padding: 15px 30px; background: linear-gradient(135deg, #ff6b6b 0%, #ffd93d 100%);">
                        🎰 ヒーローガチャへ
                    </a>
                </div>
            `;
            return;
        }
        
        // バトルタイプごとの編成表示
        let html = '<div class="targets-list" style="gap: 20px;">';
        
        // バトルタイプごとに表示
        for (const [battleType, info] of Object.entries(data.battle_types)) {
            const assignment = data.assignments[battleType];
            
            html += `
                <div class="target-card" style="border-color: ${assignment ? '#ffd700' : '#555'}; background: ${assignment ? 'rgba(255, 215, 0, 0.1)' : 'rgba(50, 50, 50, 0.5)'};">
                    <div class="target-header" style="margin-bottom: 15px;">
                        <span class="target-name" style="font-size: 18px;">${info.icon} ${info.name}</span>
                    </div>
                    <div id="hero-slot-${battleType}">
                        ${renderHeroSlot(battleType, assignment)}
                    </div>
                    <div style="margin-top: 15px;">
                        <button onclick="openHeroSelectModal('${battleType}')" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 10px 20px; border-radius: 8px; border: none; color: white; cursor: pointer; width: 100%;">
                            ${assignment ? '🔄 変更' : '➕ ヒーローを設定'}
                        </button>
                        ${assignment ? `<button onclick="removeHeroAssignment('${battleType}')" class="action-btn" style="background: rgba(255, 100, 100, 0.3); padding: 10px 20px; border-radius: 8px; border: 1px solid #ff6b6b; color: #ff6b6b; cursor: pointer; width: 100%; margin-top: 10px;">
                            ❌ 解除
                        </button>` : ''}
                    </div>
                </div>
            `;
        }
        
        html += '</div>';
        
        // 所有ヒーロー一覧
        html += `
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid rgba(255, 215, 0, 0.3);">
                <h3 style="color: #ffd700; margin-bottom: 15px;">📋 所有ヒーロー一覧 (${data.hero_count}体)</h3>
                <div class="targets-list" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    ${data.available_heroes.map(hero => `
                        <div class="target-card" style="border-color: ${getRarityColor(hero.rarity)}; text-align: center;">
                            <div style="font-size: 48px;">${hero.icon}</div>
                            <div style="color: #ffd700; font-weight: bold; margin: 10px 0;">${hero.name}</div>
                            <div style="color: #c0a080; font-size: 12px; margin-bottom: 5px;">${hero.title || ''}</div>
                            <div style="color: #ffd700;">${'⭐'.repeat(hero.star_level)}</div>
                            <div style="margin-top: 10px; font-size: 12px; color: #87ceeb;">
                                <div>⚔️ ${hero.battle_skill_name || '未設定'}</div>
                                ${hero.battle_skill_2_name ? `<div>⚔️ ${hero.battle_skill_2_name}</div>` : ''}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        section.innerHTML = html;
        
        // ヒーローデータをグローバルに保存（モーダルで使用）
        window.heroAssignmentData = data;
        
    } catch (e) {
        console.error(e);
        section.innerHTML = '<p style="color: #ff6b6b;">ヒーロー情報の読み込みに失敗しました</p>';
    }
}

// ヒーロースロットを描画
function renderHeroSlot(battleType, assignment) {
    if (!assignment) {
        return `
            <div style="text-align: center; padding: 20px; color: #888;">
                <div style="font-size: 32px; opacity: 0.5;">👤</div>
                <div style="margin-top: 10px;">ヒーロー未設定</div>
            </div>
        `;
    }
    
    const skill1Name = assignment.skill_1_type === 2 ? assignment.battle_skill_2_name : assignment.battle_skill_name;
    const skill2Name = assignment.skill_2_type ? (assignment.skill_2_type === 2 ? assignment.battle_skill_2_name : assignment.battle_skill_name) : null;
    
    return `
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="font-size: 48px;">${assignment.hero_icon}</div>
            <div style="flex: 1;">
                <div style="color: #ffd700; font-weight: bold; font-size: 16px;">${assignment.hero_name}</div>
                <div style="color: #c0a080; font-size: 12px;">${assignment.hero_title || ''}</div>
                <div style="color: #ffd700; margin: 5px 0;">${'⭐'.repeat(assignment.star_level || 1)}</div>
                <div style="color: #87ceeb; font-size: 12px;">
                    ⚔️ ${skill1Name || 'スキル1'}
                    ${skill2Name ? ` / ⚔️ ${skill2Name}` : ''}
                </div>
            </div>
        </div>
    `;
}

// レアリティ色を取得
function getRarityColor(rarity) {
    const colors = {
        'common': '#808080',
        'uncommon': '#00cc00',
        'rare': '#0080ff',
        'epic': '#cc00ff',
        'legendary': '#ffcc00'
    };
    return colors[rarity] || '#808080';
}

// ヒーロー選択モーダルを開く
function openHeroSelectModal(battleType) {
    const data = window.heroAssignmentData;
    if (!data) {
        showNotification('データが読み込まれていません', true);
        return;
    }
    
    const battleInfo = data.battle_types[battleType];
    const currentAssignment = data.assignments[battleType];
    
    let modalHtml = `
        <div id="heroSelectModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px;">
            <div style="background: linear-gradient(135deg, #1e1e2f 0%, #2d2d44 100%); border-radius: 16px; padding: 30px; max-width: 600px; width: 100%; max-height: 80vh; overflow-y: auto; border: 2px solid #ffd700;">
                <h2 style="color: #ffd700; margin: 0 0 20px 0; text-align: center;">
                    ${battleInfo.icon} ${battleInfo.name}のヒーローを選択
                </h2>
                
                <div id="heroSelectList" style="display: grid; gap: 15px;">
    `;
    
    for (const hero of data.available_heroes) {
        const isSelected = currentAssignment && currentAssignment.hero_id === hero.hero_id;
        modalHtml += `
            <div class="hero-select-item" onclick="selectHeroForBattle('${battleType}', ${hero.hero_id})" 
                 style="background: ${isSelected ? 'rgba(255, 215, 0, 0.2)' : 'rgba(50, 50, 50, 0.5)'}; 
                        border: 2px solid ${isSelected ? '#ffd700' : getRarityColor(hero.rarity)}; 
                        border-radius: 12px; padding: 15px; cursor: pointer; display: flex; align-items: center; gap: 15px;
                        transition: all 0.3s;">
                <div style="font-size: 48px;">${hero.icon}</div>
                <div style="flex: 1;">
                    <div style="color: #ffd700; font-weight: bold;">${hero.name}</div>
                    <div style="color: #c0a080; font-size: 12px;">${hero.title || ''}</div>
                    <div style="color: #ffd700; margin: 5px 0;">${'⭐'.repeat(hero.star_level)}</div>
                    <div style="font-size: 12px; color: #87ceeb;">
                        ⚔️ ${hero.battle_skill_name || 'スキル1'}
                        ${hero.battle_skill_2_name ? ` | ⚔️ ${hero.battle_skill_2_name}` : ''}
                    </div>
                </div>
                ${isSelected ? '<div style="color: #ffd700; font-size: 24px;">✓</div>' : ''}
            </div>
        `;
    }
    
    modalHtml += `
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="closeHeroSelectModal()" style="padding: 12px 30px; background: rgba(100, 100, 100, 0.5); color: white; border: 1px solid #888; border-radius: 8px; cursor: pointer; font-size: 16px;">
                        キャンセル
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

// ヒーロー選択モーダルを閉じる
function closeHeroSelectModal() {
    const modal = document.getElementById('heroSelectModal');
    if (modal) {
        modal.remove();
    }
}

// バトルタイプにヒーローを設定
async function selectHeroForBattle(battleType, heroId) {
    closeHeroSelectModal();
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'set_battle_hero',
                battle_type: battleType,
                hero_id: heroId,
                skill_1_type: 1,  // デフォルトで第1スキルを使用
                skill_2_type: null
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadHeroAssignments(); // 再読み込み
        } else {
            showNotification(data.error || 'ヒーローの設定に失敗しました', true);
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', true);
    }
}

// ヒーロー割り当てを解除
async function removeHeroAssignment(battleType) {
    if (!confirm('このヒーローの編成を解除しますか？')) {
        return;
    }
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'set_battle_hero',
                battle_type: battleType,
                hero_id: null
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            loadHeroAssignments(); // 再読み込み
        } else {
            showNotification(data.error || 'ヒーローの解除に失敗しました', true);
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', true);
    }
}

// ===============================================
// メールシステム
// ===============================================
let mailCurrentPage = 1;
let mailCurrentFilter = 'all';
let mailUnreadCount = 0;

// メール一覧を読み込む
async function loadMails(page = 1, filter = null) {
    if (filter !== null) {
        mailCurrentFilter = filter;
    }
    mailCurrentPage = page;
    
    const mailList = document.getElementById('mail-list');
    if (!mailList) return;
    
    mailList.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">📭 メールを読み込み中...</div>';
    
    try {
        const body = {
            action: 'get_mails',
            page: page,
            per_page: 20
        };
        if (mailCurrentFilter !== 'all') {
            body.mail_type = mailCurrentFilter;
        }
        
        const res = await fetch('civilization_mail_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        
        if (data.ok) {
            mailUnreadCount = data.unread_count;
            updateMailBadge();
            renderMailList(data.mails, data.total_pages, page);
        } else {
            mailList.innerHTML = `<div style="text-align: center; padding: 40px; color: #ff6b6b;">エラー: ${data.error}</div>`;
        }
    } catch (e) {
        console.error(e);
        mailList.innerHTML = '<div style="text-align: center; padding: 40px; color: #ff6b6b;">読み込みエラー</div>';
    }
}

// メールリストを描画
function renderMailList(mails, totalPages, currentPage) {
    const mailList = document.getElementById('mail-list');
    if (!mailList) return;
    
    if (mails.length === 0) {
        mailList.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">📭 メールはありません</div>';
        return;
    }
    
    const typeIcons = {
        'info': '📢',
        'war': '⚔️',
        'conquest': '🏰',
        'reconnaissance': '🔭'
    };
    
    const typeNames = {
        'info': '情報',
        'war': '戦争',
        'conquest': '占領戦',
        'reconnaissance': '偵察'
    };
    
    let html = '';
    mails.forEach(mail => {
        const isUnread = !mail.is_read;
        const hasCompensation = mail.compensation && !mail.compensation_claimed;
        const date = new Date(mail.created_at);
        const dateStr = date.toLocaleDateString('ja-JP') + ' ' + date.toLocaleTimeString('ja-JP', {hour: '2-digit', minute: '2-digit'});
        
        html += `
            <div class="mail-item" data-mail-id="${mail.id}" 
                 style="background: ${isUnread ? 'rgba(100, 149, 237, 0.2)' : 'rgba(0,0,0,0.3)'}; 
                        border: 1px solid ${isUnread ? '#6495ed' : '#444'}; 
                        border-radius: 8px; padding: 12px; margin-bottom: 10px; cursor: pointer;
                        transition: all 0.3s;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                            <span style="font-size: 16px;">${typeIcons[mail.mail_type] || '📧'}</span>
                            <span style="color: ${isUnread ? '#87ceeb' : '#aaa'}; font-weight: ${isUnread ? 'bold' : 'normal'};">
                                ${mail.subject}
                            </span>
                            ${hasCompensation ? '<span style="background: #ffd700; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 10px;">🎁 補填あり</span>' : ''}
                        </div>
                        <div style="font-size: 12px; color: #888;">
                            差出人: ${mail.sender_display} → 受取人: ${mail.recipient_display}
                        </div>
                    </div>
                    <div style="text-align: right; font-size: 11px; color: #666;">
                        ${dateStr}
                    </div>
                </div>
            </div>
        `;
    });
    
    mailList.innerHTML = html;
    
    // ページネーション
    const pagination = document.getElementById('mail-pagination');
    if (pagination && totalPages > 1) {
        let pagHtml = '';
        for (let i = 1; i <= totalPages; i++) {
            pagHtml += `<button onclick="loadMails(${i})" 
                        style="padding: 5px 12px; border: 1px solid #6495ed; 
                               background: ${i === currentPage ? '#6495ed' : 'rgba(0,0,0,0.3)'};
                               color: #fff; border-radius: 4px; cursor: pointer;">${i}</button>`;
        }
        pagination.innerHTML = pagHtml;
    } else if (pagination) {
        pagination.innerHTML = '';
    }
    
    // クリックイベント
    document.querySelectorAll('.mail-item').forEach(item => {
        item.addEventListener('click', () => openMailDetail(item.dataset.mailId));
    });
}

// メール詳細を開く
async function openMailDetail(mailId) {
    try {
        const res = await fetch('civilization_mail_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_mail_detail', mail_id: parseInt(mailId)})
        });
        const data = await res.json();
        
        if (data.ok) {
            showMailDetailModal(data.mail);
            // 未読バッジを更新
            loadMails(mailCurrentPage);
        } else {
            showNotification(data.error || 'メールの読み込みに失敗しました', true);
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', true);
    }
}

// メール詳細モーダルを表示
function showMailDetailModal(mail) {
    const typeNames = {
        'info': '📢 情報',
        'war': '⚔️ 戦争',
        'conquest': '🏰 占領戦',
        'reconnaissance': '🔭 偵察'
    };
    
    const date = new Date(mail.created_at);
    const dateStr = date.toLocaleDateString('ja-JP') + ' ' + date.toLocaleTimeString('ja-JP');
    
    let compensationHtml = '';
    if (mail.compensation) {
        compensationHtml = '<div style="background: rgba(255, 215, 0, 0.2); border: 1px solid #ffd700; border-radius: 8px; padding: 15px; margin-top: 15px;">';
        compensationHtml += '<div style="color: #ffd700; font-weight: bold; margin-bottom: 10px;">🎁 補填内容:</div>';
        
        if (mail.compensation.coins) compensationHtml += `<div style="color: #fff;">🪙 コイン: ${mail.compensation.coins}</div>`;
        if (mail.compensation.crystals) compensationHtml += `<div style="color: #fff;">💎 クリスタル: ${mail.compensation.crystals}</div>`;
        if (mail.compensation.diamonds) compensationHtml += `<div style="color: #fff;">💠 ダイヤモンド: ${mail.compensation.diamonds}</div>`;
        if (mail.compensation.resources) {
            for (const [key, value] of Object.entries(mail.compensation.resources)) {
                compensationHtml += `<div style="color: #fff;">${key}: ${value}</div>`;
            }
        }
        
        if (!mail.compensation_claimed) {
            compensationHtml += `<button onclick="claimCompensation(${mail.id})" style="margin-top: 10px; padding: 10px 20px; background: linear-gradient(135deg, #ffd700, #ffa500); color: #000; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">🎁 補填を受け取る</button>`;
        } else {
            compensationHtml += '<div style="color: #90ee90; margin-top: 10px;">✅ 受け取り済み</div>';
        }
        compensationHtml += '</div>';
    }
    
    const modalHtml = `
        <div id="mailDetailModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center; z-index: 10000;" onclick="if(event.target===this) closeMailDetailModal()">
            <div style="background: linear-gradient(135deg, #1a0f0a 0%, #2d1810 100%); border: 2px solid #6495ed; border-radius: 16px; padding: 25px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div>
                        <div style="color: #6495ed; font-size: 12px; margin-bottom: 5px;">${typeNames[mail.mail_type] || '📧 メール'}</div>
                        <h3 style="color: #87ceeb; margin: 0;">${mail.subject}</h3>
                    </div>
                    <button onclick="closeMailDetailModal()" style="background: none; border: none; color: #888; font-size: 24px; cursor: pointer;">&times;</button>
                </div>
                <div style="color: #888; font-size: 12px; margin-bottom: 15px;">
                    差出人: ${mail.sender_display} → 受取人: ${mail.recipient_display}<br>
                    日時: ${dateStr}
                </div>
                <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 15px; white-space: pre-wrap; color: #f5deb3; line-height: 1.6;">
                    ${escapeHtml(mail.body)}
                </div>
                ${compensationHtml}
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function closeMailDetailModal() {
    const modal = document.getElementById('mailDetailModal');
    if (modal) modal.remove();
}

// escapeHtml is already defined above (line 4373)

// 補填を受け取る
async function claimCompensation(mailId) {
    if (!confirm('補填を受け取りますか？')) return;
    
    try {
        const res = await fetch('civilization_mail_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'claim_compensation', mail_id: mailId})
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            closeMailDetailModal();
            loadMails(mailCurrentPage);
            loadData(); // 資源を更新
        } else {
            showNotification(data.error || '補填の受け取りに失敗しました', true);
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', true);
    }
}

// メールバッジを更新
function updateMailBadge() {
    const badge = document.getElementById('mail-badge');
    if (badge) {
        if (mailUnreadCount > 0) {
            badge.textContent = mailUnreadCount > 99 ? '99+' : mailUnreadCount;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}

// 偵察レート制限を読み込む
async function loadReconnaissanceStatus() {
    try {
        const res = await fetch('civilization_mail_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_reconnaissance_rate_limit'})
        });
        const data = await res.json();
        
        if (data.ok) {
            // 戦争偵察の表示を更新
            updateReconnaissanceRateLimitDisplay('war', data.war);
            
            // 占領戦偵察の表示を更新
            updateReconnaissanceRateLimitDisplay('conquest', data.conquest);
        }
    } catch (e) {
        console.error(e);
    }
}

// 偵察レート制限の表示を更新
function updateReconnaissanceRateLimitDisplay(type, limitData) {
    const prefix = type === 'war' ? 'warRecon' : 'conquestRecon';
    const sectionEl = document.getElementById(`${prefix}RateLimitSection`);
    const remainingEl = document.getElementById(`${prefix}Remaining`);
    const barEl = document.getElementById(`${prefix}ProgressBar`);
    const messageEl = document.getElementById(`${prefix}Message`);
    
    if (!sectionEl || !remainingEl || !barEl || !messageEl) return;
    
    const remaining = limitData.remaining || 0;
    const limit = limitData.limit || (type === 'war' ? 5 : 15);
    const isLimited = limitData.is_limited || false;
    const waitSeconds = limitData.wait_seconds || 0;
    
    // 残り回数を表示
    remainingEl.textContent = `${remaining} / ${limit}`;
    
    // プログレスバーを更新
    const percentage = (remaining / limit) * 100;
    barEl.style.width = `${percentage}%`;
    
    // バーの色を更新
    if (remaining === 0) {
        barEl.style.background = 'linear-gradient(90deg, #8b0000 0%, #dc143c 100%)';
        sectionEl.style.borderColor = '#ff6b6b';
    } else if (remaining <= 2) {
        barEl.style.background = 'linear-gradient(90deg, #ffa500 0%, #ff6b6b 100%)';
        sectionEl.style.borderColor = type === 'war' ? '#ffa500' : '#da70d6';
    } else {
        barEl.style.background = 'linear-gradient(90deg, #32cd32 0%, #228b22 100%)';
        sectionEl.style.borderColor = type === 'war' ? '#ffa500' : '#da70d6';
    }
    
    // メッセージを更新
    if (isLimited) {
        const hours = Math.floor(waitSeconds / 3600);
        const minutes = Math.floor((waitSeconds % 3600) / 60);
        let timeText = '';
        if (hours > 0) {
            timeText = `${hours}時間${minutes}分`;
        } else if (minutes > 0) {
            timeText = `${minutes}分`;
        } else {
            timeText = '1分未満';
        }
        
        messageEl.innerHTML = `⚠️ <span style="color: #ff6b6b; font-weight: bold;">制限中</span> - 次まで <span style="color: #ffd700; font-weight: bold;">${timeText}</span>`;
        sectionEl.style.background = 'rgba(139, 0, 0, 0.3)';
    } else if (remaining === 1) {
        messageEl.innerHTML = `⚠️ あと <span style="color: #ffd700; font-weight: bold;">1回</span> で制限`;
        sectionEl.style.background = 'rgba(139, 69, 0, 0.2)';
    } else if (remaining <= 3) {
        messageEl.innerHTML = `💡 あと <span style="color: #ffd700;">${remaining}回</span> 偵察可能`;
        sectionEl.style.background = 'rgba(139, 69, 0, 0.15)';
    } else {
        messageEl.innerHTML = `✅ 偵察可能（残り${remaining}回）`;
        sectionEl.style.background = 'rgba(0,0,0,0.3)';
    }
}

// 偵察を実行
async function performReconnaissance(type, targetId, targetName, castleInfo = null) {
    const confirmMessage = type === 'war' 
        ? `${targetName}の防御部隊を偵察しますか？\n\n⚠️ 30%の確率で失敗します。`
        : `${castleInfo?.name || '城'}(${castleInfo?.coords || ''})を偵察しますか？\n\n⚠️ 30%の確率で失敗します。`;
    
    if (!confirm(confirmMessage)) return;
    
    try {
        const body = type === 'war' 
            ? { action: 'reconnaissance_war', target_user_id: targetId }
            : { action: 'reconnaissance_conquest', castle_id: targetId };
        
        const res = await fetch('civilization_mail_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        
        if (data.ok) {
            if (data.success) {
                showNotification(`🔭 ${data.message}`);
                // メールタブの未読数を更新
                loadMailUnreadCount();
            } else {
                showNotification(`❌ ${data.message}`, true);
            }
            
            // レート制限表示を更新（新しいプログレスバー付き表示）
            loadReconnaissanceStatus();
        } else {
            showNotification(data.error || '偵察に失敗しました', true);
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', true);
    }
}

// メールフィルターのイベントリスナー
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('mail-filter-btn')) {
        document.querySelectorAll('.mail-filter-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.style.background = 'rgba(0,0,0,0.3)';
        });
        e.target.classList.add('active');
        e.target.style.background = 'rgba(100, 149, 237, 0.3)';
        
        loadMails(1, e.target.dataset.filter);
    }
});

// タブ切り替え時にメールを読み込む
const originalTabHandler = document.querySelector('.tab-btn[data-tab="mail"]');
if (originalTabHandler) {
    originalTabHandler.addEventListener('click', () => {
        loadMails(1, mailCurrentFilter);
        loadReconnaissanceStatus();
    });
}

// 初期未読カウント取得
async function loadMailUnreadCount() {
    try {
        const res = await fetch('civilization_mail_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_unread_count'})
        });
        const data = await res.json();
        if (data.ok) {
            mailUnreadCount = data.unread_count;
            updateMailBadge();
        }
    } catch (e) {
        console.error(e);
    }
}

// 初期読み込み時に未読数を取得
setTimeout(loadMailUnreadCount, 1000);

// 初期読み込み時に偵察レート制限を取得
setTimeout(loadReconnaissanceStatus, 1500);

// ===============================================
// 保護設定機能のJavaScript
// ===============================================

// 保護設定を読み込む
async function loadProtectionSettings() {
    await loadVaultProtection();
    await loadShelterProtection();
}

// 保管庫の保護設定を読み込む
async function loadVaultProtection() {
    const vaultInfo = document.getElementById('vault-info');
    const vaultResources = document.getElementById('vault-resources');
    
    if (!vaultInfo || !vaultResources) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_vault_protections'})
        });
        const data = await res.json();
        
        if (!data.ok) {
            vaultInfo.innerHTML = `<p style="color: #ff6b6b;">${data.error}</p>`;
            vaultResources.innerHTML = '';
            return;
        }
        
        vaultInfo.innerHTML = `
            <div style="font-size: 14px;">
                <p>🏦 保管庫レベル: <span style="color: #ffd700;">${data.vault_level}</span></p>
                <p>📦 保護可能容量: <span style="color: #90ee90;">${data.total_capacity.toLocaleString()}</span></p>
                <p style="color: #888; font-size: 12px; margin-top: 5px;">※ 保護容量 = 人口 × 20 × 保管庫レベル</p>
            </div>
        `;
        
        // 資源リストを取得して表示
        const resources = civData.resources.filter(r => r.unlocked);
        let html = '';
        
        // コインも追加
        const coinProtection = data.protections.find(p => p.resource_type === 'coins');
        html += renderVaultResourceItem('coins', '🪙 コイン', coinProtection?.protected_amount || 0, civData.balance.coins);
        
        // 各資源
        resources.forEach(r => {
            const protection = data.protections.find(p => p.resource_type == r.resource_type_id);
            html += renderVaultResourceItem(r.resource_type_id, `${r.icon} ${r.name}`, protection?.protected_amount || 0, r.amount);
        });
        
        vaultResources.innerHTML = html;
        
    } catch (e) {
        console.error(e);
        vaultInfo.innerHTML = '<p style="color: #ff6b6b;">読み込みエラー</p>';
    }
}

function renderVaultResourceItem(resourceType, label, protectedAmount, totalAmount) {
    return `
        <div style="background: rgba(0,0,0,0.4); padding: 15px; border-radius: 8px; border: 1px solid #555;">
            <div style="margin-bottom: 10px;">
                <span style="color: #ffd700; font-weight: bold;">${label}</span>
                <div style="color: #c0a080; font-size: 13px; margin-top: 5px;">
                    所有: ${totalAmount.toLocaleString()} / 保護中: <span style="color: #90ee90;">${protectedAmount.toLocaleString()}</span>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="number" id="vault-${resourceType}" value="${protectedAmount}" min="0" max="${totalAmount}" 
                       style="flex: 1; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #666; border-radius: 4px; color: #fff;">
                <button onclick="saveVaultProtection('${resourceType}')" 
                        style="padding: 8px 15px; background: linear-gradient(135deg, #d4af37, #b8860b); color: #000; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                    保存
                </button>
            </div>
        </div>
    `;
}

async function saveVaultProtection(resourceType) {
    const input = document.getElementById(`vault-${resourceType}`);
    const amount = parseInt(input.value) || 0;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'set_vault_protection',
                resource_type: resourceType,
                amount: amount
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            await loadVaultProtection();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', true);
    }
}

// シェルターの保護設定を読み込む
async function loadShelterProtection() {
    const shelterInfo = document.getElementById('shelter-info');
    const shelterTroops = document.getElementById('shelter-troops');
    
    if (!shelterInfo || !shelterTroops) return;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_shelter_protections'})
        });
        const data = await res.json();
        
        if (!data.ok) {
            shelterInfo.innerHTML = `<p style="color: #ff6b6b;">${data.error}</p>`;
            shelterTroops.innerHTML = '';
            return;
        }
        
        shelterInfo.innerHTML = `
            <div style="font-size: 14px;">
                <p>🛡️ シェルターレベル: <span style="color: #ffd700;">${data.shelter_level}</span></p>
                <p>👥 保護可能兵士数: <span style="color: #90ee90;">${data.total_capacity.toLocaleString()}</span></p>
                <p style="color: #888; font-size: 12px; margin-top: 5px;">※ 保護可能数 = 軍事力 × 0.001 × シェルターレベル</p>
            </div>
        `;
        
        // 兵種リストを取得
        const troopsRes = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_troops'})
        });
        const troopsData = await troopsRes.json();
        
        if (!troopsData.ok) {
            shelterTroops.innerHTML = '<p style="color: #ff6b6b;">兵種データの読み込みに失敗しました</p>';
            return;
        }
        
        let html = '';
        troopsData.troops.forEach(t => {
            const protection = data.protections.find(p => p.troop_type_id == t.troop_type_id);
            html += renderShelterTroopItem(t.troop_type_id, t.icon, t.name, protection?.protected_count || 0, t.count);
        });
        
        if (html === '') {
            html = '<p style="color: #888;">訓練済みの兵士がいません</p>';
        }
        
        shelterTroops.innerHTML = html;
        
    } catch (e) {
        console.error(e);
        shelterInfo.innerHTML = '<p style="color: #ff6b6b;">読み込みエラー</p>';
    }
}

function renderShelterTroopItem(troopTypeId, icon, name, protectedCount, totalCount) {
    return `
        <div style="background: rgba(0,0,0,0.4); padding: 15px; border-radius: 8px; border: 1px solid #555;">
            <div style="margin-bottom: 10px;">
                <span style="color: #ffd700; font-weight: bold;">${icon} ${name}</span>
                <div style="color: #c0a080; font-size: 13px; margin-top: 5px;">
                    所有: ${totalCount.toLocaleString()} / 保護中: <span style="color: #90ee90;">${protectedCount.toLocaleString()}</span>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="number" id="shelter-${troopTypeId}" value="${protectedCount}" min="0" max="${totalCount}" 
                       style="flex: 1; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #666; border-radius: 4px; color: #fff;">
                <button onclick="saveShelterProtection(${troopTypeId})" 
                        style="padding: 8px 15px; background: linear-gradient(135deg, #d4af37, #b8860b); color: #000; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                    保存
                </button>
            </div>
        </div>
    `;
}

async function saveShelterProtection(troopTypeId) {
    const input = document.getElementById(`shelter-${troopTypeId}`);
    const count = parseInt(input.value) || 0;
    
    try {
        const res = await fetch('civilization_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'set_shelter_protection',
                troop_type_id: troopTypeId,
                count: count
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.message);
            await loadShelterProtection();
        } else {
            showNotification(data.error, true);
        }
    } catch (e) {
        console.error(e);
        showNotification('エラーが発生しました', true);
    }
}

// 初期読み込み
loadData();
startUpdateTimer();
setupInteractionListeners();
</script>
</body>
</html>
