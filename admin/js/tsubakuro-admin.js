/* global jQuery, tsubakuroAdmin */
( function ( $ ) {
	'use strict';

	// =========================================================================
	// Delete task
	// =========================================================================
	function deleteTask( taskId ) {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( 'このタスクを削除しますか？' ) ) {
			return;
		}

		$.post(
			tsubakuroAdmin.ajaxUrl,
			{
				action: 'tsubakuro_delete_task',
				nonce: tsubakuroAdmin.nonce,
				task_id: taskId,
			},
			function ( response ) {
				if ( response.success ) {
					window.location.reload();
				} else {
					// eslint-disable-next-line no-alert
					window.alert(
						response.data.message || '削除に失敗しました。'
					);
				}
			}
		).fail( function () {
			// eslint-disable-next-line no-alert
			window.alert( '通信エラーが発生しました。' );
		} );
	}

	// =========================================================================
	// Related pages search (task form page)
	// =========================================================================
	function getRelatedIds() {
		const val = $( '#tsubakuro-task-related' ).val();
		if ( ! val.trim() ) {
			return [];
		}
		return val
			.split( ',' )
			.map( function ( s ) {
				return parseInt( s.trim(), 10 );
			} )
			.filter( Boolean );
	}

	function updateRelatedInput( ids ) {
		$( '#tsubakuro-task-related' ).val( ids.join( ', ' ) );
	}

	function addRelatedPage( id, title, url ) {
		const ids = getRelatedIds();
		if ( ids.indexOf( id ) !== -1 ) {
			return;
		}
		ids.push( id );
		updateRelatedInput( ids );

		const $tag = $( '<span class="tsubakuro-related-tag">' ).attr(
			'data-id',
			id
		);
		let $content;
		if ( url ) {
			$content = $( '<a>' )
				.attr( { href: url, target: '_blank', rel: 'noopener' } )
				.text( title );
		} else {
			$content = $( '<span>' ).text( title );
		}
		const $btn = $(
			'<button type="button" class="tsubakuro-related-remove">'
		)
			.html( '&#x2715;' )
			.attr( 'data-id', id )
			.attr( 'aria-label', '削除' );
		$tag.append( $content ).append( $btn );
		$( '#tsubakuro-related-tags' ).append( $tag );
	}

	function removeRelatedPage( id ) {
		const ids = getRelatedIds().filter( function ( i ) {
			return i !== id;
		} );
		updateRelatedInput( ids );
		$(
			'#tsubakuro-related-tags .tsubakuro-related-tag[data-id="' +
				id +
				'"]'
		).remove();
	}

	let searchTimeout = null;
	function searchRelatedPosts( keyword ) {
		clearTimeout( searchTimeout );
		const $results = $( '#tsubakuro-related-results' );

		if ( ! keyword.trim() ) {
			$results.prop( 'hidden', true ).empty();
			return;
		}

		searchTimeout = setTimeout( function () {
			$.get(
				tsubakuroAdmin.ajaxUrl,
				{
					action: 'tsubakuro_search_posts',
					nonce: tsubakuroAdmin.nonce,
					keyword,
				},
				function ( response ) {
					$results.empty();
					if ( response.success && response.data.length ) {
						response.data.forEach( function ( post ) {
							const $item = $(
								'<div class="tsubakuro-related-result-item">'
							)
								.text( post.title )
								.attr( 'data-id', post.id )
								.attr( 'data-title', post.title )
								.attr( 'data-url', post.url );
							$results.append( $item );
						} );
						$results.prop( 'hidden', false );
					} else {
						$results.prop( 'hidden', true );
					}
				}
			).fail( function () {
				$results.prop( 'hidden', true );
			} );
		}, 300 );
	}

	// =========================================================================
	// Comments (task form page)
	// =========================================================================
	function renderComment( $container, c ) {
		const $item = $( '<div class="tsubakuro-comment-item">' );
		const $meta = $( '<div class="tsubakuro-comment-meta">' ).html(
			'<strong>' +
				escapeHtml( c.user_name ) +
				'</strong> &mdash; ' +
				escapeHtml( c.created_at )
		);
		const $body = $( '<div class="tsubakuro-comment-body">' ).text(
			c.comment
		);
		$item.append( $meta ).append( $body );
		$container.append( $item );
	}

	function addComment() {
		const $newComment = $( '#tsubakuro-new-comment' );
		const comment = $.trim( $newComment.val() );

		if ( ! comment ) {
			return;
		}

		const $commList = $( '#tsubakuro-comment-list' );
		const taskId = $( '#tsubakuro-task-id' ).val();

		$.post(
			tsubakuroAdmin.ajaxUrl,
			{
				action: 'tsubakuro_add_comment',
				nonce: tsubakuroAdmin.nonce,
				task_id: taskId,
				comment,
			},
			function ( response ) {
				if ( response.success ) {
					$newComment.val( '' );
					// Remove the "no comments" placeholder if present.
					$commList.find( '.tsubakuro-no-comments' ).remove();
					renderComment( $commList, response.data );
				} else {
					// eslint-disable-next-line no-alert
					window.alert(
						response.data.message ||
							'コメントの追加に失敗しました。'
					);
				}
			}
		).fail( function () {
			// eslint-disable-next-line no-alert
			window.alert( '通信エラーが発生しました。' );
		} );
	}

	// =========================================================================
	// Utility
	// =========================================================================
	function escapeHtml( str ) {
		return $( '<div>' )
			.text( str || '' )
			.html();
	}

	// =========================================================================
	// Column visibility (task list page)
	// =========================================================================
	const OPTIONAL_COLS = [ 'assignee', 'date' ];
	const COL_STORAGE_KEY = 'tsubakuro_visible_cols';

	function getVisibleCols() {
		try {
			const stored = window.localStorage.getItem( COL_STORAGE_KEY );
			if ( stored ) {
				return JSON.parse( stored );
			}
		} catch {
			// localStorage unavailable; fall through to default.
		}
		return [];
	}

	function saveVisibleCols( cols ) {
		try {
			window.localStorage.setItem(
				COL_STORAGE_KEY,
				JSON.stringify( cols )
			);
		} catch {
			// Ignore write failures.
		}
	}

	function applyColumnVisibility() {
		const visibleCols = getVisibleCols();
		OPTIONAL_COLS.forEach( function ( col ) {
			const isVisible = visibleCols.indexOf( col ) !== -1;
			$(
				'.tsubakuro-col-toggle[data-column="' +
					$.escapeSelector( col ) +
					'"]'
			).prop( 'checked', isVisible );
			$( '.tsubakuro-col--' + $.escapeSelector( col ) ).toggleClass(
				'tsubakuro-col-visible',
				isVisible
			);
		} );
	}

	// =========================================================================
	// =========================================================================
	$( document ).ready( function () {
		// Apply column visibility from stored preferences.
		applyColumnVisibility();

		// Toggle display options panel.
		$( document ).on(
			'click',
			'#tsubakuro-screen-options-toggle',
			function () {
				const $panel = $( '#tsubakuro-screen-options-panel' );
				const isExpanded = $panel.prop( 'hidden' ) === false;
				$panel.prop( 'hidden', isExpanded );
				$( this ).attr(
					'aria-expanded',
					isExpanded ? 'false' : 'true'
				);
				$( this )
					.find( '.dashicons' )
					.toggleClass( 'dashicons-arrow-down', isExpanded )
					.toggleClass( 'dashicons-arrow-up', ! isExpanded );
			}
		);

		// Column visibility checkbox change.
		$( document ).on( 'change', '.tsubakuro-col-toggle', function () {
			const col = $( this ).data( 'column' );

			// Validate column name against the allowed list.
			if ( OPTIONAL_COLS.indexOf( col ) === -1 ) {
				return;
			}

			const isChecked = $( this ).prop( 'checked' );
			let visibleCols = getVisibleCols();

			if ( isChecked ) {
				if ( visibleCols.indexOf( col ) === -1 ) {
					visibleCols.push( col );
				}
			} else {
				visibleCols = visibleCols.filter( function ( c ) {
					return c !== col;
				} );
			}

			$( '.tsubakuro-col--' + $.escapeSelector( col ) ).toggleClass(
				'tsubakuro-col-visible',
				isChecked
			);
			saveVisibleCols( visibleCols );
		} );

		// List table: select all checkboxes.
		$( document ).on( 'change', '.tsubakuro-select-all', function () {
			$( '.tsubakuro-task-table tbody input[name="task_ids[]"]' ).prop(
				'checked',
				$( this ).prop( 'checked' )
			);
		} );

		// List table: mirror the bottom bulk action into the submitted field.
		$( document ).on( 'click', '.tsubakuro-bulk-apply-bottom', function () {
			$( '#tsubakuro-bulk-action-selector-top' ).val(
				$( '#tsubakuro-bulk-action-selector-bottom' ).val()
			);
		} );

		// Delete task.
		$( document ).on( 'click', '.tsubakuro-delete-task', function () {
			deleteTask( $( this ).data( 'task-id' ) );
		} );

		// Add comment.
		$( document ).on( 'click', '#tsubakuro-add-comment-btn', addComment );

		// Allow Ctrl/Cmd+Enter to submit comment.
		$( document ).on( 'keydown', '#tsubakuro-new-comment', function ( e ) {
			if ( e.key === 'Enter' && ( e.ctrlKey || e.metaKey ) ) {
				addComment();
			}
		} );

		// Related pages: search as you type.
		$( document ).on(
			'input',
			'#tsubakuro-related-search-input',
			function () {
				searchRelatedPosts( $.trim( $( this ).val() ) );
			}
		);

		// Related pages: select a result.
		$( document ).on(
			'click',
			'.tsubakuro-related-result-item',
			function () {
				const id = parseInt( $( this ).data( 'id' ), 10 );
				const title = String( $( this ).data( 'title' ) );
				const url = String( $( this ).data( 'url' ) );
				addRelatedPage( id, title, url );
				$( '#tsubakuro-related-search-input' ).val( '' );
				$( '#tsubakuro-related-results' )
					.prop( 'hidden', true )
					.empty();
			}
		);

		// Related pages: remove a tag.
		$( document ).on( 'click', '.tsubakuro-related-remove', function () {
			removeRelatedPage( parseInt( $( this ).data( 'id' ), 10 ) );
		} );

		// Related pages: close dropdown when clicking outside.
		$( document ).on( 'click', function ( e ) {
			if (
				! $( e.target ).closest( '.tsubakuro-related-search' ).length
			) {
				$( '#tsubakuro-related-results' ).prop( 'hidden', true );
			}
		} );
	} );
} )( jQuery );
