<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_Reminders.
 */
class RemindersTest extends TestCase
{

    protected function setUp(): void
    {
        tsubakuro_test_reset();
    }

    private function make_task_post(int $id, string $title): object
    {
        return (object) array(
            'ID'            => $id,
            'post_type'     => 'tsubakuro_task',
            'post_title'    => $title,
            'post_content'  => 'Body',
            'post_date'     => '2026-05-01 10:00:00',
            'post_modified' => '2026-05-01 11:00:00',
            'post_author'   => 2,
        );
    }

    public function test_schedule_event_registers_quarter_hour_cron_once(): void
    {
        Tsubakuro_Reminders::schedule_event();
        Tsubakuro_Reminders::schedule_event();

        $this->assertCount(1, $GLOBALS['tsubakuro_test']['cron_events']);
        $this->assertSame('tsubakuro_quarter_hour', $GLOBALS['tsubakuro_test']['cron_events'][0]['recurrence']);
        $this->assertSame(Tsubakuro_Reminders::CRON_HOOK, $GLOBALS['tsubakuro_test']['cron_events'][0]['hook']);
    }

    public function test_process_due_reminders_sends_mail_to_assignee_first(): void
    {
        $GLOBALS['tsubakuro_test']['posts'][10] = $this->make_task_post(10, 'Reminder Task');
        $GLOBALS['tsubakuro_test']['users'][2]  = (object) array(
            'ID'           => 2,
            'display_name' => 'Author',
            'user_email'   => 'author@example.test',
        );
        $GLOBALS['tsubakuro_test']['users'][7]  = (object) array(
            'ID'           => 7,
            'display_name' => 'Assignee',
            'user_email'   => 'assignee@example.test',
        );
        $GLOBALS['tsubakuro_test']['post_meta'][10] = array(
            '_tsubakuro_status'          => array('todo'),
            '_tsubakuro_assignee'        => array(7),
            '_tsubakuro_start_remind_at' => array('2026-05-01 09:00:00'),
        );

        Tsubakuro_Reminders::process_due_reminders();

        $this->assertCount(1, $GLOBALS['tsubakuro_test']['sent_mails']);
        $this->assertSame('assignee@example.test', $GLOBALS['tsubakuro_test']['sent_mails'][0]['to']);
        $this->assertSame(
            array('2026-05-02 00:00:00'),
            $GLOBALS['tsubakuro_test']['post_meta'][10]['_tsubakuro_start_reminded_at']
        );
    }

    public function test_process_due_reminders_does_not_resend_when_already_sent(): void
    {
        $GLOBALS['tsubakuro_test']['posts'][10] = $this->make_task_post(10, 'Reminder Task');
        $GLOBALS['tsubakuro_test']['users'][2]  = (object) array(
            'ID'           => 2,
            'display_name' => 'Author',
            'user_email'   => 'author@example.test',
        );
        $GLOBALS['tsubakuro_test']['post_meta'][10] = array(
            '_tsubakuro_status'           => array('todo'),
            '_tsubakuro_start_remind_at'  => array('2026-05-01 09:00:00'),
            '_tsubakuro_start_reminded_at' => array('2026-05-01 09:05:00'),
        );

        Tsubakuro_Reminders::process_due_reminders();

        $this->assertCount(0, $GLOBALS['tsubakuro_test']['sent_mails']);
    }
}
