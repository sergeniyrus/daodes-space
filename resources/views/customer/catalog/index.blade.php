@extends('customer.layouts.workspace')

@section('title_page')
    Меню — {{ $organization->name }}
@endsection

@section('customer-content')






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
                                        <div class="customer-order-item-heading">
                                            <h3 class="customer-order-item-title">{{ $item->name }}</h3>
                                            @if($item->description)
                                                <button type="button"
                                                    class="customer-order-item-composition"
                                                    data-composition-tooltip="{{ strip_tags($item->description) }}"
                                                    aria-label="Показать состав блюда: {{ $item->name }}"
                                                    aria-describedby="customer-composition-tooltip">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <circle cx="12" cy="12" r="9"></circle>
                                                        <path d="M12 11v5"></path>
                                                        <path d="M12 8h.01"></path>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>

                                        <div class="customer-order-item-main">
                                            <button type="button" class="customer-order-item-image-button" data-menu-image-open data-image-src="{{ $item->image_path ? asset($item->image_path) : asset('images/org/' . $organization->id . '/not-img.png') }}" aria-label="Увеличить фотографию: {{ $item->name }}">
                                                <img src="{{ $item->image_path ? asset($item->image_path) : asset('images/org/' . $organization->id . '/not-img.png') }}" alt="{{ $item->name }}" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('images/org/' . $organization->id . '/not-img.png') }}';">
                                            </button>

                                            <div class="customer-order-item-content">
                                                @if($item->description)
                                                    <div class="customer-order-item-description">
                                                        <p>{{ $item->description }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="customer-order-item-meta">
                                            @if($item->weight || $item->unit)
                                                <span class="customer-order-item-weight">{{ $item->weight }} {{ $item->unit }}</span>
                                            @endif
                                            <div class="customer-order-price">
                                                {{ number_format((float) $item->price, 2, ',', ' ') }}
                                                @if($settings?->currency)
                                                    {{ $currencySymbol }}
                                                @endif
                                            </div>
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
                                                <button type="submit" class="customer-order-button">В корзину</button>
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


<div class="customer-menu-image-viewer" id="customer-menu-image-viewer" hidden aria-hidden="true">
    <button type="button" class="customer-menu-image-viewer-close" data-menu-image-close aria-label="Закрыть">×</button>
    <img src="" alt="" id="customer-menu-image-viewer-img">
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
                const buttonGroup = button.closest('[data-section-group]')
                    || (pinnedCategory?.button === button ? pinnedCategory.group : null);
                buttonGroup?.classList.toggle('is-expanded', isActive);
            });
            document.querySelectorAll('[data-section-panel]').forEach((panel) => {
                panel.hidden = !shouldExpand || panel.dataset.sectionPanel !== sectionId;
            });
            const activePanel = document.querySelector('[data-section-panel="' + sectionId + '"]');
            const activeTrack = activePanel?.querySelector('[data-product-carousel]');
            if (shouldExpand && activeTrack) updateCarouselControls(activeTrack);

            releasePinnedCategory();
            if (shouldExpand && mobileLayout.matches) {
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        const sectionGroup = sectionButton.closest('[data-section-group]');
                        if (!sectionGroup) return;

                        // Закрепляем кнопку сразу после раскрытия, не ожидая
                        // scroll-события или точного совпадения координат.
                        const placeholder = pinCategoryButton(sectionButton, sectionGroup);
                        if (!placeholder) return;

                        // Новый раздел всегда начинаем с первой карточки.
                        // Сбрасываем возможную внутреннюю вертикальную прокрутку
                        // и выравниваем начало панели сразу под закреплённой кнопкой.
                        if (activePanel) {
                            activePanel.scrollTop = 0;
                            const panelTrack = activePanel.querySelector('[data-product-carousel]');
                            if (panelTrack) panelTrack.scrollTop = 0;
                        }

                        const panelTop = activePanel
                            ? activePanel.getBoundingClientRect().top + window.scrollY
                            : placeholder.getBoundingClientRect().top + window.scrollY + sectionButton.offsetHeight;

                        window.scrollTo({
                            top: Math.max(0, panelTop - sectionButton.offsetHeight),
                            behavior: 'smooth'
                        });
                    });
                });
            }
            return;
        }

        const carouselButton = event.target.closest('[data-carousel-prev], [data-carousel-next]');
        if (carouselButton) {
            const carousel = carouselButton.closest('.customer-order-carousel');
            const track = carousel?.querySelector('[data-product-carousel]');
            if (!track) return;
            const direction = carouselButton.hasAttribute('data-carousel-next') ? 1 : -1;
            const pages = Array.from(track.querySelectorAll(':scope > .customer-order-carousel-page'));
            if (!pages.length) return;

            const trackRect = track.getBoundingClientRect();
            const currentScroll = track.scrollLeft;
            let nearestIndex = 0;
            let nearestDistance = Number.POSITIVE_INFINITY;

            pages.forEach((page, index) => {
                const pageLeft = page.getBoundingClientRect().left - trackRect.left + currentScroll;
                const distance = Math.abs(pageLeft - currentScroll);
                if (distance < nearestDistance) {
                    nearestDistance = distance;
                    nearestIndex = index;
                }
            });

            const currentIndex = Number.isInteger(Number(track.dataset.carouselTargetPage))
                ? Number(track.dataset.carouselTargetPage)
                : nearestIndex;
            const targetIndex = Math.max(0, Math.min(pages.length - 1, currentIndex + direction));
            const targetPage = pages[targetIndex];

            track.dataset.carouselTargetPage = String(targetIndex);
            const targetLeft = targetPage.getBoundingClientRect().left - trackRect.left + currentScroll;
            track.scrollTo({ left: targetLeft, behavior: 'smooth' });
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

    // Состояние закрепления одно на всю страницу. Обработчики прокрутки регистрируются
    // один раз, а не при каждом нажатии на кнопку категории.
    let pinnedCategory = null;
    let lastPinnedScrollY = window.scrollY;

    function releasePinnedCategory() {
        if (!pinnedCategory) return;

        const { button, placeholder } = pinnedCategory;
        const shouldRestoreFocus = document.activeElement === button;
        button.classList.remove('is-scroll-pinned');
        button.style.removeProperty('--customer-pinned-left');
        button.style.removeProperty('--customer-pinned-width');

        if (placeholder?.parentNode) {
            placeholder.parentNode.insertBefore(button, placeholder.nextSibling);
            placeholder.remove();
            if (shouldRestoreFocus) button.focus({ preventScroll: true });
        }

        pinnedCategory = null;
    }

    function pinCategoryButton(button, group) {
        if (!mobileLayout.matches || !button || !group) return null;

        if (pinnedCategory?.button === button && pinnedCategory.placeholder) {
            return pinnedCategory.placeholder;
        }

        if (pinnedCategory) releasePinnedCategory();

        const buttonRect = button.getBoundingClientRect();
        const groupRect = group.getBoundingClientRect();
        const placeholder = document.createElement('span');
        placeholder.className = 'customer-order-section-pin-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.style.cssText = [
            'display:block',
            'flex:0 0 auto',
            'height:' + buttonRect.height + 'px',
            'width:' + groupRect.width + 'px',
            'visibility:hidden',
            'pointer-events:none'
        ].join(';');

        button.parentNode.insertBefore(placeholder, button);
        pinnedCategory = {
            button,
            group,
            placeholder,
            // Пока автоматическая прокрутка после открытия не довела
            // исходное место кнопки до верхнего края, ручное правило
            // открепления при движении вверх не применяется.
            hasReachedPinnedTop: placeholder.getBoundingClientRect().top <= 1
        };
        lastPinnedScrollY = window.scrollY;

        // Кнопка вынесена из всех контейнеров прокрутки и эффектов предков.
        document.body.appendChild(button);

        const placeholderRect = placeholder.getBoundingClientRect();
        button.style.setProperty('--customer-pinned-left', placeholderRect.left + 'px');
        button.style.setProperty('--customer-pinned-width', placeholderRect.width + 'px');
        button.classList.add('is-scroll-pinned');

        return placeholder;
    }

    function updatePinnedCategory() {
        const currentScrollY = window.scrollY;
        const isScrollingUp = currentScrollY < lastPinnedScrollY - 0.5;
        lastPinnedScrollY = currentScrollY;

        if (!mobileLayout.matches) {
            releasePinnedCategory();
            return;
        }

        // Если кнопка уже вынесена в body и закреплена, больше не проверяем
        // её группу через closest()/активный список: после переноса кнопки
        // структура DOM и события прокрутки могут отличаться на мобильных
        // браузерах. Состояние закрепления хранится до явного закрытия раздела
        // или возврата вверх к исходному месту кнопки.
        if (pinnedCategory?.button && pinnedCategory.placeholder) {
            const pinnedButton = pinnedCategory.button;

            if (pinnedButton.getAttribute('aria-expanded') !== 'true') {
                releasePinnedCategory();
                return;
            }

            const placeholderRect = pinnedCategory.placeholder.getBoundingClientRect();

            if (!pinnedCategory.hasReachedPinnedTop && placeholderRect.top <= 1) {
                pinnedCategory.hasReachedPinnedTop = true;
            }

            // Открепляем только при движении вверх, когда исходная позиция
            // кнопки снова появляется ниже верхнего края экрана.
            if (pinnedCategory.hasReachedPinnedTop && isScrollingUp && placeholderRect.top > 1) {
                releasePinnedCategory();
                return;
            }

            pinnedButton.style.setProperty('--customer-pinned-left', placeholderRect.left + 'px');
            pinnedButton.style.setProperty('--customer-pinned-width', placeholderRect.width + 'px');
            return;
        }

        const activeButton = document.querySelector('[data-section-select][aria-expanded="true"]');
        const activeGroup = activeButton?.closest('[data-section-group]');

        if (!activeButton || !activeGroup || !activeGroup.classList.contains('is-expanded')) {
            return;
        }

        // На случай, если раздел был раскрыт без обработчика клика,
        // закрепляем кнопку при достижении верхнего края окна.
        if (activeButton.getBoundingClientRect().top <= 1) {
            pinCategoryButton(activeButton, activeGroup);
        }
    }

    let pinnedUpdateFrame = 0;
    const schedulePinnedCategoryUpdate = () => {
        if (pinnedUpdateFrame) return;
        pinnedUpdateFrame = requestAnimationFrame(() => {
            pinnedUpdateFrame = 0;
            updatePinnedCategory();
        });
    };

    // Отслеживаем и прокрутку страницы, и вложенных прокручиваемых областей.
    window.addEventListener('scroll', schedulePinnedCategoryUpdate, { passive: true });
    document.addEventListener('scroll', schedulePinnedCategoryUpdate, { passive: true, capture: true });
    window.addEventListener('resize', schedulePinnedCategoryUpdate);

    // On desktop each carousel page contains exactly eight dishes.
    // On mobile the page wrappers are removed so every dish remains in normal page flow.
    const arrangeCarouselPages = () => {
        document.querySelectorAll('[data-product-carousel]').forEach((track) => {
            const layout = mobileLayout.matches ? 'mobile' : 'desktop';
            if (track.dataset.carouselLayout === layout) return;

            const items = [];
            Array.from(track.children).forEach((child) => {
                if (child.classList.contains('customer-order-carousel-page')) {
                    const pageGrid = child.querySelector('.customer-order-carousel-page-grid');
                    if (pageGrid) items.push(...Array.from(pageGrid.children));
                } else if (
                    child.classList.contains('customer-order-item') ||
                    child.classList.contains('customer-order-empty')
                ) {
                    items.push(child);
                }
            });

            track.replaceChildren();

            if (layout === 'mobile') {
                items.forEach((item) => track.appendChild(item));
            } else {
                for (let index = 0; index < items.length; index += 8) {
                    const page = document.createElement('div');
                    page.className = 'customer-order-carousel-page';

                    const pageGrid = document.createElement('div');
                    pageGrid.className = 'customer-order-carousel-page-grid';
                    pageGrid.style.cssText = 'display:contents';

                    // The page itself is the 4 x 2 grid; display:contents lets its cards fill it.
                    items.slice(index, index + 8).forEach((item) => pageGrid.appendChild(item));
                    page.appendChild(pageGrid);
                    track.appendChild(page);
                }
            }

            track.dataset.carouselLayout = layout;
            track.dataset.carouselTargetPage = '0';
            track.scrollLeft = 0;
        });
    };

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

    // На мобильных при обычном открытии меню все разделы свёрнуты.
    // Если передан section (после добавления блюда), откроем именно его.
    const requestedSection = new URLSearchParams(window.location.search).get('section');

    if (mobileLayout.matches) {
        document.querySelectorAll('[data-section-select]').forEach((button) => {
            button.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('[data-section-panel]').forEach((panel) => {
            panel.hidden = true;
        });
    }

    arrangeSectionPanels();
    arrangeCarouselPages();
    if (requestedSection) {
        const requestedButton = document.querySelector('[data-section-select="' + requestedSection + '"]');
        if (requestedButton) {
            requestedButton.setAttribute('aria-expanded', 'false');
            requestedButton.click();
        }
    }

    document.querySelectorAll('[data-product-carousel]').forEach((track) => {
        track.addEventListener('scroll', () => updateCarouselControls(track), { passive: true });
        track.addEventListener('scrollend', () => {
            delete track.dataset.carouselTargetPage;
            updateCarouselControls(track);
        });
        updateCarouselControls(track);
    });

    let previousMobileLayout = mobileLayout.matches;

    // Перестраиваем DOM и снимаем закрепление только при реальном переходе
    // между мобильной и настольной раскладкой. На мобильных браузерах обычная
    // прокрутка может менять высоту viewport и вызывать resize — это не должно
    // сбрасывать закреплённую кнопку.
    const handleLayoutChange = () => {
        const currentMobileLayout = mobileLayout.matches;

        if (currentMobileLayout !== previousMobileLayout) {
            releasePinnedCategory();
            previousMobileLayout = currentMobileLayout;
            arrangeSectionPanels();
            arrangeCarouselPages();
        } else if (pinnedCategory?.placeholder && pinnedCategory.button) {
            const placeholderRect = pinnedCategory.placeholder.getBoundingClientRect();
            pinnedCategory.button.style.setProperty('--customer-pinned-left', placeholderRect.left + 'px');
            pinnedCategory.button.style.setProperty('--customer-pinned-width', placeholderRect.width + 'px');
        }

        document.querySelectorAll('[data-product-carousel]').forEach(updateCarouselControls);
    };

    if (typeof mobileLayout.addEventListener === 'function') {
        mobileLayout.addEventListener('change', handleLayoutChange);
    } else {
        mobileLayout.addListener(handleLayoutChange);
    }

    // Resize мобильной адресной строки не снимает закрепление.
    window.addEventListener('resize', handleLayoutChange);

    // Подсказка состава вынесена в body, чтобы её не обрезали overflow карточек и карусели.
    const compositionTooltip = document.createElement('div');
    compositionTooltip.id = 'customer-composition-tooltip';
    compositionTooltip.className = 'customer-composition-tooltip';
    compositionTooltip.setAttribute('role', 'tooltip');
    document.body.appendChild(compositionTooltip);

    let compositionTooltipTarget = null;
    const hideCompositionTooltip = () => {
        compositionTooltip.classList.remove('is-visible');
        compositionTooltipTarget = null;
    };
    const showCompositionTooltip = (button) => {
        const description = button.dataset.compositionTooltip;
        if (!description) return;

        compositionTooltip.textContent = description;
        compositionTooltipTarget = button;
        compositionTooltip.classList.add('is-visible');

        const rect = button.getBoundingClientRect();
        const tooltipRect = compositionTooltip.getBoundingClientRect();
        const margin = 12;
        const left = Math.max(margin, Math.min(
            rect.left + rect.width / 2 - tooltipRect.width / 2,
            window.innerWidth - tooltipRect.width - margin
        ));
        const spaceAbove = rect.top - margin;
        const placeAbove = spaceAbove >= tooltipRect.height + 10;
        const top = placeAbove
            ? rect.top - tooltipRect.height - 9
            : Math.min(window.innerHeight - tooltipRect.height - margin, rect.bottom + 9);

        compositionTooltip.style.left = left + 'px';
        compositionTooltip.style.top = Math.max(margin, top) + 'px';
    };

    document.addEventListener('pointerover', (event) => {
        const button = event.target.closest('[data-composition-tooltip]');
        if (button && !button.contains(event.relatedTarget)) showCompositionTooltip(button);
    });
    document.addEventListener('pointerout', (event) => {
        const button = event.target.closest('[data-composition-tooltip]');
        if (button && !button.contains(event.relatedTarget) && compositionTooltipTarget === button) {
            hideCompositionTooltip();
        }
    });
    document.addEventListener('focusin', (event) => {
        const button = event.target.closest('[data-composition-tooltip]');
        if (button) showCompositionTooltip(button);
    });
    document.addEventListener('focusout', (event) => {
        const button = event.target.closest('[data-composition-tooltip]');
        if (button && compositionTooltipTarget === button) hideCompositionTooltip();
    });
    window.addEventListener('scroll', hideCompositionTooltip, { passive: true });
    window.addEventListener('resize', hideCompositionTooltip);

    const imageViewer = document.getElementById('customer-menu-image-viewer');
    const imageViewerImage = document.getElementById('customer-menu-image-viewer-img');
    const closeImageViewer = () => {
        if (!imageViewer) return;
        imageViewer.hidden = true;
        imageViewer.setAttribute('aria-hidden', 'true');
        imageViewerImage.src = '';
        document.body.classList.remove('customer-menu-image-open');
    };
    document.addEventListener('click', (event) => {
        const imageButton = event.target.closest('[data-menu-image-open]');
        if (imageButton && imageViewer && imageViewerImage) {
            event.preventDefault();
            imageViewerImage.src = imageButton.dataset.imageSrc;
            imageViewerImage.alt = imageButton.querySelector('img')?.alt || '';
            imageViewer.hidden = false;
            imageViewer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('customer-menu-image-open');
            return;
        }
        if (event.target.closest('[data-menu-image-close]') || event.target === imageViewer) {
            closeImageViewer();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && imageViewer && !imageViewer.hidden) closeImageViewer();
    });

})();
</script>
@endsection
