@extends('template')

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

/* Фирменные всплывающие уведомления клиентского меню. */
.customer-order-toast {
    position: fixed;
    top: 18px;
    right: 20px;
    z-index: 2000;
    display: flex;
    align-items: center;
    gap: 13px;
    width: min(410px, calc(100vw - 32px));
    padding: 15px 46px 17px 16px;
    color: #f4f4f4;
    background:
        radial-gradient(circle at 0 0, rgba(0, 229, 212, .16), transparent 48%),
        linear-gradient(135deg, #101522 0%, #171b2b 55%, #0b0c18 100%);
    border: 1px solid rgba(0, 229, 212, .58);
    border-left: 3px solid #00e5d4;
    border-radius: 13px;
    box-shadow: 0 14px 38px rgba(0, 0, 0, .48), 0 0 22px rgba(0, 229, 212, .12);
    overflow: hidden;
    animation: customer-toast-enter .32s ease-out both;
}
.customer-order-toast[hidden] { display: none !important; }
.customer-order-toast-icon {
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    color: #071817;
    background: linear-gradient(145deg, #65fff0, #00cbbb);
    border: 1px solid rgba(255, 215, 0, .7);
    border-radius: 50%;
    box-shadow: 0 0 15px rgba(0, 229, 212, .32);
    font-size: 19px;
    font-weight: 900;
}
.customer-order-toast-message {
    min-width: 0;
    color: #f4f4f4;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    line-height: 1.5;
    font-weight: 600;
}
.customer-order-toast-title {
    display: block;
    margin-bottom: 2px;
    color: #ffd700;
    font-family: 'Crimson Text', serif;
    font-size: 17px;
    line-height: 1.15;
    letter-spacing: .35px;
}
.customer-order-toast-close {
    position: absolute;
    top: 7px;
    right: 9px;
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    padding: 0;
    color: rgba(220, 230, 240, .7);
    background: transparent;
    border: 0;
    border-radius: 50%;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
}
.customer-order-toast-close:hover {
    color: #ffd700;
    background: rgba(255, 215, 0, .1);
}
.customer-order-toast-progress {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    height: 2px;
    background: rgba(0, 229, 212, .14);
}
.customer-order-toast-progress::after {
    content: '';
    display: block;
    width: 100%;
    height: 100%;
    transform-origin: left;
    background: linear-gradient(90deg, #00e5d4, #ffd700);
    animation: customer-toast-progress 4.8s linear forwards;
}
.customer-order-toast.error {
    border-color: rgba(255, 100, 100, .62);
    border-left-color: #ff6969;
    background:
        radial-gradient(circle at 0 0, rgba(255, 80, 80, .16), transparent 48%),
        linear-gradient(135deg, #17121b 0%, #211824 55%, #100d16 100%);
}
.customer-order-toast.error .customer-order-toast-icon {
    color: #fff;
    background: linear-gradient(145deg, #ff9999, #d94355);
    border-color: rgba(255, 215, 0, .35);
    box-shadow: 0 0 15px rgba(255, 80, 80, .22);
}
.customer-order-toast.error .customer-order-toast-title { color: #ff9b9b; }
@keyframes customer-toast-enter {
    from { opacity: 0; transform: translate3d(18px, -8px, 0) scale(.97); }
    to { opacity: 1; transform: translate3d(0, 0, 0) scale(1); }
}
@keyframes customer-toast-progress {
    from { transform: scaleX(1); }
    to { transform: scaleX(0); }
}
@media (max-width: 700px) {
    .customer-order-toast {
        top: 12px;
        right: 12px;
        left: 12px;
        width: auto;
        padding: 13px 42px 15px 13px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .customer-order-toast,
    .customer-order-toast-progress::after {
        animation: none !important;
    }
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
    display: none !important;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 14px;
}
.customer-order-products-heading h2 {
    display: none !important;
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
    grid-auto-columns: calc((100% - 42px) / 4);
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
    .customer-order-grid { grid-auto-columns: calc((100% - 42px) / 4); }
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
    grid-template-rows: 30fr 60fr 10fr;
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
    margin-top: 0;
    /* padding: 10px 15px; */
}
.customer-order-header-methods {
    color: #00e5d4;
    font-size: clamp(1rem, 1.35vw, 1.4rem);
    /* line-height: 1.2; */
    font-weight: 700;
    margin-top: 0;
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



/* Каталог на мобильных должен прокручиваться документом, а не внутренним main.
   Иначе sticky привязывается к отдельной области прокрутки и кнопка не держится у верха экрана. */
@media (max-width: 700px) {
    .customer-workspace-catalog .customer-workspace-content {
        height: auto !important;
        min-height: 0 !important;
        overflow-x: visible !important;
        overflow-y: visible !important;
        overscroll-behavior-y: auto;
        -webkit-overflow-scrolling: auto;
    }
}

/* Mobile category groups: keep the expanded section toggle pinned above its dishes. */
@media (max-width: 700px) {
    /* Sticky category must follow the document viewport, not an inner clipped container. */
    .customer-order-page,
    .customer-order-layout,
    .customer-order-sidebar,
    .customer-order-section-list,
    .customer-order-section-group {
        overflow: visible !important;
    }

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
        display: flex;
        flex-direction: column;
        overflow: visible !important;
        padding-top: 0;
        box-sizing: border-box;
    }

    .customer-order-section-group.is-expanded > .customer-order-section-choice {
        position: relative;
        z-index: 2;
        align-self: flex-start !important;
        flex: 0 0 auto;
        width: 100%;
        min-height: 42px;
        margin: 0;
        box-sizing: border-box;
        background: linear-gradient(110deg, #0b2530, #182033) !important;
        border-color: rgba(255, 215, 0, .9) !important;
        box-shadow: 0 8px 22px rgba(0, 0, 0, .65), 0 0 18px rgba(0, 229, 212, .2);
    }

    /* Закреплённая кнопка находится непосредственно в body, вне sidebar и backdrop-filter. */
    .customer-order-section-choice.is-scroll-pinned,
    .customer-order-section-choice.is-scroll-pinned[aria-expanded="true"] {
        position: fixed !important;
        top: env(safe-area-inset-top, 0px) !important;
        left: var(--customer-pinned-left, 0px) !important;
        width: var(--customer-pinned-width, 100%) !important;
        min-height: 48px !important;
        z-index: 2147483000 !important;
        display: flex !important;
        align-items: center !important;
        margin: 0 !important;
        padding: 10px 14px !important;
        box-sizing: border-box !important;
        color: #ffe36b !important;
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        background: linear-gradient(110deg, #071923 0%, #0d2b38 52%, #172238 100%) !important;
        background-color: #0d2431 !important;
        border: 1px solid rgba(255, 215, 0, .95) !important;
        border-left: 4px solid #00e5d4 !important;
        border-radius: 0 0 12px 12px !important;
        box-shadow: 0 9px 28px rgba(0, 0, 0, .88), 0 0 0 1px rgba(0, 229, 212, .3), 0 0 22px rgba(0, 229, 212, .28) !important;
        text-shadow: 0 1px 3px rgba(0, 0, 0, .9) !important;
        transform: none !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
    }

    .customer-order-section-choice.is-scroll-pinned .customer-order-section-choice-name,
    .customer-order-section-choice.is-scroll-pinned .customer-order-section-choice-meta,
    .customer-order-section-choice.is-scroll-pinned .customer-order-section-choice-arrow {
        color: #ffe36b !important;
        opacity: 1 !important;
        visibility: visible !important;
    }

    .customer-order-section-choice.is-scroll-pinned .customer-order-section-choice-count {
        color: #c9fff8 !important;
        background: #123c46 !important;
        border-color: rgba(0, 229, 212, .7) !important;
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
        height: auto !important;
        min-height: 100vh;
        min-height: 100dvh;
        max-height: none !important;
        margin: 0 auto;
        padding: 12px 0;
        box-sizing: border-box;
        display: flex;
        flex: 0 0 auto;
        flex-direction: column;
        justify-content: flex-start;
        overflow: visible !important;
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
.customer-order-grid .customer-order-item-title {
    display: block;
    width: 100%;
    margin: 0 0 10px !important;
    color: #fff;
    font-size: 1.12rem;
    font-weight: 800;
    line-height: 1.3;
    text-align: center;
    overflow-wrap: anywhere;
}
.customer-order-item-main {
    display: flow-root;
    min-width: 0;
}
.customer-order-item-content {
    display: block;
    min-width: 0;
}
.customer-order-item-image-placeholder {
    float: right;
    margin: 0 0 10px 12px;
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
/* Dedicated metadata row: weight left, price right, directly above the controls. */
.customer-order-grid .customer-order-item-meta {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) auto !important;
    align-items: center !important;
    justify-content: stretch !important;
    flex-wrap: initial !important;
    gap: 12px !important;
    width: 100% !important;
    min-width: 0;
    margin: 0 !important;
    padding: 8px 2px 2px !important;
    border-top: 1px solid rgba(255, 215, 0, .22);
}
.customer-order-grid .customer-order-item-weight {
    display: block !important;
    min-width: 0;
    color: #aeb5c4;
    font-size: .95rem;
    line-height: 1.3;
    text-align: left;
    overflow-wrap: anywhere;
}
.customer-order-grid .customer-order-item-meta .customer-order-price {
    display: block !important;
    justify-self: end;
    margin: 0 !important;
    padding: 0 !important;
    color: #ffd700;
    font-size: 1.12rem;
    font-weight: 800;
    line-height: 1.3;
    text-align: right;
    white-space: nowrap;
}
.customer-order-item-image-placeholder {
    float: right;
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
    justify-content: space-between !important;
    flex-wrap: nowrap;
    gap: 10px;
    width: 100%;
    margin: 0;
}
.customer-order-grid .customer-order-add .customer-quantity-stepper {
    flex: 0 0 auto;
    margin-right: auto;
}
.customer-order-grid .customer-order-add .customer-order-button {
    flex: 0 0 auto;
    margin-left: auto;
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
    .customer-order-item-image-placeholder {
        width: 104px;
        margin-left: 10px;
    }
}
@media (max-width: 420px) {
    .customer-order-item-image-placeholder {
        width: 88px;
        margin-left: 8px;
    }
    .customer-order-grid .customer-order-add {
        gap: 8px;
    }
}


/* Desktop carousel: paginate dishes in fixed groups of eight (4 columns x 2 rows). */
.customer-order-grid[data-product-carousel] {
    display: flex !important;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: 0 !important;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x mandatory;
    scroll-padding: 0;
}
.customer-order-carousel-page {
    flex: 0 0 100%;
    width: 100%;
    min-width: 0;
    height: 100%;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    grid-template-rows: repeat(2, minmax(0, 1fr));
    gap: 14px;
    box-sizing: border-box;
    scroll-snap-align: start;
}
.customer-order-carousel-page .customer-order-item {
    width: auto;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    scroll-snap-align: none;
}
@media (max-width: 700px) {
    .customer-order-grid[data-product-carousel] {
        display: grid !important;
        grid-auto-flow: row;
        grid-template-columns: 1fr;
        grid-template-rows: none;
        gap: 12px;
        height: auto !important;
        min-height: 0 !important;
        overflow: visible !important;
    }
    .customer-order-carousel-page {
        display: contents;
    }
}


/* Layer mobile header tooltips above cards and speed up menu interactions. */
.customer-order-header {
    position: relative;
    z-index: 500;
}
.customer-order-header-info {
    position: relative;
    z-index: 501;
    overflow: visible;
}
.customer-order-header-methods {
    position: relative;
    z-index: 502;
}
.customer-order-header-tooltip {
    z-index: 1000;
    transition: opacity .1s ease, transform .1s ease, visibility .1s ease;
}
.customer-order-section-choice,
.customer-order-section-choice-arrow,
.customer-order-section > summary,
.customer-order-section-chevron,
.customer-order-section {
    transition-duration: .12s !important;
}
.customer-order-grid[data-product-carousel] {
    scroll-behavior: smooth;
    scroll-snap-type: x mandatory;
}
.customer-order-carousel-page {
    scroll-snap-align: start;
    scroll-snap-stop: always;
}
@media (max-width: 700px) {
    .customer-order-header {
        z-index: 500;
    }
    .customer-order-header-info {
        z-index: 501;
        overflow: visible !important;
    }
    .customer-order-header-methods {
        z-index: 502;
    }
    .customer-order-header-tooltip {
        z-index: 1000;
        max-width: calc(100vw - 28px);
        white-space: normal;
    }
}



/* Общая рабочая область клиентского кабинета. */
.customer-workspace-content {
    min-width: 0;
    min-height: 0;
    height: 100%;
    overflow-y: auto;
    padding: 2px 5px 18px;
    box-sizing: border-box;
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 229, 212, .45) transparent;
}
.customer-workspace-other .customer-order-layout {
    grid-template-columns: minmax(190px, 1fr) minmax(0, 4fr);
    min-height: 0;
    flex: 1 1 auto;
    overflow: hidden;
}
.customer-workspace-other .customer-order-sidebar {
    position: sticky;
    top: 0;
    align-self: start;
}
.customer-workspace-other .customer-order-products {
    height: 100%;
    min-height: 0;
    overflow: hidden;
}
.customer-workspace-other .customer-cart-page,
.customer-workspace-other .customer-orders-page,
.customer-workspace-other .customer-checkout-page,
.customer-workspace-other .customer-order-detail {
    width: 100%;
    max-width: none;
    margin: 0 auto;
}
.customer-workspace-other .customer-cart-panel,
.customer-workspace-other .customer-orders-panel,
.customer-workspace-other .customer-checkout-panel,
.customer-workspace-other .customer-order-detail-panel {
    margin-bottom: 13px;
}
.customer-order-header-card.is-active {
    border-color: rgba(255, 215, 0, .85);
    box-shadow: 0 0 0 1px rgba(255, 215, 0, .18), 0 10px 28px rgba(0, 0, 0, .3), 0 0 18px rgba(255, 215, 0, .09);
}
.customer-order-header-card.is-active .customer-order-header-action-title { color: #ffd700; }
@media (max-width: 700px) {
    .customer-workspace-other {
        width: calc(100% - 20px);
        height: auto;
        min-height: 0;
        margin: 14px auto 30px;
        overflow: visible;
    }
    .customer-workspace-other .customer-order-header,
    .customer-workspace-other .customer-order-sidebar { display: none !important; }
    .customer-workspace-other .customer-order-layout {
        display: block;
        overflow: visible;
    }
    .customer-workspace-other .customer-order-products {
        height: auto;
        overflow: visible;
    }
    .customer-workspace-other .customer-workspace-content {
        height: auto;
        overflow: visible;
        padding: 0;
    }
    .customer-workspace-other .customer-cart-page,
    .customer-workspace-other .customer-orders-page,
    .customer-workspace-other .customer-checkout-page,
    .customer-workspace-other .customer-order-detail {
        width: 100%;
        margin: 0 auto;
    }
}
/* Checkout and other customer pages scroll inside the central work area.
   The fixed header and category sidebar remain in place. */
.customer-workspace-other .customer-order-products.customer-workspace-content {
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
}
</style>

{{-- Каталог уже получает эти данные от контроллера. Используем их как fallback,
     если общий composer не смог определить организацию для шапки. --}}
@php
    if (!isset($customerWorkspace) && isset($organization)) {
        $customerWorkspace = [
            'organization' => $organization,
            'settings' => $settings ?? null,
            'sections' => $sections ?? collect(),
            'canOrder' => $canOrder ?? false,
            'cartCount' => $cartCount ?? 0,
            'cartSubtotal' => $cartSubtotal ?? 0,
            'ordersHistoryCount' => $ordersHistoryCount ?? 0,
            'ordersInProgressCount' => $ordersInProgressCount ?? 0,
            'currencySymbol' => $currencySymbol ?? '',
        ];
    }
@endphp

@if(isset($customerWorkspace))
<div class="customer-order-page {{ request()->routeIs('customer.catalog') ? 'customer-workspace-catalog' : 'customer-workspace-other' }}">
    @if(session('success') || session('error'))
        @php($toastIsError = session()->has('error'))
        <div
            class="customer-order-toast{{ $toastIsError ? ' error' : '' }}"
            role="{{ $toastIsError ? 'alert' : 'status' }}"
            aria-live="{{ $toastIsError ? 'assertive' : 'polite' }}"
            data-customer-toast
        >
            <span class="customer-order-toast-icon" aria-hidden="true">{{ $toastIsError ? '!' : '✓' }}</span>
            <span class="customer-order-toast-message">
                <span class="customer-order-toast-title">{{ $toastIsError ? 'Внимание' : 'Готово' }}</span>
                {{ session('error') ?: session('success') }}
            </span>
            <button type="button" class="customer-order-toast-close" data-customer-toast-close aria-label="Закрыть уведомление">×</button>
            <span class="customer-order-toast-progress" aria-hidden="true"></span>
        </div>
    @endif

    <div class="customer-order-header">
        <section class="customer-order-header-card customer-order-header-info">
            <h1 class="customer-order-header-organization">{{ $customerWorkspace['organization']->name }}</h1>
            <div class="customer-order-header-menu-title">НАШЕ МЕНЮ</div>
            <div class="customer-order-header-methods">
                @if($customerWorkspace['settings']?->delivery_enabled)
                    <span class="customer-order-header-method customer-order-header-method-delivery" tabindex="0" aria-describedby="customer-order-delivery-tooltip">
                        Доставка
                        <span class="customer-order-header-tooltip" id="customer-order-delivery-tooltip" role="tooltip">
                            <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">
                                Доставка: {{ number_format((float) $customerWorkspace['settings']->delivery_fee, 2, ',', ' ') }} {{ $customerWorkspace['currencySymbol'] }}
                            </span>
                            @if($customerWorkspace['settings']->minimum_order !== null)
                                <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">
                                    Бесплатно от {{ number_format((float) $customerWorkspace['settings']->minimum_order, 2, ',', ' ') }} {{ $customerWorkspace['currencySymbol'] }}
                                </span>
                            @endif
                        </span>
                    </span>
                @endif
                @if($customerWorkspace['settings']?->pickup_enabled)
                    <span class="customer-order-header-method" tabindex="0" aria-describedby="customer-order-pickup-tooltip">
                        · Самовывоз
                        <span class="customer-order-header-tooltip" id="customer-order-pickup-tooltip" role="tooltip">
                            <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">Термбург, Парковая, 23</span>
                        </span>
                    </span>
                @endif
                @if($customerWorkspace['settings']?->dine_in_enabled)
                    <span class="customer-order-header-method" tabindex="0" aria-describedby="customer-order-cafe-tooltip">
                        · В Ёлках термбурга
                        <span class="customer-order-header-tooltip" id="customer-order-cafe-tooltip" role="tooltip">
                            <span class="customer-order-header-tooltip-line customer-order-header-tooltip-accent">Заказать в нашем кафе в Термбурге</span>
                        </span>
                    </span>
                @endif
            </div>
            @unless($customerWorkspace['canOrder'])
                <div class="customer-order-header-delivery">Организация временно не принимает онлайн-заказы.</div>
            @endunless
        </section>

        <section class="customer-order-header-card {{ request()->routeIs('customer.orders.*') ? 'is-active' : '' }}">
            <a class="customer-order-header-action" href="{{ auth('customer')->check() ? route('customer.orders.index') : route('customer.login') }}">
                <span class="customer-order-header-action-title">Мои заказы</span>
                <span class="customer-order-header-action-stats">
                    <span class="customer-order-header-action-stat">История <span class="customer-order-header-action-count">{{ $customerWorkspace['ordersHistoryCount'] }}</span></span>
                    <span class="customer-order-header-action-stat">В работе <span class="customer-order-header-action-count">{{ $customerWorkspace['ordersInProgressCount'] }}</span></span>
                </span>
            </a>
        </section>

        <section class="customer-order-header-card {{ request()->routeIs('customer.cart') || request()->routeIs('customer.checkout') ? 'is-active' : '' }}">
            <a class="customer-order-header-action" href="{{ route('customer.cart') }}">
                <span class="customer-order-header-action-title">Корзина</span>
                <span class="customer-order-header-action-stats">
                    <span class="customer-order-header-action-stat">Выбрано <span class="customer-order-header-action-count">{{ $customerWorkspace['cartCount'] }}</span></span>
                    <span class="customer-order-header-action-stat customer-order-header-cart-total">Начислено <strong>{{ number_format((float) $customerWorkspace['cartSubtotal'], 2, ',', ' ') }} {{ $customerWorkspace['currencySymbol'] }}</strong></span>
                </span>
            </a>
        </section>
    </div>

    @if(request()->routeIs('customer.catalog'))
        @yield('customer-content')
    @else
        <div class="customer-order-layout">
            <aside class="customer-order-sidebar" aria-label="Разделы меню">
    <div class="customer-order-sidebar-title">Разделы меню</div>
    <nav class="customer-order-section-list">
        @foreach($customerWorkspace['sections'] as $section)
            @if(request()->routeIs('customer.catalog'))
                <button type="button" class="customer-order-section-choice"
                    data-section-select="{{ $section->id }}"
                    aria-controls="customer-order-products-{{ $section->id }}"
                    aria-expanded="{{ request()->query('section') == $section->id || (!request()->has('section') && $loop->first) ? 'true' : 'false' }}">
            @else
                <a class="customer-order-section-choice"
                    href="{{ route('customer.catalog', $customerWorkspace['organization']) }}?section={{ $section->id }}"
                    style="text-decoration:none;">
            @endif
                <span class="customer-order-section-choice-name">{{ $section->name }}</span>
                <span class="customer-order-section-choice-meta">
                    <span class="customer-order-section-choice-count">{{ $section->activeItems->count() }}</span>
                    <span class="customer-order-section-choice-arrow" aria-hidden="true"></span>
                </span>
            @if(request()->routeIs('customer.catalog'))
                </button>
            @else
                </a>
            @endif
        @endforeach
    </nav>
</aside>
            <main class="customer-order-products customer-workspace-content">
                @yield('customer-content')
            </main>
        </div>
    @endif
</div>
@else
    @yield('customer-content')
@endif

<script>
(() => {
    const toast = document.querySelector('[data-customer-toast]');
    if (!toast) return;

    let dismissTimer = window.setTimeout(dismissToast, 4800);

    function dismissToast() {
        if (!toast.isConnected) return;
        toast.style.transition = 'opacity .22s ease, transform .22s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translate3d(12px, -6px, 0)';
        window.setTimeout(() => toast.remove(), 240);
    }

    toast.addEventListener('mouseenter', () => window.clearTimeout(dismissTimer));
    toast.addEventListener('mouseleave', () => {
        window.clearTimeout(dismissTimer);
        dismissTimer = window.setTimeout(dismissToast, 1800);
    });

    toast.querySelector('[data-customer-toast-close]')?.addEventListener('click', () => {
        window.clearTimeout(dismissTimer);
        dismissToast();
    });
})();
</script>
@endsection