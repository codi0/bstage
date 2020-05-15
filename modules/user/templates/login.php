<?php

$tpl->extend('layout');

$tpl->start('main');

echo '[message]';

echo '[login one="1" two=2 ]';

echo '<div class="links">';
echo '<div class="register"><a href="' . $tpl->url('register') . '">Create a new account</a></div>';
echo '<div class="forgot"><a href="' . $tpl->url('forgot') . '">I forgot my password</a></div>';
echo '</div>';

$tpl->stop();