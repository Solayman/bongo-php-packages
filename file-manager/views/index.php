<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager & Code Editor - Bongo</title>
    <!-- CodeMirror for Zero-Dependency Professional Code Editing -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-hover: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --border: #334155;
            --danger: #ef4444;
            --success: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: var(--bg-primary); color: var(--text-primary); display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

        /* Top Navbar */
        .navbar { height: 56px; background: var(--bg-secondary); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 16px; gap: 12px; }
        .nav-title { font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 8px; color: var(--text-primary); }
        .nav-title i { color: var(--accent); }
        .toolbar { display: flex; align-items: center; gap: 8px; }
        .btn { padding: 7px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid var(--border); background: var(--bg-secondary); color: var(--text-primary); display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s; }
        .btn:hover { background: var(--bg-hover); border-color: #475569; }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-danger { background: var(--danger); border-color: var(--danger); color: #fff; }

        /* Main Workspace */
        .workspace { display: flex; flex: 1; overflow: hidden; }

        /* Left Tree Sidebar */
        .sidebar { width: 240px; background: var(--bg-secondary); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow-y: auto; }
        .tree-header { padding: 12px 16px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
        .tree-list { list-style: none; padding: 8px; }
        .tree-item { padding: 6px 10px; border-radius: 4px; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: var(--text-secondary); }
        .tree-item:hover, .tree-item.active { background: var(--bg-hover); color: var(--text-primary); }
        .tree-item i { color: #f59e0b; }

        /* Content Area */
        .content { flex: 1; display: flex; flex-direction: column; background: var(--bg-primary); overflow: hidden; }

        /* Breadcrumbs & Search */
        .subnav { height: 44px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 16px; background: rgba(30, 41, 59, 0.4); }
        .breadcrumbs { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-secondary); overflow-x: auto; }
        .crumb { cursor: pointer; color: var(--accent); }
        .crumb:hover { text-decoration: underline; }
        .search-box { display: flex; align-items: center; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px; padding: 4px 10px; }
        .search-box input { background: transparent; border: none; outline: none; color: var(--text-primary); font-size: 13px; width: 160px; }

        /* File Grid & List Views */
        .items-container { flex: 1; padding: 16px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; align-content: start; }
        .items-container.list-view { display: flex; flex-direction: column; gap: 4px; }

        .file-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; align-items: center; text-align: center; cursor: pointer; user-select: none; transition: transform 0.1s, border-color 0.1s; position: relative; }
        .file-card:hover { border-color: var(--accent); background: var(--bg-hover); }
        .file-card.selected { border-color: var(--accent); background: rgba(59, 130, 246, 0.15); }
        .file-icon { width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 8px; border-radius: 6px; overflow: hidden; }
        .file-thumb { width: 100%; height: 100%; object-fit: cover; }
        .file-name { font-size: 12px; font-weight: 500; color: var(--text-primary); width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-size { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }

        /* Modal Overlays */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; width: 90%; max-width: 950px; height: 85vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .modal-header { padding: 12px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-body { flex: 1; overflow: hidden; position: relative; }
        .modal-footer { padding: 10px 16px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }

        /* CodeMirror Theme Customization */
        .CodeMirror { height: 100% !important; font-family: 'Fira Code', Consolas, 'Courier New', monospace; font-size: 14px; }

        /* Empty State */
        .empty-state { grid-column: 1 / -1; padding: 48px; text-align: center; color: var(--text-secondary); }
        .empty-state i { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }
    </style>
</head>
<body>

    <!-- Header Toolbar -->
    <div class="navbar">
        <div class="nav-title">
            <i class="fa-solid fa-folder-tree"></i>
            <span>Bongo File Manager</span>
        </div>
        <div class="toolbar">
            <button class="btn" onclick="app.navigateUp()"><i class="fa-solid fa-arrow-up"></i> Up</button>
            <button class="btn" onclick="app.promptNewFolder()"><i class="fa-solid fa-folder-plus"></i> New Folder</button>
            <label class="btn btn-primary">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload
                <input type="file" id="fileInput" multiple style="display: none;" onchange="app.handleUpload(this)">
            </label>
            <button class="btn" onclick="app.refresh()"><i class="fa-solid fa-arrows-rotate"></i></button>
            <button class="btn" id="selectBtn" style="display: none;" onclick="app.confirmSelection()"><i class="fa-solid fa-check"></i> Select</button>
        </div>
    </div>

    <div class="workspace">
        <!-- Sidebar Folder Tree -->
        <div class="sidebar">
            <div class="tree-header">Directories</div>
            <ul class="tree-list" id="treeList">
                <li class="tree-item active" onclick="app.navigateTo('')">
                    <i class="fa-solid fa-folder-open"></i> Root
                </li>
            </ul>
        </div>

        <!-- Main Items View -->
        <div class="content">
            <div class="subnav">
                <div class="breadcrumbs" id="breadcrumbs">
                    <span class="crumb" onclick="app.navigateTo('')">uploads</span>
                </div>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass" style="font-size: 12px; color: var(--text-secondary); margin-right: 6px;"></i>
                    <input type="text" placeholder="Search..." oninput="app.filterItems(this.value)">
                </div>
            </div>

            <div class="items-container" id="itemsContainer">
                <!-- Dynamically loaded file/folder items -->
            </div>
        </div>
    </div>

    <!-- Code Editor Modal -->
    <div class="modal" id="editorModal">
        <div class="modal-content">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-file-code" style="color: var(--accent);"></i>
                    <span id="editorFileName" style="font-weight: 600;">file.php</span>
                    <span id="editorStatus" style="font-size: 11px; color: var(--text-secondary); margin-left: 8px;"></span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn" onclick="app.toggleEditorTheme()"><i class="fa-solid fa-circle-half-stroke"></i> Theme</button>
                    <button class="btn btn-primary" onclick="app.saveCodeFile()"><i class="fa-solid fa-floppy-disk"></i> Save (Ctrl+S)</button>
                    <button class="btn" onclick="app.closeEditor()"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="modal-body">
                <textarea id="codeTextarea"></textarea>
            </div>
            <div class="modal-footer">
                <span id="editorLangBadge" style="font-size: 12px; color: var(--text-secondary);">PHP</span>
                <span style="font-size: 12px; color: var(--text-secondary);">Bongo Professional Code Editor</span>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal" id="imageModal">
        <div class="modal-content" style="height: auto; max-height: 85vh;">
            <div class="modal-header">
                <span id="imageTitle" style="font-weight: 600;">Image Preview</span>
                <button class="btn" onclick="document.getElementById('imageModal').classList.remove('active')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" style="display: flex; align-items: center; justify-content: center; padding: 16px;">
                <img id="imagePreviewImg" src="" style="max-width: 100%; max-height: 70vh; border-radius: 6px;">
            </div>
        </div>
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

    <script>
        const app = {
            currentPath: '',
            items: { folders: [], files: [] },
            selectedItem: null,
            editor: null,
            editorFilePath: '',
            prefix: '<?= htmlspecialchars((string) ($prefix ?? 'filemanager'), ENT_QUOTES, 'UTF-8') ?>',
            callback: '<?= htmlspecialchars((string) ($callback ?? ''), ENT_QUOTES, 'UTF-8') ?>',
            type: '<?= htmlspecialchars((string) ($type ?? 'all'), ENT_QUOTES, 'UTF-8') ?>',

            init() {
                this.initCodeEditor();
                this.loadItems('');
                if (this.callback) {
                    document.getElementById('selectBtn').style.display = 'inline-flex';
                }
            },

            initCodeEditor() {
                const textarea = document.getElementById('codeTextarea');
                this.editor = CodeMirror.fromTextArea(textarea, {
                    lineNumbers: true,
                    theme: 'dracula',
                    mode: 'php',
                    tabSize: 4,
                    indentWithTabs: false,
                    lineWrapping: true
                });

                // Ctrl+S / Cmd+S save hotkey
                this.editor.addKeyMap({
                    'Ctrl-S': () => this.saveCodeFile(),
                    'Cmd-S': () => this.saveCodeFile()
                });
            },

            async loadItems(path) {
                this.currentPath = path;
                this.selectedItem = null;
                this.updateBreadcrumbs();

                const res = await fetch(`/${this.prefix}/items?path=${encodeURIComponent(path)}&type=${encodeURIComponent(this.type)}`);
                const data = await res.json();

                if (data.status === 'success') {
                    this.items = data;
                    this.renderItems(data.folders, data.files);
                }
            },

            renderItems(folders, files) {
                const container = document.getElementById('itemsContainer');
                container.innerHTML = '';

                if (folders.length === 0 && files.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            <p>This directory is empty.</p>
                        </div>
                    `;
                    return;
                }

                // Render folders
                folders.forEach(f => {
                    const el = document.createElement('div');
                    el.className = 'file-card';
                    el.innerHTML = `
                        <div class="file-icon" style="color: #f59e0b;"><i class="fa-solid fa-folder"></i></div>
                        <div class="file-name" title="${f.name}">${f.name}</div>
                        <div class="file-size">Folder</div>
                    `;
                    el.onclick = () => this.selectItem(f, el);
                    el.ondblclick = () => this.navigateTo(f.path);
                    container.appendChild(el);
                });

                // Render files
                files.forEach(f => {
                    const el = document.createElement('div');
                    el.className = 'file-card';
                    let iconHtml = '<i class="fa-solid fa-file"></i>';

                    if (f.type === 'image' && f.thumb_url) {
                        iconHtml = `<img class="file-thumb" src="${f.thumb_url}" alt="${f.name}">`;
                    } else if (f.type === 'code') {
                        iconHtml = '<i class="fa-solid fa-file-code" style="color: #3b82f6;"></i>';
                    } else if (f.type === 'pdf') {
                        iconHtml = '<i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i>';
                    } else if (f.type === 'archive') {
                        iconHtml = '<i class="fa-solid fa-file-zipper" style="color: #8b5cf6;"></i>';
                    }

                    el.innerHTML = `
                        <div class="file-icon">${iconHtml}</div>
                        <div class="file-name" title="${f.name}">${f.name}</div>
                        <div class="file-size">${f.size_human}</div>
                    `;
                    el.onclick = () => this.selectItem(f, el);
                    el.ondblclick = () => this.openItem(f);
                    container.appendChild(el);
                });
            },

            selectItem(item, element) {
                document.querySelectorAll('.file-card').forEach(c => c.classList.remove('selected'));
                element.classList.add('selected');
                this.selectedItem = item;
            },

            openItem(item) {
                if (item.is_dir) {
                    this.navigateTo(item.path);
                } else if (item.type === 'image') {
                    document.getElementById('imageTitle').textContent = item.name;
                    document.getElementById('imagePreviewImg').src = item.url;
                    document.getElementById('imageModal').classList.add('active');
                } else if (item.type === 'code') {
                    this.openCodeEditor(item);
                } else if (this.callback) {
                    this.confirmSelection();
                }
            },

            async openCodeEditor(item) {
                this.editorFilePath = item.path;
                document.getElementById('editorFileName').textContent = item.name;
                document.getElementById('editorStatus').textContent = 'Loading...';
                document.getElementById('editorLangBadge').textContent = (item.editor_language || 'plaintext').toUpperCase();
                document.getElementById('editorModal').classList.add('active');

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
                    document.getElementById('editorStatus').textContent = 'Ready';
                } else {
                    alert('Error reading file: ' + data.message);
                    this.closeEditor();
                }
            },

            async saveCodeFile() {
                const status = document.getElementById('editorStatus');
                status.textContent = 'Saving...';
                const content = this.editor.getValue();

                const formData = new FormData();
                formData.append('path', this.editorFilePath);
                formData.append('content', content);

                const res = await fetch(`/${this.prefix}/save`, { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success') {
                    status.textContent = 'Saved at ' + new Date().toLocaleTimeString();
                } else {
                    status.textContent = 'Save failed!';
                    alert('Error saving file: ' + data.message);
                }
            },

            closeEditor() {
                document.getElementById('editorModal').classList.remove('active');
            },

            toggleEditorTheme() {
                const current = this.editor.getOption('theme');
                this.editor.setOption('theme', current === 'dracula' ? 'eclipse' : 'dracula');
            },

            navigateTo(path) {
                this.loadItems(path);
            },

            navigateUp() {
                if (!this.currentPath) return;
                const parts = this.currentPath.split('/');
                parts.pop();
                this.navigateTo(parts.join('/'));
            },

            updateBreadcrumbs() {
                const bc = document.getElementById('breadcrumbs');
                bc.innerHTML = '<span class="crumb" onclick="app.navigateTo(\'\')">uploads</span>';

                if (!this.currentPath) return;
                const parts = this.currentPath.split('/');
                let accum = '';

                parts.forEach((p, idx) => {
                    accum = accum ? `${accum}/${p}` : p;
                    const pathCopy = accum;
                    bc.innerHTML += ` / <span class="crumb" onclick="app.navigateTo('${pathCopy}')">${p}</span>`;
                });
            },

            async promptNewFolder() {
                const name = prompt('Enter new folder name:');
                if (!name) return;

                const formData = new FormData();
                formData.append('path', this.currentPath);
                formData.append('name', name);

                const res = await fetch(`/${this.prefix}/folder`, { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success') {
                    this.refresh();
                } else {
                    alert(data.message);
                }
            },

            async handleUpload(input) {
                if (!input.files || input.files.length === 0) return;

                const formData = new FormData();
                formData.append('path', this.currentPath);
                for (let i = 0; i < input.files.length; i++) {
                    formData.append('files[]', input.files[i]);
                }

                const res = await fetch(`/${this.prefix}/upload`, { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success' || data.status === 'partial') {
                    this.refresh();
                    input.value = '';
                } else {
                    alert('Upload failed: ' + (data.errors ? data.errors.join(', ') : data.message));
                }
            },

            refresh() {
                this.loadItems(this.currentPath);
            },

            confirmSelection() {
                if (!this.selectedItem || this.selectedItem.is_dir) {
                    alert('Please select a file.');
                    return;
                }

                if (window.opener && window.opener.bongoFileManagerCallback) {
                    window.opener.bongoFileManagerCallback(this.selectedItem);
                    window.close();
                } else if (window.parent && window.parent.bongoFileManagerCallback) {
                    window.parent.bongoFileManagerCallback(this.selectedItem);
                } else {
                    alert('Selected: ' + this.selectedItem.url);
                }
            }
        };

        window.onload = () => app.init();
    </script>
</body>
</html>
