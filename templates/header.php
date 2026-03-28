<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth/user.php';
$currentUser = getCurrentUser();
$isDashboardPage = mb_strtolower((string) ($pageTitle ?? '')) === 'dashboard';
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <style>
        :root {
            /* === LIGHT MODE === */
            --bg: #ececec;
            --panel: #f2f2f2;
            --panel-2: #efefef;
            --line: #c5c5c5;
            --line-soft: #d6d6d6;
            --text: #111;
            --text-secondary: #333;
            --muted: #666;
            --accent: #5ea7e8;
            --accent-soft: #d7e8f8;
            --accent-hover: #4a95d9;
            --rail-bg: #fff;
            --rail-border: #e0e0e0;
            --nav-text: #444;
            --nav-active-bg: #e8f2ff;
            --nav-active-text: #12426f;
            --table-stripe: #f5f5f5;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --transition-color: 0.2s ease;
        }

        html.dark-mode {
            /* === DARK MODE - Professional Color Palette === */
            --bg: #0f0f0f;
            --panel: #1a1a1a;
            --panel-2: #242424;
            --line: #3a3a3a;
            --line-soft: #4a4a4a;
            --text: #e5e5e5;
            --text-secondary: #d0d0d0;
            --muted: #909090;
            --accent: #5eb3ff;
            --accent-soft: #1a3a5a;
            --accent-hover: #4a9ae6;
            --rail-bg: #141414;
            --rail-border: #2a2a2a;
            --nav-text: #b0b0b0;
            --nav-active-bg: #1a3a5a;
            --nav-active-text: #5eb3ff;
            --table-stripe: #1f1f1f;
            --success: #34d399;
            --warning: #fbbf24;
            --error: #f87171;
            --transition-color: 0.2s ease;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", "Helvetica Neue", Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 11px;
            line-height: 1.28;
            transition: background-color var(--transition-color), color var(--transition-color);
        }

        .svws-app {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 54px 1fr;
        }

        .svws-rail {
            background: var(--rail-bg);
            border-right: 1px solid var(--rail-border);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 12px 6px;
            transition: background-color var(--transition-color), border-color var(--transition-color);
        }

        .svws-brand {
            width: 34px;
            height: 34px;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: var(--panel-2);
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 18px;
            color: var(--accent);
            transition: all var(--transition-color);
        }

        .svws-nav {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 6px;
        }

        .svws-nav a {
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
            color: var(--nav-text);
            border-radius: 3px;
            padding: 7px 2px;
            line-height: 1.1;
            border: 1px solid transparent;
            font-size: 9px;
            letter-spacing: 0.2px;
            transition: all var(--transition-color);
        }

        .svws-nav-icon {
            display: block;
            font-size: 17px;
            line-height: 1;
            margin-bottom: 2px;
            transition: color var(--transition-color);
        }

        .svws-nav-label {
            display: block;
            line-height: 1.05;
        }

        .svws-nav a:hover {
            background: var(--nav-active-bg);
            border-color: var(--accent);
            color: var(--accent);
        }

        .svws-nav a.active {
            background: var(--nav-active-bg);
            border-color: var(--accent);
            color: var(--nav-active-text);
            font-weight: 600;
        }

        .svws-spacer {
            flex: 1;
        }

        .svws-workspace {
            padding: 2px 4px 4px;
        }

        .svws-topbar {
            background: var(--panel);
            border: 1px solid var(--line-soft);
            border-radius: 10px;
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all var(--transition-color);
        }

        .svws-topbar h1 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.1px;
            color: var(--text);
        }

        .svws-topbar h1.svws-topbar-title-dashboard {
            font-size: 34px;
            line-height: 1;
        }

        .svws-top-meta {
            color: var(--muted);
            font-size: 10px;
        }

        .svws-help-btn {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 2px 8px;
            background: var(--panel-2);
            color: var(--text);
            font-size: 10px;
            line-height: 1.2;
            cursor: pointer;
            transition: all var(--transition-color);
        }

        .svws-help-btn:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: var(--accent);
        }

        #dark-mode-toggle {
            appearance: none;
            -webkit-appearance: none;
            width: 40px;
            height: 20px;
            background: var(--line);
            border: 1px solid var(--line-soft);
            border-radius: 10px;
            cursor: pointer;
            transition: background var(--transition-color);
            position: relative;
        }

        #dark-mode-toggle:checked {
            background: var(--accent);
        }

        #dark-mode-toggle:before {
            content: '☀️';
            position: absolute;
            left: 2px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
        }

        #dark-mode-toggle:checked:before {
            content: '🌙';
            left: 21px;
        }

        .svws-btn-modern {
            border: 1px solid #8ea5bc;
            border-radius: 6px;
            padding: 5px 10px;
            background: linear-gradient(180deg, #ffffff 0%, #f1f6fb 100%);
            color: #1a3f63;
            font-size: 10px;
            font-weight: 600;
            line-height: 1.2;
            transition: all var(--transition-color);
            cursor: pointer;
        }

        .svws-btn-modern:hover {
            border-color: #6f8eac;
            background: linear-gradient(180deg, #fdfefe 0%, #e8f1f9 100%);
            color: #123b61;
        }

        .svws-btn-modern:active {
            background: #deebf7;
        }

        html.dark-mode .svws-btn-modern {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: var(--accent);
        }

        html.dark-mode .svws-btn-modern:hover {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent-hover);
        }

        .svws-action-buttons {
            display: flex;
            gap: 4px;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: wrap;
        }

        .svws-icon-btn {
            width: 26px;
            height: 26px;
            padding: 0;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #8ea5bc;
            background: linear-gradient(180deg, #ffffff 0%, #f1f6fb 100%);
            color: #1b476f;
            transition: all var(--transition-color);
            cursor: pointer;
        }

        .svws-icon-btn i {
            font-size: 14px;
            line-height: 1;
        }

        .svws-icon-btn:hover {
            border-color: #6f8eac;
            background: linear-gradient(180deg, #fdfefe 0%, #e8f1f9 100%);
        }

        html.dark-mode .svws-icon-btn {
            border-color: var(--line);
            background: var(--panel-2);
            color: var(--accent);
        }

        html.dark-mode .svws-icon-btn:hover {
            background: var(--accent-soft);
            border-color: var(--accent);
        }

        .svws-icon-btn.svws-icon-btn-warning {
            border-color: #c6995d;
            color: #7a4c0c;
            background: linear-gradient(180deg, #fff9f1 0%, #f7e9d6 100%);
        }

        html.dark-mode .svws-icon-btn.svws-icon-btn-warning {
            border-color: var(--warning);
            color: var(--warning);
            background: rgba(251, 191, 36, 0.1);
        }

        html.dark-mode .svws-icon-btn.svws-icon-btn-warning:hover {
            background: var(--warning);
            color: #000;
        }

        .svws-icon-btn.svws-icon-btn-danger {
            border-color: #c08b8b;
            color: #7a1f1f;
            background: linear-gradient(180deg, #fff5f5 0%, #f7dfdf 100%);
        }

        html.dark-mode .svws-icon-btn.svws-icon-btn-danger {
            border-color: var(--error);
            color: var(--error);
            background: rgba(248, 113, 113, 0.1);
        }

        html.dark-mode .svws-icon-btn.svws-icon-btn-danger:hover {
            background: var(--error);
            color: #fff;
        }

        .svws-main {
            margin-top: 6px;
            background: var(--bg);
            border: none;
            border-radius: 12px;
            min-height: calc(100vh - 58px);
            padding: 8px 8px 12px;
            transition: background-color var(--transition-color);
        }

        .svws-main h2 {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .svws-main h3 {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }

        .svws-muted {
            color: var(--muted);
        }

        .svws-split {
            display: grid;
            grid-template-columns: 238px 1fr;
            gap: 8px;
        }

        .svws-panel {
            background: var(--panel);
            border: 1px solid var(--line-soft);
            border-radius: 10px;
            overflow: hidden;
            transition: all var(--transition-color);
        }

        .svws-panel-header {
            padding: 7px 8px;
            border-bottom: 1px solid var(--line-soft);
            background: var(--panel-2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all var(--transition-color);
        }

        .svws-panel-body {
            padding: 7px;
            transition: background-color var(--transition-color);
        }

        .svws-search {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 5px 8px;
            font-size: 11px;
            background: var(--panel);
            color: var(--text);
            height: 30px;
            transition: all var(--transition-color);
        }

        .svws-search:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px var(--accent-soft);
        }

        .svws-filter {
            width: 100%;
            margin-top: 5px;
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 11px;
            background: var(--panel);
            color: var(--text);
            height: 30px;
            transition: all var(--transition-color);
        }

        .svws-chip-row {
            margin-top: 4px;
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .svws-chip {
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 1px 6px;
            background: var(--panel-2);
            font-size: 10px;
            color: var(--text);
            transition: all var(--transition-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--panel);
            table-layout: fixed;
            transition: background-color var(--transition-color);
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 3px 5px;
            text-align: left;
            vertical-align: top;
            font-size: 11px;
            line-height: 1.25;
            color: var(--text);
            transition: all var(--transition-color);
        }

        th {
            background: var(--panel-2);
            font-weight: 700;
            white-space: nowrap;
            border-top: 1px solid var(--line);
            transition: all var(--transition-color);
        }

        .svws-row-active {
            background: var(--accent-soft);
        }

        .svws-row-active td {
            color: var(--accent);
            font-weight: 600;
        }

        .svws-row-active a {
            color: var(--accent);
        }

        .svws-tight td {
            height: 24px;
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .svws-grid-note {
            margin-top: 6px;
            color: var(--muted);
            font-size: 10px;
        }

        .svws-header-title {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
        }

        .svws-header-sub {
            margin-top: 2px;
            color: var(--muted);
            font-size: 11px;
            transition: color var(--transition-color);
        }

        .svws-content-header {
            display: grid;
            grid-template-columns: 42px 1fr;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .svws-avatar {
            width: 40px;
            height: 40px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: var(--panel-2);
            display: grid;
            place-items: center;
            color: var(--text-secondary);
            font-size: 20px;
            font-weight: 700;
            transition: background-color var(--transition-color), border-color var(--transition-color), color var(--transition-color);
        }

        .svws-title-main {
            font-size: 27px;
            font-weight: 700;
            margin: 0;
            line-height: 1;
        }

        .svws-title-sub {
            margin-top: 2px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            transition: color var(--transition-color);
        }

        .svws-tabs {
            display: flex;
            gap: 6px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 4px;
            margin-bottom: 6px;
            transition: border-color var(--transition-color);
        }

        .svws-tab {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 4px;
            border-bottom: 2px solid transparent;
            transition: color var(--transition-color), border-color var(--transition-color);
        }

        .svws-tab.active {
            border-bottom-color: var(--accent);
            color: var(--accent);
        }

        .svws-kpis {
            display: grid;
            grid-template-columns: repeat(3, minmax(140px, 220px));
            gap: 10px;
            margin-top: 10px;
        }

        .svws-kpi {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px;
            transition: background-color var(--transition-color), border-color var(--transition-color);
        }

        .svws-kpi-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            transition: color var(--transition-color);
        }

        .svws-kpi-label {
            color: var(--muted);
            margin-top: 2px;
        }

        .svws-card-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .svws-nav-card {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            text-decoration: none;
            color: inherit;
            background: var(--panel);
            border: 2px solid var(--accent);
            border-radius: 12px;
            min-height: 164px;
            padding: 18px;
            transition: transform 0.12s ease, box-shadow 0.12s ease, border-color var(--transition-color), background-color var(--transition-color);
        }

        .svws-nav-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 14px rgba(0, 0, 0, 0.1);
            border-color: var(--accent-hover);
        }

        html.dark-mode .svws-nav-card:hover {
            box-shadow: 0 8px 14px rgba(94, 179, 255, 0.15);
        }

        .svws-nav-card-icon {
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            border-radius: 10px;
            background: var(--accent-soft);
            border: 1px solid var(--line);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color var(--transition-color), border-color var(--transition-color);
        }

        .svws-nav-card-icon i {
            font-size: 24px;
            line-height: 1;
            color: var(--accent);
            transition: color var(--transition-color);
        }

        .svws-nav-card-body {
            display: flex;
            flex-direction: column;
        }

        .svws-nav-card-title {
            font-size: 17px;
            font-weight: 700;
            margin: 2px 0 8px;
        }

        .svws-nav-card-text {
            color: var(--muted);
            margin: 0;
            font-size: 13px;
            line-height: 1.45;
        }

        @media (max-width: 780px) {
            .svws-card-grid {
                grid-template-columns: 1fr;
            }

            .svws-nav-card {
                min-height: 150px;
                padding: 16px;
            }
        }

        .svws-school-meta-title {
            margin: 2px 0 14px;
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            transition: color var(--transition-color);
        }

        .svws-school-meta-details {
            font-weight: 400;
            color: var(--muted);
            font-size: 13px;
        }

        .svws-list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .svws-list td {
            border-bottom: 1px solid var(--line);
            padding: 5px 4px;
            transition: border-color var(--transition-color);
        }

        .svws-list a {
            color: var(--accent);
            text-decoration: none;
            transition: color var(--transition-color);
        }

        .svws-list a:hover {
            text-decoration: underline;
        }

        @media (max-width: 1024px) {
            .svws-split {
                grid-template-columns: 1fr;
            }

            .svws-kpis {
                grid-template-columns: 1fr;
            }

            .svws-content-header {
                grid-template-columns: 1fr;
            }

            .svws-topbar h1.svws-topbar-title-dashboard {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
<div class="svws-app">
    <aside class="svws-rail">
        <div class="svws-brand">AD</div>
        <nav class="svws-nav">
            <a class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="/dashboard.php">
                <i class="ri-school-line svws-nav-icon" aria-hidden="true"></i>
                <span class="svws-nav-label">Schule</span>
            </a>
            <a class="<?= $activeNav === 'media' ? 'active' : '' ?>" href="/media_list.php">
                <i class="ri-book-open-line svws-nav-icon" aria-hidden="true"></i>
                <span class="svws-nav-label">Medien</span>
            </a>
            <a class="<?= $activeNav === 'data' ? 'active' : '' ?>" href="/sync_data.php">
                <i class="ri-database-2-line svws-nav-icon" aria-hidden="true"></i>
                <span class="svws-nav-label">Daten</span>
            </a>
            <a class="<?= $activeNav === 'lending' ? 'active' : '' ?>" href="/lending.php">
                <i class="ri-exchange-line svws-nav-icon" aria-hidden="true"></i>
                <span class="svws-nav-label">Leihe</span>
            </a>
            <a class="<?= $activeNav === 'sync' ? 'active' : '' ?>" href="/sync_svws.php">
                <i class="ri-arrow-left-right-line svws-nav-icon" aria-hidden="true"></i>
                <span class="svws-nav-label">Sync</span>
            </a>
            <a class="<?= $activeNav === 'reports' ? 'active' : '' ?>" href="/reports.php">
                <i class="ri-printer-line svws-nav-icon" aria-hidden="true"></i>
                <span class="svws-nav-label">Druck</span>
            </a>
        </nav>
        <div class="svws-spacer"></div>
        <nav class="svws-nav">
            <a class="<?= $activeNav === 'admin' ? 'active' : '' ?>" href="/users.php">
                <i class="ri-settings-3-line svws-nav-icon" aria-hidden="true"></i>
                <span class="svws-nav-label">User</span>
            </a>
            <a href="/logout.php">
                <i class="ri-logout-box-r-line svws-nav-icon" aria-hidden="true"></i>
                <span class="svws-nav-label">Abm</span>
            </a>
        </nav>
    </aside>

    <div class="svws-workspace">
        <header class="svws-topbar">
            <div>
                <h1 class="<?= $isDashboardPage ? 'svws-topbar-title-dashboard' : '' ?>"><?= htmlspecialchars($pageTitle) ?></h1>
                <div class="svws-top-meta">
                    <?= htmlspecialchars($currentUser['username'] ?? 'Gast') ?> | <?= htmlspecialchars($currentUser['role'] ?? 'viewer') ?>
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <input type="checkbox" id="dark-mode-toggle" style="width: 40px; height: 24px; cursor: pointer;" aria-label="Dark Mode Toggle">
                <button class="svws-help-btn" type="button" onclick="alert('Hilfe wird bald verfügbar');">Hilfe</button>
            </div>
        </header>
        <main class="svws-main">
