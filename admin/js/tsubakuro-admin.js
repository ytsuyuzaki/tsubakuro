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
	// Event bindings
	// =========================================================================
	$( document ).ready( function () {
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
