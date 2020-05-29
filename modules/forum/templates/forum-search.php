<section class="forum search">
	[search name="forums" placeholder="Search community discussions"]
</section>

<section class="forum list">
	<h2><?= $tpl['meta.title'] ?: 'Latest discussions' ?></h2>
	<a class="add" href="<?= $tpl['url(forum/add)'] ?>" title="Add new topic">+</a>
	<div class="topics">
	<?php foreach($tpl['topics'] as $topic) { ?>
		<div class="topic">
			<div class="about">
				<div class="title">
					<a href="<?= $topic['url(forum/topic/$id)'] ?>"><?= $topic['title'] ?></a>
				</div>
				<div class="author meta">
					<a class="alt" href="<?= $topic['url(profile/$author_id)'] ?>"><?= $topic['author_name|ucfirst'] ?></a>
					&middot;
					<a class="alt" href="<?= $topic['url(forum/$category_id)'] ?>"><?= $topic['category_name'] ?></a>
					<span class="replies alt">&middot; <?= $topic['reply_num'] . ($topic['reply_num'] == 1 ? ' reply' : ' replies') ?></span>
				</div>
			</div>
			<div class="activity meta">
				<div class="when">
					<a href="<?= $topic['url(forum/topic/$id/latest)'] ?>"><?= $topic['last_msg_time|relativeTime'] ?></a>
				</div>
				<div class="who">
					<a class="alt" href="<?= $topic['url(profile/$last_msg_author_id)'] ?>"><?= $topic['last_msg_author_name|ucfirst'] ?></a>
				</div>
			</div>
		</div>
	<?php } if(!$tpl['topics']) { ?>
		<p class="no-results">No discussions found, please try a different search.</p>
	<?php } ?>
	</div>
	<?php
	if($tpl['meta.page_total'] > 1) {
		echo $tpl->html('pagination', $tpl['meta.page'], $tpl['meta.page_total']);
	}
	?>
</section>