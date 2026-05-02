/* global jQuery, tsubakuroAdmin */
(function ($) {
	'use strict';

	// =========================================================================
	// References
	// =========================================================================
	var $overlay     = $('#tsubakuro-modal-overlay');
	var $modal       = $('#tsubakuro-task-modal');
	var $modalTitle  = $('#tsubakuro-modal-title');
	var $taskId      = $('#tsubakuro-task-id');
	var $title       = $('#tsubakuro-task-title');
	var $content     = $('#tsubakuro-task-content');
	var $status      = $('#tsubakuro-task-status');
	var $assignee    = $('#tsubakuro-task-assignee');
	var $related     = $('#tsubakuro-task-related');
	var $commSec     = $('#tsubakuro-comments-section');
	var $commList    = $('#tsubakuro-comment-list');
	var $newComment  = $('#tsubakuro-new-comment');
	var $saveBtn     = $('#tsubakuro-save-task-btn');

	// =========================================================================
	// Open / close modal
	// =========================================================================
	function openModal(mode, task) {
		clearForm();
		if (mode === 'edit' && task) {
			$modalTitle.text('タスクを編集');
			$taskId.val(task.id);
			$title.val(task.title);
			$content.val(task.content);
			$status.val(task.status);
			$assignee.val(task.assignee ? task.assignee.id : '');
			$related.val((task.related_pages || []).join(', '));
			$commSec.show();
			loadComments(task.id);
		} else {
			$modalTitle.text('新規タスク追加');
			$commSec.hide();
		}
		$overlay.fadeIn(180);
		$title.focus();
	}

	function closeModal() {
		$overlay.fadeOut(150);
		clearForm();
	}

	function clearForm() {
		$taskId.val('');
		$title.val('');
		$content.val('');
		$status.val('todo');
		$assignee.val('');
		$related.val('');
		$commList.empty();
		$newComment.val('');
		clearNotice();
	}

	// =========================================================================
	// Notices
	// =========================================================================
	function showNotice(message, type) {
		clearNotice();
		var $notice = $('<div class="tsubakuro-notice tsubakuro-notice--' + type + '">').text(message);
		$('.tsubakuro-modal-body').prepend($notice);
	}

	function clearNotice() {
		$('.tsubakuro-notice').remove();
	}

	// =========================================================================
	// Save task (create or update)
	// =========================================================================
	function saveTask() {
		var id      = $taskId.val();
		var titleV  = $.trim($title.val());

		if (!titleV) {
			showNotice('タイトルは必須です。', 'error');
			$title.focus();
			return;
		}

		var data = {
			action:        id ? 'tsubakuro_update_task' : 'tsubakuro_create_task',
			nonce:         tsubakuroAdmin.nonce,
			title:         titleV,
			content:       $content.val(),
			status:        $status.val(),
			assignee:      $assignee.val(),
			related_pages: $related.val()
		};

		if (id) {
			data.task_id = id;
		}

		$saveBtn.prop('disabled', true).text('保存中...');

		$.post(tsubakuroAdmin.ajaxUrl, data, function (response) {
			if (response.success) {
				closeModal();
				window.location.reload();
			} else {
				showNotice(response.data.message || '保存に失敗しました。', 'error');
			}
		}).fail(function () {
			showNotice('通信エラーが発生しました。', 'error');
		}).always(function () {
			$saveBtn.prop('disabled', false).text('保存');
		});
	}

	// =========================================================================
	// Delete task
	// =========================================================================
	function deleteTask(taskId) {
		if (!window.confirm('このタスクを削除しますか？')) {
			return;
		}

		$.post(tsubakuroAdmin.ajaxUrl, {
			action:  'tsubakuro_delete_task',
			nonce:   tsubakuroAdmin.nonce,
			task_id: taskId
		}, function (response) {
			if (response.success) {
				window.location.reload();
			} else {
				alert(response.data.message || '削除に失敗しました。');
			}
		}).fail(function () {
			alert('通信エラーが発生しました。');
		});
	}

	// =========================================================================
	// Load task detail & open modal in edit mode
	// =========================================================================
	function openTaskDetail(taskId) {
		$.get(tsubakuroAdmin.ajaxUrl, {
			action:  'tsubakuro_get_task',
			nonce:   tsubakuroAdmin.nonce,
			task_id: taskId
		}, function (response) {
			if (response.success) {
				openModal('edit', response.data);
			} else {
				alert(response.data.message || 'タスクの取得に失敗しました。');
			}
		});
	}

	// =========================================================================
	// Comments
	// =========================================================================
	function loadComments(taskId) {
		$commList.html('<em>読み込み中...</em>');

		$.get(tsubakuroAdmin.ajaxUrl, {
			action:  'tsubakuro_get_comments',
			nonce:   tsubakuroAdmin.nonce,
			task_id: taskId
		}, function (response) {
			if (response.success) {
				renderComments($commList, response.data);
			}
		});
	}

	function renderComments($container, comments) {
		if (!comments || !comments.length) {
			$container.html('<p style="color:#888;font-size:13px;">コメントはありません。</p>');
			return;
		}

		$container.empty();
		comments.forEach(function (c) {
			var $item = $('<div class="tsubakuro-comment-item">');
			var $meta = $('<div class="tsubakuro-comment-meta">').html(
				'<strong>' + escapeHtml(c.user_name) + '</strong> &mdash; ' + escapeHtml(c.created_at)
			);
			var $body = $('<div class="tsubakuro-comment-body">').text(c.comment);
			$item.append($meta).append($body);
			$container.append($item);
		});
	}

	function addComment() {
		var taskId  = $taskId.val();
		var comment = $.trim($newComment.val());

		if (!comment) {
			return;
		}

		$.post(tsubakuroAdmin.ajaxUrl, {
			action:  'tsubakuro_add_comment',
			nonce:   tsubakuroAdmin.nonce,
			task_id: taskId,
			comment: comment
		}, function (response) {
			if (response.success) {
				$newComment.val('');
				loadComments(taskId);
			} else {
				alert(response.data.message || 'コメントの追加に失敗しました。');
			}
		});
	}

	// =========================================================================
	// Utility
	// =========================================================================
	function escapeHtml(str) {
		return $('<div>').text(str || '').html();
	}

	// =========================================================================
	// Event bindings
	// =========================================================================
	$(document).ready(function () {
		// Open new-task modal.
		$(document).on('click', '#tsubakuro-new-task-btn', function () {
			openModal('new');
		});

		// Open task detail modal.
		$(document).on('click', '.tsubakuro-task-detail-link', function (e) {
			e.preventDefault();
			openTaskDetail($(this).data('task-id'));
		});

		// Delete task.
		$(document).on('click', '.tsubakuro-delete-task', function () {
			deleteTask($(this).data('task-id'));
		});

		// Close modal (overlay click or close button).
		$(document).on('click', '.tsubakuro-modal-close', closeModal);
		$(document).on('click', '#tsubakuro-modal-overlay', function (e) {
			if ($(e.target).is('#tsubakuro-modal-overlay')) {
				closeModal();
			}
		});

		// Save task.
		$(document).on('click', '#tsubakuro-save-task-btn', saveTask);

		// Add comment.
		$(document).on('click', '#tsubakuro-add-comment-btn', addComment);

		// Allow Ctrl/Cmd+Enter to submit comment.
		$(document).on('keydown', '#tsubakuro-new-comment', function (e) {
			if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
				addComment();
			}
		});

		// ESC closes modal.
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $overlay.is(':visible')) {
				closeModal();
			}
		});
	});

}(jQuery));
