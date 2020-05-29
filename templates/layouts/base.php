<!DOCTYPE html>
<html lang="<?= $tpl['meta.lang'] ?: 'en' ?>">
<head>
	<base href="<?= $tpl->url('/') ?>/">
	<meta charset="<?= $tpl['meta.charset'] ?: 'utf-8' ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $tpl->block('title') ?></title>
	<?php if($tpl['meta.noindex']) { ?>
	<meta name="robots" content="noindex">
	<?php } else { ?>
	<link rel="canonical" href="<?= $tpl['meta.canonical'] ?: $tpl->url(null, []) ?>">
	<?php } ?>
	<?= $tpl->asset('css/skeleton.css'); ?>
	<?= $tpl->block('meta') ?>
</head>
<body>
	<header>
		<?= $tpl->block('header') ?>
	</header>
	<main>
		<?= $tpl->block('main') ?>
	</main>
	<footer>
		<?= $tpl->block('footer') ?>
	</footer>
	<script>
		document.querySelector('main').style.paddingBottom = (document.querySelector('footer').offsetHeight + 40) + 'px';
	</script>
</body>
</html>
<body>
	<header>
		<?= $tpl->block('header') ?>
	</header>
	<main>
		<?= $tpl->block('main') ?>
	</main>
	<footer>
		<?= $tpl->block('footer') ?>
	</footer>
	<script>
		document.querySelector('main').style.paddingBottom = (document.querySelector('footer').offsetHeight + 40) + 'px';
	</script>
</body>
</html>