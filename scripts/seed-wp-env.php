<?php
/**
 * Seed display-check data into the local wp-env site.
 *
 * Run with: npm run seed
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Insert or update one seed post identified by its stable slug.
 *
 * @param string $post_type    WordPress post type.
 * @param string $slug         Stable seed slug.
 * @param string $title        Post title.
 * @param string $content      Post content.
 * @param string $post_status  WordPress post status.
 * @return int
 */
function tsubakuro_seed_post( $post_type, $slug, $title, $content = '', $post_status = 'publish' ) {
	$existing_posts = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'name'           => $slug,
			'posts_per_page' => 1,
		)
	);
	$existing       = $existing_posts ? $existing_posts[0] : null;
	$post           = array(
		'post_type'    => $post_type,
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
		'post_status'  => $post_status,
	);

	if ( $existing ) {
		$post['ID'] = $existing->ID;
	}

	$post_id = wp_insert_post( $post, true );
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::error( $post_id->get_error_message() );
	}

	return (int) $post_id;
}

$target_post_id = tsubakuro_seed_post(
	'post',
	'tsubakuro-seed-target-article',
	'【表示確認】検索流入を増やすための記事改善例',
	'記事評価の対象記事として使用する wp-env 用サンプル記事です。'
);

$admin_user = get_user_by( 'login', 'admin' );
if ( $admin_user ) {
	wp_set_current_user( $admin_user->ID );
}

Tsubakuro_Site_Strategy::save_strategy(
	array(
		'purpose'   => '検索から訪れた読者の疑問を解決し、次の行動を選ぶための確かな情報を提供する。',
		'position'  => '初心者にも理解しやすく、実務者が判断根拠として参照できるメディア。',
		'direction' => '既存記事の検索意図を見直し、記事評価の結果を基に月次で改善する。',
		'audience'  => '専門知識はないが、比較・検討のために信頼できる情報を求めている読者。',
		'value'     => '根拠が明確な比較、具体的な手順、判断時の注意点をひとつの記事で確認できること。',
	)
);

$task_rows = array(
	array(
		'slug'     => 'tsubakuro-seed-task-todo',
		'title'    => '【ToDo】検索クエリと記事内容のずれを調査する',
		'content'  => 'Search Console のクエリを確認し、追記候補を整理します。',
		'status'   => 'todo',
		'priority' => 'high',
	),
	array(
		'slug'     => 'tsubakuro-seed-task-in-progress',
		'title'    => '【実行中】比較表の情報を最新化する',
		'content'  => '公式サイトと照合して、価格と機能を更新します。',
		'status'   => 'in_progress',
		'priority' => 'medium',
	),
	array(
		'slug'     => 'tsubakuro-seed-task-completed',
		'title'    => '【実行完了】FAQを追加する',
		'content'  => '読者から多い質問を5件追加しました。',
		'status'   => 'completed',
		'priority' => 'low',
	),
);

$task_ids = array();
foreach ( $task_rows as $row ) {
	$task_id = tsubakuro_seed_post(
		Tsubakuro_Post_Types::TASK_POST_TYPE,
		$row['slug'],
		$row['title'],
		$row['content']
	);
	Tsubakuro_Post_Types::save_meta(
		$task_id,
		array(
			'status'          => $row['status'],
			'priority'        => $row['priority'],
			'assignee'        => $admin_user ? $admin_user->ID : 0,
			'related_pages'   => array( $target_post_id ),
			'start_remind_at' => '2026-07-20 09:00:00',
			'due_remind_at'   => '2026-07-31 18:00:00',
		)
	);
	$task_ids[] = $task_id;
}

$child_task_id = tsubakuro_seed_post(
	Tsubakuro_Post_Types::TASK_POST_TYPE,
	'tsubakuro-seed-task-child',
	'【サブタスク】比較元のURLを記録する',
	'確認した公式ページをコメントに残します。'
);
wp_update_post(
	array(
		'ID'          => $child_task_id,
		'post_parent' => $task_ids[1],
	)
);
Tsubakuro_Post_Types::save_meta(
	$child_task_id,
	array(
		'status'        => 'todo',
		'priority'      => 'medium',
		'assignee'      => $admin_user ? $admin_user->ID : 0,
		'related_pages' => array( $target_post_id ),
	)
);
$task_ids[] = $child_task_id;

$evaluation_rows = array(
	array(
		'slug'        => 'tsubakuro-seed-evaluation-unevaluated',
		'title'       => '【未評価】タイトルと導入文を改善',
		'detail'      => '検索意図に合わせてタイトルと導入文を書き換えました。',
		'change_item' => 'title',
		'metric'      => 'ctr',
		'before'      => '2.1%',
		'after'       => '',
		'judgment'    => '',
		'implemented' => '2026-07-10',
		'due'         => '2026-07-24',
	),
	array(
		'slug'        => 'tsubakuro-seed-evaluation-success',
		'title'       => '【成功】FAQを追加して検索ニーズを補完',
		'detail'      => '問い合わせ内容を基にFAQを5件追加しました。',
		'change_item' => 'faq',
		'metric'      => 'clicks',
		'before'      => '120',
		'after'       => '184',
		'judgment'    => 'success',
		'implemented' => '2026-06-01',
		'due'         => '2026-06-30',
	),
	array(
		'slug'        => 'tsubakuro-seed-evaluation-partial',
		'title'       => '【一部成功】関連記事への内部リンクを追加',
		'detail'      => '本文中に関連記事への内部リンクを追加しました。',
		'change_item' => 'internal_link',
		'metric'      => 'internal_click',
		'before'      => '36',
		'after'       => '48',
		'judgment'    => 'partial',
		'implemented' => '2026-06-05',
		'due'         => '2026-07-05',
	),
	array(
		'slug'        => 'tsubakuro-seed-evaluation-no-change',
		'title'       => '【変化なし】比較表を追加',
		'detail'      => '主要サービスを比較する表を追加しました。',
		'change_item' => 'comparison',
		'metric'      => 'conversion',
		'before'      => '3件',
		'after'       => '3件',
		'judgment'    => 'no_change',
		'implemented' => '2026-05-15',
		'due'         => '2026-06-15',
	),
	array(
		'slug'        => 'tsubakuro-seed-evaluation-failure',
		'title'       => '【失敗】非常に長いタイトルの表示と横スクロールを確認するための画像差し替え評価サンプル',
		'detail'      => 'ファーストビューの画像を差し替えました。',
		'change_item' => 'image',
		'metric'      => 'pv',
		'before'      => '2,400',
		'after'       => '2,050',
		'judgment'    => 'failure',
		'implemented' => '2026-05-01',
		'due'         => '2026-06-01',
	),
	array(
		'slug'        => 'tsubakuro-seed-evaluation-pending',
		'title'       => '【判定保留】構造化データを追加',
		'detail'      => 'FAQの構造化データを追加し、再クロールを待っています。',
		'change_item' => 'structured',
		'metric'      => 'index_status',
		'before'      => '未検出',
		'after'       => '確認中',
		'judgment'    => 'pending',
		'implemented' => '2026-07-15',
		'due'         => '2026-07-29',
	),
);

$evaluation_ids = array();
foreach ( $evaluation_rows as $row ) {
	$evaluation_id = tsubakuro_seed_post(
		Tsubakuro_Evaluations::POST_TYPE,
		$row['slug'],
		$row['title'],
		$row['detail']
	);

	Tsubakuro_Evaluations::save_meta(
		$evaluation_id,
		array(
			'target_post'    => $target_post_id,
			'change_item'    => $row['change_item'],
			'purpose'        => '記事改善の効果を継続的に確認するため。',
			'implemented_at' => $row['implemented'],
			'due_at'         => $row['due'],
			'metric'         => $row['metric'],
			'before_value'   => $row['before'],
			'after_value'    => $row['after'],
			'judgment'       => $row['judgment'],
			'result'         => '一覧表示確認用のサンプル結果です。',
			'note'           => 'npm run seed で作成されたデータです。',
		)
	);
	$evaluation_ids[] = $evaluation_id;
}

$insight_id = tsubakuro_seed_post(
	Tsubakuro_Insights::POST_TYPE,
	'tsubakuro-seed-insight',
	'【表示確認】検索意図に沿った追記はクリック増加につながる',
	'複数の記事評価を関連付けた改善知見のサンプルです。'
);
Tsubakuro_Insights::save_meta(
	$insight_id,
	array(
		'site'          => home_url(),
		'post_kind'     => '解説記事',
		'hypothesis'    => '検索意図を補う情報を追加するとクリックが増える。',
		'conclusion'    => 'FAQと内部リンクの追加をほかの記事でも検証する。',
		'total_count'   => 2,
		'success_count' => 1,
		'status'        => 'verifying',
		'action'        => 'try_others',
		'evaluations'   => array_slice( $evaluation_ids, 1, 2 ),
	)
);

WP_CLI::success(
	sprintf(
		'Seeded site strategy, 1 target article, %1$d tasks, %2$d evaluations, and 1 insight.',
		count( $task_ids ),
		count( $evaluation_ids )
	)
);
