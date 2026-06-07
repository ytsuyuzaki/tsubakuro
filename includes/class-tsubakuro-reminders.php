<?php

/**
 * Reminder scheduler and mail notifications.
 *
 * @package Tsubakuro
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles reminder cron registration and delivery.
 */
class Tsubakuro_Reminders
{

    const CRON_HOOK = 'tsubakuro_check_reminders';

    /**
     * Register WordPress hooks.
     */
    public static function init()
    {
        add_filter('cron_schedules', array(__CLASS__, 'register_cron_schedules'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'process_due_reminders'));
        self::schedule_event();
    }

    /**
     * Register a 15 minute cron interval.
     *
     * @param array $schedules Existing cron schedules.
     * @return array
     */
    public static function register_cron_schedules($schedules)
    {
        if (! isset($schedules['tsubakuro_quarter_hour'])) {
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
    public static function schedule_event()
    {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'tsubakuro_quarter_hour', self::CRON_HOOK);
    }

    /**
     * Unschedule reminder cron.
     */
    public static function unschedule_event()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Process all due reminders and send mails when required.
     */
    public static function process_due_reminders()
    {
        $tasks = Tsubakuro_Post_Types::get_tasks(
            array(
                'status'         => 'all',
                'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- cron batch processing needs broader scan window.
            )
        );

        $now = strtotime(current_time('mysql'));
        if (false === $now) {
            $now = time();
        }

        foreach ($tasks as $task) {
            if ('completed' === ($task['status'] ?? '')) {
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
    private static function maybe_send_single_reminder($task, $at_meta_key, $sent_meta_key, $label, $now, $task_id)
    {
        $remind_at = get_post_meta($task_id, $at_meta_key, true);
        $sent_at   = get_post_meta($task_id, $sent_meta_key, true);

        if (! is_string($remind_at) || '' === $remind_at || ! empty($sent_at)) {
            return;
        }

        $scheduled = strtotime($remind_at);
        if (false === $scheduled || $scheduled > $now) {
            return;
        }

        if (self::send_task_reminder_mail($task, $label, $remind_at)) {
            update_post_meta($task_id, $sent_meta_key, current_time('mysql'));
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
    private static function send_task_reminder_mail($task, $kind, $remind_at)
    {
        $recipient = self::resolve_task_recipient($task);
        if (! $recipient) {
            return false;
        }

        $task_id   = (int) ($task['id'] ?? 0);
        $task_link = admin_url('admin.php?page=tsubakuro-task-form&task_id=' . $task_id);
        $subject   = sprintf('[Tsubakuro] %sリマインド: %s', $kind, (string) ($task['title'] ?? ''));
        $body      = implode(
            "\n",
            array(
                sprintf('タスク #%d の%sリマインドです。', $task_id, $kind),
                sprintf('タイトル: %s', (string) ($task['title'] ?? '')),
                sprintf('ステータス: %s', (string) ($task['status_label'] ?? ($task['status'] ?? ''))),
                sprintf('設定時刻: %s', $remind_at),
                sprintf('編集URL: %s', $task_link),
            ),
        );

        return (bool) wp_mail($recipient, $subject, $body);
    }

    /**
     * Resolve recipient email: assignee first, then author.
     *
     * @param array $task Task data.
     * @return string
     */
    private static function resolve_task_recipient($task)
    {
        $assignee_id = (int) get_post_meta((int) $task['id'], '_tsubakuro_assignee', true);
        $user        = $assignee_id ? get_user_by('id', $assignee_id) : false;

        if (! $user && ! empty($task['author_id'])) {
            $user = get_user_by('id', (int) $task['author_id']);
        }

        if (! $user || empty($user->user_email)) {
            return '';
        }

        return (string) $user->user_email;
    }
}
