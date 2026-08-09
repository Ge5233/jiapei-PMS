// 公共 JS

// 确认弹窗
function pmsConfirm(message, onOk) {
    if (confirm(message)) {
        onOk();
    }
}

// ============================================================
// 可搜索下拉框组件
// 用法: <div x-data="searchSelect(options, selectedIdGetter, onSelect, placeholder)">...</div>
// options: [{id:'',label:'SKU 名称',price:0,unit:''}, ...]
//   - 外采产品: options=products.map(p=>({id:p.id,label:p.sku+' '+p.name,price:p.cost_price,unit:p.unit}))
//   - 自产产品: options=selfProducts.map(sp=>({id:sp.id,label:sp.name,price:sp.total_cost,unit:sp.unit}))
// 模板 (放在需要的位置即可):
//   <input type="text" x-model="search" @focus="open=true;if(!search)filter()" @input="filter" @keydown.escape="open=false"
//          :placeholder="placeholder" class="form-input text-sm w-full" autocomplete="off">
//   <div x-show="open && filtered.length>0" class="absolute z-50 w-full bg-white border rounded shadow-lg max-h-48 overflow-y-auto">
//     <template x-for="o in filtered" :key="o.id">
//       <div @mousedown.prevent="pick(o)" class="px-2 py-1.5 text-sm hover:bg-blue-50 cursor-pointer" :class="{'bg-blue-100': o.id==selectedId}">
//         <span x-text="o.label"></span>
//       </div>
//     </template>
//   </div>
//   <input type="hidden" :name="name" :value="selectedId">
document.addEventListener('alpine:init', () => {
    Alpine.data('searchSelect', (options, initialId, onPick, ph) => ({
        options: options || [],
        selectedId: initialId || '',
        displayText: '',
        search: '',
        open: false,
        placeholder: ph || '搜索...',
        get filtered() {
            const q = (this.search || '').toLowerCase();
            if (!q) return this.options;
            return this.options.filter(o => o.label.toLowerCase().includes(q));
        },
        init() {
            if (this.selectedId) {
                const o = this.options.find(o => o.id == this.selectedId);
                if (o) this.displayText = o.label;
            }
        },
        filter() { /* reactive getter */ },
        pick(o) {
            this.selectedId = o.id;
            this.displayText = o.label;
            this.search = o.label;
            this.open = false;
            if (typeof onPick === 'function') onPick(o);
        },
        // Detect click outside to close
        // Uses @click.away in template
    }));
});

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
