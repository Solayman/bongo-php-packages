<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= function_exists('csrf_token') ? htmlspecialchars((string) csrf_token(), ENT_QUOTES, 'UTF-8') : '' ?>">
    <title>File Manager & Code Editor - Bongo</title>
    <!-- CodeMirror 5 for Zero-Dependency Professional Code Editing -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.css">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --nav-bg: #2b3d51;
            --nav-hover: #22313f;
            --nav-text: #ffffff;
            --sidebar-bg: #f8fafc;
            --sidebar-border: #e2e8f0;
            --main-bg: #ffffff;
            --subnav-bg: #edf2f7;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --card-border: #e2e8f0;
            --card-hover-border: #cbd5e1;
            --selected-border: #16a085; /* UniSharp turquoise highlight */
            --accent-blue: #38bdf8;
            --btn-primary: #34495e;
            --btn-primary-hover: #2c3e50;
            --danger: #ef4444;
            --success: #10b981;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background: var(--main-bg); color: var(--text-dark); display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

        /* Top Header Navbar (UniSharp Dark Slate) */
        .navbar {
            height: 52px;
            background: var(--nav-bg);
            color: var(--nav-text);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
            z-index: 20;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            text-decoration: none;
            cursor: pointer;
        }
        .nav-brand i { font-size: 18px; color: var(--accent-blue); }
        .sidebar-toggle-btn {
            display: none;
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 18px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .sidebar-toggle-btn:hover { background: rgba(255,255,255,0.1); }

        .navbar-controls {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .view-btn {
            background: transparent;
            border: 1px solid transparent;
            color: rgba(255, 255, 255, 0.85);
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .view-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
        }
        .view-btn.active {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.18);
            font-weight: 500;
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Sort Dropdown */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 4px;
            background: #ffffff;
            min-width: 170px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--sidebar-border);
            border-radius: 6px;
            overflow: hidden;
            z-index: 50;
        }
        .dropdown.open .dropdown-menu { display: block; }
        .dropdown-item {
            padding: 8px 14px;
            font-size: 13px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: background 0.1s;
        }
        .dropdown-item:hover { background: #f1f5f9; color: #0f172a; }
        .dropdown-item.active { font-weight: 600; color: #0284c7; background: #e0f2fe; }

        /* Workspace Layout */
        .workspace {
            display: flex;
            flex: 1;
            overflow: hidden;
            position: relative;
        }

        /* Left Folder Tree Sidebar */
        .sidebar {
            width: 230px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            user-select: none;
            transition: transform 0.2s ease-in-out;
        }
        .sidebar-section-title {
            padding: 14px 16px 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
        }
        .folder-tree {
            list-style: none;
            padding: 4px 8px;
        }
        .folder-node {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
            transition: background 0.12s;
            margin-bottom: 2px;
        }
        .folder-node:hover {
            background: #edf2f7;
        }
        .folder-node.drop-target {
            background: #e0f2fe !important;
            color: #0369a1 !important;
            outline: 2px dashed #0284c7;
        }
        .folder-node.active {
            background: #dfe4ea;
            color: #1e293b;
            font-weight: 600;
            border-radius: 0;
            margin: 0 -8px 2px -8px;
            padding: 7px 18px;
        }
        .folder-node i.fa-folder, .folder-node i.fa-folder-open {
            color: #34495e;
            font-size: 15px;
        }
        .folder-children {
            list-style: none;
            padding-left: 18px;
            border-left: 1px dashed #cbd5e1;
            margin-left: 14px;
        }

        /* Main Content Container */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            overflow: hidden;
            position: relative;
        }

        /* Breadcrumbs Bar */
        .subnav {
            height: 42px;
            background: var(--subnav-bg);
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            font-size: 13px;
        }
        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            overflow-x: auto;
            white-space: nowrap;
        }
        .crumb-item {
            cursor: pointer;
            color: #334155;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .crumb-item:hover { color: #0284c7; text-decoration: underline; }
        .crumb-separator { color: #94a3b8; font-size: 11px; }

        .search-container {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 3px 10px;
            gap: 6px;
        }
        .search-container input {
            border: none;
            outline: none;
            font-size: 12px;
            color: var(--text-dark);
            width: 140px;
        }
        .search-container i { color: #94a3b8; font-size: 12px; }

        /* Items Container (Cards Grid / List) */
        .items-viewport {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            position: relative;
        }

        /* Thumbnails View (UniSharp Style) */
        .thumbnails-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(136px, 1fr));
            gap: 14px;
            align-content: start;
        }
        .file-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 4px;
            width: 100%;
            height: 156px;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s ease-in-out;
            position: relative;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .file-card:hover {
            border-color: var(--card-hover-border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }
        /* UniSharp Teal Selection Highlight */
        .file-card.selected {
            border: 2.5px solid var(--selected-border) !important;
            box-shadow: 0 0 0 1px var(--selected-border) !important;
        }
        .file-card.drop-target, .list-row.drop-target {
            background: #e0f2fe !important;
            border: 2px dashed #0284c7 !important;
            outline: 2px dashed #0284c7;
        }
        .file-card.is-dragging, .list-row.is-dragging {
            opacity: 0.45;
        }

        .card-thumb {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            overflow: hidden;
            padding: 6px;
            position: relative;
        }
        .folder-svg-icon {
            width: 72px;
            height: 60px;
            filter: drop-shadow(0 2px 4px rgba(56, 189, 248, 0.2));
        }
        .thumb-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 2px;
            image-rendering: -webkit-optimize-contrast;
        }
        .file-badge-icon {
            font-size: 46px;
        }
        .card-footer {
            height: 36px;
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
            padding: 0 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-name {
            font-size: 12px;
            font-weight: 500;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            text-align: center;
        }

        /* List Table View */
        .list-table-container {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .list-table-container th {
            text-align: left;
            padding: 10px 14px;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .list-table-container td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-dark);
            vertical-align: middle;
        }
        .list-table-container tr {
            cursor: pointer;
            transition: background 0.1s;
        }
        .list-table-container tr:hover { background: #f8fafc; }
        .list-table-container tr.selected {
            background: #e6fffa;
            border-left: 3px solid var(--selected-border);
        }
        .item-action-btns {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .action-icon-btn {
            background: transparent;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.12s;
        }
        .action-icon-btn:hover { background: #e2e8f0; color: #0f172a; }
        .action-icon-btn.danger:hover { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }

        /* Floating Action Button (FAB) Speed Dial */
        .fab-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            flex-direction: column-reverse;
            align-items: flex-end;
            gap: 12px;
            z-index: 100;
        }
        .fab-main {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--nav-bg);
            color: #ffffff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(43, 61, 81, 0.4);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), background 0.15s;
        }
        .fab-main:hover {
            background: var(--nav-hover);
            transform: scale(1.05);
        }
        .fab-container.open .fab-main {
            transform: rotate(45deg);
            background: #1e293b;
        }
        .fab-menu {
            display: none;
            flex-direction: column-reverse;
            align-items: flex-end;
            gap: 12px;
        }
        .fab-container.open .fab-menu {
            display: flex;
        }
        .fab-option {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            cursor: pointer;
        }
        .fab-badge {
            background: #718096;
            color: #ffffff;
            font-size: 11.5px;
            font-weight: 500;
            padding: 3px 8px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
            pointer-events: none;
        }
        .fab-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #34495e;
            color: #ffffff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.15s;
        }
        .fab-btn:hover {
            background: #2c3e50;
            transform: scale(1.08);
        }

        /* Bottom Selection Bar (Picker / Mobile Confirmation) */
        .picker-bottom-bar {
            height: 54px;
            background: #ffffff;
            border-top: 1px solid var(--sidebar-border);
            padding: 0 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 -2px 6px rgba(0,0,0,0.03);
            z-index: 15;
        }
        .selected-file-info {
            font-size: 13px;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .picker-action-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-confirm {
            background: #16a085;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s;
        }
        .btn-confirm:hover { background: #138d75; }
        .btn-preview {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 8px 14px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .btn-preview:hover { background: #e2e8f0; color: #0f172a; }
        .btn-action-sm {
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 7px 11px;
            border-radius: 4px;
            font-size: 12.5px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-action-sm:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }
        .btn-action-sm.danger { color: #dc2626; border-color: #fca5a5; }
        .btn-action-sm.danger:hover { background: #fee2e2; border-color: #ef4444; color: #b91c1c; }

        /* Drag & Drop Overlay */
        .drop-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.92);
            border: 3px dashed #0284c7;
            border-radius: 8px;
            z-index: 80;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            color: #0284c7;
            font-weight: 600;
            font-size: 18px;
            pointer-events: none;
        }
        .drop-overlay.active { display: flex; }

        /* Modal Dialogs */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }
        .modal.active { display: flex; }
        .modal-box {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: modalFadeIn 0.18s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header {
            padding: 12px 18px;
            background: #f8fafc;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-close-btn {
            background: transparent;
            border: none;
            font-size: 16px;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
        }
        .modal-close-btn:hover { color: #0f172a; }
        .modal-body { padding: 18px; overflow-y: auto; }
        .modal-footer {
            padding: 12px 18px;
            background: #f8fafc;
            border-top: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Forms */
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; color: #334155; margin-bottom: 6px; }
        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
            transition: border 0.15s;
        }
        .form-input:focus { border-color: #0284c7; box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15); }

        /* CodeMirror Editor Modal Specific */
        .editor-modal-box {
            width: 92%;
            max-width: 1100px;
            height: 88vh;
        }
        .editor-modal-body {
            flex: 1;
            padding: 0;
            position: relative;
            overflow: hidden;
        }
        .CodeMirror {
            height: 100% !important;
            font-family: 'Fira Code', 'Cascadia Code', Consolas, 'Courier New', monospace;
            font-size: 13.5px;
            line-height: 1.5;
        }

        /* Empty State */
        .empty-state {
            grid-column: 1 / -1;
            padding: 60px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 54px; color: #cbd5e1; margin-bottom: 14px; }
        .empty-state p { font-size: 14px; font-weight: 500; }

        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 24px;
            left: 24px;
            background: #1e293b;
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 13px;
            display: none;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
            z-index: 2000;
        }
        .toast.success { border-left: 4px solid var(--success); }
        .toast.error { border-left: 4px solid var(--danger); }

        /* Responsive Mobile Styles */
        @media (max-width: 680px) {
            .sidebar-toggle-btn { display: inline-flex; }
            .sidebar {
                position: absolute;
                top: 0;
                bottom: 0;
                left: 0;
                z-index: 40;
                transform: translateX(-100%);
                box-shadow: 2px 0 8px rgba(0,0,0,0.15);
            }
            .sidebar.drawer-open { transform: translateX(0); }
            .search-container input { width: 90px; }
            .thumbnails-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 12px !important;
            }
            .desktop-only-controls { display: none !important; }
            .mobile-gear-btn { display: inline-flex !important; }
        }
        @media (min-width: 681px) {
            .mobile-gear-btn { display: none !important; }
            .mobile-menu-drawer { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <header class="navbar">
        <div style="display: flex; align-items: center; gap: 8px;">
            <button class="sidebar-toggle-btn" onclick="app.toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
            <div class="nav-brand" onclick="app.navigateTo('')">
                <i class="fa-solid fa-folder-tree"></i>
                <span>Files</span>
            </div>
        </div>

        <div class="navbar-controls">
            <div class="desktop-only-controls" style="display: flex; align-items: center; gap: 6px;">
                <!-- View Mode Toggles (Thumbnails / List) -->
                <button class="view-btn active" id="btnThumbnails" onclick="app.setViewMode('thumbnails')">
                    <i class="fa-solid fa-table-cells-large"></i>
                    <span class="view-text">Thumbnails</span>
                </button>
                <button class="view-btn" id="btnList" onclick="app.setViewMode('list')">
                    <i class="fa-solid fa-list-ul"></i>
                    <span class="view-text">List</span>
                </button>

                <!-- Sort Dropdown -->
                <div class="dropdown" id="sortDropdown">
                    <button class="view-btn" onclick="app.toggleSortDropdown()">
                        <i class="fa-solid fa-arrow-down-short-wide"></i>
                        <span class="view-text">Sort</span>
                        <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left: 2px;"></i>
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-item active" onclick="app.setSort('name', 'asc')">
                            <span>Name (A-Z)</span> <i class="fa-solid fa-check sort-check"></i>
                        </div>
                        <div class="dropdown-item" onclick="app.setSort('name', 'desc')">
                            <span>Name (Z-A)</span>
                        </div>
                        <div class="dropdown-item" onclick="app.setSort('time', 'desc')">
                            <span>Date (Newest)</span>
                        </div>
                        <div class="dropdown-item" onclick="app.setSort('time', 'asc')">
                            <span>Date (Oldest)</span>
                        </div>
                        <div class="dropdown-item" onclick="app.setSort('size', 'desc')">
                            <span>Size (Largest)</span>
                        </div>
                        <div class="dropdown-item" onclick="app.setSort('type', 'asc')">
                            <span>File Type</span>
                        </div>
                    </div>
                </div>

                <!-- Refresh Button -->
                <button class="view-btn" onclick="app.refresh()" title="Refresh">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </button>
            </div>

            <!-- Mobile Gear Menu Button -->
            <button class="view-btn mobile-gear-btn" onclick="app.toggleMobileDrawer()" title="View & Sort Settings">
                <i class="fa-solid fa-gear"></i>
            </button>

            <?php if (!empty($callback)): ?>
            <button class="view-btn" onclick="window.close()" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <?php endif; ?>
        </div>
    </header>

    <!-- Mobile Drawer for View Modes & Sort -->
    <div class="mobile-menu-drawer" id="mobileMenuDrawer" style="display: none; background: #242f3e; padding: 10px 16px; border-bottom: 1px solid rgba(255,255,255,0.15); z-index: 19;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button class="view-btn active" id="btnMobileThumbnails" onclick="app.setViewMode('thumbnails')">
                <i class="fa-solid fa-table-cells-large"></i> Thumbnails
            </button>
            <button class="view-btn" id="btnMobileList" onclick="app.setViewMode('list')">
                <i class="fa-solid fa-list-ul"></i> List
            </button>
            <button class="view-btn" onclick="app.toggleSortDropdown()">
                <i class="fa-solid fa-arrow-down-short-wide"></i> Sort ▾
            </button>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="workspace" id="workspaceContainer">
        <!-- Drag & Drop Overlay -->
        <div class="drop-overlay" id="dropOverlay">
            <i class="fa-solid fa-cloud-arrow-up fa-3x"></i>
            <span>Drop files here to upload</span>
        </div>

        <!-- Left Folder Tree Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-section-title">Directories</div>
            <ul class="folder-tree" id="rootTree">
                <li>
                    <div class="folder-node active" id="node-root" onclick="app.navigateTo('')"
                         ondragover="if(event.dataTransfer && event.dataTransfer.types.includes('application/x-bongo-item')) { event.preventDefault(); event.stopPropagation(); this.classList.add('drop-target'); }"
                         ondragleave="this.classList.remove('drop-target');"
                         ondrop="if(event.dataTransfer && event.dataTransfer.types.includes('application/x-bongo-item')) { event.preventDefault(); event.stopPropagation(); this.classList.remove('drop-target'); const src = event.dataTransfer.getData('application/x-bongo-item'); app.moveItemToFolder(src, ''); }">
                        <i class="fa-solid fa-folder-open"></i>
                        <span>Files</span>
                    </div>
                    <ul class="folder-children" id="treeSubfolders">
                        <!-- Dynamic subfolders loaded from backend -->
                    </ul>
                </li>
            </ul>

            <div class="sidebar-section-title" style="margin-top: 14px;">Storage</div>
            <ul class="folder-tree">
                <li>
                    <div class="folder-node" onclick="app.navigateTo('')">
                        <i class="fa-solid fa-hard-drive" style="color: #64748b;"></i>
                        <span>Local Disk</span>
                    </div>
                </li>
            </ul>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Subnav with Breadcrumbs & Search -->
            <div class="subnav">
                <div class="breadcrumbs" id="breadcrumbTrail">
                    <span class="crumb-item" onclick="app.navigateTo('')">
                        <i class="fa-solid fa-house" style="font-size: 11px;"></i> Files
                    </span>
                </div>
                <div class="search-container">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Search files..." oninput="app.filterItems(this.value)">
                </div>
            </div>

            <!-- Items Viewport -->
            <div class="items-viewport" id="itemsViewport">
                <!-- Dynamically populated files and folders -->
                <div class="thumbnails-grid" id="thumbnailsContainer"></div>
                <div id="listContainer" style="display: none;"></div>
            </div>

            <!-- Bottom Selection Action Bar (Picker mode or item selected) -->
            <footer class="picker-bottom-bar" id="pickerBottomBar">
                <div class="selected-file-info" id="selectedFileInfo">
                    <i class="fa-regular fa-hand-pointer" style="color: #94a3b8;"></i>
                    <span>No file selected</span>
                </div>
                <div class="picker-action-group">
                    <a class="btn-action-sm" id="btnBottomDownload" href="#" title="Download" style="display: none;">
                        <i class="fa-solid fa-download"></i> <span class="btn-label-text">Download</span>
                    </a>
                    <button class="btn-action-sm" id="btnBottomCopy" onclick="app.copySelected()" title="Copy to folder" style="display: none;">
                        <i class="fa-regular fa-copy"></i> <span class="btn-label-text">Copy</span>
                    </button>
                    <button class="btn-action-sm" id="btnBottomMove" onclick="app.moveSelected()" title="Move to folder" style="display: none;">
                        <i class="fa-solid fa-arrows-up-down-left-right"></i> <span class="btn-label-text">Move</span>
                    </button>
                    <button class="btn-action-sm" id="btnBottomRename" onclick="app.renameSelected()" title="Rename" style="display: none;">
                        <i class="fa-solid fa-pen"></i> <span class="btn-label-text">Rename</span>
                    </button>
                    <button class="btn-action-sm danger" id="btnBottomDelete" onclick="app.deleteSelected()" title="Delete" style="display: none;">
                        <i class="fa-solid fa-trash-can"></i> <span class="btn-label-text">Delete</span>
                    </button>
                    <button class="btn-preview" id="btnBottomPreview" onclick="app.previewSelected()" style="display: none;">
                        <i class="fa-regular fa-eye"></i> Preview
                    </button>
                    <?php if (!empty($callback)): ?>
                    <button class="btn-confirm" id="btnBottomConfirm" onclick="app.confirmSelection()" style="display: none;">
                        <i class="fa-solid fa-check"></i> Confirm
                    </button>
                    <?php endif; ?>
                </div>
            </footer>
        </main>
    </div>

    <!-- Floating Action Button (FAB) Speed Dial -->
    <div class="fab-container" id="fabContainer">
        <button class="fab-main" id="fabMainBtn" onclick="app.toggleFab()" title="Actions">
            <i class="fa-solid fa-plus"></i>
        </button>
        <div class="fab-menu">
            <!-- Upload Speed Dial Button -->
            <label class="fab-option">
                <span class="fab-badge">Upload</span>
                <span class="fab-btn"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                <input type="file" id="fileUploadInput" multiple style="display: none;" onchange="app.handleUpload(this)">
            </label>
            <!-- New Folder Speed Dial Button -->
            <div class="fab-option" onclick="app.openNewFolderModal()">
                <span class="fab-badge">New Folder</span>
                <button class="fab-btn"><i class="fa-solid fa-folder-plus"></i></button>
            </div>
        </div>
    </div>

    <!-- New Folder Modal -->
    <div class="modal" id="folderModal">
        <div class="modal-box" style="width: 380px;">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-folder-plus" style="color: var(--accent-blue);"></i> New Folder</div>
                <button class="modal-close-btn" onclick="app.closeModal('folderModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Folder Name</label>
                    <input type="text" class="form-input" id="newFolderNameInput" placeholder="e.g. photos, documents" onkeydown="if(event.key==='Enter') app.submitNewFolder()">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-preview" onclick="app.closeModal('folderModal')">Cancel</button>
                <button class="btn-confirm" onclick="app.submitNewFolder()">Create</button>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div class="modal" id="renameModal">
        <div class="modal-box" style="width: 380px;">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-pen" style="color: #6366f1;"></i> Rename Item</div>
                <button class="modal-close-btn" onclick="app.closeModal('renameModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">New Name</label>
                    <input type="text" class="form-input" id="renameItemInput" onkeydown="if(event.key==='Enter') app.submitRename()">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-preview" onclick="app.closeModal('renameModal')">Cancel</button>
                <button class="btn-confirm" onclick="app.submitRename()">Rename</button>
            </div>
        </div>
    </div>

    <!-- Move Modal -->
    <div class="modal" id="moveModal">
        <div class="modal-box" style="width: 400px;">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-arrows-up-down-left-right" style="color: #f59e0b;"></i> Move Item</div>
                <button class="modal-close-btn" onclick="app.closeModal('moveModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Destination Folder</label>
                    <select class="form-input" id="moveFolderSelect"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-preview" onclick="app.closeModal('moveModal')">Cancel</button>
                <button class="btn-confirm" onclick="app.submitMove()">Move</button>
            </div>
        </div>
    </div>

    <!-- Copy Modal -->
    <div class="modal" id="copyModal">
        <div class="modal-box" style="width: 400px;">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-regular fa-copy" style="color: #0284c7;"></i> Copy Item</div>
                <button class="modal-close-btn" onclick="app.closeModal('copyModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label">Destination Folder</label>
                    <select class="form-input" id="copyFolderSelect"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">New Name (Optional)</label>
                    <input type="text" class="form-input" id="copyNewNameInput" placeholder="Leave blank to keep same name" onkeydown="if(event.key==='Enter') app.submitCopy()">
                    <span style="font-size: 11.5px; color: var(--text-muted); display: block; margin-top: 4px;">Leave blank to keep original name (auto-appends _copy if exists).</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-preview" onclick="app.closeModal('copyModal')">Cancel</button>
                <button class="btn-confirm" onclick="app.submitCopy()">Copy</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-box" style="width: 380px;">
            <div class="modal-header">
                <div class="modal-title" style="color: var(--danger);"><i class="fa-solid fa-trash-can"></i> Delete Item</div>
                <button class="modal-close-btn" onclick="app.closeModal('deleteModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <p style="font-size: 14px; color: #475569;" id="deleteItemPromptText">Are you sure you want to delete this item? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="btn-preview" onclick="app.closeModal('deleteModal')">Cancel</button>
                <button class="btn-confirm" style="background: var(--danger);" onclick="app.submitDelete()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Professional Code Editor Modal -->
    <div class="modal" id="editorModal">
        <div class="modal-box editor-modal-box">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fa-solid fa-code" style="color: #3b82f6;"></i>
                    <span id="editorFileName">file.php</span>
                    <span id="editorLanguageBadge" style="font-size: 11px; background: #e0f2fe; color: #0284c7; padding: 2px 6px; border-radius: 4px; font-weight: 600;">PHP</span>
                    <span id="editorStatusText" style="font-size: 12px; color: #94a3b8; font-weight: 400; margin-left: 8px;"></span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button class="btn-preview" onclick="app.toggleEditorTheme()" title="Switch Light/Dark Theme">
                        <i class="fa-solid fa-circle-half-stroke"></i> Theme
                    </button>
                    <button class="btn-confirm" onclick="app.saveCodeFile()" title="Save (Ctrl+S / Cmd+S)">
                        <i class="fa-solid fa-floppy-disk"></i> Save (Ctrl+S)
                    </button>
                    <button class="modal-close-btn" onclick="app.closeEditor()"><i class="fa-solid fa-xmark fa-lg"></i></button>
                </div>
            </div>
            <div class="modal-body editor-modal-body">
                <textarea id="codeTextarea"></textarea>
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <span style="font-size: 12px; color: var(--text-muted);" id="editorLinesCount">0 lines</span>
                <span style="font-size: 12px; color: var(--text-muted);">Bongo Zero-Dependency Code Editor</span>
            </div>
        </div>
    </div>

    <!-- High Quality Image Preview / Lightbox Modal -->
    <div class="modal" id="imageLightboxModal">
        <div class="modal-box" style="width: 90%; max-width: 850px; max-height: 88vh;">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fa-regular fa-image" style="color: #10b981;"></i>
                    <span id="lightboxTitle">Image Preview</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a id="lightboxDownloadBtn" href="#" class="btn-preview" download title="Download"><i class="fa-solid fa-download"></i></a>
                    <button class="modal-close-btn" onclick="app.closeModal('imageLightboxModal')"><i class="fa-solid fa-xmark fa-lg"></i></button>
                </div>
            </div>
            <div class="modal-body" style="display: flex; align-items: center; justify-content: center; background: #0f172a; padding: 20px;">
                <img id="lightboxImage" src="" alt="Preview" style="max-width: 100%; max-height: 68vh; object-fit: contain; border-radius: 4px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <span style="font-size: 12px; color: var(--text-muted);" id="lightboxDetails">Dimensions & Size</span>
                <?php if (!empty($callback)): ?>
                <button class="btn-confirm" onclick="app.confirmSelection(); app.closeModal('imageLightboxModal');">
                    <i class="fa-solid fa-check"></i> Use This Image
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Toast Notification -->
    <div class="toast" id="toastNotification">
        <i class="fa-solid fa-circle-check" id="toastIcon"></i>
        <span id="toastMessage">Success</span>
    </div>

    <!-- CodeMirror Modes & Addons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/yaml/yaml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>

    <!-- Main Client Application -->
    <script>
        const app = {
            currentPath: '',
            currentViewMode: 'thumbnails', // 'thumbnails' or 'list'
            sortBy: 'name',
            sortOrder: 'asc',
            searchQuery: '',
            allItems: { folders: [], files: [] },
            selectedItem: null,
            targetActionItem: null,
            editor: null,
            editorFilePath: '',
            prefix: '<?= htmlspecialchars((string) ($prefix ?? 'filemanager'), ENT_QUOTES, 'UTF-8') ?>',
            callback: '<?= htmlspecialchars((string) ($callback ?? ''), ENT_QUOTES, 'UTF-8') ?>',
            type: '<?= htmlspecialchars((string) ($type ?? 'all'), ENT_QUOTES, 'UTF-8') ?>',
            csrfToken: '<?= function_exists('csrf_token') ? htmlspecialchars((string) csrf_token(), ENT_QUOTES, 'UTF-8') : '' ?>',

            getCsrfToken() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return meta && meta.content ? meta.content : this.csrfToken;
            },

            init() {
                this.initCodeEditor();
                this.setupDragAndDrop();
                this.loadTree();
                this.loadItems('');

                // Global click closes dropdowns & FAB
                document.addEventListener('click', (e) => {
                    const sortDropdown = document.getElementById('sortDropdown');
                    if (sortDropdown && !sortDropdown.contains(e.target)) {
                        sortDropdown.classList.remove('open');
                    }
                    const fab = document.getElementById('fabContainer');
                    if (fab && !fab.contains(e.target)) {
                        fab.classList.remove('open');
                    }
                });

                // Hotkey navigation
                window.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
                    }
                });
            },

            initCodeEditor() {
                const textarea = document.getElementById('codeTextarea');
                this.editor = CodeMirror.fromTextArea(textarea, {
                    lineNumbers: true,
                    matchBrackets: true,
                    autoCloseBrackets: true,
                    theme: 'dracula',
                    mode: 'php',
                    tabSize: 4,
                    indentWithTabs: false,
                    lineWrapping: true
                });

                this.editor.addKeyMap({
                    'Ctrl-S': () => this.saveCodeFile(),
                    'Cmd-S': () => this.saveCodeFile()
                });

                this.editor.on('change', () => {
                    document.getElementById('editorLinesCount').textContent = this.editor.lineCount() + ' lines';
                });
            },

            setupDragAndDrop() {
                const workspace = document.getElementById('workspaceContainer');
                const overlay = document.getElementById('dropOverlay');

                ['dragenter', 'dragover'].forEach(name => {
                    workspace.addEventListener(name, (e) => {
                        // If dragging an internal file/folder inside file manager, don't show upload overlay
                        if (e.dataTransfer && e.dataTransfer.types.includes('application/x-bongo-item')) {
                            return;
                        }
                        e.preventDefault();
                        overlay.classList.add('active');
                    });
                });

                ['dragleave', 'drop'].forEach(name => {
                    workspace.addEventListener(name, (e) => {
                        // Internal drop handled by target folder element
                        if (e.dataTransfer && e.dataTransfer.types.includes('application/x-bongo-item')) {
                            return;
                        }
                        e.preventDefault();
                        if (e.type === 'drop') {
                            overlay.classList.remove('active');
                            const dt = e.dataTransfer;
                            if (dt && dt.files && dt.files.length > 0) {
                                this.uploadFilesArray(dt.files);
                            }
                        } else if (!workspace.contains(e.relatedTarget)) {
                            overlay.classList.remove('active');
                        }
                    });
                });
            },

            async moveItemToFolder(sourcePath, targetFolder) {
                if (!sourcePath) return;
                if (sourcePath === targetFolder) return;

                // Check if target is a subfolder of source (cannot move folder inside itself)
                if (targetFolder && (targetFolder === sourcePath || targetFolder.startsWith(sourcePath + '/'))) {
                    this.showToast('Cannot move a folder inside itself.', 'error');
                    return;
                }

                // If already in that folder, do nothing
                const parentOfSource = sourcePath.includes('/') ? sourcePath.substring(0, sourcePath.lastIndexOf('/')) : '';
                if (parentOfSource === targetFolder) {
                    return;
                }

                const formData = new FormData();
                formData.append('path', sourcePath);
                formData.append('target', targetFolder);

                try {
                    const res = await fetch(`/${this.prefix}/move`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.getCsrfToken() },
                        body: formData
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.showToast('Item moved successfully.', 'success');
                        this.refresh();
                        this.loadTree();
                    } else {
                        this.showToast(data.message || 'Failed to move item.', 'error');
                    }
                } catch (err) {
                    this.showToast('Failed to move item: ' + err.message, 'error');
                }
            },

            async loadTree() {
                try {
                    const res = await fetch(`/${this.prefix}/tree`);
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.renderTree(data.tree, document.getElementById('treeSubfolders'));
                    }
                } catch (e) {
                    console.error('Tree load error:', e);
                }
            },

            renderTree(nodes, container) {
                container.innerHTML = '';
                nodes.forEach(node => {
                    const li = document.createElement('li');
                    const div = document.createElement('div');
                    div.className = 'folder-node' + (this.currentPath === node.path ? ' active' : '');
                    div.innerHTML = `<i class="fa-solid fa-folder"></i> <span>${node.name}</span>`;
                    div.onclick = (e) => {
                        e.stopPropagation();
                        this.navigateTo(node.path);
                    };

                    // Tree node as drop target
                    div.ondragover = (e) => {
                        if (e.dataTransfer && e.dataTransfer.types.includes('application/x-bongo-item')) {
                            e.preventDefault();
                            e.stopPropagation();
                            div.classList.add('drop-target');
                        }
                    };
                    div.ondragleave = () => {
                        div.classList.remove('drop-target');
                    };
                    div.ondrop = (e) => {
                        if (e.dataTransfer && e.dataTransfer.types.includes('application/x-bongo-item')) {
                            e.preventDefault();
                            e.stopPropagation();
                            div.classList.remove('drop-target');
                            const sourcePath = e.dataTransfer.getData('application/x-bongo-item');
                            this.moveItemToFolder(sourcePath, node.path);
                        }
                    };

                    li.appendChild(div);

                    if (node.children && node.children.length > 0) {
                        const childUl = document.createElement('ul');
                        childUl.className = 'folder-children';
                        this.renderTree(node.children, childUl);
                        li.appendChild(childUl);
                    }
                    container.appendChild(li);
                });
            },

            async loadItems(path) {
                this.currentPath = path;
                this.selectedItem = null;
                this.updateBreadcrumbs();
                this.updateBottomBar();
                this.highlightTreeActive(path);

                try {
                    const res = await fetch(`/${this.prefix}/items?path=${encodeURIComponent(path)}&type=${encodeURIComponent(this.type)}`);
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.allItems = data;
                        this.applyFilterAndSort();
                    }
                } catch (e) {
                    this.showToast('Failed to load directory items', 'error');
                }
            },

            highlightTreeActive(path) {
                document.querySelectorAll('.folder-node').forEach(n => n.classList.remove('active'));
                if (!path) {
                    const root = document.getElementById('node-root');
                    if (root) root.classList.add('active');
                } else {
                    document.querySelectorAll('.folder-node span').forEach(s => {
                        if (s.textContent === path.split('/').pop()) {
                            s.parentElement.classList.add('active');
                        }
                    });
                }
            },

            applyFilterAndSort() {
                let folders = [...this.allItems.folders];
                let files = [...this.allItems.files];

                // Search query filter
                if (this.searchQuery) {
                    const q = this.searchQuery.toLowerCase();
                    folders = folders.filter(f => f.name.toLowerCase().includes(q));
                    files = files.filter(f => f.name.toLowerCase().includes(q));
                }

                // Sorting
                const sortFn = (a, b) => {
                    let valA, valB;
                    if (this.sortBy === 'name') {
                        valA = a.name.toLowerCase();
                        valB = b.name.toLowerCase();
                    } else if (this.sortBy === 'time') {
                        valA = a.updated_at || '';
                        valB = b.updated_at || '';
                    } else if (this.sortBy === 'size') {
                        valA = a.size || 0;
                        valB = b.size || 0;
                    } else if (this.sortBy === 'type') {
                        valA = a.extension || (a.is_dir ? 'folder' : 'file');
                        valB = b.extension || (b.is_dir ? 'folder' : 'file');
                    }
                    if (valA < valB) return this.sortOrder === 'asc' ? -1 : 1;
                    if (valA > valB) return this.sortOrder === 'asc' ? 1 : -1;
                    return 0;
                };

                folders.sort(sortFn);
                files.sort(sortFn);

                if (this.currentViewMode === 'thumbnails') {
                    this.renderThumbnails(folders, files);
                } else {
                    this.renderList(folders, files);
                }
            },

            renderThumbnails(folders, files) {
                const container = document.getElementById('thumbnailsContainer');
                container.innerHTML = '';
                document.getElementById('listContainer').style.display = 'none';
                container.style.display = 'grid';

                if (folders.length === 0 && files.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            <p>This folder is empty.</p>
                        </div>
                    `;
                    return;
                }

                // SVG Folder Icon (UniSharp Style Sky-Blue)
                const folderSvg = `
                    <svg class="folder-svg-icon" viewBox="0 0 72 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0 6C0 2.68629 2.68629 0 6 0H24.5C26.5 0 28.5 1 29.5 2.5L34 8H66C69.3137 8 72 10.6863 72 14V54C72 57.3137 69.3137 60 66 60H6C2.68629 60 0 57.3137 0 54V6Z" fill="#38BDF8"/>
                        <path d="M0 18C0 14.6863 2.68629 12 6 12H66C69.3137 12 72 14.6863 72 18V54C72 57.3137 69.3137 60 66 60H6C2.68629 60 0 57.3137 0 54V18Z" fill="#0284C7" fill-opacity="0.15"/>
                        <path d="M0 22C0 18.6863 2.68629 16 6 16H66C69.3137 16 72 18.6863 72 22V54C72 57.3137 69.3137 60 66 60H6C2.68629 60 0 57.3137 0 54V22Z" fill="#38BDF8"/>
                    </svg>
                `;

                // Render Folders
                folders.forEach(f => {
                    const card = document.createElement('div');
                    card.className = 'file-card';
                    card.draggable = true;
                    card.innerHTML = `
                        <div class="card-thumb">${folderSvg}</div>
                        <div class="card-footer"><span class="card-name" title="${f.name}">${f.name}</span></div>
                    `;
                    card.onclick = () => this.selectItem(f, card);
                    card.ondblclick = () => this.navigateTo(f.path);

                    // Drag source
                    card.ondragstart = (e) => {
                        e.dataTransfer.setData('application/x-bongo-item', f.path);
                        e.dataTransfer.effectAllowed = 'move';
                        card.classList.add('is-dragging');
                    };
                    card.ondragend = () => {
                        card.classList.remove('is-dragging');
                    };

                    // Drop target (move item into this folder)
                    card.ondragover = (e) => {
                        if (e.dataTransfer && e.dataTransfer.types.includes('application/x-bongo-item')) {
                            e.preventDefault();
                            e.stopPropagation();
                            card.classList.add('drop-target');
                        }
                    };
                    card.ondragleave = () => {
                        card.classList.remove('drop-target');
                    };
                    card.ondrop = (e) => {
                        if (e.dataTransfer && e.dataTransfer.types.includes('application/x-bongo-item')) {
                            e.preventDefault();
                            e.stopPropagation();
                            card.classList.remove('drop-target');
                            const sourcePath = e.dataTransfer.getData('application/x-bongo-item');
                            this.moveItemToFolder(sourcePath, f.path);
                        }
                    };

                    container.appendChild(card);
                });

                // Render Files
                files.forEach(f => {
                    const card = document.createElement('div');
                    card.className = 'file-card';
                    card.draggable = true;
                    let previewContent = '';

                    if (f.type === 'image' && f.thumb_url) {
                        previewContent = `<img class="thumb-image" src="${f.thumb_url}" alt="${f.name}" loading="lazy">`;
                    } else if (f.type === 'code') {
                        previewContent = `<i class="fa-solid fa-file-code file-badge-icon" style="color: #6366f1;"></i>`;
                    } else if (f.type === 'pdf') {
                        previewContent = `<i class="fa-solid fa-file-pdf file-badge-icon" style="color: #ef4444;"></i>`;
                    } else if (f.type === 'archive') {
                        previewContent = `<i class="fa-solid fa-file-zipper file-badge-icon" style="color: #8b5cf6;"></i>`;
                    } else {
                        previewContent = `<i class="fa-solid fa-file-lines file-badge-icon" style="color: #94a3b8;"></i>`;
                    }

                    card.innerHTML = `
                        <div class="card-thumb">${previewContent}</div>
                        <div class="card-footer"><span class="card-name" title="${f.name}">${f.name}</span></div>
                    `;
                    card.onclick = () => this.selectItem(f, card);
                    card.ondblclick = () => this.openItem(f);

                    // Drag source
                    card.ondragstart = (e) => {
                        e.dataTransfer.setData('application/x-bongo-item', f.path);
                        e.dataTransfer.effectAllowed = 'move';
                        card.classList.add('is-dragging');
                    };
                    card.ondragend = () => {
                        card.classList.remove('is-dragging');
                    };

                    container.appendChild(card);
                });
            },

            renderList(folders, files) {
                const container = document.getElementById('listContainer');
                document.getElementById('thumbnailsContainer').style.display = 'none';
                container.style.display = 'block';

                if (folders.length === 0 && files.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            <p>This folder is empty.</p>
                        </div>
                    `;
                    return;
                }

                let html = `
                    <table class="list-table-container">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th style="width: 120px;">Size</th>
                                <th style="width: 120px;">Type</th>
                                <th style="width: 160px;">Modified</th>
                                <th style="width: 160px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                folders.forEach(f => {
                    html += `
                        <tr class="list-row" draggable="true"
                            ondragstart="event.dataTransfer.setData('application/x-bongo-item', '${f.path}'); event.dataTransfer.effectAllowed = 'move'; this.classList.add('is-dragging');"
                            ondragend="this.classList.remove('is-dragging');"
                            ondragover="if(event.dataTransfer && event.dataTransfer.types.includes('application/x-bongo-item')) { event.preventDefault(); event.stopPropagation(); this.classList.add('drop-target'); }"
                            ondragleave="this.classList.remove('drop-target');"
                            ondrop="if(event.dataTransfer && event.dataTransfer.types.includes('application/x-bongo-item')) { event.preventDefault(); event.stopPropagation(); this.classList.remove('drop-target'); const src = event.dataTransfer.getData('application/x-bongo-item'); app.moveItemToFolder(src, '${f.path}'); }"
                            onclick="app.selectItem(${JSON.stringify(f).replace(/"/g, '&quot;')}, this)" ondblclick="app.navigateTo('${f.path}')">
                            <td><i class="fa-solid fa-folder" style="color: #38bdf8; margin-right: 8px;"></i> <strong>${f.name}</strong></td>
                            <td>—</td>
                            <td>Folder</td>
                            <td>${f.updated_at || '—'}</td>
                            <td style="text-align: right;">
                                <div class="item-action-btns" style="justify-content: flex-end;">
                                    <button class="action-icon-btn" onclick="event.stopPropagation(); app.promptCopy('${f.path}')" title="Copy"><i class="fa-regular fa-copy"></i></button>
                                    <button class="action-icon-btn" onclick="event.stopPropagation(); app.promptRename('${f.path}', '${f.name}')" title="Rename"><i class="fa-solid fa-pen"></i></button>
                                    <button class="action-icon-btn" onclick="event.stopPropagation(); app.promptMove('${f.path}')" title="Move"><i class="fa-solid fa-arrows-up-down-left-right"></i></button>
                                    <button class="action-icon-btn danger" onclick="event.stopPropagation(); app.promptDelete('${f.path}', '${f.name}')" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    `;
                });

                files.forEach(f => {
                    let icon = '<i class="fa-regular fa-file" style="color: #94a3b8; margin-right: 8px;"></i>';
                    if (f.type === 'image') icon = '<i class="fa-regular fa-image" style="color: #10b981; margin-right: 8px;"></i>';
                    else if (f.type === 'code') icon = '<i class="fa-solid fa-file-code" style="color: #6366f1; margin-right: 8px;"></i>';
                    else if (f.type === 'pdf') icon = '<i class="fa-solid fa-file-pdf" style="color: #ef4444; margin-right: 8px;"></i>';
                    else if (f.type === 'archive') icon = '<i class="fa-solid fa-file-zipper" style="color: #8b5cf6; margin-right: 8px;"></i>';

                    html += `
                        <tr class="list-row" draggable="true"
                            ondragstart="event.dataTransfer.setData('application/x-bongo-item', '${f.path}'); event.dataTransfer.effectAllowed = 'move'; this.classList.add('is-dragging');"
                            ondragend="this.classList.remove('is-dragging');"
                            onclick="app.selectItem(${JSON.stringify(f).replace(/"/g, '&quot;')}, this)" ondblclick="app.openItem(${JSON.stringify(f).replace(/"/g, '&quot;')})">
                            <td>${icon} <span>${f.name}</span></td>
                            <td>${f.size_human}</td>
                            <td>${(f.extension || f.type).toUpperCase()}</td>
                            <td>${f.updated_at || '—'}</td>
                            <td style="text-align: right;">
                                <div class="item-action-btns" style="justify-content: flex-end;">
                                    <a class="action-icon-btn" href="/${this.prefix}/download?path=${encodeURIComponent(f.path)}" title="Download" onclick="event.stopPropagation();"><i class="fa-solid fa-download"></i></a>
                                    ${f.type === 'code' ? `<button class="action-icon-btn" onclick="event.stopPropagation(); app.openCodeEditor(${JSON.stringify(f).replace(/"/g, '&quot;')})" title="Edit Code"><i class="fa-solid fa-code"></i></button>` : ''}
                                    <button class="action-icon-btn" onclick="event.stopPropagation(); app.promptCopy('${f.path}')" title="Copy"><i class="fa-regular fa-copy"></i></button>
                                    <button class="action-icon-btn" onclick="event.stopPropagation(); app.promptRename('${f.path}', '${f.name}')" title="Rename"><i class="fa-solid fa-pen"></i></button>
                                    <button class="action-icon-btn" onclick="event.stopPropagation(); app.promptMove('${f.path}')" title="Move"><i class="fa-solid fa-arrows-up-down-left-right"></i></button>
                                    <button class="action-icon-btn danger" onclick="event.stopPropagation(); app.promptDelete('${f.path}', '${f.name}')" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    `;
                });

                html += `</tbody></table>`;
                container.innerHTML = html;
            },

            selectItem(item, element) {
                document.querySelectorAll('.file-card, .list-row').forEach(c => c.classList.remove('selected'));
                element.classList.add('selected');
                this.selectedItem = item;
                this.updateBottomBar();
            },

            updateBottomBar() {
                const info = document.getElementById('selectedFileInfo');
                const btnPrev = document.getElementById('btnBottomPreview');
                const btnConf = document.getElementById('btnBottomConfirm');
                const btnDl = document.getElementById('btnBottomDownload');
                const btnCopy = document.getElementById('btnBottomCopy');
                const btnMove = document.getElementById('btnBottomMove');
                const btnRename = document.getElementById('btnBottomRename');
                const btnDelete = document.getElementById('btnBottomDelete');

                if (this.selectedItem) {
                    info.innerHTML = `
                        <i class="fa-solid fa-check-circle" style="color: var(--selected-border);"></i>
                        <span><strong>${this.selectedItem.name}</strong> (${this.selectedItem.is_dir ? 'Folder' : this.selectedItem.size_human})</span>
                    `;
                    if (!this.selectedItem.is_dir && (this.selectedItem.type === 'image' || this.selectedItem.type === 'code')) {
                        btnPrev.style.display = 'inline-flex';
                    } else {
                        btnPrev.style.display = 'none';
                    }
                    if (btnConf) btnConf.style.display = 'inline-flex';

                    // Actions toolbar
                    if (btnCopy) btnCopy.style.display = 'inline-flex';
                    if (btnMove) btnMove.style.display = 'inline-flex';
                    if (btnRename) btnRename.style.display = 'inline-flex';
                    if (btnDelete) btnDelete.style.display = 'inline-flex';

                    if (btnDl) {
                        if (!this.selectedItem.is_dir) {
                            btnDl.href = `/${this.prefix}/download?path=${encodeURIComponent(this.selectedItem.path)}`;
                            btnDl.style.display = 'inline-flex';
                        } else {
                            btnDl.style.display = 'none';
                        }
                    }
                } else {
                    info.innerHTML = `<i class="fa-regular fa-hand-pointer" style="color: #94a3b8;"></i> <span>No file selected</span>`;
                    btnPrev.style.display = 'none';
                    if (btnConf) btnConf.style.display = 'none';
                    if (btnCopy) btnCopy.style.display = 'none';
                    if (btnMove) btnMove.style.display = 'none';
                    if (btnRename) btnRename.style.display = 'none';
                    if (btnDelete) btnDelete.style.display = 'none';
                    if (btnDl) btnDl.style.display = 'none';
                }
            },

            copySelected() {
                if (!this.selectedItem) return;
                this.promptCopy(this.selectedItem.path);
            },

            moveSelected() {
                if (!this.selectedItem) return;
                this.promptMove(this.selectedItem.path);
            },

            renameSelected() {
                if (!this.selectedItem) return;
                this.promptRename(this.selectedItem.path, this.selectedItem.name);
            },

            deleteSelected() {
                if (!this.selectedItem) return;
                this.promptDelete(this.selectedItem.path, this.selectedItem.name);
            },

            openItem(item) {
                if (item.is_dir) {
                    this.navigateTo(item.path);
                } else if (this.callback) {
                    this.confirmSelection();
                } else if (item.type === 'image') {
                    this.openImageLightbox(item);
                } else if (item.type === 'code') {
                    this.openCodeEditor(item);
                }
            },

            previewSelected() {
                if (!this.selectedItem) return;
                this.openItem(this.selectedItem);
            },

            openImageLightbox(item) {
                document.getElementById('lightboxTitle').textContent = item.name;
                const img = document.getElementById('lightboxImage');
                img.src = item.url;
                img.onload = () => {
                    document.getElementById('lightboxDetails').textContent = `${img.naturalWidth} × ${img.naturalHeight} px — ${item.size_human}`;
                };
                document.getElementById('lightboxDownloadBtn').href = `/${this.prefix}/download?path=${encodeURIComponent(item.path)}`;
                this.openModal('imageLightboxModal');
            },

            async openCodeEditor(item) {
                this.editorFilePath = item.path;
                document.getElementById('editorFileName').textContent = item.name;
                document.getElementById('editorLanguageBadge').textContent = (item.editor_language || 'plaintext').toUpperCase();
                document.getElementById('editorStatusText').textContent = 'Loading...';
                this.openModal('editorModal');

                try {
                    const res = await fetch(`/${this.prefix}/read?path=${encodeURIComponent(item.path)}`);
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.editor.setValue(data.content);
                        const modeMap = {
                            php: 'php',
                            javascript: 'javascript',
                            json: 'javascript',
                            css: 'css',
                            html: 'htmlmixed',
                            sql: 'sql',
                            markdown: 'markdown',
                            yaml: 'yaml',
                            xml: 'xml'
                        };
                        this.editor.setOption('mode', modeMap[data.language] || 'plaintext');
                        this.editor.refresh();
                        document.getElementById('editorStatusText').textContent = 'Ready';
                        document.getElementById('editorLinesCount').textContent = this.editor.lineCount() + ' lines';
                    } else {
                        this.showToast(data.message, 'error');
                        this.closeEditor();
                    }
                } catch (e) {
                    this.showToast('Failed to load file content', 'error');
                    this.closeEditor();
                }
            },

            async saveCodeFile() {
                const status = document.getElementById('editorStatusText');
                status.textContent = 'Saving...';
                const content = this.editor.getValue();

                const formData = new FormData();
                formData.append('path', this.editorFilePath);
                formData.append('content', content);

                try {
                    const res = await fetch(`/${this.prefix}/save`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.getCsrfToken() },
                        body: formData
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        status.textContent = 'Saved at ' + new Date().toLocaleTimeString();
                        this.showToast('File saved successfully', 'success');
                    } else {
                        status.textContent = 'Save failed!';
                        this.showToast(data.message || 'Error saving file', 'error');
                    }
                } catch (e) {
                    status.textContent = 'Save error!';
                    this.showToast('Failed to save file', 'error');
                }
            },

            closeEditor() {
                this.closeModal('editorModal');
            },

            toggleEditorTheme() {
                const current = this.editor.getOption('theme');
                this.editor.setOption('theme', current === 'dracula' ? 'eclipse' : 'dracula');
            },

            setViewMode(mode) {
                this.currentViewMode = mode;
                document.getElementById('btnThumbnails').classList.toggle('active', mode === 'thumbnails');
                document.getElementById('btnList').classList.toggle('active', mode === 'list');
                this.applyFilterAndSort();
            },

            toggleSortDropdown() {
                document.getElementById('sortDropdown').classList.toggle('open');
            },

            setSort(field, order) {
                this.sortBy = field;
                this.sortOrder = order;
                document.getElementById('sortDropdown').classList.remove('open');
                this.applyFilterAndSort();
            },

            toggleFab() {
                document.getElementById('fabContainer').classList.toggle('open');
            },

            toggleSidebar() {
                document.getElementById('sidebar').classList.toggle('drawer-open');
            },

            filterItems(val) {
                this.searchQuery = val.trim();
                this.applyFilterAndSort();
            },

            navigateTo(path) {
                this.loadItems(path);
                const sidebar = document.getElementById('sidebar');
                if (sidebar.classList.contains('drawer-open')) {
                    sidebar.classList.remove('drawer-open');
                }
            },

            updateBreadcrumbs() {
                const bc = document.getElementById('breadcrumbTrail');
                bc.innerHTML = `
                    <span class="crumb-item" onclick="app.navigateTo('')">
                        <i class="fa-solid fa-house" style="font-size: 11px;"></i> Files
                    </span>
                `;

                if (!this.currentPath) return;
                const parts = this.currentPath.split('/');
                let accum = '';

                parts.forEach((p) => {
                    accum = accum ? `${accum}/${p}` : p;
                    const pathCopy = accum;
                    bc.innerHTML += `
                        <span class="crumb-separator">/</span>
                        <span class="crumb-item" onclick="app.navigateTo('${pathCopy}')">${p}</span>
                    `;
                });
            },

            // Modal Handlers
            openModal(id) {
                document.getElementById(id).classList.add('active');
            },

            closeModal(id) {
                document.getElementById(id).classList.remove('active');
            },

            openNewFolderModal() {
                document.getElementById('fabContainer').classList.remove('open');
                const input = document.getElementById('newFolderNameInput');
                input.value = '';
                this.openModal('folderModal');
                setTimeout(() => input.focus(), 100);
            },

            async submitNewFolder() {
                const input = document.getElementById('newFolderNameInput');
                const name = input.value.trim();
                if (!name) return;

                const formData = new FormData();
                formData.append('path', this.currentPath);
                formData.append('name', name);

                const res = await fetch(`/${this.prefix}/folder`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.getCsrfToken() },
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    this.closeModal('folderModal');
                    this.showToast('Folder created.', 'success');
                    this.refresh();
                    this.loadTree();
                } else {
                    this.showToast(data.message, 'error');
                }
            },

            promptRename(path, oldName) {
                this.targetActionItem = path;
                const input = document.getElementById('renameItemInput');
                input.value = oldName;
                this.openModal('renameModal');
                setTimeout(() => input.focus(), 100);
            },

            async submitRename() {
                const input = document.getElementById('renameItemInput');
                const newName = input.value.trim();
                if (!newName) return;

                const formData = new FormData();
                formData.append('path', this.targetActionItem);
                formData.append('new_name', newName);

                const res = await fetch(`/${this.prefix}/rename`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.getCsrfToken() },
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    this.closeModal('renameModal');
                    this.showToast('Item renamed.', 'success');
                    this.refresh();
                    this.loadTree();
                } else {
                    this.showToast(data.message, 'error');
                }
            },

            promptMove(path) {
                this.targetActionItem = path;
                const select = document.getElementById('moveFolderSelect');
                select.innerHTML = '<option value="">(Root)</option>';

                const populateOptions = (nodes, prefix = '') => {
                    nodes.forEach(n => {
                        select.innerHTML += `<option value="${n.path}">${prefix}/${n.name}</option>`;
                        if (n.children && n.children.length > 0) {
                            populateOptions(n.children, prefix + '/' + n.name);
                        }
                    });
                };

                fetch(`/${this.prefix}/tree`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            populateOptions(data.tree);
                            this.openModal('moveModal');
                        }
                    });
            },

            async submitMove() {
                const target = document.getElementById('moveFolderSelect').value;
                const formData = new FormData();
                formData.append('path', this.targetActionItem);
                formData.append('target', target);

                const res = await fetch(`/${this.prefix}/move`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.getCsrfToken() },
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    this.closeModal('moveModal');
                    this.showToast('Item moved.', 'success');
                    this.refresh();
                    this.loadTree();
                } else {
                    this.showToast(data.message, 'error');
                }
            },

            promptCopy(path) {
                this.targetActionItem = path;
                const select = document.getElementById('copyFolderSelect');
                select.innerHTML = '<option value="">(Root)</option>';

                const populateOptions = (nodes, prefix = '') => {
                    nodes.forEach(n => {
                        select.innerHTML += `<option value="${n.path}">${prefix}/${n.name}</option>`;
                        if (n.children && n.children.length > 0) {
                            populateOptions(n.children, prefix + '/' + n.name);
                        }
                    });
                };

                fetch(`/${this.prefix}/tree`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            populateOptions(data.tree);
                            const nameInput = document.getElementById('copyNewNameInput');
                            if (nameInput) {
                                const currentName = path.split('/').pop() || '';
                                nameInput.value = currentName;
                            }
                            this.openModal('copyModal');
                        }
                    });
            },

            async submitCopy() {
                const target = document.getElementById('copyFolderSelect').value;
                const newName = (document.getElementById('copyNewNameInput').value || '').trim();
                const formData = new FormData();
                formData.append('path', this.targetActionItem);
                formData.append('target', target);
                if (newName) {
                    formData.append('new_name', newName);
                }

                const res = await fetch(`/${this.prefix}/copy`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.getCsrfToken() },
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    this.closeModal('copyModal');
                    this.showToast('Item copied.', 'success');
                    this.refresh();
                    this.loadTree();
                } else {
                    this.showToast(data.message, 'error');
                }
            },

            promptDelete(path, name) {
                this.targetActionItem = path;
                document.getElementById('deleteItemPromptText').textContent = `Are you sure you want to delete "${name}"? This cannot be undone.`;
                this.openModal('deleteModal');
            },

            async submitDelete() {
                const formData = new FormData();
                formData.append('path', this.targetActionItem);

                const res = await fetch(`/${this.prefix}/delete`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.getCsrfToken() },
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    this.closeModal('deleteModal');
                    this.showToast('Item deleted.', 'success');
                    this.refresh();
                    this.loadTree();
                } else {
                    this.showToast(data.message, 'error');
                }
            },

            async handleUpload(input) {
                if (!input.files || input.files.length === 0) return;
                await this.uploadFilesArray(input.files);
                input.value = '';
            },

            async uploadFilesArray(files) {
                const formData = new FormData();
                formData.append('path', this.currentPath);
                for (let i = 0; i < files.length; i++) {
                    formData.append('files[]', files[i]);
                }

                this.showToast('Uploading...', 'success');
                const res = await fetch(`/${this.prefix}/upload`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.getCsrfToken() },
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success' || data.status === 'partial') {
                    this.showToast(`Uploaded ${data.uploaded.length} file(s).`, 'success');
                    this.refresh();
                } else {
                    this.showToast('Upload failed: ' + (data.errors ? data.errors.join(', ') : data.message), 'error');
                }
            },

            refresh() {
                this.loadItems(this.currentPath);
            },

            confirmSelection() {
                if (!this.selectedItem || this.selectedItem.is_dir) {
                    this.showToast('Please select a file to confirm.', 'error');
                    return;
                }

                const item = this.selectedItem;

                // 1. TinyMCE 5, 6, and 7 iframe postMessage protocol (windowManager.openUrl)
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ mceAction: 'custom', content: item.url, item: item }, '*');
                    window.parent.postMessage({ mceAction: 'FileSelected', content: item.url, item: item }, '*');
                }
                if (window.opener && window.opener !== window) {
                    try {
                        window.opener.postMessage({ mceAction: 'custom', content: item.url, item: item }, '*');
                        window.opener.postMessage({ mceAction: 'FileSelected', content: item.url, item: item }, '*');
                    } catch (e) {}
                }

                // 2. CKEditor 4 support (?CKEditorFuncNum=...)
                const urlParams = new URLSearchParams(window.location.search);
                const funcNum = urlParams.get('CKEditorFuncNum');
                if (funcNum && window.opener && window.opener.CKEDITOR) {
                    window.opener.CKEDITOR.tools.callFunction(funcNum, item.url);
                    window.close();
                    return;
                }

                // 3. TinyMCE 4 windowManager
                if (window.parent && window.parent.tinymce && window.parent.tinymce.activeEditor) {
                    const wm = window.parent.tinymce.activeEditor.windowManager;
                    if (wm && typeof wm.getParams === 'function') {
                        const params = wm.getParams();
                        if (params && typeof params.oninsert === 'function') {
                            params.oninsert(item.url, item);
                            if (typeof wm.close === 'function') wm.close();
                            return;
                        }
                    }
                }

                // 4. Standard Bongo File Manager callback (window.opener or window.parent)
                if (window.opener && typeof window.opener.bongoFileManagerCallback === 'function') {
                    window.opener.bongoFileManagerCallback(item);
                    window.close();
                    return;
                }
                if (window.parent && typeof window.parent.bongoFileManagerCallback === 'function' && window.parent !== window) {
                    window.parent.bongoFileManagerCallback(item);
                    return;
                }

                this.showToast('Selected file: ' + item.url, 'success');
            },

            showToast(message, type = 'success') {
                const toast = document.getElementById('toastNotification');
                const text = document.getElementById('toastMessage');
                const icon = document.getElementById('toastIcon');

                text.textContent = message;
                toast.className = 'toast ' + type;
                icon.className = type === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-triangle-exclamation';

                toast.style.display = 'flex';
                clearTimeout(this.toastTimeout);
                this.toastTimeout = setTimeout(() => {
                    toast.style.display = 'none';
                }, 3500);
            }
        };

        window.onload = () => app.init();
    </script>
</body>
</html>
