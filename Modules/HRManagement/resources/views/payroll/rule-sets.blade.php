@extends('theme::layouts.app', ['title' => __('Payroll rule sets'), 'heading' => __('Payroll rule sets')])

@section('content')
    <style>
        .payroll-wrap{max-width:1120px;display:grid;gap:12px}
        .payroll-card{border:1px solid color-mix(in srgb,var(--border)90%,transparent);border-radius:12px;background:var(--card);padding:12px 14px;box-shadow:0 1px 0 color-mix(in srgb,var(--border)55%,transparent) inset,0 6px 18px rgba(0,0,0,.04)}
        .payroll-head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px}
        .payroll-title{margin:0;font-size:.98rem;font-weight:800}
        .payroll-sub{margin:3px 0 0;font-size:12px;line-height:1.4;color:var(--muted)}
        .payroll-grid{display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-items:end}
        .payroll-field label{display:block;font-size:10px;font-weight:700;color:var(--muted);margin-bottom:4px}
        .payroll-input{width:100%;box-sizing:border-box;border:1px solid color-mix(in srgb,var(--border)90%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);color:var(--text);border-radius:8px;padding:8px 10px;font-size:12px;line-height:1.35;outline:none}
        .payroll-input:focus{border-color:color-mix(in srgb,var(--primary)48%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary)14%,transparent)}
        .payroll-table-wrap{overflow:auto;border:1px solid color-mix(in srgb,var(--border)90%,transparent);border-radius:10px;background:color-mix(in srgb,var(--card)98%,transparent)}
        .payroll-table{width:100%;min-width:760px;border-collapse:separate;border-spacing:0}
        .payroll-table th,.payroll-table td{vertical-align:top}
        .payroll-table thead th{background:color-mix(in srgb,var(--card)94%,transparent);color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;padding:8px;white-space:nowrap;border-bottom:1px solid color-mix(in srgb,var(--border)82%,transparent)}
        .payroll-table tbody td{padding:8px;border-bottom:1px solid color-mix(in srgb,var(--border)74%,transparent)}
        .payroll-table tbody tr:nth-child(even) td{background:color-mix(in srgb,var(--card)97%,transparent)}
        .payroll-table tbody tr:hover td{background:color-mix(in srgb,var(--primary)6%,transparent)}
        .payroll-table tbody tr:last-child td{border-bottom:none}
        .payroll-table th + th,.payroll-table td + td{border-left:1px solid color-mix(in srgb,var(--border)68%,transparent)}
        .payroll-name{font-size:12px;font-weight:700;line-height:1.25}
        .payroll-rules-col{font-size:11px;line-height:1.25}
        .payroll-col-name-head,.payroll-col-effective-head{}
        .payroll-col-effective-val{font-size:10px;line-height:1.25}
        .payroll-col-currency-val{font-size:10px;line-height:1.2}
        .payroll-td--num{text-align:right;font-variant-numeric:tabular-nums}
        .payroll-td--center{text-align:center}
        .payroll-rule-cell{background:color-mix(in srgb,var(--card)95%,transparent)!important}
        .payroll-chip{display:inline-flex;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;border:1px solid var(--border);background:color-mix(in srgb,var(--card)96%,transparent)}
        .payroll-chip--ok{border-color:color-mix(in srgb,#22c55e 42%,var(--border));color:#15803d;background:color-mix(in srgb,#22c55e 10%,transparent)}
        .payroll-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary)42%,var(--border));background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);font-size:12px;font-weight:700;cursor:pointer}
        .payroll-btn:hover{background:color-mix(in srgb,var(--primary)18%,transparent)}
        button.payroll-btn:disabled,.payroll-btn[disabled]{opacity:.5;cursor:not-allowed}
        button.payroll-btn:disabled:hover,.payroll-btn[disabled]:hover{background:color-mix(in srgb,var(--primary)12%,transparent)}
        .payroll-rule-form{display:grid;gap:5px;grid-template-columns:repeat(3,minmax(100px,1fr));padding:5px;border:1px solid color-mix(in srgb,var(--border)84%,transparent);border-radius:8px;background:color-mix(in srgb,var(--card)98%,transparent)}
        .payroll-rule-list{
            margin-top:8px;
            border:1px solid color-mix(in srgb,var(--border)84%,transparent);
            border-radius:10px;
            padding:8px;
            background:color-mix(in srgb,var(--card)99%,transparent);
        }
        .payroll-rule-list summary{
            cursor:pointer;
            font-size:11px;
            font-weight:800;
            color:var(--muted);
            letter-spacing:.04em;
            text-transform:uppercase;
        }
        .payroll-rule-list[open] summary{margin-bottom:8px}
        .payroll-rule-item{
            display:grid;
            gap:6px;
            border:1px solid color-mix(in srgb,var(--border)76%,transparent);
            border-radius:10px;
            padding:8px;
            margin-top:6px;
            background:color-mix(in srgb,var(--card)96%,transparent);
        }
        .payroll-rule-item:first-of-type{margin-top:0}
        .payroll-rule-item-head{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:8px;
        }
        .payroll-rule-item-title{font-size:12px;line-height:1.3}
        .payroll-rule-code{
            display:inline-flex;
            font-size:11px;
            font-weight:800;
            border-radius:6px;
            padding:2px 6px;
            border:1px solid color-mix(in srgb,var(--primary)38%,var(--border));
            background:color-mix(in srgb,var(--primary)10%,transparent);
        }
        .payroll-rule-tags{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
        .payroll-rule-tag{
            font-size:10px;
            font-weight:700;
            border-radius:999px;
            padding:2px 7px;
            border:1px solid color-mix(in srgb,var(--border)80%,transparent);
            background:color-mix(in srgb,var(--card)95%,transparent);
            color:var(--muted);
        }
        .payroll-rule-config{
            font-size:10px;
            line-height:1.35;
            color:var(--muted);
            background:color-mix(in srgb,var(--card)94%,transparent);
            border:1px dashed color-mix(in srgb,var(--border)76%,transparent);
            border-radius:8px;
            padding:8px;
            display:grid;
            gap:6px;
        }
        .payroll-rule-config-row{
            display:flex;
            justify-content:space-between;
            gap:8px;
            align-items:flex-start;
            border-bottom:1px solid color-mix(in srgb,var(--border)62%,transparent);
            padding-bottom:4px;
        }
        .payroll-rule-config-row:last-child{border-bottom:none;padding-bottom:0}
        .payroll-rule-config-key{font-weight:700;color:var(--text);font-size:10px}
        .payroll-rule-config-value{text-align:right;font-size:10px}
        .payroll-rule-config-slabs{
            display:flex;
            flex-wrap:wrap;
            gap:6px;
            justify-content:flex-end;
        }
        .payroll-rule-config-slab{
            font-size:9px;
            border:1px solid color-mix(in srgb,var(--border)72%,transparent);
            border-radius:999px;
            padding:2px 7px;
            background:color-mix(in srgb,var(--card)97%,transparent);
        }
        .payroll-rule-empty{
            font-size:11px;
            color:var(--muted);
            padding:6px 2px;
        }

        .payroll-add-rule-summary{cursor:pointer}
        .payroll-add-rule-details summary{list-style:none}
        .payroll-add-rule-details summary::-webkit-details-marker{display:none}
        .payroll-add-rule-details summary::marker{display:none}

        .payroll-rule-set-cards{display:flex;flex-direction:column;gap:12px}
        .payroll-rule-set-card{border:1px solid color-mix(in srgb,var(--border)90%,transparent);border-radius:12px;background:color-mix(in srgb,var(--card)100%,transparent);padding:12px 14px;}
        .payroll-rule-set-head{display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
        .payroll-rule-set-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;min-width:140px}
        .payroll-rule-set-effective{font-size:9.5px;line-height:1.25;color:var(--muted)}
        .payroll-rule-set-line{display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:11px;color:var(--muted)}
        .payroll-rule-set-line .payroll-name{font-size:12px}
        .payroll-rule-set-sep{opacity:.65}
        @media (max-width:720px){.payroll-rule-set-cards{gap:10px}}

        /* Modal */
        .payroll-modal-overlay{
            position:fixed;inset:0;
            background:rgba(2,6,23,.55);
            backdrop-filter:blur(3px);
            display:none;
            align-items:center;
            justify-content:center;
            z-index:1100;
            padding:18px;
        }
        .payroll-modal-overlay.is-open{display:flex}
        /* Flow designer: scroll the overlay instead of vertically centering a tall modal (avoids clipped header/footer). */
        #payflowEditorOverlay{padding:clamp(8px,1.8vmin,14px)}
        #payflowEditorOverlay.is-open{
            align-items:flex-start;
            justify-content:center;
            overflow-x:hidden;
            overflow-y:auto;
            -webkit-overflow-scrolling:touch;
        }
        .payroll-modal{
            width:100%;
            max-width:760px;
            border:1px solid color-mix(in srgb,var(--border)86%,transparent);
            border-radius:16px;
            background:linear-gradient(180deg,color-mix(in srgb,var(--card)96%,#fff 4%),var(--card));
            box-shadow:0 22px 55px rgba(2,6,23,.35);
            padding:14px;
        }
        .payroll-modal__head{
            display:flex;align-items:flex-start;justify-content:space-between;gap:10px;
            margin-bottom:12px;
            padding:2px 2px 8px;
            border-bottom:1px solid color-mix(in srgb,var(--border)80%,transparent);
        }
        .payroll-modal__title{margin:0;font-size:1.05rem;font-weight:900;letter-spacing:-.01em}
        .payroll-modal__sub{margin:4px 0 0;font-size:11px;line-height:1.35;color:var(--muted)}
        .payroll-modal__close{
            border:1px solid color-mix(in srgb,var(--border)84%,transparent);background:color-mix(in srgb,var(--card)97%,transparent);color:var(--muted);
            font-size:20px;line-height:1;cursor:pointer;padding:4px 8px;border-radius:10px;
        }
        .payroll-modal__close:hover{background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text)}
        .payroll-modal__form-wrap{
            border:1px solid color-mix(in srgb,var(--border)82%,transparent);
            border-radius:12px;
            background:color-mix(in srgb,var(--card)97%,transparent);
            padding:10px;
        }
        .payroll-modal__actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:12px}
        .payroll-modal__actions .payroll-btn{padding:7px 12px}
        .payroll-modal__btn-primary{
            border-color:color-mix(in srgb,var(--primary)60%,var(--border));
            background:linear-gradient(180deg,color-mix(in srgb,var(--primary)28%,transparent),color-mix(in srgb,var(--primary)18%,transparent));
        }
        .payroll-modal__btn-secondary{
            border-color:color-mix(in srgb,var(--border)88%,transparent);
            background:color-mix(in srgb,var(--card)96%,transparent);
        }
        /* Add/edit rule modal: allow scroll when the form exceeds the viewport. */
        #payrollRuleModalOverlay .payroll-modal,
        #payrollRuleSetModalOverlay .payroll-modal{
            max-height:min(92vh,calc(100vh - 32px));
            overflow-x:hidden;
            overflow-y:auto;
            -webkit-overflow-scrolling:touch;
        }
        @media (max-width:640px){
            .payroll-rule-form{grid-template-columns:1fr;gap:8px;padding:8px}
        }
        .payroll-rule-help{display:block;margin-top:4px;font-size:10px;line-height:1.35;color:var(--muted)}
        .payroll-field-help{display:block;margin-top:4px;font-size:10px;line-height:1.35;color:var(--muted)}

        /* D3 flow editor — modal ~98% of viewport */
        .payroll-modal.payroll-flow-modal--wide{
            width:98vw;
            max-width:min(98vw, calc(100vw - 24px));
            max-height:min(calc(100vh - 24px),98vh);
            height:min(96vh,calc(100vh - 28px));
            box-sizing:border-box;
            overflow-x:hidden;
            overflow-y:hidden;
            display:flex;
            flex-direction:column;
        }
        .payroll-modal.payroll-flow-modal--wide > .payflow-flow-modal-top{
            flex-shrink:0;
            position:sticky;
            top:0;
            z-index:12;
            margin:-14px -14px 0 -14px;
            padding:14px 0 0 0;
            border-bottom:1px solid color-mix(in srgb,var(--border)80%,transparent);
            background:linear-gradient(180deg,color-mix(in srgb,var(--card)96%,#fff 4%),var(--card));
        }
        .payflow-flow-modal-top .payroll-modal__head{
            margin-bottom:0;
            padding:0 14px 6px 14px;
            border-bottom:1px solid color-mix(in srgb,var(--border)72%,transparent);
        }
        .payflow-flow-modal-top .payflow-logic-tools{
            margin:0 0 6px 0;
            width:100%;
            max-width:none;
            flex-basis:auto;
            box-sizing:border-box;
            border-radius:0;
            border-left:0;
            border-right:0;
            padding:2px 10px 6px 10px;
        }
        .payflow-flow-modal-top .payflow-logic-tools__body{gap:4px}
        .payflow-flow-modal-top .payflow-logic-block.is-visible{gap:6px}
        .payflow-flow-modal-top .payflow-logic-block--stack{padding-left:10px}
        .payflow-flow-modal-top .payflow-logic-chips{flex:0 1 auto}
        .payroll-modal.payroll-flow-modal--wide > #payflowSaveForm{
            flex:1;
            min-height:0;
            display:flex;
            flex-direction:column;
            overflow:hidden;
        }
        .payroll-modal.payroll-flow-modal--wide #payflowSaveForm > .payroll-flow-help{flex-shrink:0}
        .payroll-modal.payroll-flow-modal--wide #payflowSaveForm > .payroll-modal__actions{flex-shrink:0;margin-top:auto}
        .payroll-modal.payroll-flow-modal--wide .payroll-flow-editor-layout{
            flex:1;
            min-height:0;
            display:flex;
            flex-direction:column;
            flex-wrap:nowrap;
            align-items:stretch;
            gap:12px;
            margin-top:6px;
            overflow:hidden;
        }
        .payroll-modal.payroll-flow-modal--wide .payflow-flow-editor-workspace{
            flex:1;
            min-height:0;
            min-width:0;
            display:flex;
            flex-direction:row;
            flex-wrap:nowrap;
            align-items:stretch;
            gap:12px;
            overflow:hidden;
        }
        .payroll-flow-editor-layout{display:flex;gap:12px;flex-wrap:wrap;align-items:stretch;margin-top:6px}
        .payroll-modal.payroll-flow-modal--wide .payflow-formula-chrome{
            flex-shrink:0;
            width:100%;
            max-width:100%;
        }
        .payroll-flow-svg-wrap{
            flex:1;min-width:300px;border:1px solid color-mix(in srgb,var(--border)80%,transparent);
            border-radius:12px;background:color-mix(in srgb,var(--primary)5%,transparent);
            overflow:hidden;
            min-height:min(76vh,720px)
        }
        .payroll-modal.payroll-flow-modal--wide .payroll-flow-svg-wrap{
            flex:1 1 0;
            min-width:min(100%,280px);
            min-height:0;
            max-height:none;
            overflow:auto;
            display:flex;
            flex-direction:column;
            -webkit-overflow-scrolling:touch;
        }
        .payroll-modal.payroll-flow-modal--wide .payroll-flow-svg-wrap svg{
            display:block;
            width:100%;
            flex:1 1 auto;
            align-self:stretch;
            min-height:clamp(260px,min(42vh,480px),520px);
            height:100%;
            max-height:none;
            box-sizing:border-box;
            font-family:system-ui,sans-serif;touch-action:none;
        }
        .payroll-modal.payroll-flow-modal--wide .payroll-flow-svg-wrap .payflow-load-error{flex-shrink:0}
        .payroll-flow-sidebar{
            flex:1;min-width:240px;max-width:360px;padding:10px;border:1px solid color-mix(in srgb,var(--border)82%,transparent);
            border-radius:12px;background:color-mix(in srgb,var(--card)98%,transparent);
            max-height:min(calc(98vh - 160px),900px);overflow:auto
        }
        .payroll-modal.payroll-flow-modal--wide .payroll-flow-sidebar{
            flex:0 0 clamp(260px,28vw,340px);
            align-self:stretch;
            max-height:none;
            height:auto;
            min-height:0;
            overflow-x:hidden;
            overflow-y:auto;
            position:relative;
            -webkit-overflow-scrolling:touch;
        }
        @media (max-width:780px){
            .payroll-modal.payroll-flow-modal--wide{
                height:auto;
                min-height:min(96vh,min(680px,calc(100vh - 20px)));
                max-height:min(calc(100vh - 16px),98vh);
                overflow-y:auto;
                -webkit-overflow-scrolling:touch;
            }
            .payroll-modal.payroll-flow-modal--wide .payflow-flow-editor-workspace{
                flex-direction:column;
                overflow-y:auto;
                overflow-x:hidden;
                -webkit-overflow-scrolling:touch;
            }
            .payroll-modal.payroll-flow-modal--wide .payroll-flow-svg-wrap{
                flex:1 1 auto;
                min-height:240px;
                max-height:min(45vh,360px);
            }
            .payroll-modal.payroll-flow-modal--wide .payroll-flow-sidebar{
                flex:0 0 auto;
                width:100%;
                max-width:none;
                max-height:min(38vh,320px);
            }
        }
        .payroll-flow-sidebar h4{margin:0 0 8px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
        .payroll-flow-help{margin:0 0 8px;font-size:10px;line-height:1.35;color:var(--muted)}
        .payflow-link{stroke:color-mix(in srgb,var(--border)82%,transparent);stroke-width:1.6px;stroke-linecap:round;fill:none}
        .payflow-link-arrow{stroke:color-mix(in srgb,var(--muted)76%,transparent);stroke-width:1.2px;fill:none}
        .payflow-node{cursor:grab;touch-action:none}
        .payflow-node:active{cursor:grabbing}
        /* Rect/text colors via CSS so theme variables resolve (SVG fill attributes often ignore color-mix) */
        .payflow-node rect{fill:color-mix(in srgb,var(--card)92%,transparent);stroke:color-mix(in srgb,var(--border)80%,transparent)}
        .payflow-node .payflow-t-title{fill:var(--text);font-weight:800}
        .payflow-node .payflow-sub{fill:var(--muted)}
        .payflow-node.selected rect{stroke:color-mix(in srgb,var(--primary)55%,var(--border));stroke-width:2px}
        .payflow-node--bill rect{fill:color-mix(in srgb,#f59e0b 18%,var(--card));stroke:color-mix(in srgb,#d97706 48%,var(--border))}
        .payflow-node--bill .payflow-t-title{fill:color-mix(in srgb,#92400e 80%,var(--text))}
        .payflow-load-error{margin:12px;font-size:12px;color:#b91c1c;padding:10px;border-radius:10px;border:1px solid color-mix(in srgb,#f87171 38%,var(--border));background:color-mix(in srgb,#fef2f2 55%,var(--card))}
        .payflow-open-btn{font-size:10px!important;padding:4px 8px!important;white-space:nowrap}
        .payflow-logic-tools{
            width:100%;flex-basis:100%;flex-shrink:0;margin-bottom:8px;padding:8px 10px 10px;
            border:1px solid color-mix(in srgb,var(--border)76%,transparent);border-radius:12px;
            background:color-mix(in srgb,var(--primary)5%,transparent);
            display:flex;flex-direction:column;gap:8px;
        }
        .payflow-logic-tools__body{display:flex;flex-direction:column;gap:8px}
        .payflow-logic-block{display:none;flex-wrap:wrap;align-items:center;gap:6px 12px;position:relative;overflow:visible}
        .payflow-logic-block.is-visible{display:flex}
        .payflow-logic-block--fallback{display:none}
        .payflow-logic-block--stack{width:100%;flex-direction:column;align-items:flex-start;gap:4px;border-left:3px solid color-mix(in srgb,var(--primary)42%,transparent);padding-left:8px}
        .payflow-logic-hint{width:100%;flex-basis:100%;margin:0;font-size:9px;line-height:1.35;color:var(--muted)}
        /* Tooltip below row so it stays inside overflow boundaries of the modal shell. */
        .payflow-logic-section-pop{
            position:absolute;left:0;top:calc(100% + 4px);bottom:auto;display:flex;align-items:flex-start;gap:8px;padding:8px 10px;
            max-width:min(320px,calc(100vw - 48px));font-size:10px;line-height:1.3;text-align:left;
            background:color-mix(in srgb,var(--card)97%,transparent);
            border:1px solid color-mix(in srgb,var(--border)78%,transparent);border-radius:8px;
            box-shadow:0 6px 20px rgba(0,0,0,.14);
            color:var(--text);opacity:0;visibility:hidden;transform:translateY(-4px);pointer-events:none;
            transition:opacity .14s ease,transform .14s ease,visibility .14s ease;z-index:40;
        }
        .payflow-logic-section-pop > i.fa{flex-shrink:0;margin-top:1px;font-size:14px;color:color-mix(in srgb,var(--primary)72%,var(--muted))}
        .payflow-logic-section-pop__title{font-weight:800;display:block;margin:0 0 2px;font-size:10px;color:var(--text)}
        .payflow-logic-section-pop__desc{margin:0;font-size:9px;font-weight:600;color:var(--muted);line-height:1.35}
        .payflow-logic-block.is-visible:hover .payflow-logic-section-pop,
        .payflow-logic-block.is-visible:focus-within .payflow-logic-section-pop{
            opacity:1;visibility:visible;transform:translateY(0);pointer-events:none;
        }
        .payflow-logic-chips{display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0}
        button.payflow-logic-chip{
            font-family:inherit;font-size:10px;font-weight:700;padding:5px 9px;border-radius:8px;cursor:pointer;
            border:1px solid color-mix(in srgb,var(--border)82%,transparent);
            background:color-mix(in srgb,var(--card)94%,transparent);color:var(--text);line-height:1.2
        }
        button.payflow-logic-chip:hover{background:color-mix(in srgb,var(--primary)14%,transparent);border-color:color-mix(in srgb,var(--primary)42%,var(--border))}
        button.payflow-logic-chip--op{min-width:2rem;text-align:center;font-variant-numeric:tabular-nums}
        button.payflow-logic-chip--snippet{font-weight:650;max-width:100%;text-align:left}
        .payflow-logic-tools--tabbed{padding:0;border-radius:10px;gap:2px}
        .payflow-logic-tab-strip{
            display:flex;flex-wrap:nowrap;align-items:flex-end;gap:4px;margin:0 0 4px;padding:0;
            border-bottom:1px solid color-mix(in srgb,var(--border)72%,transparent);
            overflow-x:auto;overflow-y:hidden;scrollbar-width:thin;-webkit-overflow-scrolling:touch;
            white-space:nowrap;
        }
        .payflow-logic-tab-strip::-webkit-scrollbar{height:4px}
        .payflow-logic-tab-strip::-webkit-scrollbar-thumb{border-radius:4px;background:color-mix(in srgb,var(--border)70%,transparent)}
        .payflow-logic-tab-strip--hidden{display:none!important}
        button.payflow-logic-tab{
            font:inherit;font-size:10px;font-weight:700;line-height:1;white-space:nowrap;margin:0 2px -1px 0;
            padding:6px 10px;border:1px solid transparent;border-bottom:none;border-radius:6px 6px 0 0;
            cursor:pointer;color:color-mix(in srgb,var(--muted)94%,var(--text) 6%);
            background:color-mix(in srgb,var(--card)40%,transparent);
            display:inline-flex;flex-direction:row;align-items:center;justify-content:center;gap:6px;flex-shrink:0;
            vertical-align:bottom;
            -webkit-appearance:none;appearance:none;
        }
        .payflow-logic-tab .payflow-logic-tab__ico{
            flex-shrink:0;font-size:12px;line-height:1;width:1em;text-align:center;color:inherit;opacity:.92;
        }
        .payflow-logic-tab__lbl{flex-shrink:0;line-height:1.15}
        button.payflow-logic-tab:hover{
            background:color-mix(in srgb,var(--primary)14%,transparent);
            color:var(--text);
        }
        button.payflow-logic-tab--active{
            color:color-mix(in srgb,var(--primary)76%,var(--text));
            background:color-mix(in srgb,var(--card)94%,transparent);
            border-color:color-mix(in srgb,var(--border)74%,transparent);
            border-bottom-color:transparent;margin-bottom:-1px;padding-bottom:7px;
        }
        button.payflow-logic-tab:focus-visible{outline:2px solid color-mix(in srgb,var(--primary)45%,transparent);outline-offset:1px}
        .payflow-logic-tools__body--tabbed{display:block}
        .payflow-logic-tabpanel{display:none;margin:0;padding:0;border:none;background:transparent;overflow:visible}
        .payflow-logic-tabpanel--active{display:block;overflow:visible}
        #payflowLogicTools{overflow:visible}
        .payflow-formula-chrome{width:100%;flex-basis:100%;margin-bottom:8px;padding:10px;border:1px solid color-mix(in srgb,var(--border)76%,transparent);
            border-radius:12px;background:color-mix(in srgb,var(--primary)5%,transparent)}
        .payflow-graph-toolbar{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:10px}
        .payflow-graph-toolbar > span{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-right:4px}
        .payflow-root-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:4px}
        .payflow-root-row label{font-size:10px;font-weight:700;color:var(--muted)}
        .payflow-root-row select.payroll-input{max-width:220px;font-size:11px;padding:6px 8px}
        .payflow-toolbar-btn{font-size:10px!important;padding:6px 9px!important}
    </style>

    @if(session('status'))
        <p class="emp-show__flash" role="status" style="max-width:1080px;">{{ session('status') }}</p>
    @endif
    @if($errors->any())
        <div class="emp-show__err" role="alert" style="max-width:1080px;">
            <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="payroll-wrap">
        <section class="payroll-card">
            <div class="payroll-head">
                <div>
                    <h2 class="payroll-title">{{ __('Rule sets') }}</h2>
                    <p class="payroll-sub">{{ __('Create and maintain payroll rules with effective dates. Use this page for EPF, ETF, APIT, and custom formulas.') }}</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" id="payrollRuleSetOpenBtn" class="payroll-btn"><i class="fa fa-plus"></i>{{ __('Add rule set') }}</button>
                    <a href="{{ route('hr.payroll.index') }}" class="payroll-btn"><i class="fa fa-arrow-left"></i>{{ __('Back to payroll') }}</a>
                </div>
            </div>

            <div class="payroll-rule-set-cards" style="margin-top:14px;">
                @forelse($ruleSets as $set)
                    <section class="payroll-rule-set-card">
                        <div class="payroll-rule-set-head">
                            <div>
                                <div class="payroll-rule-set-line">
                                    <span class="payroll-name">{{ $set->name }}</span>
                                    <span class="payroll-rule-set-sep">•</span>
                                    <span class="payroll-rule-set-effective">{{ $set->effective_from?->format('Y-m-d') ?? '—' }} → {{ $set->effective_to?->format('Y-m-d') ?? __('open') }}</span>
                                    <span class="payroll-rule-set-sep">•</span>
                                    <span class="emp-docs-table__meta payroll-col-currency-val">{{ $set->currency }}</span>
                                </div>
                            </div>
                            <div class="payroll-rule-set-right">
                                <div class="payroll-rules-col">{{ $set->rules_count }} {{ __('rules') }}</div>
                                @if($set->is_default)
                                    <span class="payroll-chip payroll-chip--ok">{{ __('Default') }}</span>
                                @else
                                    <span class="payroll-chip">{{ __('Not default') }}</span>
                                @endif
                            </div>
                        </div>

                        <button
                            type="button"
                            class="payroll-btn payroll-add-rule-btn"
                            data-rule-set-id="{{ $set->id }}"
                            data-action-url="{{ route('hr.payroll.rules.store', $set) }}"
                        >
                            <i class="fa fa-plus"></i>{{ __('Add rule') }}
                        </button>

                        <details class="payroll-rule-list">
                            <summary>{{ __('View added rules') }} ({{ $set->rules->count() }})</summary>
                            @forelse($set->rules as $rule)
                                @php
                                    $flowCfg = is_array($rule->config_json) ? $rule->config_json : [];
                                    $flowPayload = json_encode([
                                        'id' => $rule->id,
                                        'code' => $rule->code,
                                        'name' => $rule->name,
                                        'component_type' => $rule->component_type,
                                        'calculation_mode' => $rule->calculation_mode,
                                        'config_json' => $flowCfg,
                                    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS);
                                @endphp
                                <div class="payroll-rule-item">
                                    <div class="payroll-rule-item-head">
                                        <div class="payroll-rule-item-title">
                                            <span class="payroll-rule-code">{{ $rule->code }}</span>
                                            {{ $rule->name }}
                                        </div>
                                        <div class="payroll-rule-tags">
                                            <span class="payroll-rule-tag">{{ ucfirst((string) $rule->component_type) }}</span>
                                            <span class="payroll-rule-tag">{{ ucfirst((string) $rule->calculation_mode) }}</span>
                                            <button
                                                type="button"
                                                class="payroll-btn payflow-open-btn payroll-flow-editor-open-btn"
                                                data-rule-payload="{{ base64_encode((string) $flowPayload) }}"
                                                data-rule-patch-url="{{ route('hr.payroll.rules.update', $rule) }}"
                                            >
                                                <i class="fa fa-diagram-project"></i>{{ __('Flow') }}
                                            </button>
                                        </div>
                                    </div>
                                    @if(!empty($rule->config_json))
                                        @php
                                            $config = is_array($rule->config_json) ? $rule->config_json : [];
                                        @endphp
                                        <div class="payroll-rule-config">
                                            @foreach($config as $configKey => $configValue)
                                                @if($configKey === 'slabs' && is_array($configValue))
                                                    <div class="payroll-rule-config-row">
                                                        <span class="payroll-rule-config-key">{{ __('Slabs') }}</span>
                                                        <span class="payroll-rule-config-slabs">
                                                            @foreach($configValue as $slab)
                                                                @php
                                                                    $from = (float) ($slab['from'] ?? 0);
                                                                    $to = $slab['to'] ?? null;
                                                                    $percent = (float) ($slab['percent'] ?? 0);
                                                                @endphp
                                                                <span class="payroll-rule-config-slab">
                                                                    {{ number_format($from, 0) }} - {{ $to === null ? __('above') : number_format((float) $to, 0) }} : {{ number_format($percent, 2) }}%
                                                                </span>
                                                            @endforeach
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="payroll-rule-config-row">
                                                        <span class="payroll-rule-config-key">{{ ucwords(str_replace('_', ' ', (string) $configKey)) }}</span>
                                                        <span class="payroll-rule-config-value">
                                                            @if(is_array($configValue))
                                                                {{ implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $configValue)) }}
                                                            @else
                                                                {{ is_bool($configValue) ? ($configValue ? __('Yes') : __('No')) : (string) $configValue }}
                                                            @endif
                                                        </span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="payroll-rule-empty">{{ __('No rules added yet.') }}</div>
                            @endforelse
                        </details>
                    </section>
                @empty
                    <p class="muted" style="margin:0;">{{ __('No rule sets yet.') }}</p>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Rule set create modal --}}
    <div id="payrollRuleSetModalOverlay" class="payroll-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="payrollRuleSetModalTitle">
        <div class="payroll-modal">
            <div class="payroll-modal__head">
                <div>
                    <h3 id="payrollRuleSetModalTitle" class="payroll-modal__title">{{ __('Add rule set') }}</h3>
                    <p class="payroll-modal__sub">{{ __('Create a payroll rule set with effective dates and description, then add EPF/ETF/APIT/custom rules.') }}</p>
                </div>
                <button type="button" id="payrollRuleSetModalClose" class="payroll-modal__close" aria-label="{{ __('Close') }}">×</button>
            </div>

            <form method="post" action="{{ route('hr.payroll.rule-sets.store') }}">
                @csrf
                <div class="payroll-modal__form-wrap">
                    <div class="payroll-grid">
                        <div class="payroll-field">
                            <label>{{ __('Rule set name') }}</label>
                            <input type="text" name="name" class="payroll-input" value="{{ old('name') }}" required>
                            <small class="payroll-field-help">{{ __('A clear template name, e.g. Sri Lanka Standard 2026.') }}</small>
                        </div>
                        <div class="payroll-field">
                            <label>{{ __('Currency') }}</label>
                            <input type="text" name="currency" class="payroll-input" value="{{ old('currency', $business->currency ?? 'LKR') }}">
                            <small class="payroll-field-help">{{ __('Payroll currency for this rule set (LKR, USD, etc.).') }}</small>
                        </div>
                        <div class="payroll-field">
                            <label>{{ __('Effective from') }}</label>
                            <input type="date" name="effective_from" class="payroll-input" value="{{ old('effective_from', now()->toDateString()) }}" required>
                            <small class="payroll-field-help">{{ __('Start date from which this rule set is valid.') }}</small>
                        </div>
                        <div class="payroll-field">
                            <label>{{ __('Effective to') }}</label>
                            <input type="date" name="effective_to" class="payroll-input" value="{{ old('effective_to') }}">
                            <small class="payroll-field-help">{{ __('Optional end date; leave empty for open-ended usage.') }}</small>
                        </div>
                        <div class="payroll-field">
                            <label>{{ __('Default') }}</label>
                            <select name="is_default" class="payroll-input"><option value="0" @selected(old('is_default', '0') === '0')>{{ __('No') }}</option><option value="1" @selected(old('is_default') === '1')>{{ __('Yes') }}</option></select>
                            <small class="payroll-field-help">{{ __('Set as default to auto-select this rule set for new cycles.') }}</small>
                        </div>
                        <div class="payroll-field" style="grid-column:1/-1;">
                            <label>{{ __('Description') }}</label>
                            <textarea name="notes" class="payroll-input" rows="3" placeholder="{{ __('Add notes about statutory scope, assumptions, or period applicability...') }}">{{ old('notes') }}</textarea>
                            <small class="payroll-field-help">{{ __('Optional internal notes for this rule set (saved as description).') }}</small>
                        </div>
                    </div>
                </div>

                <div class="payroll-modal__actions">
                    <button type="button" id="payrollRuleSetModalCancel" class="payroll-btn payroll-modal__btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="payroll-btn payroll-modal__btn-primary"><i class="fa fa-plus"></i>{{ __('Create rule set') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Salary/payroll rule modal (single instance) --}}
    <div id="payrollRuleModalOverlay" class="payroll-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="payrollRuleModalTitle">
        <div class="payroll-modal">
            <div class="payroll-modal__head">
                <div>
                    <h3 id="payrollRuleModalTitle" class="payroll-modal__title">{{ __('Add payroll rule') }}</h3>
                    <p class="payroll-modal__sub">{{ __('Define a professional payroll component with clear type, calculation mode, and JSON configuration.') }}</p>
                </div>
                <button type="button" id="payrollRuleModalClose" class="payroll-modal__close" aria-label="{{ __('Close') }}">×</button>
            </div>

            <form id="payrollRuleModalForm" method="post" action="">
                @csrf
                <input type="hidden" name="rule_set_id" id="payrollRuleModalRuleSetId" value="{{ old('rule_set_id') }}">

                <div class="payroll-modal__form-wrap">
                <div class="payroll-rule-form">
                    <div class="payroll-field" style="grid-column:1/-1;">
                        <label>{{ __('Rule code') }}</label>
                        <input type="text" name="code" class="payroll-input" placeholder="EPF_EMPLOYEE" value="{{ old('code') }}" required>
                        <small class="payroll-rule-help">{{ __('Short unique code, uppercase, used in reports and formulas.') }}</small>
                    </div>
                    <div class="payroll-field" style="grid-column:1/-1;">
                        <label>{{ __('Rule name') }}</label>
                        <input type="text" name="name" class="payroll-input" placeholder="{{ __('EPF employee contribution') }}" value="{{ old('name') }}" required>
                        <small class="payroll-rule-help">{{ __('Human friendly label visible on payslips and salary sheet.') }}</small>
                    </div>
                    <div class="payroll-field">
                        <label>{{ __('Component type') }}</label>
                        <select name="component_type" class="payroll-input" required>
                            <option value="earning" @selected(old('component_type') === 'earning')>{{ __('Earning') }}</option>
                            <option value="deduction" @selected(old('component_type') === 'deduction')>{{ __('Deduction') }}</option>
                            <option value="statutory" @selected(old('component_type') === 'statutory')>{{ __('Statutory') }}</option>
                            <option value="overtime" @selected(old('component_type') === 'overtime')>{{ __('Overtime') }}</option>
                            <option value="informational" @selected(old('component_type') === 'informational')>{{ __('Informational (display only)') }}</option>
                            <option value="employer_tracking" @selected(old('component_type') === 'employer_tracking')>{{ __('Employer tracking / cost lines') }}</option>
                        </select>
                        <small class="payroll-rule-help">{{ __('Choose whether this is income, deduction, statutory, or overtime.') }}</small>
                    </div>
                    <div class="payroll-field">
                        <label>{{ __('Calculation mode') }}</label>
                        <select name="calculation_mode" class="payroll-input" required>
                            <option value="fixed" @selected(old('calculation_mode') === 'fixed')>{{ __('Fixed') }}</option>
                            <option value="percentage" @selected(old('calculation_mode') === 'percentage')>{{ __('Percentage') }}</option>
                            <option value="slab" @selected(old('calculation_mode') === 'slab')>{{ __('Slab') }}</option>
                            <option value="formula" @selected(old('calculation_mode') === 'formula')>{{ __('Formula') }}</option>
                        </select>
                        <small class="payroll-rule-help">{{ __('How the amount is calculated (flat value, % of base, tax slabs, or formula).') }}</small>
                    </div>
                    <div class="payroll-field">
                        <label>{{ __('Sort order') }}</label>
                        <input type="number" name="sort_order" class="payroll-input" placeholder="{{ __('Order') }}" value="{{ old('sort_order', 0) }}" min="0">
                        <small class="payroll-rule-help">{{ __('Controls evaluation/display order. Lower numbers run first.') }}</small>
                    </div>
                    <div class="payroll-field" style="grid-column:1/-1;">
                        <label>{{ __('Config JSON') }}</label>
                        <input type="text" name="config_json" class="payroll-input" placeholder='{"amount":1000} or {"base_field":"basic_salary","percent":8}' value="{{ old('config_json') }}">
                        <small class="payroll-rule-help">{{ __('Advanced settings as JSON. For example fixed amount, percentage base field, or slab definitions.') }}</small>
                    </div>
                    <div class="payroll-field" style="grid-column:1/-1;">
                        <button type="button" id="payrollRuleFlowDesignerBtn" class="payroll-btn" style="width:100%;justify-content:center;">
                            <i class="fa fa-diagram-project"></i>{{ __('Open flow designer (uses calculation mode above)') }}
                        </button>
                        <small id="payrollRuleFlowDesignerHelpDefault" class="payroll-rule-help">{{ __('For non-formula modes you can draft the flow here; config JSON updates when you apply from the designer.') }}</small>
                        <small id="payrollRuleFlowDesignerHelpFormula" class="payroll-rule-help" hidden style="color:color-mix(in srgb,var(--primary)55%,var(--muted));font-weight:650;">{{ __('Formula: save this rule first. After it appears in the list, open the flow editor from that rule to build the graph.') }}</small>
                    </div>
                </div>
                </div>

                <div class="payroll-modal__actions">
                    <button type="button" id="payrollRuleModalCancel" class="payroll-btn payroll-modal__btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="payroll-btn payroll-modal__btn-primary">
                        <i class="fa fa-plus"></i>{{ __('Save rule') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="payflowEditorOverlay" class="payroll-modal-overlay" aria-hidden="true">
        <div class="payroll-modal payroll-flow-modal--wide">
            @php
                $payflowContextFields = [
                    ['basic_salary', __('Salary — basic (monthly)')],
                    ['gross_salary', __('Salary — gross on record')],
                    ['gross_earnings', __('Running total — earnings + overtime processed so far')],
                    ['taxable_earnings', __('Running taxable portion of earnings')],
                    ['total_deductions', __('Running deductions total so far')],
                    ['overtime_hours', __('Overtime hours (cycle input)')],
                    ['overtime_rate', __('Overtime rate per hour')],
                    ['attendance_days', __('Attendance days (input)')],
                    ['working_days', __('Working days in period (input)')],
                    ['leave_without_pay_days', __('LWP days (input)')],
                    ['standard_days', __('Payroll standard days (settings, e.g. 26)')],
                    ['actual_days', __('Effective actual days (attendance, capped to standard)')],
                    ['proration_ratio', __('actual ÷ standard (for allowance pro-rating)')],
                    ['no_pay_amount', __('Unpaid basic fraction (reference)')],
                    ['epf_salary', __('EPF salary base (pro-rated basic for the month)')],
                    ['medical_allowance_monthly', __('Medical allowance monthly (from employee allowances)')],
                    ['cola_allowance_monthly', __('COLA monthly (from employee allowances)')],
                    ['attendance_allowance_monthly', __('Attendance allowance monthly')],
                    ['performance_allowance_monthly', __('Performance allowance monthly')],
                    ['salary_advance', __('Salary advance deduction (cycle input)')],
                    ['stamp_duty', __('Stamp duty (cycle input)')],
                ];
                $payflowFormulaSnippets = [
                    ['( taxable_earnings * 0.05 )', __('Example: 5% of taxable')],
                    ['( basic_salary * 8 / 100 )', __('Example: 8% of basic')],
                    ['( gross_earnings - total_deductions )', __('Example: net-style difference')],
                ];
            @endphp
            <div class="payflow-flow-modal-top">
                <div class="payroll-modal__head">
                    <div>
                        <h3 id="payflowModalTitle" class="payroll-modal__title">{{ __('Rule flow') }}</h3>
                        <p class="payroll-modal__sub" id="payflowModalSub"></p>
                    </div>
                    <button type="button" id="payflowModalClose" class="payroll-modal__close" aria-label="{{ __('Close') }}">×</button>
                </div>

                <div id="payflowLogicTools" class="payflow-logic-tools payflow-logic-tools--tabbed" hidden role="toolbar" aria-label="{{ __('Logic tools') }}">
                    <div id="payflowLogicTabStrip" class="payflow-logic-tab-strip payflow-logic-tab-strip--hidden" role="tablist">
                        <button type="button" id="payflowLogicTabPrimary" class="payflow-logic-tab payflow-logic-tab--active" role="tab" tabindex="0" aria-selected="true" aria-controls="payflowLogicTabPanelPrimary" data-logic-tab="primary">
                            <i id="payflowLogicTabPrimaryIcon" class="fa fa-th-list payflow-logic-tab__ico" aria-hidden="true"></i><span id="payflowLogicTabPrimaryLbl" class="payflow-logic-tab__lbl">{{ __('Fields') }}</span>
                        </button>
                        <button type="button" id="payflowLogicTabSecondary" class="payflow-logic-tab" role="tab" tabindex="-1" aria-selected="false" aria-controls="payflowLogicTabPanelSecondary" data-logic-tab="secondary">
                            <i id="payflowLogicTabSecondaryIcon" class="fa fa-code payflow-logic-tab__ico" aria-hidden="true"></i><span id="payflowLogicTabSecondaryLbl" class="payflow-logic-tab__lbl">{{ __('Formula & examples') }}</span>
                        </button>
                    </div>
                    <div class="payflow-logic-tools__body payflow-logic-tools__body--tabbed">
                        <div id="payflowLogicTabPanelPrimary" class="payflow-logic-tabpanel payflow-logic-tabpanel--active" role="tabpanel" aria-labelledby="payflowLogicTabPrimary">
                            <div class="payflow-logic-block payflow-logic-block--stack" data-show-for="fixed" aria-label="{{ __('Fixed') }}">
                                <div class="payflow-logic-section-pop" aria-hidden="true">
                                    <i class="fa fa-thumbtack" aria-hidden="true"></i>
                                    <div>
                                        <span class="payflow-logic-section-pop__title">{{ __('Fixed') }}</span>
                                        <p class="payflow-logic-section-pop__desc">{{ __('Enter amount on the Amount node; other modes use payroll fields.') }}</p>
                                    </div>
                                </div>
                                <p class="payflow-logic-hint">{{ __('Enter amount on the Amount node. Use Formula / % / Slab modes for payroll fields.') }}</p>
                            </div>

                            <div class="payflow-logic-block" data-show-for="percentage slab formula" aria-label="{{ __('Fields') }}">
                                <div class="payflow-logic-section-pop" aria-hidden="true">
                                    <i class="fa fa-file-lines" aria-hidden="true"></i>
                                    <div>
                                        <span class="payflow-logic-section-pop__title">{{ __('Fields') }}</span>
                                        <p class="payflow-logic-section-pop__desc">{{ __('Insert at cursor / base / input fields') }}</p>
                                    </div>
                                </div>
                                <div class="payflow-logic-chips">
                                    @foreach($payflowContextFields as [$pfKey, $pfHint])
                                        <button type="button" class="payflow-logic-chip" title="{{ $pfHint }}" data-payflow-insert="{{ $pfKey }}">{{ $pfKey }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="payflow-logic-block payflow-logic-block--stack payflow-logic-block--fallback" aria-label="{{ __('Reference') }}">
                                <div class="payflow-logic-section-pop" aria-hidden="true">
                                    <i class="fa fa-question-circle" aria-hidden="true"></i>
                                    <div>
                                        <span class="payflow-logic-section-pop__title">{{ __('Reference') }}</span>
                                        <p class="payflow-logic-section-pop__desc">{{ __('Choose a calculation mode in rule settings.') }}</p>
                                    </div>
                                </div>
                                <p class="payflow-logic-hint">{{ __('Use Formula for math on context keys, or pick Fixed / Percentage / Slab in rule settings.') }}</p>
                            </div>
                        </div>
                        <div id="payflowLogicTabPanelSecondary" class="payflow-logic-tabpanel" role="tabpanel" aria-labelledby="payflowLogicTabSecondary">
                            <div class="payflow-logic-block" data-show-for="formula" aria-label="{{ __('Operators') }}">
                                <div class="payflow-logic-section-pop" aria-hidden="true">
                                    <i class="fa fa-calculator" aria-hidden="true"></i>
                                    <div>
                                        <span class="payflow-logic-section-pop__title">{{ __('Ops') }}</span>
                                        <p class="payflow-logic-section-pop__desc">{{ __('Insert operators and parentheses at the cursor.') }}</p>
                                    </div>
                                </div>
                                <div class="payflow-logic-chips">
                                    @foreach(['+', '-', '*', '/', '(', ')'] as $pfOp)
                                        <button type="button" class="payflow-logic-chip payflow-logic-chip--op" data-payflow-insert="{{ $pfOp }}">{{ $pfOp }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="payflow-logic-block payflow-logic-block--stack" data-show-for="formula" aria-label="{{ __('Graph') }}">
                                <div class="payflow-logic-section-pop" aria-hidden="true">
                                    <i class="fa fa-sitemap" aria-hidden="true"></i>
                                    <div>
                                        <span class="payflow-logic-section-pop__title">{{ __('Graph') }}</span>
                                        <p class="payflow-logic-section-pop__desc">{{ __('Compare, If/else branches, and output node.') }}</p>
                                    </div>
                                </div>
                                <p class="payflow-logic-hint">{{ __('Compare → 0/1, If / else picks branch. Set Output to the graph result node.') }}</p>
                            </div>

                            <div class="payflow-logic-block" data-show-for="formula" aria-label="{{ __('Examples') }}">
                                <div class="payflow-logic-section-pop" aria-hidden="true">
                                    <i class="fa fa-lightbulb" aria-hidden="true"></i>
                                    <div>
                                        <span class="payflow-logic-section-pop__title">{{ __('Examples') }}</span>
                                        <p class="payflow-logic-section-pop__desc">{{ __('Sample expressions you can insert and edit.') }}</p>
                                    </div>
                                </div>
                                <div class="payflow-logic-chips">
                                    @foreach($payflowFormulaSnippets as [$pfSnip, $pfLabel])
                                        <button type="button" class="payflow-logic-chip payflow-logic-chip--snippet" data-payflow-insert="{{ $pfSnip }}" title="{{ $pfLabel }}">{{ $pfLabel }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="payflow-logic-block payflow-logic-block--stack" data-show-for="percentage" aria-label="{{ __('Percentage') }}">
                                <div class="payflow-logic-section-pop" aria-hidden="true">
                                    <i class="fa fa-percent" aria-hidden="true"></i>
                                    <div>
                                        <span class="payflow-logic-section-pop__title">{{ __('Percentage') }}</span>
                                        <p class="payflow-logic-section-pop__desc">{{ __('Base field vs payroll context.') }}</p>
                                    </div>
                                </div>
                                <p class="payflow-logic-hint">{{ __('Percent uses the Base field vs payroll context.') }}</p>
                            </div>

                            <div class="payflow-logic-block payflow-logic-block--stack" data-show-for="slab" aria-label="{{ __('Slabs') }}">
                                <div class="payflow-logic-section-pop" aria-hidden="true">
                                    <i class="fa fa-bars" aria-hidden="true"></i>
                                    <div>
                                        <span class="payflow-logic-section-pop__title">{{ __('Slabs') }}</span>
                                        <p class="payflow-logic-section-pop__desc">{{ __('Tiered bands with percent and optional fixed add-on.') }}</p>
                                    </div>
                                </div>
                                <p class="payflow-logic-hint">{{ __('Bands by From; optional To; percent + optional fixed add-on per band.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form id="payflowSaveForm" method="post" action="">
                @csrf
                @method('PATCH')
                <input type="hidden" name="config_json" id="payflowConfigJsonInput" value="">

                <div class="payroll-flow-editor-layout">
                    <div id="payflowFormulaChrome" class="payflow-formula-chrome" style="display:none;">
                        <div class="payflow-graph-toolbar">
                            <span>{{ __('Graph') }}</span>
                            <button type="button" class="payroll-btn payflow-toolbar-btn" id="payflowAddFvContext">{{ __('+ Field') }}</button>
                            <button type="button" class="payroll-btn payflow-toolbar-btn" id="payflowAddFvConst">{{ __('+ Number') }}</button>
                            <button type="button" class="payroll-btn payflow-toolbar-btn" id="payflowAddFvBin">{{ __('+ Math') }}</button>
                            <button type="button" class="payroll-btn payflow-toolbar-btn" id="payflowAddFvCmp">{{ __('+ Compare') }}</button>
                            <button type="button" class="payroll-btn payflow-toolbar-btn" id="payflowAddFvCond">{{ __('+ If / else') }}</button>
                            <button type="button" class="payroll-btn payflow-toolbar-btn" id="payflowAddFvBill">{{ __('+ Bill') }}</button>
                            <button type="button" class="payroll-btn payroll-modal__btn-secondary payflow-toolbar-btn" id="payflowFvDeleteNode">{{ __('Delete selected') }}</button>
                        </div>
                        <div class="payflow-root-row">
                            <label for="payflowFormulaRoot">{{ __('Output node') }}</label>
                            <select id="payflowFormulaRoot" class="payroll-input"></select>
                            <span class="payroll-flow-help" style="margin:0;">{{ __('The graph result is this node. Comparisons output 1 (true) or 0 (false); “If / else” uses that test.') }}</span>
                        </div>
                    </div>
                    <div class="payflow-flow-editor-workspace">
                        <div class="payroll-flow-svg-wrap">
                            <p id="payflowLoadError" class="payflow-load-error" style="display:none;" role="alert"></p>
                            <svg id="payflowSvg" role="img" aria-label="{{ __('Flow diagram') }}"></svg>
                        </div>
                        <aside class="payroll-flow-sidebar">
                            <h4>{{ __('Selection') }}</h4>
                            <div id="payflowSidebarPlaceholder" class="payroll-flow-help">{{ __('Click a node in the diagram to edit its settings.') }}</div>
                            <div id="payflowSidebarBody" style="display:none;"></div>
                            <div id="payflowSlabToolbar" style="display:none;margin-top:10px;padding-top:10px;border-top:1px solid color-mix(in srgb,var(--border)76%,transparent);">
                                <button type="button" class="payroll-btn" id="payflowAddSlabBtn" style="width:100%;justify-content:center;">
                                    <i class="fa fa-plus"></i>{{ __('Add slab band') }}
                                </button>
                                <button type="button" class="payroll-btn payroll-modal__btn-secondary" id="payflowRemoveSlabBtn" style="width:100%;justify-content:center;margin-top:6px;">
                                    <i class="fa fa-minus"></i>{{ __('Remove selected slab') }}
                                </button>
                            </div>
                        </aside>
                    </div>
                </div>

                <div class="payroll-modal__actions">
                    <button type="button" id="payflowCancelBtn" class="payroll-btn payroll-modal__btn-secondary">{{ __('Cancel') }}</button>
                    <button type="button" id="payflowApplyDraftBtn" class="payroll-btn payroll-modal__btn-primary" style="display:none;">
                        <i class="fa fa-arrow-down"></i>{{ __('Apply to new rule') }}
                    </button>
                    <button type="submit" id="payflowSubmitPatchBtn" class="payroll-btn payroll-modal__btn-primary">
                        <i class="fa fa-floppy-disk"></i>{{ __('Save configuration') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="{{ asset('vendor/d3/d3.v7.min.js') }}"></script>
    <script>
        (function () {
            const flowOverlay = document.getElementById('payflowEditorOverlay');
            const flowForm = document.getElementById('payflowSaveForm');
            const flowSvg = document.getElementById('payflowSvg');
            const flowLoadError = document.getElementById('payflowLoadError');
            const flowTitle = document.getElementById('payflowModalTitle');
            const flowSub = document.getElementById('payflowModalSub');
            const flowConfigInput = document.getElementById('payflowConfigJsonInput');
            const flowClose = document.getElementById('payflowModalClose');
            const flowCancel = document.getElementById('payflowCancelBtn');
            const flowSidebarPh = document.getElementById('payflowSidebarPlaceholder');
            const flowSidebarBody = document.getElementById('payflowSidebarBody');
            const flowSlabToolbar = document.getElementById('payflowSlabToolbar');
            const flowAddSlabBtn = document.getElementById('payflowAddSlabBtn');
            const flowRemoveSlabBtn = document.getElementById('payflowRemoveSlabBtn');
            const flowLogicToolsRoot = document.getElementById('payflowLogicTools');
            const payflowLogicTabStrip = document.getElementById('payflowLogicTabStrip');
            const payflowLogicTabPrimary = document.getElementById('payflowLogicTabPrimary');
            const payflowLogicTabSecondary = document.getElementById('payflowLogicTabSecondary');
            const payflowLogicPanelPrimary = document.getElementById('payflowLogicTabPanelPrimary');
            const payflowLogicPanelSecondary = document.getElementById('payflowLogicTabPanelSecondary');
            const PAYFLOW_LOGIC_UI = {
                primaryKeys: {!! json_encode(__('Fields')) !!},
                primaryGuide: {!! json_encode(__('Guide')) !!},
                secondaryFormula: {!! json_encode(__('Formula & examples')) !!},
                secondaryPercentage: {!! json_encode(__('Percentage')) !!},
                secondarySlab: {!! json_encode(__('Slabs')) !!},
            };
            const payflowLogicTabPrimaryLbl = document.getElementById('payflowLogicTabPrimaryLbl');
            const payflowLogicTabPrimaryIcon = document.getElementById('payflowLogicTabPrimaryIcon');
            const payflowLogicTabSecondaryLbl = document.getElementById('payflowLogicTabSecondaryLbl');
            const payflowLogicTabSecondaryIcon = document.getElementById('payflowLogicTabSecondaryIcon');
            const payflowFormulaChrome = document.getElementById('payflowFormulaChrome');
            const payflowFormulaRootSel = document.getElementById('payflowFormulaRoot');
            const payflowApplyDraftBtn = document.getElementById('payflowApplyDraftBtn');
            const payflowSubmitPatchBtn = document.getElementById('payflowSubmitPatchBtn');
            const payrollRuleFlowDesignerBtn = document.getElementById('payrollRuleFlowDesignerBtn');
            const payrollRuleModalForm = document.getElementById('payrollRuleModalForm');
            const payflowNeedFieldMsg = {!! json_encode(__('Select a formula or field node first, or focus a text field in the sidebar.')) !!};
            let flowFvSeq = 0;
            let flowState = null;
            let flowSim = null;
            let flowSelectedId = null;
            let flowZoom = null;

            const NODE_W = 168;
            const NODE_H = 44;

            function setLogicToolTab(which) {
                if (!payflowLogicTabPrimary || !payflowLogicTabSecondary || !payflowLogicPanelPrimary || !payflowLogicPanelSecondary) return;
                var isPri = which === 'primary';
                payflowLogicTabPrimary.classList.toggle('payflow-logic-tab--active', isPri);
                payflowLogicTabSecondary.classList.toggle('payflow-logic-tab--active', !isPri);
                payflowLogicTabPrimary.setAttribute('aria-selected', isPri ? 'true' : 'false');
                payflowLogicTabSecondary.setAttribute('aria-selected', (!isPri) ? 'true' : 'false');
                payflowLogicTabPrimary.tabIndex = isPri ? 0 : -1;
                payflowLogicTabSecondary.tabIndex = (!isPri) ? 0 : -1;
                payflowLogicPanelPrimary.classList.toggle('payflow-logic-tabpanel--active', isPri);
                payflowLogicPanelSecondary.classList.toggle('payflow-logic-tabpanel--active', !isPri);
            }

            function refreshLogicToolTabs(mode, matched) {
                if (!payflowLogicTabStrip || !payflowLogicTabPrimary || !payflowLogicTabSecondary || !payflowLogicPanelSecondary) return;
                var nSecondary = payflowLogicPanelSecondary.querySelectorAll('.payflow-logic-block.is-visible').length;
                var showSecondaryStrip = matched > 0 && nSecondary > 0;
                if (payflowLogicTabPrimaryLbl) {
                    payflowLogicTabPrimaryLbl.textContent = mode === 'fixed' ? PAYFLOW_LOGIC_UI.primaryGuide : PAYFLOW_LOGIC_UI.primaryKeys;
                }
                if (payflowLogicTabPrimaryIcon) {
                    payflowLogicTabPrimaryIcon.className = 'fa ' + (mode === 'fixed' ? 'fa-info-circle' : 'fa-th-list') + ' payflow-logic-tab__ico';
                }
                if (payflowLogicTabSecondaryLbl) {
                    payflowLogicTabSecondaryLbl.textContent = mode === 'formula'
                        ? PAYFLOW_LOGIC_UI.secondaryFormula
                        : (mode === 'percentage' ? PAYFLOW_LOGIC_UI.secondaryPercentage : PAYFLOW_LOGIC_UI.secondarySlab);
                }
                if (payflowLogicTabSecondaryIcon) {
                    var secIc = mode === 'formula' ? 'fa-code' : (mode === 'percentage' ? 'fa-percent' : 'fa-bars');
                    payflowLogicTabSecondaryIcon.className = 'fa ' + secIc + ' payflow-logic-tab__ico';
                }
                if (mode === 'fixed' || !showSecondaryStrip) {
                    payflowLogicTabStrip.classList.add('payflow-logic-tab-strip--hidden');
                    payflowLogicTabSecondary.hidden = true;
                    setLogicToolTab('primary');
                } else {
                    payflowLogicTabStrip.classList.remove('payflow-logic-tab-strip--hidden');
                    payflowLogicTabSecondary.hidden = false;
                    setLogicToolTab('primary');
                }
            }

            function updateLogicTools(mode) {
                if (!flowLogicToolsRoot) return;
                var matched = 0;
                flowLogicToolsRoot.querySelectorAll('.payflow-logic-block[data-show-for]').forEach(function (bl) {
                    var modes = (bl.getAttribute('data-show-for') || '').trim().split(/\s+/).filter(Boolean);
                    var ok = modes.indexOf(mode) !== -1;
                    bl.classList.toggle('is-visible', ok);
                    if (ok) matched++;
                });
                var fb = flowLogicToolsRoot.querySelector('.payflow-logic-block--fallback');
                if (fb) fb.classList.toggle('is-visible', matched === 0);
                refreshLogicToolTabs(mode, matched);
            }

            function insertAtCursor(el, text) {
                if (!el || el.value === undefined) return;
                var start = el.selectionStart != null ? el.selectionStart : el.value.length;
                var end = el.selectionEnd != null ? el.selectionEnd : start;
                var v = String(el.value);
                el.value = v.slice(0, start) + text + v.slice(end);
                var np = start + text.length;
                if (typeof el.setSelectionRange === 'function') el.setSelectionRange(np, np);
                el.focus();
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }

            function getPayflowEditableTarget() {
                var ae = document.activeElement;
                if (ae && flowSidebarBody && ae.closest && ae.closest('#payflowSidebarBody')) {
                    if (ae.matches('textarea.pf-side-ta')) return ae;
                    if (ae.matches('input.pf-side-inp[data-k="base_field"], input.pf-side-inp[data-k="input_field"]')) return ae;
                }
                if (!flowSidebarBody || !flowState) return null;
                if (flowState.mode === 'formula') {
                    return flowSidebarBody.querySelector('textarea.pf-side-ta');
                }
                if (flowState.mode === 'percentage') {
                    return flowSidebarBody.querySelector('input.pf-side-inp[data-k="base_field"]');
                }
                if (flowState.mode === 'slab') {
                    return flowSidebarBody.querySelector('input.pf-side-inp[data-k="input_field"]');
                }
                return flowSidebarBody.querySelector('textarea.pf-side-ta, input.pf-side-inp[data-k="base_field"], input.pf-side-inp[data-k="input_field"]');
            }

            function insertPayflowLogic(raw) {
                if (!raw || !flowSidebarBody) return;
                var el = getPayflowEditableTarget();
                if (!el) {
                    window.alert(payflowNeedFieldMsg);
                    return;
                }
                var token = String(raw).trim();
                if (el.matches('input.pf-side-inp[data-k="base_field"], input.pf-side-inp[data-k="input_field"]')) {
                    if (/^[A-Za-z_][A-Za-z0-9_]*$/.test(token)) {
                        el.value = token;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.focus();
                        return;
                    }
                }
                var text = raw;
                if (el.tagName === 'TEXTAREA') {
                    var pos = el.selectionStart != null ? el.selectionStart : el.value.length;
                    var ch = pos > 0 ? el.value.charAt(pos - 1) : '';
                    if (pos > 0 && ch && !/\s/.test(ch) && /[A-Za-z0-9_)]/.test(ch) && /^[A-Za-z_(]/.test(raw)) {
                        text = ' ' + raw;
                    }
                }
                insertAtCursor(el, text);
            }

            if (flowLogicToolsRoot) {
                flowLogicToolsRoot.addEventListener('click', function (ev) {
                    var lt = ev.target.closest('button.payflow-logic-tab[data-logic-tab]');
                    if (lt && flowLogicToolsRoot.contains(lt)) {
                        var which = lt.getAttribute('data-logic-tab');
                        if (which === 'primary' || which === 'secondary') setLogicToolTab(which);
                        ev.preventDefault();
                        return;
                    }
                    var btn = ev.target.closest('button[data-payflow-insert]');
                    if (!btn || !flowLogicToolsRoot.contains(btn)) return;
                    ev.preventDefault();
                    var tok = btn.getAttribute('data-payflow-insert');
                    if (tok) insertPayflowLogic(tok);
                });
            }

            function decodePayload(btn) {
                const b64 = btn.getAttribute('data-rule-payload') || '';
                const bin = atob(b64);
                const bytes = new Uint8Array(bin.length);
                for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
                const json = new TextDecoder('utf-8').decode(bytes);
                return JSON.parse(json);
            }

            function ensureConfig(mode, cfg) {
                const c = cfg && typeof cfg === 'object' ? Object.assign({}, cfg) : {};
                if (mode === 'fixed') {
                    if (c.amount === undefined) c.amount = 0;
                } else if (mode === 'percentage') {
                    if (!c.base_field) c.base_field = 'basic_salary';
                    if (c.percent === undefined) c.percent = 0;
                } else if (mode === 'slab') {
                    if (!c.input_field) c.input_field = 'taxable_earnings';
                    if (!Array.isArray(c.slabs) || c.slabs.length === 0) {
                        c.slabs = [{ from: 0, to: null, percent: 0, fixed: 0 }];
                    }
                } else if (mode === 'formula') {
                    if (c.formula === undefined) c.formula = '';
                }
                return c;
            }

            function nextFvNodeId() {
                flowFvSeq += 1;
                return 'n_' + Date.now().toString(36) + '_' + flowFvSeq;
            }

            function hasFormulaFlow(cfg) {
                var fv = cfg && cfg.flow_v1;
                var nodes = fv && fv.nodes && typeof fv.nodes === 'object' ? fv.nodes : null;
                return !!(fv && fv.root && nodes && Object.keys(nodes).length > 0);
            }

            function normalizeFormulaFlow(config) {
                if (!hasFormulaFlow(config)) return;
                if (!config.flow_v1.version) config.flow_v1.version = 1;
                var nodes = config.flow_v1.nodes || {};
                var rk = config.flow_v1.root;
                if (!rk || !nodes[rk]) {
                    var ks = Object.keys(nodes);
                    if (ks.length) config.flow_v1.root = ks[0];
                }
            }

            function seedFormulaFlowV1(config) {
                var id = nextFvNodeId();
                config.flow_v1 = { version: 1, root: id, nodes: {} };
                config.flow_v1.nodes[id] = { type: 'constant', value: 0 };
                config.formula = '';
            }

            function fvStripRefs(nodes, deletedId) {
                Object.keys(nodes).forEach(function (k) {
                    var nd = nodes[k];
                    if (!nd || typeof nd !== 'object') return;
                    ['left', 'right', 'test', 'then'].forEach(function (key) {
                        if ((nd[key] || '') === deletedId) nd[key] = '';
                    });
                    if ((nd['else'] || '') === deletedId) nd['else'] = '';
                });
            }

            function buildFormulaFlowGraph(cfg) {
                var fv = cfg.flow_v1;
                var nodeMap = (fv && fv.nodes) ? fv.nodes : {};
                var nodes = [];
                var links = [];

                function subFor(data) {
                    if (!data) return '';
                    var t = data.type || '';
                    if (t === 'context') return String(data.field || '');
                    if (t === 'constant') return String(data.value ?? 0);
                    if (t === 'binary') return (data.op || '?') + ' (…)';
                    if (t === 'compare') return (data.op || '?') + ' ?';
                    if (t === 'cond') return 'if / else';
                    if (t === 'bill') return (data.scope === 'business' ? 'biz' : 'emp') + ' · ' + (data.metric || 'overdue_total').replace(/_/g, ' ');
                    return t;
                }

                Object.keys(nodeMap).forEach(function (fid) {
                    var data = nodeMap[fid];
                    if (!data || typeof data !== 'object') return;
                    var title = '';
                    if (data.type === 'context') title = '{{ __('Field') }}';
                    else if (data.type === 'constant') title = '{{ __('Number') }}';
                    else if (data.type === 'binary') title = '{{ __('Math') }}';
                    else if (data.type === 'compare') title = '{{ __('Compare') }}';
                    else if (data.type === 'cond') title = '{{ __('Condition') }}';
                    else if (data.type === 'bill') title = '{{ __('Bill') }}';
                    else title = String(data.type || '');

                    nodes.push({
                        id: fid,
                        role: 'fv',
                        flowType: data.type,
                        title: title,
                        subtitle: subFor(data),
                        flowData: data,
                    });
                });

                function linkIf(from, to) {
                    if (from && to && nodeMap[from] && nodeMap[to]) {
                        links.push({ source: from, target: to });
                    }
                }

                Object.keys(nodeMap).forEach(function (fid) {
                    var data = nodeMap[fid];
                    if (!data) return;
                    if (data.type === 'binary' || data.type === 'compare') {
                        linkIf(data.left, fid);
                        linkIf(data.right, fid);
                    }
                    if (data.type === 'cond') {
                        linkIf(data.test, fid);
                        linkIf(data.then, fid);
                        linkIf(data['else'], fid);
                    }
                });

                var outId = fv.root;
                var OUT_SINK = 'pf_flow_out_sink';
                if (outId && nodeMap[outId]) {
                    nodes.push({ id: OUT_SINK, role: 'output', title: '{{ __('Output') }}', subtitle: '{{ __('Final amount') }}' });
                    links.push({ source: outId, target: OUT_SINK });
                }

                return { nodes: nodes, links: links };
            }

            function syncFormulaChrome() {
                if (!payflowFormulaChrome) return;
                var show = !!(flowState && flowState.mode === 'formula' && hasFormulaFlow(flowState.config));
                payflowFormulaChrome.style.display = show ? 'block' : 'none';
                if (!show || !payflowFormulaRootSel || !flowState.config.flow_v1) return;
                var nodes = flowState.config.flow_v1.nodes || {};
                var root = flowState.config.flow_v1.root || '';
                var keys = Object.keys(nodes).sort();
                payflowFormulaRootSel.innerHTML = '';
                keys.forEach(function (k) {
                    var opt = document.createElement('option');
                    opt.value = k;
                    opt.textContent = k + ' (' + (nodes[k].type || '') + ')';
                    if (k === root) opt.selected = true;
                    payflowFormulaRootSel.appendChild(opt);
                });
                payflowFormulaRootSel.value = root || (keys[0] || '');
            }

            payflowFormulaRootSel && payflowFormulaRootSel.addEventListener('change', function () {
                if (!flowState || !flowState.config.flow_v1) return;
                flowState.config.flow_v1.root = payflowFormulaRootSel.value || flowState.config.flow_v1.root;
                var keep = flowSelectedId;
                flowState.graph = buildGraph(flowState.mode, flowState.config);
                renderFlow();
                if (keep && flowState.graph.nodes.some(function (n) { return n.id === keep; })) selectNode(keep);
            });

            function rebuildFormulaGraphFromConfig(preserveSel) {
                if (!flowState || flowState.mode !== 'formula' || !hasFormulaFlow(flowState.config)) return;
                normalizeFormulaFlow(flowState.config);
                var keep = preserveSel !== false ? flowSelectedId : null;
                flowState.graph = buildGraph('formula', flowState.config);
                renderFlow();
                if (keep && flowState.graph.nodes.some(function (n) { return n.id === keep; })) selectNode(keep);
            }

            function buildGraph(mode, cfg) {
                const nodes = [];
                const links = [];
                let seq = 0;
                const nid = function () { return 'pf_' + (seq++); };

                if (mode === 'fixed') {
                    const a = { id: nid(), role: 'amount', title: 'Amount', subtitle: String(cfg.amount) };
                    const o = { id: nid(), role: 'output', title: 'Output', subtitle: 'Fixed pay component' };
                    nodes.push(a, o);
                    links.push({ source: a.id, target: o.id });
                } else if (mode === 'percentage') {
                    const b = { id: nid(), role: 'base_field', title: 'Base field', subtitle: String(cfg.base_field) };
                    const p = { id: nid(), role: 'percent', title: 'Percent', subtitle: String(cfg.percent) + '%' };
                    const o = { id: nid(), role: 'output', title: 'Output', subtitle: '% of base' };
                    nodes.push(b, p, o);
                    links.push({ source: b.id, target: p.id }, { source: p.id, target: o.id });
                } else if (mode === 'slab') {
                    const inp = { id: nid(), role: 'input_field', title: 'Input field', subtitle: String(cfg.input_field) };
                    nodes.push(inp);
                    let prev = inp;
                    cfg.slabs.forEach(function (s, i) {
                        const sn = {
                            id: nid(),
                            role: 'slab',
                            slabIndex: i,
                            title: 'Slab ' + (i + 1),
                            subtitle: slabSubtitle(s),
                        };
                        nodes.push(sn);
                        links.push({ source: prev.id, target: sn.id });
                        prev = sn;
                    });
                    const o = { id: nid(), role: 'output', title: 'Output', subtitle: 'Slab total' };
                    nodes.push(o);
                    links.push({ source: prev.id, target: o.id });
                } else if (mode === 'formula') {
                    if (hasFormulaFlow(cfg)) {
                        normalizeFormulaFlow(cfg);
                        return buildFormulaFlowGraph(cfg);
                    }
                    const f = { id: nid(), role: 'formula', title: '{{ __('Formula') }}', subtitle: truncate(String(cfg.formula || ''), 42) };
                    const o = { id: nid(), role: 'output', title: '{{ __('Output') }}', subtitle: '{{ __('Computed') }}' };
                    nodes.push(f, o);
                    links.push({ source: f.id, target: o.id });
                }

                return { nodes: nodes, links: links };
            }

            function slabSubtitle(s) {
                const fr = Number(s.from) || 0;
                const to = s.to === null || s.to === undefined || s.to === '' ? '∞' : String(s.to);
                const pc = Number(s.percent) || 0;
                return fr + ' → ' + to + ' @ ' + pc + '%';
            }

            function truncate(s, n) {
                if (s.length <= n) return s;
                return s.slice(0, n - 1) + '…';
            }

            function refreshNodeSubtitles() {
                if (!flowState) return;
                const cfg = flowState.config;
                flowState.graph.nodes.forEach(function (n) {
                    if (n.role === 'amount') n.subtitle = String(cfg.amount);
                    else if (n.role === 'base_field') n.subtitle = String(cfg.base_field);
                    else if (n.role === 'percent') n.subtitle = String(cfg.percent) + '%';
                    else if (n.role === 'input_field') n.subtitle = String(cfg.input_field);
                    else if (n.role === 'slab' && cfg.slabs && cfg.slabs[n.slabIndex]) n.subtitle = slabSubtitle(cfg.slabs[n.slabIndex]);
                    else if (n.role === 'formula') n.subtitle = truncate(String(cfg.formula || ''), 42);
                    else if (n.flowType && n.flowData) {
                        var d = n.flowData;
                        if (n.flowType === 'context') n.subtitle = String(d.field || '');
                        else if (n.flowType === 'constant') n.subtitle = String(d.value ?? 0);
                        else if (n.flowType === 'binary') n.subtitle = (d.op || '?') + ' (…)';
                        else if (n.flowType === 'compare') n.subtitle = (d.op || '?') + ' ?';
                        else if (n.flowType === 'cond') n.subtitle = 'if / else';
                        else if (n.flowType === 'bill') n.subtitle = (d.scope === 'business' ? 'biz' : 'emp') + ' · ' + (d.metric || 'overdue_total').replace(/_/g, ' ');
                        else n.subtitle = n.flowType;
                    }
                });
            }

            function stopSimulation() {
                if (flowSim) {
                    flowSim.stop();
                    flowSim = null;
                }
            }

            function renderFlow() {
                if (!flowSvg || !flowState) return;
                if (typeof d3 === 'undefined') {
                    setFlowError({!! json_encode(__('Diagram library failed to load. Refresh the page or contact support.')) !!});
                    return;
                }
                setFlowError('');

                stopSimulation();
                flowSvg.innerHTML = '';
                flowSelectedId = null;
                if (flowSidebarBody) {
                    flowSidebarBody.style.display = 'none';
                    flowSidebarBody.innerHTML = '';
                }
                if (flowSidebarPh) flowSidebarPh.style.display = 'block';

                var svgWrap = flowSvg.closest('.payroll-flow-svg-wrap');
                var width = flowSvg.clientWidth || (svgWrap && svgWrap.clientWidth) || 600;
                var height = flowSvg.clientHeight || (svgWrap && svgWrap.clientHeight) || 0;
                if (height < 120) height = Math.max(280, Math.floor((window.innerHeight || 600) * 0.35));

                const root = d3.select(flowSvg);
                const gMain = root.append('g').attr('class', 'payflow-zoom-layer');
                flowZoom = d3.zoom()
                    .scaleExtent([0.45, 2])
                    .filter(function (event) {
                        if (event.type === 'wheel') return true;
                        if (event.type === 'dblclick') return false;
                        const t = event.target;
                        return !(t && typeof t.closest === 'function' && t.closest('.payflow-node'));
                    })
                    .on('zoom', function (ev) {
                        gMain.attr('transform', ev.transform);
                    });
                root.call(flowZoom).call(flowZoom.transform, d3.zoomIdentity);

                const defs = root.append('defs');
                defs.append('marker')
                    .attr('id', 'payflow-arrow')
                    .attr('viewBox', '0 -5 10 10')
                    .attr('refX', 10)
                    .attr('refY', 0)
                    .attr('markerWidth', 5)
                    .attr('markerHeight', 5)
                    .attr('orient', 'auto')
                    .append('path')
                    .attr('d', 'M0,-5L10,0L0,5')
                    .attr('class', 'payflow-link-arrow');

                refreshNodeSubtitles();
                const nodes = flowState.graph.nodes.map(function (d) { return Object.assign({}, d); });
                const byId = new Map(nodes.map(function (n) { return [n.id, n]; }));
                const links = flowState.graph.links.map(function (l) {
                    return { source: byId.get(l.source), target: byId.get(l.target) };
                }).filter(function (l) { return l.source && l.target; });

                nodes.forEach(function (n, i) {
                    n.x = width * 0.25 + (i % 4) * 130;
                    n.y = height * 0.28 + Math.floor(i / 4) * 90;
                });

                const linkSel = gMain.append('g')
                    .attr('fill', 'none')
                    .selectAll('path')
                    .data(links)
                    .join('path')
                    .attr('class', 'payflow-link')
                    .attr('marker-end', 'url(#payflow-arrow)');

                const nodeSel = gMain.append('g')
                    .selectAll('g')
                    .data(nodes)
                    .join('g')
                    .attr('class', function (d) { return 'payflow-node' + (d.flowType === 'bill' ? ' payflow-node--bill' : ''); })
                    .call(d3.drag()
                        .on('start', function (ev, d) {
                            if (ev.sourceEvent && ev.sourceEvent.stopPropagation) ev.sourceEvent.stopPropagation();
                            if (flowSim) flowSim.alphaTarget(0.3).restart();
                            d.fx = d.x;
                            d.fy = d.y;
                        })
                        .on('drag', function (ev, d) {
                            if (ev.sourceEvent && ev.sourceEvent.stopPropagation) ev.sourceEvent.stopPropagation();
                            d.fx = ev.x;
                            d.fy = ev.y;
                        })
                        .on('end', function (ev, d) {
                            if (ev.sourceEvent && ev.sourceEvent.stopPropagation) ev.sourceEvent.stopPropagation();
                            if (flowSim) flowSim.alphaTarget(0);
                            d.fx = null;
                            d.fy = null;
                        }));

                nodeSel.append('rect')
                    .attr('width', NODE_W)
                    .attr('height', NODE_H)
                    .attr('x', -NODE_W / 2)
                    .attr('y', -NODE_H / 2)
                    .attr('rx', 10)
                    .attr('ry', 10);

                nodeSel.append('text')
                    .attr('class', 'payflow-t-title')
                    .attr('text-anchor', 'middle')
                    .attr('y', -6)
                    .attr('font-size', 11)
                    .text(function (d) { return d.title; });

                nodeSel.append('text')
                    .attr('class', 'payflow-sub')
                    .attr('text-anchor', 'middle')
                    .attr('y', 10)
                    .attr('font-size', 9)
                    .text(function (d) { return d.subtitle; });

                nodeSel.on('click', function (ev, d) {
                    ev.stopPropagation();
                    if (ev.sourceEvent && ev.sourceEvent.stopPropagation) ev.sourceEvent.stopPropagation();
                    selectNode(d.id);
                    nodeSel.classed('selected', function (n) { return n.id === d.id; });
                });

                flowSim = d3.forceSimulation(nodes)
                    .force('link', d3.forceLink(links).id(function (d) { return d.id; }).distance(140).strength(0.55))
                    .force('charge', d3.forceManyBody().strength(-420))
                    .force('center', d3.forceCenter(width / 2, height / 2))
                    .force('collide', d3.forceCollide().radius(56));

                flowSim.on('tick', function () {
                    linkSel.attr('d', function (d) {
                        const sx = d.source.x, sy = d.source.y, tx = d.target.x, ty = d.target.y;
                        const dx = tx - sx, dy = ty - sy;
                        const len = Math.sqrt(dx * dx + dy * dy) || 1;
                        const ux = dx / len, uy = dy / len;
                        const padS = NODE_W / 2 + 4, padT = NODE_W / 2 + 10;
                        const x1 = sx + ux * padS, y1 = sy + uy * (NODE_H / 2 + 4);
                        const x2 = tx - ux * padT, y2 = ty - uy * (NODE_H / 2 + 4);
                        return 'M' + x1 + ',' + y1 + 'L' + x2 + ',' + y2;
                    });

                    nodeSel.attr('transform', function (d) {
                        return 'translate(' + d.x + ',' + d.y + ')';
                    });

                    nodeSel.select('text.payflow-sub').text(function (d) { return d.subtitle; });
                });

                syncFormulaChrome();
            }

            function selectNode(id) {
                flowSelectedId = id;
                if (!flowState) return;
                const node = flowState.graph.nodes.find(function (n) { return n.id === id; });
                if (flowSidebarPh) flowSidebarPh.style.display = node && node.role !== 'output' ? 'none' : 'block';
                if (flowSidebarBody) flowSidebarBody.style.display = node && node.role !== 'output' ? 'block' : 'none';
                if (!flowSidebarBody) return;
                if (!node || node.role === 'output') {
                    flowSidebarBody.innerHTML = '';
                    return;
                }
                const cfg = flowState.config;
                const esc = function (v) {
                    const d = document.createElement('div');
                    d.textContent = String(v);
                    return d.innerHTML;
                };
                let html = '';
                if (node.role === 'amount') {
                    html += '<div class="payroll-field"><label>' + '{{ __('Fixed amount') }}' + '</label>';
                    html += '<input type="number" step="0.01" class="payroll-input pf-side-inp" data-k="amount" value="' + esc(cfg.amount) + '"></div>';
                } else if (node.role === 'base_field') {
                    html += '<div class="payroll-field"><label>' + '{{ __('Context field') }}' + '</label>';
                    html += '<input type="text" class="payroll-input pf-side-inp" data-k="base_field" value="' + esc(cfg.base_field) + '"></div>';
                    html += '<p class="payroll-flow-help">' + '{{ __('Evaluated against employee payroll context (for example basic_salary).') }}' + '</p>';
                } else if (node.role === 'percent') {
                    html += '<div class="payroll-field"><label>' + '{{ __('Percent') }}' + '</label>';
                    html += '<input type="number" step="0.0001" class="payroll-input pf-side-inp" data-k="percent" value="' + esc(cfg.percent) + '"></div>';
                } else if (node.role === 'input_field') {
                    html += '<div class="payroll-field"><label>' + '{{ __('Input field') }}' + '</label>';
                    html += '<input type="text" class="payroll-input pf-side-inp" data-k="input_field" value="' + esc(cfg.input_field) + '"></div>';
                } else if (node.role === 'slab') {
                    var s = cfg.slabs[node.slabIndex] || { from: 0, to: null, percent: 0, fixed: 0 };
                    html += '<div class="payroll-field"><label>' + '{{ __('From') }}' + '</label>';
                    html += '<input type="number" step="0.01" class="payroll-input pf-slab-inp" data-idx="' + node.slabIndex + '" data-f="from" value="' + esc(s.from) + '"></div>';
                    html += '<div class="payroll-field"><label>' + '{{ __('To (empty = no upper cap)') }}' + '</label>';
                    html += '<input type="number" step="0.01" class="payroll-input pf-slab-inp" data-idx="' + node.slabIndex + '" data-f="to" value="' + (s.to === null || s.to === undefined ? '' : esc(s.to)) + '"></div>';
                    html += '<div class="payroll-field"><label>' + '{{ __('Percent') }}' + '</label>';
                    html += '<input type="number" step="0.0001" class="payroll-input pf-slab-inp" data-idx="' + node.slabIndex + '" data-f="percent" value="' + esc(s.percent) + '"></div>';
                    html += '<div class="payroll-field"><label>' + '{{ __('Fixed add-on') }}' + '</label>';
                    html += '<input type="number" step="0.01" class="payroll-input pf-slab-inp" data-idx="' + node.slabIndex + '" data-f="fixed" value="' + esc(s.fixed) + '"></div>';
                } else if (node.flowType) {
                    var fvNodes = (cfg.flow_v1 && cfg.flow_v1.nodes) ? cfg.flow_v1.nodes : {};
                    var fvId = node.id;
                    var d = node.flowData || fvNodes[fvId] || {};
                    function fvOpts(cur) {
                        var o = '<option value="">{{ __('None') }}</option>';
                        Object.keys(fvNodes).sort().forEach(function (kid) {
                            if (kid === fvId) return;
                            o += '<option value="' + esc(kid) + '"' + (cur === kid ? ' selected' : '') + '>' + esc(kid) + '</option>';
                        });
                        return o;
                    }
                    html += '<p class="payroll-flow-help" style="margin-top:0;"><strong>' + esc(fvId) + '</strong> · ' + esc(node.flowType) + '</p>';
                    if (node.flowType === 'context') {
                        html += '<div class="payroll-field"><label>' + '{{ __('Context key') }}' + '</label>';
                        html += '<input type="text" class="payroll-input fv-flow-inp" data-fv-id="' + esc(fvId) + '" data-fv-field="field" value="' + esc(d.field || '') + '"></div>';
                    } else if (node.flowType === 'constant') {
                        html += '<div class="payroll-field"><label>' + '{{ __('Value') }}' + '</label>';
                        html += '<input type="number" step="any" class="payroll-input fv-flow-inp" data-fv-id="' + esc(fvId) + '" data-fv-field="value" value="' + esc(d.value ?? 0) + '"></div>';
                    } else if (node.flowType === 'binary') {
                        html += '<div class="payroll-field"><label>' + '{{ __('Operator') }}' + '</label>';
                        html += '<select class="payroll-input fv-flow-sel" data-fv-id="' + esc(fvId) + '" data-fv-field="op">';
                        ['+', '-', '*', '/'].forEach(function (op) {
                            html += '<option value="' + op + '"' + ((d.op || '+') === op ? ' selected' : '') + '>' + esc(op) + '</option>';
                        });
                        html += '</select></div>';
                        html += '<div class="payroll-field"><label>' + '{{ __('Left operand') }}' + '</label><select class="payroll-input fv-flow-sel" data-fv-id="' + esc(fvId) + '" data-fv-field="left">' + fvOpts(d.left || '') + '</select></div>';
                        html += '<div class="payroll-field"><label>' + '{{ __('Right operand') }}' + '</label><select class="payroll-input fv-flow-sel" data-fv-id="' + esc(fvId) + '" data-fv-field="right">' + fvOpts(d.right || '') + '</select></div>';
                    } else if (node.flowType === 'compare') {
                        html += '<div class="payroll-field"><label>' + '{{ __('Comparison') }}' + '</label>';
                        html += '<select class="payroll-input fv-flow-sel" data-fv-id="' + esc(fvId) + '" data-fv-field="op">';
                        [['gt', '>'], ['gte', '≥'], ['lt', '<'], ['lte', '≤'], ['eq', '=']].forEach(function (row) {
                            html += '<option value="' + row[0] + '"' + ((d.op || 'gt') === row[0] ? ' selected' : '') + '>' + row[1] + ' (' + row[0] + ')</option>';
                        });
                        html += '</select></div>';
                        html += '<div class="payroll-field"><label>' + '{{ __('Left') }}' + '</label><select class="payroll-input fv-flow-sel" data-fv-id="' + esc(fvId) + '" data-fv-field="left">' + fvOpts(d.left || '') + '</select></div>';
                        html += '<div class="payroll-field"><label>' + '{{ __('Right') }}' + '</label><select class="payroll-input fv-flow-sel" data-fv-id="' + esc(fvId) + '" data-fv-field="right">' + fvOpts(d.right || '') + '</select></div>';
                        html += '<p class="payroll-flow-help">' + '{{ __('Result is 1 if true, 0 if false.') }}' + '</p>';
                    } else if (node.flowType === 'cond') {
                        html += '<div class=”payroll-field”><label>' + '{{ __('Test (use a compare node)') }}' + '</label><select class=”payroll-input fv-flow-sel” data-fv-id=”' + esc(fvId) + '” data-fv-field=”test”>' + fvOpts(d.test || '') + '</select></div>';
                        html += '<div class=”payroll-field”><label>' + '{{ __('Then value') }}' + '</label><select class=”payroll-input fv-flow-sel” data-fv-id=”' + esc(fvId) + '” data-fv-field=”then”>' + fvOpts(d.then || '') + '</select></div>';
                        html += '<div class=”payroll-field”><label>' + '{{ __('Else value') }}' + '</label><select class=”payroll-input fv-flow-sel” data-fv-id=”' + esc(fvId) + '” data-fv-field=”else”>' + fvOpts(d['else'] || '') + '</select></div>';
                        html += '<p class=”payroll-flow-help”>' + '{{ __('If test is non-zero, “then” is used; otherwise “else”.') }}' + '</p>';
                    } else if (node.flowType === 'bill') {
                        var billScopeOpts = '<option value=”employee”' + ((d.scope || 'employee') === 'employee' ? ' selected' : '') + '>{{ __('Employee') }}</option>';
                        billScopeOpts    += '<option value=”business”' + (d.scope === 'business' ? ' selected' : '') + '>{{ __('Business') }}</option>';
                        var billMetricOpts = '<option value=”overdue_total”'   + ((d.metric || 'overdue_total') === 'overdue_total'   ? ' selected' : '') + '>{{ __('Overdue total (amount)') }}</option>';
                        billMetricOpts    += '<option value=”overdue_count”'   + (d.metric === 'overdue_count'   ? ' selected' : '') + '>{{ __('Overdue count') }}</option>';
                        billMetricOpts    += '<option value=”available_total”' + (d.metric === 'available_total' ? ' selected' : '') + '>{{ __('Available total (amount)') }}</option>';
                        billMetricOpts    += '<option value=”available_count”' + (d.metric === 'available_count' ? ' selected' : '') + '>{{ __('Available count') }}</option>';
                        html += '<div class=”payroll-field”><label>' + '{{ __('Scope') }}' + '</label>';
                        html += '<select class=”payroll-input fv-flow-sel” data-fv-id=”' + esc(fvId) + '” data-fv-field=”scope”>' + billScopeOpts + '</select></div>';
                        html += '<div class=”payroll-field”><label>' + '{{ __('Metric') }}' + '</label>';
                        html += '<select class=”payroll-input fv-flow-sel” data-fv-id=”' + esc(fvId) + '” data-fv-field=”metric”>' + billMetricOpts + '</select></div>';
                        html += '<p class=”payroll-flow-help”>{{ __('Employee: bills assigned to this employee. Business: all bills for the business. Overdue = due_date is past today; available = any active bill.') }}</p>';
                    }
                } else if (node.role === 'formula') {
                    html += '<div class="payroll-field"><label>' + '{{ __('Expression') }}' + '</label>';
                    html += '<textarea rows="4" class="payroll-input pf-side-ta" data-k="formula" style="resize:vertical;">' + esc(cfg.formula) + '</textarea></div>';
                    html += '<p class="payroll-flow-help">' + '{{ __('Use + - * / ( ) and context field names available at runtime.') }}' + '</p>';
                    html += '<button type="button" id="payflowMigrateVisualBtn" class="payroll-btn" style="width:100%;justify-content:center;margin-top:8px;">';
                    html += '<i class="fa fa-diagram-project"></i>' + '{{ __('Switch to visual graph (clears this text)') }}' + '</button>';
                }
                flowSidebarBody.innerHTML = html;

                flowSidebarBody.querySelectorAll('.fv-flow-inp').forEach(function (inp) {
                    inp.addEventListener('input', function () {
                        var id = inp.getAttribute('data-fv-id');
                        var field = inp.getAttribute('data-fv-field');
                        var nd = cfg.flow_v1.nodes[id];
                        if (!nd) return;
                        if (field === 'field') nd.field = inp.value;
                        else if (field === 'value') nd.value = parseFloat(inp.value) || 0;
                        syncSubtitles();
                        if (flowSim) flowSim.alpha(0.12).restart();
                    });
                });
                flowSidebarBody.querySelectorAll('.fv-flow-sel').forEach(function (sel) {
                    sel.addEventListener('change', function () {
                        var id = sel.getAttribute('data-fv-id');
                        var field = sel.getAttribute('data-fv-field');
                        var nd = cfg.flow_v1.nodes[id];
                        if (!nd) return;
                        if (field === 'else') nd['else'] = sel.value;
                        else nd[field] = sel.value;
                        syncFormulaChrome();
                        rebuildFormulaGraphFromConfig(true);
                    });
                });
                var migrateBtn = flowSidebarBody.querySelector('#payflowMigrateVisualBtn');
                if (migrateBtn) {
                    migrateBtn.addEventListener('click', function () {
                        if (!window.confirm({!! json_encode(__('Replace the text formula with an empty visual graph? This cannot be undone from here.')) !!})) return;
                        seedFormulaFlowV1(cfg);
                        syncFormulaChrome();
                        flowState.graph = buildGraph('formula', cfg);
                        renderFlow();
                        selectNode(cfg.flow_v1.root);
                    });
                }

                flowSidebarBody.querySelectorAll('.pf-side-inp').forEach(function (inp) {
                    inp.addEventListener('input', function () {
                        const k = inp.getAttribute('data-k');
                        if (k === 'amount') cfg.amount = parseFloat(inp.value) || 0;
                        else if (k === 'base_field') cfg.base_field = inp.value;
                        else if (k === 'percent') cfg.percent = parseFloat(inp.value) || 0;
                        else if (k === 'input_field') cfg.input_field = inp.value;
                        syncSubtitles();
                    });
                });
                var ta = flowSidebarBody.querySelector('.pf-side-ta');
                if (ta) {
                    ta.addEventListener('input', function () {
                        cfg.formula = ta.value;
                        syncSubtitles();
                    });
                }
                flowSidebarBody.querySelectorAll('.pf-slab-inp').forEach(function (inp) {
                    inp.addEventListener('input', function () {
                        const idx = parseInt(inp.getAttribute('data-idx'), 10);
                        const f = inp.getAttribute('data-f');
                        if (!cfg.slabs[idx]) return;
                        if (f === 'to') {
                            cfg.slabs[idx].to = inp.value === '' ? null : (parseFloat(inp.value) || 0);
                        } else {
                            cfg.slabs[idx][f] = parseFloat(inp.value) || 0;
                        }
                        syncSubtitles();
                    });
                });
            }

            function syncSubtitles() {
                refreshNodeSubtitles();
                if (flowSim) flowSim.alpha(0.15).restart();
            }

            function setFlowError(msg) {
                if (!flowLoadError || !flowSvg) return;
                if (msg) {
                    flowLoadError.textContent = msg;
                    flowLoadError.style.display = 'block';
                    flowSvg.style.display = 'none';
                } else {
                    flowLoadError.textContent = '';
                    flowLoadError.style.display = 'none';
                    flowSvg.style.display = 'block';
                }
            }

            function openFlowWithPayload(payload, opts) {
                opts = opts || {};
                const mode = String(payload.calculation_mode || '').trim().toLowerCase();
                var config = ensureConfig(mode, payload.config_json);
                if (opts.seedFormulaGraph && mode === 'formula' && !hasFormulaFlow(config)) {
                    seedFormulaFlowV1(config);
                }
                let graph = buildGraph(mode, config);
                if (!graph.nodes.length) {
                    graph = {
                        nodes: [
                            { id: 'pf_unknown', role: 'output', title: '{{ __('Unsupported') }}', subtitle: mode || '—' },
                        ],
                        links: [],
                    };
                }
                flowState = {
                    createMode: !!opts.createMode,
                    payload: payload,
                    mode: mode,
                    config: config,
                    graph: graph,
                };

                if (flowState.createMode) {
                    if (payflowApplyDraftBtn) payflowApplyDraftBtn.style.display = 'inline-flex';
                    if (payflowSubmitPatchBtn) payflowSubmitPatchBtn.style.display = 'none';
                    if (flowForm) flowForm.removeAttribute('action');
                } else {
                    if (payflowApplyDraftBtn) payflowApplyDraftBtn.style.display = 'none';
                    if (payflowSubmitPatchBtn) payflowSubmitPatchBtn.style.display = 'inline-flex';
                    var patchUrl = (opts.patchUrl || '').trim();
                    if (!patchUrl) {
                        alert({!! json_encode(__('Missing save URL for this rule. Refresh the page and try again.')) !!});
                        flowState = null;
                        return;
                    }
                    if (flowForm) flowForm.action = patchUrl;
                }

                flowTitle.textContent = (flowState.createMode ? '{{ __('New rule · Flow') }}' : '{{ __('Rule flow') }}') + ': ' + String(payload.code || '—').trim();
                flowSub.textContent = (payload.name || '') + ' · ' + mode;
                flowSlabToolbar.style.display = mode === 'slab' ? 'block' : 'none';
                flowConfigInput.value = JSON.stringify(config);
                flowOverlay.classList.add('is-open');
                flowOverlay.setAttribute('aria-hidden', 'false');
                if (flowLogicToolsRoot) {
                    flowLogicToolsRoot.removeAttribute('hidden');
                    updateLogicTools(mode);
                }
                setFlowError('');
                syncFormulaChrome();
                requestAnimationFrame(function () {
                    requestAnimationFrame(renderFlow);
                });
            }

            function openFlowEditor(btn) {
                let payload;
                try {
                    payload = decodePayload(btn);
                } catch (e) {
                    alert({!! json_encode(__('Could not open flow editor (invalid rule data).')) !!});
                    return;
                }
                openFlowWithPayload(payload, {
                    createMode: false,
                    patchUrl: (btn.getAttribute('data-rule-patch-url') || '').trim(),
                    seedFormulaGraph: false,
                });
            }

            function openFlowFromCreateForm() {
                if (!payrollRuleModalForm) return;
                var modeEl = payrollRuleModalForm.querySelector('[name="calculation_mode"]');
                var mode = modeEl ? modeEl.value : 'fixed';
                if (String(mode).toLowerCase() === 'formula') {
                    alert({!! json_encode(__('Save this rule first, then open the flow editor from the rule list.')) !!});
                    return;
                }
                var raw = '';
                var cj = payrollRuleModalForm.querySelector('[name="config_json"]');
                if (cj) raw = cj.value || '';
                var parsed = {};
                if (raw.trim()) {
                    try {
                        parsed = JSON.parse(raw);
                    } catch (e) {
                        alert({!! json_encode(__('Config JSON is not valid. Fix it or clear the field before opening the flow designer.')) !!});
                        return;
                    }
                }
                openFlowWithPayload({
                    id: null,
                    code: (payrollRuleModalForm.querySelector('[name="code"]') || {}).value || '',
                    name: (payrollRuleModalForm.querySelector('[name="name"]') || {}).value || '',
                    component_type: (payrollRuleModalForm.querySelector('[name="component_type"]') || {}).value || 'earning',
                    calculation_mode: mode,
                    config_json: parsed,
                }, {
                    createMode: true,
                    patchUrl: '',
                    seedFormulaGraph: mode === 'formula',
                });
            }

            function closeFlowEditor() {
                if (!flowOverlay) return;
                stopSimulation();
                flowOverlay.classList.remove('is-open');
                flowOverlay.setAttribute('aria-hidden', 'true');
                if (flowSvg) flowSvg.innerHTML = '';
                setFlowError('');
                if (flowLogicToolsRoot) flowLogicToolsRoot.setAttribute('hidden', 'hidden');
                if (payflowApplyDraftBtn) payflowApplyDraftBtn.style.display = 'none';
                if (payflowSubmitPatchBtn) payflowSubmitPatchBtn.style.display = 'inline-flex';
                flowState = null;
            }

            flowForm && flowForm.addEventListener('submit', function (e) {
                if (flowState && flowState.createMode) {
                    e.preventDefault();
                    return;
                }
                if (flowState) {
                    flowConfigInput.value = JSON.stringify(flowState.config);
                }
            });

            payflowApplyDraftBtn && payflowApplyDraftBtn.addEventListener('click', function () {
                if (!flowState || !flowState.createMode || !payrollRuleModalForm) return;
                var cj = payrollRuleModalForm.querySelector('[name="config_json"]');
                if (cj) cj.value = JSON.stringify(flowState.config);
                var modeEl = payrollRuleModalForm.querySelector('[name="calculation_mode"]');
                if (modeEl) modeEl.value = flowState.mode;
                closeFlowEditor();
            });

            payrollRuleFlowDesignerBtn && payrollRuleFlowDesignerBtn.addEventListener('click', function () {
                openFlowFromCreateForm();
            });

            function addFormulaFlowNode(kind) {
                if (!flowState || flowState.mode !== 'formula') return;
                if (!hasFormulaFlow(flowState.config)) seedFormulaFlowV1(flowState.config);
                var id = nextFvNodeId();
                var nodes = flowState.config.flow_v1.nodes;
                if (kind === 'context') nodes[id] = { type: 'context', field: 'basic_salary' };
                else if (kind === 'constant') nodes[id] = { type: 'constant', value: 0 };
                else if (kind === 'binary') nodes[id] = { type: 'binary', op: '+', left: '', right: '' };
                else if (kind === 'compare') nodes[id] = { type: 'compare', op: 'gt', left: '', right: '' };
                else if (kind === 'cond') nodes[id] = { type: 'cond', test: '', then: '', 'else': '' };
                else if (kind === 'bill') nodes[id] = { type: 'bill', scope: 'employee', metric: 'overdue_total' };
                flowState.config.flow_v1.root = id;
                syncFormulaChrome();
                rebuildFormulaGraphFromConfig(false);
                selectNode(id);
            }

            function deleteFormulaFlowSelected() {
                if (!flowState || !hasFormulaFlow(flowState.config)) return;
                var sid = flowSelectedId;
                if (!sid || !flowState.config.flow_v1.nodes[sid]) return;
                var nodes = flowState.config.flow_v1.nodes;
                delete nodes[sid];
                fvStripRefs(nodes, sid);
                if (flowState.config.flow_v1.root === sid) {
                    var ks = Object.keys(nodes);
                    flowState.config.flow_v1.root = ks[0] || '';
                }
                if (Object.keys(nodes).length === 0) {
                    seedFormulaFlowV1(flowState.config);
                }
                syncFormulaChrome();
                rebuildFormulaGraphFromConfig(false);
            }

            document.getElementById('payflowAddFvContext') && document.getElementById('payflowAddFvContext').addEventListener('click', function () { addFormulaFlowNode('context'); });
            document.getElementById('payflowAddFvConst') && document.getElementById('payflowAddFvConst').addEventListener('click', function () { addFormulaFlowNode('constant'); });
            document.getElementById('payflowAddFvBin') && document.getElementById('payflowAddFvBin').addEventListener('click', function () { addFormulaFlowNode('binary'); });
            document.getElementById('payflowAddFvCmp') && document.getElementById('payflowAddFvCmp').addEventListener('click', function () { addFormulaFlowNode('compare'); });
            document.getElementById('payflowAddFvCond') && document.getElementById('payflowAddFvCond').addEventListener('click', function () { addFormulaFlowNode('cond'); });
            document.getElementById('payflowAddFvBill') && document.getElementById('payflowAddFvBill').addEventListener('click', function () { addFormulaFlowNode('bill'); });
            document.getElementById('payflowFvDeleteNode') && document.getElementById('payflowFvDeleteNode').addEventListener('click', deleteFormulaFlowSelected);

            document.querySelectorAll('.payroll-flow-editor-open-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openFlowEditor(btn);
                });
            });

            flowClose && flowClose.addEventListener('click', closeFlowEditor);
            flowCancel && flowCancel.addEventListener('click', closeFlowEditor);
            flowOverlay && flowOverlay.addEventListener('click', function (e) {
                if (e.target === flowOverlay) closeFlowEditor();
            });

            flowAddSlabBtn && flowAddSlabBtn.addEventListener('click', function () {
                if (!flowState || flowState.mode !== 'slab') return;
                flowState.config.slabs.push({ from: 0, to: null, percent: 0, fixed: 0 });
                flowState.graph = buildGraph(flowState.mode, flowState.config);
                renderFlow();
            });

            flowRemoveSlabBtn && flowRemoveSlabBtn.addEventListener('click', function () {
                if (!flowState || flowState.mode !== 'slab') return;
                let idx = -1;
                if (flowSelectedId) {
                    const n = flowState.graph.nodes.find(function (x) { return x.id === flowSelectedId; });
                    if (n && n.role === 'slab') idx = n.slabIndex;
                }
                if (idx < 0) idx = flowState.config.slabs.length - 1;
                if (flowState.config.slabs.length <= 1) return;
                flowState.config.slabs.splice(idx, 1);
                flowState.graph = buildGraph(flowState.mode, flowState.config);
                renderFlow();
            });

            var flowSvgWrapEl = flowSvg ? flowSvg.closest('.payroll-flow-svg-wrap') : null;
            var payflowSvgResizeTimer = null;
            if (flowSvgWrapEl && typeof ResizeObserver !== 'undefined') {
                var payflowSvgRo = new ResizeObserver(function () {
                    if (!flowOverlay || !flowOverlay.classList.contains('is-open') || !flowState) return;
                    clearTimeout(payflowSvgResizeTimer);
                    payflowSvgResizeTimer = setTimeout(function () { renderFlow(); }, 70);
                });
                payflowSvgRo.observe(flowSvgWrapEl);
            }
        })();
    </script>
    <script>
        (function () {
            const overlay = document.getElementById('payrollRuleModalOverlay');
            const closeBtn = document.getElementById('payrollRuleModalClose');
            const cancelBtn = document.getElementById('payrollRuleModalCancel');
            const form = document.getElementById('payrollRuleModalForm');
            const ruleSetIdInput = document.getElementById('payrollRuleModalRuleSetId');
            const ruleSetOverlay = document.getElementById('payrollRuleSetModalOverlay');
            const ruleSetOpenBtn = document.getElementById('payrollRuleSetOpenBtn');
            const ruleSetCloseBtn = document.getElementById('payrollRuleSetModalClose');
            const ruleSetCancelBtn = document.getElementById('payrollRuleSetModalCancel');

            const payrollRuleFlowDesignerBtn = document.getElementById('payrollRuleFlowDesignerBtn');
            const payrollRuleCalcModeEl = form ? form.querySelector('[name="calculation_mode"]') : null;
            const payrollRuleFlowHelpDefault = document.getElementById('payrollRuleFlowDesignerHelpDefault');
            const payrollRuleFlowHelpFormula = document.getElementById('payrollRuleFlowDesignerHelpFormula');

            function syncPayrollRuleFlowDesignerGate() {
                if (!payrollRuleFlowDesignerBtn || !payrollRuleCalcModeEl) return;
                var isFormula = String(payrollRuleCalcModeEl.value || '').toLowerCase() === 'formula';
                payrollRuleFlowDesignerBtn.disabled = isFormula;
                if (payrollRuleFlowHelpDefault) payrollRuleFlowHelpDefault.hidden = isFormula;
                if (payrollRuleFlowHelpFormula) payrollRuleFlowHelpFormula.hidden = !isFormula;
            }

            function openModalFromButton(btn) {
                if (!overlay || !form || !ruleSetIdInput || !btn) return;
                const actionUrl = btn.getAttribute('data-action-url') || '';
                const ruleSetId = btn.getAttribute('data-rule-set-id') || '';
                form.action = actionUrl;
                ruleSetIdInput.value = ruleSetId;
                overlay.classList.add('is-open');
                syncPayrollRuleFlowDesignerGate();
            }

            function closeModal() {
                if (!overlay) return;
                overlay.classList.remove('is-open');
            }

            function openRuleSetModal() {
                if (!ruleSetOverlay) return;
                ruleSetOverlay.classList.add('is-open');
            }

            function closeRuleSetModal() {
                if (!ruleSetOverlay) return;
                ruleSetOverlay.classList.remove('is-open');
            }

            payrollRuleCalcModeEl && payrollRuleCalcModeEl.addEventListener('change', syncPayrollRuleFlowDesignerGate);

            document.querySelectorAll('.payroll-add-rule-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModalFromButton(btn);
                });
            });

            closeBtn && closeBtn.addEventListener('click', closeModal);
            cancelBtn && cancelBtn.addEventListener('click', closeModal);
            overlay && overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });
            ruleSetOpenBtn && ruleSetOpenBtn.addEventListener('click', openRuleSetModal);
            ruleSetCloseBtn && ruleSetCloseBtn.addEventListener('click', closeRuleSetModal);
            ruleSetCancelBtn && ruleSetCancelBtn.addEventListener('click', closeRuleSetModal);
            ruleSetOverlay && ruleSetOverlay.addEventListener('click', function (e) {
                if (e.target === ruleSetOverlay) closeRuleSetModal();
            });

            const oldRuleSetId = @json(old('rule_set_id'));
            if (oldRuleSetId) {
                document.querySelectorAll('.payroll-add-rule-btn').forEach(function (btn) {
                    if ((btn.getAttribute('data-rule-set-id') || '') === String(oldRuleSetId)) {
                        openModalFromButton(btn);
                    }
                });
            }

            const hasRuleSetOldInput = @json(
                old('name') !== null
                || old('currency') !== null
                || old('effective_from') !== null
                || old('effective_to') !== null
                || old('is_default') !== null
                || old('notes') !== null
            );
            if (hasRuleSetOldInput) {
                openRuleSetModal();
            }
        })();
    </script>
@endsection
