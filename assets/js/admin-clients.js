jQuery(function ($) {
    let ajax = BVDCRMAdmin.ajax, nonce = BVDCRMAdmin.nonce;

    // Helper function to refresh nonce on 400 errors
    function refreshNonce() {
        return $.post(ajax, { action: 'bvd_crm_refresh_nonce' })
            .done(res => { 
                if (res.success) nonce = res.data.nonce; 
            });
    }

    // Enhanced AJAX with auto-retry on nonce expiration
    function ajaxPost(data, retryCount = 0) {
        data.nonce = nonce;
        return $.post(ajax, data).fail(xhr => {
            if (xhr.status === 400 && retryCount === 0) {
                refreshNonce().done(() => ajaxPost(data, 1));
            }
        });
    }

    // inline edit on blur ----------------------------------------------------
    $(document).on('blur', '.bvd-editable[data-field]', function () {
        const $cell = $(this),
              id    = $cell.closest('tr').data('id'),
              field = $cell.data('field'),
              value = $cell.text().trim();

        ajaxPost({
            action: 'bvd_crm_client_update',
            id, field, value
        });
    });

    // add new client ---------------------------------------------------------
    $('#bvd-add-client').on('submit', function (e) {
        e.preventDefault();
        const fd = $(this).serializeArray();
        fd.push({name:'action',value:'bvd_crm_client_add'});

        ajaxPost(Object.fromEntries(fd.map(item => [item.name, item.value])))
            .done(res => {
                if (!res.success) return alert(res.data);
                const r = res.data.row;
                $('#bvd-clients-tbody').append(rowHtml(r));
                this.reset();
            });
    });

    function rowHtml(r) {
        return `<tr data-id="${r.id}">
            <td>${r.name}</td>
            <td class="bvd-editable" data-field="monthly_limit" contenteditable>${r.monthly_limit}</td>
            <td class="bvd-editable" data-field="quarterly_limit" contenteditable>${r.quarterly_limit}</td>
            <td class="bvd-editable" data-field="notes" contenteditable>${r.notes ?? ''}</td>
        </tr>`;
    }
});
