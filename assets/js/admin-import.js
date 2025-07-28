jQuery(function ($) {
    $('#bvd-import-form').on('submit', function (e) {
        e.preventDefault();
        const file = this.file.files[0];
        if (!file) return;

        const $status = $('#bvd-import-progress').text('Uploading…');

        const formData = new FormData();
        formData.append('action', 'bvd_crm_import_start');
        formData.append('nonce', BVDCRMAdmin.nonce);
        formData.append('file', file);

        $.ajax({
            url: BVDCRMAdmin.ajax,
            method: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false
        }).done(res => {
            if (!res.success) return alert(res.data);
            pollChunk(res.data.path, res.data.pointer);
        }).fail(() => alert('Upload error'));

        function pollChunk(path, pointer) {
            $status.text('Importing …');

            $.post(BVDCRMAdmin.ajax, {
                action: 'bvd_crm_import_chunk',
                nonce: BVDCRMAdmin.nonce,
                path: path,
                pointer: pointer
            }, res => {
                if (!res.success) return alert(res.data);
                if (res.data.done) return $status.text('✅ Done');
                pollChunk(path, res.data.pointer);
            });
        }
    });
});
