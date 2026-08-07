<?php
/**
 * 报价计算器
 * 输入：指导售价 + 客户要求折扣 → 输出实际成交价、毛利率
 * 支持从产品库快速带入价格
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

$products = Product::allForSelect();
$pageTitle = '报价计算器';
$activeMenu = 'quote';
require __DIR__ . '/includes/views/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- 左侧输入 -->
    <div class="card">
        <div class="card-header">输入参数</div>
        <div class="card-body space-y-4">
            <div>
                <label class="form-label">从产品库选择（可选）</label>
                <select id="product_select" class="form-select" onchange="loadFromProduct(this.value)">
                    <option value="">— 手动输入 —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"
                            data-cost="<?= $p['cost_price'] ?>"
                            data-price="<?= $p['guide_price'] ?>"
                            data-discount="<?= $p['min_discount'] ?>"
                            data-name="<?= h($p['name']) ?>"
                            data-sku="<?= h($p['sku']) ?>">
                            <?= h($p['name']) ?>（<?= h($p['sku']) ?>）
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-help">选择一个产品后自动带入价格</p>
            </div>

            <div class="border-t border-slate-200 pt-4"></div>

            <div>
                <label class="form-label">综合进价 <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                    <input type="number" id="cost_price" class="form-input pl-7 tabular-nums" step="0.01" min="0" value="0">
                </div>
            </div>

            <div>
                <label class="form-label">指导售价 <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                    <input type="number" id="guide_price" class="form-input pl-7 tabular-nums" step="0.01" min="0" value="0">
                </div>
            </div>

            <div>
                <label class="form-label">客户要求折扣 <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="number" id="discount" class="form-input pr-9 tabular-nums" step="0.01" min="0" max="1" value="1.00">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">×</span>
                </div>
                <p class="form-help">1.00 = 不打折，0.85 = 8.5 折，0.80 = 8 折</p>
            </div>

            <div>
                <label class="form-label">该产品允许的最低折扣</label>
                <div class="relative">
                    <input type="number" id="min_discount" class="form-input pr-9 tabular-nums bg-slate-50" step="0.01" min="0" max="1" value="1.00" readonly>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">×</span>
                </div>
                <p class="form-help">从产品库带入，不可手动修改</p>
            </div>
        </div>
    </div>

    <!-- 右侧结果 -->
    <div class="card">
        <div class="card-header">报价结果</div>
        <div class="card-body">
            <div class="space-y-4">
                <div>
                    <div class="text-sm text-slate-500 mb-1">实际成交价</div>
                    <div class="text-3xl font-semibold text-slate-800 tabular-nums">
                        <span class="text-base text-slate-400">¥</span><span id="r_deal">0.00</span>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <div class="text-sm text-slate-500 mb-1">实际毛利率</div>
                    <div class="flex items-baseline gap-2">
                        <div class="text-3xl font-semibold tabular-nums" id="r_margin">0.00%</div>
                        <div id="r_margin_badge"></div>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <div class="text-sm text-slate-500 mb-1">实际毛利（每件）</div>
                    <div class="text-xl font-medium text-slate-700 tabular-nums">
                        <span class="text-sm text-slate-400">¥</span><span id="r_profit">0.00</span>
                    </div>
                </div>

                <div id="warning_box" class="hidden border-t border-slate-200 pt-4">
                    <div id="warning_content" class="p-3 rounded text-sm"></div>
                </div>
            </div>
        </div>
        <div class="card-footer text-xs text-slate-400 text-center">
            ⚠️ 本计算结果不入库，仅用于内部报价参考
        </div>
    </div>
</div>

<script>
function loadFromProduct(id) {
    const sel = document.getElementById('product_select');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('cost_price').value = parseFloat(opt.dataset.cost || 0).toFixed(2);
    document.getElementById('guide_price').value = parseFloat(opt.dataset.price || 0).toFixed(2);
    document.getElementById('min_discount').value = parseFloat(opt.dataset.discount || 1).toFixed(2);
    document.getElementById('discount').value = parseFloat(opt.dataset.discount || 1).toFixed(2);
    calculate();
}

function calculate() {
    const cost = parseFloat(document.getElementById('cost_price').value) || 0;
    const price = parseFloat(document.getElementById('guide_price').value) || 0;
    const disc = parseFloat(document.getElementById('discount').value) || 0;
    const minDisc = parseFloat(document.getElementById('min_discount').value) || 0;

    const deal = price * disc;
    const profit = deal - cost;
    const margin = price > 0 ? (profit / deal) * 100 : 0;

    document.getElementById('r_deal').textContent = deal.toFixed(2);
    document.getElementById('r_margin').textContent = margin.toFixed(2) + '%';
    document.getElementById('r_profit').textContent = profit.toFixed(2);

    const badge = document.getElementById('r_margin_badge');
    const warningBox = document.getElementById('warning_box');
    const warningContent = document.getElementById('warning_content');

    badge.innerHTML = '';
    warningBox.classList.add('hidden');

    if (price === 0) {
        document.getElementById('r_margin').className = 'text-3xl font-semibold tabular-nums text-slate-400';
        return;
    }

    if (margin < 10) {
        document.getElementById('r_margin').className = 'text-3xl font-semibold tabular-nums text-red-600';
        badge.innerHTML = '<span class="badge badge-red">毛利率过低</span>';
    } else if (margin < 20) {
        document.getElementById('r_margin').className = 'text-3xl font-semibold tabular-nums text-amber-600';
        badge.innerHTML = '<span class="badge badge-amber">毛利率偏低</span>';
    } else {
        document.getElementById('r_margin').className = 'text-3xl font-semibold tabular-nums text-emerald-600';
        badge.innerHTML = '<span class="badge badge-green">毛利率健康</span>';
    }

    if (disc < minDisc) {
        warningBox.classList.remove('hidden');
        warningContent.className = 'p-3 rounded text-sm bg-red-50 border border-red-200 text-red-700';
        warningContent.innerHTML = '<strong>⚠️ 突破最低折扣线</strong><br>该产品允许的最低折扣是 <strong>' + minDisc.toFixed(2) + '</strong>（即 ' + (minDisc * 10).toFixed(1) + ' 折），你输入的 <strong>' + disc.toFixed(2) + '</strong> 已超出。实际成交价 <strong>¥' + deal.toFixed(2) + '</strong> 低于底线价 <strong>¥' + (price * minDisc).toFixed(2) + '</strong>。';
    } else if (deal < cost) {
        warningBox.classList.remove('hidden');
        warningContent.className = 'p-3 rounded text-sm bg-red-50 border border-red-200 text-red-700';
        warningContent.innerHTML = '<strong>⚠️ 售价低于进价</strong><br>实际成交价 ¥' + deal.toFixed(2) + ' 低于综合进价 ¥' + cost.toFixed(2) + '，此单会亏损 ¥' + Math.abs(profit).toFixed(2) + '。';
    } else if (margin < 10) {
        warningBox.classList.remove('hidden');
        warningContent.className = 'p-3 rounded text-sm bg-amber-50 border border-amber-200 text-amber-700';
        warningContent.innerHTML = '<strong>⚠️ 毛利率过低</strong><br>建议提升售价或与客户协商折扣幅度。';
    }
}

['cost_price', 'guide_price', 'discount'].forEach(id => {
    document.getElementById(id).addEventListener('input', calculate);
});
calculate();
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
