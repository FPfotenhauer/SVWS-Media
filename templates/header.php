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
            --bg: #ececec;
            --panel: #f2f2f2;
            --panel-2: #efefef;
            --line: #c5c5c5;
            --line-soft: #d6d6d6;
            --text: #111;
            --muted: #666;
            --accent: #5ea7e8;
            --accent-soft: #d7e8f8;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", "Helvetica Neue", Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 11px;
            line-height: 1.28;
        }

        .svws-app {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 54px 1fr;
        }

        .svws-rail {
            background: #fff;
            border-right: 1px solid var(--line-soft);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 12px 6px;
        }

        .svws-brand {
            width: 34px;
            height: 34px;
            border: 1px solid #a8a8a8;
            border-radius: 7px;
            background: #fafafa;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 8px;
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
            color: #444;
            border-radius: 3px;
            padding: 7px 2px;
            line-height: 1.1;
            border: 1px solid transparent;
            font-size: 9px;
            letter-spacing: 0.2px;
        }

        .svws-nav-icon {
            display: block;
            font-size: 17px;
            line-height: 1;
            margin-bottom: 2px;
        }

        .svws-nav-label {
            display: block;
            line-height: 1.05;
        }

        .svws-nav a:hover {
            background: #eef4fa;
            border-color: #d2e0ee;
        }

        .svws-nav a.active {
            background: var(--accent-soft);
            border-color: #bad5ef;
            color: #12426f;
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
        }

        .svws-topbar h1 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.1px;
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
            border: 1px solid #9b9b9b;
            border-radius: 12px;
            padding: 2px 8px;
            background: #fff;
            font-size: 10px;
            line-height: 1.2;
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
            transition: background-color 0.12s ease, border-color 0.12s ease, color 0.12s ease;
        }

        .svws-btn-modern:hover {
            border-color: #6f8eac;
            background: linear-gradient(180deg, #fdfefe 0%, #e8f1f9 100%);
            color: #123b61;
            cursor: pointer;
        }

        .svws-btn-modern:active {
            background: #deebf7;
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
        }

        .svws-icon-btn i {
            font-size: 14px;
            line-height: 1;
        }

        .svws-icon-btn.svws-icon-btn-warning {
            border-color: #c6995d;
            color: #7a4c0c;
            background: linear-gradient(180deg, #fff9f1 0%, #f7e9d6 100%);
        }

        .svws-icon-btn.svws-icon-btn-danger {
            border-color: #c08b8b;
            color: #7a1f1f;
            background: linear-gradient(180deg, #fff5f5 0%, #f7dfdf 100%);
        }

        .svws-icon-btn:hover {
            border-color: #6f8eac;
            background: linear-gradient(180deg, #fdfefe 0%, #e8f1f9 100%);
            cursor: pointer;
        }

        .svws-main {
            margin-top: 6px;
            background: var(--panel);
            border: 1px solid var(--line-soft);
            border-radius: 12px;
            min-height: calc(100vh - 58px);
            padding: 8px 8px 12px;
        }

        .svws-main h2 {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 700;
        }

        .svws-main h3 {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
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
            background: var(--panel-2);
            border: 1px solid var(--line-soft);
            border-radius: 10px;
            overflow: hidden;
        }

        .svws-panel-header {
            padding: 7px 8px;
            border-bottom: 1px solid var(--line-soft);
            background: #ededed;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .svws-panel-body {
            padding: 7px;
        }

        .svws-search {
            width: 100%;
            border: 1px solid #b2b2b2;
            border-radius: 4px;
            padding: 5px 8px;
            font-size: 11px;
            background: #fff;
            height: 30px;
        }

        .svws-filter {
            width: 100%;
            margin-top: 5px;
            border: 1px solid #b2b2b2;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 11px;
            background: #fff;
            color: #666;
            height: 30px;
        }

        .svws-chip-row {
            margin-top: 4px;
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .svws-chip {
            border: 1px solid #b2b2b2;
            border-radius: 4px;
            padding: 1px 6px;
            background: #f9f9f9;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fdfdfd;
            table-layout: fixed;
        }

        th,
        td {
            border-bottom: 1px solid #b9b9b9;
            padding: 3px 5px;
            text-align: left;
            vertical-align: top;
            font-size: 11px;
            line-height: 1.25;
        }

        th {
            background: #efefef;
            font-weight: 700;
            white-space: nowrap;
            border-top: 1px solid #c4c4c4;
        }

        .svws-row-active {
            background: #c9d9e8;
        }

        .svws-row-active td {
            color: #114777;
            font-weight: 600;
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
            color: #6b6b6b;
            font-size: 11px;
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
            border: 1px solid #b4b4b4;
            border-radius: 6px;
            background: #ededed;
            display: grid;
            place-items: center;
            color: #7a7a7a;
            font-size: 20px;
            font-weight: 700;
        }

        .svws-title-main {
            font-size: 27px;
            font-weight: 700;
            margin: 0;
            line-height: 1;
        }

        .svws-title-sub {
            margin-top: 2px;
            color: #666;
            font-size: 11px;
            font-weight: 600;
        }

        .svws-tabs {
            display: flex;
            gap: 6px;
            border-bottom: 1px solid #d4d4d4;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .svws-tab {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 4px;
            border-bottom: 2px solid transparent;
        }

        .svws-tab.active {
            border-bottom-color: var(--accent);
            color: #0e5b93;
        }

        .svws-kpis {
            display: grid;
            grid-template-columns: repeat(3, minmax(140px, 220px));
            gap: 10px;
            margin-top: 10px;
        }

        .svws-kpi {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px;
        }

        .svws-kpi-value {
            font-size: 24px;
            font-weight: 700;
        }

        .svws-kpi-label {
            color: var(--muted);
            margin-top: 2px;
        }

        .svws-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 14px;
        }

        .svws-nav-card {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            text-decoration: none;
            color: inherit;
            background: #fff;
            border: 2px solid #2f6fae;
            border-radius: 12px;
            min-height: 164px;
            padding: 18px;
            transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
        }

        .svws-nav-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 14px rgba(0, 0, 0, 0.1);
            border-color: #1f5588;
        }

        .svws-nav-card-icon {
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            border-radius: 10px;
            background: #e9f2fb;
            border: 1px solid #b9d2eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1f5f95;
        }

        .svws-nav-card-icon i {
            font-size: 24px;
            line-height: 1;
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
            color: #1a3a5c;
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
            border-bottom: 1px solid #d4d4d4;
            padding: 5px 4px;
        }

        .svws-list a {
            color: #114777;
            text-decoration: none;
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
            <button class="svws-help-btn" type="button">Hilfe</button>
        </header>
        <main class="svws-main">
