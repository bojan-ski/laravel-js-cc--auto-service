const DEBOUNCE_MS = 250;
const MIN_LENGTH = 2;

function parse(json) {
    try {
        return json ? JSON.parse(json) : null;
    } catch {
        return null;
    }
}

function setUp(picker) {
    const part = (name) => picker.querySelector(`[data-customer-picker-${name}]`);

    const search = part('search');
    const input = part('input');
    const results = part('results');
    const status = part('status');
    const idInput = part('id');
    const card = part('card');
    const newCustomer = part('new-customer');
    const fallback = part('fallback');
    const vehicles = part('vehicles');
    const vehicleList = part('vehicle-list');
    const newVehicle = part('new-vehicle');
    const locked = part('locked');
    const optionTemplate = part('option-template');
    const vehicleTemplate = part('vehicle-template');
    const newVehicleTemplate = part('new-vehicle-template');
    const name = picker.querySelector('[data-customer-picker-name]');
    const phone = picker.querySelector('[data-customer-picker-phone]');

    if (!search || !input || !results || !idInput || !card || !newCustomer || !vehicles || !newVehicle) {
        return;
    }

    let found = [];
    let active = -1;
    let timer;
    let request;

    search.hidden = false;

    if (fallback) {
        fallback.hidden = true;
        fallback.querySelectorAll('select').forEach((select) => {
            select.disabled = true;
        });
    }

    function announce(count) {
        if (!status) {
            return;
        }

        const template = count === 0 ? status.dataset.none : (count === 1 ? status.dataset.one : status.dataset.other);
        status.textContent = template.replace(':count', count);
    }

    function close() {
        results.hidden = true;
        results.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        found = [];
        active = -1;
    }

    function highlight(index) {
        const options = results.querySelectorAll('[role="option"]');

        options.forEach((option, position) => option.setAttribute('aria-selected', position === index ? 'true' : 'false'));
        active = index;

        if (options[index]) {
            input.setAttribute('aria-activedescendant', options[index].id);
            options[index].scrollIntoView({ block: 'nearest' });
        }
    }

    function render(customers) {
        results.replaceChildren();
        found = customers;
        active = -1;

        customers.forEach((customer, index) => {
            const option = optionTemplate.content.firstElementChild.cloneNode(true);
            const plates = customer.vehicles.map((vehicle) => vehicle.plate);

            option.id = `customer-option-${customer.id}`;
            option.querySelector('[data-name]').textContent = customer.name;
            option.querySelector('[data-meta]').textContent = [customer.phone, ...plates].join(' · ');
            option.addEventListener('mousedown', (event) => {
                event.preventDefault();
                pick(customer, customer.matchedVehicleId);
            });
            option.addEventListener('mousemove', () => highlight(index));

            results.append(option);
        });

        results.hidden = customers.length === 0;
        input.setAttribute('aria-expanded', customers.length > 0 ? 'true' : 'false');
        announce(customers.length);
    }

    async function lookup(term) {
        request?.abort();
        request = new AbortController();

        const url = new URL(picker.dataset.customerPickerUrl, window.location.origin);
        url.searchParams.set('q', term);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: request.signal,
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            render(payload.data ?? []);
        } catch {
            return;
        }
    }

    function refreshLock() {
        if (idInput.value !== '') {
            return;
        }

        const ready = Boolean(name?.value.trim() && phone?.value.trim());

        newVehicle.hidden = false;
        newVehicle.disabled = !ready;

        if (locked) {
            locked.hidden = ready;
        }
    }

    function refreshVehicleMode() {
        const chosen = vehicleList.querySelector('input[type="radio"]:checked');
        const wantsNew = chosen !== null && chosen.value === '';

        newVehicle.hidden = !wantsNew;
        newVehicle.disabled = !wantsNew;

        if (locked) {
            locked.hidden = true;
        }
    }

    function renderVehicles(list, selectedId, newChosen) {
        vehicleList.replaceChildren();

        if (list.length === 0) {
            vehicles.hidden = true;
            newVehicle.hidden = false;
            newVehicle.disabled = false;

            if (locked) {
                locked.hidden = true;
            }

            return;
        }

        const selected = newChosen ? '' : String(selectedId ?? (list.length === 1 ? list[0].id : ''));

        list.forEach((vehicle) => {
            const choice = vehicleTemplate.content.firstElementChild.cloneNode(true);
            const radio = choice.querySelector('input');

            radio.value = vehicle.id;
            radio.checked = selected !== '' && String(vehicle.id) === selected;
            choice.querySelector('[data-plate]').textContent = vehicle.plate;
            choice.querySelector('[data-label]').textContent = vehicle.label;

            vehicleList.append(choice);
        });

        const addNew = newVehicleTemplate.content.firstElementChild.cloneNode(true);
        addNew.querySelector('input').checked = Boolean(newChosen);
        vehicleList.append(addNew);

        vehicles.hidden = false;
        refreshVehicleMode();
    }

    function pick(customer, selectedId = null, newChosen = false) {
        close();

        idInput.value = customer.id;
        card.querySelector('[data-customer-picker-card-name]').textContent = customer.name;
        card.querySelector('[data-customer-picker-card-contact]').textContent = [customer.phone, customer.email].filter(Boolean).join(' · ');
        card.hidden = false;
        search.hidden = true;
        newCustomer.hidden = true;
        newCustomer.disabled = true;

        renderVehicles(customer.vehicles, selectedId, newChosen);
    }

    function change() {
        idInput.value = '';
        card.hidden = true;
        search.hidden = false;
        newCustomer.hidden = false;
        newCustomer.disabled = false;
        vehicles.hidden = true;
        vehicleList.replaceChildren();
        input.value = '';
        refreshLock();
        input.focus();
    }

    input.addEventListener('input', () => {
        window.clearTimeout(timer);

        const term = input.value.trim();

        if (term.length < MIN_LENGTH) {
            request?.abort();
            close();

            return;
        }

        timer = window.setTimeout(() => lookup(term), DEBOUNCE_MS);
    });

    input.addEventListener('keydown', (event) => {
        if (results.hidden || found.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight(Math.min(active + 1, found.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight(Math.max(active - 1, 0));
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            pick(found[active], found[active].matchedVehicleId);
        } else if (event.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', () => window.setTimeout(close, 100));
    card.querySelector('[data-customer-picker-change]')?.addEventListener('click', change);
    vehicleList.addEventListener('change', refreshVehicleMode);
    name?.addEventListener('input', refreshLock);
    phone?.addEventListener('input', refreshLock);

    const picked = parse(picker.dataset.customerPickerPicked);

    if (picked) {
        pick(picked, picker.dataset.customerPickerVehicle || null, picker.hasAttribute('data-customer-picker-new-vehicle-chosen'));
    } else {
        refreshLock();
    }
}

export function registerCustomerPicker() {
    document.querySelectorAll('[data-customer-picker]').forEach(setUp);
}
