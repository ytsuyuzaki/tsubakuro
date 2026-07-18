<?php
/**
 * Reminder scheduler and mail notifications.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles reminder cron registration and delivery.
 */
class Tsubakuro_Reminders {


	const CRON_HOOK = 'tsubakuro_check_reminders';

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_due_reminders' ) );
		self::schedule_event();
	}

	/**
	 * Register a 15 minute cron interval.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public static function register_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['tsubakuro_quarter_hour'] ) ) {
			$schedules['tsubakuro_quarter_hour'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => 'Every 15 Minutes (Tsubakuro)',
			);
		}

		return $schedules;
	}

	/**
	 * Schedule reminder cron if not already scheduled.
	 */
	public static function schedule_event() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'tsubakuro_quarter_hour', self::CRON_HOOK );
	}

	/**
	 * Unschedule reminder cron.
	 */
	public static function unschedule_event() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Process all due reminders and send mails when required.
	 */
	public static function process_due_reminders() {
		$tasks = Tsubakuro_Post_Types::get_tasks(
			array(
				'status'         => 'all',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- cron batch processing needs broader scan window.
			)
		);

		$now = strtotime( current_time( 'mysql' ) );
		if ( false === $now ) {
			$now = time();
		}

		foreach ( $tasks as $task ) {
			if ( 'completed' === ( $task['status'] ?? '' ) ) {
				continue;
			}

			$task_id = (int) $task['id'];

			self::maybe_send_single_reminder(
				$task,
				'_tsubakuro_start_remind_at',
				'_tsubakuro_start_reminded_at',
				'開始時間',
				$now,
				$task_id
			);

			self::maybe_send_single_reminder(
				$task,
				'_tsubakuro_due_remind_at',
				'_tsubakuro_due_reminded_at',
				'完了期限',
				$now,
				$task_id
			);
		}

		self::process_evaluation_reminders( $now );
	}

	/**
	 * Notify about evaluations whose evaluation-due date has passed while no
	 * verdict has been recorded yet.
	 *
	 * @param int $now Current unix timestamp.
	 */
	public static function process_evaluation_reminders( $now ) {
		if ( ! class_exists( 'Tsubakuro_Evaluations' ) ) {
			return;
		}

		$evaluations = Tsubakuro_Evaluations::get_evaluations(
			array(
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- cron batch processing needs broader scan window.
			)
		);

		foreach ( $evaluations as $evaluation ) {
			self::maybe_send_evaluation_reminder( $evaluation, $now );
		}
	}

	/**
	 * Send an overdue-evaluation reminder for one evaluation when required.
	 *
	 * Fires once the evaluation-due date has passed AND no verdict is recorded
	 * AND no reminder has been sent yet. The sent flag is cleared by
	 * Tsubakuro_Evaluations::save_meta() whenever the verdict changes, so an
	 * evaluation re-enters the reminder pool if its verdict is later removed.
	 *
	 * @param array $evaluation Formatted evaluation data.
	 * @param int   $now        Current unix timestamp.
	 */
	private static function maybe_send_evaluation_reminder( $evaluation, $now ) {
		if ( ! empty( $evaluation['is_evaluated'] ) ) {
			return;
		}

		$due_at = (string) ( $evaluation['due_at'] ?? '' );
		if ( '' === $due_at ) {
			return;
		}

		$eval_id = (int) $evaluation['id'];
		$sent_at = get_post_meta( $eval_id, '_tsubakuro_eval_reminded_at', true );
		if ( ! empty( $sent_at ) ) {
			return;
		}

		$scheduled = strtotime( $due_at . ' 23:59:59' );
		if ( false === $scheduled || $scheduled > $now ) {
			return;
		}

		if ( self::send_evaluation_reminder_mail( $evaluation, $due_at ) ) {
			update_post_meta( $eval_id, '_tsubakuro_eval_reminded_at', current_time( 'mysql' ) );
		}
	}

	/**
	 * Send the overdue-evaluation reminder mail for one evaluation.
	 *
	 * @param array  $evaluation Formatted evaluation data.
	 * @param string $due_at     Evaluation due date.
	 * @return bool
	 */
	private static function send_evaluation_reminder_mail( $evaluation, $due_at ) {
		$recipient = self::resolve_author_recipient( (int) ( $evaluation['author_id'] ?? 0 ) );
		if ( ! $recipient ) {
			return false;
		}

		$eval_id   = (int) ( $evaluation['id'] ?? 0 );
		$eval_link = admin_url( 'admin.php?page=tsubakuro-evaluation-form&evaluation_id=' . $eval_id );
		$subject   = sprintf( '[Tsubakuro] 評価予定日リマインド: %s', (string) ( $evaluation['title'] ?? '' ) );
		$body      = implode(
			"\n",
			array(
				sprintf( '記事評価 #%d の評価予定日を過ぎましたが、判定が未登録です。', $eval_id ),
				sprintf( 'タイトル: %s', (string) ( $evaluation['title'] ?? '' ) ),
				sprintf( '評価予定日: %s', $due_at ),
				sprintf( '編集URL: %s', $eval_link ),
			),
		);

		return (bool) wp_mail( $recipient, $subject, $body );
	}

	/**
	 * Resolve a recipient email from a user ID.
	 *
	 * @param int $author_id Author user ID.
	 * @return string
	 */
	private static function resolve_author_recipient( $author_id ) {
		$user = $author_id ? get_user_by( 'id', $author_id ) : false;

		if ( ! $user || empty( $user->user_email ) ) {
			return '';
		}

		return (string) $user->user_email;
	}

	/**
	 * Check and send one reminder type for a task.
	 *
	 * @param array  $task              Task data.
	 * @param string $at_meta_key       Reminder datetime meta key.
	 * @param string $sent_meta_key     Reminder sent-at meta key.
	 * @param string $label             Reminder label.
	 * @param int    $now               Current unix timestamp.
	 * @param int    $task_id           Task ID.
	 */
	private static function maybe_send_single_reminder( $task, $at_meta_key, $sent_meta_key, $label, $now, $task_id ) {
		$remind_at = get_post_meta( $task_id, $at_meta_key, true );
		$sent_at   = get_post_meta( $task_id, $sent_meta_key, true );

		if ( ! is_string( $remind_at ) || '' === $remind_at || ! empty( $sent_at ) ) {
			return;
		}

		$scheduled = strtotime( $remind_at );
		if ( false === $scheduled || $scheduled > $now ) {
			return;
		}

		if ( self::send_task_reminder_mail( $task, $label, $remind_at ) ) {
			update_post_meta( $task_id, $sent_meta_key, current_time( 'mysql' ) );
		}
	}

	/**
	 * Send reminder mail for one task.
	 *
	 * @param array  $task      Task data.
	 * @param string $kind      Reminder kind label.
	 * @param string $remind_at Scheduled datetime.
	 * @return bool
	 */
	private static function send_task_reminder_mail( $task, $kind, $remind_at ) {
		$recipient = self::resolve_task_recipient( $task );
		if ( ! $recipient ) {
			return false;
		}

		$task_id   = (int) ( $task['id'] ?? 0 );
		$task_link = admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $task_id );
		$subject   = sprintf( '[Tsubakuro] %sリマインド: %s', $kind, (string) ( $task['title'] ?? '' ) );
		$body      = implode(
			"\n",
			array(
				sprintf( 'タスク #%d の%sリマインドです。', $task_id, $kind ),
				sprintf( 'タイトル: %s', (string) ( $task['title'] ?? '' ) ),
				sprintf( 'ステータス: %s', (string) ( $task['status_label'] ?? ( $task['status'] ?? '' ) ) ),
				sprintf( '設定時刻: %s', $remind_at ),
				sprintf( '編集URL: %s', $task_link ),
			),
		);

		return (bool) wp_mail( $recipient, $subject, $body );
	}

	/**
	 * Resolve recipient email: assignee first, then author.
	 *
	 * @param array $task Task data.
	 * @return string
	 */
	private static function resolve_task_recipient( $task ) {
		$assignee_id = (int) get_post_meta( (int) $task['id'], '_tsubakuro_assignee', true );
		$user        = $assignee_id ? get_user_by( 'id', $assignee_id ) : false;

		if ( ! $user && ! empty( $task['author_id'] ) ) {
			$user = get_user_by( 'id', (int) $task['author_id'] );
		}

		if ( ! $user || empty( $user->user_email ) ) {
			return '';
		}

		return (string) $user->user_email;
	}
}
