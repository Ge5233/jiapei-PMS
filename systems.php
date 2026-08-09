<?php
/**
 * 大型系统 BOM 管理 — v3 可搜索下拉
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$projects = SystemProject::all();
$allProducts = Product::allForSelect();
$allSelfProducts = class_exists('SelfProduct') ? SelfProduct::allForSelect() : [];

// JSON for JS
$prodJson = json_encode(array_map(fn($p) => [
    'id' => $p['id'],
    'label' => $p['sku'] . ' ' . $p['name'],
    'price' => (float)($p['cost_price'] ?? 0),
    'unit' => $p['unit'] ?? '',
], $allProducts), JSON_UNESCAPED_UNICODE);
$spJson = json_encode(array_map(fn($sp) => [
    'id' => $sp['id'],
    'label' => $sp['name'],
    'price' => (float)($sp['total_cost'] ?? 0),
    'unit' => $sp['unit'] ?? '',
], $allSelfProducts), JSON_UNESCAPED_UNICODE);

$pageTitle = '大型系统 BOM';
$activeMenu = 'systems';
require __DIR__ . '/includes/views/header.php';
?>
<style>
.collapse-icon{transition:transform 0.2s}
.collapse-icon.open{transform:rotate(90deg)}
.search-drop{position:absolute;z-index:50;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:0.375rem;box-shadow:0 10px 25px rgba(0,0,0,.1);max-height:200px;overflow-y:auto}
.search-drop>div{padding:4px 8px;font-size:13px;cursor:pointer}
.search-drop>div:hover{background:#eff6ff}
.search-drop>div.sel{background:#dbeafe}
</style>

<div class="flex gap-4" style="height:calc(100vh - 150px)" x-data="pmsSystem()">

<!-- 左侧 -->
<div class="w-56 flex-shrink-0 flex flex-col">
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-slate-600">系统项目</span>
        <button class="text-blue-600 hover:text-blue-800 text-sm" @click="newProject">
            <i data-lucide="plus" class="w-4 h-4 inline"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto space-y-1">
        <template x-for="p in projects" :key="p.id">
            <div class="px-3 py-2 rounded cursor-pointer text-sm truncate"
                 :class="activeId===p.id?'bg-blue-50 text-blue-700':'hover:bg-slate-50'"
                 @click="select(p.id)" x-text="p.name"></div>
        </template>
    </div>
</div>

<!-- 右侧 -->
<div class="flex-1 flex flex-col min-w-0" x-show="activeId!==null">
    <div class="flex items-center gap-3 mb-3">
        <input x-model="form.name" :readonly="!editMode" class="text-lg font-medium bg-transparent border-b border-slate-200 focus:border-blue-500 focus:outline-none px-1" placeholder="系统名称">
        <select x-model="form.status" class="text-xs border rounded px-2 py-0.5" :disabled="!editMode">
            <option value="1">在建</option><option value="0">完工</option>
        </select>
        <div class="flex gap-2 ml-auto">
            <button class="btn btn-secondary text-sm" @click="editMode=!editMode" x-text="editMode?'🔒 查看':'✏️ 编辑'"></button>
            <button class="btn btn-secondary text-sm" :class="{ 'ring-2 ring-blue-300': view==='module' }" @click="view='module'">按模块</button>
            <button class="btn btn-secondary text-sm" :class="{ 'ring-2 ring-blue-300': view==='summary' }" @click="view='summary'">物料汇总</button>
            <button class="btn btn-primary text-sm" @click="save" x-show="editMode">保存</button>
            <button class="btn btn-ghost text-sm text-red-500" @click="doDelete" x-show="editMode">删除</button>
        </div>
    </div>
    <textarea x-model="form.desc" :readonly="!editMode" class="form-input text-sm w-full mb-3" rows="2" placeholder="备注"></textarea>

    <!-- 模块视图 -->
    <div class="flex-1 overflow-y-auto" x-show="view==='module'">
        <template x-for="(mod,mi) in modules" :key="mi">
            <div class="mb-3 border rounded">
                <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border-b cursor-pointer" @click="mod._open=!mod._open">
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5 collapse-icon" :class="{open:mod._open}"></i>
                    <input x-model="mod.name" class="font-medium text-sm bg-transparent border-b border-transparent focus:border-blue-500 focus:outline-none flex-1" placeholder="模块名称" @click.stop="">
                    <span class="text-xs text-slate-500" x-text="'¥'+fmt(moduleSum(mi))"></span>
                    <button class="text-blue-500 text-xs" @click.stop="addItem(mi)" x-show="editMode">+主材</button>
                    <button class="text-xs" @click.stop="moveMod(mi,-1)" x-show="editMode">↑</button>
                    <button class="text-xs" @click.stop="moveMod(mi,1)" x-show="editMode">↓</button>
                    <button class="text-xs text-red-400" @click.stop="modules.splice(mi,1)" x-show="editMode">×</button>
                </div>
                <div x-show="mod._open!==false">
                    <div x-show="!(mod.items||[]).length" class="text-center text-xs text-slate-400 py-3">还没有主材，点上方 +主材 添加</div>
                    <template x-for="(it,ii) in (mod.items||[])" :key="ii">
                        <div>
                            <div class="flex items-center gap-1 px-3 py-1.5 text-sm border-b border-slate-50">
                                <select x-model="it.src" class="text-xs border rounded py-0.5 w-14" @change="srcChanged(it)" :disabled="!editMode">
                                    <option value="p">外采</option><option value="s">自产</option><option value="a">临时</option>
                                </select>

                                <!-- 外采搜索框 -->
                                <div x-show="it.src==='p'" class="flex-1 relative">
                                    <input type="text" @focus="it._prodOpen=true" @input="it._prodFilter=$el.value" @keydown.escape="it._prodOpen=false"
                                           @click.away="it._prodOpen=false"
                                           x-model="it._prodShow" :readonly="!editMode"
                                           class="text-sm border rounded px-1 w-full" placeholder="输入关键词搜索..." autocomplete="off">
                                    <div x-show="it._prodOpen && filteredProducts(it._prodFilter||'').length>0" class="search-drop">
                                        <template x-for="p in filteredProducts(it._prodFilter||'')" :key="p.id">
                                            <div @mousedown.prevent="pickProduct(it,p)" :class="{sel:it.pid==p.id}" x-text="p.label"></div>
                                        </template>
                                    </div>
                                </div>

                                <!-- 自产搜索框 -->
                                <div x-show="it.src==='s'" class="flex-1 relative">
                                    <input type="text" @focus="it._spOpen=true" @input="it._spFilter=$el.value" @keydown.escape="it._spOpen=false"
                                           @click.away="it._spOpen=false"
                                           x-model="it._spShow" :readonly="!editMode"
                                           class="text-sm border rounded px-1 w-full" placeholder="输关键词搜索..." autocomplete="off">
                                    <div x-show="it._spOpen && filteredSp(it._spFilter||'').length>0" class="search-drop">
                                        <template x-for="s in filteredSp(it._spFilter||'')" :key="s.id">
                                            <div @mousedown.prevent="pickSp(it,s)" :class="{sel:it.sid==s.id}" x-text="s.label"></div>
                                        </template>
                                    </div>
                                </div>

                                <!-- 临时 名称输入 -->
                                <input x-show="it.src==='a'" x-model="it.name" :readonly="!editMode" class="text-sm border rounded px-1 flex-1 min-w-0" placeholder="名称">

                                <input x-model="it.spec" :readonly="!editMode" class="text-sm border rounded px-1 w-20" placeholder="规格">
                                <input x-model="it.unit" :readonly="!editMode" class="text-sm border rounded px-1 w-12 text-center" placeholder="单位">
                                <input x-model.number="it.qty" :readonly="!editMode" class="text-sm border rounded px-1 w-16 text-right" placeholder="数量">
                                <input x-model.number="it.price" :readonly="!editMode" class="text-sm border rounded px-1 w-20 text-right" placeholder="单价">
                                <span class="w-20 text-right text-xs" x-text="'¥'+fmt(it.qty*it.price)"></span>
                                <button class="text-xs text-blue-400" @click="addSub(it)" x-show="editMode">+配件</button>
                                <button class="text-xs text-red-400" @click="mod.items.splice(ii,1)" x-show="editMode">×</button>
                            </div>
                            <!-- 配件 -->
                            <template x-if="(it.subs||[]).length>0">
                                <div class="bg-slate-50/50 pl-8 border-l-2 border-blue-200">
                                    <template x-for="(s,si) in (it.subs||[])" :key="si">
                                        <div class="flex items-center gap-1 px-3 py-1 text-xs border-b border-slate-100">
                                            <select x-model="s.src" class="text-xs border rounded py-0.5 w-12" @change="srcChanged(s)" :disabled="!editMode">
                                                <option value="p">外采</option><option value="s">自产</option><option value="a">临时</option>
                                            </select>

                                            <!-- 外采 -->
                                            <div x-show="s.src==='p'" class="flex-1 relative">
                                                <input type="text" @focus="s._prodOpen=true" @input="s._prodFilter=$el.value" @keydown.escape="s._prodOpen=false"
                                                       @click.away="s._prodOpen=false"
                                                       x-model="s._prodShow" :readonly="!editMode"
                                                       class="text-xs border rounded px-1 w-full" placeholder="搜索..." autocomplete="off">
                                                <div x-show="s._prodOpen && filteredProducts(s._prodFilter||'').length>0" class="search-drop">
                                                    <template x-for="p in filteredProducts(s._prodFilter||'')" :key="p.id">
                                                        <div @mousedown.prevent="pickProduct(s,p)" :class="{sel:s.pid==p.id}" x-text="p.label"></div>
                                                    </template>
                                                </div>
                                            </div>

                                            <!-- 自产 -->
                                            <div x-show="s.src==='s'" class="flex-1 relative">
                                                <input type="text" @focus="s._spOpen=true" @input="s._spFilter=$el.value" @keydown.escape="s._spOpen=false"
                                                       @click.away="s._spOpen=false"
                                                       x-model="s._spShow" :readonly="!editMode"
                                                       class="text-xs border rounded px-1 w-full" placeholder="搜索..." autocomplete="off">
                                                <div x-show="s._spOpen && filteredSp(s._spFilter||'').length>0" class="search-drop">
                                                    <template x-for="sp in filteredSp(s._spFilter||'')" :key="sp.id">
                                                        <div @mousedown.prevent="pickSp(s,sp)" :class="{sel:s.sid==sp.id}" x-text="sp.label"></div>
                                                    </template>
                                                </div>
                                            </div>

                                            <input x-show="s.src==='a'" x-model="s.name" :readonly="!editMode" class="text-xs border rounded px-1 flex-1 min-w-0" placeholder="名称">
                                            <input x-model="s.spec" :readonly="!editMode" class="text-xs border rounded px-1 w-16" placeholder="规格">
                                            <input x-model="s.unit" :readonly="!editMode" class="text-xs border rounded px-1 w-10 text-center">
                                            <input x-model.number="s.qty" :readonly="!editMode" class="text-xs border rounded px-1 w-14 text-right">
                                            <input x-model.number="s.price" :readonly="!editMode" class="text-xs border rounded px-1 w-16 text-right">
                                            <span class="w-16 text-right" x-text="'¥'+fmt(s.qty*s.price)"></span>
                                            <button class="text-xs text-red-400" @click="it.subs.splice(si,1)" x-show="editMode">×</button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        <button class="btn btn-secondary text-sm w-full" @click="addMod" x-show="editMode">+ 添加模块</button>
    </div>

    <!-- 汇总视图 -->
    <div class="flex-1 overflow-y-auto" x-show="view==='summary'">
        <table class="data-table text-sm w-full">
            <thead><tr><th>物料</th><th>规格</th><th class="text-right">数量</th><th>单位</th><th class="text-right">单价</th><th class="text-right">金额</th><th>来源模块</th></tr></thead>
            <tbody>
                <template x-for="r in summary" :key="r.k">
                    <tr><td x-text="r.n"></td><td x-text="r.spec" class="text-slate-500"></td><td class="text-right" x-text="r.q"></td><td x-text="r.u"></td><td class="text-right" x-text="'¥'+fmt(r.p)"></td><td class="text-right font-medium" x-text="'¥'+fmt(r.t)"></td><td class="text-xs text-slate-400" x-text="r.srcs"></td></tr>
                </template>
            </tbody>
        </table>
        <div class="text-right mt-2 pr-4 font-medium">合计：<span class="text-blue-600" x-text="'¥'+fmt(summaryTotal)"></span></div>
    </div>
</div>

<div class="flex-1 flex items-center justify-center" x-show="activeId===null">
    <div class="text-center text-slate-400"><i data-lucide="cpu" class="w-16 h-16 mx-auto opacity-20"></i><p class="mt-2">选择或新建项目</p></div>
</div>

</div>

<script>
document.addEventListener('alpine:init',()=>{
    Alpine.data('pmsSystem',()=>{
        const PL=<?= $prodJson ?>;
        const SL=<?= $spJson ?>;
        return {
            projects:<?= json_encode($projects,JSON_UNESCAPED_UNICODE) ?>,
            activeId:null,form:{name:'',desc:'',status:1},modules:[],view:'module',editMode:true,
            CSRF:'<?= csrfToken() ?>',

            select(id){this.activeId=id;const p=this.projects.find(x=>x.id===id);if(!p)return;Object.assign(this.form,{name:p.name,desc:p.description||'',status:parseInt(p.status)});this.load(id)},
            async load(id){const r=await fetch('/api/system_bom.php?project_id='+id);const d=await r.json();this.normModules(d.modules||[])},
            normModules(arr){this.modules=arr.map(m=>({name:m.name||'',_open:true,items:(m.items||[]).map(it=>({src:it.self_product_id?'s':(it.product_id?'p':(it.item_name?'a':'p')),pid:it.product_id||'',sid:it.self_product_id||'',name:it.item_name||'',spec:it.spec||'',unit:it.unit||'',qty:parseFloat(it.quantity)||0,price:parseFloat(it.unit_price)||0,_prodOpen:false,_prodFilter:'',_prodShow:it.product_id?(PL.find(x=>x.id==it.product_id)?.label||''):'',_spOpen:false,_spFilter:'',_spShow:it.self_product_id?(SL.find(x=>x.id==it.self_product_id)?.label||''):'',subs:(it.sub_items||[]).map(s=>({src:s.self_product_id?'s':(s.product_id?'p':(s.item_name?'a':'a')),pid:s.product_id||'',sid:s.self_product_id||'',name:s.item_name||'',spec:s.spec||'',unit:s.unit||'',qty:parseFloat(s.quantity)||0,price:parseFloat(s.unit_price)||0,_prodOpen:false,_prodFilter:'',_prodShow:'',_spOpen:false,_spFilter:'',_spShow:''}))}))}));if(!this.modules.length)this.addMod()},
            newProject(){this.activeId='new';this.form={name:'新系统项目',desc:'',status:1};this.modules=[];this.addMod()},
            addMod(){this.modules.push({name:'',_open:true,items:[]})},
            moveMod(i,d){const t=i+d;if(t<0||t>=this.modules.length)return;[this.modules[i],this.modules[t]]=[this.modules[t],this.modules[i]]},
            addItem(mi){this.modules[mi].items=[...this.modules[mi].items,{src:'p',pid:'',sid:'',name:'',spec:'',unit:'',qty:0,price:0,_prodOpen:false,_prodFilter:'',_prodShow:'',_spOpen:false,_spFilter:'',_spShow:'',subs:[]}];this.modules[mi]._open=true},
            addSub(it){it.subs=[...it.subs,{src:'a',pid:'',sid:'',name:'',spec:'',unit:'',qty:0,price:0,_prodOpen:false,_prodFilter:'',_prodShow:'',_spOpen:false,_spFilter:'',_spShow:''}]},
            moduleSum(i){const mod=this.modules[i];let t=0;(mod.items||[]).forEach(it=>{t+=(it.qty||0)*(it.price||0);(it.subs||[]).forEach(s=>t+=(s.qty||0)*(s.price||0))});return t},

            filteredProducts(q){q=(q||'').toLowerCase();return q?PL.filter(p=>p.label.toLowerCase().includes(q)):PL},
            filteredSp(q){q=(q||'').toLowerCase();return q?SL.filter(s=>s.label.toLowerCase().includes(q)):SL},
            pickProduct(it,p){it.pid=p.id;it._prodShow=p.label;it._prodFilter='';it._prodOpen=false;it.price=p.price;it.unit=p.unit;it.name=''},
            pickSp(it,s){it.sid=s.id;it._spShow=s.label;it._spFilter='';it._spOpen=false;it.price=s.price;it.unit=s.unit;it.name=''},
            srcChanged(it){['pid','sid','name','price','_prodShow','_spShow'].forEach(k=>it[k]='')},

            async save(){
                const ser=it=>({source_type:it.src==='p'?'product':(it.src==='s'?'self_product':'adhoc'),product_id:it.src==='p'&&it.pid?it.pid:null,self_product_id:it.src==='s'&&it.sid?it.sid:null,item_name:it.src==='a'&&it.name?it.name:null,spec:it.spec||'',unit:it.unit||'',quantity:it.qty||0,unit_price:it.price||0,sub_items:(it.subs||[]).map(ser)});
                const fd=new FormData();
                if(this.activeId&&this.activeId!=='new')fd.append('id',this.activeId);
                fd.append('name',this.form.name);fd.append('description',this.form.desc||'');fd.append('status',this.form.status);
                fd.append('bom',JSON.stringify(this.modules.map(m=>({name:m.name,items:(m.items||[]).map(ser)}))));
                fd.append('_csrf',this.CSRF);
                try{const r=await fetch('/api/system_save.php',{method:'POST',body:fd});const d=await r.json();if(d.ok){if(this.activeId==='new'){this.activeId=d.id;this.projects.unshift({id:d.id,name:this.form.name,status:this.form.status})}else{const p=this.projects.find(x=>x.id===this.activeId);if(p){p.name=this.form.name;p.status=this.form.status}}alert('保存成功')}else alert(d.message)}catch(e){alert('出错：'+e.message)}
            },
            async doDelete(){if(!confirm('删除「'+this.form.name+'」？'))return;const fd=new FormData();fd.append('action','delete');fd.append('id',this.activeId);fd.append('_csrf',this.CSRF);await fetch('/api/system_save.php',{method:'POST',body:fd});this.projects=this.projects.filter(x=>x.id!==this.activeId);this.activeId=null},
            get summary(){const m={};const add=(k,n,spec,u,q,p,src)=>{if(!m[k])m[k]={k,n,spec,u,q:0,p,t:0,srcs:new Set};m[k].q=+(m[k].q+q).toFixed(2);m[k].t=+(m[k].t+q*p).toFixed(2);m[k].srcs.add(src)};this.modules.forEach(mod=>{(mod.items||[]).forEach(it=>{const nm=it.src==='a'?it.name:(it.pid?(PL.find(x=>x.id==it.pid)?.label):SL.find(x=>x.id==it.sid)?.label);add(nm||'?',nm||'?',it.spec,it.unit,it.qty||0,it.price||0,mod.name);(it.subs||[]).forEach(s=>{const sn=s.src==='a'?s.name:(s.pid?(PL.find(x=>x.id==s.pid)?.label):SL.find(x=>x.id==s.sid)?.label);add(sn||'?',sn||'?',s.spec,s.unit,s.qty||0,s.price||0,mod.name)})})});return Object.values(m).map(r=>({...r,srcs:[...r.srcs].join(', ')}))},
            get summaryTotal(){return this.summary.reduce((t,r)=>t+r.t,0)},
            fmt(v){return(parseFloat(v)||0).toFixed(2)},
        }
    })
})
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
