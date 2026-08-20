(function () {
    'use strict';

    function directChild(element, selector) {
        return Array.from(element.children).find(function (child) {
            return child.matches(selector);
        }) || null;
    }

    function syncParentState(item) {
        const parentLink = directChild(item, '.nav-link');
        const tree = directChild(item, '.nav-treeview');
        if (!parentLink || !tree) return;

        const isOpen = item.classList.contains('menu-open')
            || item.classList.contains('menu-is-opening');
        // A child route opens its parent on initial render. If the user then
        // explicitly collapses the tree, remove the parent highlight as well.
        const shouldHighlight = isOpen;

        parentLink.classList.toggle('sidebar-parent-active', shouldHighlight);
        parentLink.classList.toggle('active', shouldHighlight);
        parentLink.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function initializeSidebarState() {
        const sidebar = document.querySelector('.nav-sidebar[data-widget="treeview"]');
        if (!sidebar) return;

        const parentItems = sidebar.querySelectorAll('.nav-item[data-sidebar-parent]');
        parentItems.forEach(function (item) {
            syncParentState(item);

            const observer = new MutationObserver(function () {
                syncParentState(item);
            });
            observer.observe(item, {
                attributes: true,
                attributeFilter: ['class'],
            });
        });

        sidebar.addEventListener('click', function (event) {
            const parentLink = event.target.closest('[data-sidebar-parent] > .nav-link');
            if (!parentLink) return;

            const parentItem = parentLink.parentElement;
            window.setTimeout(function () {
                syncParentState(parentItem);
            }, 350);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSidebarState);
    } else {
        initializeSidebarState();
    }
})();
