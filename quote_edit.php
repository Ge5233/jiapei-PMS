<?php
/**
 * 报价单 新增/编辑
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$quote = null;
$items = [];

if ($isEdit) {
    $quote = Quote::find($id);
    if (!$quote) { setFlash('danger','报价单不存在'); header('Location: /quotes.php'); exit; }
    $items = Quote::getItems($id);
}

// 产品数据给前端下拉
$allProducts = Product::allForSelect();
$allSelfProducts = class_exists('SelfProduct') ? SelfProduct::allForSelect() : [];
$categories = Category::allGrouped();

// 一级分类列表（用于分类汇总 + 确定默认系数）
$level1Categories = [];
foreach ($categories as $c) {
    $level1Categories[$c['id']] = $c['name'];
}

$pageTitle = $isEdit ? '编辑报价单' : '新建报价单';
$activeMenu = 'quotes';
require __DIR__ . '/includes/views/header.php';
?>

<div class="max-w-5xl mx-auto" x-data="quoteForm(<?= h(json_encode([
    'id' => $id, 'isEdit' => $isEdit, 'quote' => $quote, 'items' => $items,
    'allProducts' => $allProducts, 'allSelfProducts' => $allSelfProducts,
    'level1Categories' => $level1Categories,
], JSON_UNESCAPED_UNICODE)) ?>)">

    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium text-slate-800" x-text="isEdit ? '编辑报价单' : '新建报价单'"></h2>
        <a href="/quotes.php" class="btn btn-secondary text-sm"><i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>返回列表</a>
    </div>

    <form @submit.prevent="save">

    <!-- 报价单头 -->
    <div class="card p-6 mb-4">
        <h3 class="text-base font-medium mb-4 pb-2 border-b">基本信息</h3>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="form-label">报价单编号</label>
                <input type="text" class="form-input bg-slate-50" :value="form.quote_no || '（保存后自动生成）'" readonly>
            </div>
            <div>
                <label class="form-label">状态</label>
                <select x-model="form.status" class="form-select">
                    <option value="draft">草稿</option>
                    <option value="sent">已发出</option>
                    <option value="accepted">已接受</option>
                    <option value="rejected">已拒绝</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">项目名称 <span class="text-red-500">*</span></label>
                <input type="text" x-model="form.project_name" class="form-input" required maxlength="200">
            </div>
            <div>
                <label class="form-label">客户名称</label>
                <input type="text" x-model="form.customer_name" class="form-input" maxlength="100">
            </div>
            <div>
                <label class="form-label">联系人</label>
                <input type="text" x-model="form.contact_person" class="form-input" maxlength="50">
            </div>
            <div>
                <label class="form-label">联系电话</label>
                <input type="text" x-model="form.contact_phone" class="form-input" maxlength="30">
            </div>
        </div>
    </div>

    <!-- 产品明细 -->
    <div class="card p-6 mb-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b">
            <h3 class="text-base font-medium">产品明细</h3>
            <div class="flex gap-2">
                <button type="button" class="btn btn-secondary text-sm" @click="addItem('product')"><i data-lucide="plus" class="w-3.5 h-3.5 mr-1"></i>外采产品</button>
                <button type="button" class="btn btn-secondary text-sm" @click="addItem('self_product')"><i data-lucide="plus" class="w-3.5 h-3.5 mr-1"></i>自产产品</button>
                <button type="button" class="btn btn-secondary text-sm" @click="addItem('adhoc')"><i data-lucide="plus" class="w-3.5 h-3.5 mr-1"></i>临时项</button>
            </div>
        </div>

        <template x-if="items.length===0">
            <div class="text-center py-8 text-slate-400 text-sm">点击上方按钮添加产品</div>
        </template>

        <template x-if="items.length>0">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b bg-slate-50 text-xs font-medium text-slate-500">
                        <th class="px-2 py-2 w-8">#</th>
                        <th class="px-2 py-2">来源</th>
                        <th class="px-2 py-2 text-left">产品/名称</th>
                        <th class="px-2 py-2 w-20">数量</th>
                        <th class="px-2 py-2 w-16">单位</th>
                        <th class="px-2 py-2 w-24 text-right">单价</th>
                        <th class="px-2 py-2 w-20 text-right">折扣</th>
                        <th class="px-2 py-2 w-24 text-right">小计</th>
                        <th class="px-2 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, idx) in items" :key="idx">
                        <tr class="border-b hover:bg-slate-50">
                            <td class="px-2 py-2 text-center text-sm" x-text="idx+1"></td>
                            <td class="px-2 py-2">
                                <select class="form-select text-xs py-1" :value="item.source_type"
                                    @change="switchType(idx, $event.target.value)">
                                    <option value="product">外采</option>
                                    <option value="self_product">自产</option>
                                    <option value="adhoc">临时</option>
                                </select>
                            </td>
                            <td class="px-2 py-2">
                                <!-- 外采 -->
                                <template x-if="item.source_type==='product'">
                                    <select class="form-select text-sm" @change="productChanged(idx, $event.target.value)">
                                        <option value="">--选择产品--</option>
                                        <template x-for="p in allProducts" :key="p.id">
                                            <option :value="p.id" x-text="p.sku+' '+p.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <!-- 自产 -->
                                <template x-if="item.source_type==='self_product'">
                                    <select class="form-select text-sm" @change="selfProductChanged(idx, $event.target.value)">
                                        <option value="">--选择产品--</option>
                                        <template x-for="p in allSelfProducts" :key="p.id">
                                            <option :value="p.id" x-text="p.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <!-- 临时 -->
                                <template x-if="item.source_type==='adhoc'">
                                    <input type="text" x-model="item.item_name" class="form-input text-sm" placeholder="名称" @input="calc">
                                </template>
                            </td>
                            <td class="px-2 py-2"><input type="number" x-model="item.quantity" step="0.01" min="0" class="form-input text-sm text-right w-full" @input="calc"></td>
                            <td class="px-2 py-2"><input type="text" x-model="item.unit" class="form-input text-sm w-full" placeholder="套"></td>
                            <td class="px-2 py-2"><input type="number" x-model="item.unit_price" step="0.01" min="0" class="form-input text-sm text-right w-full" @input="calc"></td>
                            <td class="px-2 py-2"><input type="number" x-model="item.discount" step="0.01" min="0.01" max="1" class="form-input text-sm text-right w-full" @input="calc"></td>
                            <td class="px-2 py-2 text-right text-sm tabular-nums font-medium">¥<span x-text="item._subtotal?.toFixed(2)"></span></td>
                            <td class="px-2 py-2 text-center"><button type="button" class="text-red-400 hover:text-red-600" @click="items.splice(idx,1);calc()">&times;</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        </template>
    </div>

    <!-- 商务条款 -->
    <div class="card p-6 mb-4">
        <h3 class="text-base font-medium mb-4 pb-2 border-b">商务条款</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">付款方式</label>
                <input type="text" x-model="form.payment_terms" class="form-input" maxlength="200">
            </div>
            <div>
                <label class="form-label">质保</label>
                <input type="text" x-model="form.warranty" class="form-input" maxlength="100">
            </div>
            <div>
                <label class="form-label">交期</label>
                <input type="text" x-model="form.delivery_period" class="form-input" maxlength="100">
            </div>
            <div>
                <label class="form-label">报价有效期</label>
                <input type="date" x-model="form.valid_until" class="form-input">
            </div>
        </div>
    </div>

    <!-- 汇总 -->
    <div class="card p-6 mb-4 bg-slate-50">
        <div class="flex items-center justify-end gap-8 text-sm">
            <div class="text-right">
                <div class="text-slate-500">小计</div>
                <div class="text-lg font-semibold tabular-nums">¥<span x-text="subtotal.toFixed(2)"></span></div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-slate-500">税率</span>
                <input type="number" x-model="form.tax_rate" step="0.01" min="0" max="1" class="form-input w-20 text-sm text-right" @input="calc">
                <span class="text-slate-500" x-text="'='+(form.tax_rate*100).toFixed(0)+'%'"></span>
            </div>
            <div class="text-right">
                <div class="text-slate-500">税额</div>
                <div class="text-lg font-semibold tabular-nums">¥<span x-text="taxAmount.toFixed(2)"></span></div>
            </div>
            <div class="text-right">
                <div class="text-slate-500">合计</div>
                <div class="text-2xl font-bold text-blue-700 tabular-nums">¥<span x-text="totalAmount.toFixed(2)"></span></div>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3 mb-8">
        <a href="/quotes.php" class="btn btn-secondary">取消</a>
        <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4 mr-1.5"></i><span x-text="isEdit?'保存修改':'创建报价单'"></span></button>
    </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init',()=>{
Alpine.data('quoteForm',(init)=>{
    const productMap={}; (init.allProducts||[]).forEach(p=>{productMap[p.id]=p});
    const selfProductMap={}; (init.allSelfProducts||[]).forEach(p=>{selfProductMap[p.id]=p});
    const level1Cats=init.level1Categories||{};

    // 获取某个产品所属的一级分类
    function getLevel1Cat(pid){
        const p=productMap[pid]; if(!p) return null;
        // productMap doesn't have category info directly... skip for now
        return {id:null,name:''};
    }

    return {
        isEdit: init.isEdit,
        form:{
            quote_no: init.quote?.quote_no||'',
            project_name: init.quote?.project_name||'',
            customer_name: init.quote?.customer_name||'',
            contact_person: init.quote?.contact_person||'',
            contact_phone: init.quote?.contact_phone||'',
            payment_terms: init.quote?.payment_terms||'预付30%，发货前付70%',
            warranty: init.quote?.warranty||'1年',
            delivery_period: init.quote?.delivery_period||'',
            valid_until: init.quote?.valid_until||'',
            tax_rate: init.quote?.tax_rate||0.13,
            status: init.quote?.status||'draft',
        },
        items: (init.items||[]).map(item=>({
            ...item,
            product_id: item.product_id?String(item.product_id):'',
            self_product_id: item.self_product_id?String(item.self_product_id):'',
            quantity: parseFloat(item.quantity)||1,
            unit_price: parseFloat(item.unit_price)||0,
            discount: parseFloat(item.discount)||1,
            _subtotal: parseFloat(item.line_total)||0,
        })),
        allProducts: init.allProducts||[],
        allSelfProducts: init.allSelfProducts||[],
        submitted: false,

        addItem(type){
            this.items.push({source_type:type,product_id:'',self_product_id:'',item_name:'',quantity:1,unit:'套',unit_price:0,discount:1,_subtotal:0,spec:'',category_id:null,category_name:''});
            this.$nextTick(()=>lucide.createIcons());
        },
        switchType(idx,type){
            const item=this.items[idx];
            item.source_type=type; item.product_id=''; item.self_product_id=''; item.item_name='';
            item.unit_price=0; item._subtotal=0;
            if(type==='adhoc') item.item_name=''; else item.item_name='';
            this.calc();
        },
        productChanged(idx,pid){
            const item=this.items[idx];
            item.product_id=pid;
            if(pid&&productMap[pid]){
                const p=productMap[pid];
                item.spec=p.spec||'';
                item.unit=p.unit||'套';
                // 建议售价 = 进价 × 系数
                const coef=parseFloat(p.guide_price_coefficient||1.1);
                item.unit_price=parseFloat(((p.cost_price||0)*coef).toFixed(2));
                item.item_name='';
            }
            this.calc();
        },
        selfProductChanged(idx,pid){
            const item=this.items[idx];
            item.self_product_id=pid;
            if(pid&&selfProductMap[pid]){
                const p=selfProductMap[pid];
                item.spec=p.model_no||'';
                item.unit=p.unit||'套';
                const coef=parseFloat(p.guide_price_coefficient||1.6);
                item.unit_price=parseFloat(((p.total_cost||0)*coef).toFixed(2));
                item.item_name='';
            }
            this.calc();
        },
        calc(){
            let sub=0;
            this.items.forEach(item=>{
                const q=parseFloat(item.quantity)||0;
                const p=parseFloat(item.unit_price)||0;
                const d=parseFloat(item.discount)||1;
                item._subtotal=q*p*d;
                sub+=item._subtotal;
            });
            this.subtotal=sub;
            this.taxAmount=sub*parseFloat(this.form.tax_rate||0);
            this.totalAmount=sub+this.taxAmount;
        },
        get subtotal(){return this._subtotal??0},
        set subtotal(v){this._subtotal=v},
        get taxAmount(){return this._taxAmount??0},
        set taxAmount(v){this._taxAmount=v},
        get totalAmount(){return this._totalAmount??0},
        set totalAmount(v){this._totalAmount=v},

        async save(){
            if(!this.form.project_name.trim()){alert('请输入项目名称');return;}
            if(this.submitted)return; this.submitted=true;
            const fd=new FormData();
            fd.append('id',init.id);
            fd.append('project_name',this.form.project_name);
            fd.append('customer_name',this.form.customer_name);
            fd.append('contact_person',this.form.contact_person);
            fd.append('contact_phone',this.form.contact_phone);
            fd.append('payment_terms',this.form.payment_terms);
            fd.append('warranty',this.form.warranty);
            fd.append('delivery_period',this.form.delivery_period);
            fd.append('valid_until',this.form.valid_until);
            fd.append('tax_rate',this.form.tax_rate);
            fd.append('status',this.form.status);
            fd.append('subtotal',this.subtotal.toFixed(2));
            fd.append('tax_amount',this.taxAmount.toFixed(2));
            fd.append('total_amount',this.totalAmount.toFixed(2));
            fd.append('items',JSON.stringify(this.items.map((item,i)=>({
                source_type:item.source_type,
                product_id:item.product_id||null,
                self_product_id:item.self_product_id||null,
                item_name:item.item_name||null,
                spec:item.spec||'',
                unit:item.unit||'套',
                quantity:parseFloat(item.quantity)||0,
                unit_price:parseFloat(item.unit_price)||0,
                discount:parseFloat(item.discount)||1,
                line_total:item._subtotal||0,
                category_id:item.category_id||null,
                category_name:item.category_name||'',
                sort_order:i,
            }))));
            try{
                const r=await fetch('/api/quote_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':document.querySelector('input[name="_csrf"]')?.value||''},body:fd});
                const d=await r.json();
                if(d.ok) window.location.href='/quotes.php';
                else {alert(d.message);this.submitted=false;}
            }catch(e){alert('网络错误');this.submitted=false;}
        },
    };
});
});
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
