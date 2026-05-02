/* global jQuery, tsubakuroPublic */
( function ( $ ) {
	'use strict';

	const cfg = tsubakuroPublic;
	const REST = cfg.restUrl; // e.g. https://example.com/wp-json/tsubakuro/v1
	let currentTaskId = null;

	// =========================================================================
	// REST helper
	// =========================================================================
	function restRequest( method, path, body, onSuccess, onError ) {
		const opts = {
			url: REST + path,
			method,
			headers: { 'X-WP-Nonce': cfg.restNonce },
		};

		if ( body !== null ) {
			opts.data = JSON.stringify( body );
			opts.contentType = 'application/json';
		}

		$.ajax( opts )
			.done( onSuccess )
			.fail(
				onError ||
					function () {
						showPubNotice( '通信エラーが発生しました。', 'error' );
					}
			);
	}

	// =========================================================================
	// Panel toggle
	// =========================================================================
	$( document ).on( 'click', '#tsubakuro-fab', function () {
		const $panel = $( '#tsubakuro-panel' );
		if ( $panel.is( ':visible' ) ) {
			$panel.hide();
		} else {
			$panel.show();
			loadTaskList();
		}
	} );

	$( document ).on( 'click', '.tsubakuro-panel-close', function () {
		$( '#tsubakuro-panel' ).hide();
	} );

	// =========================================================================
	// Tabs
	// =========================================================================
	$( document ).on( 'click', '.tsubakuro-tab', function () {
		const tab = $( this ).data( 'tab' );
		$( '.tsubakuro-tab' ).removeClass( 'active' );
		$( '.tsubakuro-tab-content' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		$( '.tsubakuro-tab-content[data-tab="' + tab + '"]' ).addClass(
			'active'
		);

		if ( tab === 'list' ) {
			loadTaskList();
		} else if ( tab === 'new' ) {
			resetNewForm();
		}
	} );

	// =========================================================================
	// Load task list
	// =========================================================================
	function loadTaskList() {
		const $list = $( '#tsubakuro-pub-task-list' );
		$list.html( '<p class="tsubakuro-loading">読み込み中...</p>' );

		const params = {};
		const statusFilter = $( '#tsubakuro-pub-status-filter' ).val();
		const pageFilter = $( '#tsubakuro-pub-page-filter' ).is( ':checked' );

		if ( statusFilter ) {
			params.status = statusFilter;
		}

		if ( pageFilter && cfg.currentPage ) {
			params.related_page = cfg.currentPage;
		}

		const qs = $.param( params );
		restRequest(
			'GET',
			'/tasks' + ( qs ? '?' + qs : '' ),
			null,
			function ( tasks ) {
				renderTaskList( $list, tasks );
			},
			function () {
				$list.html(
					'<p class="tsubakuro-empty">タスクを読み込めませんでした。</p>'
				);
			}
		);
	}

	function renderTaskList( $list, tasks ) {
		if ( ! tasks || ! tasks.length ) {
			$list.html( '<p class="tsubakuro-empty">タスクがありません。</p>' );
			return;
		}

		$list.empty();

		tasks.forEach( function ( task ) {
			const assigneeName = task.assignee
				? task.assignee.display_name
				: '未アサイン';
			const $item = $(
				'<div class="tsubakuro-pub-task-item" data-task-id="' +
					escapeHtml( String( task.id ) ) +
					'">' +
					'<div class="tsubakuro-pub-task-title">' +
					escapeHtml( task.title ) +
					'</div>' +
					'<div class="tsubakuro-pub-task-meta">' +
					'<span class="tsubakuro-badge tsubakuro-badge--' +
					escapeHtml( task.status ) +
					'">' +
					escapeHtml( task.status_label ) +
					'</span>' +
					'<span>' +
					escapeHtml( assigneeName ) +
					'</span>' +
					'</div>' +
					'</div>'
			);
			$list.append( $item );
		} );
	}

	// =========================================================================
	// Task detail
	// =========================================================================
	$( document ).on( 'click', '.tsubakuro-pub-task-item', function () {
		openDetail( $( this ).data( 'task-id' ) );
	} );

	function openDetail( taskId ) {
		currentTaskId = taskId;

		$( '.tsubakuro-tab-content[data-tab="list"]' ).hide();
		$( '#tsubakuro-pub-detail' ).show();

		restRequest(
			'GET',
			'/tasks/' + taskId,
			null,
			function ( task ) {
				renderDetail( task );
			},
			function () {
				$( '#tsubakuro-pub-detail-title' ).text( '読み込みエラー' );
			}
		);
	}

	function renderDetail( task ) {
		$( '#tsubakuro-pub-detail-title' ).text( task.title );
		$( '#tsubakuro-pub-detail-content' ).text( task.content || '' );
		$( '#tsubakuro-pub-detail-status' ).val( task.status );
		renderPublicComments( task.comments || [] );
	}

	function renderPublicComments( comments ) {
		const $list = $( '#tsubakuro-pub-comment-list' );
		$list.empty();

		if ( ! comments.length ) {
			$list.html(
				'<p style="color:#9ca3af;font-size:12px;padding:4px 0;">コメントはありません。</p>'
			);
			return;
		}

		comments.forEach( function ( c ) {
			$list.append(
				'<div class="tsubakuro-pub-comment-item">' +
					'<div class="tsubakuro-pub-comment-meta">' +
					escapeHtml( c.user_name ) +
					' \u2013 ' +
					escapeHtml( c.created_at ) +
					'</div>' +
					'<div>' +
					escapeHtml( c.comment ) +
					'</div>' +
					'</div>'
			);
		} );
	}

	// Back button.
	$( document ).on( 'click', '#tsubakuro-pub-detail-back', function () {
		currentTaskId = null;
		$( '#tsubakuro-pub-detail' ).hide();
		$( '.tsubakuro-tab-content[data-tab="list"]' ).show();
	} );

	// Update status.
	$( document ).on( 'click', '#tsubakuro-pub-update-status', function () {
		if ( ! currentTaskId ) {
			return;
		}

		const newStatus = $( '#tsubakuro-pub-detail-status' ).val();

		restRequest(
			'POST',
			'/tasks/' + currentTaskId,
			{ status: newStatus },
			function () {
				showPubNotice( 'ステータスを更新しました。', 'success' );
			},
			function () {
				showPubNotice( '更新に失敗しました。', 'error' );
			}
		);
	} );

	// Add comment.
	$( document ).on( 'click', '#tsubakuro-pub-comment-submit', function () {
		if ( ! currentTaskId ) {
			return;
		}

		const comment = $.trim( $( '#tsubakuro-pub-comment-input' ).val() );
		if ( ! comment ) {
			return;
		}

		restRequest(
			'POST',
			'/tasks/' + currentTaskId + '/comments',
			{ comment },
			function () {
				$( '#tsubakuro-pub-comment-input' ).val( '' );
				openDetail( currentTaskId );
			}
		);
	} );

	// =========================================================================
	// New task form (public)
	// =========================================================================
	function resetNewForm() {
		$( '#tsubakuro-pub-editing-id' ).val( '' );
		$( '#tsubakuro-pub-title' ).val( '' );
		$( '#tsubakuro-pub-content' ).val( '' );
		$( '#tsubakuro-pub-status' ).val( 'todo' );
		$( '#tsubakuro-pub-assignee' ).val( '' );
		$( '#tsubakuro-pub-link-page' ).prop( 'checked', true );
		$( '#tsubakuro-pub-cancel-edit' ).hide();
	}

	$( document ).on( 'click', '#tsubakuro-pub-save', function () {
		const titleV = $.trim( $( '#tsubakuro-pub-title' ).val() );

		if ( ! titleV ) {
			showPubNotice( 'タイトルは必須です。', 'error' );
			return;
		}

		const editingId = $( '#tsubakuro-pub-editing-id' ).val();

		let relatedPages = [];
		if (
			$( '#tsubakuro-pub-link-page' ).is( ':checked' ) &&
			cfg.currentPage
		) {
			relatedPages = [ parseInt( cfg.currentPage, 10 ) ];
		}

		const path = editingId ? '/tasks/' + editingId : '/tasks';

		const body = {
			title: titleV,
			content: $( '#tsubakuro-pub-content' ).val(),
			status: $( '#tsubakuro-pub-status' ).val(),
			assignee: parseInt( $( '#tsubakuro-pub-assignee' ).val(), 10 ) || 0,
			related_pages: relatedPages,
		};

		restRequest(
			'POST',
			path,
			body,
			function () {
				showPubNotice( 'タスクを保存しました。', 'success' );
				resetNewForm();
				$( '.tsubakuro-tab[data-tab="list"]' ).trigger( 'click' );
			},
			function () {
				showPubNotice( '保存に失敗しました。', 'error' );
			}
		);
	} );

	$( document ).on( 'click', '#tsubakuro-pub-cancel-edit', function () {
		resetNewForm();
	} );

	// =========================================================================
	// Filters
	// =========================================================================
	$( document ).on(
		'change',
		'#tsubakuro-pub-status-filter, #tsubakuro-pub-page-filter',
		function () {
			loadTaskList();
		}
	);

	// =========================================================================
	// Utilities
	// =========================================================================
	function showPubNotice( message, type ) {
		$( '.tsubakuro-pub-notice' ).remove();
		const $notice = $(
			'<div class="tsubakuro-pub-notice tsubakuro-pub-notice--' +
				type +
				'">'
		).text( message );
		$( '#tsubakuro-panel .tsubakuro-tabs' ).after( $notice );
		setTimeout( function () {
			$notice.fadeOut( 400, function () {
				$( this ).remove();
			} );
		}, 3000 );
	}

	function escapeHtml( str ) {
		return $( '<div>' )
			.text( str || '' )
			.html();
	}
} )( jQuery );
