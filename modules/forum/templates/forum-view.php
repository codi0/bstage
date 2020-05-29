<section class="forum topic">
	<p><a href="<?= $tpl->url('forum') ?>">&laquo; Back to results</a></p>
	<h2><?= $tpl['topic.title'] ?></h2>
	<p class="meta">
		<a class="alt" href="<?= $tpl['url(forum/' . $tpl['topic.category.id'] . ')']; ?>"><?= $tpl['topic.category.name'] ?></a>
		&middot;
		<a href="<?= $tpl['url()'] ?>#reply">Add reply</a>
	</p>
	<div class="messages">
	<?php foreach($tpl['topic.messages'] as $key => $message) { ?>
		<div class="message"<?= ($key == ($tpl['topic.messages|count']-1) ? ' id="last"' : '') ?>>
			<div class="meta">
				<div class="author">
					<a href="<?= $message['url(profile/$user.id)'] ?>"><?= $message['user.username|ucfirst'] ?></a>
				</div>
				<div class="time">
					<?= $message['dateCreated|relativeTime'] ?>
				</div>
				<?php if($message['user.avatar']) { ?>
				<div class="avatar">
					<img src="<?= $message['user.avatar'] ?>" alt="">
				</div>
				<?php } ?>
			</div>
			<div class="text">
				<?= str_replace("\n", "\n<br>\n", $message['text']) ?>
			</div>
		</div>
	<?php } ?>
	<?php
	if($tpl['meta.page_total'] > 1) {
		echo $tpl->html('pagination', $tpl['meta.page'], $tpl['meta.page_total']);
	}
	?>
	</div>
	<div id="reply" class="reply">
		<h3>Add reply</h3>
		[forum-reply topic="<?= $tpl['topic.id'] ?>"]
	</div>
</section>