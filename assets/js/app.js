const bindConfirmations = () => {
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message = element.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
};

const applyThemeLabel = (theme) => {
    const toggle = document.querySelector('[data-theme-toggle]');
    if (!toggle) {
        return;
    }

    const icon = toggle.querySelector('.theme-toggle-icon');
    const text = toggle.querySelector('.theme-toggle-text');
    const isDark = theme === 'dark';

    if (icon) {
        icon.textContent = isDark ? '☀️' : '🌙';
    }

    if (text) {
        text.textContent = isDark ? 'Light mode' : 'Dark mode';
    }

    toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
};

const bindThemeToggle = () => {
    const toggle = document.querySelector('[data-theme-toggle]');
    if (!toggle) {
        return;
    }

    applyThemeLabel(document.documentElement.getAttribute('data-theme') || 'light');

    toggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', nextTheme);
        window.localStorage.setItem('portal-theme', nextTheme);
        applyThemeLabel(nextTheme);
    });
};

bindConfirmations();
bindThemeToggle();


const formatCatalogLabel = (item) => {
    const typeLabel = item.type === 'service' ? 'Service' : 'Product';
    const meta = item.meta ? ` • ${item.meta}` : '';
    return `${typeLabel}: ${item.name}${meta} • ${Number(item.price).toFixed(2)}`;
};

const parseCatalogValue = (value) => {
    if (!value) {
        return { type: 'manual', id: '' };
    }

    const [type, id] = value.split(':');
    return { type: type || 'manual', id: id || '' };
};

const bindInvoiceCatalog = () => {
    document.querySelectorAll('[data-invoice-form]').forEach((form) => {
        let catalog = [];
        try {
            catalog = JSON.parse(form.getAttribute('data-catalog') || '[]');
        } catch (error) {
            catalog = [];
        }

        const companySelect = form.querySelector('[data-company-select]');
        const clientSelect = form.querySelector('[data-client-select]');
        const lineItems = form.querySelector('[data-line-items]');
        const addButton = form.querySelector('[data-add-line-item]');
        const template = form.querySelector('#invoice-line-item-template');

        const filteredItems = () => {
            const companyId = companySelect ? companySelect.value : '';
            const clientId = clientSelect ? clientSelect.value : '';
            return catalog.filter((item) => {
                if (companyId && String(item.company_id) !== String(companyId)) {
                    return false;
                }
                if (item.type === 'service') {
                    return clientId && String(item.client_id) === String(clientId);
                }
                return true;
            });
        };

        const updateRowSelect = (row) => {
            const select = row.querySelector('[data-catalog-select]');
            const sourceType = row.querySelector('[name="item_source_type[]"]');
            const sourceId = row.querySelector('[name="item_source_id[]"]');
            if (!select || !sourceType || !sourceId) {
                return;
            }
            const current = parseCatalogValue(select.value);
            const selectedType = select.dataset.selectedSourceType || current.type;
            const selectedId = select.dataset.selectedSourceId || current.id;
            let hasMatch = false;
            select.innerHTML = '<option value="">Manual entry</option>';
            filteredItems().forEach((item) => {
                const option = document.createElement('option');
                option.value = `${item.type}:${item.id}`;
                option.textContent = formatCatalogLabel(item);
                if (String(item.id) === String(selectedId) && item.type === selectedType) {
                    option.selected = true;
                    hasMatch = true;
                }
                select.appendChild(option);
            });
            if (!hasMatch) {
                select.value = '';
                select.dataset.selectedSourceType = 'manual';
                select.dataset.selectedSourceId = '';
                sourceType.value = 'manual';
                sourceId.value = '';
                return;
            }
            select.dataset.selectedSourceType = selectedType;
            select.dataset.selectedSourceId = selectedId;
            sourceType.value = selectedType;
            sourceId.value = selectedId;
        };

        const updateAllRows = () => {
            lineItems.querySelectorAll('[data-line-item]').forEach(updateRowSelect);
        };

        const bindRow = (row) => {
            const select = row.querySelector('[data-catalog-select]');
            const description = row.querySelector('[name="item_description[]"]');
            const price = row.querySelector('[name="item_price[]"]');
            const sourceType = row.querySelector('[name="item_source_type[]"]');
            const sourceId = row.querySelector('[name="item_source_id[]"]');
            if (!select || !description || !price || !sourceType || !sourceId) {
                return;
            }
            updateRowSelect(row);
            select.addEventListener('change', () => {
                const parsed = parseCatalogValue(select.value);
                sourceType.value = parsed.type;
                sourceId.value = parsed.id;
                select.dataset.selectedSourceType = parsed.type;
                select.dataset.selectedSourceId = parsed.id;
                const selectedItem = filteredItems().find((item) => item.type === parsed.type && String(item.id) === String(parsed.id));
                if (!selectedItem) {
                    sourceType.value = 'manual';
                    sourceId.value = '';
                    return;
                }
                description.value = selectedItem.description || selectedItem.name;
                price.value = Number(selectedItem.price).toFixed(2);
            });
        };

        lineItems.querySelectorAll('[data-line-item]').forEach(bindRow);

        if (addButton && template) {
            addButton.addEventListener('click', () => {
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('[data-line-item]');
                lineItems.appendChild(fragment);
                if (row) {
                    bindRow(lineItems.lastElementChild);
                }
            });
        }

        [companySelect, clientSelect].forEach((element) => {
            if (!element) {
                return;
            }
            element.addEventListener('change', () => {
                updateAllRows();
            });
        });

        updateAllRows();
    });
};

bindInvoiceCatalog();
