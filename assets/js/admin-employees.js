/* global jQuery, BVDCRMAdmin */

jQuery(function ($) {
    let ajax = BVDCRMAdmin.ajax,
        nonce = BVDCRMAdmin.nonce;

    function refreshNonce() {
        return $.post(ajax, { action: 'bvd_crm_refresh_nonce' })
                 .done(r => { if (r.success) nonce = r.data.nonce; });
    }

    function post(data, retry = 0) {
        data.nonce = nonce;
        return $.post(ajax, data).fail(x => {
            if (x.status === 400 && !retry) {
                refreshNonce().done(() => post(data, 1));
            }
        });
    }

    /* ---------- inline edit ---------- */
    $(document).on('blur', '.bvd-editable[data-field]', function () {
        const $c = $(this),
              id = $c.closest('tr').data('id'),
              val = $c.text().trim();
        post({ action: 'bvd_crm_employee_update', id, value: val, field: 'name' });
    });

    /* ---------- add new ---------- */
    $('#bvd-add-employee').on('submit', function (e) {
        e.preventDefault();
        const name = $('input[name="name"]', this).val().trim();
        if (!name) { return; }

        post({ action: 'bvd_crm_employee_add', name })
            .done(res => {
                if (!res.success) return alert(res.data);
                $('#bvd-employees-tbody').append(
                    `<tr data-id="${res.data.row.id}">
                        <td class="bvd-editable" data-field="name"
                            contenteditable>${res.data.row.name}</td>
                     </tr>`
                );
                this.reset();
            });
    });
});
