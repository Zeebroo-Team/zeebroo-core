@once
<style>
/* Three-panel POS: left = sale, center = browse/products, right = checkout */
.pos-three-panel{display:grid;gap:10px;flex:1;min-height:0;width:100%;}
@media (min-width:960px){
    .pos-three-panel{grid-template-columns:minmax(360px,440px) minmax(0,1fr) minmax(280px,340px);align-items:stretch;gap:10px;}
}
.pos-three-panel__left,.pos-three-panel__center,.pos-three-panel__right{display:flex;flex-direction:column;min-height:0;min-width:0;overflow:hidden;border:1px solid var(--border);border-radius:12px;background:var(--card);}
.pos-three-panel__center{border-radius:12px;}
.pos-three-panel__left{border-radius:12px;}
.pos-three-panel__right{border-radius:12px;}

body.pos-walking-active .pos-three-panel{
    display:grid;
    grid-template-columns:var(--pos-walking-sale-w) minmax(0,1fr) var(--pos-walking-cart-w);
    grid-template-rows:1fr;
    gap:0;
    height:calc(100vh - var(--pos-walking-top-h,52px));
    max-height:calc(100vh - var(--pos-walking-top-h,52px));
    min-height:0;
}
body.pos-walking-active .pos-three-panel__left,
body.pos-walking-active .pos-three-panel__center,
body.pos-walking-active .pos-three-panel__right{
    border-radius:0;
    border-top:0;
    border-bottom:0;
    box-shadow:none;
}
body.pos-walking-active .pos-three-panel__left{border-left:0;border-right:1px solid var(--border);}
body.pos-walking-active .pos-three-panel__center{border-left:0;border-right:1px solid var(--border);}
body.pos-walking-active .pos-three-panel__right{border-right:0;border-left:1px solid var(--border);}

body.pos-walking-active .pos-fixed-sale,
body.pos-walking-active .pos-fixed-cart{
    position:static!important;
    top:auto!important;
    left:auto!important;
    right:auto!important;
    bottom:auto!important;
    width:auto!important;
    height:auto!important;
    max-height:none!important;
    margin:0!important;
    z-index:auto!important;
}
body.pos-walking-active .pos-online__catalog,
body.pos-walking-active .pos-register__catalog{
    margin:0!important;
    height:auto!important;
    flex:1;
    min-height:0;
    border:none;
    background:var(--card);
}
body.pos-walking-active .pos-online--walking .pos-three-panel__left,
body.pos-walking-active .pos-online--walking .pos-three-panel__center,
body.pos-walking-active .pos-page--walking .pos-three-panel__left,
body.pos-walking-active .pos-page--walking .pos-three-panel__center{
    background:var(--card);
}
body.pos-walking-active .pos-online__catalog{display:flex;flex-direction:column;}
body.pos-walking-active .pos-online__catalog-body{flex:1;min-height:0;display:flex;flex-direction:column;}
body.pos-walking-active .pos-online__catalog-main{flex:1;min-height:0;display:flex;flex-direction:column;}
body.pos-walking-active .pos-online__grid-wrap{flex:1;min-height:0;overflow-y:auto;}
body.pos-walking-active .pos-register__catalog{display:flex;flex-direction:column;}
body.pos-walking-active .pos-register__catalog-body{flex:1;min-height:0;display:flex;flex-direction:column;}
body.pos-walking-active .pos-register__catalog .pos-panel__body{flex:1;min-height:0;overflow-y:auto;}

@media (max-width:720px){
    body.pos-walking-active .pos-three-panel{
        grid-template-columns:var(--pos-walking-sale-w) minmax(0,1fr);
        grid-template-rows:minmax(0,1fr) min(44vh,380px);
    }
    body.pos-walking-active .pos-three-panel__left{grid-column:1;grid-row:1;}
    body.pos-walking-active .pos-three-panel__center{grid-column:2;grid-row:1;}
    body.pos-walking-active .pos-three-panel__right{
        grid-column:1 / -1;
        grid-row:2;
        border-top:1px solid var(--border);
        border-left:0;
        max-height:min(44vh,380px);
    }
}
</style>
@endonce
