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
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/codi0/fstage@0.0.2/fstage.min.css">
	<script defer src="https://cdn.jsdelivr.net/gh/codi0/fstage@0.0.2/fstage.min.js"></script>
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