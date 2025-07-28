jQuery(function ($) {
    const ajax = BVDCRMAdmin.ajax, nonce = BVDCRMAdmin.nonce;

    // inline edit on blur ----------------------------------------------------
    $(document).on('blur', '.bvd-editable[data-field]', function () {
        const $cell = $(this),
              id    = $cell.closest('tr').data('id'),
              field = $cell.data('field'),
              value = $cell.text().trim();

        $.post(ajax, {
            action: 'bvd_crm_client_update',
            nonce, id, field, value
        });
    });

    // add new client ---------------------------------------------------------
    $('#bvd-add-client').on('submit', function (e) {
        e.preventDefault();
        const fd = $(this).serializeArray();
        fd.push({name:'action',value:'bvd_crm_client_add'},{name:'nonce',value:nonce});

        $.post(ajax, fd).done(res => {
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
