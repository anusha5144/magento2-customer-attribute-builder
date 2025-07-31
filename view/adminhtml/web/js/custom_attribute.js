require(['jquery', 'domReady!'], function ($) {
    let currentPage = 1;
    let perPage = 20;
    let currentFilters = {};
    let allAttributes = [];

    function renderTablePage() {
        let start = (currentPage - 1) * perPage;
        let end = start + perPage;
        let visibleAttributes = allAttributes.slice(start, end);
        let tbody = $('#attribute-table-body');
        tbody.empty();

        if (visibleAttributes.length === 0) {
            tbody.append('<tr><td colspan="8" style="text-align:center;">No attributes found.</td></tr>');
        } else {
            visibleAttributes.forEach(function (attr) {
                if (attr && attr.attribute_id) {
                    tbody.append(`
                        <tr class="clickable-row" data-id="${attr.attribute_id}" style="cursor:pointer;">
                            <td>${attr.attribute_code || ''}</td>
                            <td>${attr.frontend_label || ''}</td>
                            <td>${attr.is_required == 1 ? 'Yes' : 'No'}</td>
                            <td>${attr.is_system == 1 ? 'Yes' : 'No'}</td>
                            <td>${attr.is_visible == 1 ? 'Yes' : 'No'}</td>
                            <td>${attr.store_info || ''}</td>
                            <td>${attr.sort_order || ''}</td>
                            <td>
                                <button class="action-edit" data-id="${attr.attribute_id}" style="background: none; border: none; color: rgba(33, 72, 248, 0.93); padding: 6px 0; cursor: pointer;">Edit</button>
                            </td>
                        </tr>
                    `);
                }
            });
        }

        let totalPages = Math.ceil(allAttributes.length / perPage);
        let controls = '';
        for (let i = 1; i <= totalPages; i++) {
            controls += `<button class="page-btn" data-page="${i}" style="margin: 0 2px; padding: 4px 8px;">${i}</button>`;
        }
        $('#pagination-controls').html(controls);
        $('#record-count').text('Total Records: ' + allAttributes.length);
        $('#current-page-box').text('Page ' + currentPage);
    }

    function loadAttributes(filters = {}) {
        $.ajax({
            url: window.customerAttributeUrls.fetch,
            type: 'GET',
            dataType: 'json',
            data: filters,
            success: function (response) {
                if (response.success) {
                    allAttributes = response.attributes;
                    renderTablePage();
                } else {
                    console.error('Fetch failed', response.message);
                }
            },
            error: function (xhr) {
                console.error('AJAX error', xhr);
            }
        });
    }

    $('#add_new_attribute').on('click', function () {
        window.location.href = window.customerAttributeUrls.new;
    });

    $('#search').on('click', function () {
        currentPage = 1;
        currentFilters = {};
        const fieldMap = ['attribute_code', 'frontend_label', 'is_required', 'is_system', 'is_visible', 'store_info', 'sort_order'];

        $('.filter-row input, .filter-row select').each(function (index) {
            let val = $(this).val();
            if (val !== '') {
                currentFilters[fieldMap[index]] = val;
            }
        });

        loadAttributes(currentFilters);
    });

    $('#reset_filters').on('click', function () {
        currentPage = 1;
        $('.filter-row input, .filter-row select').val('');
        currentFilters = {};
        loadAttributes();
    });

    $('#per-page').on('change', function () {
        perPage = parseInt($(this).val());
        currentPage = 1;
        renderTablePage();
    });

    $(document).on('click', '.page-btn', function () {
        currentPage = parseInt($(this).data('page'));
        renderTablePage();
    });

    $(document).on('click', '.clickable-row', function () {
        var attributeId = $(this).data('id');
        if (attributeId) {
            location.href = window.customerAttributeUrls.edit + '?id=' + attributeId;
        }
    });

    $('#submit_attribute').on('click', function () {
        var $submitButton = $(this);
        
        // Validate required fields
        var catalogInput = $('#catalog_input').val();
        var dropdownOptions = $('#dropdown_options').val();
        
        if (catalogInput === 'dropdown' && !dropdownOptions.trim()) {
            alert('Please enter dropdown options when selecting dropdown as the input type.');
            return;
        }
        
        $submitButton.prop('disabled', true);

        var postData = {
            form_key: $('#form_key').val(),
            attribute_id: $('#attribute_id').val(),
            default_label: $('#default_label').val(),
            attribute_code: $('#attribute_code').val(),
            store_view: $('#store_view').val(),
            catalog_input: $('#catalog_input').val(),
            dropdown_options: $('#dropdown_options').val(),
            input_validation: $('#input_validation').val(),
            default_value: $('#default_value').val(),
            values_required: $('#values_required').val(),
            is_visible: $('#is_visible').val(),
            used_in_forms: $('#used_in_forms').val(),
            sorting_order: $('#sorting_order').val()
        };

        $.ajax({
            url: $submitButton.data('save-url'),
            type: 'POST',
            data: postData,
            showLoader: true,
            success: function (response) {
                if (response.success) {
                    alert(response.message);
                    window.location.href = $submitButton.data('redirect-url');
                } else {
                    alert('Error: ' + response.message);
                    $submitButton.prop('disabled', false);
                }
            },
            error: function () {
                alert('Something went wrong while saving the attribute.');
                $submitButton.prop('disabled', false);
            }
        });
    });

    $('#save_continue_edit').on('click', function () {
        // Validate required fields
        var catalogInput = $('#catalog_input').val();
        var dropdownOptions = $('#dropdown_options').val();
        
        if (catalogInput === 'dropdown' && !dropdownOptions.trim()) {
            alert('Please enter dropdown options when selecting dropdown as the input type.');
            return;
        }
        
        var postData = {
            form_key: $('#form_key').val(),
            attribute_id: $('#attribute_id').val(),
            default_label: $('#default_label').val(),
            attribute_code: $('#attribute_code').val(),
            store_view: $('#store_view').val(),
            catalog_input: $('#catalog_input').val(),
            dropdown_options: $('#dropdown_options').val(),
            input_validation: $('#input_validation').val(),
            default_value: $('#default_value').val(),
            values_required: $('#values_required').val(),
            is_visible: $('#is_visible').val(),
            used_in_forms: $('#used_in_forms').val(),
            sorting_order: $('#sorting_order').val()
        };

        $.ajax({
            url: $('#save_continue_edit').data('save-url'),
            type: 'POST',
            data: postData,
            showLoader: true,
            success: function (response) {
                if (response.success) {
                    alert(response.message);
                    const d = response.post_data;
                    $('#attribute_id').val(d.attribute_id);
                    $('#default_label').val(d.default_label);
                    $('#attribute_code').val(d.attribute_code);
                    $('#store_view').val(d.store_view);
                    $('#catalog_input').val(d.catalog_input);
                    $('#dropdown_options').val(d.dropdown_options || '');
                    $('#input_validation').val(d.input_validation);
                    $('#default_value').val(d.default_value);
                    $('#values_required').val(d.values_required);
                    $('#show_account').val(d.show_account);
                    $('#show_shipping').val(d.show_shipping);
                    $('#show_registration').val(d.show_registration);
                    $('#sorting_order').val(d.sorting_order);
                    
                    // Trigger change event to show/hide dropdown options
                    $('#catalog_input').trigger('change');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function () {
                alert('Something went wrong while saving the attribute.');
            }
        });
    });

    $('#delete_attribute').on('click', function () {
        if (confirm('Are you sure you want to delete this attribute?')) {
            var attributeCode = $('#attribute_code').val();
            $.ajax({
                url: $('#delete_attribute').data('delete-url'),
                type: 'POST',
                data: {
                    attribute_code: attributeCode,
                    form_key: $('#form_key').val()
                },
                showLoader: true,
                success: function (response) {
                    if (response.success) {
                        alert(response.message);
                        window.location.href = $('#delete_attribute').data('redirect-url');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function () {
                    alert('Something went wrong while deleting the attribute.');
                }
            });
        }
    });

    if ($('#attribute-table-body').length) {
        loadAttributes();
    }

    // Handle dropdown options visibility
    $('#catalog_input').on('change', function () {
        const selectedValue = $(this).val();
        const dropdownContainer = $('#dropdown_options_container');
        
        if (selectedValue === 'dropdown') {
            dropdownContainer.show();
        } else {
            dropdownContainer.hide();
            $('#dropdown_options').val('');
        }
    });

    // Trigger change event on page load to set initial state
    $('#catalog_input').trigger('change');

    $('#reset_form').on('click', function () {
        const form = $('#add-attribute-form');
        form.find('input[type="text"]').val('');
        form.find('textarea').val('');
        form.find('select').each(function () {
            this.selectedIndex = 0;
        });
        form.find('select[multiple]').each(function () {
            $(this).find('option').prop('selected', false);
        });
        // Hide dropdown options container on reset
        $('#dropdown_options_container').hide();
    });
});
