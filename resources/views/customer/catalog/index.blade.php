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
        max-height: 240px;
        box-sizing: border-box;
    }
    .customer-order-section-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
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
    .customer-order-grid .customer-order-item { height: auto; min-height: 250px; }
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
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
    align-items: center;
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
    color: #ffd700;
    font-family: Georgia, 'Palatino Linotype', 'Book Antiqua', serif;
    font-size: clamp(1.15rem, 1.65vw, 1.75rem);
    font-weight: 900;
    letter-spacing: .2em;
    text-shadow: 0 0 12px rgba(255, 215, 0, .25);
}
.customer-order-header-methods {
    position: relative;
    color: #bafaf3;
    font-size: clamp(1rem, 1.35vw, 1.4rem);
    line-height: 1.2;
    font-weight: 700;
    cursor: help;
}
.customer-order-header-tooltip {
    position: absolute;
    z-index: 30;
    top: calc(100% + 12px);
    left: 50%;
    width: min(390px, calc(100vw - 48px));
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
.customer-order-header-methods:hover .customer-order-header-tooltip,
.customer-order-header-methods:focus-within .customer-order-header-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translate(-50%, 0);
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
.customer-order-header-action-count {
    min-width: 34px;
    min-height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 12px;
    color: #081016;
    background: linear-gradient(135deg, #ffe28a, #ffd700);
    border-radius: 20px;
    font-size: .9rem;
    font-weight: 900;
    box-shadow: 0 0 15px rgba(255, 215, 0, .18);
}
.customer-order-header-cart-total {
    color: #00e5d4;
    font-size: .95rem;
    font-weight: 800;
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
    .customer-order-header { grid-template-columns: 1fr; gap: 9px; }
    .customer-order-header-card { min-height: 0; padding: 13px; }
    .customer-order-header-info {
        height: auto;
        min-height: 190px;
        grid-template-rows: minmax(62px, 40fr) minmax(48px, 30fr) minmax(34px, 15fr) minmax(38px, 15fr);
        padding: 10px 13px;
    }
    .customer-order-header-info > * { padding: 3px 0; }
    .customer-order-header-organization { font-size: clamp(1.35rem, 6vw, 1.8rem); }
    .customer-order-header-menu-title { font-size: clamp(1.05rem, 4.5vw, 1.35rem); }
    .customer-order-header-methods { font-size: clamp(.9rem, 3.5vw, 1.05rem); }
    .customer-order-header-delivery { font-size: clamp(.88rem, 3.3vw, 1rem); }
    .customer-order-header-action { min-height: 78px; }
    .customer-order-page { height: auto; overflow: visible; }
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
            <div class="customer-order-header-methods" tabindex="0" aria-describedby="customer-order-header-tooltip">
                @if($settings?->delivery_enabled)Доставка@endif
                @if($settings?->pickup_enabled) · Самовывоз@endif
                @if($settings?->dine_in_enabled) · В Ёлках термбурга@endif

                <span class="customer-order-header-tooltip" id="customer-order-header-tooltip" role="tooltip">
                    @if($settings?->delivery_enabled)
                        <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">
                            Доставка: {{ number_format((float) $settings->delivery_fee, 2, ',', ' ') }} {{ $currencySymbol }}
                            @if($settings->minimum_order !== null)
                                · бесплатно от {{ number_format((float) $settings->minimum_order, 2, ',', ' ') }} {{ $currencySymbol }}
                            @endif
                        </span>
                    @endif
                    <span class="customer-order-header-tooltip-line">Из Термбурга, Парковая, 23</span>
                    <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">Заказать в нашем кафе в Термбурге</span>
                    @if($settings?->pickup_enabled && $settings->pickup_min_minutes !== null)
                        <span class="customer-order-header-tooltip-line">Самовывоз: от {{ $settings->pickup_min_minutes }} мин.</span>
                    @endif
                </span>
            </div>
            @unless($canOrder)
                <div class="customer-order-header-delivery">Организация временно не принимает онлайн-заказы.</div>
            @endunless
        </section>

        <section class="customer-order-header-card">
            <a class="customer-order-header-action"
               href="{{ auth('customer')->check() ? route('customer.orders.index') : route('customer.login') }}">
                <span class="customer-order-header-action-title">Мои заказы</span>
                <span class="customer-order-header-action-count">{{ $ordersCount }}</span>
                <span class="customer-order-header-methods">Всего заказов в этой организации</span>
            </a>
        </section>

        <section class="customer-order-header-card">
            <a class="customer-order-header-action" href="{{ route('customer.cart') }}">
                <span class="customer-order-header-action-title">🛒 Корзина</span>
                <span class="customer-order-header-action-count">{{ $cartCount }} товаров</span>
                <span class="customer-order-header-cart-total">
                    {{ number_format((float) $cartSubtotal, 2, ',', ' ') }} {{ $currencySymbol }}
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
                                            <button type="button" class="customer-order-button customer-order-disabled" disabled>
                                                Заказ недоступен
                                            </button>
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
            document.querySelectorAll('[data-section-select]').forEach((button) => {
                const active = button === sectionButton;
                button.setAttribute('aria-expanded', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-section-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.sectionPanel !== sectionId;
            });
            const activePanel = document.querySelector('[data-section-panel="' + sectionId + '"]');
            const activeTrack = activePanel?.querySelector('[data-product-carousel]');
            if (activeTrack) updateCarouselControls(activeTrack);
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

    document.querySelectorAll('[data-product-carousel]').forEach((track) => {
        track.addEventListener('scroll', () => updateCarouselControls(track), { passive: true });
        updateCarouselControls(track);
    });

    window.addEventListener('resize', () => {
        document.querySelectorAll('[data-product-carousel]').forEach(updateCarouselControls);
    });
})();
</script>
@endsection
