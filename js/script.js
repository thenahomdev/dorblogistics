var nav = document.getElementById('siteNav');

var toggle = nav
    ? nav.querySelector('.nav-toggle')
    : null;

if (toggle && nav) {
    toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('is-open');

        toggle.setAttribute(
            'aria-expanded',
            open ? 'true' : 'false'
        );
    });

    nav
        .querySelectorAll('.nav-links a')
        .forEach(function (link) {
            link.addEventListener('click', function () {
                nav.classList.remove('is-open');

                toggle.setAttribute(
                    'aria-expanded',
                    'false'
                );
            });
        });
}

var companyRadio =
    document.getElementById('type-bedrijf');

var privateRadio =
    document.getElementById('type-particulier');

var companyNameRow =
    document.getElementById('company-name-row');

var companyNameInput =
    document.getElementById('bedrijfsnaam');

function toggleCompanyName() {
    if (
        !companyRadio ||
        !privateRadio ||
        !companyNameRow ||
        !companyNameInput
    ) {
        return;
    }

    var isCompany =
        companyRadio.checked;

    companyNameRow.classList.toggle(
        'register_field_hidden',
        !isCompany
    );

    companyNameInput.required =
        isCompany;

    companyNameInput.disabled =
        !isCompany;

    // if (!isCompany) 
    //     {
    //        companyNameInput.value = '';
    //     }

}

if (companyRadio && privateRadio) {
    companyRadio.addEventListener(
        'change',
        toggleCompanyName
    );

    privateRadio.addEventListener(
        'change',
        toggleCompanyName
    );

    toggleCompanyName();
}

var currentOrderFilter = 'openstaand';

var onlyMineOrders = false;

function filterMineOrders(checked) {
    onlyMineOrders = checked;

    filterOrders(
        currentOrderFilter
    );
}

function updateSelection() {
    document
        .querySelectorAll(
            '.orders-table tbody tr.order-row'
        )
        .forEach(function (row) {
            var orderCheckbox =
                row.querySelector(
                    'input[name="selected_orders[]"]'
                );

            row.classList.toggle(
                'selected',
                !!(
                    orderCheckbox &&
                    orderCheckbox.checked
                )
            );
        });
}

function claimOrder(orderId, button) {
    if (!orderId || !button) {
        console.error(
            'claimOrder: orderId of button ontbreekt.'
        );

        return;
    }

    console.log(
        'Order claimen:',
        orderId
    );

    button.disabled = true;

    button.textContent =
        'Bezig...';

    var formData =
        new FormData();

    formData.append(
        'order_id',
        orderId
    );

    fetch(
        'php/claim_order.php',
        {
            method: 'POST',
            body: formData
        }
    )
        .then(function (response) {
            return response.text()
                .then(function (text) {
                    console.log(
                        'Response claim_order.php:',
                        text
                    );

                    var data;

                    try {
                        data =
                            JSON.parse(text);
                    } catch (e) {
                        console.error(
                            'PHP claim_order.php gaf geen geldige JSON terug.'
                        );

                        console.error(
                            'Server response:',
                            text
                        );

                        throw new Error(
                            'PHP gaf geen geldige JSON terug.'
                        );
                    }

                    if (!response.ok) {
                        throw new Error(
                            data.message ||
                            'De server gaf een fout terug.'
                        );
                    }

                    return data;
                });
        })
        .then(function (data) {
            console.log(
                'Claim response:',
                data
            );

            if (!data.success) {
                alert(
                    data.message ||
                    'De order kon niet worden geclaimd.'
                );

                button.disabled = false;

                button.textContent =
                    'Claimen';

                return;
            }

            var row =
                document.querySelector(
                    '.orders-table tbody tr[data-order-id="' +
                    orderId +
                    '"]'
                );

            if (!row) {
                console.error(
                    'Orderregel niet gevonden:',
                    orderId
                );

                alert(
                    'De order is geclaimd, maar de orderregel kon niet worden gevonden.'
                );

                return;
            }

            row.setAttribute(
                'data-assigned',
                '1'
            );

            if (
                data.employee_id !== undefined
            ) {
                row.setAttribute(
                    'data-planner-id',
                    data.employee_id
                );
            }

            row.classList.add(
                'assigned-row'
            );

            row.classList.add(
                'selected'
            );

            var assignedCell =
                row.querySelector(
                    '.assigned-cell'
                );

            if (assignedCell) {
                assignedCell.textContent =
                    'Mijn order';
            }

            var claimCell =
                row.querySelector(
                    '.claim-cell'
                );

            if (claimCell) {
                claimCell.innerHTML =
                    '<span class="claimed-by-me">' +
                    'Mijn order' +
                    '</span>';
            }

            var orderCheckbox =
                row.querySelector(
                    'input[name="selected_orders[]"]'
                );

            if (orderCheckbox) {
                orderCheckbox.checked = true;

                updateSelection();
            }

            var checkbox =
                document.getElementById(
                    'mijn-orders-checkbox'
                );

            if (checkbox) {
                onlyMineOrders =
                    checkbox.checked;
            }

            filterOrders(
                currentOrderFilter
            );

            alert(
                'Order succesvol geclaimd.'
            );
        })
        .catch(function (error) {
            console.error(
                'Fout bij claimen:',
                error
            );

            alert(
                'De order kon niet worden geclaimd.\n\n' +
                error.message
            );

            button.disabled = false;

            button.textContent =
                'Claimen';
        });
}

function createNewTrip() {
    var checked =
        document.querySelectorAll(
            '.orders-table tbody input[name="selected_orders[]"]:checked'
        );

    if (checked.length === 0) {
        alert(
            'Selecteer eerst een of meer orders.'
        );

        return;
    }

    var ids = [];

    for (var i = 0; i < checked.length; i++) {
        var row =
            checked[i].closest('tr');

        if (
            row.getAttribute(
                'data-assigned'
            ) !== '1'
        ) {
            alert(
                'Je moet alle geselecteerde orders eerst claimen voordat je een rit kunt maken.'
            );

            return;
        }

        ids.push(
            checked[i].value
        );
    }

    window.location.href =
        'index.php?page=order_linking&orders=' +
        ids.join(',');
}

function filterOrders(filter) {
    currentOrderFilter =
        filter;

    var rows =
        document.querySelectorAll(
            '.orders-table tbody tr.order-row'
        );

    var visibleCount = 0;

    rows.forEach(function (row) {
        var status =
            row.getAttribute(
                'data-status'
            );

        var assigned =
            row.getAttribute(
                'data-assigned'
            );

        var matchesStatus =
            filter === 'alles' ||
            status === filter;

        var matchesMine =
            !onlyMineOrders ||
            assigned === '1';

        if (
            matchesStatus &&
            matchesMine
        ) {
            row.style.display =
                '';

            visibleCount++;
        } else {
            row.style.display =
                'none';
        }
    });

    var allesButton =
        document.getElementById(
            'filter-alles'
        );

    var openstaandButton =
        document.getElementById(
            'filter-openstaand'
        );

    var afgehandeldButton =
        document.getElementById(
            'filter-afgehandeld'
        );

    if (allesButton) {
        allesButton.classList.toggle(
            'active',
            filter === 'alles'
        );
    }

    if (openstaandButton) {
        openstaandButton.classList.toggle(
            'active',
            filter === 'openstaand'
        );
    }

    if (afgehandeldButton) {
        afgehandeldButton.classList.toggle(
            'active',
            filter === 'afgehandeld'
        );
    }

    var orderCountText =
        document.getElementById(
            'order-count-text'
        );

    if (orderCountText) {
        orderCountText.textContent =
            visibleCount + ' orders';
    }

    var emptyRow =
        document.querySelector(
            '.orders-table tbody tr.empty-row'
        );

    if (emptyRow) {
        emptyRow.style.display =
            visibleCount === 0
                ? ''
                : 'none';
    }

    document
        .querySelectorAll(
            '.orders-table tbody tr.land-row'
        )
        .forEach(function (landRow) {
            var hasVisible = false;

            var next =
                landRow.nextElementSibling;

            while (
                next &&
                !next.classList.contains('land-row')
            ) {
                if (
                    next.classList.contains('order-row') &&
                    next.style.display !== 'none'
                ) {
                    hasVisible = true;
                    break;
                }

                next =
                    next.nextElementSibling;
            }

            landRow.style.display =
                hasVisible
                    ? ''
                    : 'none';
        });
}

document.addEventListener(
    'DOMContentLoaded',
    function () {
        var checkbox =
            document.getElementById(
                'mijn-orders-checkbox'
            );

        if (checkbox) {
            onlyMineOrders =
                checkbox.checked;
        }

        var orderTable =
            document.querySelector(
                '.orders-table'
            );

        if (orderTable) {
            filterOrders(
                'openstaand'
            );
        }
    }
);

document.addEventListener(
    'DOMContentLoaded',
    function () {
        var deleteForm =
            document.getElementById(
                'deleteAccountForm'
            );

        if (deleteForm) {
            deleteForm.addEventListener(
                'submit',
                function (event) {
                    var confirmed =
                        confirm(
                            'Weet je zeker dat je je account wilt verwijderen?\n\n' +
                            'Je account wordt op inactief gezet en je kunt daarna niet meer inloggen.'
                        );

                    if (!confirmed) {
                        event.preventDefault();
                    }
                }
            );
        }

        var passwordForm =
            document.getElementById(
                'passwordForm'
            );

        if (passwordForm) {
            passwordForm.addEventListener(
                'submit',
                function (event) {
                    var newPasswordElement =
                        document.getElementById(
                            'new_password'
                        );

                    var confirmPasswordElement =
                        document.getElementById(
                            'confirm_password'
                        );

                    if (
                        !newPasswordElement ||
                        !confirmPasswordElement
                    ) {
                        return;
                    }

                    var newPassword =
                        newPasswordElement.value;

                    var confirmPassword =
                        confirmPasswordElement.value;

                    if (
                        newPassword !==
                        confirmPassword
                    ) {
                        event.preventDefault();

                        alert(
                            'De nieuwe wachtwoorden komen niet overeen.'
                        );

                        return;
                    }

                    if (
                        newPassword.length < 8
                    ) {
                        event.preventDefault();

                        alert(
                            'Het nieuwe wachtwoord moet minimaal 8 tekens bevatten.'
                        );
                    }
                }
            );
        }

        var alerts =
            document.querySelectorAll(
                '.alert'
            );

        alerts.forEach(function (alertElement) {
            setTimeout(
                function () {
                    alertElement.classList.add(
                        'alert-hidden'
                    );
                },
                5000
            );
        });
    }
);

document.addEventListener(
    'DOMContentLoaded',
    function () {
        var filterButtons =
            document.querySelectorAll(
                '.orders-filter-button'
            );

        var orderRows =
            document.querySelectorAll(
                '.order-row'
            );

        var detailRows =
            document.querySelectorAll(
                '.order-detail-row'
            );

        var detailButtons =
            document.querySelectorAll(
                '.order-detail-button'
            );

        var noResults =
            document.querySelector(
                '.orders-no-results'
            );

        if (
            filterButtons.length === 0 &&
            orderRows.length === 0 &&
            detailRows.length === 0 &&
            detailButtons.length === 0
        ) {
            return;
        }

        function closeAllDetails() {
            detailRows.forEach(function (row) {
                row.classList.remove('open');
                row.style.display = 'none';
            });

            detailButtons.forEach(function (button) {
                button.textContent =
                    'Details';
            });
        }

        function applyFilter(filter) {
            var visibleOrders = 0;

            orderRows.forEach(function (row) {
                var statusGroup =
                    row.dataset.statusGroup;

                var show =
                    filter === 'alle' ||
                    statusGroup === filter;

                row.style.display =
                    show
                        ? ''
                        : 'none';

                var orderId =
                    row.dataset.orderId;

                var detailRow =
                    document.getElementById(
                        'order-detail-' +
                        orderId
                    );

                if (detailRow) {
                    detailRow.classList.remove(
                        'open'
                    );

                    detailRow.style.display =
                        'none';
                }

                if (show) {
                    visibleOrders++;
                }
            });

            detailButtons.forEach(function (button) {
                button.textContent =
                    'Details';
            });

            if (noResults) {
                noResults.hidden =
                    visibleOrders !== 0;
            }
        }

        filterButtons.forEach(function (button) {
            button.addEventListener(
                'click',
                function () {
                    filterButtons.forEach(
                        function (item) {
                            item.classList.remove(
                                'active'
                            );
                        }
                    );

                    button.classList.add(
                        'active'
                    );

                    applyFilter(
                        button.dataset.filter
                    );
                }
            );
        });

        detailButtons.forEach(function (button) {
            button.addEventListener(
                'click',
                function () {
                    var orderId =
                        button.dataset.orderId;

                    var detailRow =
                        document.getElementById(
                            'order-detail-' +
                            orderId
                        );

                    if (!detailRow) {
                        return;
                    }

                    var isOpen =
                        detailRow.classList.contains(
                            'open'
                        );

                    closeAllDetails();

                    if (!isOpen) {
                        detailRow.classList.add(
                            'open'
                        );

                        detailRow.style.display =
                            'table-row';

                        button.textContent =
                            'Sluiten';
                    }
                }
            );
        });

        applyFilter('alle');
    }
);
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('employee-role');
    const driverFields = document.getElementById('driver-fields');

    const licenseType = document.getElementById('license-type');
    const licenseNumber = document.getElementById('license-number');
    const licenseExpiry = document.getElementById('license-expiry');

    const form = document.getElementById('employee-create-form');
    const password = document.getElementById('password');
    const passwordRepeat = document.getElementById('password-repeat');

    if (!roleSelect || !driverFields || !form) {
        return;
    }

    function updateDriverFields() {
        const selectedOption = roleSelect.options[roleSelect.selectedIndex];

        const selectedRole = selectedOption
            ? (selectedOption.dataset.role || '').trim().toLowerCase()
            : '';

        const isDriver = selectedRole === 'chauffeur';

        driverFields.hidden = !isDriver;
        driverFields.style.display = isDriver ? '' : 'none';

        if (licenseType) {
            licenseType.required = isDriver;
        }

        if (licenseNumber) {
            licenseNumber.required = isDriver;
        }

        if (licenseExpiry) {
            licenseExpiry.required = isDriver;
        }

        if (!isDriver) {
            if (licenseType) {
                licenseType.value = '';
            }

            if (licenseNumber) {
                licenseNumber.value = '';
            }

            if (licenseExpiry) {
                licenseExpiry.value = '';
            }

            document
                .querySelectorAll('input[name="driver_land_ids[]"]')
                .forEach(function (checkbox) {
                    checkbox.checked = false;
                });
        }
    }

    roleSelect.addEventListener('change', updateDriverFields);

    updateDriverFields();

    if (!password || !passwordRepeat) {
        return;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (password.value !== passwordRepeat.value) {
            alert('De wachtwoorden komen niet overeen.');
            passwordRepeat.focus();
            return;
        }

        const passwordValue = password.value;

        const hasMinimumLength = passwordValue.length >= 8;
        const hasUppercase = /[A-Z]/.test(passwordValue);
        const hasNumber = /[0-9]/.test(passwordValue);
        const hasSpecial = /[^A-Za-z0-9]/.test(passwordValue);

        if (
            !hasMinimumLength ||
            !hasUppercase ||
            !hasNumber ||
            !hasSpecial
        ) {
            alert(
                'Het wachtwoord moet minimaal 8 tekens bevatten, ' +
                'waarvan minimaal één hoofdletter, één cijfer ' +
                'en één speciaal teken.'
            );

            password.focus();
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Medewerker aanmaken...';
        }

        try {
            const formData = new FormData(form);

            const endpoint = new URL(
                '/dorblogistics/php/medewerkers_admin_verwerken.php',
                window.location.origin
            );

            const response = await fetch(endpoint.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const responseText = await response.text();

            if (response.status === 404) {
                alert(
                    'Het bestand voor het aanmaken van medewerkers is niet gevonden.\n\n' +
                    'Geprobeerd:\n' +
                    endpoint.href
                );

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Medewerker aanmaken';
                }

                return;
            }

            let result;

            try {
                result = JSON.parse(responseText);
            } catch (jsonError) {
                alert(
                    'De server gaf geen geldige JSON terug.\n\n' +
                    'HTTP-status: ' +
                    response.status +
                    '\n\n' +
                    'Serverantwoord:\n' +
                    responseText.substring(0, 2000)
                );

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Medewerker aanmaken';
                }

                return;
            }

            if (!response.ok || !result.success) {
                const errors = Array.isArray(result.errors)
                    ? result.errors
                    : [];

                if (errors.length > 0) {
                    alert(errors.join('\n'));
                } else {
                    alert(
                        result.message ||
                        'De medewerker kon niet worden aangemaakt.'
                    );
                }

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Medewerker aanmaken';
                }

                return;
            }

            window.location.href = '/dorblogistics/index.php?page=medewerkers_admin';

        } catch (error) {
            console.error('Fetch error:', error);

            alert(
                'Er kon geen verbinding worden gemaakt met de server.\n\n' +
                error.message
            );

            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Medewerker aanmaken';
            }
        }
    });
});