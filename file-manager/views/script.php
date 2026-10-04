<script>
/**
 * Bongo File Manager Integration Helper
 *
 * Example:
 *   bongoFileManager({
 *       button: '#select-image-btn',
 *       targetInput: '#image_url',
 *       targetPreview: '#image_preview',
 *       type: 'image'
 *   });
 */
function bongoFileManager(options) {
    const opts = Object.assign({
        type: 'all',
        prefix: '/filemanager',
        button: null,
        targetInput: null,
        targetPreview: null,
        onSelect: null
    }, options);

    const openManager = function (e) {
        if (e) e.preventDefault();

        const width = 1100;
        const height = 700;
        const left = (screen.width - width) / 2;
        const top = (screen.height - height) / 2;
        const url = `${opts.prefix}?type=${encodeURIComponent(opts.type)}&callback=1`;

        window.bongoFileManagerCallback = function (item) {
            if (opts.targetInput) {
                const el = document.querySelector(opts.targetInput);
                if (el) {
                    el.value = item.url;
                    el.dispatchEvent(new Event('change'));
                }
            }
            if (opts.targetPreview) {
                const img = document.querySelector(opts.targetPreview);
                if (img) {
                    img.src = item.url;
                }
            }
            if (typeof opts.onSelect === 'function') {
                opts.onSelect(item);
            }
        };

        window.open(url, 'BongoFileManager', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`);
    };

    if (opts.button) {
        const btn = document.querySelector(opts.button);
        if (btn) {
            btn.addEventListener('click', openManager);
        }
    }

    return { open: openManager };
}

/**
 * Bongo File Manager Integration Helper for TinyMCE 5, 6, and 7
 *
 * Example:
 *   tinymce.init({
 *       selector: 'textarea#editor',
 *       plugins: 'image link media code',
 *       toolbar: 'undo redo | bold italic | link image media | code',
 *       file_picker_callback: bongoTinyMCEPicker()
 *   });
 */
function bongoTinyMCEPicker(options) {
    const opts = Object.assign({
        prefix: '/filemanager',
        width: 1100,
        height: 700
    }, options || {});

    return function (callback, value, meta) {
        const type = meta.filetype === 'image' ? 'image' : (meta.filetype === 'media' ? 'all' : 'all');
        const width = opts.width;
        const height = opts.height;
        const left = (screen.width - width) / 2;
        const top = (screen.height - height) / 2;
        const url = `${opts.prefix}?type=${encodeURIComponent(type)}&callback=1`;

        window.bongoFileManagerCallback = function (item) {
            callback(item.url, {
                text: item.name,
                alt: item.name,
                title: item.name
            });
        };

        window.open(url, 'BongoFileManager', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`);
    };
}
</script>
