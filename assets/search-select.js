/**
 * 通用可搜索选择组件 — v1.0
 *
 * 模式一（pick）：纯单选，必须从列表选
 *   用法: x-data="pmsSearchSelect({ mode:'pick', items:[...], placeholder:'搜索...' })"
 *
 * 模式二（source）：三态（外采/自产/临时），前有来源选择
 *   用法: x-data="pmsSearchSelect({ mode:'source', sources:{ product:[...], self:[...] }, placeholder:'搜索...' })"
 *
 * 选中后显示为只读标签，可×清除重新选择。
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('pmsSearchSelect', (config) => {
        // ---- 模式一：纯单选 ----
        if (config.mode === 'pick') {
            return {
                open: false,
                keyword: '',
                selectedId: config.selectedId || '',
                selectedLabel: config.selectedLabel || '',
                items: config.items || [],
                placeholder: config.placeholder || '输入关键字筛选',
                filtered: [],

                init() {
                    this.filter();
                    // 暴露刷新方法（如供应商弹窗新增后刷新列表）
                    if (config.refreshKey) {
                        window['_ssRefresh_' + config.refreshKey] = (list) => {
                            this.items = list;
                            this.keyword = '';
                            this.filter();
                        };
                    }
                    this.$watch('open', (v) => {
                        if (v) {
                            this.$nextTick(() => this.$refs.ssInput?.focus());
                        } else {
                            this.keyword = '';
                            this.filter();
                        }
                    });
                },

                pick(item) {
                    this.selectedId = item.id;
                    this.selectedLabel = item.label;
                    this.open = false;
                    if (config.onselect) config.onselect(item);
                },

                clear() {
                    this.selectedId = '';
                    this.selectedLabel = '';
                    if (config.onclear) config.onclear();
                },

                filter() {
                    const kw = this.keyword.trim().toLowerCase();
                    if (!kw) {
                        this.filtered = this.items.slice();
                    } else {
                        this.filtered = this.items.filter(it =>
                            (it.label || '').toLowerCase().includes(kw)
                        );
                    }
                },

                // 键盘导航
                _navIdx: -1,
                onKeydown(e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this._navIdx = Math.min(this._navIdx + 1, this.filtered.length - 1);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this._navIdx = Math.max(this._navIdx - 1, 0);
                    } else if (e.key === 'Enter' && this._navIdx >= 0) {
                        e.preventDefault();
                        this.pick(this.filtered[this._navIdx]);
                    } else if (e.key === 'Escape') {
                        this.open = false;
                    }
                },

                openDropdown() {
                    this.open = true;
                    this._navIdx = this.selectedId ? this.filtered.findIndex(it => it.id === this.selectedId) : -1;
                }
            };
        }

        // ---- 模式二：三态 ----
        if (config.mode === 'source') {
            return {
                open: false,
                keyword: '',
                selectedId: config.selectedId || '',
                selectedLabel: config.selectedLabel || '',
                selectedSource: config.selectedSource || 'product',
                sources: config.sources || { product: [], self: [] },
                placeholder: config.placeholder || '搜索...',
                filtered: [],

                init() {
                    this.filter();
                    this.$watch('open', (v) => {
                        if (v) {
                            this.$nextTick(() => this.$refs.ssInput?.focus());
                        } else {
                            this.keyword = '';
                            this.filter();
                        }
                    });
                    this.$watch('selectedSource', () => {
                        this.keyword = '';
                        this.filter();
                    });
                },

                get currentItems() {
                    return this.sources[this.selectedSource] || [];
                },

                pick(item) {
                    this.selectedId = item.id;
                    this.selectedLabel = item.label;
                    this.open = false;
                    if (config.onselect) config.onselect(item, this.selectedSource);
                },

                clear() {
                    this.selectedId = '';
                    this.selectedLabel = '';
                    if (config.onclear) config.onclear();
                },

                filter() {
                    const kw = this.keyword.trim().toLowerCase();
                    const list = this.currentItems;
                    if (!kw) {
                        this.filtered = list.slice();
                    } else {
                        this.filtered = list.filter(it =>
                            (it.label || '').toLowerCase().includes(kw)
                        );
                    }
                },

                _navIdx: -1,
                onKeydown(e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this._navIdx = Math.min(this._navIdx + 1, this.filtered.length - 1);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this._navIdx = Math.max(this._navIdx - 1, 0);
                    } else if (e.key === 'Enter' && this._navIdx >= 0) {
                        e.preventDefault();
                        this.pick(this.filtered[this._navIdx]);
                    } else if (e.key === 'Escape') {
                        this.open = false;
                    }
                },

                openDropdown() {
                    this.open = true;
                    this._navIdx = this.selectedId ? this.filtered.findIndex(it => it.id === this.selectedId) : -1;
                }
            };
        }

        // fallback
        return {};
    });
});
