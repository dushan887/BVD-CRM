/* global jQuery, BVDCRM, bootstrap */

jQuery(function ($) {

    /* ── DOM handles & state ─────────────────────────────── */
    const $spinner  = $('.bvd-crm-summary .spinner-border');
    const $table    = $('#bvd-crm-table');
    const $empty    = $('#bvd-empty');
    const $toolbar  = $('#bvd-toolbar');
    const $selType  = $('#bvd-period-type');
    const $selVal   = $('#bvd-period-val');

    let periodType      = 'month';
    let periodVal       = null;   // null → “current”
    let fallbackHops    = 0;      // how many times we already stepped back
    let isLoading       = false;  // global flag to prevent spam

    /* ── bootstrap modal instance (create once) ──────────── */
    const taskModalEl   = document.getElementById('bvdTaskModal');
    const taskModal     = new bootstrap.Modal(taskModalEl);

    /* ── bootstrap ───────────────────────────────────────── */
    buildPicker().then(() => {
        $toolbar.show();
        loadSummary();
    });

    $selType.on('change', () => {
        periodType   = $selType.val();
        fallbackHops = 0;
        buildPicker().then(loadSummary);
    });
    $selVal.on('change', () => {
        periodVal    = $selVal.val();
        fallbackHops = 0;          // manual change resets fallback
        loadSummary();
    });

    /* ======================================================
       1. Picker helpers (dynamic: only show periods with data)
       ====================================================== */
    async function buildPicker() {
        $selVal.empty();
        try {
            const res = await $.get(BVDCRM.ajax, {
                action: 'bvd_crm_available_periods',
                type: periodType,
                nonce: BVDCRM.nonce
            });
            if (res.success && Array.isArray(res.data) && res.data.length) {
                res.data.forEach(p => {
                    const lbl = periodType === 'month'
                        ? fmtMonth(p)
                        : p.replace(/(\d{4})-Q(\d)/, 'Q$2 $1');
                    $selVal.append($('<option>').val(p).text(lbl));
                });
                periodVal = $selVal.val() || null;
            } else {
                $selVal.append($('<option>').val('').text('No data available'));
                periodVal = null;
            }
        } catch (e) {
            console.error(e);
            $selVal.append($('<option>').val('').text('Error loading periods'));
        }
    }

    /* ======================================================
       2. Summary AJAX
       ====================================================== */
    // Fetch and render summary, with spinner, disabled controls, and spam prevention
    function loadSummary() {
        if (isLoading) return; // prevent spam
        isLoading = true;
        $spinner.show(); $empty.hide(); $table.hide();
        $selType.prop('disabled', true);
        $selVal.prop('disabled', true);
        $.get(BVDCRM.ajax, {
            action      : 'bvd_crm_client_summary',
            nonce       : BVDCRM.nonce,
            period_type : periodType,
            value       : periodVal
        })
        .done(res => {
            renderSummary(res);
            isLoading = false;
            $selType.prop('disabled', false);
            $selVal.prop('disabled', false);
        })
        .fail(jq => {
            if (jq.status === 400) { refreshNonce(loadSummary); }
            else console.error(jq.responseText);
            isLoading = false;
            $selType.prop('disabled', false);
            $selVal.prop('disabled', false);
        });
    }
    function refreshNonce(cb){
        $.get(BVDCRM.ajax,{action:'bvd_crm_nonce'})
         .done(r=>{ if(r.success){ BVDCRM.nonce=r.data; cb(); }});
    }

    /* ======================================================
       3. DataTable render  (+auto‑fallback)
       ====================================================== */
    function renderSummary(res) {
        $spinner.hide();

        if (!res.success) { $empty.text(res.data||'Error').show(); return; }

        /* ---------- AUTO‑FALLBACK: move to previous option until we hit data ---------- */
        if ((!Array.isArray(res.data) || !res.data.length) && fallbackHops < 11) {
            const $next = $selVal.find('option:selected').next(); // next = older period
            if ($next.length) {
                fallbackHops++;
                $selVal.val($next.val()).trigger('change');       // will call loadSummary()
                return;
            }
        }

        if (!Array.isArray(res.data) || !res.data.length){ $empty.show(); return; }

        // (re‑)initialise DataTable
        if ($.fn.DataTable.isDataTable($table)) {
            $table.DataTable().destroy();
            $table.find('tbody').empty();
        }

        const rows = res.data.map(r => {
            const spent = +r.default_billable || 0;
            const limit = periodType === 'month' ? (+r.monthly_limit || 0) : (+r.quarterly_limit || 0);
            const usage = limit ? Math.round(spent / limit * 100) + '%' : '—';
            return [
                r.id,
                r.name,
                periodType === 'month' ? fmtMonth(periodVal) : periodVal,
                spent.toFixed(2) + (limit ? ' / ' + limit : ''),
                usage
            ];
        });

        const dt = $table.DataTable({
            data: rows,
            order:[[1,'asc']],
            pageLength:50,
            lengthMenu:[25,50,100,250],
            deferRender:true,
            columns:[
                {title:'ID',visible:false},
                {title:'Client'},
                {title:'Period'},
                {title:'Billable hrs / Limit'},
                {title:'Usage'}
            ]
        });

        $table.show();

        /* ── expandable rows ─────────────────────────────── */
        $('#bvd-crm-table tbody').off('click').on('click','tr',function(){
            const row = dt.row(this); if(!row.data()) return;
            const $tr = $(this);
            if ($tr.hasClass('loading')) return; // prevent spam clicks during load

            if (row.child.isShown()){ row.child.hide(); $tr.removeClass('shown'); return; }
            if (row.child() && row.child().length){ row.child.show(); $tr.addClass('shown'); return; }
            // show spinner while loading tasks
            row.child('<div class="spinner-border text-primary my-3 mx-auto d-block" role="status"><span class="visually-hidden">Loading tasks…</span></div>').show();
            $tr.addClass('shown loading'); // for spam prevention
            $.get(BVDCRM.ajax,{
                action      :'bvd_crm_client_tasks',
                nonce       : BVDCRM.nonce,
                client      : row.data()[0],
                period_type : periodType,
                value       : periodVal
            }).done(r=>{
                if (!r.success || !Array.isArray(r.data)) { // guard
                    console.error(r.data);
                    row.child('<em>Error loading tasks.</em>');
                    $tr.removeClass('loading');
                    return;
                }
                row.child(renderTasks(r.data));
                $tr.removeClass('loading');
            }).fail(() => {
                row.child('<em>Error loading tasks.</em>');
                $tr.removeClass('loading');
            });
        });
    }

    /* ======================================================
       4. Render task list inside child row
       ====================================================== */
    function renderTasks(data){

        if(!Array.isArray(data) || !data.length){
            return '<em>No tasks in this period.</em>';
        }

        const bucket={Default:[],Project:[],'Non‑Billable':[]};
        const totals={Default:0,Project:0,'Non‑Billable':0};

        data.forEach(t=>{
            const cat = t.non_billable>0?'Non‑Billable':
                        (t.project_billable>0?'Project':'Default');
            totals[cat]+= +t.total || 0;

            const hrsStr = (BVDCRM.isAdmin && Array.isArray(t.employees) && t.employees.length)
                ? t.employees.map(e=>`${e.emp}&nbsp;${(+e.hrs).toFixed(2)}h`).join('<br>')
                : (+t.total).toFixed(2)+'h';

            bucket[cat].push(`
                <tr class="bvd-task-row" data-job="${t.job_id}">
                    <td>
                       <a href="#" class="bvd-job-link" data-job="${t.job_id}">
                         ${t.job_code||'—'}
                       </a>
                       &nbsp;${t.title}
                    </td>
                    <td>${hrsStr}</td>
                </tr>`);
        });

        return Object.keys(bucket).filter(cat=>bucket[cat].length).map(cat=>`
            <h6 class="mt-2">${cat} Tasks
               <span class="badge bg-secondary">${totals[cat].toFixed(2)}h</span>
            </h6>
            <table class="table table-bordered table-hover table-sm">
                <thead><tr><th>Task</th><th>Hours</th></tr></thead>
                <tbody>${bucket[cat].join('')}</tbody>
            </table>`).join('') || '<em>No tasks in this period.</em>';
    }

    /* ======================================================
       5. Modal (per‑job details – admin only)
       ====================================================== */
    $(document)
    .off('click','.bvd-job-link, .bvd-task-row')
    .on('click','.bvd-job-link, .bvd-task-row',function(e){
        e.preventDefault();
        if (!BVDCRM.isAdmin) return;                // only admins

        const jobId = $(this).data('job');

        $('#bvd-modal-body').html('<div class="spinner-border" role="status"></div>');
        taskModal.show();
        $.get(BVDCRM.ajax,{
            action : 'bvd_crm_task_details',
            nonce : BVDCRM.nonce,
            job : jobId
        }).done(r=>{
            if (!r.success || !Array.isArray(r.data) || !r.data.length) {
                $('#bvd-modal-body').html('<p class="text-muted">No historical data for this task.</p>');
                return;
            }
            // Group by clinic > date > employees
            const grouped = r.data.reduce((acc, d) => {
                if (!acc[d.clinic]) acc[d.clinic] = { dates: {}, total: 0 };
                if (!acc[d.clinic].dates[d.date]) acc[d.clinic].dates[d.date] = { employees: [], date_total: 0 };
                acc[d.clinic].dates[d.date].employees.push({ emp: d.employee, hrs: +d.hrs });
                acc[d.clinic].dates[d.date].date_total += +d.hrs;
                acc[d.clinic].total += +d.hrs;
                return acc;
            }, {});
            // Render sections per clinic > date > employees
            let html = '';
            for (const [clinic, { dates, total }] of Object.entries(grouped)) {
                let clinicHtml = `<h6 class="mt-3">${clinic} <span class="badge bg-secondary ms-2">Total: ${total.toFixed(2)}h</span></h6>`;
                for (const [date, { employees, date_total }] of Object.entries(dates)) {
                    const empRows = employees.map(e => `
                        <tr>
                        <td>${e.emp}</td>
                        <td>${e.hrs.toFixed(2)}h</td>
                        </tr>`).join('');
                    clinicHtml += `
                        <h6 class="mt-2 ms-3">${fmtDate(date)} <span class="badge bg-info ms-2">Date Total: ${date_total.toFixed(2)}h</span></h6>
                        <table class="table table-striped table-sm ms-3">
                            <thead><tr><th>Employee</th><th>Hours</th></tr></thead>
                            <tbody>${empRows}</tbody>
                        </table>`;
                }
                html += clinicHtml;
            }
            $('#bvd-modal-body').html(html);
        });
    });

    function fmtDate(ymd) {
        const [y, m, d] = ymd.split('-');
        return `${d}.${m}.${y}`;
    }

    /* ── util ───────────────────────────────────────────── */
    function fmtMonth(ym){
        const [y,m]=ym.split('-');
        return new Date(y, (+m)-1, 1)
               .toLocaleString('default', { month:'short', year:'numeric' });
    }
});
