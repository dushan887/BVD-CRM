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
       1. Picker helpers
       ====================================================== */
    async function buildPicker() {
        const today = new Date();
        const curY  = today.getFullYear();
        const curM  = today.getMonth() + 1;  // 1‑based

        $selVal.empty();

        if (periodType === 'month') {
            for (let i = 0; i < 15; i++) {
                const d = new Date(curY, curM - 1 - i, 1);
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const val = `${y}-${m}`;
                const lbl = d.toLocaleString('default', { month:'short', year:'numeric' });
                $selVal.append($('<option>').val(val).text(lbl));
            }
        } else {
            const curQ = Math.ceil(curM / 3);
            let added = 0;
            for (let y = curY; y >= curY - 3; y--) {
                for (let q = (y === curY ? curQ : 4); q >= 1; q--) {
                    $selVal.append(
                        $('<option>').val(`${y}-Q${q}`).text(`Q${q} ${y}`)
                    );
                    if (++added >= 12) break;
                }
                if (added >= 12) break;
            }
        }

        periodVal = $selVal.val();
    }

    /* ======================================================
       2. Summary AJAX
       ====================================================== */
    function loadSummary() {
        $spinner.show(); $empty.hide(); $table.hide();

        $.get(BVDCRM.ajax, {
            action      :'bvd_crm_client_summary',
            nonce       : BVDCRM.nonce,
            period_type : periodType,
            value       : periodVal
        })
        .done(renderSummary)
        .fail(jq => {
            if (jq.status === 400) { refreshNonce(loadSummary); }
            else console.error(jq.responseText);
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
            const limit = +(r.monthly_limit || r.quarterly_limit) || 0;
            const usage = limit ? Math.round(spent/limit*100)+'%' : '—';
            return [
                r.id,
                r.name,
                periodType==='month' ? fmtMonth(periodVal) : periodVal,
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

            if (row.child.isShown()){ row.child.hide(); $tr.removeClass('shown'); return; }
            if (row.child() && row.child().length){ row.child.show(); $tr.addClass('shown'); return; }

            $.get(BVDCRM.ajax,{
                action:'bvd_crm_client_tasks',
                nonce :BVDCRM.nonce,
                client:row.data()[0],
                period_type:periodType,
                value:periodVal
            }).done(r=>{
                if(!r.success || !Array.isArray(r.data)){     // guard
                    console.error(r.data);
                    row.child('<em>Error loading tasks.</em>').show();
                    $tr.addClass('shown');
                    return;
                }
                row.child(renderTasks(r.data)).show(); $tr.addClass('shown');
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

        $('#bvd-modal-body')
            .html('<div class="spinner-border" role="status"></div>');
        taskModal.show();

        $.get(BVDCRM.ajax,{
            action:'bvd_crm_task_details',
            nonce :BVDCRM.nonce,
            job   :jobId          // no period args → back‑end returns *all* years
        }).done(r=>{
            if(!r.success || !Array.isArray(r.data)){
                $('#bvd-modal-body').text(r.data||'No data'); return;
            }

            const rows = r.data.map(d=>`
                <tr>
                  <td>${d.clinic}</td>
                  <td>${d.employee}</td>
                  <td>${d.date}</td>
                  <td>${(+d.hrs).toFixed(2)}</td>
                </tr>`).join('');

            $('#bvd-modal-body').html(`
                <table class="table table-striped table-sm">
                    <thead>
                      <tr><th>Clinic</th><th>Employee</th><th>Date</th><th>Hours</th></tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>`);
        });
    });

    /* ── util ───────────────────────────────────────────── */
    function fmtMonth(ym){
        const [y,m]=ym.split('-');
        return new Date(y, (+m)-1, 1)
               .toLocaleString('default', { month:'short', year:'numeric' });
    }
});
