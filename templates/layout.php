<!DOCTYPE html>
<html lang="{{ page.lang|ifEmpty(en) }}">
<head>
	<meta charset="{{ page.charset|ifEmpty(utf-8) }}">
	<title><?= $tpl->block('title'); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<base href="<?= $tpl->url('/'); ?>/">
	<link rel="canonical" href="<?= $tpl->url(null, []); ?>">
	<?= $tpl->block('meta'); ?>
</head>
<body>
	<div id="wrap">
		<header>
			<?= $tpl->block('header'); ?>
		</header>
		<main>
			<?= $tpl->block('main'); ?>
		</main>
		<footer>
			<?= $tpl->block('footer'); ?>
		</footer>
	</div>
</body>
</html>