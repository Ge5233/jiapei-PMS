// 公共 JS

// 确认弹窗
function pmsConfirm(message, onOk) {
    if (confirm(message)) {
        onOk();
    }
}

// 异步 fetch 包装
async function pmsFetch(url, options = {}) {
    const defaults = {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '',
        },
    };
    const opts = { ...defaults, ...options };
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(opts.body);
    }
    const resp = await fetch(url, opts);
    const ct = resp.headers.get('Content-Type') || '';
    if (ct.includes('application/json')) {
        return await resp.json();
    }
    return await resp.text();
}

// 拖拽上传
function initUpload(areaEl, inputEl, onFile) {
    if (!areaEl || !inputEl) return;
    areaEl.addEventListener('click', () => inputEl.click());
    ['dragenter', 'dragover'].forEach(ev => {
        areaEl.addEventListener(ev, e => {
            e.preventDefault();
            e.stopPropagation();
            areaEl.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(ev => {
        areaEl.addEventListener(ev, e => {
            e.preventDefault();
            e.stopPropagation();
            areaEl.classList.remove('dragover');
        });
    });
    areaEl.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        if (files.length) onFile(files[0]);
    });
    inputEl.addEventListener('change', e => {
        if (e.target.files.length) onFile(e.target.files[0]);
    });
}

// 简易拖拽排序
function initDragSort(containerEl, itemSelector, onSortEnd) {
    if (!containerEl) return;
    let dragEl = null;
    containerEl.querySelectorAll(itemSelector).forEach(item => {
        item.setAttribute('draggable', 'true');
        item.addEventListener('dragstart', e => {
            dragEl = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            containerEl.querySelectorAll(itemSelector).forEach(el => el.classList.remove('drag-over'));
            dragEl = null;
        });
        item.addEventListener('dragover', e => {
            e.preventDefault();
            if (!dragEl || dragEl === item) return;
            item.classList.add('drag-over');
        });
        item.addEventListener('dragleave', () => {
            item.classList.remove('drag-over');
        });
        item.addEventListener('drop', e => {
            e.preventDefault();
            item.classList.remove('drag-over');
            if (!dragEl || dragEl === item) return;
            const rect = item.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            if (after) {
                item.parentNode.insertBefore(dragEl, item.nextSibling);
            } else {
                item.parentNode.insertBefore(dragEl, item);
            }
            const ids = Array.from(containerEl.querySelectorAll(itemSelector))
                .map(el => el.dataset.id)
                .filter(Boolean);
            onSortEnd(ids);
        });
    });
}
