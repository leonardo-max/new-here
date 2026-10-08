/**
 * New Here: points at what is new on the current page.
 *
 * Highlights come from three places:
 *  - elements rendered with `data-new-here` (`->isNew()` on actions, columns, fields...);
 *  - hidden `data-new-here-filters` elements inside tables (`->isNew()` on filters);
 *  - `config.pages` (`#[IsNew]` on pages and resources: sidebar link + page heading).
 *
 * Nothing is marked as seen unless the user actually saw the hint.
 */
export default function newHere(config) {
    return {
        // Dismissals of this tab survive `wire:navigate` and the back button,
        // which may restore a page rendered before the dismissal.
        seen: new Set([...(config.seen ?? []), ...readSessionSeen()]),
        snoozed: new Set(),
        items: [],
        openKey: null,
        openedByUser: false,
        autoOpened: false,
        layer: null,
        observer: null,
        scanTimer: null,
        scanDeadline: null,
        frame: null,
        listeners: [],
        inertElements: [],

        init() {
            this.layer = document.createElement('div')
            this.layer.className = 'nh-layer'
            this.layer.setAttribute('data-new-here-layer', '')
            document.body.appendChild(this.layer)

            this.observer = new MutationObserver((mutations) => {
                if (mutations.every((mutation) => this.layer.contains(mutation.target))) {
                    return
                }

                this.scheduleScan()
            })

            this.observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['data-new-here', 'data-new-here-filters', 'class', 'aria-expanded', 'hidden'],
            })

            this.listen(window, 'scroll', () => this.schedulePosition(), { passive: true, capture: true })
            this.listen(window, 'resize', () => this.schedulePosition(), { passive: true })
            this.listen(document, 'keydown', (event) => {
                // Escape inside a Filament modal closes the modal, not the hint.
                if (event.key === 'Escape' && this.openKey && (this.isFocusMode() || ! this.openModal())) {
                    this.close()
                }

                if (event.key === 'Tab' && this.isFocusMode()) {
                    this.trapFocus(event)
                }
            })
            this.listen(document, 'click', (event) => this.onDocumentClick(event), { capture: true })
            this.listen(document, 'livewire:navigated', () => this.scheduleScan())

            this.scheduleScan(50)
        },

        destroy() {
            this.setFocusMode(false)
            this.observer?.disconnect()
            this.listeners.forEach(([target, type, handler, options]) => target.removeEventListener(type, handler, options))
            this.layer?.remove()
            cancelAnimationFrame(this.frame)
            clearTimeout(this.scanTimer)
        },

        listen(target, type, handler, options = {}) {
            target.addEventListener(type, handler, options)
            this.listeners.push([target, type, handler, options])
        },

        /**
         * Debounced, with a ceiling: a page that never stops mutating (a
         * progress bar, a ticking clock) still gets scanned every 600 ms.
         */
        scheduleScan(delay = 150) {
            clearTimeout(this.scanTimer)

            this.scanDeadline ??= Date.now() + 600

            this.scanTimer = setTimeout(() => {
                this.scanDeadline = null
                this.scan()
            }, Math.max(0, Math.min(delay, this.scanDeadline - Date.now())))
        },

        schedulePosition() {
            cancelAnimationFrame(this.frame)
            this.frame = requestAnimationFrame(() => this.position())
        },

        /**
         * Collects every highlight available on the page right now.
         */
        candidates() {
            const found = new Map()
            const modal = this.openModal()

            const add = (feature, element) => {
                if (! feature.key || found.has(feature.key) || ! this.isEligible(feature)) {
                    return
                }

                // With a modal open, only what is inside it can be pointed at.
                if (modal && ! modal.contains(element)) {
                    return
                }

                const anchor = this.resolveAnchor(element, feature)

                if (anchor) {
                    found.set(feature.key, { ...feature, element, anchor })
                }
            }

            document.querySelectorAll('[data-new-here]').forEach((element) => {
                if (this.layer.contains(element)) {
                    return
                }

                add(
                    {
                        key: element.dataset.newHere,
                        since: element.dataset.newHereSince,
                        title: element.dataset.newHereTitle,
                        hint: element.dataset.newHereHint,
                    },
                    element,
                )
            })

            document.querySelectorAll('[data-new-here-filters]').forEach((marker) => {
                let filters = []

                try {
                    filters = JSON.parse(marker.dataset.newHereFilters)
                } catch {
                    return
                }

                const table = marker.closest('.fi-ta') ?? marker.closest('[wire\\:id]') ?? document

                filters.forEach((filter) => add(filter, this.findFilter(table, filter.name)))
            })

            ;(config.pages ?? []).forEach((page) => {
                if (this.isCurrentPage(page)) {
                    add(page, document.querySelector('.fi-header-heading, .fi-header h1, h1'))

                    return
                }

                add(page, this.findNavigationLink(page.url))
            })

            return [...found.values()]
        },

        isEligible(feature) {
            if (this.seen.has(feature.key) || this.snoozed.has(feature.key)) {
                return false
            }

            return ! (config.notBefore && feature.since && feature.since < config.notBefore)
        },

        /**
         * The element to point at. When the element itself is hidden (inside a
         * closed dropdown, an inactive tab, a collapsed sidebar group), point at
         * whatever reveals it.
         */
        resolveAnchor(element, feature) {
            if (! element) {
                return null
            }

            if (this.isVisible(element)) {
                // Point at the label rather than at a full-width row or field.
                const label = element.querySelector(':scope .fi-sidebar-item-label, :scope .fi-fo-field-label-content, :scope .fi-in-entry-label')

                return label && this.isVisible(label) ? label : element
            }

            const dropdown = element.closest('.fi-dropdown')?.querySelector('.fi-dropdown-trigger')

            if (dropdown && this.isVisible(dropdown)) {
                return dropdown
            }

            const tabs = element.closest('.fi-sc-tabs')

            if (tabs && feature.title) {
                const tab = [...tabs.querySelectorAll('.fi-tabs-item')].find(
                    (item) => item.textContent.trim().startsWith(feature.title),
                )

                if (tab && this.isVisible(tab)) {
                    return tab
                }
            }

            const group = element.closest('.fi-sidebar-group')?.querySelector('.fi-sidebar-group-btn')

            if (group && this.isVisible(group)) {
                return group
            }

            return null
        },

        isVisible(element) {
            if (! element.isConnected) {
                return false
            }

            const rect = element.getBoundingClientRect()

            if (rect.width === 0 && rect.height === 0) {
                return false
            }

            const style = getComputedStyle(element)

            return style.visibility !== 'hidden' && style.display !== 'none' && Number(style.opacity) !== 0
        },

        findFilter(table, name) {
            const escaped = CSS.escape(name)
            const control = table.querySelector(
                `[wire\\:model*="Filters.${escaped}."], [wire\\:model\\.live*="Filters.${escaped}."], [id*="Filters.${escaped}."], [id*="filters.${escaped}."]`,
            )

            return control?.closest('[data-field-wrapper], .fi-fo-field, .fi-fo-field-wrp') ?? control ?? null
        },

        findNavigationLink(url) {
            const path = this.pathOf(url)

            return [...document.querySelectorAll('.fi-sidebar a[href], .fi-topbar a[href]')].find(
                (link) => this.pathOf(link.href) === path,
            ) ?? null
        },

        /**
         * Resources own the URLs below their index (create, edit...). Plain
         * pages own only their own URL: a page at the panel root would
         * otherwise match every page.
         */
        isCurrentPage(page) {
            const path = this.pathOf(page.url)
            const current = this.pathOf(window.location.href)

            return current === path || (page.prefix && current.startsWith(path + '/'))
        },

        openModal() {
            return [...document.querySelectorAll('.fi-modal-window')].reverse().find((modal) => this.isVisible(modal)) ?? null
        },

        pathOf(url) {
            try {
                return new URL(url, window.location.origin).pathname.replace(/\/+$/, '')
            } catch {
                return url
            }
        },

        scan() {
            // What is on the page comes first; sidebar links wait their turn.
            const items = this.candidates()
                .sort((a, b) => (this.isNavigation(a) - this.isNavigation(b)) || this.documentOrder(a.anchor, b.anchor))
                .slice(0, Math.max(1, config.maxPerPage ?? 3))

            const previousOpenKey = this.openKey
            const unchanged = items.length === this.items.length
                && items.every((item, index) => item.key === this.items[index].key && item.anchor === this.items[index].anchor)

            this.items = items

            if (this.openKey && ! items.some((item) => item.key === this.openKey)) {
                this.openKey = null
            }

            if (config.autoOpen && ! this.autoOpened && ! this.openKey && items.length) {
                this.autoOpened = true
                this.openKey = items[0].key
                this.openedByUser = false
            }

            // The DOM changes all the time (typing, hovering, Livewire morphs).
            // Rebuilding the layer only when the highlights change keeps the
            // popover and its focus stable.
            if (unchanged && previousOpenKey === this.openKey && this.layer.childElementCount) {
                this.position()

                return
            }

            this.render()
        },

        isNavigation(item) {
            return Boolean(item.url) && ! this.isCurrentPage(item)
        },

        documentOrder(a, b) {
            if (a === b) {
                return 0
            }

            return a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1
        },

        render() {
            const opened = this.items.find((item) => item.key === this.openKey)

            const entering = Boolean(opened) && this.usesBackdrop() && ! this.isFocusMode()

            this.layer.replaceChildren()
            this.setFocusMode(Boolean(opened) && this.usesBackdrop())

            if (opened && this.usesBackdrop()) {
                this.revealAnchor(opened)
                this.layer.append(...this.backdrop(entering))
            }

            this.items.forEach((item, index) => {
                const beacon = document.createElement('button')
                beacon.type = 'button'
                beacon.className = 'nh-beacon'
                beacon.dataset.key = item.key
                beacon.setAttribute('aria-label', this.label('beacon', { title: item.title ?? this.label('badge') }))
                beacon.setAttribute('aria-expanded', String(this.openKey === item.key))
                beacon.innerHTML = '<span class="nh-beacon-ping"></span><span class="nh-beacon-dot"></span>'
                beacon.addEventListener('click', (event) => {
                    event.stopPropagation()
                    this.toggle(item.key)
                })
                this.layer.appendChild(beacon)

                if (this.openKey === item.key) {
                    this.layer.appendChild(this.popover(item, index))
                }
            })

            this.position()

            // In focus mode the hint behaves like a dialog and takes focus.
            // Otherwise never steal it on page load, nor from an open modal.
            if (this.isFocusMode() || (this.openedByUser && ! this.openModal())) {
                this.layer.querySelector('.nh-popover [data-nh-primary]')?.focus({ preventScroll: true })
            }
        },

        usesBackdrop() {
            return config.backdrop !== false
        },

        isFocusMode() {
            return this.inertElements.length > 0
        },

        /**
         * Focus mode: everything but the layer becomes `inert` (no clicks, no
         * keyboard, hidden from screen readers) and gets blurred, until the
         * user goes through the hints or presses Escape.
         */
        setFocusMode(enabled) {
            if (! enabled) {
                this.inertElements.forEach((element) => {
                    element.inert = false
                })
                this.inertElements = []
                document.documentElement.classList.remove('nh-focus-mode')

                return
            }

            if (this.isFocusMode()) {
                return
            }

            this.inertElements = [...document.body.children].filter(
                (element) => element !== this.layer && ! element.inert && element.tagName !== 'SCRIPT',
            )
            this.inertElements.forEach((element) => {
                element.inert = true
            })
            document.documentElement.classList.add('nh-focus-mode')
        },

        /**
         * Four blurred panes around the anchor, plus a ring on the anchor
         * itself. The anchor stays sharp but is not clickable either.
         */
        backdrop(entering = false) {
            return ['top', 'right', 'bottom', 'left', 'spotlight'].map((side) => {
                const pane = document.createElement('div')
                pane.className = side === 'spotlight' ? 'nh-spotlight' : 'nh-backdrop'
                pane.classList.toggle('nh-enter', entering)
                pane.dataset.side = side
                pane.setAttribute('aria-hidden', 'true')

                return pane
            })
        },

        revealAnchor(item) {
            const rect = this.rectOf(item.anchor)

            if (rect.top >= 0 && rect.bottom <= window.innerHeight) {
                return
            }

            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

            item.anchor.scrollIntoView({ block: 'center', behavior: reduced ? 'auto' : 'smooth' })
        },

        positionBackdrop(item) {
            const panes = this.layer.querySelectorAll('[data-side]')

            if (! panes.length) {
                return
            }

            const padding = 6
            const width = window.innerWidth
            const height = window.innerHeight
            const rect = item ? this.rectOf(item.anchor) : null

            const hole = rect
                ? {
                      top: Math.max(0, rect.top - padding),
                      left: Math.max(0, rect.left - padding),
                      right: Math.min(width, rect.right + padding),
                      bottom: Math.min(height, rect.bottom + padding),
                  }
                : { top: 0, left: 0, right: 0, bottom: 0 }

            const place = (pane, top, left, paneWidth, paneHeight) => {
                pane.style.top = `${top}px`
                pane.style.left = `${left}px`
                pane.style.width = `${Math.max(0, paneWidth)}px`
                pane.style.height = `${Math.max(0, paneHeight)}px`
            }

            panes.forEach((pane) => {
                switch (pane.dataset.side) {
                    case 'top':
                        place(pane, 0, 0, width, hole.top)
                        break
                    case 'bottom':
                        place(pane, hole.bottom, 0, width, height - hole.bottom)
                        break
                    case 'left':
                        place(pane, hole.top, 0, hole.left, hole.bottom - hole.top)
                        break
                    case 'right':
                        place(pane, hole.top, hole.right, width - hole.right, hole.bottom - hole.top)
                        break
                    default:
                        place(pane, hole.top, hole.left, hole.right - hole.left, hole.bottom - hole.top)
                }
            })
        },

        trapFocus(event) {
            const focusable = [...this.layer.querySelectorAll('.nh-popover button')]

            if (! focusable.length) {
                return
            }

            const first = focusable[0]
            const last = focusable[focusable.length - 1]

            if (! this.layer.contains(document.activeElement)) {
                event.preventDefault()
                first.focus()
            } else if (event.shiftKey && document.activeElement === first) {
                event.preventDefault()
                last.focus()
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault()
                first.focus()
            }
        },

        popover(item, index) {
            const total = this.items.length
            const popover = document.createElement('div')
            const titleId = `nh-title-${index}`

            popover.className = 'nh-popover'
            popover.lang = (config.locale ?? 'en').replace('_', '-')
            popover.setAttribute('role', 'dialog')

            if (this.usesBackdrop()) {
                popover.setAttribute('aria-modal', 'true')
            }
            popover.setAttribute('aria-labelledby', titleId)
            popover.dataset.key = item.key

            const header = document.createElement('div')
            header.className = 'nh-popover-header'

            const badge = document.createElement('span')
            badge.className = 'nh-badge'
            badge.textContent = this.label('badge')
            header.appendChild(badge)

            if (total > 1) {
                const counter = document.createElement('span')
                counter.className = 'nh-counter'
                counter.textContent = this.label('counter', { current: index + 1, total })
                header.appendChild(counter)
            }

            popover.appendChild(header)

            const title = document.createElement('p')
            title.className = 'nh-title'
            title.id = titleId
            title.textContent = item.title ?? this.label('badge')
            popover.appendChild(title)

            if (item.hint) {
                const hint = document.createElement('p')
                hint.className = 'nh-hint'
                hint.textContent = item.hint
                popover.appendChild(hint)
            }

            const footer = document.createElement('div')
            footer.className = 'nh-popover-footer'

            const secondary = document.createElement('button')
            secondary.type = 'button'
            secondary.className = 'nh-link'

            if (total > 1) {
                secondary.textContent = this.label('dismissAll')
                secondary.addEventListener('click', () => this.dismiss(this.items.map((candidate) => candidate.key)))
            } else {
                secondary.textContent = this.label('optOut')
                secondary.addEventListener('click', () => this.optOut())
            }

            footer.appendChild(secondary)

            const primary = document.createElement('button')
            primary.type = 'button'
            primary.className = 'nh-button'
            primary.dataset.nhPrimary = ''
            primary.textContent = index < total - 1 ? this.label('next') : this.label('gotIt')
            primary.addEventListener('click', () => {
                const next = this.items[index + 1]
                this.dismiss([item.key], next?.key ?? null)
            })
            footer.appendChild(primary)

            popover.appendChild(footer)
            popover.addEventListener('click', (event) => event.stopPropagation())

            return popover
        },

        position() {
            const margin = 8

            this.positionBackdrop(this.items.find((item) => item.key === this.openKey))

            this.layer.querySelectorAll('.nh-beacon').forEach((beacon) => {
                const item = this.items.find((candidate) => candidate.key === beacon.dataset.key)

                if (! item || ! item.anchor.isConnected) {
                    beacon.hidden = true

                    return
                }

                const rect = this.rectOf(item.anchor)
                const offscreen = rect.bottom < 0 || rect.top > window.innerHeight || rect.right < 0 || rect.left > window.innerWidth

                beacon.hidden = offscreen
                beacon.style.top = `${rect.top - 4}px`
                beacon.style.left = `${rect.right - 8}px`

                const popover = this.layer.querySelector(`.nh-popover[data-key="${CSS.escape(item.key)}"]`)

                if (! popover) {
                    return
                }

                popover.hidden = offscreen

                const width = popover.offsetWidth
                const height = popover.offsetHeight
                const below = rect.bottom + margin + height <= window.innerHeight || rect.top < height + margin

                let left = rect.left + rect.width / 2 - width / 2
                left = Math.min(Math.max(margin, left), window.innerWidth - width - margin)

                popover.style.left = `${left}px`
                popover.style.top = below ? `${rect.bottom + margin}px` : `${rect.top - height - margin}px`
                popover.dataset.placement = below ? 'bottom' : 'top'
                popover.style.setProperty('--nh-arrow-left', `${Math.min(Math.max(16, rect.left + rect.width / 2 - left), width - 16)}px`)
            })
        },

        /**
         * Header cells and sidebar labels stretch to fill the row: measure their text instead.
         */
        rectOf(element) {
            if ((element.tagName === 'TH' || element.matches('[class*="-label"]')) && element.textContent.trim()) {
                const range = document.createRange()
                range.selectNodeContents(element)

                const text = [...range.getClientRects()].find((rect) => rect.width > 0)

                if (text) {
                    return text
                }
            }

            return element.getBoundingClientRect()
        },

        toggle(key) {
            this.openedByUser = true
            this.openKey = this.openKey === key ? null : key
            this.render()
        },

        close() {
            // Closing without "Got it" snoozes the hint until the next visit.
            if (this.openKey) {
                this.snoozed.add(this.openKey)
            }

            this.openKey = null
            this.scan()
        },

        dismiss(keys, nextKey = null) {
            const previous = this.items.find((item) => item.key === this.openKey)

            keys.forEach((key) => this.seen.add(key))
            this.persist(keys)
            this.openKey = nextKey
            this.openedByUser = true
            this.scan()

            // The focused button just disappeared: hand focus back to the page.
            if (! this.openKey && this.layer.contains(document.activeElement) === false && document.activeElement === document.body) {
                previous?.anchor?.focus?.({ preventScroll: true })
            }
        },

        optOut() {
            this.items.forEach((item) => this.seen.add(item.key))
            config.pages = []
            this.items = []
            this.openKey = null
            this.render()
            this.setFocusMode(false)
            this.observer?.disconnect()
            Promise.resolve()
                .then(() => this.$wire?.optOut())
                .catch((error) => console.warn('[new-here] could not save the opt-out', error))
        },

        persist(keys) {
            if (! keys.length) {
                return
            }

            writeSessionSeen(this.seen)

            Promise.resolve()
                .then(() => this.$wire?.markSeen(keys))
                .catch((error) => console.warn('[new-here] could not save what was seen', error))
        },

        /**
         * Using the highlighted thing counts as having seen it.
         */
        onDocumentClick(event) {
            if (this.layer.contains(event.target)) {
                return
            }

            // Opening the dropdown or tab that hides the element does not count,
            // and neither does following a sidebar link: the hint waits on the page.
            const used = this.items.filter(
                (item) => ! item.url && (
                    item.element.contains(event.target)
                    // Row actions repeat on every row: any of them counts.
                    || event.target.closest?.(`[data-new-here="${CSS.escape(item.key)}"]`)
                ),
            )

            if (used.length) {
                this.dismiss(used.map((item) => item.key))
            }
        },

        label(name, replacements = {}) {
            let text = config.labels?.[name] ?? name

            Object.entries(replacements).forEach(([key, value]) => {
                text = text.replaceAll(`:${key}`, value)
            })

            return text
        },
    }
}

const SESSION_KEY = 'new-here:seen'

function readSessionSeen() {
    try {
        return JSON.parse(sessionStorage.getItem(SESSION_KEY) ?? '[]')
    } catch {
        return []
    }
}

function writeSessionSeen(seen) {
    try {
        sessionStorage.setItem(SESSION_KEY, JSON.stringify([...seen].slice(-500)))
    } catch {
        // Private mode or storage full: the server copy is enough.
    }
}
