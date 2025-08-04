/* eslint-env jquery */
jQuery(($) => {
    const ajax  = BVDCRMAdmin.ajax;
    let   nonce = BVDCRMAdmin.nonce;

    /* ── helper to refresh nonce on 400 ─────────────────── */
    function refreshNonce(cb) {
        $.post(ajax, { action: 'bvd_crm_refresh_nonce' })
            .done(r => { if (r.success) { nonce = r.data.nonce; cb(); } });
    }

    /* ── CSV export (unchanged) ─────────────────────────── */
    $('#bvd-export').on('click', () => {
        window.location = `${ajax}?action=bvd_crm_export&_wpnonce=${nonce}`;
    });

    /* ── DANGER ZONE – NUKE ─────────────────────────────── */
    $('#bvd-nuke').on('click', function () {

        if (!confirm('Delete ALL plugin data?'))            return;
        if (!confirm('This cannot be undone. Are you sure?')) return;

        const $btn = $(this).prop('disabled', true)
                            .text('Deleting…');

        $.post(ajax, {
            action : 'bvd_crm_nuke',
            nonce  : nonce                // <<<  unified field name
        })
        .done(r => {
            if (r.success) {
                location.reload();
            } else {
                alert(r.data || 'Error');
            }
        })
        .fail(xhr => {
            if (xhr.status === 400) {
                /* nonce expired – fetch a new one and retry once */
                refreshNonce(() => $btn.trigger('click'));
            } else {
                alert('Request failed – see console.');
                console.error(xhr.responseText);
                $btn.prop('disabled', false).text('⚠ NUKE all plugin tables');
            }
        });
    });
});
