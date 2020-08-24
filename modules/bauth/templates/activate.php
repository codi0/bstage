<section class="login">

	<h2>Activate account</h2>

	<div class="message">
	<?php if($tpl['activated']) { ?>
		Thank you, your email has been successfully verified.
	<?php } else { ?>
		This activation link has expired.
	<?php } ?>
	</div>

	<div class="links">
		<div class="account">
			<a href="<?= $tpl->url('account') ?>">Go to my account</a>
		</div>
	</div>

</div>