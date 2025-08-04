/* global jQuery, BVDCRMAdmin */
jQuery(function ($) {

    const $form   = $('#bvd-import-form');
    const $status = $('#bvd-import-progress');
    let   fileSize = 0,
          imported = 0;

    $form.on('submit', function (e) {
        e.preventDefault();
        const file = this.file.files[0];
        if (!file) { return; }

        // visual / UI reset ----------------------------
        $status.html(
            '<div class="progress" style="max-width:340px;">' +
              '<div class="progress-bar" role="progressbar" style="width:0%">0%</div>' +
            '</div>'
        );
        const $bar = $status.find('.progress-bar');

        // initial upload -------------------------------
        const fd = new FormData();
        fd.append('action', 'bvd_crm_import_start');
        fd.append('nonce',  BVDCRMAdmin.nonce);
        fd.append('file',   file);

        $.ajax({
            url:  BVDCRMAdmin.ajax,
            type: 'POST',
            data: fd,
            cache: false,
            processData: false,
            contentType: false
        }).done(res => {
            if (!res.success) { alert(res.data); return; }
            fileSize = res.data.filesize;
            poll(res.data.path, res.data.pointer);
        }).fail(()=> alert('Upload failed'));

        /* ----------- chunk loop ---------------------- */
        function poll(path, pointer) {

            $.post(BVDCRMAdmin.ajax, {
                action : 'bvd_crm_import_chunk',
                nonce  : BVDCRMAdmin.nonce,
                path   : path,
                pointer: pointer
            }).done(r => {
                if (!r.success) { alert(r.data); return; }

                imported += r.data.rows;
                const done   = r.data.done || false;
                const bytes  = r.data.pointer || r.data.bytes || pointer;
                const pct    = Math.min(100, Math.round(bytes / fileSize * 100));

                $bar.css('width', pct + '%')
                    .text(pct + '% – ' + imported + ' rows');

                if (done) {
                    $bar.addClass('bg-success').text('Done (' + imported + ' rows)');
                } else {
                    poll(path, r.data.pointer);
                }
            }).fail(()=> alert('Chunk failed'));
        }
    });
});
