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

    $(document).on('blur', '.bvd-editable[data-field]', function () {
        const $cell = $(this),
              id    = $cell.closest('tr').data('id'),
              field = $cell.data('field'),
              value = $cell.text().trim();

        post({ action: 'bvd_crm_job_update', id, field, value });
    });
});
