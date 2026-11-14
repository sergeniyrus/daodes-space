@extends('template')

@section('title_page')
    Меню — {{ $organization->name }}
@endsection

@section('main')
<style>
.customer-order-page {
    width: min(1180px, calc(100% - 24px));
    margin: 28px auto 55px;
    color: #f4f4f4;
}
.customer-order-panel {
    padding: 22px;
    margin-bottom: 20px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .45);
    border-radius: 18px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .35);
}
.customer-order-title {
    margin: 0;
    color: #ffd700;
    font-size: clamp(1.35rem, 3vw, 2rem);
}
.customer-order-muted { color: #aeb5c4; }
.customer-order-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 15px;
}
.customer-order-link,
.customer-order-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 8px 16px;
    color: #00e5d4;
    background: rgba(0, 229, 212, .06);
    border: 1px solid rgba(0, 229, 212, .65);
    border-radius: 22px;
    text-decoration: none;
    cursor: pointer;
    font-weight: 700;
}
.customer-order-link:hover,
.customer-order-button:hover {
    color: #081016;
    background: #00e5d4;
}
.customer-order-alert {
    padding: 12px 15px;
    margin-bottom: 15px;
    color: #f4f4f4;
    background: rgba(0, 229, 212, .08);
    border: 1px solid rgba(0, 229, 212, .35);
    border-radius: 10px;
}
.customer-order-alert.error {
    border-color: rgba(255, 100, 100, .55);
    background: rgba(255, 70, 70, .08);
}
.customer-order-page > .customer-order-section {
    position: relative;
    isolation: isolate;
    margin: 26px 0;
    padding: 22px;
    background-color: #111827 !important;
    background-image:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .12), transparent 48%),
        linear-gradient(135deg, #151b2d 0%, #0c1222 55%, #121a2b 100%) !important;
    border: 1px solid rgba(0, 229, 212, .48) !important;
    border-radius: 18px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .045), 0 12px 32px rgba(0, 0, 0, .38);
}
.customer-order-section {
    padding: 0 !important;
    overflow: hidden;
    transition: border-color .2s ease, box-shadow .2s ease;
}
.customer-order-section[open] {
    border-color: rgba(255, 215, 0, .58) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .045), 0 14px 36px rgba(0, 0, 0, .42), 0 0 18px rgba(0, 229, 212, .06);
}
.customer-order-section > summary {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    min-height: 68px;
    padding: 17px 22px;
    color: #ffd700;
    cursor: pointer;
    list-style: none;
    user-select: none;
    background:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .10), transparent 48%),
        linear-gradient(110deg, rgba(0, 229, 212, .075), rgba(255, 255, 255, .018));
    transition: background .2s ease, color .2s ease;
}
.customer-order-section > summary::-webkit-details-marker { display: none; }
.customer-order-section > summary::marker { content: ""; }
.customer-order-section > summary:hover {
    color: #fff1a8;
    background:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .15), transparent 48%),
        linear-gradient(110deg, rgba(0, 229, 212, .12), rgba(255, 255, 255, .025));
}
.customer-order-section[open] > summary {
    border-bottom: 1px solid rgba(0, 229, 212, .28);
}
.customer-order-section-title {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
    margin: 0;
    color: inherit;
    font-size: clamp(1.05rem, 2.5vw, 1.35rem);
    font-weight: 800;
    letter-spacing: .025em;
    text-shadow: 0 0 12px rgba(0, 229, 212, .12);
}
.customer-order-section-title::before {
    content: "";
    width: 7px;
    height: 7px;
    flex: 0 0 7px;
    border-radius: 50%;
    background: #00e5d4;
    box-shadow: 0 0 10px rgba(0, 229, 212, .75);
}
.customer-order-section-meta {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    flex: 0 0 auto;
}
.customer-order-section-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 8px;
    color: #bafaf3;
    background: rgba(0, 229, 212, .09);
    border: 1px solid rgba(0, 229, 212, .3);
    border-radius: 20px;
    font-size: .82rem;
    font-weight: 800;
}
.customer-order-section-chevron {
    width: 11px;
    height: 11px;
    border-right: 2px solid #ffd700;
    border-bottom: 2px solid #ffd700;
    transform: rotate(45deg) translateY(-3px);
    transition: transform .22s ease;
}
.customer-order-section[open] .customer-order-section-chevron {
    transform: rotate(225deg) translate(-2px, -1px);
}
.customer-order-section-content {
    padding: 20px 22px 22px;
}

.customer-order-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 270px), 1fr));
    gap: 14px;
}
.customer-order-item {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 17px;
    background: rgba(12, 15, 28, .92);
    border: 1px solid rgba(0, 229, 212, .25);
    border-radius: 14px;
}
.customer-order-item h3 { margin: 0; color: #fff; font-size: 1.05rem; }
.customer-order-item p { margin: 0; color: #aeb5c4; line-height: 1.45; }
.customer-order-price { margin-top: auto; color: #ffd700; font-size: 1.12rem; font-weight: 700; }
.customer-order-add { display: flex; gap: 10px; align-items: center; }
.customer-quantity-stepper {
    display: inline-flex;
    align-items: center;
    overflow: hidden;
    border: 1px solid rgba(0, 229, 212, .55);
    border-radius: 11px;
    background: rgba(4, 9, 19, .96);
    box-shadow: inset 0 0 12px rgba(0, 229, 212, .06);
}
.customer-quantity-stepper button {
    width: 38px; height: 40px; color: #00e5d4;
    background: rgba(0, 229, 212, .09); border: 0;
    cursor: pointer; font-size: 1.25rem; font-weight: 800;
}
.customer-quantity-stepper button:hover { color: #081016; background: #00e5d4; }
.customer-quantity-stepper input {
    width: 42px; min-height: 40px; padding: 4px 2px;
    color: #fff; background: transparent; border: 0;
    border-left: 1px solid rgba(0, 229, 212, .2);
    border-right: 1px solid rgba(0, 229, 212, .2);
    border-radius: 0; text-align: center;
    appearance: textfield; -moz-appearance: textfield;
}
.customer-quantity-stepper input::-webkit-inner-spin-button,
.customer-quantity-stepper input::-webkit-outer-spin-button { appearance: none; margin: 0; }
.customer-order-item { scroll-margin-top: 24px; }
.customer-order-disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 600px) {
    .customer-order-panel { padding: 17px; }
    .customer-order-section > summary { min-height: 60px; padding: 15px; }
    .customer-order-section-content { padding: 15px; }
    .customer-order-section-meta { gap: 9px; }
    .customer-order-toolbar { align-items: stretch; flex-direction: column; }
    .customer-order-link { width: 100%; box-sizing: border-box; }
}

/* Desktop: слева компактная гармошка разделов, справа каталог блюд. */
.customer-order-page {
    width: min(1800px, calc(100% - 28px));
    max-width: none;
}
.customer-order-layout {
    display: grid;
    grid-template-columns: minmax(190px, 1fr) minmax(0, 4fr);
    gap: 18px;
    align-items: start;
    width: 100%;
}
.customer-order-sidebar {
    position: sticky;
    top: 16px;
    min-width: 0;
    max-height: calc(100vh - 32px);
    overflow-y: auto;
    padding: 13px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(0, 229, 212, .38);
    border-radius: 16px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .32);
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 229, 212, .5) transparent;
}
.customer-order-sidebar-title {
    margin: 2px 5px 12px;
    color: #ffd700;
    font-size: 1rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.customer-order-section-list {
    display: flex;
    flex-direction: column;
    gap: 7px;
}
.customer-order-section-choice {
    width: 100%;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px 11px;
    color: #c8d0dc;
    text-align: left;
    background: linear-gradient(110deg, rgba(0, 229, 212, .045), rgba(255, 255, 255, .018));
    border: 1px solid rgba(0, 229, 212, .2);
    border-radius: 10px;
    cursor: pointer;
    transition: .2s ease;
}
.customer-order-section-choice:hover {
    color: #fff1a8;
    border-color: rgba(255, 215, 0, .5);
    background: rgba(0, 229, 212, .09);
}
.customer-order-section-choice[aria-expanded="true"] {
    color: #ffd700;
    border-color: rgba(255, 215, 0, .72);
    background: linear-gradient(110deg, rgba(0, 229, 212, .13), rgba(255, 215, 0, .08));
    box-shadow: inset 3px 0 #00e5d4, 0 0 14px rgba(0, 229, 212, .07);
}
.customer-order-section-choice-name {
    min-width: 0;
    overflow-wrap: anywhere;
    font-size: .9rem;
    font-weight: 750;
    line-height: 1.25;
}
.customer-order-section-choice-meta {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
    gap: 8px;
}
.customer-order-section-choice-count {
    min-width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
    color: #bafaf3;
    background: rgba(0, 229, 212, .08);
    border: 1px solid rgba(0, 229, 212, .25);
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 800;
}
.customer-order-section-choice-arrow {
    width: 8px;
    height: 8px;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(-45deg);
    transition: transform .2s ease;
}
.customer-order-section-choice[aria-expanded="true"] .customer-order-section-choice-arrow {
    transform: rotate(45deg);
}
.customer-order-products {
    min-width: 0;
}
.customer-order-products-panel {
    min-width: 0;
    padding: 18px;
    background:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .10), transparent 45%),
        linear-gradient(145deg, #111827, #0b1020 60%, #121a2b);
    border: 1px solid rgba(0, 229, 212, .38);
    border-radius: 16px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, .34);
}
.customer-order-products-panel[hidden] { display: none !important; }
.customer-order-products-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 14px;
}
.customer-order-products-heading h2 {
    margin: 0;
    color: #ffd700;
    font-size: clamp(1.1rem, 1.7vw, 1.55rem);
    font-weight: 850;
}
.customer-order-products-heading span {
    color: #9eabbc;
    font-size: .85rem;
}
.customer-order-carousel {
    position: relative;
    min-width: 0;
}
.customer-order-grid {
    display: grid;
    grid-auto-flow: column;
    grid-template-rows: repeat(2, minmax(0, 1fr));
    grid-auto-columns: calc((100% - 28px) / 3);
    gap: 14px;
    height: clamp(540px, calc(100vh - 260px), 760px);
    min-height: 540px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 229, 212, .55) rgba(255, 255, 255, .04);
    padding: 2px 2px 12px;
}
.customer-order-grid::-webkit-scrollbar { height: 8px; }
.customer-order-grid::-webkit-scrollbar-track { background: rgba(255, 255, 255, .04); border-radius: 8px; }
.customer-order-grid::-webkit-scrollbar-thumb { background: rgba(0, 229, 212, .48); border-radius: 8px; }
.customer-order-grid .customer-order-item {
    min-width: 0;
    min-height: 0;
    height: 100%;
    overflow: hidden;
    scroll-snap-align: start;
    padding: 14px;
}
.customer-order-item h3 {
    font-size: clamp(1rem, 1.15vw, 1.2rem);
    line-height: 1.3;
}
.customer-order-item p {
    font-size: .88rem;
    overflow-wrap: anywhere;
}
.customer-order-price {
    font-size: clamp(1.05rem, 1.3vw, 1.3rem);
}
.customer-order-carousel-control {
    position: absolute;
    z-index: 2;
    top: 50%;
    width: 38px;
    height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffd700;
    background: rgba(7, 12, 24, .94);
    border: 1px solid rgba(0, 229, 212, .55);
    border-radius: 10px;
    font-size: 1.7rem;
    cursor: pointer;
    transform: translateY(-50%);
    box-shadow: 0 5px 18px rgba(0, 0, 0, .4);
}
.customer-order-carousel-control:hover {
    color: #071018;
    background: #00e5d4;
}
.customer-order-carousel-control:disabled {
    opacity: .25;
    cursor: default;
}
.customer-order-carousel-control.prev { left: 7px; }
.customer-order-carousel-control.next { right: 7px; }
.customer-order-empty {
    grid-column: 1 / -1;
    align-self: center;
    color: #aeb5c4;
    text-align: center;
}
@media (min-width: 1600px) {
    .customer-order-page { width: min(2200px, calc(100% - 36px)); }
    .customer-order-grid { grid-auto-columns: calc((100% - 28px) / 3); }
}
@media (max-width: 1000px) {
    .customer-order-layout { grid-template-columns: minmax(165px, 1fr) minmax(0, 4fr); gap: 12px; }
    .customer-order-sidebar { padding: 9px; }
    .customer-order-products-panel { padding: 13px; }
    .customer-order-grid {
        grid-auto-columns: calc((100% - 14px) / 2);
        grid-template-rows: repeat(3, minmax(0, 1fr));
        height: 720px;
        min-height: 720px;
    }
}
@media (max-width: 700px) {
    .customer-order-page { width: calc(100% - 20px); margin-top: 14px; }
    .customer-order-layout { display: flex; flex-direction: column; }
    .customer-order-sidebar {
        position: static;
        width: 100%;
        max-height: none;
        overflow: visible;
        box-sizing: border-box;
    }
    .customer-order-section-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
        gap: 8px;
    }
    .customer-order-section-group > .customer-order-products-panel {
        grid-column: 1 / -1;
        width: 100%;
        min-width: 0;
        margin: 0 0 8px;
        box-sizing: border-box;
    }
    .customer-order-section-group > .customer-order-products-panel[hidden] {
        display: none !important;
    }
    .customer-order-section-choice {
        align-self: start;
    }
    .customer-order-section-choice { min-height: 42px; padding: 8px; }
    .customer-order-products { width: 100%; }
    .customer-order-products-panel { padding: 12px; }
    .customer-order-grid {
        grid-auto-flow: row;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        grid-template-rows: none;
        grid-auto-columns: auto;
        height: auto;
        min-height: 0;
        overflow: visible;
    }
    .customer-order-grid .customer-order-item {
        flex: 0 0 auto;
        height: auto;
        min-height: 0;
    }
    .customer-order-carousel-control { display: none; }
}
@media (max-width: 420px) {
    .customer-order-section-list { grid-template-columns: 1fr; }
    .customer-order-grid { grid-template-columns: 1fr; }
}

/* Fixed three-column menu header and viewport layout. */
.customer-order-page {
    width: min(1800px, calc(100% - 28px));
    height: calc(100vh - 115px);
    min-height: 560px;
    margin: 12px auto 18px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.customer-order-header {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 14px;
    flex: 0 0 auto;
    margin-bottom: 16px;
}
.customer-order-header-card {
    min-width: 0;
    min-height: 132px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 7px;
    padding: 15px 18px;
    background:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .12), transparent 55%),
        linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(0, 229, 212, .38);
    border-radius: 16px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, .3);
    box-sizing: border-box;
}
.customer-order-header-info {
    border-color: rgba(255, 215, 0, .52);
    height: 190px;
    min-height: 190px;
    display: grid;
    grid-template-rows: 40fr 30fr 15fr 15fr;
    align-content: stretch;
    justify-content: stretch;
    gap: 0;
    padding: 10px 18px;
}
.customer-order-header-info > * {
    min-width: 0;
    width: 100%;
    display: flex;
    /* align-items: center; */
    justify-content: center;
    text-align: center;
    overflow-wrap: anywhere;
    box-sizing: border-box;
}
.customer-order-header-organization {
    color: #fff1b0;
    font-family: Georgia, 'Palatino Linotype', 'Book Antiqua', serif;
    font-size: clamp(1.45rem, 2.15vw, 2.35rem);
    font-weight: 800;
    line-height: 1.08;
    text-align: center;
    overflow-wrap: anywhere;
    text-shadow: 0 0 14px rgba(255, 215, 0, .17);
}
.customer-order-header-menu-title {
    width: 100%;
    color: #ffd700;
    font-family: Georgia, 'Palatino Linotype', 'Book Antiqua', serif;
    font-size: clamp(1.8rem, 3vw, 3.1rem);
    font-weight: 900;
    letter-spacing: clamp(.12em, .42vw, .28em);
    line-height: 1;
    text-align: center;    
    white-space: nowrap;
    text-shadow: 0 0 16px rgba(255, 215, 0, .32);
    transform: scaleX(1.22);
    margin-top: 30px ;
    /* padding: 10px 15px; */
}
.customer-order-header-methods {
    color: #00e5d4;
    font-size: clamp(1rem, 1.35vw, 1.4rem);
    /* line-height: 1.2; */
    font-weight: 700;
    margin-top: 15px;
}
.customer-order-header-method {
    position: relative;
    display: inline-block;
    cursor: help;
    outline: none;
}
.customer-order-header-method:focus-visible {
    color: #ffd700;
    text-shadow: 0 0 10px rgba(255, 215, 0, .45);
}
.customer-order-header-tooltip {
    position: absolute;
    z-index: 30;
    top: calc(100% + 12px);
    left: 50%;
    width: max-content;
    max-width: calc(100vw - 32px);
    padding: 15px 18px;
    color: #eaf8f7;
    text-align: left;
    white-space: normal;
    background: linear-gradient(145deg, #101a2b, #080e1b);
    border: 1px solid rgba(255, 215, 0, .65);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, .55), 0 0 18px rgba(0, 229, 212, .12);
    font-family: Georgia, 'Palatino Linotype', 'Book Antiqua', serif;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.55;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translate(-50%, 6px);
    transition: opacity .18s ease, transform .18s ease, visibility .18s ease;
}
.customer-order-header-tooltip::before {
    content: "";
    position: absolute;
    left: 50%;
    bottom: 100%;
    border: 7px solid transparent;
    border-bottom-color: rgba(255, 215, 0, .65);
    transform: translateX(-50%);
}
.customer-order-header-method:hover .customer-order-header-tooltip,
.customer-order-header-method:focus .customer-order-header-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translate(-50%, 0);
}
.customer-order-header-method.customer-order-header-method-delivery .customer-order-header-tooltip {
    left: 0;
    width: max-content;
    max-width: min(340px, calc(100vw - 32px));
    box-sizing: border-box;
    transform: translate(0, 6px);
}
.customer-order-header-method.customer-order-header-method-delivery .customer-order-header-tooltip::before {
    left: 22px;
    transform: none;
}
.customer-order-header-method.customer-order-header-method-delivery:hover .customer-order-header-tooltip,
.customer-order-header-method.customer-order-header-method-delivery:focus .customer-order-header-tooltip {
    transform: translate(0, 0);
}
.customer-order-header-tooltip-line {
    display: block;
}
.customer-order-header-tooltip-line + .customer-order-header-tooltip-line {
    margin-top: 5px;
}
.customer-order-header-tooltip-accent {
    color: #ffd700;
}
.customer-order-header-delivery {
    color: #f4d77a;
    font-size: clamp(.95rem, 1.25vw, 1.3rem);
    font-weight: 800;
    line-height: 1.2;
    text-align: center;
}
.customer-order-header-action {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 100px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    color: #ffe9a0;
    text-align: center;
    text-decoration: none;
    background: linear-gradient(145deg, rgba(0, 229, 212, .11), rgba(255, 215, 0, .075));
    border: 1px solid rgba(255, 215, 0, .38);
    border-radius: 12px;
    box-sizing: border-box;
    transition: .2s ease;
}
.customer-order-header-action:hover {
    color: #fff;
    border-color: #00e5d4;
    background: linear-gradient(145deg, rgba(0, 229, 212, .2), rgba(255, 215, 0, .13));
    box-shadow: 0 0 22px rgba(0, 229, 212, .12);
}
.customer-order-header-action-title {
    font-family: Georgia, 'Palatino Linotype', 'Book Antiqua', serif;
    font-size: clamp(1.05rem, 1.5vw, 1.45rem);
    font-weight: 800;
    letter-spacing: .035em;
}
.customer-order-header-action-stats {
    width: 100%;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 9px 18px;
}
.customer-order-header-action-stat {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #bafaf3;
    font-size: .92rem;
    font-weight: 700;
}
.customer-order-header-action-count {
    min-width: 30px;
    min-height: 27px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 9px;
    color: #ffe9a0;
    background: linear-gradient(145deg, rgba(255, 215, 0, .08), rgba(0, 229, 212, .08));
    border: 1px solid rgba(255, 215, 0, .62);
    border-radius: 8px;
    font-size: .9rem;
    font-weight: 900;
    line-height: 1;
    box-shadow: inset 0 0 10px rgba(0, 229, 212, .06);
}
.customer-order-header-cart-total {
    color: #00e5d4;
    font-size: .95rem;
    font-weight: 800;
}
.customer-order-header-cart-total .customer-order-header-action-stat {
    color: #bafaf3;
}
.customer-order-header-cart-total strong {
    color: #ffe9a0;
    font-weight: 900;
}
.customer-order-layout {
    flex: 1 1 auto;
    min-height: 0;
    align-items: stretch;
    overflow: hidden;
}
.customer-order-sidebar {
    height: 100%;
    max-height: none;
    box-sizing: border-box;
}
.customer-order-products,
.customer-order-products-panel,
.customer-order-carousel {
    height: 100%;
    min-height: 0;
}
.customer-order-products-panel {
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    overflow: hidden;
}
.customer-order-products-heading {
    flex: 0 0 auto;
}
.customer-order-products-heading span {
    color: #f2d98a;
    font-family: Georgia, 'Palatino Linotype', 'Book Antiqua', serif;
    font-size: clamp(1rem, 1.35vw, 1.35rem);
    font-weight: 800;
    letter-spacing: .035em;
    text-shadow: 0 0 12px rgba(255, 215, 0, .16);
}
.customer-order-grid {
    height: 100%;
    min-height: 0;
    box-sizing: border-box;
}
@media (max-width: 1000px) {
    .customer-order-header {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 10px;
    }
    .customer-order-header-card {
        padding: 10px 8px;
    }
    .customer-order-header-action {
        padding: 8px 5px;
        gap: 6px;
    }
    .customer-order-header-action-stats {
        flex-direction: column;
        gap: 4px;
    }
    .customer-order-header-action-stat {
        flex-wrap: wrap;
        justify-content: center;
        gap: 4px;
        font-size: .78rem;
    }
    .customer-order-header-action-count {
        min-width: 22px;
        min-height: 22px;
        padding: 2px 5px;
        font-size: .75rem;
    }
    .customer-order-header-cart-total {
        font-size: .78rem;
    }
    .customer-order-page {
        height: auto;
        min-height: 0;
        overflow: visible;
    }
    .customer-order-layout {
        flex: none;
        overflow: visible;
    }
    .customer-order-sidebar,
    .customer-order-products,
    .customer-order-products-panel,
    .customer-order-carousel {
        height: auto;
    }
    .customer-order-products-panel { display: block; }
    .customer-order-grid { height: 720px; min-height: 720px; }
}
@media (max-width: 700px) {
    /* Mobile header: organization/menu full width, actions side by side. */
    .customer-order-header {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 9px;
    }
    .customer-order-header-info {
        grid-column: 1 / -1;
    }
    .customer-order-header-card:not(.customer-order-header-info) {
        min-width: 0;
    }
    .customer-order-header-card { min-height: 0; padding: 13px; }
    .customer-order-header-action { padding: 12px; gap: 8px; }
    .customer-order-header-action-stats { flex-direction: row; gap: 9px 18px; }
    .customer-order-header-action-stat { font-size: .92rem; gap: 7px; }
    .customer-order-header-action-count {
        min-width: 30px;
        min-height: 27px;
        padding: 2px 9px;
        font-size: .9rem;
    }
    .customer-order-header-cart-total { font-size: .95rem; }
    .customer-order-header-info {
        height: auto;
        min-height: 190px;
        grid-template-rows: minmax(62px, 40fr) minmax(48px, 30fr) minmax(34px, 15fr) minmax(38px, 15fr);
        padding: 10px 13px;
    }
    .customer-order-header-info > * { padding: 3px 0; }
    .customer-order-header-organization { font-size: clamp(1.35rem, 6vw, 1.8rem); }
    .customer-order-header-menu-title {
        font-size: clamp(1.35rem, 6vw, 2rem);
        letter-spacing: clamp(.1em, 1.2vw, .2em);
        transform: scaleX(1.08);
    }
    .customer-order-header-methods { font-size: clamp(.9rem, 3.5vw, 1.05rem); }
    .customer-order-header-delivery { font-size: clamp(.88rem, 3.3vw, 1rem); }
    .customer-order-header-action { min-height: 78px; }
    .customer-order-page { height: auto; overflow: visible; }
}


/* Use the full viewport for the customer catalog on desktop without vertical scrolling. */
@media (min-width: 1001px) {
    html:has(.customer-order-page),
    body:has(.customer-order-page) {
        height: 100%;
        overflow: hidden;
    }

    .customer-order-page {
        height: calc(100vh - 30px);
        min-height: 0;
        margin-top: 12px;
        margin-bottom: 18px;
    }

    .customer-order-sidebar {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .customer-order-section-list {
        flex: 1 1 auto;
        min-height: 0;
        justify-content: space-evenly;
    }

    .customer-order-section-choice {
        flex: 1 1 0;
        min-height: 0;
    }
}

/* Keep header tooltips above the category sidebar and product panels. */
.customer-order-header {
    position: relative;
    z-index: 50;
}
.customer-order-layout {
    position: relative;
    z-index: 1;
}

/* Full-page TV2 background for the customer catalog. */
body:has(.customer-order-page) {
    min-height: 100vh;
    background-color: #071018 !important;
    background-image:
        linear-gradient(180deg, rgba(4, 9, 16, .28), rgba(4, 9, 16, .48)),
        url('/images/tv2.png?v=2') !important;
    background-position: center center !important;
    background-size: cover !important;
    background-repeat: no-repeat !important;
    background-attachment: fixed !important;
}
.customer-order-page {
    background: transparent !important;
}
.customer-order-header-card,
.customer-order-sidebar {
    background:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .12), transparent 55%),
        linear-gradient(145deg, rgba(11, 12, 24, .82), rgba(20, 24, 42, .78) 58%, rgba(11, 12, 24, .84)) !important;
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
}
.customer-order-products-panel {
    background:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .10), transparent 45%),
        linear-gradient(145deg, rgba(17, 24, 39, .84), rgba(11, 16, 32, .80) 60%, rgba(18, 26, 43, .84)) !important;
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
}
.customer-order-item {
    background: rgba(12, 15, 28, .84) !important;
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
}
@media (max-width: 700px) {
    body:has(.customer-order-page) {
        background-attachment: scroll !important;
    }
}


/* Compact, centered mobile catalog header and controls. */
@media (max-width: 700px) {
    .customer-order-page {
        width: calc(100% - 16px);
        margin: 8px auto 18px;
    }

    .customer-order-header {
        width: 100%;
        justify-items: stretch;
        align-items: stretch;
        gap: 7px;
        margin-bottom: 10px;
    }

    .customer-order-header-info {
        height: auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        padding: 8px 10px;
        text-align: center;
    }

    .customer-order-header-info > * {
        width: 100%;
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        padding: 0;
        text-align: center;
        line-height: 1.15;
    }

    .customer-order-header-organization {
        font-size: clamp(1.15rem, 5.2vw, 1.55rem);
        line-height: 1.12;
    }

    .customer-order-header-menu-title {
        margin: 0;
        font-size: clamp(1.3rem, 5.8vw, 1.8rem);
        letter-spacing: .12em;
        line-height: 1.05;
        transform: none;
    }

    .customer-order-header-methods {
        margin: 0;
        font-size: clamp(.82rem, 3.4vw, .98rem);
        line-height: 1.25;
        text-align: center;
    }

    .customer-order-header-delivery {
        margin-top: 2px !important;
        font-size: .82rem;
        line-height: 1.2;
    }

    .customer-order-header-card:not(.customer-order-header-info) {
        min-height: 0;
        padding: 6px;
        text-align: center;
    }

    .customer-order-header-action {
        min-height: 68px;
        height: 100%;
        padding: 7px 5px;
        gap: 5px;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .customer-order-header-action-title {
        font-size: clamp(.9rem, 3.8vw, 1.05rem);
        line-height: 1.1;
    }

    .customer-order-header-action-stats {
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
    }

    .customer-order-header-action-stat {
        justify-content: center;
        gap: 5px;
        font-size: clamp(.68rem, 2.8vw, .8rem);
        line-height: 1.1;
    }

    .customer-order-header-action-count {
        min-width: 23px;
        min-height: 21px;
        padding: 2px 5px;
        font-size: .75rem;
    }

    .customer-order-header-cart-total {
        font-size: clamp(.68rem, 2.8vw, .8rem);
    }

    /* Restore the original left alignment of section headings and category rows. */
    .customer-order-sidebar-title {
        text-align: left;
    }

    .customer-order-products-heading {
        text-align: left;
        justify-content: space-between;
    }

    .customer-order-section-choice {
        justify-content: space-between;
        text-align: left;
    }

    .customer-order-section-choice-name {
        text-align: left;
    }

    .customer-order-section-choice-meta {
        margin-left: 0;
    }

    .customer-order-item {
        align-items: center;
        text-align: center;
    }

    .customer-order-item h3,
    .customer-order-item p,
    .customer-order-price {
        width: 100%;
        text-align: center;
    }

    .customer-order-add {
        justify-content: center;
        flex-wrap: wrap;
    }
}

@media (max-width: 420px) {
    .customer-order-header-info {
        padding: 7px 8px;
        gap: 2px;
    }

    .customer-order-header-organization {
        font-size: clamp(1.08rem, 5vw, 1.35rem);
    }

    .customer-order-header-menu-title {
        font-size: clamp(1.2rem, 5.6vw, 1.55rem);
    }

    .customer-order-header-methods {
        font-size: .78rem;
    }
}


/* Mobile category groups: keep the expanded section toggle sticky above its dishes. */
@media (max-width: 700px) {
    .customer-order-section-group {
        display: contents;
    }

    .customer-order-section-group.is-expanded {
        display: flex;
        flex-direction: column;
        grid-column: 1 / -1;
        gap: 8px;
        min-width: 0;
    }

    .customer-order-section-group > .customer-order-products-panel {
        display: block;
        width: 100%;
        height: auto;
        min-height: 0;
        max-height: none;
        margin: 0 0 8px;
        overflow: visible !important;
        box-sizing: border-box;
    }

    .customer-order-section-group > .customer-order-products-panel[hidden] {
        display: none !important;
    }

    .customer-order-section-group.is-expanded {
        align-self: stretch;
        overflow: visible !important;
    }

    .customer-order-section-group.is-expanded > .customer-order-section-choice {
        position: sticky !important;
        top: 8px !important;
        z-index: 100 !important;
        align-self: stretch;
        flex: 0 0 auto;
        background: linear-gradient(110deg, #0b2530, #182033);
        border-color: rgba(255, 215, 0, .85);
        box-shadow: 0 8px 22px rgba(0, 0, 0, .55), 0 0 16px rgba(0, 229, 212, .14);
    }

    .customer-order-products-heading {
        display: none !important;
    }

    .customer-order-carousel {
        width: 100%;
        height: auto;
        min-height: 0;
        max-height: none;
        overflow: visible;
    }

    .customer-order-grid {
        display: grid;
        grid-auto-flow: row;
        grid-template-rows: none;
        grid-auto-columns: auto;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow: visible !important;
        align-content: start;
    }

    .customer-order-grid .customer-order-item {
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow: visible !important;
    }
}

/* Center the complete group of customer catalog blocks on mobile.
   If its content is taller than the screen, normal page scrolling is preserved. */
@media (max-width: 700px) {
    .customer-order-page {
        position: static;
        left: auto;
        transform: none;
        width: min(680px, calc(100vw - 16px));
        max-width: calc(100vw - 16px);
        min-height: 100vh;
        min-height: 100dvh;
        margin: 0 auto;
        padding: 12px 0;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .customer-order-header,
    .customer-order-layout {
        width: 100%;
        margin-left: auto;
        margin-right: auto;
        box-sizing: border-box;
    }

    .customer-order-sidebar,
    .customer-order-products,
    .customer-order-products-panel {
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
    }
}


/* Dish cards: text on the left, reserved photo frame on the right, controls below. */
.customer-order-grid .customer-order-item {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: space-between;
    gap: 12px;
    padding: 17px;
    background: linear-gradient(135deg, rgba(22, 29, 48, .96), rgba(10, 15, 28, .98));
    border: 1px solid rgba(0, 229, 212, .27);
    border-radius: 15px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .035), 0 7px 20px rgba(0, 0, 0, .2);
}
.customer-order-item-main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 112px;
    align-items: start;
    gap: 12px;
    min-width: 0;
}
.customer-order-item-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    gap: 9px;
}
.customer-order-item-content > h3 {
    margin: 0;
    color: #fff;
    font-size: 1.12rem;
    font-weight: 800;
    line-height: 1.3;
    overflow-wrap: anywhere;
}
.customer-order-item-content > p {
    margin: 0;
    color: #aeb5c4;
    font-size: 1rem;
    line-height: 1.4;
    overflow-wrap: anywhere;
}
.customer-order-item-content > p + p {
    font-size: .92rem;
}
.customer-order-item-content .customer-order-price {
    margin: auto 0 0;
    padding-top: 3px;
    color: #ffd700;
    font-size: 1.12rem;
    font-weight: 800;
    white-space: nowrap;
}
.customer-order-item-image-placeholder {
    width: 112px;
    aspect-ratio: 1 / 1;
    box-sizing: border-box;
    border: 2px solid rgba(255, 215, 0, .78);
    border-radius: 11px;
    background: linear-gradient(145deg, rgba(255, 215, 0, .045), rgba(255, 255, 255, .012));
    box-shadow: inset 0 0 14px rgba(255, 215, 0, .045), 0 0 10px rgba(255, 215, 0, .06);
}
.customer-order-grid .customer-order-add {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: nowrap;
    gap: 10px;
    width: 100%;
    margin: 0;
}
.customer-order-grid .customer-quantity-stepper button {
    width: 40px;
    height: 43px;
    font-size: 1.35rem;
}
.customer-order-grid .customer-quantity-stepper input {
    width: 48px;
    min-height: 43px;
    font-size: 1.08rem;
}
.customer-order-grid .customer-order-button {
    min-height: 38px;
    padding: 7px 13px;
    font-size: .95rem;
}
.customer-order-add-disabled .customer-order-button {
    width: 100%;
}
@media (max-width: 700px) {
    .customer-order-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .customer-order-grid .customer-order-item {
        height: auto;
        min-height: 0;
        padding: 14px;
    }
    .customer-order-item-main {
        grid-template-columns: minmax(0, 1fr) 104px;
        gap: 10px;
    }
    .customer-order-item-image-placeholder {
        width: 104px;
    }
}
@media (max-width: 420px) {
    .customer-order-item-main {
        grid-template-columns: minmax(0, 1fr) 88px;
        gap: 8px;
    }
    .customer-order-item-image-placeholder {
        width: 88px;
    }
    .customer-order-grid .customer-order-add {
        gap: 8px;
    }
}

</style>

<div class="customer-order-page">
    @if(session('success'))
        <div class="customer-order-alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="customer-order-alert error">{{ session('error') }}</div>
    @endif

    <div class="customer-order-header">
        <section class="customer-order-header-card customer-order-header-info">
            <h1 class="customer-order-header-organization">{{ $organization->name }}</h1>
            <div class="customer-order-header-menu-title">НАШЕ МЕНЮ</div>
            <div class="customer-order-header-methods">
                @if($settings?->delivery_enabled)
                    <span class="customer-order-header-method customer-order-header-method-delivery" tabindex="0" aria-describedby="customer-order-delivery-tooltip">
                        Доставка
                        <span class="customer-order-header-tooltip" id="customer-order-delivery-tooltip" role="tooltip">
                            <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">
                                Доставка: {{ number_format((float) $settings->delivery_fee, 2, ',', ' ') }} {{ $currencySymbol }}
                            </span>
                            @if($settings->minimum_order !== null)
                                <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">
                                    Бесплатно от {{ number_format((float) $settings->minimum_order, 2, ',', ' ') }} {{ $currencySymbol }}
                                </span>
                            @endif
                        </span>
                    </span>
                @endif
                @if($settings?->pickup_enabled)
                    <span class="customer-order-header-method" tabindex="0" aria-describedby="customer-order-pickup-tooltip">
                        · Самовывоз
                        <span class="customer-order-header-tooltip" id="customer-order-pickup-tooltip" role="tooltip">
                            <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">Термбург, Парковая, 23</span>
                        </span>
                    </span>
                @endif
                @if($settings?->dine_in_enabled)
                    <span class="customer-order-header-method" tabindex="0" aria-describedby="customer-order-cafe-tooltip">
                        · В Ёлках термбурга
                        <span class="customer-order-header-tooltip" id="customer-order-cafe-tooltip" role="tooltip">
                            <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">Заказать в нашем кафе в Термбурге</span>
                        </span>
                    </span>
                @endif
            </div>
            @unless($canOrder)
                <div class="customer-order-header-delivery">Организация временно не принимает онлайн-заказы.</div>
            @endunless
        </section>

        <section class="customer-order-header-card">
            <a class="customer-order-header-action"
               href="{{ auth('customer')->check() ? route('customer.orders.index') : route('customer.login') }}">
                <span class="customer-order-header-action-title">Мои заказы</span>
                <span class="customer-order-header-action-stats">
                    <span class="customer-order-header-action-stat">
                        История <span class="customer-order-header-action-count">{{ $ordersHistoryCount }}</span>
                    </span>
                    <span class="customer-order-header-action-stat">
                        В работе <span class="customer-order-header-action-count">{{ $ordersInProgressCount }}</span>
                    </span>
                </span>
            </a>
        </section>

        <section class="customer-order-header-card">
            <a class="customer-order-header-action" href="{{ route('customer.cart') }}">
                <span class="customer-order-header-action-title">Корзина</span>
                <span class="customer-order-header-action-stats">
                    <span class="customer-order-header-action-stat">
                        Выбрано <span class="customer-order-header-action-count">{{ $cartCount }}</span>
                    </span>
                    <span class="customer-order-header-action-stat customer-order-header-cart-total">
                        Начислено <strong>{{ number_format((float) $cartSubtotal, 2, ',', ' ') }} {{ $currencySymbol }}</strong>
                    </span>
                </span>
            </a>
        </section>
    </div>

    @if($sections->isNotEmpty())
        <div class="customer-order-layout">
            <aside class="customer-order-sidebar" aria-label="Разделы меню">
                <div class="customer-order-sidebar-title">Разделы меню</div>
                <nav class="customer-order-section-list">
                    @foreach($sections as $section)
                        <button
                            type="button"
                            class="customer-order-section-choice"
                            data-section-select="{{ $section->id }}"
                            aria-controls="customer-order-products-{{ $section->id }}"
                            aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                        >
                            <span class="customer-order-section-choice-name">{{ $section->name }}</span>
                            <span class="customer-order-section-choice-meta">
                                <span class="customer-order-section-choice-count">{{ $section->activeItems->count() }}</span>
                                <span class="customer-order-section-choice-arrow" aria-hidden="true"></span>
                            </span>
                        </button>
                    @endforeach
                </nav>
            </aside>

            <div class="customer-order-products">
                @foreach($sections as $section)
                    <section
                        class="customer-order-products-panel"
                        id="customer-order-products-{{ $section->id }}"
                        data-section-panel="{{ $section->id }}"
                        @if(!$loop->first) hidden @endif
                    >
                        <div class="customer-order-products-heading">
                            <h2>{{ $section->name }}</h2>
                            <span>{{ $section->activeItems->count() }} блюд</span>
                        </div>

                        <div class="customer-order-carousel">
                            <button type="button" class="customer-order-carousel-control prev" data-carousel-prev aria-label="Предыдущие блюда">‹</button>
                            <div class="customer-order-grid" data-product-carousel>
                                @forelse($section->activeItems as $item)
                                    <article class="customer-order-item" id="menu-item-{{ $item->id }}">
                                        <div class="customer-order-item-main">
                                            <div class="customer-order-item-content">
                                                <h3>{{ $item->name }}</h3>

                                                @if($item->description)
                                                    <p>{{ $item->description }}</p>
                                                @endif

                                                @if($item->weight || $item->unit)
                                                    <p>{{ $item->weight }} {{ $item->unit }}</p>
                                                @endif

                                                <div class="customer-order-price">
                                                    {{ number_format((float) $item->price, 2, ',', ' ') }}
                                                    @if($settings?->currency)
                                                        {{ $currencySymbol }}
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="customer-order-item-image-placeholder" aria-label="Место для фотографии блюда"></div>
                                        </div>

                                        @if($canOrder)
                                            <form
                                                method="POST"
                                                action="{{ route('customer.cart.add', $organization) }}"
                                                class="customer-order-add"
                                            >
                                                @csrf
                                                <input type="hidden" name="item_id" value="{{ $item->id }}">
                                                <div class="customer-quantity-stepper">
                                                    <button type="button" data-quantity-change="-1" aria-label="Уменьшить количество">−</button>
                                                    <input type="number" name="quantity" value="1" min="1" max="99" aria-label="Количество">
                                                    <button type="button" data-quantity-change="1" aria-label="Увеличить количество">+</button>
                                                </div>
                                                <button type="submit" class="customer-order-button">Добавить</button>
                                            </form>
                                        @else
                                            <div class="customer-order-add customer-order-add-disabled">
                                                <button type="button" class="customer-order-button customer-order-disabled" disabled>
                                                    Заказ недоступен
                                                </button>
                                            </div>
                                        @endif
                                    </article>
                                @empty
                                    <p class="customer-order-empty">В этом разделе пока нет доступных блюд.</p>
                                @endforelse
                            </div>
                            <button type="button" class="customer-order-carousel-control next" data-carousel-next aria-label="Следующие блюда">›</button>
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @else
        <div class="customer-order-panel customer-order-muted">Меню пока пустое.</div>
    @endif
</div>

<script>
(() => {
    document.addEventListener('click', (event) => {
        const quantityButton = event.target.closest('[data-quantity-change]');
        if (quantityButton) {
            const input = quantityButton.closest('.customer-quantity-stepper')?.querySelector('input[name="quantity"]');
            if (input) {
                const current = Number.parseInt(input.value, 10) || 1;
                const change = Number.parseInt(quantityButton.dataset.quantityChange, 10) || 0;
                input.value = String(Math.min(99, Math.max(1, current + change)));
            }
            return;
        }

        const sectionButton = event.target.closest('[data-section-select]');
        if (sectionButton) {
            const sectionId = sectionButton.dataset.sectionSelect;
            const isExpanded = sectionButton.getAttribute('aria-expanded') === 'true';
            const shouldExpand = !isExpanded;

            document.querySelectorAll('[data-section-select]').forEach((button) => {
                const isActive = button === sectionButton && shouldExpand;
                button.setAttribute('aria-expanded', isActive ? 'true' : 'false');
                button.closest('[data-section-group]')?.classList.toggle('is-expanded', isActive);
            });
            document.querySelectorAll('[data-section-panel]').forEach((panel) => {
                panel.hidden = !shouldExpand || panel.dataset.sectionPanel !== sectionId;
            });
            const activePanel = document.querySelector('[data-section-panel="' + sectionId + '"]');
            const activeTrack = activePanel?.querySelector('[data-product-carousel]');
            if (shouldExpand && activeTrack) updateCarouselControls(activeTrack);
            return;
        }

        const carouselButton = event.target.closest('[data-carousel-prev], [data-carousel-next]');
        if (carouselButton) {
            const carousel = carouselButton.closest('.customer-order-carousel');
            const track = carousel?.querySelector('[data-product-carousel]');
            if (!track) return;
            const direction = carouselButton.hasAttribute('data-carousel-next') ? 1 : -1;
            track.scrollBy({ left: direction * track.clientWidth, behavior: 'smooth' });
        }
    });

    const updateCarouselControls = (track) => {
        const carousel = track.closest('.customer-order-carousel');
        if (!carousel) return;
        const prev = carousel.querySelector('[data-carousel-prev]');
        const next = carousel.querySelector('[data-carousel-next]');
        const maxScroll = track.scrollWidth - track.clientWidth;
        if (prev) prev.disabled = track.scrollLeft <= 2;
        if (next) next.disabled = track.scrollLeft >= maxScroll - 2;
        if (maxScroll <= 2) {
            if (prev) prev.hidden = true;
            if (next) next.hidden = true;
        } else {
            if (prev) prev.hidden = false;
            if (next) next.hidden = false;
        }
    };

    const sectionList = document.querySelector('.customer-order-section-list');
    const productsContainer = document.querySelector('.customer-order-products');
    const mobileLayout = window.matchMedia('(max-width: 700px)');

    const arrangeSectionPanels = () => {
        if (!sectionList || !productsContainer) return;

        document.querySelectorAll('[data-section-select]').forEach((button) => {
            const sectionId = button.dataset.sectionSelect;
            const panel = document.querySelector('[data-section-panel="' + sectionId + '"]');
            if (!panel) return;

            let group = button.closest('[data-section-group]');
            if (!group) {
                group = document.createElement('div');
                group.className = 'customer-order-section-group';
                group.dataset.sectionGroup = sectionId;
                button.parentNode.insertBefore(group, button);
                group.appendChild(button);
            }

            if (mobileLayout.matches) {
                group.appendChild(button);
                group.appendChild(panel);
                group.classList.toggle('is-expanded', button.getAttribute('aria-expanded') === 'true');
            } else {
                sectionList.appendChild(group);
                productsContainer.appendChild(panel);
                group.classList.remove('is-expanded');
            }
        });
    };

    arrangeSectionPanels();

    document.querySelectorAll('[data-product-carousel]').forEach((track) => {
        track.addEventListener('scroll', () => updateCarouselControls(track), { passive: true });
        updateCarouselControls(track);
    });

    const handleLayoutChange = () => {
        arrangeSectionPanels();
        document.querySelectorAll('[data-product-carousel]').forEach(updateCarouselControls);
    };

    if (typeof mobileLayout.addEventListener === 'function') {
        mobileLayout.addEventListener('change', handleLayoutChange);
    } else {
        mobileLayout.addListener(handleLayoutChange);
    }

    window.addEventListener('resize', handleLayoutChange);
})();
</script>
@endsection
