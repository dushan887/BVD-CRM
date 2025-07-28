jQuery($=>{
    const a=BVDCRMAdmin.ajax,n=BVDCRMAdmin.nonce;
    $('#bvd-export').on('click',()=>{ window.location=a+'?action=bvd_crm_export&_wpnonce='+n; });
    $('#bvd-nuke').on('click',()=>{
        if(!confirm('Delete ALL data?'))return;
        if(!confirm('This cannot be undone. Sure?'))return;
        $.post(a,{action:'bvd_crm_nuke',_wpnonce:n}).done(()=>location.reload());
    });
});
