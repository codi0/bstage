<?php

$tpl->extend('layout');

$tpl->start('main');

echo '<div class="message">';
if($tpl{'activated'}) {
	echo 'Thank you, your email has been successfully verified.';
} else {
	echo 'This activation link has expired.';
}
echo '</div>';

echo '<div class="links">';
echo '<div class="account"><a href="' . $tpl->url('account') . '">Go to my account</a></div>';
echo '</div>';

$tpl->stop();