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
                                        <h3 class="customer-order-item-title">{{ $item->name }}</h3>

                                        <div class="customer-order-item-main">
                                            <div class="customer-order-item-image-placeholder" aria-label="Место для фотографии блюда"></div>

                                            <div class="customer-order-item-content">
                                                @if($item->description)
                                                    <p>{{ $item->description }}</p>
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
                        const buttonTop = sectionButton.getBoundingClientRect().top + window.scrollY;
                        window.scrollTo({ top: Math.max(0, buttonTop), behavior: 'smooth' });
                        updatePinnedCategory();
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

    function releasePinnedCategory() {
        if (!pinnedCategory) return;

        const { button, placeholder } = pinnedCategory;
        button.classList.remove('is-scroll-pinned');
        button.style.removeProperty('--customer-pinned-left');
        button.style.removeProperty('--customer-pinned-width');

        if (placeholder?.parentNode) {
            placeholder.parentNode.insertBefore(button, placeholder.nextSibling);
            placeholder.remove();
        }

        pinnedCategory = null;
    }

    function updatePinnedCategory() {
        if (!mobileLayout.matches) {
            releasePinnedCategory();
            return;
        }

        const activeButton = document.querySelector('[data-section-select][aria-expanded="true"]');
        const activeGroup = pinnedCategory?.button === activeButton
            ? pinnedCategory.group
            : activeButton?.closest('[data-section-group]');

        if (!activeButton || !activeGroup || !activeGroup.classList.contains('is-expanded')) {
            releasePinnedCategory();
            return;
        }

        if (pinnedCategory && pinnedCategory.button !== activeButton) {
            releasePinnedCategory();
        }

        if (!pinnedCategory) {
            pinnedCategory = { button: activeButton, group: activeGroup, placeholder: null };
        }

        const { button, group } = pinnedCategory;
        const groupRect = group.getBoundingClientRect();
        const buttonHeight = button.offsetHeight;
        const shouldPin = groupRect.top <= 0 && groupRect.bottom > buttonHeight;

        if (!shouldPin) {
            if (pinnedCategory.placeholder) {
                releasePinnedCategory();
            } else {
                pinnedCategory = null;
            }
            return;
        }

        if (!pinnedCategory.placeholder) {
            const buttonRect = button.getBoundingClientRect();
            const placeholder = document.createElement('span');
            placeholder.className = 'customer-order-section-pin-placeholder';
            placeholder.setAttribute('aria-hidden', 'true');
            placeholder.style.cssText = [
                'display:block',
                'flex:0 0 auto',
                'height:' + buttonRect.height + 'px',
                'width:' + buttonRect.width + 'px',
                'visibility:hidden',
                'pointer-events:none'
            ].join(';');

            button.parentNode.insertBefore(placeholder, button);
            pinnedCategory.placeholder = placeholder;

            // Выводим настоящий элемент из sidebar с backdrop-filter в body:
            // теперь fixed всегда отсчитывается от окна браузера.
            document.body.appendChild(button);
        }

        const placeholderRect = pinnedCategory.placeholder.getBoundingClientRect();
        button.style.setProperty('--customer-pinned-left', placeholderRect.left + 'px');
        button.style.setProperty('--customer-pinned-width', placeholderRect.width + 'px');
        button.classList.add('is-scroll-pinned');
    }

    window.addEventListener('scroll', updatePinnedCategory, { passive: true });
    window.addEventListener('resize', updatePinnedCategory);

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

    const handleLayoutChange = () => {
        releasePinnedCategory();
        arrangeSectionPanels();
        arrangeCarouselPages();
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
