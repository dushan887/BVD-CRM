jQuery(function ($) {
    const $spinner = $('.bvd-crm-summary .spinner-border');
    const $table   = $('#bvd-crm-table');
    let periodType = 'month', periodVal = null; // could expose period switcher later

    function freshNonce(cb){
        $.get(BVDCRM.ajax, { action:'bvd_crm_nonce' }).done(r=>{
            if(r.success){ BVDCRM.nonce = r.data; cb(); }
            else console.error('Nonce refresh failed');
        });
    }

    loadSummary();          // initial
    $(document).on('bvd‑reload', loadSummary); // you can trigger elsewhere

    function loadSummary(){
        $.get(BVDCRM.ajax,{
            action:'bvd_crm_client_summary',
            _wpnonce:BVDCRM.nonce,        // fallback param name WP always checks
            nonce:BVDCRM.nonce,
            period_type:'month'
        })
        .done(renderSummary)
        .fail((jq,x,e)=>{
            if(jq.status===400){ freshNonce(loadSummary); } // retry once
            else console.error('AJAX',x,e,jq.responseText);
        });
    }

    function renderSummary(res) {
        if (!res.success) return;

        const rows = res.data.map(r => [
            r.id,
            r.name,
            periodType === 'month' ? periodVal ?? 'Current month' : periodVal,
            (r.default_billable || 0).toFixed(2) + '/' + (r.monthly_limit || r.quarterly_limit),
            Math.round((r.default_billable / (r.monthly_limit || r.quarterly_limit)) * 100) + '%'
        ]);

        const dt = $table.DataTable({
            data: rows,
            columns: [
                { title: 'ID', visible:false }, // hidden but needed later
                { title: 'Client' },
                { title: 'Period' },
                { title: 'Billable hrs / Limit' },
                { title: 'Usage' }
            ]
        });

        $spinner.hide();
        $table.show();

        // 2) expandable rows --------------------------------------------------
        $('#bvd-crm-table tbody').on('click', 'tr', function () {
            let tr = $(this),
                row = dt.row(tr);

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
                return;
            }

            if (row.child() && row.child().length) { // already fetched
                row.child.show();
                tr.addClass('shown');
                return;
            }

            // fetch tasks for this client
            $.get(BVDCRM.ajax, {
                action: 'bvd_crm_client_tasks',
                nonce:  BVDCRM.nonce,
                client: row.data()[0],
                period_type: periodType
            }).done(resp => {
                if (!resp.success) return;
                const html = renderTasks(resp.data);
                row.child(html).show();
                tr.addClass('shown');
            });
        });

        // 3) modal: delegated click on any task row ---------------------------
        $(document).on('click', '.bvd-task-row', function () {
            const jobId = $(this).data('job');

            $('#bvd-modal-body').html('<div class="spinner-border" role="status"></div>');
            const modal = new bootstrap.Modal('#bvdTaskModal');
            modal.show();

            $.get(BVDCRM.ajax, {
                action: 'bvd_crm_task_details',
                nonce:  BVDCRM.nonce,
                job:    jobId,
                period_type: periodType
            }).done(r => {
                if (!r.success) return $('#bvd-modal-body').text(r.data || 'Error');

                let out = '<table class="table table-sm"><thead><tr><th>Client</th><th>Employee</th><th>Hours</th></tr></thead><tbody>';
                r.data.forEach(d => {
                    out += `<tr><td>${d.name}</td><td>${d.employee}</td><td>${parseFloat(d.hrs).toFixed(2)}</td></tr>`;
                });
                $('#bvd-modal-body').html(out + '</tbody></table>');
            });
        });
    }

    // ------------------ helpers ---------------------------------------------
    function renderTasks(data) {
        const bucket = { 'Default':[], 'Project':[], 'Non‑Billable':[] };

        data.forEach(t => {
            let cat = t.non_billable > 0 ? 'Non‑Billable'
                    : (t.project_billable > 0 ? 'Project' : 'Default');

            // hrs string depending on admin flag & employee breakdown
            let hrs;
            if (BVDCRM.isAdmin && t.employees) {
                hrs = t.employees.map(e => `${e.emp} ${parseFloat(e.hrs).toFixed(2)}h`).join('<br>');
            } else {
                hrs = parseFloat(t.total).toFixed(2) + 'h';
            }
            bucket[cat].push(`<tr class="bvd-task-row" data-job="${t.job_id}">
                                <td>${t.title}</td><td>${hrs}</td></tr>`);
        });

        let html = '';
        Object.keys(bucket).forEach(cat => {
            if (!bucket[cat].length) return;
            html += `<h6 class="mt-2">${cat} Tasks</h6>
                     <table class="table table-bordered table-hover table-sm">
                        <thead><tr><th>Task</th><th>Hours</th></tr></thead>
                        <tbody>${bucket[cat].join('')}</tbody></table>`;
        });

        return html || '<em>No tasks in this period.</em>';
    }
});
