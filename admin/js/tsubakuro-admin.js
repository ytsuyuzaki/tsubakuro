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
	} );
} )( jQuery );
