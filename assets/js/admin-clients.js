/* global jQuery, BVDCRMAdmin */
jQuery(function ($) {

	let ajax  = BVDCRMAdmin.ajax,
	    nonce = BVDCRMAdmin.nonce;

	/* ---------- small helper ---------- */
	function refreshNonce () {
		return $.post( ajax, { action: 'bvd_crm_refresh_nonce' } )
		        .done( r => { if ( r.success ) nonce = r.data.nonce; } );
	}
	function post ( data, retry = 0 ) {
		data.nonce = nonce;
		return $.post( ajax, data ).fail( x => {
			if ( x.status === 400 && ! retry ) {
				refreshNonce().done( () => post( data, 1 ) );
			}
		} );
	}

	/* =========================================================
	   1. Inline editing  (existing logic – unchanged)
	   ========================================================= */
	$( document ).on( 'blur', '.bvd-editable[data-field]', function () {
		const $c   = $( this ),
		      id    = $c.closest( 'tr' ).data( 'id' ),
		      field = $c.data( 'field' ),
		      value = $c.text().trim();

		post( { action:'bvd_crm_client_update', id, field, value } );
	} );

	/* =========================================================
	   2. Add new  (existing logic – unchanged)
	   ========================================================= */
	$('#bvd-add-client').on('submit', function (e) {
		e.preventDefault();
		const fd = $(this).serializeArray();
		fd.push( { name:'action', value:'bvd_crm_client_add' } );

		post( Object.fromEntries( fd.map( i => [ i.name, i.value ] ) ) )
			.done( res => {
				if ( ! res.success ) return alert( res.data );
				const r = res.data.row;
				$('#bvd-clients-tbody').append( rowHtml( r ) );
				this.reset();
			} );
	});
	function rowHtml ( r ) {
		return `<tr data-id="${r.id}">
			<td><input type="checkbox" class="bvd-row-cb"></td>
			<td>${r.name}</td>
			<td class="bvd-editable" data-field="monthly_limit" contenteditable>${r.monthly_limit}</td>
			<td class="bvd-editable" data-field="quarterly_limit" contenteditable>${r.quarterly_limit}</td>
			<td class="bvd-editable" data-field="notes" contenteditable>${r.notes ?? ''}</td>
		</tr>`;
	}

	/* =========================================================
	   3. MERGE  (new)
	   ========================================================= */
	const $mergeBox   = $('#bvd-merge-box'),
	      $mergeSel   = $('#bvd-merge-target'),
	      $mergeBtn   = $('#bvd-merge-btn'),
	      $checkAll   = $('#bvd-check-all');

	/* 3.1  master checkbox */
	$checkAll.on('change', function () {
		const on = this.checked;
		$('.bvd-row-cb').prop('checked', on).trigger('change');
	});

	/* 3.2  per‑row checkbox → rebuild merge UI */
	$(document).on('change', '.bvd-row-cb', function () {
		const $rows = $('.bvd-row-cb:checked').closest('tr');
		if ($rows.length < 2) {
			$mergeBox.hide();
			return;
		}

		/* populate <select> with selected rows */
		$mergeSel.empty();
		$rows.each(function(){
			const id   = $(this).data('id'),
			      name = $('td:eq(1)', this).text();
			$mergeSel.append($('<option>').val(id).text(name));
		});
		$mergeBox.show();
	});

	/* 3.3  merge‑button click */
	$mergeBtn.on('click', function () {
		const ids    = $('.bvd-row-cb:checked').closest('tr')
		               .map( (i,el) => $(el).data('id') ).get();
		const target = parseInt( $mergeSel.val(), 10 );

		if ( ids.length < 2 || ! target ) {
			return alert( 'Select at least two rows.' );
		}

		if ( ! confirm( 'Merge the selected clients? This cannot be undone.' ) ) {
			return;
		}

		post( {
			action : 'bvd_crm_clients_merge',
			ids    : ids,
			target : target
		} ).done( r => {
			if ( ! r.success ) return alert( r.data || 'Error' );
			location.reload();
		} );
	});
});
