<?php

$tpl->extend('layout');

$tpl->start('main');

echo '[register]';

echo '<div class="links">';
echo '<div class="login"><a href="' . $tpl->url('login') . '">I already have an account</a></div>';
echo '</div>';

$tpl->stop();