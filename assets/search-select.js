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
(function () {
    function register() {
        if (typeof Alpine === 'undefined') return false;

        // ---- 模式一：纯单选 ----
        Alpine.data('pmsSearchSelect', function (config) {
            if (config && config.mode === 'pick') {
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
                        if (config.refreshKey) {
                            window['_ssRefresh_' + config.refreshKey] = function (list) {
                                this.items = list;
                                this.keyword = '';
                                this.filter();
                            }.bind(this);
                        }
                        this.$watch('open', function (v) {
                            if (v) {
                                this.$nextTick(function () {
                                    var el = this.$refs.ssInput;
                                    if (el && el.focus) el.focus();
                                }.bind(this));
                            } else {
                                this.keyword = '';
                                this.filter();
                            }
                        }.bind(this));
                    },

                    pick: function (item) {
                        this.selectedId = item.id;
                        this.selectedLabel = item.label;
                        this.open = false;
                        if (config.onselect) config.onselect(item);
                    },

                    clear: function () {
                        this.selectedId = '';
                        this.selectedLabel = '';
                        if (config.onclear) config.onclear();
                    },

                    filter: function () {
                        var kw = (this.keyword || '').trim().toLowerCase();
                        if (!kw) {
                            this.filtered = this.items.slice();
                            return;
                        }
                        this.filtered = this.items.filter(function (it) {
                            return (it.label || '').toLowerCase().includes(kw);
                        });
                    },

                    onKeydown: function (e) {
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

                    openDropdown: function () {
                        this.open = true;
                        this._navIdx = this.selectedId
                            ? this.filtered.findIndex(function (it) { return it.id === this.selectedId; }.bind(this))
                            : -1;
                    }
                };
            }

            // ---- 模式二：三态 ----
            if (config && config.mode === 'source') {
                return {
                    open: false,
                    keyword: '',
                    selectedId: config.selectedId || '',
                    selectedLabel: config.selectedLabel || '',
                    selectedSource: config.selectedSource || 'product',
                    sources: config.sources || { product: [], self: [] },
                    placeholder: config.placeholder || '搜索...',
                    filtered: [],

                    init: function () {
                        this.filter();
                    },

                    getCurrentItems: function () {
                        return this.sources[this.selectedSource] || [];
                    },

                    pick: function (item) {
                        this.selectedId = item.id;
                        this.selectedLabel = item.label;
                        this.open = false;
                        if (config.onselect) config.onselect(item, this.selectedSource);
                    },

                    clear: function () {
                        this.selectedId = '';
                        this.selectedLabel = '';
                        if (config.onclear) config.onclear();
                    },

                    filter: function () {
                        var kw = (this.keyword || '').trim().toLowerCase();
                        var list = this.sources[this.selectedSource] || [];
                        if (!kw) {
                            this.filtered = list.slice();
                            return;
                        }
                        this.filtered = list.filter(function (it) {
                            return (it.label || '').toLowerCase().includes(kw);
                        });
                    },

                    onKeydown: function (e) {
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

                    openDropdown: function () {
                        this.open = true;
                        this._navIdx = -1;
                    }
                };
            }

            return {};
        });
        return true;
    }

    // Alpine 通过 defer 加载（在 DOMContentLoaded 后）
    // search-select.js 不 defer 会先执行，需要等 Alpine 就绪
    if (register()) return;

    document.addEventListener('alpine:init', register);

    // 兜底：Alpine 加载完成但 alpine:init 已触发（加载顺序异常）
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Alpine !== 'undefined' && !Alpine.$data) {
            // Alpine 已就绪，再次尝试
            register();
        }
    });
})();